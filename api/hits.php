<?php
/*
 * Eigenes, cookiefreies Analytics fuer ViceGuide (kein Drittanbieter, kein
 * Consent-Banner noetig). Speichert bewusst NUR Pfad, Referrer und UTM-Werte,
 * keine IP, keine Client-ID, kein Cookie, kein Personenbezug. Reicht, um zu
 * sehen, ueber welche Quelle Besucher kommen (z.B. Instagram), aber nicht,
 * um einzelne Besucher wiederzuerkennen.
 *
 * POST {path, referrer?, utm_source?, utm_medium?, utm_campaign?} -> loggt
 *      einen Seitenaufruf. Oeffentlich, kein Login noetig, feuert bei jeder
 *      SPA-Navigation aus index.html (siehe syncHash()).
 * GET  ?days=30 -> aggregierte Auswertung (Admin). Top-Referrer, Top-UTM-
 *      Quellen, Verlauf pro Tag, dazu ein direkter Instagram-Ausschnitt.
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
[$pdo, $cfg] = vg_db();
$method = $_SERVER['REQUEST_METHOD'];

function vg_out_h($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function vg_body_h(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

if ($method === 'POST') {
    $b = vg_body_h();
    $path = trim((string)($b['path'] ?? ''));
    if ($path === '') vg_out_h(['error' => 'path fehlt'], 400);
    $path = substr($path, 0, 300);

    $referrer = trim((string)($b['referrer'] ?? ''));
    $referrer = $referrer !== '' ? substr($referrer, 0, 500) : null;

    $refHost = null;
    if ($referrer) {
        $h = parse_url($referrer, PHP_URL_HOST);
        if ($h) $refHost = substr(strtolower(preg_replace('/^www\./', '', $h)), 0, 190);
    }

    $utmSource = trim((string)($b['utm_source'] ?? ''));
    $utmMedium = trim((string)($b['utm_medium'] ?? ''));
    $utmCampaign = trim((string)($b['utm_campaign'] ?? ''));

    $st = $pdo->prepare('INSERT INTO hits (path, referrer, ref_host, utm_source, utm_medium, utm_campaign) VALUES (?, ?, ?, ?, ?, ?)');
    $st->execute([
        $path,
        $referrer,
        $refHost,
        $utmSource !== '' ? substr($utmSource, 0, 100) : null,
        $utmMedium !== '' ? substr($utmMedium, 0, 100) : null,
        $utmCampaign !== '' ? substr($utmCampaign, 0, 100) : null,
    ]);
    vg_out_h(['ok' => true]);
}

if ($method === 'GET') {
    vg_require_admin($cfg);

    $days = max(1, min(180, (int)($_GET['days'] ?? 30)));
    $isSqlite = str_starts_with($cfg['db_dsn'], 'sqlite:');
    $since = $isSqlite
        ? "datetime('now','-$days days')"
        : "DATE_SUB(NOW(), INTERVAL $days DAY)";

    $total = (int)$pdo->query("SELECT COUNT(*) c FROM hits WHERE created_at >= $since")->fetch()['c'];

    $byRef = $pdo->query("
        SELECT COALESCE(ref_host,'(direkt / kein Referrer)') AS ref_host, COUNT(*) AS c
        FROM hits WHERE created_at >= $since
        GROUP BY ref_host ORDER BY c DESC LIMIT 20
    ")->fetchAll();

    $byUtmSource = $pdo->query("
        SELECT utm_source, COUNT(*) AS c FROM hits
        WHERE created_at >= $since AND utm_source IS NOT NULL AND utm_source != ''
        GROUP BY utm_source ORDER BY c DESC LIMIT 20
    ")->fetchAll();

    $byUtmCampaign = $pdo->query("
        SELECT utm_source, utm_campaign, COUNT(*) AS c FROM hits
        WHERE created_at >= $since AND utm_campaign IS NOT NULL AND utm_campaign != ''
        GROUP BY utm_source, utm_campaign ORDER BY c DESC LIMIT 30
    ")->fetchAll();

    $dayExpr = $isSqlite ? "date(created_at)" : "DATE(created_at)";
    $perDay = $pdo->query("
        SELECT $dayExpr AS d, COUNT(*) AS c FROM hits
        WHERE created_at >= $since GROUP BY d ORDER BY d ASC
    ")->fetchAll();

    // Instagram eigens herausgezogen, weil das der aktuell interessierende Kanal ist.
    // Greift sowohl ueber echten Referrer (l.instagram.com / instagram.com, kommt
    // aber selten durch, IG haengt den Referrer meist ab) als auch ueber den
    // UTM-Tag im Bio-Link/Story-Sticker (der zuverlaessige Weg, siehe Hinweis unten).
    $instaByRef = (int)$pdo->query("
        SELECT COUNT(*) c FROM hits
        WHERE created_at >= $since AND ref_host LIKE '%instagram.com%'
    ")->fetch()['c'];
    $instaByUtm = (int)$pdo->query("
        SELECT COUNT(*) c FROM hits
        WHERE created_at >= $since AND LOWER(utm_source) = 'instagram'
    ")->fetch()['c'];

    vg_out_h([
        'days' => $days,
        'total' => $total,
        'instagram' => ['by_referrer' => $instaByRef, 'by_utm' => $instaByUtm],
        'top_referrers' => $byRef,
        'top_utm_sources' => $byUtmSource,
        'top_utm_campaigns' => $byUtmCampaign,
        'per_day' => $perDay,
        'note' => 'Instagram haengt den echten Referrer meist ab. by_utm (aus dem Bio-Link/Story-Sticker mit ?utm_source=instagram) ist der verlaessliche Wert, by_referrer nur zur Kontrolle.',
    ]);
}

vg_out_h(['error' => 'Methode nicht unterstuetzt'], 405);
