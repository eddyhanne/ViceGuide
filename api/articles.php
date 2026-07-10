<?php
/*
 * Artikel-API fuer ViceGuide.
 *
 * GET                  -> Liste aller Artikel (wie articles.json, aber aus der Datenbank)
 * POST   {...}         -> neuen Artikel anlegen (Admin), gibt die vergebene id zurueck
 * PUT    {id,...}      -> Aenderung als Entwurf speichern (Admin)
 * POST ?action=publish -> alle offenen Entwuerfe veroeffentlichen (Admin)
 * POST ?action=discard -> alle offenen Entwuerfe verwerfen, ohne sie zu
 *                         veroeffentlichen (Admin)
 * DELETE {id}          -> Artikel und seine Kommentare loeschen (Admin)
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

[$pdo, $cfg] = vg_db();
$method = $_SERVER['REQUEST_METHOD'];

function vg_out2($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function vg_body2(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function vg_slugify(string $s): string {
    $s = strtr($s, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue','ß'=>'ss']);
    $s = mb_strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'artikel';
}

/* $full=true liefert das echte Base64-Bild (fuer Backups/Bearbeiten), sonst
   nur eine schlanke, abrufbare Bild-URL. Grund: die Startseite laedt sonst
   bei jedem Besuch alle Artikelbilder aller Artikel auf einmal mit (PageSpeed
   bemaengelte dadurch eine Netzwerklast von ueber 9 MB), obwohl nur wenige
   Bilder tatsaechlich sichtbar sind. Echtes Bild und Bild-URL funktionieren
   fuer <img src>/CSS background-image identisch, daher keine Aenderung an der
   Anzeige-Logik noetig, siehe CLAUDE.md.
   Die URL traegt zusaetzlich &v=<updated_at>, reiner Cache-Buster: der
   Browser cached api/article_image.php mit langer Lebensdauer (siehe dort),
   ohne den Versions-Anhang wuerde ein neues Bild unter derselben URL bei
   Besuchern mit bereits gefuelltem Cache erst nach Ablauf der Cache-Zeit
   ankommen, obwohl der Server laengst das neue Bild ausliefert. */
function vg_rowToArticle(array $r, bool $full = false): array {
    $hasImg = !empty($r['img']);
    return [
        'id'      => $r['id'],
        'cat'     => $r['cat'],
        'title'   => $r['title'],
        'date'    => $r['article_date'],
        'summary' => $r['summary'],
        'meta'    => $r['meta'],
        'lead'    => $r['lead'],
        'content' => json_decode($r['content_json'] ?? '[]', true) ?: [],
        'sources' => json_decode($r['sources_json'] ?? '[]', true) ?: [],
        'img'     => $hasImg ? ($full ? $r['img'] : ('api/article_image.php?id=' . urlencode($r['id']) . '&v=' . urlencode((string)($r['updated_at'] ?? '')))) : null,
        'imgfit'  => $r['imgfit_json'] ? json_decode($r['imgfit_json'], true) : null,
        'credit'  => $r['credit'] ?: null,
        'author'  => $r['author'] ?: null,
    ];
}

/* Schreibt ein Feld-Set (im clientseitigen JSON-Format, also z.B. "content"
   statt "content_json") in die echten, oeffentlichen Spalten eines Artikels.
   Wird sowohl vom bisherigen direkten Speichern als auch beim Veroeffentlichen
   eines Entwurfs genutzt (siehe PUT und POST ?action=publish). Nur Felder, die
   im uebergebenen Array tatsaechlich vorhanden sind, werden angefasst (auch
   ein expliziter null-Wert, z.B. ein entferntes Bild, wird dabei geschrieben),
   nicht vorhandene Felder bleiben unveraendert. */
function vg_writeArticleFields(PDO $pdo, string $id, array $d, bool $clearDraft): void {
    $fieldsMap = [
        'cat' => 'cat', 'title' => 'title', 'date' => 'article_date', 'summary' => 'summary',
        'meta' => 'meta', 'lead' => 'lead', 'credit' => 'credit', 'author' => 'author',
    ];
    $sets = []; $vals = [];
    foreach ($fieldsMap as $jsonKey => $col) {
        if (array_key_exists($jsonKey, $d)) { $sets[] = "$col = ?"; $vals[] = $d[$jsonKey]; }
    }
    if (array_key_exists('content', $d)) { $sets[] = 'content_json = ?'; $vals[] = json_encode($d['content'], JSON_UNESCAPED_UNICODE); }
    if (array_key_exists('sources', $d)) { $sets[] = 'sources_json = ?'; $vals[] = json_encode($d['sources'], JSON_UNESCAPED_UNICODE); }
    if (array_key_exists('img', $d)) { $sets[] = 'img = ?'; $vals[] = $d['img']; }
    if (array_key_exists('imgfit', $d)) { $sets[] = 'imgfit_json = ?'; $vals[] = $d['imgfit'] ? json_encode($d['imgfit'], JSON_UNESCAPED_UNICODE) : null; }
    if (!$sets) return;
    $sets[] = 'updated_at = CURRENT_TIMESTAMP';
    if ($clearDraft) { $sets[] = 'draft_json = NULL'; }
    $vals[] = $id;
    $pdo->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
}

if ($method === 'GET') {
    $full = !empty($_GET['full']);
    if ($full) vg_require_admin($cfg);
    $admin = vg_is_admin();
    $rows = $pdo->query('SELECT * FROM articles ORDER BY article_date DESC')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $article = vg_rowToArticle($r, $full);
        /* Entwurfsmodus: ein eingeloggter Admin sieht seine eigenen, noch
           nicht veroeffentlichten Aenderungen sofort (auch nach einem
           Reload), alle anderen Besucher weiterhin nur den zuletzt
           veroeffentlichten Stand. Das Bild im Entwurf ist absichtlich das
           echte Base64-Bild, nicht die schlanke URL, es ist ja nur fuer die
           eigene Vorschau bestimmt. */
        if ($admin && !empty($r['draft_json'])) {
            $draft = json_decode($r['draft_json'], true);
            if (is_array($draft)) { $article = array_merge($article, $draft); $article['_draft'] = true; }
        }
        $out[] = $article;
    }
    vg_out2(['articles' => $out]);
}

if ($method === 'POST' && ($_GET['action'] ?? '') === 'publish') {
    vg_require_admin($cfg);
    $rows = $pdo->query("SELECT id, draft_json FROM articles WHERE draft_json IS NOT NULL AND draft_json <> ''")->fetchAll();
    foreach ($rows as $r) {
        $d = json_decode($r['draft_json'], true);
        if (is_array($d)) vg_writeArticleFields($pdo, $r['id'], $d, true);
    }
    vg_out2(['ok' => true, 'published' => count($rows)]);
}

if ($method === 'POST' && ($_GET['action'] ?? '') === 'discard') {
    vg_require_admin($cfg);
    $n = $pdo->exec("UPDATE articles SET draft_json = NULL WHERE draft_json IS NOT NULL AND draft_json <> ''");
    vg_out2(['ok' => true, 'discarded' => $n]);
}

if ($method === 'POST') {
    vg_require_admin($cfg);
    $b = vg_body2();
    $title = trim($b['title'] ?? '');
    if ($title === '') vg_out2(['error' => 'title erforderlich'], 400);

    $id = trim($b['id'] ?? '') ?: vg_slugify($title);
    $base = $id; $n = 2;
    $check = $pdo->prepare('SELECT COUNT(*) c FROM articles WHERE id = ?');
    while (true) {
        $check->execute([$id]);
        if ((int)$check->fetch()['c'] === 0) break;
        $id = $base . '-' . $n; $n++;
    }

    $stmt = $pdo->prepare('INSERT INTO articles (id, cat, title, article_date, summary, meta, lead, content_json, sources_json, img, imgfit_json, credit, author)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $id,
        $b['cat'] ?? 'news',
        $title,
        $b['date'] ?? date('Y-m-d\TH:i'),
        $b['summary'] ?? '',
        $b['meta'] ?? '',
        $b['lead'] ?? '',
        json_encode($b['content'] ?? [], JSON_UNESCAPED_UNICODE),
        json_encode($b['sources'] ?? [], JSON_UNESCAPED_UNICODE),
        $b['img'] ?? null,
        isset($b['imgfit']) ? json_encode($b['imgfit'], JSON_UNESCAPED_UNICODE) : null,
        $b['credit'] ?? null,
        $b['author'] ?? null,
    ]);
    vg_out2(['ok' => true, 'id' => $id], 201);
}

if ($method === 'PUT') {
    vg_require_admin($cfg);
    $b = vg_body2();
    $id = trim($b['id'] ?? '');
    if ($id === '') vg_out2(['error' => 'id erforderlich'], 400);

    $check = $pdo->prepare('SELECT draft_json FROM articles WHERE id = ?');
    $check->execute([$id]);
    $row = $check->fetch();
    if (!$row) vg_out2(['error' => 'Artikel nicht gefunden'], 404);

    /* Schreibt bewusst NICHT mehr direkt in die oeffentlichen Spalten,
       sondern sammelt Aenderungen in draft_json, bis der Editiermodus mit
       "Fertigstellen" beendet wird (siehe POST ?action=publish). So bleibt
       fuer normale Besucher der zuletzt veroeffentlichte Stand sichtbar,
       waehrend der Admin bereits an mehreren Feldern/Bildern arbeitet. Ein
       bereits bestehender Entwurf wird dabei Feld fuer Feld ergaenzt, nicht
       komplett ersetzt, damit mehrere Speichervorgaenge nacheinander (erst
       Bild, spaeter Text) sich nicht gegenseitig ueberschreiben. */
    $draft = $row['draft_json'] ? (json_decode($row['draft_json'], true) ?: []) : [];
    $allowed = ['cat','title','date','summary','meta','lead','credit','author','content','sources','img','imgfit'];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $b)) { $draft[$key] = $b[$key]; }
    }
    if (!$draft) vg_out2(['error' => 'keine Felder zum Aktualisieren']);

    $pdo->prepare('UPDATE articles SET draft_json = ? WHERE id = ?')
        ->execute([json_encode($draft, JSON_UNESCAPED_UNICODE), $id]);
    vg_out2(['ok' => true, 'draft' => true]);
}

if ($method === 'DELETE') {
    vg_require_admin($cfg);
    $b = vg_body2();
    $id = trim($b['id'] ?? '');
    if ($id === '') vg_out2(['error' => 'id erforderlich'], 400);

    $stmt = $pdo->prepare('DELETE FROM articles WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) vg_out2(['error' => 'Artikel nicht gefunden, nichts geloescht'], 404);
    $pdo->prepare('DELETE FROM comments WHERE article_id = ?')->execute([$id]);
    vg_out2(['ok' => true]);
}

vg_out2(['error' => 'Methode nicht unterstuetzt'], 405);
