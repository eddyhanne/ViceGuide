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

function vg_buildTree(array $rows): array {
    $byId = [];
    foreach ($rows as $r) {
        $r['id'] = (int)$r['id'];
        $r['likes'] = (int)$r['likes'];
        $r['dislikes'] = (int)$r['dislikes'];
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
    $stmt = $pdo->prepare('SELECT id, parent_id, name, text, quote, likes, dislikes, created_at FROM comments WHERE article_id = ? ORDER BY created_at ASC');
    $stmt->execute([$articleId]);
    vg_out(['comments' => vg_buildTree($stmt->fetchAll())]);
}

if ($method === 'POST') {
    $b = vg_body();
    $articleId = trim($b['article'] ?? '');
    $name = mb_substr(trim($b['name'] ?? '') ?: 'Gast', 0, 60);
    $text = mb_substr(trim($b['text'] ?? ''), 0, 800);
    $quote = isset($b['quote']) ? mb_substr(trim((string)$b['quote']), 0, 200) : null;
    $parentId = isset($b['parentId']) && $b['parentId'] ? (int)$b['parentId'] : null;

    if ($articleId === '' || $text === '') vg_out(['error' => 'article und text sind erforderlich'], 400);

    $stmt = $pdo->prepare('INSERT INTO comments (article_id, parent_id, name, text, quote) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$articleId, $parentId, $name, $text, $quote ?: null]);
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
