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

    $data = [
        'days' => $days,
        'total' => $total,
        'instagram' => ['by_referrer' => $instaByRef, 'by_utm' => $instaByUtm],
        'top_referrers' => $byRef,
        'top_utm_sources' => $byUtmSource,
        'top_utm_campaigns' => $byUtmCampaign,
        'per_day' => $perDay,
        'note' => 'Instagram haengt den echten Referrer meist ab. by_utm (aus dem Bio-Link/Story-Sticker mit ?utm_source=instagram) ist der verlaessliche Wert, by_referrer nur zur Kontrolle.',
    ];

    // Rohes JSON weiterhin erreichbar (z.B. fuer spaetere Automatisierung),
    // Standard beim normalen Browser-Aufruf ist aber die lesbare HTML-Ansicht.
    if (($_GET['format'] ?? '') === 'json') {
        vg_out_h($data);
    }
    vg_render_html($data);
    exit;
}

/* ---- Lesbare HTML-Ansicht ---- */
function vg_e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function vg_bar_rows(array $rows, string|array $labelKey, int $max): string {
    if (!$rows) return '<tr><td colspan="3" class="empty">Noch keine Daten in diesem Zeitraum.</td></tr>';
    $out = '';
    foreach ($rows as $r) {
        $label = is_array($labelKey) ? implode(' / ', array_map(fn($k) => $r[$k], $labelKey)) : $r[$labelKey];
        $c = (int)$r['c'];
        $pct = $max > 0 ? round($c / $max * 100) : 0;
        $out .= '<tr><td class="lbl">' . vg_e($label) . '</td>'
              . '<td class="barcell"><div class="bar" style="width:' . $pct . '%"></div></td>'
              . '<td class="num">' . $c . '</td></tr>';
    }
    return $out;
}

function vg_render_html(array $d): never {
    header('Content-Type: text/html; charset=utf-8');
    $days = $d['days'];
    $ranges = [1 => 'Heute', 7 => '7 Tage', 30 => '30 Tage', 90 => '90 Tage'];
    $tabs = '';
    foreach ($ranges as $n => $label) {
        $active = $n === $days ? ' class="tab active"' : ' class="tab"';
        $tabs .= '<a href="?days=' . $n . '"' . $active . '>' . vg_e($label) . '</a>';
    }

    $maxRef = max(array_column($d['top_referrers'], 'c') ?: [0]);
    $maxSrc = max(array_column($d['top_utm_sources'], 'c') ?: [0]);
    $maxCmp = max(array_column($d['top_utm_campaigns'], 'c') ?: [0]);
    $maxDay = max(array_column($d['per_day'], 'c') ?: [0]);

    $dayRows = '';
    foreach (array_reverse($d['per_day']) as $r) {
        $pct = $maxDay > 0 ? round($r['c'] / $maxDay * 100) : 0;
        $dayRows .= '<tr><td class="lbl">' . vg_e($r['d']) . '</td>'
                  . '<td class="barcell"><div class="bar" style="width:' . $pct . '%"></div></td>'
                  . '<td class="num">' . (int)$r['c'] . '</td></tr>';
    }
    if (!$dayRows) $dayRows = '<tr><td colspan="3" class="empty">Noch keine Daten in diesem Zeitraum.</td></tr>';

    $campaignRows = vg_bar_rows($d['top_utm_campaigns'], ['utm_source', 'utm_campaign'], $maxCmp);

    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
      . '<meta name="robots" content="noindex,nofollow">'
      . '<title>ViceGuide Statistik</title><style>'
      . ':root{--bg:#1A0B2E;--card:#241242;--text:#FDF3E6;--soft:#c9bcd9;--accent:#FF2D95;}'
      . '*{box-sizing:border-box}'
      . 'body{margin:0;background:var(--bg);color:var(--text);font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:24px 16px 60px}'
      . 'h1{font-size:1.3rem;margin:0 0 4px}'
      . '.sub{color:var(--soft);font-size:.85rem;margin:0 0 20px}'
      . '.tabs{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}'
      . '.tab{color:var(--soft);text-decoration:none;padding:6px 14px;border-radius:20px;border:1px solid #3a2a55;font-size:.85rem}'
      . '.tab.active{background:var(--accent);color:#1A0B2E;border-color:var(--accent);font-weight:700}'
      . '.tiles{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:28px}'
      . '.tile{background:var(--card);border-radius:12px;padding:16px 20px;min-width:150px;flex:1}'
      . '.tile .n{font-size:1.8rem;font-weight:700;color:var(--accent)}'
      . '.tile .l{font-size:.78rem;color:var(--soft);margin-top:2px}'
      . '.tile .l2{font-size:.7rem;color:#8a7a9e;margin-top:6px;line-height:1.4}'
      . '.card{background:var(--card);border-radius:12px;padding:18px 20px;margin-bottom:20px}'
      . '.card h2{font-size:.95rem;margin:0 0 14px;color:var(--text)}'
      . 'table{width:100%;border-collapse:collapse}'
      . 'td{padding:6px 4px;font-size:.85rem;vertical-align:middle}'
      . 'td.lbl{color:var(--text);white-space:nowrap;padding-right:12px;max-width:220px;overflow:hidden;text-overflow:ellipsis}'
      . 'td.num{text-align:right;color:var(--soft);font-variant-numeric:tabular-nums;padding-left:10px;white-space:nowrap}'
      . 'td.barcell{width:100%}'
      . '.bar{height:8px;background:var(--accent);border-radius:4px;min-width:2px}'
      . 'td.empty{color:#8a7a9e;font-style:italic;padding:10px 4px}'
      . '.note{color:var(--soft);font-size:.75rem;margin-top:-6px;margin-bottom:20px;line-height:1.5}'
      . '</style></head><body>'
      . '<h1>ViceGuide Statistik</h1>'
      . '<p class="sub">Eigenes, cookiefreies Tracking. Nur echte Besucher, dein eigener Login zaehlt nicht mit.</p>'
      . '<div class="tabs">' . $tabs . '</div>'
      . '<div class="tiles">'
      . '<div class="tile"><div class="n">' . $d['total'] . '</div><div class="l">Seitenaufrufe gesamt</div></div>'
      . '<div class="tile"><div class="n">' . $d['instagram']['by_utm'] . '</div><div class="l">Instagram (per UTM-Tag)</div><div class="l2">Zaehlt Klicks ueber Bio-Link/Story mit ?utm_source=instagram, das ist der verlaessliche Wert.</div></div>'
      . '<div class="tile"><div class="n">' . $d['instagram']['by_referrer'] . '</div><div class="l">Instagram (per Referrer)</div><div class="l2">Nur zur Kontrolle, Instagram haengt den echten Referrer meist ab.</div></div>'
      . '</div>'
      . '<div class="card"><h2>Verlauf pro Tag</h2><table>' . $dayRows . '</table></div>'
      . '<div class="card"><h2>Top-Quellen (UTM-Source)</h2><table>' . vg_bar_rows($d['top_utm_sources'], 'utm_source', $maxSrc) . '</table></div>'
      . '<div class="card"><h2>Top-Kampagnen (Quelle / Kampagne)</h2><table>' . $campaignRows . '</table></div>'
      . '<div class="card"><h2>Top-Referrer (echte Herkunfts-Domain)</h2><table>' . vg_bar_rows($d['top_referrers'], 'ref_host', $maxRef) . '</table></div>'
      . '<p class="note">' . vg_e($d['note']) . '</p>'
      . '</body></html>';
    exit;
}

vg_out_h(['error' => 'Methode nicht unterstuetzt'], 405);
