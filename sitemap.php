<?php
/*
 * Dynamische Sitemap: Startseite, jeder Artikel unter seiner echten URL
 * (/artikel/{id}) und jeder Datenbank-Eintrag unter seiner echten URL
 * (/charaktere/{slug}, /fahrzeuge/{slug}, ...). Ersetzt die vorherige
 * statische sitemap.xml, die nur die Startseite enthalten konnte (siehe
 * CLAUDE.md).
 */

require __DIR__ . '/cache.php';
vg_cache_serve(3600, 'application/xml; charset=utf-8');

require __DIR__ . '/api/db.php';
[$pdo, $cfg] = vg_db();

header('Content-Type: application/xml; charset=utf-8');

const VG_SITEMAP_SECTION_PREFIX = [
    'characters' => 'charaktere',
    'vehicles'   => 'fahrzeuge',
    'weapons'    => 'waffen',
    'wildlife'   => 'wildtiere',
    'gangs'      => 'gangs',
    'radio'      => 'radio',
    'activities' => 'aktivitaeten',
    'locations'  => 'orte',
];

function vg_sitemap_date(?string $iso): string {
    if (!$iso || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) return date('Y-m-d');
    return "{$m[1]}-{$m[2]}-{$m[3]}";
}

$articles = $pdo->query('SELECT id, article_date, updated_at FROM articles ORDER BY article_date DESC')->fetchAll();

vg_ensure_entry_slugs($pdo);
try {
    // Nur indexierbare (redaktionell freigegebene) Eintraege in die Sitemap,
    // noindex-Seiten gehoeren nicht hinein (siehe entry.php, seo_index).
    $entries = $pdo->query("SELECT section, slug, updated_at FROM db_entries WHERE slug IS NOT NULL AND slug <> '' AND seo_index = 1 ORDER BY section, sort_order")->fetchAll();
} catch (Throwable $e) {
    $entries = [];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo "  <url>\n    <loc>https://viceguide.de/</loc>\n    <changefreq>daily</changefreq>\n    <priority>1.0</priority>\n  </url>\n";
echo "  <url>\n    <loc>https://viceguide.de/news</loc>\n    <changefreq>daily</changefreq>\n    <priority>0.9</priority>\n  </url>\n";
// News-Rubriken mit eigener URL (nur wenn nicht-Ratgeber-Artikel der cat existieren).
try {
    $rgBlocks = require __DIR__ . '/ratgeber_data.php';
    $rgIds = [];
    foreach ($rgBlocks as $b) { foreach ($b['ids'] as $id) { $rgIds[$id] = true; } }
    $catHas = [];
    foreach ($pdo->query('SELECT id, cat FROM articles')->fetchAll() as $r) {
        if (isset($rgIds[$r['id']])) continue;
        $catHas[$r['cat']] = true;
    }
    $newsSlug = ['news' => 'updates', 'leaks' => 'leaks', 'trailer' => 'trailer', 'story' => 'story', 'map' => 'map', 'community' => 'community'];
    foreach ($newsSlug as $cat => $slug) {
        if (empty($catHas[$cat])) continue;
        echo "  <url>\n    <loc>https://viceguide.de/news/{$slug}</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.7</priority>\n  </url>\n";
    }
} catch (Throwable $e) {}
echo "  <url>\n    <loc>https://viceguide.de/datenbank/</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.9</priority>\n  </url>\n";
echo "  <url>\n    <loc>https://viceguide.de/ratgeber/</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
echo "  <url>\n    <loc>https://viceguide.de/leaks-chronik/</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
// /guides/ ist bis Release nur ein Platzhalter (noindex, siehe hub.php), daher nicht in der Sitemap.
foreach (VG_SITEMAP_SECTION_PREFIX as $prefix) {
    echo "  <url>\n    <loc>https://viceguide.de/{$prefix}/</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.7</priority>\n  </url>\n";
}
echo "  <url>\n    <loc>https://viceguide.de/ueber-uns</loc>\n    <changefreq>monthly</changefreq>\n    <priority>0.5</priority>\n  </url>\n";
foreach (['impressum', 'datenschutz'] as $legalPage) {
    echo "  <url>\n    <loc>https://viceguide.de/{$legalPage}</loc>\n    <changefreq>yearly</changefreq>\n    <priority>0.2</priority>\n  </url>\n";
}
// karte ist ein Platzhalter (noindex, siehe section.php), daher nicht in der Sitemap.
foreach (['videos', 'community'] as $sectionPage) {
    echo "  <url>\n    <loc>https://viceguide.de/{$sectionPage}</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.5</priority>\n  </url>\n";
}
foreach ($articles as $r) {
    $loc = 'https://viceguide.de/artikel/' . htmlspecialchars($r['id'], ENT_QUOTES, 'UTF-8');
    $lastmod = vg_sitemap_date($r['updated_at'] ?: $r['article_date']);
    echo "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
}
foreach ($entries as $r) {
    $prefix = VG_SITEMAP_SECTION_PREFIX[$r['section']] ?? null;
    if (!$prefix) continue;
    $loc = 'https://viceguide.de/' . $prefix . '/' . htmlspecialchars($r['slug'], ENT_QUOTES, 'UTF-8');
    $lastmod = vg_sitemap_date($r['updated_at']);
    echo "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n  </url>\n";
}
// Creator-Seiten: nur aktive UND fuer Google freigegebene (seo_index=1). Das
// Beispiel-/Vorschauprofil (seo=0) bleibt draussen, ebenso die Uebersicht,
// solange kein echter Partner indexierbar ist.
try {
    $creators = $pdo->query("SELECT slug, updated_at FROM creators WHERE active = 1 AND seo_index = 1 ORDER BY sort_order")->fetchAll();
} catch (Throwable $e) {
    $creators = [];
}
if ($creators) {
    echo "  <url>\n    <loc>https://viceguide.de/creator/</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.6</priority>\n  </url>\n";
    foreach ($creators as $r) {
        $loc = 'https://viceguide.de/creator/' . htmlspecialchars($r['slug'], ENT_QUOTES, 'UTF-8');
        $lastmod = vg_sitemap_date($r['updated_at']);
        echo "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n  </url>\n";
    }
}
echo '</urlset>' . "\n";
