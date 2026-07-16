<?php
/*
 * Gemeinsame Ratgeber-Zuordnung fuer die SSR-Fassaden (hub.php, section.php).
 * Spiegel der Konstante RATGEBER in index.html (Client). Beide Stellen muessen
 * uebereinstimmen: hier fuer Crawler/no-JS, in index.html fuer die App.
 * Ein neuer Ratgeber-Artikel gehoert in beide Listen.
 */
return [
    ['key' => 'kauf',      'label' => 'Kaufberatung',          'ids' => ['gta-6-vorbestellen', 'gta-6-guenstig-kaufen', 'editionen-zu-release', 'ultimate-edition-paywall', 'physische-edition-ohne-disc', 'vorbesteller-boni', 'gta-6-konsolen-bundles-amazon']],
    ['key' => 'plattform', 'label' => 'Plattformen & Technik', 'ids' => ['plattformen-zu-release', 'wann-pc-version', 'gta-6-pc-version-warum-spaeter', 'gta-6-cloud-gaming', 'gta-6-ps5-vs-ps5-pro-unterschied', 'wetter-technik-engine', 'gta-6-ps5-emulatoren-sharpemu-pc']],
    ['key' => 'release',   'label' => 'Rund um den Release',   'ids' => ['gta-6-uhrzeit-freischaltung', 'gta-6-preload-termin-speicherplatz', 'gta-6-altersfreigabe-usk']],
    ['key' => 'features',  'label' => 'Features & Modi',       'ids' => ['gta-6-online-modus', 'gta-6-offline-spielen', 'gta-6-splitscreen-koop', 'gta-online-zukunft-nach-gta-6', 'gta-6-crossplay-crossprogression', 'gta-6-cheats-was-bekannt', 'gta-6-deutsche-synchronisation']],
];
