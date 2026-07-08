<?php
/*
 * Datenbank-Eintraege-API fuer ViceGuide (Charaktere, Fahrzeuge, Waffen, ...).
 *
 * GET          -> alle Eintraege, gruppiert nach section (wie database.json)
 * PUT {id,...} -> bestehenden Eintrag aktualisieren (Admin), id ist die
 *                 interne Datenbank-Zeilen-ID (_id im GET-Ergebnis)
 * DELETE {id}  -> Eintrag loeschen (Admin)
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

[$pdo, $cfg] = vg_db();
$method = $_SERVER['REQUEST_METHOD'];

function vg_out3($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function vg_body3(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function vg_rowToEntry(array $r): array {
    $out = [
        '_id'  => (int)$r['id'],
        'name' => $r['name'],
        'sub'  => $r['sub'] ?: null,
        'cat'  => $r['cat'] ?: null,
        'src'  => $r['src'] ?: null,
        'desc' => $r['description'] ?: null,
        'img'  => $r['img'] ?: null,
    ];
    if ($r['fields_json']) $out['fields'] = json_decode($r['fields_json'], true);
    if ($r['imgfit_json']) $out['imgfit'] = json_decode($r['imgfit_json'], true);
    if ($r['credit']) $out['credit'] = $r['credit'];
    return $out;
}

if ($method === 'GET') {
    $rows = $pdo->query('SELECT * FROM db_entries ORDER BY section, sort_order, id')->fetchAll();
    $grouped = [];
    foreach ($rows as $r) {
        $grouped[$r['section']][] = vg_rowToEntry($r);
    }
    vg_out3(['sections' => $grouped]);
}

if ($method === 'PUT') {
    vg_require_admin($cfg);
    $b = vg_body3();
    $id = (int)($b['id'] ?? 0);
    if (!$id) vg_out3(['error' => 'id erforderlich'], 400);

    $check = $pdo->prepare('SELECT COUNT(*) c FROM db_entries WHERE id = ?');
    $check->execute([$id]);
    if ((int)$check->fetch()['c'] === 0) vg_out3(['error' => 'Eintrag nicht gefunden'], 404);

    $fieldsMap = ['name' => 'name', 'sub' => 'sub', 'cat' => 'cat', 'src' => 'src', 'credit' => 'credit'];
    $sets = []; $vals = [];
    foreach ($fieldsMap as $jsonKey => $col) {
        if (array_key_exists($jsonKey, $b)) { $sets[] = "$col = ?"; $vals[] = $b[$jsonKey]; }
    }
    if (array_key_exists('desc', $b)) { $sets[] = 'description = ?'; $vals[] = $b['desc']; }
    if (array_key_exists('fields', $b)) { $sets[] = 'fields_json = ?'; $vals[] = $b['fields'] ? json_encode($b['fields'], JSON_UNESCAPED_UNICODE) : null; }
    if (array_key_exists('img', $b)) { $sets[] = 'img = ?'; $vals[] = $b['img']; }
    if (array_key_exists('imgfit', $b)) { $sets[] = 'imgfit_json = ?'; $vals[] = $b['imgfit'] ? json_encode($b['imgfit'], JSON_UNESCAPED_UNICODE) : null; }

    if (!$sets) vg_out3(['error' => 'keine Felder zum Aktualisieren']);

    $vals[] = $id;
    $stmt = $pdo->prepare('UPDATE db_entries SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($vals);
    vg_out3(['ok' => true]);
}

if ($method === 'DELETE') {
    vg_require_admin($cfg);
    $b = vg_body3();
    $id = (int)($b['id'] ?? 0);
    if (!$id) vg_out3(['error' => 'id erforderlich'], 400);

    $stmt = $pdo->prepare('DELETE FROM db_entries WHERE id = ?');
    $stmt->execute([$id]);
    vg_out3(['ok' => true]);
}

vg_out3(['error' => 'Methode nicht unterstuetzt'], 405);
