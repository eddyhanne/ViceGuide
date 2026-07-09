<?php
/*
 * Liefert das Titelbild eines Artikels als echte, oeffentliche Bild-URL aus.
 * Bilder liegen in der Datenbank nur als base64-Data-URI (siehe CLAUDE.md),
 * fuer og:image/twitter:image in Link-Vorschauen wird aber eine normal
 * abrufbare Bild-Adresse gebraucht, kein data:-URI. Dieser Endpunkt decodiert
 * das gespeicherte Bild einmal pro Aufruf und gibt die rohen Bytes zurueck.
 *
 * GET ?id=<artikel-id> -> Bilddaten mit passendem Content-Type
 */

require __DIR__ . '/db.php';
[$pdo, $cfg] = vg_db();

$id = preg_replace('/[^a-z0-9-]/', '', $_GET['id'] ?? '');

$stmt = $pdo->prepare('SELECT img FROM articles WHERE id = ?');
$stmt->execute([$id]);
$row = $id !== '' ? $stmt->fetch() : false;

if (!$row || empty($row['img']) || !preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$#', $row['img'], $m)) {
    header('Location: https://viceguide.de/og-image.jpg', true, 302);
    exit;
}

$mime = $m[1];
$bytes = base64_decode($m[2]);

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: public, max-age=86400');
echo $bytes;
