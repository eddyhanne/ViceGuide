<?php
/*
 * Dynamische Sitemap: Startseite plus jeder Artikel unter seiner echten
 * URL (/artikel/{id}). Ersetzt die vorherige statische sitemap.xml, die nur
 * die Startseite enthalten konnte (siehe CLAUDE.md).
 */

require __DIR__ . '/api/db.php';
[$pdo, $cfg] = vg_db();

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

function vg_sitemap_date(?string $iso): string {
    if (!$iso || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) return date('Y-m-d');
    return "{$m[1]}-{$m[2]}-{$m[3]}";
}

$rows = $pdo->query('SELECT id, article_date FROM articles ORDER BY article_date DESC')->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo "  <url>\n    <loc>https://viceguide.de/</loc>\n    <changefreq>daily</changefreq>\n    <priority>1.0</priority>\n  </url>\n";
foreach ($rows as $r) {
    $loc = 'https://viceguide.de/artikel/' . htmlspecialchars($r['id'], ENT_QUOTES, 'UTF-8');
    $lastmod = vg_sitemap_date($r['article_date']);
    echo "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
}
echo '</urlset>' . "\n";
