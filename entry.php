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

/* Meta-Description sauber kuerzen: bevorzugt an einem Satzende, sonst an einer
   Wortgrenze, nie mitten im Wort (fruehere harte 155-Zeichen-Kappung schnitt
   Woerter ab und endete mit … im Wort). Liefert '' zurueck, wenn kein Text da
   ist, der Aufrufer setzt dann seinen Fallback. */
function vg_meta_desc(string $desc): string {
    $desc = trim(preg_replace('/\s+/', ' ', $desc));
    if ($desc === '' || mb_strlen($desc) <= 155) return $desc;
    $cut = mb_substr($desc, 0, 155);
    if (preg_match('/^(.*[.!?])\s/u', $cut, $m) && mb_strlen($m[1]) >= 80) return $m[1];
    $sp = mb_strrpos($cut, ' ');
    if ($sp !== false && $sp >= 80) return rtrim(mb_substr($cut, 0, $sp)) . '…';
    return rtrim($cut) . '…';
}

$secInfo = VG_SECTION_MAP[$section];
$name = $row['name'];
$sub = trim($row['sub'] ?? '');
$desc = trim($row['description'] ?? '');
$summary = vg_meta_desc($desc);
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
    '<title>ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)</title>' =>
        '<title>' . vg_esc2($pageTitle) . '</title>',
    '<meta name="description" content="Der deutsche GTA-6-Hub: aktuelle News, Gerüchte und Leaks, dazu eine große Datenbank zu Charakteren, Fahrzeugen, Waffen und Orten. Alles zu GTA 6 an einem Ort.">' =>
        '<meta name="description" content="' . vg_esc2($summary) . '">',
    '<link rel="canonical" href="https://viceguide.de/">' =>
        '<link rel="canonical" href="' . vg_esc2($canonical) . '">',
    '<meta property="og:title" content="ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)">' =>
        '<meta property="og:title" content="' . vg_esc2($pageTitle) . '">',
    '<meta property="og:description" content="Alles zu GTA 6 an einem Ort. Der deutsche Hub mit Datenbank, Guides und aktuellen News.">' =>
        '<meta property="og:description" content="' . vg_esc2($summary) . '">',
    '<meta property="og:url" content="https://viceguide.de/">' =>
        '<meta property="og:url" content="' . vg_esc2($canonical) . '">',
    '<meta property="og:image" content="https://viceguide.de/og-image.jpg">' =>
        '<meta property="og:image" content="' . vg_esc2($imgUrl) . '">',
    '<meta name="twitter:title" content="ViceGuide: GTA 6 Hub mit Datenbank, News & Guides (Deutsch)">' =>
        '<meta name="twitter:title" content="' . vg_esc2($pageTitle) . '">',
    '<meta name="twitter:description" content="Alles zu GTA 6 an einem Ort. Der deutsche Hub mit Datenbank, Guides und aktuellen News.">' =>
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
// Selektive Indexierung: nur redaktionell freigegebene Eintraege (seo_index=1)
// bleiben indexierbar. Alle anderen bekommen noindex, follow (Self-Canonical
// bleibt, fuer Nutzer weiterhin normal erreichbar, aus der Sitemap raus, siehe
// sitemap.php). Kein zusaetzliches robots.txt-Blocken, sonst kann Google das
// noindex gar nicht erst lesen.
if (empty($row['seo_index'])) {
    $head['<meta name="robots" content="index, follow">'] =
        '<meta name="robots" content="noindex, follow">';
}
foreach ($head as $search => $replace) {
    $html = str_replace($search, $replace, $html);
}

// JSON-LD kurz vor dem <style>-Block einfuegen. Spezifischerer Schema-Typ, wo
// er ohne Fehlmodellierung passt: Charaktere als Person, Orte als Place, der
// Rest bleibt beim generischen Thing (Fahrzeuge/Waffen/Wildtiere/Radio etc.
// haben keinen sauber passenden, breit unterstuetzten Typ).
$schemaType = $section === 'characters' ? 'Person' : ($section === 'locations' ? 'Place' : 'Thing');
$entryLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => $schemaType,
    'name' => $name,
    'description' => $summary,
    'image' => $imgUrl,
    'url' => $canonical,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
// BreadcrumbList: Startseite > [Rubrik] > [Eintrag].
$breadcrumbLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => 'https://viceguide.de/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $secInfo['label'], 'item' => 'https://viceguide.de/' . $secInfo['prefix'] . '/'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $name, 'item' => $canonical],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
/* Nur vor dem ersten <style> (Head-CSS) einfuegen, sonst landet ein </script>
   in weiteren <style>-Vorkommen (u.a. in einem JS-String) und beendet das
   Haupt-<script> vorzeitig. Siehe ausfuehrlicher Kommentar in article.php. */
$ldInject = '<script type="application/ld+json">' . $entryLd . '</script>' . "\n"
    . '<script type="application/ld+json">' . $breadcrumbLd . '</script>' . "\n";
$stylePos = strpos($html, '<style>');
if ($stylePos !== false) {
    $html = substr($html, 0, $stylePos) . $ldInject . substr($html, $stylePos);
}

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

// Sichtbare Breadcrumb mit echten Links, deckungsgleich zum BreadcrumbList-Markup.
$crumb = '<nav class="db-crumb"><a href="/">Startseite</a> › <a href="/' . $secInfo['prefix'] . '/">' . vg_esc2($secInfo['label'])
    . '</a> › <span>' . vg_esc2($name) . '</span></nav>';

// Interne Verlinkung: weitere Eintraege derselben Rubrik plus der Rubrik-Hub.
// Gibt jeder (indexierbaren) Detailseite crawlbare Wege zu verwandten Inhalten,
// statt sie nur ueber die Sitemap erreichbar zu lassen (SEO-Audit, Punkt E).
$sibHtml = '';
try {
    $sib = $pdo->prepare("SELECT name, slug FROM db_entries WHERE section = ? AND slug IS NOT NULL AND slug <> '' AND id <> ? ORDER BY sort_order LIMIT 6");
    $sib->execute([$section, (int)$row['id']]);
    $sibs = $sib->fetchAll();
    if ($sibs) {
        $lis = '';
        foreach ($sibs as $s) {
            $lis .= '<li><a href="/' . $secInfo['prefix'] . '/' . vg_esc2($s['slug']) . '">' . vg_esc2($s['name']) . '</a></li>';
        }
        $sibHtml = '<div class="mabout">Mehr aus ' . vg_esc2($secInfo['label']) . '</div><ul class="db-rel">' . $lis
            . '</ul><p><a href="/' . $secInfo['prefix'] . '/">Alle ' . vg_esc2($secInfo['label']) . ' ansehen</a></p>';
    }
} catch (Throwable $e) { $sibHtml = ''; }

$modalInner =
    ($hasImg ? '<img src="' . vg_esc2($imgUrl) . '" alt="' . vg_esc2($name) . '" style="width:100%;border-radius:16px 16px 0 0">' : '') .
    '<div class="mbody">' . $crumb . '<h1>' . vg_esc2($name) . '</h1><div class="msub">' . vg_esc2($sub) . '</div>' .
    $chips . $fieldsHtml .
    '<div class="mabout">Beschreibung</div>' . $descHtml . $sibHtml .
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
