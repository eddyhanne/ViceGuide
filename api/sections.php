<?php
/*
 * Rubrik-Bilder fuer ViceGuide (Portal-Kacheln "GTA-6-Datenbank").
 *
 * GET               -> alle Rubrik-Bilder als schlanke Bild-URLs (fuer die
 *                      Anzeige), mit ?full=1 die echten Base64-Bilder (Admin,
 *                      fuer den Bild-Editor).
 * PUT {section,...} -> Bild/Zuschnitt/Quelle einer Rubrik setzen (Admin).
 *                      Sofort oeffentlich (kein Entwurf), analog zum Anpinnen:
 *                      ein Rubrik-Bild ist Kuration, kein Artikelinhalt.
 *
 * Die acht Datenbank-Rubriken sind ansonsten nur JS-Konstanten (SECTIONS in
 * index.html) ohne eigenen Datenbank-Datensatz, section_meta ist der einzige
 * Speicher fuer ihre Bilder.
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
[$pdo, $cfg] = vg_db();
$method = $_SERVER['REQUEST_METHOD'];

require __DIR__ . '/../cache.php';
if ($method === 'PUT') {
    register_shutdown_function(function () {
        $c = http_response_code();
        if ($c >= 200 && $c < 300) vg_cache_flush();
    });
}

function vg_out_s($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function vg_body_s(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

// Gueltige Sektionen fuer Rubrik-Bilder: die Datenbank-Rubriken (inkl. der Juli
// 2026 ergaenzten Rubrik "brands") plus die gesperrten Phase-2-Guide-Kacheln.
const VG_IMG_SECTIONS = ['characters','vehicles','weapons','wildlife','gangs','radio','activities','locations','brands','money','missions','tips','online','secrets','collect','veh','weap','trophies','beginner','customization','business'];

if ($method === 'GET') {
    $full = !empty($_GET['full']);
    if ($full) vg_require_admin($cfg);
    $rows = $pdo->query('SELECT section, img, imgfit_json, credit, updated_at FROM section_meta')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $hasImg = !empty($r['img']);
        $out[$r['section']] = [
            'img'    => $hasImg ? ($full ? $r['img'] : ('api/section_image.php?section=' . urlencode($r['section']) . '&v=' . urlencode((string)($r['updated_at'] ?? '')))) : null,
            'imgfit' => $r['imgfit_json'] ? json_decode($r['imgfit_json'], true) : null,
            'credit' => $r['credit'] ?: null,
        ];
    }
    if (!$full) {
        header('Cache-Control: public, max-age=120, stale-while-revalidate=600');
    }
    vg_out_s(['sections' => $out]);
}

if ($method === 'PUT') {
    vg_require_admin($cfg);
    $b = vg_body_s();
    $section = trim($b['section'] ?? '');
    if ($section === '' || !in_array($section, VG_IMG_SECTIONS, true)) {
        vg_out_s(['error' => 'ungueltige section'], 400);
    }

    $sets = [];
    if (array_key_exists('img', $b))    { $sets['img'] = $b['img']; }
    if (array_key_exists('imgfit', $b)) { $sets['imgfit_json'] = $b['imgfit'] ? json_encode($b['imgfit'], JSON_UNESCAPED_UNICODE) : null; }
    if (array_key_exists('credit', $b)) { $sets['credit'] = $b['credit'] ?: null; }
    if (!$sets) vg_out_s(['error' => 'keine Felder zum Aktualisieren']);

    $chk = $pdo->prepare('SELECT section FROM section_meta WHERE section = ?');
    $chk->execute([$section]);
    if ($chk->fetch()) {
        $cols = []; $vals = [];
        foreach ($sets as $col => $val) { $cols[] = "$col = ?"; $vals[] = $val; }
        $cols[] = 'updated_at = CURRENT_TIMESTAMP';
        $vals[] = $section;
        $pdo->prepare('UPDATE section_meta SET ' . implode(', ', $cols) . ' WHERE section = ?')->execute($vals);
    } else {
        $keys = array_keys($sets);
        $ph = implode(',', array_fill(0, count($keys) + 1, '?'));
        $pdo->prepare('INSERT INTO section_meta (section, ' . implode(', ', $keys) . ') VALUES (' . $ph . ')')
            ->execute(array_merge([$section], array_values($sets)));
    }
    vg_out_s(['ok' => true]);
}

vg_out_s(['error' => 'Methode nicht unterstuetzt'], 405);
