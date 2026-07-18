<?php
/*
 * Serverseitig gerenderte Startseite (/). Bisher wurde fuer / die nackte
 * index.html ausgeliefert: leeres #view, ein leeres <h1>, die Navigation
 * entsteht erst per JavaScript. Suchmaschinen sahen damit auf der wichtigsten
 * Seite praktisch keinen Inhalt und kaum interne Links (SEO-Audit, Juli 2026).
 *
 * Diese Fassade spritzt einen echten, crawlbaren Startblock in #view: ein
 * sichtbares H1, eine kurze Portal-Beschreibung und echte <a href>-Links zu
 * News, Datenbank-Hub, allen acht Kategorie-Rubriken, den neuesten Artikeln
 * und dem Plattform-Pillar. Die SPA ersetzt #view beim Laden wie gewohnt
 * (Progressive Enhancement), fuer Nutzer aendert sich optisch nichts. Head,
 * Title, Canonical und OG-Tags von index.html bleiben unveraendert, sie zeigen
 * bereits korrekt auf https://viceguide.de/.
 */

require __DIR__ . '/cache.php';
vg_cache_serve(600);

require __DIR__ . '/api/db.php';
[$pdo, $cfg] = vg_db();

function vg_esc_home($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

$articles = [];
try {
    $articles = $pdo->query('SELECT id, title FROM articles ORDER BY article_date DESC LIMIT 10')->fetchAll();
} catch (Throwable $e) {
    $articles = [];
}

// Deutsche Rubrik-Praefixe fuer die Datenbank-Hubs (siehe .htaccess, sitemap.php).
$hubs = [
    ['charaktere', 'Charaktere'], ['fahrzeuge', 'Fahrzeuge'], ['waffen', 'Waffen'],
    ['wildtiere', 'Wildtiere'], ['gangs', 'Gangs'], ['radio', 'Radio'],
    ['aktivitaeten', 'Aktivitäten'], ['orte', 'Orte'],
];

ob_start();
?>
<section class="ssr-home">
  <h1 class="sr-only">ViceGuide: Der deutsche GTA-6-Hub mit Datenbank, Guides und News</h1>
  <p>ViceGuide ist die deutsche GTA-6-Zentrale: aktuelle News, Gerüchte und Leaks, eine große Datenbank zu Charakteren, Fahrzeugen, Waffen und Orten von Leonida, dazu ab dem Release am 19. November 2026 die Guides. Alles zu Grand Theft Auto VI auf Deutsch.</p>
  <p class="ssr-lead-cta">Neu hier? Der beste Einstieg ist unser großer Überblick: <a href="/artikel/gta-6-alles-was-wir-wissen">GTA 6: Alles was wir wissen zu Release, Map und Story</a>.</p>
  <nav aria-label="Hauptbereiche" class="ssr-nav">
    <a href="/news">News &amp; Leaks</a>
    <a href="/datenbank/">Datenbank</a>
    <a href="/guides/">Guides</a>
    <a href="/karte">Karte</a>
    <a href="/community">Community</a>
  </nav>
  <h2>Datenbank zu GTA 6</h2>
  <ul class="ssr-list">
<?php foreach ($hubs as $h): ?>    <li><a href="/<?= $h[0] ?>/"><?= vg_esc_home($h[1]) ?></a></li>
<?php endforeach; ?>  </ul>
<?php if ($articles): ?>  <h2>Aktuelle News und Analysen</h2>
  <ul class="ssr-list">
<?php foreach ($articles as $a): ?>    <li><a href="/artikel/<?= vg_esc_home($a['id']) ?>"><?= vg_esc_home($a['title']) ?></a></li>
<?php endforeach; ?>  </ul>
<?php endif; ?>  <p>Im Fokus: <a href="/artikel/plattformen-zu-release">GTA 6 Plattformen: Für welche Konsolen es erscheint</a>. Der Release ist der 19. November 2026 für PS5 und Xbox Series X/S.</p>
</section>
<?php
$block = ob_get_clean();

$html = file_get_contents(__DIR__ . '/index.html');
$html = str_replace('<div id="view"></div>', '<div id="view">' . $block . '</div>', $html);
echo $html;
