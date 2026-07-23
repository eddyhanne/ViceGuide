<?php
/*
 * Datenbank-Eintraege-API fuer ViceGuide (Charaktere, Fahrzeuge, Waffen, ...).
 *
 * GET                  -> alle Eintraege, gruppiert nach section (wie database.json)
 * POST {section,name,...} -> neuen Eintrag anlegen (Admin, sofort live, kein
 *                         Entwurf, analog zu articles.php), gibt die neue
 *                         interne _id zurueck
 * PUT {id,...}         -> Aenderung als Entwurf speichern (Admin), id ist die
 *                         interne Datenbank-Zeilen-ID (_id im GET-Ergebnis)
 * POST ?action=publish -> alle offenen Entwuerfe veroeffentlichen (Admin)
 * POST ?action=discard -> alle offenen Entwuerfe verwerfen, ohne sie zu
 *                         veroeffentlichen (Admin)
 * DELETE {id}          -> Eintrag loeschen (Admin)
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

[$pdo, $cfg] = vg_db();
$method = $_SERVER['REQUEST_METHOD'];

/* Full-Page-Cache leeren bei oeffentlicher Aenderung (POST/DELETE), nicht bei
   reinem Entwurf (PUT). Siehe articles.php fuer die Begruendung. */
require __DIR__ . '/../cache.php';
if ($method === 'POST' || $method === 'DELETE') {
    register_shutdown_function(function () {
        $c = http_response_code();
        if ($c >= 200 && $c < 300) vg_cache_flush();
    });
}

function vg_out3($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function vg_body3(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

/* $full=true liefert das echte Base64-Bild (fuer Backups/Bearbeiten), sonst
   nur eine schlanke, abrufbare Bild-URL, siehe gleiche Begruendung wie in
   articles.php (PageSpeed: ueber 9 MB Netzwerklast beim Startseiten-Laden).
   &v=<updated_at> ist ein reiner Cache-Buster, siehe articles.php fuer die
   ausfuehrliche Begruendung (sonst zeigt ein Browser mit gefuelltem Cache
   nach einem geaenderten Bild noch tagelang das alte Bild unter derselben
   URL an). */
function vg_rowToEntry(array $r, bool $full = false): array {
    $hasImg = !empty($r['img']);
    $out = [
        '_id'  => (int)$r['id'],
        'slug' => $r['slug'] ?: null,
        'name' => $r['name'],
        'sub'  => $r['sub'] ?: null,
        'cat'  => $r['cat'] ?: null,
        'src'  => $r['src'] ?: null,
        'desc' => $r['description'] ?: null,
        'img'  => $hasImg ? ($full ? $r['img'] : ('api/entry_image.php?id=' . (int)$r['id'] . '&v=' . urlencode((string)($r['updated_at'] ?? '')))) : null,
        'seo'  => !empty($r['seo_index']),
    ];
    if ($r['fields_json']) $out['fields'] = json_decode($r['fields_json'], true);
    if ($r['imgfit_json']) $out['imgfit'] = json_decode($r['imgfit_json'], true);
    if ($r['credit']) $out['credit'] = $r['credit'];
    return $out;
}

/* Schreibt ein Feld-Set (clientseitiges JSON-Format) in die echten,
   oeffentlichen Spalten eines Eintrags. Genutzt vom bisherigen direkten
   Speichern und beim Veroeffentlichen eines Entwurfs, siehe articles.php
   fuer die identische Begruendung/Funktionsweise bei Artikeln. */
function vg_writeEntryFields(PDO $pdo, int $id, array $d, bool $clearDraft): void {
    $fieldsMap = ['name' => 'name', 'sub' => 'sub', 'cat' => 'cat', 'src' => 'src', 'credit' => 'credit'];
    $sets = []; $vals = [];
    foreach ($fieldsMap as $jsonKey => $col) {
        if (array_key_exists($jsonKey, $d)) { $sets[] = "$col = ?"; $vals[] = $d[$jsonKey]; }
    }
    if (array_key_exists('desc', $d)) { $sets[] = 'description = ?'; $vals[] = $d['desc']; }
    if (array_key_exists('fields', $d)) { $sets[] = 'fields_json = ?'; $vals[] = $d['fields'] ? json_encode($d['fields'], JSON_UNESCAPED_UNICODE) : null; }
    if (array_key_exists('img', $d)) { $sets[] = 'img = ?'; $vals[] = $d['img']; }
    if (array_key_exists('imgfit', $d)) { $sets[] = 'imgfit_json = ?'; $vals[] = $d['imgfit'] ? json_encode($d['imgfit'], JSON_UNESCAPED_UNICODE) : null; }
    if (array_key_exists('seo', $d)) { $sets[] = 'seo_index = ?'; $vals[] = !empty($d['seo']) ? 1 : 0; }
    if (!$sets) return;
    $sets[] = 'updated_at = CURRENT_TIMESTAMP';
    if ($clearDraft) { $sets[] = 'draft_json = NULL'; }
    $vals[] = $id;
    $pdo->prepare('UPDATE db_entries SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
}

if ($method === 'GET') {
    vg_ensure_entry_slugs($pdo);
    $full = !empty($_GET['full']);
    if ($full) vg_require_admin($cfg);
    $admin = vg_is_admin();
    // Anonyme Besucher: kurz cachebar, siehe articles.php fuer die Begruendung.
    if (!$admin) {
        header('Cache-Control: public, max-age=120, stale-while-revalidate=600');
        header('Vary: Cookie');
    }
    $rows = $pdo->query('SELECT * FROM db_entries ORDER BY section, sort_order, id')->fetchAll();
    $grouped = [];
    foreach ($rows as $r) {
        $entry = vg_rowToEntry($r, $full);
        /* Entwurfsmodus, siehe articles.php fuer die ausfuehrliche
           Begruendung: ein eingeloggter Admin sieht seine eigenen, noch
           nicht veroeffentlichten Aenderungen sofort, andere Besucher
           weiterhin nur den zuletzt veroeffentlichten Stand. */
        if ($admin && !empty($r['draft_json'])) {
            $draft = json_decode($r['draft_json'], true);
            if (is_array($draft)) { $entry = array_merge($entry, $draft); $entry['_draft'] = true; }
        }
        $grouped[$r['section']][] = $entry;
    }
    vg_out3(['sections' => $grouped]);
}

if ($method === 'POST' && ($_GET['action'] ?? '') === 'publish') {
    vg_require_admin($cfg);
    $rows = $pdo->query("SELECT id, draft_json FROM db_entries WHERE draft_json IS NOT NULL AND draft_json <> ''")->fetchAll();
    foreach ($rows as $r) {
        $d = json_decode($r['draft_json'], true);
        if (is_array($d)) vg_writeEntryFields($pdo, (int)$r['id'], $d, true);
    }
    vg_out3(['ok' => true, 'published' => count($rows)]);
}

if ($method === 'POST' && ($_GET['action'] ?? '') === 'discard') {
    vg_require_admin($cfg);
    $n = $pdo->exec("UPDATE db_entries SET draft_json = NULL WHERE draft_json IS NOT NULL AND draft_json <> ''");
    vg_out3(['ok' => true, 'discarded' => $n]);
}

if ($method === 'POST') {
    vg_require_admin($cfg);
    $b = vg_body3();
    $validSections = ['characters', 'vehicles', 'weapons', 'wildlife', 'gangs', 'radio', 'activities', 'locations', 'brands', 'soundtrack'];
    $section = trim($b['section'] ?? '');
    $name = trim($b['name'] ?? '');
    if (!in_array($section, $validSections, true)) vg_out3(['error' => 'ungueltige section'], 400);
    if ($name === '') vg_out3(['error' => 'name erforderlich'], 400);

    $maxOrder = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) m FROM db_entries WHERE section = ?');
    $maxOrder->execute([$section]);
    $sortOrder = (int)$maxOrder->fetch()['m'] + 1;

    $stmt = $pdo->prepare('INSERT INTO db_entries (section, sort_order, name, sub, cat, src, description, fields_json, img, imgfit_json, credit, seo_index)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $section,
        $sortOrder,
        $name,
        $b['sub'] ?? null,
        $b['cat'] ?? null,
        $b['src'] ?? null,
        $b['desc'] ?? null,
        isset($b['fields']) ? json_encode($b['fields'], JSON_UNESCAPED_UNICODE) : null,
        $b['img'] ?? null,
        isset($b['imgfit']) ? json_encode($b['imgfit'], JSON_UNESCAPED_UNICODE) : null,
        $b['credit'] ?? null,
        array_key_exists('seo', $b) ? (!empty($b['seo']) ? 1 : 0) : 0,
    ]);
    $id = (int)$pdo->lastInsertId();
    vg_ensure_entry_slugs($pdo);
    vg_out3(['ok' => true, '_id' => $id], 201);
}

if ($method === 'PUT') {
    vg_require_admin($cfg);
    $b = vg_body3();
    $id = (int)($b['id'] ?? 0);
    if (!$id) vg_out3(['error' => 'id erforderlich'], 400);

    $check = $pdo->prepare('SELECT draft_json FROM db_entries WHERE id = ?');
    $check->execute([$id]);
    $row = $check->fetch();
    if (!$row) vg_out3(['error' => 'Eintrag nicht gefunden'], 404);

    /* Schreibt bewusst NICHT mehr direkt in die oeffentlichen Spalten,
       sondern sammelt Aenderungen in draft_json, bis der Editiermodus mit
       "Fertigstellen" beendet wird, siehe articles.php fuer die identische
       Begruendung. Ein bereits bestehender Entwurf wird Feld fuer Feld
       ergaenzt, nicht komplett ersetzt. */
    $draft = $row['draft_json'] ? (json_decode($row['draft_json'], true) ?: []) : [];
    $allowed = ['name','sub','cat','src','credit','desc','fields','img','imgfit','seo'];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $b)) { $draft[$key] = $b[$key]; }
    }
    if (!$draft) vg_out3(['error' => 'keine Felder zum Aktualisieren']);

    $pdo->prepare('UPDATE db_entries SET draft_json = ? WHERE id = ?')
        ->execute([json_encode($draft, JSON_UNESCAPED_UNICODE), $id]);
    vg_out3(['ok' => true, 'draft' => true]);
}

if ($method === 'DELETE') {
    vg_require_admin($cfg);
    $b = vg_body3();
    $id = (int)($b['id'] ?? 0);
    if (!$id) vg_out3(['error' => 'id erforderlich'], 400);

    $stmt = $pdo->prepare('DELETE FROM db_entries WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) vg_out3(['error' => 'Eintrag nicht gefunden, nichts geloescht'], 404);
    vg_out3(['ok' => true]);
}

vg_out3(['error' => 'Methode nicht unterstuetzt'], 405);
