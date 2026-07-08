<?php
/*
 * Einmal-Werkzeug: importiert die bestehenden articles.json und database.json
 * (liegen im Hauptordner, eine Ebene ueber /api) in die neuen Datenbanktabellen.
 *
 * Aufruf einmalig im Browser: /api/migrate_import.php
 * Laeuft nur, wenn die Tabellen noch leer sind, es sei denn du haengst
 * ?force=1 an (dann werden vorhandene Zeilen zuerst geloescht und neu importiert).
 *
 * Diese Datei danach wieder loeschen (Hostinger Dateimanager), sie soll nicht
 * dauerhaft online erreichbar bleiben.
 */

require __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

[$pdo, $cfg] = vg_db();
$force = isset($_GET['force']);

$root = dirname(__DIR__);
$articlesPath = $root . '/articles.json';
$databasePath = $root . '/database.json';

$artCount = (int)$pdo->query('SELECT COUNT(*) c FROM articles')->fetch()['c'];
$dbCount  = (int)$pdo->query('SELECT COUNT(*) c FROM db_entries')->fetch()['c'];

if (($artCount > 0 || $dbCount > 0) && !$force) {
    echo "Es liegen schon Daten in der Datenbank (articles: $artCount, db_entries: $dbCount).\n";
    echo "Nichts importiert, um keine Duplikate zu erzeugen.\n";
    echo "Falls du wirklich neu importieren willst (bestehende Zeilen werden geloescht): diese Seite mit ?force=1 aufrufen.\n";
    exit;
}

if ($force) {
    $pdo->exec('DELETE FROM articles');
    $pdo->exec('DELETE FROM db_entries');
    echo "Bestehende Zeilen geloescht.\n";
}

$imported = ['articles' => 0, 'db_entries' => 0];

if (file_exists($articlesPath)) {
    $articles = json_decode(file_get_contents($articlesPath), true) ?: [];
    $stmt = $pdo->prepare('INSERT INTO articles (id, cat, title, article_date, summary, meta, lead, content_json, sources_json, img, imgfit_json, credit, author)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($articles as $a) {
        if (empty($a['id'])) continue;
        $stmt->execute([
            $a['id'],
            $a['cat'] ?? 'news',
            $a['title'] ?? '',
            $a['date'] ?? null,
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
        $imported['articles']++;
    }
} else {
    echo "Hinweis: articles.json nicht gefunden, uebersprungen.\n";
}

if (file_exists($databasePath)) {
    $db = json_decode(file_get_contents($databasePath), true) ?: [];
    $stmt = $pdo->prepare('INSERT INTO db_entries (section, sort_order, name, sub, cat, src, description, fields_json, img, imgfit_json, credit)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($db as $section => $entries) {
        $order = 0;
        foreach ($entries as $e) {
            $stmt->execute([
                $section,
                $order++,
                $e['name'] ?? '',
                $e['sub'] ?? null,
                $e['cat'] ?? null,
                $e['src'] ?? null,
                $e['desc'] ?? null,
                isset($e['fields']) ? json_encode($e['fields'], JSON_UNESCAPED_UNICODE) : null,
                $e['img'] ?? null,
                isset($e['imgfit']) ? json_encode($e['imgfit'], JSON_UNESCAPED_UNICODE) : null,
                $e['credit'] ?? null,
            ]);
            $imported['db_entries']++;
        }
    }
} else {
    echo "Hinweis: database.json nicht gefunden, uebersprungen.\n";
}

echo "Fertig. Importiert: {$imported['articles']} Artikel, {$imported['db_entries']} Datenbank-Eintraege.\n";
echo "Diese Datei (migrate_import.php) jetzt bitte wieder loeschen.\n";
