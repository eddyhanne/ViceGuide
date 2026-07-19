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

/* Speichert die geparsten Zeilen einer Art (page|query) und ersetzt den
   vorherigen Stand komplett. */
function vg_gsc_store(PDO $pdo, array $cfg, string $kind, array $rows, ?string $range): void {
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM gsc_rows WHERE kind = ?')->execute([$kind]);
        $ins = $pdo->prepare('INSERT INTO gsc_rows (kind, label, clicks, impressions, ctr, position) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($rows as $r) { $ins->execute([$kind, $r[0], $r[1], $r[2], $r[3], $r[4]]); }
        $pdo->prepare('DELETE FROM gsc_meta WHERE kind = ?')->execute([$kind]);
        if (str_starts_with($cfg['db_dsn'], 'sqlite:')) {
            $pdo->prepare('INSERT INTO gsc_meta (kind, range_label, imported_at) VALUES (?, ?, ?)')->execute([$kind, $range, date('Y-m-d H:i:s')]);
        } else {
            $pdo->prepare('INSERT INTO gsc_meta (kind, range_label) VALUES (?, ?)')->execute([$kind, $range]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/* Aus dem Search-Console-Zip die richtigen zwei CSVs herausfinden, ohne auf
   die (lokalisierten) Dateinamen angewiesen zu sein: Die Seiten-CSV hat URLs
   in der ersten Spalte, die Suchanfragen-CSV ist die uebrige KPI-Tabelle mit
   den meisten Zeilen (Laender, Geraete, Darstellung werden per Namensmuster
   ausgeschlossen). Gibt [pagesRows|null, queriesRows|null] zurueck. */
function vg_gsc_from_zip(string $bin): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('no-zip-ext');
    $tmp = tempnam(sys_get_temp_dir(), 'gsc');
    file_put_contents($tmp, $bin);
    $za = new ZipArchive();
    if ($za->open($tmp) !== true) { @unlink($tmp); throw new RuntimeException('zip-unreadable'); }
    $csvs = [];
    for ($i = 0; $i < $za->numFiles; $i++) {
        $name = $za->getNameIndex($i);
        if (preg_match('/\.csv$/i', $name)) $csvs[$name] = $za->getFromIndex($i);
    }
    $za->close();
    @unlink($tmp);

    $pages = null; $cand = [];
    foreach ($csvs as $name => $content) {
        $rows = vg_parse_gsc_csv((string)$content);
        if (!$rows) continue;
        $urlish = 0;
        foreach ($rows as $r) { if (preg_match('#^https?://#i', $r[0])) $urlish++; }
        if ($urlish >= max(1, (int)floor(count($rows) * 0.5))) { $pages = $rows; continue; }
        $ln = mb_strtolower(basename($name));
        if (preg_match('/(l(ä|ae)nder|land|countr|pais|paese|ger(ä|ae)t|device|dispositiv|darstellung|appear|aussehen|apparence|filter|filtre|diagram|chart|grafik|datum|date|fecha)/u', $ln)) continue;
        $cand[] = ['name' => $ln, 'rows' => $rows];
    }
    $queries = null;
    foreach ($cand as $c) {
        if (preg_match('/(anfrage|abfrage|quer|query|consultas|requ|zoekop|zapyt|ricerch|consulta)/u', $c['name'])) { $queries = $c['rows']; break; }
    }
    if ($queries === null && $cand) {
        usort($cand, fn($a, $b) => count($b['rows']) - count($a['rows']));
        $queries = $cand[0]['rows'];
    }
    return [$pages, $queries];
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
    $range = trim((string)($b['range'] ?? ''));
    $range = $range !== '' ? mb_substr($range, 0, 120) : null;

    // Variante 1: ganze Search-Console-Zip, Seiten und Suchanfragen automatisch.
    if (isset($b['zip']) && is_string($b['zip']) && $b['zip'] !== '') {
        $raw = $b['zip'];
        if (($p = strpos($raw, ',')) !== false && strncmp($raw, 'data:', 5) === 0) $raw = substr($raw, $p + 1);
        $bin = base64_decode($raw, true);
        if ($bin === false || $bin === '') vg_out_g(['error' => 'Zip nicht lesbar'], 400);
        try {
            [$pages, $queries] = vg_gsc_from_zip($bin);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage() === 'no-zip-ext'
                ? 'Der Server kann Zip-Dateien nicht entpacken. Bitte die einzelnen CSVs (Seiten, Suchanfragen) hochladen.'
                : 'Zip nicht lesbar. Ist es der Original-Export aus der Search Console?';
            vg_out_g(['error' => $msg], 422);
        }
        if (!$pages && !$queries) vg_out_g(['error' => 'In der Zip wurden keine Seiten- oder Suchanfragen-Tabelle erkannt.'], 422);
        try {
            if ($pages) vg_gsc_store($pdo, $cfg, 'page', $pages, $range);
            if ($queries) vg_gsc_store($pdo, $cfg, 'query', $queries, $range);
        } catch (Throwable $e) {
            vg_out_g(['error' => 'Import fehlgeschlagen'], 500);
        }
        vg_out_g(['ok' => true, 'pages' => $pages ? count($pages) : 0, 'queries' => $queries ? count($queries) : 0]);
    }

    // Variante 2: einzelne CSV mit ausdruecklicher Art.
    $kind = ($b['kind'] ?? '') === 'query' ? 'query' : (($b['kind'] ?? '') === 'page' ? 'page' : '');
    if ($kind === '') vg_out_g(['error' => 'kind muss page oder query sein'], 400);
    $csv = (string)($b['csv'] ?? '');
    if (trim($csv) === '') vg_out_g(['error' => 'csv fehlt'], 400);
    $rows = vg_parse_gsc_csv($csv);
    if (!$rows) vg_out_g(['error' => 'Keine gueltigen Zeilen erkannt. Ist es die CSV aus der Search Console (Spalten Klicks, Impressionen, CTR, Position)?'], 422);
    try {
        vg_gsc_store($pdo, $cfg, $kind, $rows, $range);
    } catch (Throwable $e) {
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
