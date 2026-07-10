<?php
/*
 * Serverseitiges Ausliefern einer echten Artikel-URL (/artikel/{id}).
 *
 * Zweck: Suchmaschinen und Link-Vorschauen (Discord, WhatsApp, Twitter/X, ...)
 * fuehren kein JavaScript aus, bevor sie Titel/Beschreibung/Bild lesen. Diese
 * Datei liefert deshalb die normale index.html aus, ersetzt aber vorher die
 * <head>-Metadaten durch die echten Artikel-Angaben und befuellt den
 * Artikel-Bereich mit einer einfachen Text-Fassung des Inhalts (ohne
 * Aufzaehlungen/FAQ-Akkordeon/Bild-Zuschnitt, das macht danach ganz normal
 * das clientseitige JavaScript). Fuer echte Besucher mit aktiviertem
 * JavaScript (praktisch alle) ist das unsichtbar, die App uebernimmt sofort
 * und rendert den Artikel wie gewohnt vollstaendig nach.
 */

require __DIR__ . '/api/db.php';
[$pdo, $cfg] = vg_db();

$slug = preg_replace('/[^a-z0-9-]/', '', $_GET['slug'] ?? '');

$stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
$stmt->execute([$slug]);
$row = $slug !== '' ? $stmt->fetch() : false;

if (!$row) {
    // Unbekannte oder fehlende id: normale App ausliefern, das Routing im
    // Frontend zeigt dann die Startseite (kein 404-Rums fuer Besucher), aber
    // mit echtem 404-Statuscode. Ohne den wuerde die Adresse als ganz normale
    // 200-Seite behandelt (ein "soft 404"), Suchmaschinen werten das negativ
    // und verschwenden Crawl-Budget auf Adressen ohne echten Inhalt.
    http_response_code(404);
    readfile(__DIR__ . '/index.html');
    exit;
}

function vg_esc($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

function vg_fmt_date(?string $iso): string {
    if (!$iso || !preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/', $iso, $m)) return '';
    $h = $m[4] ?? '00'; $i = $m[5] ?? '00';
    return "{$m[3]}.{$m[2]}.{$m[1]}, {$h}:{$i} Uhr";
}

// Baut eine einfache Lesefassung aus dem content-Array, ohne die Sonder-
// syntax (###, img:, - , faq:, [[id|text]]), nur reiner lesbarer Text.
function vg_plain_content(array $content): string {
    $out = '';
    foreach ($content as $block) {
        if (!is_string($block) || trim($block) === '') continue;
        $text = $block;
        if (str_starts_with($text, 'img:')) continue;
        if (str_starts_with($text, '###')) {
            $out .= '<h3>' . vg_esc(trim(preg_replace('/^###\s*/', '', $text))) . '</h3>';
        } elseif (str_starts_with($text, '- ')) {
            $out .= '<p>' . vg_esc(trim(substr($text, 2))) . '</p>';
        } elseif (str_starts_with($text, 'faq:')) {
            $parts = explode('|', substr($text, 4), 2);
            $out .= '<p><strong>' . vg_esc(trim($parts[0] ?? '')) . '</strong> ' . vg_esc(trim($parts[1] ?? '')) . '</p>';
        } else {
            $plain = preg_replace('/\[\[[a-z0-9-]+\|([^\]]+)\]\]/', '$1', $text);
            $out .= '<p>' . vg_esc($plain) . '</p>';
        }
    }
    return $out;
}

$title = $row['title'];
$summary = trim($row['summary'] ?: $row['lead'] ?: '');
$content = json_decode($row['content_json'] ?? '[]', true) ?: [];
$sources = json_decode($row['sources_json'] ?? '[]', true) ?: [];
$hasImg = !empty($row['img']);
$canonical = 'https://viceguide.de/artikel/' . $slug;
$imgUrl = $hasImg ? ('https://viceguide.de/api/article_image.php?id=' . urlencode($slug)) : 'https://viceguide.de/og-image.jpg';
$pageTitle = $title . ' - ViceGuide';

// Echte Bildmasse/Mime-Type ermitteln, damit og:image:type/width/height zum
// tatsaechlichen Artikelbild passen (Uploads sind meist WebP, nicht JPEG,
// siehe CLAUDE.md Bildkompression), sonst verwerfen manche Crawler die
// Vorschau bei einem falschen Type.
$imgMeta = null;
if ($hasImg && preg_match('#^data:image/[a-zA-Z0-9.+-]+;base64,(.+)$#', $row['img'], $im)) {
    $bytes = base64_decode($im[1]);
    $info = @getimagesizefromstring($bytes);
    if ($info) {
        $imgMeta = ['w' => $info[0], 'h' => $info[1], 'mime' => $info['mime']];
    }
}

$html = file_get_contents(__DIR__ . '/index.html');

$head = [
    '<title>ViceGuide - GTA 6 News, Guides & Datenbank (Deutsch)</title>' =>
        '<title>' . vg_esc($pageTitle) . '</title>',
    '<meta name="description" content="ViceGuide ist deine deutschsprachige Anlaufstelle für GTA 6: aktuelle News, Gerüchte und Leaks, dazu eine große Datenbank zu Charakteren, Fahrzeugen, Waffen, Wildtieren, Orten und mehr. Ab Release folgen Guides, Geld-Tipps und Easter Eggs.">' =>
        '<meta name="description" content="' . vg_esc($summary) . '">',
    '<meta property="og:type" content="website">' =>
        '<meta property="og:type" content="article">',
    '<link rel="canonical" href="https://viceguide.de/">' =>
        '<link rel="canonical" href="' . vg_esc($canonical) . '">',
    '<meta property="og:title" content="ViceGuide - GTA 6 News, Guides & Datenbank (Deutsch)">' =>
        '<meta property="og:title" content="' . vg_esc($pageTitle) . '">',
    '<meta property="og:description" content="Deine deutschsprachige Anlaufstelle für GTA 6: News, Leaks, Datenbank und ab Release Guides, Geld-Tipps und Easter Eggs.">' =>
        '<meta property="og:description" content="' . vg_esc($summary) . '">',
    '<meta property="og:url" content="https://viceguide.de/">' =>
        '<meta property="og:url" content="' . vg_esc($canonical) . '">',
    '<meta property="og:image" content="https://viceguide.de/og-image.jpg">' =>
        '<meta property="og:image" content="' . vg_esc($imgUrl) . '">',
    '<meta name="twitter:title" content="ViceGuide - GTA 6 News, Guides & Datenbank (Deutsch)">' =>
        '<meta name="twitter:title" content="' . vg_esc($pageTitle) . '">',
    '<meta name="twitter:description" content="Deine deutschsprachige Anlaufstelle für GTA 6: News, Leaks, Datenbank und ab Release Guides.">' =>
        '<meta name="twitter:description" content="' . vg_esc($summary) . '">',
    '<meta name="twitter:image" content="https://viceguide.de/og-image.jpg">' =>
        '<meta name="twitter:image" content="' . vg_esc($imgUrl) . '">',
];
if ($imgMeta) {
    $head['<meta property="og:image:width" content="1200">'] =
        '<meta property="og:image:width" content="' . (int)$imgMeta['w'] . '">';
    $head['<meta property="og:image:height" content="630">'] =
        '<meta property="og:image:height" content="' . (int)$imgMeta['h'] . '">';
    $head['<meta property="og:image:type" content="image/jpeg">'] =
        '<meta property="og:image:type" content="' . vg_esc($imgMeta['mime']) . '">';
}
foreach ($head as $search => $replace) {
    $html = str_replace($search, $replace, $html);
}

// Artikel-JSON-LD kurz vor dem <style>-Block einfuegen.
$articleLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $title,
    'description' => $summary,
    'image' => $imgUrl,
    'datePublished' => $row['article_date'],
    'author' => ['@type' => 'Organization', 'name' => $row['author'] ?: 'ViceGuide Redaktion'],
    'publisher' => ['@type' => 'Organization', 'name' => 'ViceGuide'],
    'mainEntityOfPage' => $canonical,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$html = str_replace(
    '<style>',
    '<script type="application/ld+json">' . $articleLd . '</script>' . "\n" . '<style>',
    $html
);

// Sichtbaren Basis-Inhalt einbauen, damit auch ohne JavaScript echter Text
// da ist (Crawler ohne JS-Ausfuehrung, Lesbarkeit im "Quelltext anzeigen").
$body = [
    '<div id="view"></div>' => '<div id="view" style="display:none"></div>',
    '<article class="article" id="article">' => '<article class="article show" id="article">',
    '<h1 id="a-title"></h1>' => '<h1 id="a-title">' . vg_esc($title) . '</h1>',
    '<span class="art-author" id="a-author"></span>' => '<span class="art-author" id="a-author">' . vg_esc($row['author'] ?: 'ViceGuide Redaktion') . '</span>',
    '<span class="art-date" id="a-date"></span>' => '<span class="art-date" id="a-date">' . vg_esc(vg_fmt_date($row['article_date'])) . '</span>',
    '<p class="lead" id="a-lead"></p>' => '<p class="lead" id="a-lead">' . vg_esc($row['lead'] ?: $summary) . '</p>',
    '<div class="content" id="a-content"></div>' => '<div class="content" id="a-content">' . vg_plain_content($content) . '</div>',
];
if ($hasImg) {
    /* Nur der oeffnende Tag und der (separat verankerte) Credit-Span werden
       ersetzt, nicht das gesamte Element als ein Stueck: der Hero-Div in
       index.html traegt inzwischen weitere Kind-Elemente (Editiermodus-Badge,
       Leer-Hinweis), ein Abgleich des kompletten Element-Strings wuerde bei
       jeder kuenftigen Aenderung an diesen JS-only-Elementen stillschweigend
       nicht mehr treffen (str_replace() ohne Fehlermeldung bei keinem
       Treffer), das Titelbild bliebe dann in der SSR-Fassung unsichtbar. */
    $body['<div class="art-hero" id="a-hero" style="display:none">'] =
        '<div class="art-hero" id="a-hero" style="display:block;background-image:url(\'' . vg_esc($imgUrl) . '\');background-size:cover;background-position:center">';
    $body['<span class="credit" id="a-herocredit"></span>'] =
        '<span class="credit" id="a-herocredit">' . vg_esc($row['credit'] ?: '') . '</span>';
}
if ($sources) {
    $srcHtml = '';
    foreach ($sources as $s) {
        $srcHtml .= '<a href="' . vg_esc($s['url'] ?? '#') . '" target="_blank" rel="noopener">' . vg_esc($s['title'] ?? ($s['url'] ?? '')) . '</a>';
    }
    $body['<div class="sources" id="a-sources" style="display:none"><b>Quellen dieses Artikels</b><div id="a-src"></div></div>'] =
        '<div class="sources" id="a-sources" style="display:block"><b>Quellen dieses Artikels</b><div id="a-src">' . $srcHtml . '</div></div>';
}
foreach ($body as $search => $replace) {
    $html = str_replace($search, $replace, $html);
}

echo $html;
