<?php
/*
 * Liefert den Avatar eines Creators als echte, oeffentliche Bild-URL aus.
 * Analog zu entry_image.php, nur fuer creators.avatar statt db_entries.img.
 *
 * GET ?id=<Creator-ID> -> Bilddaten mit passendem Content-Type
 */

require __DIR__ . '/db.php';
[$pdo, $cfg] = vg_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT avatar FROM creators WHERE id = ?');
$stmt->execute([$id]);
$row = $id ? $stmt->fetch() : false;

if (!$row || empty($row['avatar']) || !preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$#', $row['avatar'], $m)) {
    header('Location: https://viceguide.de/og-image.jpg', true, 302);
    exit;
}

$mime = $m[1];
$bytes = base64_decode($m[2]);

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: public, max-age=86400');
echo $bytes;
