<?php
/*
 * Serverseitig gerenderte Uebersicht fuer die Sektionen ohne Datenbank-
 * Backing (Videos, Community, Karte). Analog zu legal.php: der Inhalt kommt
 * aus den bestehenden JS-Konstanten in index.html (VIDEOS, COMMUNITY), keine
 * zweite Pflegestelle. Ergibt echte, crawlbare URLs (/videos, /community,
 * /karte) statt nur ueber das Hash-Schema (/#/videos) erreichbar zu sein.
 * Fuer Besucher mit JavaScript uebernimmt go() beim Laden sofort und
 * rendert die normale, interaktive Ansicht.
 */

require __DIR__ . '/cache.php';
vg_cache_serve(600);

// Interne section-id (siehe SECTIONS in index.html) -> deutsches URL-Praefix.
// Bei videos/community ist das deutsche Wort zufaellig identisch, bei map
// nicht (karte), daher die explizite Zuordnung statt einfach $page zu nehmen.
const VG_SECTION_URL_PREFIX = ['videos' => 'videos', 'community' => 'community', 'map' => 'karte', 'news' => 'news'];

$page = $_GET['page'] ?? '';
$valid = ['videos' => 'Videos', 'community' => 'Community', 'map' => 'Karte', 'news' => 'News & Gerüchte'];

if (!isset($valid[$page])) {
    http_response_code(404);
    readfile(__DIR__ . '/index.html');
    exit;
}

function vg_esc6($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

$html = file_get_contents(__DIR__ . '/index.html');
$canonical = 'https://viceguide.de/' . VG_SECTION_URL_PREFIX[$page];

if ($page === 'videos') {
    // Feste Objekt-Form je Eintrag (siehe const VIDEOS in index.html), per
    // Regex statt json_decode ausgelesen, weil die Keys dort unquotiert
    // sind (gueltiges JS, kein gueltiges JSON).
    preg_match_all('/\{\s*id:"([^"]*)",\s*title:"([^"]*)",\s*author:"([^"]*)",\s*date:"([^"]*)"\s*\}/', $html, $vm, PREG_SET_ORDER);
    $items = '';
    foreach ($vm as $v) {
        $items .= '<li><a href="https://www.youtube.com/watch?v=' . vg_esc6($v[1]) . '" target="_blank" rel="noopener">' . vg_esc6($v[2]) . '</a> <span class="cat-ssr-sub">' . vg_esc6($v[3]) . '</span></li>';
    }
    $pageTitle = 'GTA 6 Videos: Trailer und Clips auf Deutsch - ViceGuide';
    $description = 'Alle wichtigen GTA-6-Videos an einem Ort: ' . count($vm) . ' Trailer und Clips, deutsch eingeordnet.';
    $h1 = 'GTA 6 Videos';
} elseif ($page === 'community') {
    preg_match('/discordInvite:"([^"]*)"/', $html, $dm);
    preg_match('/redditUrl:"([^"]*)"/', $html, $rm);
    $discordInvite = $dm[1] ?? '';
    $redditUrl = $rm[1] ?? '';
    $items = '';
    if ($discordInvite !== '') {
        $items .= '<li><a href="' . vg_esc6($discordInvite) . '" target="_blank" rel="noopener">Discord beitreten</a></li>';
    }
    if ($redditUrl !== '') {
        $items .= '<li><a href="' . vg_esc6($redditUrl) . '" target="_blank" rel="noopener">Subreddit öffnen</a></li>';
    }
    $pageTitle = 'Community: Discord und Austausch zu GTA 6 - ViceGuide';
    $description = 'Tausch dich mit anderen GTA-6-Fans aus: Discord, Diskussionen, Leaks und Theorien.';
    $h1 = 'Community';
} elseif ($page === 'news') {
    // News-Rubrik: die Artikel kommen aus der Datenbank (anders als
    // Videos/Community aus JS-Konstanten). Wir listen die Beitraege als
    // echte, crawlbare Links auf ihre /artikel/{id}-URL. Fuer Besucher mit
    // JavaScript uebernimmt go() beim Laden und rendert die interaktive
    // News-Ansicht (Filter, Suche, Kacheln/Liste).
    require_once __DIR__ . '/api/db.php';
    [$pdo, $cfg] = vg_db();
    // Ratgeber-Artikel gehoeren in /ratgeber/, nicht in die News-Listung.
    $rgBlocks = require __DIR__ . '/ratgeber_data.php';
    $rgIds = [];
    foreach ($rgBlocks as $blk) { foreach ($blk['ids'] as $id) { $rgIds[$id] = true; } }

    // Optionaler Rubrik-Filter (/news/leaks ...): slug -> cat-Wert + Label.
    $NEWS_CATS = [
        'updates'   => ['news',      'News & Updates'],
        'leaks'     => ['leaks',     'Gerüchte & Leaks'],
        'trailer'   => ['trailer',   'Trailer-Analysen'],
        'story'     => ['story',     'Charaktere & Story'],
        'map'       => ['map',       'Map & Setting'],
        'community' => ['community', 'Community & Infohäppchen'],
    ];
    $catSlug = $_GET['cat'] ?? '';
    if ($catSlug !== '') {
        if (!isset($NEWS_CATS[$catSlug])) { http_response_code(404); readfile(__DIR__ . '/index.html'); exit; }
        [$catId, $catLabel] = $NEWS_CATS[$catSlug];
        $stmt = $pdo->prepare('SELECT id, title, article_date FROM articles WHERE cat = ? ORDER BY article_date DESC');
        $stmt->execute([$catId]);
        $rows = $stmt->fetchAll();
        $canonical = 'https://viceguide.de/news/' . $catSlug;
        $pageTitle = 'GTA 6 ' . $catLabel . ' auf Deutsch - ViceGuide';
        $description = 'Alle GTA-6-Beiträge der Rubrik ' . $catLabel . ' auf Deutsch, chronologisch und eingeordnet.';
        $h1 = $catLabel;
    } else {
        $rows = $pdo->query('SELECT id, title, article_date FROM articles ORDER BY article_date DESC')->fetchAll();
        $pageTitle = 'GTA 6 News und Gerüchte auf Deutsch - ViceGuide';
        $description = 'Alle GTA-6-News, Gerüchte und Leaks auf Deutsch, chronologisch und eingeordnet. Laufend aktualisiert.';
        $h1 = 'News & Gerüchte';
    }
    $items = '';
    foreach ($rows as $r) {
        if (isset($rgIds[$r['id']])) continue; // Ratgeber-Artikel raus
        $d = $r['article_date'] ? substr((string)$r['article_date'], 0, 10) : '';
        $items .= '<li><a href="/artikel/' . vg_esc6($r['id']) . '">' . vg_esc6($r['title']) . '</a>' . ($d !== '' ? ' <span class="cat-ssr-sub">' . vg_esc6($d) . '</span>' : '') . '</li>';
    }
} else {
    // Karte: die interaktive Kartenansicht ist noch nicht gebaut (siehe
    // renderMap() in index.html), bis dahin verweist die SSR-Fassung auf die
    // Orte-Datenbank, genau wie der Platzhalter-Button im Client.
    $items = '<li><a href="/orte/">Zu den Orten</a></li>';
    $pageTitle = 'Karte von Leonida: GTA 6 Map auf Deutsch - ViceGuide';
    $description = 'Die interaktive Karte von Leonida folgt in Kürze. Bis dahin findest du alle bekannten Orte in der Orte-Datenbank.';
    $h1 = 'Karte von Leonida';
}

$head = [
    '<title>ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)</title>' =>
        '<title>' . vg_esc6($pageTitle) . '</title>',
    '<meta name="description" content="Der deutsche GTA-6-Hub: aktuelle News, Gerüchte und Leaks, dazu eine große Datenbank zu Charakteren, Fahrzeugen, Waffen und Orten. Alles zu GTA 6 an einem Ort.">' =>
        '<meta name="description" content="' . vg_esc6($description) . '">',
    '<link rel="canonical" href="https://viceguide.de/">' =>
        '<link rel="canonical" href="' . vg_esc6($canonical) . '">',
    // Karte ist noch ein Platzhalter (interaktive Map folgt), bis dahin nicht
    // indexieren, Links aber folgen. Videos/Community bleiben index,follow.
    '<meta name="robots" content="index, follow">' =>
        ($page === 'map' ? '<meta name="robots" content="noindex, follow">' : '<meta name="robots" content="index, follow">'),
    '<meta property="og:title" content="ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)">' =>
        '<meta property="og:title" content="' . vg_esc6($pageTitle) . '">',
    '<meta property="og:description" content="Alles zu GTA 6 an einem Ort. Der deutsche Hub mit Datenbank, Guides und aktuellen News.">' =>
        '<meta property="og:description" content="' . vg_esc6($description) . '">',
    '<meta property="og:url" content="https://viceguide.de/">' =>
        '<meta property="og:url" content="' . vg_esc6($canonical) . '">',
    '<meta name="twitter:title" content="ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)">' =>
        '<meta name="twitter:title" content="' . vg_esc6($pageTitle) . '">',
    '<meta name="twitter:description" content="Alles zu GTA 6 an einem Ort. Der deutsche Hub mit Datenbank, Guides und aktuellen News.">' =>
        '<meta name="twitter:description" content="' . vg_esc6($description) . '">',
];
foreach ($head as $search => $replace) {
    $html = str_replace($search, $replace, $html);
}

$body = [
    '<div id="view"></div>' => '<div id="view" style="display:none"></div>',
    '<div id="cat-ssr" style="display:none"></div>' =>
        '<div id="cat-ssr" style="display:block;max-width:900px;margin:0 auto;padding:32px 20px">' .
            '<h1>' . vg_esc6($h1) . '</h1>' .
            '<p>' . vg_esc6($description) . '</p>' .
            ($items !== '' ? '<ul class="cat-ssr-list">' . $items . '</ul>' : '') .
        '</div>',
];
foreach ($body as $search => $replace) {
    $html = str_replace($search, $replace, $html);
}

// News-Seite ist eine Artikelsammlung: CollectionPage + ItemList (die Beitraege)
// und ein Breadcrumb. Nur vor dem ersten <style> einfuegen (siehe article.php).
if ($page === 'news' && !empty($rows)) {
    $itemList = [];
    $pos = 1;
    foreach ($rows as $r) {
        if (!empty($rgIds) && isset($rgIds[$r['id']])) continue;
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'url' => 'https://viceguide.de/artikel/' . $r['id'],
            'name' => $r['title'],
        ];
    }
    $collLd = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageTitle,
        'description' => $description,
        'url' => $canonical,
        'inLanguage' => 'de',
        'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $itemList],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $bcLd = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => 'https://viceguide.de/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'News & Gerüchte', 'item' => $canonical],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ldInject = '<script type="application/ld+json">' . $collLd . '</script>' . "\n"
        . '<script type="application/ld+json">' . $bcLd . '</script>' . "\n";
    $stylePos = strpos($html, '<style>');
    if ($stylePos !== false) {
        $html = substr($html, 0, $stylePos) . $ldInject . substr($html, $stylePos);
    }
}

echo $html;
