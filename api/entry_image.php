<?php
/*
 * Liefert das Bild eines Datenbank-Eintrags als echte, oeffentliche Bild-URL
 * aus. Analog zu article_image.php, nur fuer db_entries statt articles.
 *
 * GET ?id=<interne Zeilen-ID> -> Bilddaten mit passendem Content-Type
 */

require __DIR__ . '/db.php';
[$pdo, $cfg] = vg_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT img FROM db_entries WHERE id = ?');
$stmt->execute([$id]);
$row = $id ? $stmt->fetch() : false;

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
