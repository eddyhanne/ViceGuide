<?php
/*
 * Ereignis-Tracking als Ergaenzung zum Seitenaufruf-Zaehler (api/hits.php).
 * Weiterhin cookiefrei, anonym, aggregiert: keine IP, keine Client-ID, kein
 * Wiedererkennen. Erfasst nur zwei Signale fuer die Content-Qualitaet:
 *
 *  - type "search":  anonym mitgeschnittene interne Suchanfrage (q) plus die
 *                    Trefferzahl (num). Zeigt, wonach gesucht wird und was
 *                    null Treffer liefert (Content-Luecken).
 *  - type "engage":  Verweildauer (num = Sekunden) und maximale Scrolltiefe
 *                    (num2 = Prozent) auf einer Artikelseite (path).
 *
 * POST {type, q?, path?, results?, seconds?, depth?} -> loggt ein Ereignis.
 *      Oeffentlich, kein Login noetig, feuert per sendBeacon aus index.html.
 * DELETE (Admin) -> leert die Tabelle. Die Auswertung liefert api/hits.php.
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
[$pdo, $cfg] = vg_db();
$method = $_SERVER['REQUEST_METHOD'];

function vg_out_e($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true);
    if (!is_array($b)) $b = [];
    $type = (string)($b['type'] ?? '');
    if (!in_array($type, ['search', 'engage'], true)) vg_out_e(['error' => 'typ fehlt'], 400);

    if ($type === 'search') {
        $q = trim((string)($b['q'] ?? ''));
        if ($q === '') vg_out_e(['error' => 'q fehlt'], 400);
        $q = mb_substr($q, 0, 160);
        $num = max(0, (int)($b['results'] ?? 0));
        $st = $pdo->prepare('INSERT INTO events (type, q, num) VALUES (?, ?, ?)');
        $st->execute(['search', $q, $num]);
        vg_out_e(['ok' => true]);
    }

    // engage
    $path = trim((string)($b['path'] ?? ''));
    if ($path === '') vg_out_e(['error' => 'path fehlt'], 400);
    $path = substr($path, 0, 300);
    $seconds = max(0, min(1800, (int)($b['seconds'] ?? 0))); // Deckel gegen Idle-Tab-Aufblaehung
    $depth = max(0, min(100, (int)($b['depth'] ?? 0)));
    $st = $pdo->prepare('INSERT INTO events (type, path, num, num2) VALUES (?, ?, ?, ?)');
    $st->execute(['engage', $path, $seconds, $depth]);
    vg_out_e(['ok' => true]);
}

if ($method === 'DELETE') {
    vg_require_admin($cfg);
    $pdo->exec('DELETE FROM events');
    vg_out_e(['ok' => true]);
}

vg_out_e(['error' => 'Methode nicht unterstützt'], 405);
