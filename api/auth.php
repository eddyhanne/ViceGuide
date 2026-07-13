<?php
/*
 * Login fuer den Editiermodus.
 *
 * GET               -> {loggedIn: true|false}
 * POST {password}   -> bei richtigem Passwort wird eine Sitzung gestartet (Cookie, 90 Tage)
 * DELETE            -> Sitzung beenden (abmelden)
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

[$pdo, $cfg] = vg_db();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Kein Session-Start fuer anonyme Besucher: der Status-Check laeuft bei
    // jedem Seitenaufbau, ein bedingungsloser Session-Start wuerde jedem
    // Besucher ein PHPSESSID-Cookie setzen und damit den Cache der Inhalts-
    // APIs fragmentieren. Ohne Cookie kann niemand eingeloggt sein.
    if (!empty($_COOKIE[session_name()])) vg_session_start();
    echo json_encode(['loggedIn' => !empty($_SESSION['vg_admin'])]);
    exit;
}

if ($method === 'POST') {
    vg_session_start();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $pw = (string)($body['password'] ?? '');
    if ($pw === '' || empty($cfg['admin_hash']) || !password_verify($pw, $cfg['admin_hash'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Falsches Passwort']);
        exit;
    }
    session_regenerate_id(true);
    $_SESSION['vg_admin'] = true;
    echo json_encode(['loggedIn' => true]);
    exit;
}

if ($method === 'DELETE') {
    if (!empty($_COOKIE[session_name()])) vg_session_start();
    $_SESSION = [];
    session_destroy();
    echo json_encode(['loggedIn' => false]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode nicht unterstuetzt']);
