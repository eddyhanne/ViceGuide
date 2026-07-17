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

    /* Bekannte Schreibweisen derselben Quelle zusammenfassen (z.B. "ig" aus
       fruehen Tests vor der /ig-Weiterleitung), analog zu canonCat()/
       DB_CAT_ALIAS im Frontend: nur die Anzeige normalisieren, die
       gespeicherten Rohdaten bleiben unangetastet. */
    $srcNorm = "CASE WHEN LOWER(utm_source) IN ('ig') THEN 'instagram' ELSE utm_source END";

    $byUtmSource = $pdo->query("
        SELECT $srcNorm AS utm_source, COUNT(*) AS c FROM hits
        WHERE created_at >= $since AND utm_source IS NOT NULL AND utm_source != ''
        GROUP BY $srcNorm ORDER BY c DESC LIMIT 20
    ")->fetchAll();

    $byUtmCampaign = $pdo->query("
        SELECT $srcNorm AS utm_source, utm_campaign, COUNT(*) AS c FROM hits
        WHERE created_at >= $since AND utm_campaign IS NOT NULL AND utm_campaign != ''
        GROUP BY $srcNorm, utm_campaign ORDER BY c DESC LIMIT 30
    ")->fetchAll();

    // Welche Seiten wirklich aufgerufen wurden, getrennt von der Frage woher
    // die Besucher kamen (Quelle/Referrer).
    $byPath = $pdo->query("
        SELECT path, COUNT(*) AS c FROM hits
        WHERE created_at >= $since
        GROUP BY path ORDER BY c DESC LIMIT 20
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
        WHERE created_at >= $since AND LOWER(utm_source) IN ('instagram','ig')
    ")->fetch()['c'];

    $data = [
        'days' => $days,
        'total' => $total,
        'instagram' => ['by_referrer' => $instaByRef, 'by_utm' => $instaByUtm],
        'top_referrers' => $byRef,
        'top_utm_sources' => $byUtmSource,
        'top_utm_campaigns' => $byUtmCampaign,
        'top_paths' => $byPath,
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

/* Liniendiagramm im Stil der Google Search Console: durchgehende Zeitachse
   (fehlende Tage werden als 0 aufgefuellt, sonst waeren Luecken im Verlauf
   unsichtbar zusammengezogen statt als Einbruch erkennbar), gefuellte Flaeche
   unter der Linie, Punkte mit nativem Hover-Tooltip (SVG <title>, kein JS
   noetig). */
function vg_line_chart(array $perDay, int $days): string {
    $counts = [];
    foreach ($perDay as $r) { $counts[$r['d']] = (int)$r['c']; }
    $labels = []; $values = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $labels[] = $d;
        $values[] = $counts[$d] ?? 0;
    }
    $n = count($values);
    $max = max(max($values), 1);
    $w = 760; $h = 200; $padL = 6; $padR = 6; $padT = 14; $padB = 26;
    $plotW = $w - $padL - $padR; $plotH = $h - $padT - $padB;

    $pts = [];
    for ($i = 0; $i < $n; $i++) {
        $x = $n > 1 ? $padL + ($i / ($n - 1)) * $plotW : $padL + $plotW / 2;
        $y = $padT + $plotH - ($values[$i] / $max) * $plotH;
        $pts[] = [round($x, 1), round($y, 1), $values[$i], $labels[$i]];
    }
    $lineStr = implode(' ', array_map(fn($p) => $p[0] . ',' . $p[1], $pts));
    $baseline = $padT + $plotH;
    $areaStr = $lineStr . ' ' . $pts[$n - 1][0] . ',' . $baseline . ' ' . $pts[0][0] . ',' . $baseline;

    $dots = '';
    foreach ($pts as $p) {
        $dateFmt = date('d.m.', strtotime($p[3]));
        $dots .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="3" class="pt">'
               . '<title>' . vg_e($dateFmt) . ': ' . $p[2] . ' Aufrufe</title></circle>';
    }

    $labelCount = min(7, $n);
    $step = max(1, (int)round(($n - 1) / max(1, $labelCount - 1)));
    $axis = '';
    for ($i = 0; $i < $n; $i += $step) {
        $axis .= '<text x="' . $pts[$i][0] . '" y="' . ($h - 6) . '" class="axislbl" text-anchor="middle">'
               . vg_e(date('d.m.', strtotime($pts[$i][3]))) . '</text>';
    }
    // letzten Tag garantiert mit beschriften, auch wenn er nicht exakt auf den Schrittraster faellt.
    $lastIdx = $n - 1;
    if ($lastIdx % $step !== 0) {
        $axis .= '<text x="' . $pts[$lastIdx][0] . '" y="' . ($h - 6) . '" class="axislbl" text-anchor="middle">'
               . vg_e(date('d.m.', strtotime($pts[$lastIdx][3]))) . '</text>';
    }

    return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="linechart">'
         . '<polygon points="' . $areaStr . '" class="area"></polygon>'
         . '<polyline points="' . $lineStr . '" class="line"></polyline>'
         . $dots . $axis
         . '</svg>';
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
    $maxPath = max(array_column($d['top_paths'], 'c') ?: [0]);

    $campaignRows = vg_bar_rows($d['top_utm_campaigns'], ['utm_source', 'utm_campaign'], $maxCmp);
    $chart = vg_line_chart($d['per_day'], $days);

    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
      . '<meta name="robots" content="noindex,nofollow">'
      . '<meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<title>ViceGuide Statistik</title><style>'
      // Echte Hellmodus-Variablen der Website (index.html, :root[data-theme="light"]),
      // damit diese interne Seite optisch zu ViceGuide passt statt einem
      // generischen Dashboard-Look zu folgen.
      . ':root{--bg:#FBF3E7;--bg-2:#F4E8D6;--surface:#FFFDFB;--text:#221041;--soft:#6B5E85;--accent:#D00059;--line:rgba(34,16,65,.12);}'
      . '*{box-sizing:border-box}'
      . 'body{margin:0;background:var(--bg);color:var(--text);font-family:"Inter",-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:24px 16px 60px}'
      . 'h1{font-family:"Oswald",Impact,sans-serif;font-size:1.4rem;margin:0 0 4px;letter-spacing:.02em}'
      . '.sub{color:var(--soft);font-size:.85rem;margin:0 0 20px}'
      . '.tabs{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}'
      . '.tab{color:var(--soft);text-decoration:none;padding:6px 14px;border-radius:20px;border:1px solid var(--line);font-size:.85rem;background:var(--surface)}'
      . '.tab.active{background:var(--accent);color:#fff;border-color:var(--accent);font-weight:700}'
      . '.tiles{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px}'
      . '.tile{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:16px 20px;min-width:150px;flex:1;box-shadow:0 8px 20px -14px rgba(34,16,65,.3)}'
      . '.tile .n{font-size:1.8rem;font-weight:700;color:var(--accent)}'
      . '.tile .l{font-size:.78rem;color:var(--text);font-weight:600;margin-top:2px}'
      . '.tile .l2{font-size:.7rem;color:var(--soft);margin-top:6px;line-height:1.4}'
      . '.card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:18px 20px;margin-bottom:20px;box-shadow:0 8px 20px -14px rgba(34,16,65,.25)}'
      . '.card h2{font-family:"Oswald",Impact,sans-serif;font-size:1rem;margin:0 0 2px;font-weight:600;letter-spacing:.01em}'
      . '.card .help{font-size:.75rem;color:var(--soft);margin:0 0 14px;line-height:1.5}'
      . 'table{width:100%;border-collapse:collapse}'
      . 'td{padding:7px 4px;font-size:.85rem;vertical-align:middle;border-bottom:1px solid var(--line)}'
      . 'tr:last-child td{border-bottom:none}'
      . 'td.lbl{color:var(--text);white-space:nowrap;padding-right:12px;max-width:220px;overflow:hidden;text-overflow:ellipsis}'
      . 'td.num{text-align:right;color:var(--soft);font-variant-numeric:tabular-nums;padding-left:10px;white-space:nowrap;font-weight:600}'
      . 'td.barcell{width:100%}'
      . '.bar{height:8px;background:var(--accent);border-radius:4px;min-width:2px;opacity:.85}'
      . 'td.empty{color:var(--soft);font-style:italic;padding:10px 4px;border-bottom:none}'
      . '.note{color:var(--soft);font-size:.75rem;margin-top:-6px;margin-bottom:20px;line-height:1.5}'
      . '.linechart{width:100%;height:auto;display:block}'
      . '.linechart .area{fill:var(--accent);opacity:.14}'
      . '.linechart .line{fill:none;stroke:var(--accent);stroke-width:2.5;stroke-linejoin:round}'
      . '.linechart .pt{fill:var(--surface);stroke:var(--accent);stroke-width:2}'
      . '.linechart .axislbl{fill:var(--soft);font-size:9px}'
      . '</style></head><body>'
      . '<h1>ViceGuide Statistik</h1>'
      . '<p class="sub">Eigenes, cookiefreies Tracking. Zaehlt echte Seitenaufrufe (jeden Ansichtswechsel in der App), nicht jeden einzelnen Klick. Dein eigener Admin-Login zaehlt nicht mit.</p>'
      . '<div class="tabs">' . $tabs . '</div>'
      . '<div class="tiles">'
      . '<div class="tile"><div class="n">' . $d['total'] . '</div><div class="l">Seitenaufrufe gesamt</div></div>'
      . '<div class="tile"><div class="n">' . $d['instagram']['by_utm'] . '</div><div class="l">Instagram (per UTM-Tag)</div><div class="l2">Ueber Bio-Link/Story mit utm_source=instagram, das ist der verlaessliche Wert.</div></div>'
      . '<div class="tile"><div class="n">' . $d['instagram']['by_referrer'] . '</div><div class="l">Instagram (per Referrer)</div><div class="l2">Nur zur Kontrolle, Instagram unterdrueckt diese Info meistens.</div></div>'
      . '</div>'
      . '<div class="card"><h2>Verlauf pro Tag</h2><p class="help">Seitenaufrufe je Kalendertag im gewaehlten Zeitraum.</p>' . $chart . '</div>'
      . '<div class="card"><h2>Top-Seiten</h2><p class="help">Welche Seiten/Artikel tatsaechlich aufgerufen wurden, unabhaengig davon woher der Besuch kam.</p><table>' . vg_bar_rows($d['top_paths'], 'path', $maxPath) . '</table></div>'
      . '<div class="card"><h2>Top-Quellen (UTM-Source)</h2><p class="help">Gruppiert nach dem Tag im geklickten Link, unabhaengig vom technischen Referrer.</p><table>' . vg_bar_rows($d['top_utm_sources'], 'utm_source', $maxSrc) . '</table></div>'
      . '<div class="card"><h2>Top-Kampagnen</h2><p class="help">Quelle und Kampagnenname zusammen, zeigt welcher einzelne Post/Link wie viel gebracht hat.</p><table>' . $campaignRows . '</table></div>'
      . '<div class="card"><h2>Top-Referrer</h2><p class="help">Technische Herkunfts-Domain laut Browser, komplett unabhaengig von jedem Tag. Zeigt auch Besuche ohne UTM-Link (z.B. geteilte Links ohne Tag).</p><table>' . vg_bar_rows($d['top_referrers'], 'ref_host', $maxRef) . '</table></div>'
      . '<p class="note">' . vg_e($d['note']) . '</p>'
      . '</body></html>';
    exit;
}

vg_out_h(['error' => 'Methode nicht unterstuetzt'], 405);
