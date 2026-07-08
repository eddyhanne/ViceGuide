<?php
/*
 * Artikel-API fuer ViceGuide.
 *
 * GET            -> Liste aller Artikel (wie articles.json, aber aus der Datenbank)
 * POST   {...}   -> neuen Artikel anlegen (Admin), gibt die vergebene id zurueck
 * PUT    {id,...}-> bestehenden Artikel aktualisieren (Admin)
 * DELETE {id}    -> Artikel und seine Kommentare loeschen (Admin)
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

function vg_rowToArticle(array $r): array {
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
        'img'     => $r['img'] ?: null,
        'imgfit'  => $r['imgfit_json'] ? json_decode($r['imgfit_json'], true) : null,
        'credit'  => $r['credit'] ?: null,
        'author'  => $r['author'] ?: null,
    ];
}

if ($method === 'GET') {
    $rows = $pdo->query('SELECT * FROM articles ORDER BY article_date DESC')->fetchAll();
    vg_out2(['articles' => array_map('vg_rowToArticle', $rows)]);
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

    $check = $pdo->prepare('SELECT COUNT(*) c FROM articles WHERE id = ?');
    $check->execute([$id]);
    if ((int)$check->fetch()['c'] === 0) vg_out2(['error' => 'Artikel nicht gefunden'], 404);

    $fieldsMap = [
        'cat' => 'cat', 'title' => 'title', 'date' => 'article_date', 'summary' => 'summary',
        'meta' => 'meta', 'lead' => 'lead', 'credit' => 'credit', 'author' => 'author',
    ];
    $sets = []; $vals = [];
    foreach ($fieldsMap as $jsonKey => $col) {
        if (array_key_exists($jsonKey, $b)) { $sets[] = "$col = ?"; $vals[] = $b[$jsonKey]; }
    }
    if (array_key_exists('content', $b)) { $sets[] = 'content_json = ?'; $vals[] = json_encode($b['content'], JSON_UNESCAPED_UNICODE); }
    if (array_key_exists('sources', $b)) { $sets[] = 'sources_json = ?'; $vals[] = json_encode($b['sources'], JSON_UNESCAPED_UNICODE); }
    if (array_key_exists('img', $b)) { $sets[] = 'img = ?'; $vals[] = $b['img']; }
    if (array_key_exists('imgfit', $b)) { $sets[] = 'imgfit_json = ?'; $vals[] = $b['imgfit'] ? json_encode($b['imgfit'], JSON_UNESCAPED_UNICODE) : null; }

    if (!$sets) vg_out2(['error' => 'keine Felder zum Aktualisieren']);

    $vals[] = $id;
    $stmt = $pdo->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($vals);
    vg_out2(['ok' => true]);
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
