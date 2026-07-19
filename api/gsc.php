<?php
/*
 * Google-Search-Console-Daten per CSV-Upload (leichte Variante ohne OAuth).
 * Der Admin exportiert in der Search Console unter "Leistung" die Tabelle
 * (Seiten bzw. Suchanfragen) als CSV und laedt sie hier hoch. Damit zeigt das
 * Dashboard Impressionen, Klicks, CTR und Position, also genau die Daten, die
 * das eigene First-Party-Tracking prinzipiell nicht sehen kann (Google
 * versteckt die Suchanfrage im Referrer).
 *
 * GET  (Admin) -> {pages:[...], queries:[...], meta:{page:{...}, query:{...}}}
 * POST (Admin) {kind:'page'|'query', csv:'<text>', range?:'...'} -> parst das
 *      CSV und ersetzt die gespeicherten Zeilen dieser Art komplett.
 * DELETE (Admin) {kind?} -> leert alle bzw. nur die eine Art.
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
[$pdo, $cfg] = vg_db();
$method = $_SERVER['REQUEST_METHOD'];

function vg_out_g($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Zahl aus einer GSC-Zelle: entfernt Prozent, Leerzeichen und geschuetzte
   Leerzeichen, wandelt deutsches Dezimalkomma in einen Punkt. */
function vg_gnum($s): ?float {
    $s = trim((string)$s);
    $s = str_replace(['%', ' ', "\xc2\xa0"], '', $s);
    $s = str_replace(',', '.', $s);
    if ($s === '' || !is_numeric($s)) return null;
    return (float)$s;
}

function vg_parse_gsc_csv(string $csv): array {
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv); // BOM
    $lines = preg_split('/\r\n|\r|\n/', $csv);
    $out = [];
    foreach ($lines as $ln) {
        if (trim($ln) === '') continue;
        $c = str_getcsv($ln);
        if (count($c) < 3) continue;
        $label = trim((string)$c[0]);
        $clicks = vg_gnum($c[1] ?? '');
        $impr = vg_gnum($c[2] ?? '');
        if ($label === '' || $clicks === null || $impr === null) continue; // Kopfzeile o.ae.
        $ctr = vg_gnum($c[3] ?? '') ?? 0.0;
        $pos = vg_gnum($c[4] ?? '') ?? 0.0;
        $out[] = [mb_substr($label, 0, 400), (int)round($clicks), (int)round($impr), $ctr, $pos];
    }
    return $out;
}

if ($method === 'GET') {
    vg_require_admin($cfg);
    $fetch = function (string $kind) use ($pdo): array {
        $st = $pdo->prepare('SELECT label, clicks, impressions, ctr, position FROM gsc_rows WHERE kind = ? ORDER BY impressions DESC, clicks DESC LIMIT 200');
        $st->execute([$kind]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    };
    $meta = [];
    foreach ($pdo->query('SELECT kind, range_label, imported_at FROM gsc_meta')->fetchAll() as $m) {
        $meta[$m['kind']] = ['range' => $m['range_label'], 'imported' => $m['imported_at']];
    }
    vg_out_g(['pages' => $fetch('page'), 'queries' => $fetch('query'), 'meta' => $meta]);
}

if ($method === 'POST') {
    vg_require_admin($cfg);
    $b = json_decode(file_get_contents('php://input'), true);
    if (!is_array($b)) $b = [];
    $kind = ($b['kind'] ?? '') === 'query' ? 'query' : (($b['kind'] ?? '') === 'page' ? 'page' : '');
    if ($kind === '') vg_out_g(['error' => 'kind muss page oder query sein'], 400);
    $csv = (string)($b['csv'] ?? '');
    if (trim($csv) === '') vg_out_g(['error' => 'csv fehlt'], 400);
    $rows = vg_parse_gsc_csv($csv);
    if (!$rows) vg_out_g(['error' => 'Keine gueltigen Zeilen erkannt. Ist es die CSV aus der Search Console (Spalten Klicks, Impressionen, CTR, Position)?'], 422);

    $range = trim((string)($b['range'] ?? ''));
    $range = $range !== '' ? mb_substr($range, 0, 120) : null;

    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM gsc_rows WHERE kind = ?');
        $del->execute([$kind]);
        $ins = $pdo->prepare('INSERT INTO gsc_rows (kind, label, clicks, impressions, ctr, position) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($rows as $r) { $ins->execute([$kind, $r[0], $r[1], $r[2], $r[3], $r[4]]); }
        // Meta upsert, portabel ueber delete+insert.
        $pdo->prepare('DELETE FROM gsc_meta WHERE kind = ?')->execute([$kind]);
        $isSqlite = str_starts_with($cfg['db_dsn'], 'sqlite:');
        $now = $isSqlite ? date('Y-m-d H:i:s') : null;
        if ($now !== null) {
            $pdo->prepare('INSERT INTO gsc_meta (kind, range_label, imported_at) VALUES (?, ?, ?)')->execute([$kind, $range, $now]);
        } else {
            $pdo->prepare('INSERT INTO gsc_meta (kind, range_label) VALUES (?, ?)')->execute([$kind, $range]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        vg_out_g(['error' => 'Import fehlgeschlagen'], 500);
    }
    vg_out_g(['ok' => true, 'kind' => $kind, 'rows' => count($rows)]);
}

if ($method === 'DELETE') {
    vg_require_admin($cfg);
    $b = json_decode(file_get_contents('php://input'), true);
    $kind = is_array($b) ? ($b['kind'] ?? '') : '';
    if ($kind === 'page' || $kind === 'query') {
        $pdo->prepare('DELETE FROM gsc_rows WHERE kind = ?')->execute([$kind]);
        $pdo->prepare('DELETE FROM gsc_meta WHERE kind = ?')->execute([$kind]);
    } else {
        $pdo->exec('DELETE FROM gsc_rows');
        $pdo->exec('DELETE FROM gsc_meta');
    }
    vg_out_g(['ok' => true]);
}

vg_out_g(['error' => 'Methode nicht unterstuetzt'], 405);
