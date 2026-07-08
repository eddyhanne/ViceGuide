<?php
/*
 * EINMAL-WERKZEUG, nach Gebrauch wieder aus dem Repo entfernen (git rm),
 * sonst kommt es beim naechsten Deploy automatisch zurueck.
 *
 * Ueberträgt die eingefrorenen Artikel aus articles.json (Repo-Wurzel) in
 * die echte Datenbank, falls sie dort noch fehlen. Ueberspringt Artikel,
 * deren id schon in der Datenbank existiert, kann also gefahrlos mehrfach
 * aufgerufen werden. Nur mit Admin-Login nutzbar.
 *
 * Aufruf: einmal im Browser oeffnen (eingeloggt im Editiermodus), Ergebnis
 * ablesen, danach diese Datei wieder loeschen.
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

[$pdo, $cfg] = vg_db();
vg_require_admin($cfg);

$jsonPath = __DIR__ . '/../articles.json';
if (!file_exists($jsonPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'articles.json nicht gefunden unter ' . $jsonPath]);
    exit;
}
$articles = json_decode(file_get_contents($jsonPath), true);
if (!is_array($articles)) {
    http_response_code(500);
    echo json_encode(['error' => 'articles.json ist kein gueltiges JSON-Array']);
    exit;
}

function vg_mig_slugify(string $s): string {
    $s = strtr($s, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue','ß'=>'ss']);
    $s = mb_strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'artikel';
}

$check = $pdo->prepare('SELECT COUNT(*) c FROM articles WHERE id = ?');
$insert = $pdo->prepare('INSERT INTO articles (id, cat, title, article_date, summary, meta, lead, content_json, sources_json, img, imgfit_json, credit, author)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');

$imported = [];
$skipped = [];

foreach ($articles as $a) {
    $title = trim($a['title'] ?? '');
    if ($title === '') continue;
    $id = trim($a['id'] ?? '') ?: vg_mig_slugify($title);

    $check->execute([$id]);
    if ((int)$check->fetch()['c'] > 0) {
        $skipped[] = $id;
        continue;
    }

    $insert->execute([
        $id,
        $a['cat'] ?? 'news',
        $title,
        $a['date'] ?? date('Y-m-d\TH:i'),
        $a['summary'] ?? '',
        $a['meta'] ?? '',
        $a['lead'] ?? '',
        json_encode($a['content'] ?? [], JSON_UNESCAPED_UNICODE),
        json_encode($a['sources'] ?? [], JSON_UNESCAPED_UNICODE),
        $a['img'] ?? null,
        isset($a['imgfit']) ? json_encode($a['imgfit'], JSON_UNESCAPED_UNICODE) : null,
        $a['credit'] ?? null,
        $a['author'] ?? null,
    ]);
    $imported[] = $id;
}

echo json_encode([
    'ok' => true,
    'imported_count' => count($imported),
    'imported' => $imported,
    'skipped_count' => count($skipped),
    'skipped' => $skipped,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
