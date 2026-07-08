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

[$pdo, $cfg] = vg_db();
vg_session_start();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(['loggedIn' => !empty($_SESSION['vg_admin'])]);
    exit;
}

if ($method === 'POST') {
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
    $_SESSION = [];
    session_destroy();
    echo json_encode(['loggedIn' => false]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode nicht unterstuetzt']);
