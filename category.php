<?php
/*
 * Serverseitig gerenderte Kategorie-Uebersicht (z.B. /charaktere/) mit allen
 * veroeffentlichten Eintraegen der Sektion als echte, crawlbare Links.
 * Ergaenzt entry.php (eine Detailseite pro Eintrag) um die Ebene darueber:
 * bisher gab es keine serverseitig gerenderte Listenansicht pro Sektion, nur
 * einzelne Eintrags-URLs, Google fand also keinen indexierbaren Einstieg wie
 * "alle Charaktere". Analog zu article.php/entry.php: liefert dieselbe
 * index.html aus, ersetzt aber die <head>-Metadaten und befuellt einen
 * eigenen, sonst leeren Block mit einer einfachen Linkliste. Fuer Besucher
 * mit JavaScript uebernimmt go() beim Laden sofort und rendert die normale,
 * interaktive Kachelansicht.
 */

require __DIR__ . '/api/db.php';
[$pdo, $cfg] = vg_db();

// Deutsches URL-Praefix -> interne section-id und sichtbares Label, siehe
// auch .htaccess, entry.php und sitemap.php.
const VG_CATEGORY_SECTION_MAP = [
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

if (!isset(VG_CATEGORY_SECTION_MAP[$section])) {
    http_response_code(404);
    readfile(__DIR__ . '/index.html');
    exit;
}

function vg_esc4($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

$secInfo = VG_CATEGORY_SECTION_MAP[$section];

vg_ensure_entry_slugs($pdo);
$stmt = $pdo->prepare("SELECT name, sub, slug FROM db_entries WHERE section = ? AND slug IS NOT NULL AND slug <> '' ORDER BY sort_order, name");
$stmt->execute([$section]);
$rows = $stmt->fetchAll();

$canonical = 'https://viceguide.de/' . $secInfo['prefix'] . '/';
$pageTitle = $secInfo['label'] . ' in GTA 6, komplette Übersicht - ViceGuide';
$description = 'Alle ' . $secInfo['label'] . ' zu GTA 6 auf einen Blick: ' . count($rows) . ' Einträge, laufend aktualisiert. Deutschsprachige Übersicht bei ViceGuide.';

$html = file_get_contents(__DIR__ . '/index.html');

$head = [
    '<title>ViceGuide: GTA 6 Datenbank auf Deutsch, News, Guides & mehr</title>' =>
        '<title>' . vg_esc4($pageTitle) . '</title>',
    '<meta name="description" content="Die deutschsprachige GTA-6-Datenbank: aktuelle News und Leaks, dazu Charaktere, Fahrzeuge, Waffen, Orte, Guides und Easter Eggs. Alles zu GTA 6 an einem Ort.">' =>
        '<meta name="description" content="' . vg_esc4($description) . '">',
    '<link rel="canonical" href="https://viceguide.de/">' =>
        '<link rel="canonical" href="' . vg_esc4($canonical) . '">',
    '<meta property="og:title" content="ViceGuide: GTA 6 Datenbank auf Deutsch, News, Guides & mehr">' =>
        '<meta property="og:title" content="' . vg_esc4($pageTitle) . '">',
    '<meta property="og:description" content="Alles zu GTA 6 an einem Ort. Deine deutschsprachige Datenbank für News, Guides und Easter Eggs.">' =>
        '<meta property="og:description" content="' . vg_esc4($description) . '">',
    '<meta property="og:url" content="https://viceguide.de/">' =>
        '<meta property="og:url" content="' . vg_esc4($canonical) . '">',
    '<meta name="twitter:title" content="ViceGuide: GTA 6 Datenbank auf Deutsch, News, Guides & mehr">' =>
        '<meta name="twitter:title" content="' . vg_esc4($pageTitle) . '">',
    '<meta name="twitter:description" content="Alles zu GTA 6 an einem Ort. Deine deutschsprachige Datenbank für News, Guides und Easter Eggs.">' =>
        '<meta name="twitter:description" content="' . vg_esc4($description) . '">',
];
foreach ($head as $search => $replace) {
    $html = str_replace($search, $replace, $html);
}

// CollectionPage/ItemList-JSON-LD kurz vor dem <style>-Block einfuegen, damit
// Google die Seite als thematische Sammlung mit klaren Einzel-URLs versteht.
$itemList = [];
foreach ($rows as $i => $r) {
    $itemList[] = [
        '@type'    => 'ListItem',
        'position' => $i + 1,
        'url'      => 'https://viceguide.de/' . $secInfo['prefix'] . '/' . $r['slug'],
        'name'     => $r['name'],
    ];
}
$categoryLd = json_encode([
    '@context'   => 'https://schema.org',
    '@type'      => 'CollectionPage',
    'name'       => $pageTitle,
    'url'        => $canonical,
    'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => count($rows), 'itemListElement' => $itemList],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$html = str_replace(
    '<style>',
    '<script type="application/ld+json">' . $categoryLd . '</script>' . "\n" . '<style>',
    $html
);

// Sichtbaren Basis-Inhalt einbauen: Titel, kurzer Text, Linkliste zu allen
// Detailseiten der Sektion. Eigener, sonst leerer Block direkt hinter
// #view (siehe index.html), go() blendet ihn beim Uebernehmen durch das
// clientseitige Rendering wieder aus.
$itemsHtml = '';
foreach ($rows as $r) {
    $href = '/' . $secInfo['prefix'] . '/' . vg_esc4($r['slug']);
    $sub = trim($r['sub'] ?? '');
    $itemsHtml .= '<li><a href="' . $href . '">' . vg_esc4($r['name']) . '</a>' . ($sub !== '' ? ' <span class="cat-ssr-sub">' . vg_esc4($sub) . '</span>' : '') . '</li>';
}
$body = [
    '<div id="view"></div>' => '<div id="view" style="display:none"></div>',
    '<div id="cat-ssr" style="display:none"></div>' =>
        '<div id="cat-ssr" style="display:block;max-width:900px;margin:0 auto;padding:32px 20px">' .
            '<h1>' . vg_esc4($secInfo['label']) . ' in GTA 6</h1>' .
            '<p>' . vg_esc4($description) . '</p>' .
            '<ul class="cat-ssr-list">' . $itemsHtml . '</ul>' .
        '</div>',
];
foreach ($body as $search => $replace) {
    $html = str_replace($search, $replace, $html);
}

echo $html;
