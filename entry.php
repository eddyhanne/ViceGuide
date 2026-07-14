<?php
/*
 * Serverseitiges Ausliefern einer echten Datenbank-Eintrag-URL
 * (/charaktere/{slug}, /fahrzeuge/{slug}, ...). Analog zu article.php:
 * liefert dieselbe index.html aus, ersetzt aber vorher die <head>-Metadaten
 * durch die echten Eintrags-Angaben und befuellt den sichtbaren Bereich mit
 * einer einfachen Text-Fassung (Name, Unterzeile, Kategorie-Chip, Felder,
 * Beschreibung), damit Suchmaschinen und Link-Vorschauen ohne JavaScript
 * echte Inhalte bekommen. Fuer Besucher mit JavaScript uebernimmt openModal()
 * beim Laden sofort und rendert wie gewohnt vollstaendig.
 */

require __DIR__ . '/cache.php';
vg_cache_serve(600);   // Treffer wird hier ausgeliefert, DB bleibt unberuehrt.

require __DIR__ . '/api/db.php';
[$pdo, $cfg] = vg_db();

// Deutsches URL-Praefix -> interne section-id (SECTIONS in index.html) und
// sichtbares Label, siehe auch .htaccess und sitemap.php.
const VG_SECTION_MAP = [
    'characters' => ['prefix' => 'charaktere',   'label' => 'Charaktere'],
    'vehicles'   => ['prefix' => 'fahrzeuge',    'label' => 'Fahrzeuge'],
    'weapons'    => ['prefix' => 'waffen',       'label' => 'Waffen'],
    'wildlife'   => ['prefix' => 'wildtiere',    'label' => 'Wildtiere'],
    'gangs'      => ['prefix' => 'gangs',        'label' => 'Gangs'],
    'radio'      => ['prefix' => 'radio',        'label' => 'Radio'],
    'activities' => ['prefix' => 'aktivitaeten', 'label' => 'Aktivitäten'],
    'locations'  => ['prefix' => 'orte',         'label' => 'Orte'],
];

$section = $_GET['section'] ?? '';
$slug = preg_replace('/[^a-z0-9-]/', '', $_GET['slug'] ?? '');

if (!isset(VG_SECTION_MAP[$section]) || $slug === '') {
    http_response_code(404);
    readfile(__DIR__ . '/index.html');
    exit;
}

vg_ensure_entry_slugs($pdo);

$stmt = $pdo->prepare('SELECT * FROM db_entries WHERE section = ? AND slug = ?');
$stmt->execute([$section, $slug]);
$row = $stmt->fetch();

if (!$row) {
    // Unbekannter oder fehlender Eintrag: normale App ausliefern, das
    // Frontend-Routing zeigt dann die Startseite (kein 404-Rums), aber mit
    // echtem 404-Statuscode, siehe article.php fuer die Begruendung (soft 404
    // vermeiden).
    http_response_code(404);
    readfile(__DIR__ . '/index.html');
    exit;
}

function vg_esc2($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

$secInfo = VG_SECTION_MAP[$section];
$name = $row['name'];
$sub = trim($row['sub'] ?? '');
$desc = trim($row['description'] ?? '');
$summary = mb_strlen($desc) > 155 ? (mb_substr($desc, 0, 154) . '…') : $desc;
if ($summary === '') $summary = $sub !== '' ? $sub : ($secInfo['label'] . ' in GTA 6, Übersicht bei ViceGuide.');
$fields = $row['fields_json'] ? json_decode($row['fields_json'], true) : [];
$hasImg = !empty($row['img']);
$canonical = 'https://viceguide.de/' . $secInfo['prefix'] . '/' . $slug;
$imgUrl = $hasImg ? ('https://viceguide.de/api/entry_image.php?id=' . (int)$row['id']) : 'https://viceguide.de/og-image.jpg';
// Wie in article.php: Bestandteile mit dem geringsten SEO-Wert (Branding-
// Suffix, dann Unterzeile) zuerst weglassen, wenn der Titel sonst ueber der
// rund 60-Zeichen-Abschneidegrenze im Suchergebnis landen wuerde.
$base = $name . ($sub !== '' ? ' (' . $sub . ')' : '');
$withLabel = $base . ' - ' . $secInfo['label'];
$pageTitle = $withLabel . ' - ViceGuide';
if (mb_strlen($pageTitle) > 60) $pageTitle = $withLabel;
if (mb_strlen($pageTitle) > 60 && $sub !== '') $pageTitle = $name . ' - ' . $secInfo['label'];

$imgMeta = null;
if ($hasImg && preg_match('#^data:image/[a-zA-Z0-9.+-]+;base64,(.+)$#', $row['img'], $im)) {
    $bytes = base64_decode($im[1]);
    $info = @getimagesizefromstring($bytes);
    if ($info) { $imgMeta = ['w' => $info[0], 'h' => $info[1], 'mime' => $info['mime']]; }
}

$html = file_get_contents(__DIR__ . '/index.html');

$head = [
    '<title>ViceGuide: GTA 6 Datenbank auf Deutsch, News, Guides & mehr</title>' =>
        '<title>' . vg_esc2($pageTitle) . '</title>',
    '<meta name="description" content="Die deutschsprachige GTA-6-Datenbank: aktuelle News und Gerüchte, dazu Charaktere, Fahrzeuge, Waffen, Orte, Guides und Easter Eggs. Alles zu GTA 6 an einem Ort.">' =>
        '<meta name="description" content="' . vg_esc2($summary) . '">',
    '<link rel="canonical" href="https://viceguide.de/">' =>
        '<link rel="canonical" href="' . vg_esc2($canonical) . '">',
    '<meta property="og:title" content="ViceGuide: GTA 6 Datenbank auf Deutsch, News, Guides & mehr">' =>
        '<meta property="og:title" content="' . vg_esc2($pageTitle) . '">',
    '<meta property="og:description" content="Alles zu GTA 6 an einem Ort. Deine deutschsprachige Datenbank für News, Guides und Easter Eggs.">' =>
        '<meta property="og:description" content="' . vg_esc2($summary) . '">',
    '<meta property="og:url" content="https://viceguide.de/">' =>
        '<meta property="og:url" content="' . vg_esc2($canonical) . '">',
    '<meta property="og:image" content="https://viceguide.de/og-image.jpg">' =>
        '<meta property="og:image" content="' . vg_esc2($imgUrl) . '">',
    '<meta name="twitter:title" content="ViceGuide: GTA 6 Datenbank auf Deutsch, News, Guides & mehr">' =>
        '<meta name="twitter:title" content="' . vg_esc2($pageTitle) . '">',
    '<meta name="twitter:description" content="Alles zu GTA 6 an einem Ort. Deine deutschsprachige Datenbank für News, Guides und Easter Eggs.">' =>
        '<meta name="twitter:description" content="' . vg_esc2($summary) . '">',
    '<meta name="twitter:image" content="https://viceguide.de/og-image.jpg">' =>
        '<meta name="twitter:image" content="' . vg_esc2($imgUrl) . '">',
];
if ($imgMeta) {
    $head['<meta property="og:image:width" content="1200">'] =
        '<meta property="og:image:width" content="' . (int)$imgMeta['w'] . '">';
    $head['<meta property="og:image:height" content="630">'] =
        '<meta property="og:image:height" content="' . (int)$imgMeta['h'] . '">';
    $head['<meta property="og:image:type" content="image/jpeg">'] =
        '<meta property="og:image:type" content="' . vg_esc2($imgMeta['mime']) . '">';
}
foreach ($head as $search => $replace) {
    $html = str_replace($search, $replace, $html);
}

// Thing-JSON-LD kurz vor dem <style>-Block einfuegen (generisch, deckt
// Charaktere/Fahrzeuge/Waffen/Orte etc. gleichermassen ab).
$entryLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Thing',
    'name' => $name,
    'description' => $summary,
    'image' => $imgUrl,
    'url' => $canonical,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$html = str_replace(
    '<style>',
    '<script type="application/ld+json">' . $entryLd . '</script>' . "\n" . '<style>',
    $html
);

// Sichtbaren Basis-Inhalt einbauen: Name als h1, Unterzeile, Kategorie-Chip,
// Felder-Liste, Beschreibung. Bewusst kein Nachbau des Bild-Zuschnitts
// (imgfit), das Bild wird einfach vollflaechig gezeigt.
$fieldsHtml = '';
if ($fields) {
    foreach ($fields as $k => $v) {
        $fieldsHtml .= '<p><strong>' . vg_esc2($k) . ':</strong> ' . vg_esc2($v) . '</p>';
    }
}
$descHtml = '';
foreach (preg_split('/\r?\n+/', $desc) as $para) {
    $para = trim($para);
    if ($para !== '') $descHtml .= '<p>' . vg_esc2($para) . '</p>';
}
$chips = '<p>' . vg_esc2($secInfo['label']) . ($row['cat'] ? ' · ' . vg_esc2($row['cat']) : '') . ($row['src'] ? ' · Quelle: ' . vg_esc2($row['src']) : '') . '</p>';

$modalInner =
    ($hasImg ? '<img src="' . vg_esc2($imgUrl) . '" alt="' . vg_esc2($name) . '" style="width:100%;border-radius:16px 16px 0 0">' : '') .
    '<div class="mbody"><h1>' . vg_esc2($name) . '</h1><div class="msub">' . vg_esc2($sub) . '</div>' .
    $chips . $fieldsHtml .
    '<div class="mabout">Beschreibung</div>' . $descHtml .
    '</div>';

$body = [
    '<div id="view"></div>' => '<div id="view" style="display:none"></div>',
    '<div class="ov" id="ov" onclick="if(event.target.id===\'ov\')closeModal()"><div class="modal" id="modal"></div></div>' =>
        '<div class="ov show" id="ov" onclick="if(event.target.id===\'ov\')closeModal()"><div class="modal" id="modal">' . $modalInner . '</div></div>',
];
foreach ($body as $search => $replace) {
    $html = str_replace($search, $replace, $html);
}

echo $html;
