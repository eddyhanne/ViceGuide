<?php
/*
 * Liefert das Bild einer Rubrik als echte, oeffentliche Bild-URL aus.
 * Analog zu article_image.php / entry_image.php, nur fuer section_meta.
 *
 * GET ?section=<id> -> Bilddaten mit passendem Content-Type
 */

require __DIR__ . '/db.php';
[$pdo, $cfg] = vg_db();

$section = (string)($_GET['section'] ?? '');

$stmt = $pdo->prepare('SELECT img FROM section_meta WHERE section = ?');
$stmt->execute([$section]);
$row = $section !== '' ? $stmt->fetch() : false;

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
