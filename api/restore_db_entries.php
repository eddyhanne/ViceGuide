<?php
/*
 * EINMAL-WERKZEUG / Sicherheitsnetz, nach Gebrauch wieder aus dem Repo
 * entfernen (git rm), sonst kommt es beim naechsten Deploy automatisch
 * zurueck.
 *
 * Vergleicht database.json (Repo-Wurzel) mit der echten Datenbank und
 * traegt jeden Eintrag nach, der dort (z.B. durch versehentliches
 * Loeschen im Editiermodus) fehlt. Abgleich ueber (section, name), also
 * gefahrlos mehrfach aufrufbar, bereits vorhandene Eintraege werden nie
 * angefasst oder dupliziert. Nur mit Admin-Login nutzbar.
 *
 * Aufruf: einmal im Browser oeffnen (eingeloggt im Editiermodus), Ergebnis
 * ablesen, danach diese Datei wieder loeschen.
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

[$pdo, $cfg] = vg_db();
vg_require_admin($cfg);

$jsonPath = __DIR__ . '/../database.json';
if (!file_exists($jsonPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'database.json nicht gefunden unter ' . $jsonPath]);
    exit;
}
$sections = json_decode(file_get_contents($jsonPath), true);
if (!is_array($sections)) {
    http_response_code(500);
    echo json_encode(['error' => 'database.json ist kein gueltiges JSON-Objekt']);
    exit;
}

$check = $pdo->prepare('SELECT COUNT(*) c FROM db_entries WHERE section = ? AND name = ?');
$insert = $pdo->prepare('INSERT INTO db_entries (section, sort_order, name, sub, cat, src, description, fields_json, img, imgfit_json, credit)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)');

$restored = [];
$skipped_count = 0;

foreach ($sections as $sectionId => $entries) {
    if (!is_array($entries)) continue;
    foreach ($entries as $i => $e) {
        $name = trim($e['name'] ?? '');
        if ($name === '') continue;

        $check->execute([$sectionId, $name]);
        if ((int)$check->fetch()['c'] > 0) {
            $skipped_count++;
            continue;
        }

        $insert->execute([
            $sectionId,
            $i,
            $name,
            $e['sub'] ?? null,
            $e['cat'] ?? null,
            $e['src'] ?? null,
            $e['desc'] ?? null,
            isset($e['fields']) ? json_encode($e['fields'], JSON_UNESCAPED_UNICODE) : null,
            $e['img'] ?? null,
            isset($e['imgfit']) ? json_encode($e['imgfit'], JSON_UNESCAPED_UNICODE) : null,
            $e['credit'] ?? null,
        ]);
        $restored[] = $sectionId . ': ' . $name;
    }
}

echo json_encode([
    'ok' => true,
    'restored_count' => count($restored),
    'restored' => $restored,
    'skipped_count' => $skipped_count,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
