<?php
/*
 * Serverseitig gerenderte Sammelseiten ueber den Rubriken:
 *   /datenbank/  -> alle Datenbank-Rubriken als crawlbare Links
 *   /guides/     -> die geplanten Guide-Kategorien (vor Release gesperrt)
 * Analog zu category.php/section.php: liefert index.html aus, ersetzt die
 * <head>-Metadaten und befuellt den sonst leeren #cat-ssr-Block. Fuer Besucher
 * mit JavaScript uebernimmt go() beim Laden sofort die interaktive Hub-Ansicht.
 */

require __DIR__ . '/cache.php';
vg_cache_serve(600);

require __DIR__ . '/api/db.php';
[$pdo, $cfg] = vg_db();

function vg_esc_hub($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

$page = $_GET['page'] ?? '';
if ($page !== 'datenbank' && $page !== 'guides') {
    http_response_code(404);
    readfile(__DIR__ . '/index.html');
    exit;
}

// Datenbank-Rubriken (interne id -> deutsches Praefix + Label), siehe .htaccess.
$DB_SECTIONS = [
    'characters' => ['prefix' => 'charaktere',   'label' => 'Charaktere'],
    'vehicles'   => ['prefix' => 'fahrzeuge',    'label' => 'Fahrzeuge'],
    'weapons'    => ['prefix' => 'waffen',       'label' => 'Waffen'],
    'wildlife'   => ['prefix' => 'wildtiere',    'label' => 'Wildtiere'],
    'gangs'      => ['prefix' => 'gangs',        'label' => 'Gangs'],
    'radio'      => ['prefix' => 'radio',        'label' => 'Radio'],
    'activities' => ['prefix' => 'aktivitaeten', 'label' => 'Aktivitäten'],
    'locations'  => ['prefix' => 'orte',         'label' => 'Orte'],
];
// Guide-Kategorien (vor Release gesperrt, noch keine eigenen Seiten).
$GUIDE_CATS = [
    'Anfänger-Guides', 'Geld verdienen', 'Missionen & Walkthroughs', 'Online-Modus',
    'Tipps & Tricks', 'Secrets & Easter Eggs', 'Fahrzeuge & Tuning',
    'Waffen & Ausrüstung', 'Charakter & Anpassung', 'Immobilien & Business',
];

if ($page === 'datenbank') {
    $counts = [];
    try {
        $stmt = $pdo->query("SELECT section, COUNT(*) c FROM db_entries WHERE slug IS NOT NULL AND slug <> '' GROUP BY section");
        foreach ($stmt->fetchAll() as $r) { $counts[$r['section']] = (int)$r['c']; }
    } catch (Exception $e) {}
    $canonical   = 'https://viceguide.de/datenbank/';
    $pageTitle   = 'GTA 6 Datenbank: Charaktere, Fahrzeuge, Waffen & mehr - ViceGuide';
    $description = 'Die deutschsprachige GTA-6-Datenbank: Charaktere, Fahrzeuge, Waffen, Wildtiere, Gangs, Radio, Aktivitäten und Orte. Alle Einträge auf einen Blick.';
    $h1          = 'GTA 6 Datenbank';
    $intro       = 'Das komplette deutschsprachige Nachschlagewerk zu GTA 6. Wähle eine Rubrik, um alle Einträge zu durchstöbern.';
} else {
    $canonical   = 'https://viceguide.de/guides/';
    $pageTitle   = 'GTA 6 Guides auf Deutsch: Geld, Missionen, Trophäen - ViceGuide';
    $description = 'Alle GTA-6-Guides an einem Ort: Geld verdienen, Missionen, Trophäen, Tipps und mehr. Schalten mit dem Release am 19. November 2026 frei.';
    $h1          = 'GTA 6 Guides';
    $intro       = 'Die echten In-Game-Guides zu GTA 6. Money-Methods, Missionen, Trophäen, Tipps und mehr. Schalten mit dem Release am 19. November 2026 frei.';
}

$html = file_get_contents(__DIR__ . '/index.html');

$head = [
    '<title>ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)</title>' =>
        '<title>' . vg_esc_hub($pageTitle) . '</title>',
    '<meta name="description" content="Der deutsche GTA-6-Hub: aktuelle News, Gerüchte und Leaks, dazu eine große Datenbank zu Charakteren, Fahrzeugen, Waffen und Orten. Alles zu GTA 6 an einem Ort.">' =>
        '<meta name="description" content="' . vg_esc_hub($description) . '">',
    '<link rel="canonical" href="https://viceguide.de/">' =>
        '<link rel="canonical" href="' . vg_esc_hub($canonical) . '">',
    '<meta property="og:title" content="ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)">' =>
        '<meta property="og:title" content="' . vg_esc_hub($pageTitle) . '">',
    '<meta property="og:description" content="Alles zu GTA 6 an einem Ort. Der deutsche Hub mit Datenbank, Guides und aktuellen News.">' =>
        '<meta property="og:description" content="' . vg_esc_hub($description) . '">',
    '<meta property="og:url" content="https://viceguide.de/">' =>
        '<meta property="og:url" content="' . vg_esc_hub($canonical) . '">',
    '<meta name="twitter:title" content="ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)">' =>
        '<meta name="twitter:title" content="' . vg_esc_hub($pageTitle) . '">',
    '<meta name="twitter:description" content="Alles zu GTA 6 an einem Ort. Der deutsche Hub mit Datenbank, Guides und aktuellen News.">' =>
        '<meta name="twitter:description" content="' . vg_esc_hub($description) . '">',
];
foreach ($head as $search => $replace) { $html = str_replace($search, $replace, $html); }

// Der Guides-Hub ist bis Release nur ein Platzhalter (gesperrte Kategorien,
// noch keine echten Guides). Bis dahin nicht indexieren, Links aber folgen,
// analog zur Karte (siehe section.php). Die Datenbank bleibt index,follow.
if ($page === 'guides') {
    $html = str_replace(
        '<meta name="robots" content="index, follow">',
        '<meta name="robots" content="noindex, follow">',
        $html
    );
}

// BreadcrumbList: Startseite > [Hub].
$breadcrumbLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => 'https://viceguide.de/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $h1, 'item' => $canonical],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// CollectionPage/ItemList nur fuer die Datenbank (echte Rubrik-URLs).
$lds = [$breadcrumbLd];
if ($page === 'datenbank') {
    $itemList = []; $pos = 0;
    foreach ($DB_SECTIONS as $sec => $info) {
        $itemList[] = [
            '@type' => 'ListItem', 'position' => ++$pos,
            'url' => 'https://viceguide.de/' . $info['prefix'] . '/',
            'name' => $info['label'],
        ];
    }
    $lds[] = json_encode([
        '@context' => 'https://schema.org', '@type' => 'CollectionPage',
        'name' => $pageTitle, 'url' => $canonical,
        'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => count($itemList), 'itemListElement' => $itemList],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
$ldInject = '';
foreach ($lds as $ld) { $ldInject .= '<script type="application/ld+json">' . $ld . '</script>' . "\n"; }
$stylePos = strpos($html, '<style>');
if ($stylePos !== false) { $html = substr($html, 0, $stylePos) . $ldInject . substr($html, $stylePos); }

// Sichtbaren Basis-Inhalt in den sonst leeren #cat-ssr-Block bauen.
$itemsHtml = '';
if ($page === 'datenbank') {
    foreach ($DB_SECTIONS as $sec => $info) {
        $href = '/' . $info['prefix'] . '/';
        $n = $counts[$sec] ?? 0;
        $itemsHtml .= '<li><a href="' . $href . '">' . vg_esc_hub($info['label']) . '</a>'
            . ' <span class="cat-ssr-sub">' . $n . ' Einträge</span></li>';
    }
} else {
    foreach ($GUIDE_CATS as $name) {
        $itemsHtml .= '<li>' . vg_esc_hub($name) . ' <span class="cat-ssr-sub">ab Release</span></li>';
    }
}
$body = [
    '<div id="view"></div>' => '<div id="view" style="display:none"></div>',
    '<div id="cat-ssr" style="display:none"></div>' =>
        '<div id="cat-ssr" style="display:block;max-width:900px;margin:0 auto;padding:32px 20px">' .
            '<h1>' . vg_esc_hub($h1) . '</h1>' .
            '<p>' . vg_esc_hub($intro) . '</p>' .
            '<ul class="cat-ssr-list">' . $itemsHtml . '</ul>' .
        '</div>',
];
foreach ($body as $search => $replace) { $html = str_replace($search, $replace, $html); }

echo $html;
