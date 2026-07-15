<?php
/*
 * Serverseitig gerenderte Impressum-/Datenschutz-Seite (/impressum,
 * /datenschutz). Bisher gab es dafuer nur ein clientseitiges Modal
 * (openLegal() in index.html), keine eigene URL, weder fuer Suchmaschinen
 * noch fuers Impressumspflicht-Gebot der leichten, unmittelbaren
 * Erreichbarkeit. Der eigentliche Text bleibt einzig im LEGAL-Objekt in
 * index.html gepflegt, diese Datei liest ihn von dort, statt ihn ein
 * zweites Mal zu pflegen (sonst laufen beide Fassungen irgendwann
 * auseinander).
 */

require __DIR__ . '/cache.php';
vg_cache_serve(1800);   // Rechtstexte aendern sich selten, Treffer ohne DB.

require __DIR__ . '/api/db.php';

$page = $_GET['page'] ?? '';
$valid = ['impressum' => 'Impressum', 'datenschutz' => 'Datenschutzerklärung', 'ueber' => 'Über uns'];

if (!isset($valid[$page])) {
    http_response_code(404);
    readfile(__DIR__ . '/index.html');
    exit;
}

$html = file_get_contents(__DIR__ . '/index.html');

if (!preg_match('/const LEGAL=\{(.*?)\n\};/s', $html, $m)) {
    http_response_code(500);
    exit('Rechtstext-Block nicht gefunden.');
}
if (!preg_match('/' . preg_quote($page, '/') . ':`(.*?)`/s', $m[1], $cm)) {
    http_response_code(500);
    exit('Inhalt nicht gefunden.');
}
$content = $cm[1];

function vg_esc5($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

$label = $valid[$page];
// Die "Über uns"-Seite liegt unter der sprechenden URL /ueber-uns, intern
// heisst der LEGAL-Schluessel aber "ueber" (Objekt-Key ohne Bindestrich).
$urlPath = $page === 'ueber' ? 'ueber-uns' : $page;
$canonical = 'https://viceguide.de/' . $urlPath;
$pageTitle = $label . ' - ViceGuide';
$description = $page === 'ueber'
    ? 'Über ViceGuide: die deutschsprachige Anlaufstelle für GTA 6. Wer wir sind, wie wir arbeiten und wie du uns erreichst.'
    : $label . ' von ViceGuide, dem inoffiziellen, deutschsprachigen GTA-6-Fan-Portal.';

$head = [
    '<title>ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)</title>' =>
        '<title>' . vg_esc5($pageTitle) . '</title>',
    '<meta name="description" content="Der deutsche GTA-6-Hub: aktuelle News, Gerüchte und Leaks, dazu eine große Datenbank zu Charakteren, Fahrzeugen, Waffen und Orten. Alles zu GTA 6 an einem Ort.">' =>
        '<meta name="description" content="' . vg_esc5($description) . '">',
    '<link rel="canonical" href="https://viceguide.de/">' =>
        '<link rel="canonical" href="' . vg_esc5($canonical) . '">',
    '<meta property="og:title" content="ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)">' =>
        '<meta property="og:title" content="' . vg_esc5($pageTitle) . '">',
    '<meta property="og:description" content="Alles zu GTA 6 an einem Ort. Der deutsche Hub mit Datenbank, Guides und aktuellen News.">' =>
        '<meta property="og:description" content="' . vg_esc5($description) . '">',
    '<meta property="og:url" content="https://viceguide.de/">' =>
        '<meta property="og:url" content="' . vg_esc5($canonical) . '">',
    '<meta name="twitter:title" content="ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)">' =>
        '<meta name="twitter:title" content="' . vg_esc5($pageTitle) . '">',
    '<meta name="twitter:description" content="Alles zu GTA 6 an einem Ort. Der deutsche Hub mit Datenbank, Guides und aktuellen News.">' =>
        '<meta name="twitter:description" content="' . vg_esc5($description) . '">',
];
foreach ($head as $search => $replace) {
    $html = str_replace($search, $replace, $html);
}

// AboutPage-/Organization-/Person-JSON-LD nur fuer die "Über uns"-Seite
// (E-E-A-T: echter Betreiber, klare Organisation). Bewusst nur vor dem ERSTEN
// <style> eingefuegt, nie global (index.html enthaelt <style> auch als
// JS-String, siehe CLAUDE.md-Stolperfalle).
if ($page === 'ueber') {
    $ld = [
        '@context' => 'https://schema.org',
        '@type' => 'AboutPage',
        'url' => $canonical,
        'name' => 'Über ViceGuide',
        'inLanguage' => 'de',
        'mainEntity' => [
            '@type' => 'Organization',
            'name' => 'ViceGuide',
            'url' => 'https://viceguide.de/',
            'logo' => 'https://viceguide.de/logo.png',
            'description' => 'Inoffizielles, deutschsprachiges Fan-Portal rund um GTA 6 mit News, Datenbank und Guides.',
            'founder' => ['@type' => 'Person', 'name' => 'Eddy Hanné'],
        ],
    ];
    $ldTag = '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    $pos = strpos($html, '<style>');
    if ($pos !== false) {
        $html = substr($html, 0, $pos) . $ldTag . substr($html, $pos);
    }
}

// Bestehendes #legal-Overlay serverseitig sichtbar machen und mit dem Text
// aus LEGAL befuellen, genau das Element, das openLegal() spaeter clientseitig
// weiterbenutzt (kein zweites Markup-Geruest noetig).
$body = [
    '<div id="view"></div>' => '<div id="view" style="display:none"></div>',
    '<div class="ov" id="legal" onclick="if(event.target.id===\'legal\')closeLegal()"><div class="modal" style="max-width:640px"><div class="mbody">' =>
        '<div class="ov show" id="legal" onclick="if(event.target.id===\'legal\')closeLegal()"><div class="modal" style="max-width:640px"><div class="mbody">',
    '<div id="legal-content" class="legal-content"></div>' =>
        '<div id="legal-content" class="legal-content">' . $content . '</div>',
];
foreach ($body as $search => $replace) {
    $html = str_replace($search, $replace, $html);
}

echo $html;
