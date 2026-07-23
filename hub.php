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

/* Status-Verdikt eines Artikels als Wort (Leak-Chronik), deckungsgleich zu
   artStatusMeta() in index.html. Ein Leak-Artikel ohne Status gilt als
   unbestaetigt. */
function vg_art_verdict($status, $cat) {
    if ($status === 'confirmed') return 'Bestätigt';
    if ($status === 'mixed') return 'Teils bestätigt';
    if ($status === 'rumor') return 'Unbestätigt';
    if ($cat === 'leaks') return 'Unbestätigt';
    return '';
}
function vg_art_verdict_cls($status) {
    return $status === 'confirmed' ? 'ok' : ($status === 'mixed' ? 'mid' : 'rum');
}
function vg_lc_date($d) {
    $d = substr((string)$d, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return '';
    [$y, $m, $dd] = explode('-', $d);
    return (int)$dd . '.' . (int)$m . '.' . $y;
}

$page = $_GET['page'] ?? '';
if ($page !== 'datenbank' && $page !== 'guides' && $page !== 'ratgeber' && $page !== 'leaks-chronik') {
    http_response_code(404);
    readfile(__DIR__ . '/index.html');
    exit;
}

// Ratgeber-Bloecke (Spiegel der RATGEBER-Konstante in index.html), aus der
// gemeinsamen Datei geladen, die auch section.php nutzt.
$RATGEBER = require __DIR__ . '/ratgeber_data.php';

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
} elseif ($page === 'ratgeber') {
    // Titel der Ratgeber-Artikel laden.
    $rgTitles = [];
    $allIds = [];
    foreach ($RATGEBER as $blk) { foreach ($blk['ids'] as $id) { $allIds[] = $id; } }
    if ($allIds) {
        try {
            $ph = implode(',', array_fill(0, count($allIds), '?'));
            $st = $pdo->prepare("SELECT id, title FROM articles WHERE id IN ($ph)");
            $st->execute($allIds);
            foreach ($st->fetchAll() as $r) { $rgTitles[$r['id']] = $r['title']; }
        } catch (Throwable $e) {}
    }
    $canonical   = 'https://viceguide.de/ratgeber/';
    $pageTitle   = 'GTA 6 Ratgeber: Kaufberatung, Plattformen, Release-Wissen - ViceGuide';
    $description = 'Alle immergrünen GTA-6-Ratgeber an einem Ort: Editionen und Preise, Plattformen und Technik, Release-Wissen sowie Features und Modi. Verständlich erklärt.';
    $h1          = 'GTA 6 Ratgeber';
    $intro       = 'Alles Immergrüne rund um GTA 6 an einem Ort: Kaufberatung, Plattformen und Technik, Wissen rund um den Release sowie Features und Modi.';
} elseif ($page === 'leaks-chronik') {
    // Alle Geruechte/Leaks: cat=leaks oder Status rumor/mixed, neueste zuerst.
    $lcRows = [];
    try {
        $st = $pdo->query("SELECT id, title, article_date, summary, status, cat FROM articles
            WHERE cat = 'leaks' OR status = 'rumor'
            ORDER BY article_date DESC, id DESC");
        $lcRows = $st->fetchAll();
    } catch (Throwable $e) {}
    $canonical   = 'https://viceguide.de/leaks-chronik/';
    $pageTitle   = 'GTA 6 Leaks & Gerüchte: die Chronik - ViceGuide';
    $description = 'Alle großen GTA-6-Leaks und Gerüchte chronologisch mit klarem Verdikt: was ist offiziell bestätigt, was teils, was reiner Leak. Der laufende Faktencheck.';
    $h1          = 'GTA 6 Leaks & Gerüchte: die Chronik';
    $intro       = 'Jedes große GTA-6-Gerücht mit klarem Verdikt: was ist offiziell bestätigt, was teils, was reiner Leak. Chronologisch sortiert, jeder Eintrag führt zur ausführlichen Einordnung.';
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
} elseif ($page === 'ratgeber') {
    $itemList = []; $pos = 0;
    foreach ($RATGEBER as $blk) {
        foreach ($blk['ids'] as $id) {
            if (!isset($rgTitles[$id])) continue;
            $itemList[] = [
                '@type' => 'ListItem', 'position' => ++$pos,
                'url' => 'https://viceguide.de/artikel/' . $id,
                'name' => $rgTitles[$id],
            ];
        }
    }
    $lds[] = json_encode([
        '@context' => 'https://schema.org', '@type' => 'CollectionPage',
        'name' => $pageTitle, 'url' => $canonical,
        'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => count($itemList), 'itemListElement' => $itemList],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} elseif ($page === 'leaks-chronik') {
    $itemList = []; $pos = 0;
    foreach ($lcRows as $r) {
        $itemList[] = [
            '@type' => 'ListItem', 'position' => ++$pos,
            'url' => 'https://viceguide.de/artikel/' . $r['id'],
            'name' => $r['title'],
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
$listHtml = '';
if ($page === 'datenbank') {
    $itemsHtml = '';
    foreach ($DB_SECTIONS as $sec => $info) {
        $href = '/' . $info['prefix'] . '/';
        $n = $counts[$sec] ?? 0;
        $itemsHtml .= '<li><a href="' . $href . '">' . vg_esc_hub($info['label']) . '</a>'
            . ' <span class="cat-ssr-sub">' . $n . ' Einträge</span></li>';
    }
    $listHtml = '<ul class="cat-ssr-list">' . $itemsHtml . '</ul>';
} elseif ($page === 'ratgeber') {
    // Nach Themenblock gruppiert, echte Links auf die Artikel (crawlbar).
    foreach ($RATGEBER as $blk) {
        $lis = '';
        foreach ($blk['ids'] as $id) {
            if (!isset($rgTitles[$id])) continue;
            $lis .= '<li><a href="/artikel/' . vg_esc_hub($id) . '">' . vg_esc_hub($rgTitles[$id]) . '</a></li>';
        }
        if ($lis !== '') {
            $listHtml .= '<h2>' . vg_esc_hub($blk['label']) . '</h2><ul class="cat-ssr-list">' . $lis . '</ul>';
        }
    }
} elseif ($page === 'leaks-chronik') {
    // Chronologische Liste mit Verdikt-Label und echten Links (crawlbar).
    $lis = '';
    foreach ($lcRows as $r) {
        $verdict = vg_art_verdict($r['status'], $r['cat']);
        $vcls = vg_art_verdict_cls($r['status']);
        $date = vg_lc_date($r['article_date']);
        $lis .= '<li>'
            . ($verdict ? '<span class="db-status ' . $vcls . '"><span class="dot"></span>' . $verdict . '</span> ' : '')
            . '<a href="/artikel/' . vg_esc_hub($r['id']) . '">' . vg_esc_hub($r['title']) . '</a>'
            . ($date ? ' <span class="cat-ssr-sub">' . vg_esc_hub($date) . '</span>' : '')
            . ($r['summary'] ? '<br><span class="cat-ssr-sub">' . vg_esc_hub($r['summary']) . '</span>' : '')
            . '</li>';
    }
    $listHtml = '<ul class="cat-ssr-list lc-ssr">' . ($lis ?: '<li>Noch keine Einträge.</li>') . '</ul>';
} else {
    $itemsHtml = '';
    foreach ($GUIDE_CATS as $name) {
        $itemsHtml .= '<li>' . vg_esc_hub($name) . ' <span class="cat-ssr-sub">ab Release</span></li>';
    }
    $listHtml = '<ul class="cat-ssr-list">' . $itemsHtml . '</ul>';
}
$body = [
    '<div id="view"></div>' => '<div id="view" style="display:none"></div>',
    '<div id="cat-ssr" style="display:none"></div>' =>
        '<div id="cat-ssr" style="display:block;max-width:900px;margin:0 auto;padding:32px 20px">' .
            '<h1>' . vg_esc_hub($h1) . '</h1>' .
            '<p>' . vg_esc_hub($intro) . '</p>' .
            $listHtml .
        '</div>',
];
foreach ($body as $search => $replace) { $html = str_replace($search, $replace, $html); }

echo $html;
