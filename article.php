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

require __DIR__ . '/cache.php';
vg_cache_serve(600);   // Treffer wird hier ausgeliefert, DB bleibt unberuehrt.

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
        } elseif (str_starts_with($text, 'quote:')) {
            $parts = explode('|', substr($text, 6), 3);
            $q = preg_replace('/\[\[[a-z0-9-]+\|([^\]]+)\]\]/', '$1', trim($parts[0] ?? ''));
            $who = trim($parts[1] ?? '');
            $orig = trim($parts[2] ?? '');
            $out .= '<blockquote>' . vg_esc($q) . ($who ? '<cite>' . vg_esc($who) . '</cite>' : '')
                . ($orig ? '<details><summary>Original anzeigen</summary><p>' . vg_esc($orig) . '</p></details>' : '') . '</blockquote>';
        } elseif (str_starts_with($text, 'yt:')) {
            $parts = explode('|', substr($text, 3), 2);
            $id = trim($parts[0] ?? ''); $cap = trim($parts[1] ?? '');
            if (preg_match('/^[\w-]{6,20}$/', $id)) {
                $u = 'https://www.youtube.com/watch?v=' . rawurlencode($id);
                $out .= '<p><a href="' . vg_esc($u) . '" rel="noopener nofollow" target="_blank">' . vg_esc($cap !== '' ? $cap : 'Video auf YouTube ansehen') . '</a></p>';
            }
        } elseif (str_starts_with($text, 'embed:')) {
            $parts = explode('|', substr($text, 6), 3);
            $plat = strtolower(trim($parts[0] ?? '')); $url = trim($parts[1] ?? ''); $cap = trim($parts[2] ?? '');
            if (preg_match('#^https?://#', $url)) {
                $lbl = $cap !== '' ? $cap : ($plat === 'instagram' ? 'Beitrag auf Instagram ansehen' : 'Beitrag auf X ansehen');
                $out .= '<p><a href="' . vg_esc($url) . '" rel="noopener nofollow" target="_blank">' . vg_esc($lbl) . '</a></p>';
            }
        } else {
            $plain = preg_replace('/\[\[[a-z0-9-]+\|([^\]]+)\]\]/', '$1', $text);
            $out .= '<p>' . vg_esc($plain) . '</p>';
        }
    }
    return $out;
}

$title = $row['title'];
$updatedAt = $row['updated_at'] ?? null;
// Nur als echtes Update ausweisen, wenn seit der Veroeffentlichung wirklich
// mehr als ein Tag vergangen ist (analog zur Client-Logik in index.html),
// sonst markiert der minimale Zeitversatz zwischen article_date (Browser)
// und updated_at (Server) jeden frisch veroeffentlichten Artikel faelschlich
// als aktualisiert.
$pubTs = $row['article_date'] ? strtotime($row['article_date']) : false;
$updTs = $updatedAt ? strtotime($updatedAt) : false;
$showUpdated = $pubTs && $updTs && ($updTs - $pubTs) > 86400;
$summary = trim($row['summary'] ?: $row['lead'] ?: '');
$content = json_decode($row['content_json'] ?? '[]', true) ?: [];
$sources = json_decode($row['sources_json'] ?? '[]', true) ?: [];
$tldr = json_decode($row['tldr_json'] ?? '[]', true) ?: [];

// Kommentare serverseitig rendern: macht sie zu indexierbarem, nutzergeneriertem
// Inhalt (SEO), sonst laedt sie nur das clientseitige JavaScript per API nach und
// Google sieht sie praktisch nie. Fuer echte Besucher ueberschreibt
// renderComments() den Bereich beim Laden mit der interaktiven Fassung.
$cmStmt = $pdo->prepare('SELECT id, parent_id, name, text, quote, created_at FROM comments WHERE article_id = ? ORDER BY created_at ASC');
$cmStmt->execute([$slug]);
$cmRows = $cmStmt->fetchAll();
$cmCount = count($cmRows);
$cmHtml = '';
if ($cmRows) {
    $byId = [];
    foreach ($cmRows as $r) { $r['replies'] = []; $byId[$r['id']] = $r; }
    $roots = [];
    foreach ($byId as $id => &$r) {
        $pid = $r['parent_id'] ? (int)$r['parent_id'] : null;
        if ($pid && isset($byId[$pid])) { $byId[$pid]['replies'][] = &$r; } else { $roots[] = &$r; }
    }
    unset($r);
    $renderCm = function ($list, $depth) use (&$renderCm) {
        $h = '';
        foreach ($list as $c) {
            $h .= '<div class="cm-item' . ($depth ? ' cm-reply' : '') . '">'
                . '<div class="cm-top"><span class="cm-name">' . vg_esc($c['name']) . '</span><span class="cm-ts">' . vg_esc(vg_fmt_date($c['created_at'])) . '</span></div>'
                . ($c['quote'] ? '<div class="cm-quote">' . vg_esc($c['quote']) . '</div>' : '')
                . '<div class="cm-body">' . nl2br(vg_esc($c['text'])) . '</div>';
            if (!empty($c['replies'])) $h .= '<div class="cm-children">' . $renderCm($c['replies'], $depth + 1) . '</div>';
            $h .= '</div>';
        }
        return $h;
    };
    $cmHtml = '<h3 class="cm-h">Kommentare <span class="cm-c">' . $cmCount . '</span></h3>'
            . '<div class="cm-list">' . $renderCm($roots, 0) . '</div>';
}
$hasImg = !empty($row['img']);
$canonical = 'https://viceguide.de/artikel/' . $slug;
$imgUrl = $hasImg ? ('https://viceguide.de/api/article_image.php?id=' . urlencode($slug)) : 'https://viceguide.de/og-image.jpg';
// Suffix nur anhaengen, wenn das Ergebnis unter der Google-Abschneidegrenze
// (rund 60 Zeichen) bleibt, sonst frisst " - ViceGuide" das sichtbare Ende
// eines ohnehin schon langen Titels im Suchergebnis.
$withSuffix = $title . ' - ViceGuide';
$pageTitle = mb_strlen($withSuffix) <= 60 ? $withSuffix : $title;

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
    '<title>ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)</title>' =>
        '<title>' . vg_esc($pageTitle) . '</title>',
    '<meta name="description" content="Der deutsche GTA-6-Hub: aktuelle News, Gerüchte und Leaks, dazu eine große Datenbank zu Charakteren, Fahrzeugen, Waffen und Orten. Alles zu GTA 6 an einem Ort.">' =>
        '<meta name="description" content="' . vg_esc($summary) . '">',
    '<meta property="og:type" content="website">' =>
        '<meta property="og:type" content="article">',
    '<link rel="canonical" href="https://viceguide.de/">' =>
        '<link rel="canonical" href="' . vg_esc($canonical) . '">',
    '<meta property="og:title" content="ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)">' =>
        '<meta property="og:title" content="' . vg_esc($pageTitle) . '">',
    '<meta property="og:description" content="Alles zu GTA 6 an einem Ort. Der deutsche Hub mit Datenbank, Guides und aktuellen News.">' =>
        '<meta property="og:description" content="' . vg_esc($summary) . '">',
    '<meta property="og:url" content="https://viceguide.de/">' =>
        '<meta property="og:url" content="' . vg_esc($canonical) . '">',
    '<meta property="og:image" content="https://viceguide.de/og-image.jpg">' =>
        '<meta property="og:image" content="' . vg_esc($imgUrl) . '">',
    '<meta name="twitter:title" content="ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)">' =>
        '<meta name="twitter:title" content="' . vg_esc($pageTitle) . '">',
    '<meta name="twitter:description" content="Alles zu GTA 6 an einem Ort. Der deutsche Hub mit Datenbank, Guides und aktuellen News.">' =>
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

/* Datum als gueltiges ISO 8601 mit Zeitzone ausgeben (z.B. 2026-07-15T09:10:00+02:00).
   article_date kommt als lokale Berliner Zeit ohne Zeitzone aus dem Client,
   updated_at liegt als UTC in der Datenbank (Verbindung fest auf UTC, siehe
   db.php). Beide werden nach Europe/Berlin normalisiert und mit Offset formatiert. */
function vg_iso8601(?string $s, bool $fromUtc = false): ?string {
    if (!$s) return null;
    try {
        $berlin = new DateTimeZone('Europe/Berlin');
        $dt = new DateTime($s, $fromUtc ? new DateTimeZone('UTC') : $berlin);
        $dt->setTimezone($berlin);
        return $dt->format('c');
    } catch (Throwable $e) { return null; }
}
$datePublishedIso = vg_iso8601($row['article_date'], false) ?: $row['article_date'];
$dateModifiedIso  = $updatedAt ? (vg_iso8601($updatedAt, true) ?: $updatedAt) : $datePublishedIso;

// Artikel-JSON-LD kurz vor dem <style>-Block einfuegen.
$articleLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $title,
    'description' => $summary,
    'image' => $imgUrl,
    'datePublished' => $datePublishedIso,
    'dateModified' => $dateModifiedIso,
    'author' => ['@type' => 'Person', 'name' => $row['author'] ?: 'Eddy Hanné', 'url' => 'https://viceguide.de/ueber-uns'],
    'publisher' => ['@type' => 'Organization', 'name' => 'ViceGuide'],
    'mainEntityOfPage' => $canonical,
    'commentCount' => $cmCount,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
// BreadcrumbList: Startseite > News & Gerüchte > Artikel. Hilft Google, die
// Hierarchie zu verstehen, und kann die Breadcrumb im Suchergebnis anzeigen.
$breadcrumbLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => 'https://viceguide.de/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'News & Gerüchte', 'item' => 'https://viceguide.de/news'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $row['title'], 'item' => $canonical],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
/* NUR vor dem ersten <style> (dem Head-CSS) einfuegen. index.html enthaelt
   inzwischen weitere <style>-Vorkommen (Footer-CSS im Body und ein <style> in
   einem JS-String der Newsletter-Vorschau). Ein globales str_replace('<style>')
   wuerde die JSON-LD-Skripte auch dort einsetzen und dabei mitten ins
   Haupt-<script> ein </script> schreiben, das den Skriptblock vorzeitig
   beendet, alles danach erschiene dann als roher Text auf der Seite. */
/* FAQPage aus den faq:-Zeilen des Artikels. Das Schema erzeugte bisher nur
   das clientseitige JavaScript in index.html, die von Google zuerst gecrawlte
   SSR-Fassade hier hatte es nicht, damit blieb das FAQ-Rich-Snippet ungenutzt.
   Interne [[id|text]]-Verweise werden im Antworttext zu Klartext aufgeloest. */
$faqItems = [];
foreach ($content as $block) {
    if (!is_string($block) || !str_starts_with($block, 'faq:')) continue;
    $parts = explode('|', substr($block, 4), 2);
    $q = trim($parts[0] ?? '');
    $a = trim(preg_replace('/\[\[[a-z0-9-]+\|([^\]]+)\]\]/', '$1', $parts[1] ?? ''));
    if ($q === '' || $a === '') continue;
    $faqItems[] = [
        '@type' => 'Question',
        'name' => $q,
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
    ];
}
$faqLd = $faqItems ? json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => $faqItems,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
$ldInject = '<script type="application/ld+json">' . $articleLd . '</script>' . "\n"
    . '<script type="application/ld+json">' . $breadcrumbLd . '</script>' . "\n"
    . ($faqLd ? '<script type="application/ld+json">' . $faqLd . '</script>' . "\n" : '');
$stylePos = strpos($html, '<style>');
if ($stylePos !== false) {
    $html = substr($html, 0, $stylePos) . $ldInject . substr($html, $stylePos);
}

// Sichtbaren Basis-Inhalt einbauen, damit auch ohne JavaScript echter Text
// da ist (Crawler ohne JS-Ausfuehrung, Lesbarkeit im "Quelltext anzeigen").
$body = [
    '<div id="view"></div>' => '<div id="view" style="display:none"></div>',
    '<article class="article" id="article">' => '<article class="article show" id="article">',
    '<h1 id="a-title"></h1>' => '<h1 id="a-title">' . vg_esc($title) . '</h1>',
    '<span class="art-author" id="a-author"></span>' => '<span class="art-author" id="a-author">' . vg_esc($row['author'] ?: 'Eddy Hanné') . '</span>',
    '<span class="art-date" id="a-date"></span>' => '<span class="art-date" id="a-date">' . vg_esc(vg_fmt_date($row['article_date'])) . '</span>',
    '<p class="lead" id="a-lead"></p>' => '<p class="lead" id="a-lead">' . vg_esc($row['lead'] ?: $summary) . '</p>',
    '<div class="content" id="a-content"></div>' => '<div class="content" id="a-content">' . vg_plain_content($content) . '</div>',
];
// "Auf einen Blick"-Box serverseitig fuellen (interne [[id|text]]-Verweise als
// Klartext), sonst blieb sie ohne JavaScript unsichtbar.
if (is_array($tldr) && $tldr) {
    $lis = '';
    foreach ($tldr as $t) {
        if (!is_string($t) || trim($t) === '') continue;
        $lis .= '<li>' . vg_esc(trim(preg_replace('/\[\[[a-z0-9-]+\|([^\]]+)\]\]/', '$1', $t))) . '</li>';
    }
    if ($lis !== '') {
        $body['<div class="art-tldr" id="a-tldr" style="display:none"><div class="artbox-h">Auf einen Blick</div><ul id="a-tldr-list"></ul></div>'] =
            '<div class="art-tldr" id="a-tldr"><div class="artbox-h">Auf einen Blick</div><ul id="a-tldr-list">' . $lis . '</ul></div>';
    }
}
if ($showUpdated) {
    $body['<span class="art-dot" id="a-upd-dot" style="display:none">·</span>'] =
        '<span class="art-dot" id="a-upd-dot">·</span>';
    $body['<span class="art-date" id="a-updated" style="display:none"></span>'] =
        '<span class="art-date" id="a-updated">Aktualisiert: ' . vg_esc(vg_fmt_date($updatedAt)) . '</span>';
}
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
/* Interne Verlinkung fuer Crawler: den "Mehr Artikel"-Block serverseitig mit
   echten Links fuellen (gleiche Kategorie zuerst, dann neueste). Ankertext ist
   der Artikeltitel, das ist das SEO-relevante Signal. Fuer normale Besucher
   ueberschreibt openArticle() den Block beim Laden ohnehin clientseitig. */
$moreStmt = $pdo->prepare('SELECT id, title, article_date FROM articles WHERE id <> ? ORDER BY (cat = ?) DESC, article_date DESC LIMIT 6');
$moreStmt->execute([$row['id'], $row['cat']]);
$moreHtml = '';
foreach ($moreStmt->fetchAll() as $m) {
    $moreHtml .= '<a class="mcard" href="/artikel/' . vg_esc($m['id']) . '">'
        . '<div class="mcb"><span class="mt">' . vg_esc($m['title']) . '</span>'
        . '<span class="mdate">' . vg_esc(vg_fmt_date($m['article_date'])) . '</span></div></a>';
}
if ($moreHtml !== '') {
    $body['<div class="more-grid" id="a-more"></div>'] = '<div class="more-grid" id="a-more">' . $moreHtml . '</div>';
}
if ($cmHtml !== '') {
    $body['<section class="comments" id="a-comments"></section>'] = '<section class="comments" id="a-comments">' . $cmHtml . '</section>';
}
foreach ($body as $search => $replace) {
    $html = str_replace($search, $replace, $html);
}

echo $html;
