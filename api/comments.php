<?php
/*
 * Kommentar-API fuer ViceGuide.
 *
 * GET    ?article=<id>              -> Liste aller Kommentare zu einem Artikel (als Baum)
 * POST   {article,name,text,parentId?,quote?} -> neuen Kommentar/Antwort anlegen
 * PATCH  {id,dir:"up"|"down"}        -> Stimme fuer einen Kommentar zaehlen
 * DELETE {id,password}               -> Kommentar loeschen, nur mit Admin-Passwort
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

[$pdo, $cfg] = vg_db();
$method = $_SERVER['REQUEST_METHOD'];

function vg_out($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function vg_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function vg_buildTree(array $rows, string $voter = ''): array {
    $byId = [];
    foreach ($rows as $r) {
        $r['id'] = (int)$r['id'];
        $r['likes'] = (int)$r['likes'];
        $r['dislikes'] = (int)$r['dislikes'];
        /* Eigener Kommentar? Vergleich des mitgeschickten Wähler-Tokens mit dem
           beim Anlegen gespeicherten Autor-Token. Das Token selbst wird nie
           ausgeliefert (nur das abgeleitete Flag), damit keine fremden Tokens
           nach aussen sichtbar werden. */
        $r['own'] = ($voter !== '' && !empty($r['author_token']) && hash_equals((string)$r['author_token'], $voter));
        unset($r['author_token']);
        $r['replies'] = [];
        $byId[$r['id']] = $r;
    }
    $roots = [];
    foreach ($byId as $id => &$r) {
        $pid = $r['parent_id'] ? (int)$r['parent_id'] : null;
        if ($pid && isset($byId[$pid])) {
            $byId[$pid]['replies'][] = &$r;
        } else {
            $roots[] = &$r;
        }
    }
    unset($r);
    return $roots;
}

if ($method === 'GET') {
    $articleId = trim($_GET['article'] ?? '');
    if ($articleId === '') vg_out(['error' => 'article fehlt'], 400);
    $voter = substr(trim((string)($_GET['voter'] ?? '')), 0, 100);
    $stmt = $pdo->prepare('SELECT id, parent_id, name, text, quote, author_token, likes, dislikes, created_at FROM comments WHERE article_id = ? ORDER BY created_at ASC');
    $stmt->execute([$articleId]);
    vg_out(['comments' => vg_buildTree($stmt->fetchAll(), $voter)]);
}

if ($method === 'POST') {
    $b = vg_body();
    $articleId = trim($b['article'] ?? '');
    $name = mb_substr(trim($b['name'] ?? '') ?: 'Gast', 0, 60);
    $text = mb_substr(trim($b['text'] ?? ''), 0, 800);
    $quote = isset($b['quote']) ? mb_substr(trim((string)$b['quote']), 0, 200) : null;
    $parentId = isset($b['parentId']) && $b['parentId'] ? (int)$b['parentId'] : null;

    if ($articleId === '' || $text === '') vg_out(['error' => 'article und text sind erforderlich'], 400);

    /* Anonymes Wähler-Token als Autor-Kennung merken, damit man den eigenen
       Kommentar spaeter nicht selbst bewerten kann (siehe PATCH und GET own). */
    $author = substr(trim((string)($b['voter'] ?? '')), 0, 100) ?: null;
    $stmt = $pdo->prepare('INSERT INTO comments (article_id, parent_id, name, text, quote, author_token) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$articleId, $parentId, $name, $text, $quote ?: null, $author]);
    vg_out(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
}

if ($method === 'PATCH') {
    $b = vg_body();
    $id = (int)($b['id'] ?? 0);
    $dir = $b['dir'] ?? '';
    if (!$id || !in_array($dir, ['up', 'down'], true)) vg_out(['error' => 'id und dir (up/down) erforderlich'], 400);
    $col = $dir === 'up' ? 'likes' : 'dislikes';

    /* Eine Stimme pro Wähler pro Kommentar, serverseitig erzwungen. "Wähler"
       ist ein anonymes, dauerhaftes Browser-Token (localStorage vg-voter, vom
       Client mitgeschickt). Fehlt es (z.B. direkter API-Aufruf), fällt der
       Wähler auf einen IP-Hash zurück, damit auch dann nicht beliebig oft
       abgestimmt werden kann. Der reine localStorage-Check im Client war
       trivial umgehbar (Speicher leeren, anderer Browser, curl). */
    $voter = trim((string)($b['voter'] ?? ''));
    if ($voter === '') {
        $voter = 'ip:' . substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($cfg['admin_hash'] ?? '')), 0, 40);
    }
    $voter = substr($voter, 0, 100);

    /* Eigenen Kommentar nicht selbst bewerten. Autor-Token beim Anlegen
       gespeichert (POST), hier gegen den abstimmenden Wähler geprüft. */
    $own = $pdo->prepare('SELECT likes, dislikes, author_token FROM comments WHERE id = ?');
    $own->execute([$id]);
    $ownRow = $own->fetch();
    if (!$ownRow) vg_out(['error' => 'Kommentar nicht gefunden'], 404);
    if (!empty($ownRow['author_token']) && hash_equals((string)$ownRow['author_token'], $voter)) {
        vg_out(['ok' => true, 'own' => true, 'likes' => (int)$ownRow['likes'], 'dislikes' => (int)$ownRow['dislikes']]);
    }

    $chk = $pdo->prepare('SELECT 1 FROM comment_votes WHERE comment_id = ? AND voter = ? LIMIT 1');
    $chk->execute([$id, $voter]);
    $already = (bool)$chk->fetchColumn();

    if (!$already) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO comment_votes (comment_id, voter, dir) VALUES (?,?,?)')
                ->execute([$id, $voter, $dir]);
            $pdo->prepare("UPDATE comments SET $col = $col + 1 WHERE id = ?")->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            /* Unique-Verletzung durch parallele Klicks (Race): als "schon
               abgestimmt" behandeln, nicht doppelt zählen. */
            if ($pdo->inTransaction()) $pdo->rollBack();
            $already = true;
        }
    }

    $cur = $pdo->prepare('SELECT likes, dislikes FROM comments WHERE id = ?');
    $cur->execute([$id]);
    $row = $cur->fetch();
    if (!$row) vg_out(['error' => 'Kommentar nicht gefunden'], 404);
    vg_out(['ok' => true, 'already' => $already, 'likes' => (int)$row['likes'], 'dislikes' => (int)$row['dislikes']]);
}

if ($method === 'DELETE') {
    vg_require_admin($cfg);
    $b = vg_body();
    $id = (int)($b['id'] ?? 0);
    if (!$id) vg_out(['error' => 'id erforderlich'], 400);
    $stmt = $pdo->prepare('DELETE FROM comments WHERE id = ?');
    $stmt->execute([$id]);
    vg_out(['ok' => true]);
}

vg_out(['error' => 'Methode nicht unterstuetzt'], 405);
