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

if ($method === 'DELETE') {
    vg_require_admin($cfg);
    $pdo->exec('DELETE FROM hits');
    vg_out_h(['ok' => true]);
}

if ($method === 'GET') {
    vg_require_admin($cfg);

    $days = max(1, min(180, (int)($_GET['days'] ?? 30)));
    $isSqlite = str_starts_with($cfg['db_dsn'], 'sqlite:');
    $since = $isSqlite
        ? "datetime('now','-$days days')"
        : "DATE_SUB(NOW(), INTERVAL $days DAY)";
    // Vorperiode gleicher Laenge direkt davor, fuer den Prozent-Vergleich
    // ("gegenueber Vorperiode"), wie bei Google Analytics/Search Console.
    $prevSince = $isSqlite
        ? "datetime('now','-" . (2 * $days) . " days')"
        : "DATE_SUB(NOW(), INTERVAL " . (2 * $days) . " DAY)";

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

    // Vorperiode zum Vergleich (gleiche Laenge, direkt davor).
    $prevTotal = (int)$pdo->query("SELECT COUNT(*) c FROM hits WHERE created_at >= $prevSince AND created_at < $since")->fetch()['c'];
    $prevInstaUtm = (int)$pdo->query("
        SELECT COUNT(*) c FROM hits
        WHERE created_at >= $prevSince AND created_at < $since AND LOWER(utm_source) IN ('instagram','ig')
    ")->fetch()['c'];

    $data = [
        'days' => $days,
        'total' => $total,
        'prev_total' => $prevTotal,
        'instagram' => ['by_referrer' => $instaByRef, 'by_utm' => $instaByUtm, 'prev_by_utm' => $prevInstaUtm],
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
    $w = 760; $h = 220; $padL = 34; $padR = 6; $padT = 14; $padB = 26;
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

    // Gitterlinien mit Skala (0, Mitte, Maximum), macht die Groessenordnung
    // auf einen Blick lesbar statt nur die Kurvenform zu zeigen.
    $grid = '';
    foreach ([0, 0.5, 1] as $frac) {
        $y = $padT + $plotH * (1 - $frac);
        $val = (int)round($max * $frac);
        $grid .= '<line x1="' . $padL . '" y1="' . round($y, 1) . '" x2="' . ($w - $padR) . '" y2="' . round($y, 1) . '" class="gridline"></line>'
               . '<text x="' . ($padL - 8) . '" y="' . round($y + 3, 1) . '" class="axislbl" text-anchor="end">' . $val . '</text>';
    }

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
    $lastIdx = $n - 1;
    if ($lastIdx % $step !== 0) {
        $axis .= '<text x="' . $pts[$lastIdx][0] . '" y="' . ($h - 6) . '" class="axislbl" text-anchor="middle">'
               . vg_e(date('d.m.', strtotime($pts[$lastIdx][3]))) . '</text>';
    }

    return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="linechart" preserveAspectRatio="none">'
         . $grid
         . '<polygon points="' . $areaStr . '" class="area"></polygon>'
         . '<polyline points="' . $lineStr . '" class="line"></polyline>'
         . $dots . $axis
         . '</svg>';
}

function vg_delta_html(int $cur, int $prev): string {
    if ($prev <= 0) {
        return $cur > 0 ? '<span class="delta up">▲ neu</span>' : '<span class="delta flat">ohne Vergleichswert</span>';
    }
    $pct = (int)round((($cur - $prev) / $prev) * 100);
    if ($pct > 0) return '<span class="delta up">▲ ' . $pct . '% ggue. Vorperiode</span>';
    if ($pct < 0) return '<span class="delta down">▼ ' . abs($pct) . '% ggue. Vorperiode</span>';
    return '<span class="delta flat">0% ggue. Vorperiode</span>';
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

    $topSourceLabel = $d['top_utm_sources'][0]['utm_source'] ?? 'noch keine';
    $topSourceCount = $d['top_utm_sources'][0]['c'] ?? 0;
    $topPathLabel = $d['top_paths'][0]['path'] ?? 'noch keine';
    $topPathCount = $d['top_paths'][0]['c'] ?? 0;

    // Kacheln als Datensatz statt Copy-Paste-HTML, damit Reihenfolge und
    // Drag&Drop-IDs an einer Stelle definiert sind.
    $cards = [
        'top_pages' => [
            'tag' => 'Verhalten',
            'title' => 'Top-Seiten',
            'help' => 'Welche Seiten/Artikel tatsaechlich aufgerufen wurden, unabhaengig davon woher der Besuch kam.',
            'body' => '<table>' . vg_bar_rows($d['top_paths'], 'path', $maxPath) . '</table>',
        ],
        'top_sources' => [
            'tag' => 'Akquise',
            'title' => 'Top-Quellen (UTM-Source)',
            'help' => 'Gruppiert nach dem Tag im geklickten Link, unabhaengig vom technischen Referrer.',
            'body' => '<table>' . vg_bar_rows($d['top_utm_sources'], 'utm_source', $maxSrc) . '</table>',
        ],
        'top_campaigns' => [
            'tag' => 'Akquise',
            'title' => 'Top-Kampagnen',
            'help' => 'Quelle und Kampagnenname zusammen, zeigt welcher einzelne Post/Link wie viel gebracht hat.',
            'body' => '<table>' . $campaignRows . '</table>',
        ],
        'top_referrers' => [
            'tag' => 'Akquise',
            'title' => 'Top-Referrer',
            'help' => 'Technische Herkunfts-Domain laut Browser, unabhaengig von jedem Tag. Zeigt auch Besuche ohne UTM-Link.',
            'body' => '<table>' . vg_bar_rows($d['top_referrers'], 'ref_host', $maxRef) . '</table>',
        ],
    ];
    $cardHtml = '';
    foreach ($cards as $id => $c) {
        $cardHtml .= '<div class="card" draggable="true" data-id="' . vg_e($id) . '">'
                   . '<div class="draghandle" title="Ziehen zum Verschieben">⠿</div>'
                   . '<h2>' . vg_e($c['title']) . ' <span class="cardtag">' . vg_e($c['tag']) . '</span></h2>'
                   . '<p class="help">' . vg_e($c['help']) . '</p>'
                   . $c['body']
                   . '</div>';
    }

    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
      . '<meta name="robots" content="noindex,nofollow">'
      . '<meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<title>ViceGuide Statistik</title><style>'
      // Echte Hellmodus-Variablen der Website (index.html, :root[data-theme="light"]),
      // damit diese interne Seite optisch zu ViceGuide passt statt einem
      // generischen Dashboard-Look zu folgen.
      . ':root{--bg:#FBF3E7;--bg-2:#F4E8D6;--surface:#FFFDFB;--text:#221041;--soft:#6B5E85;--accent:#D00059;--line:rgba(34,16,65,.12);--ok:#0F7A3D;--ok-bg:rgba(15,122,61,.1);--bad:#C0264B;--bad-bg:rgba(192,38,75,.1);}'
      . '*{box-sizing:border-box}'
      . 'body{margin:0;background:var(--bg);color:var(--text);font-family:"Inter",-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:24px 16px 60px}'
      . '.topbar{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px}'
      . 'h1{font-size:1.4rem;margin:0 0 4px;font-weight:700}'
      . '.sub{color:var(--soft);font-size:.85rem;margin:0;max-width:640px}'
      . '.resetbtn{background:var(--surface);color:var(--bad);border:1px solid var(--line);border-radius:20px;padding:7px 16px;font-size:.8rem;font-weight:600;cursor:pointer;white-space:nowrap}'
      . '.resetbtn:hover{background:var(--bad-bg)}'
      . '.tabs{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}'
      . '.tab{color:var(--soft);text-decoration:none;padding:6px 14px;border-radius:20px;border:1px solid var(--line);font-size:.85rem;background:var(--surface)}'
      . '.tab.active{background:var(--accent);color:#fff;border-color:var(--accent);font-weight:700}'
      . '.tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:24px}'
      . '.tile{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:16px 18px;box-shadow:0 8px 20px -14px rgba(34,16,65,.3)}'
      . '.tile .n{font-size:1.7rem;font-weight:700;color:var(--accent);line-height:1.2}'
      . '.tile .n.small{font-size:1.05rem;line-height:1.3;word-break:break-word}'
      . '.tile .l{font-size:.78rem;color:var(--text);font-weight:600;margin-top:4px}'
      . '.tile .l2{font-size:.7rem;color:var(--soft);margin-top:6px;line-height:1.4}'
      . '.delta{display:inline-block;font-size:.68rem;font-weight:700;margin-top:6px;padding:2px 7px;border-radius:10px}'
      . '.delta.up{color:var(--ok);background:var(--ok-bg)}'
      . '.delta.down{color:var(--bad);background:var(--bad-bg)}'
      . '.delta.flat{color:var(--soft);background:var(--bg-2)}'
      . '.sectionlbl{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--soft);font-weight:700;margin:22px 2px 10px}'
      . '.chartcard{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:18px 20px;margin-bottom:8px;box-shadow:0 8px 20px -14px rgba(34,16,65,.25)}'
      . '.chartcard h2{font-size:1rem;margin:0 0 2px;font-weight:700}'
      . '.chartcard .help{font-size:.75rem;color:var(--soft);margin:0 0 8px;line-height:1.5}'
      . '#vg-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start}'
      . '.card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:18px 20px 18px 34px;box-shadow:0 8px 20px -14px rgba(34,16,65,.25);position:relative;cursor:grab}'
      . '.card.dragging{opacity:.4}'
      . '.card.over{outline:2px dashed var(--accent);outline-offset:2px}'
      . '.draghandle{position:absolute;left:10px;top:18px;color:var(--soft);font-size:1rem;line-height:1}'
      . '.card h2{font-size:1rem;margin:0 0 2px;font-weight:700;display:flex;align-items:center;gap:8px;flex-wrap:wrap}'
      . '.cardtag{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--accent);background:rgba(208,0,89,.1);padding:2px 8px;border-radius:8px}'
      . '.card .help{font-size:.75rem;color:var(--soft);margin:0 0 14px;line-height:1.5}'
      . 'table{width:100%;border-collapse:collapse}'
      . 'td{padding:7px 4px;font-size:.85rem;vertical-align:middle;border-bottom:1px solid var(--line)}'
      . 'tr:last-child td{border-bottom:none}'
      . 'td.lbl{color:var(--text);white-space:nowrap;padding-right:12px;max-width:220px;overflow:hidden;text-overflow:ellipsis}'
      . 'td.num{text-align:right;color:var(--soft);font-variant-numeric:tabular-nums;padding-left:10px;white-space:nowrap;font-weight:600}'
      . 'td.barcell{width:100%}'
      . '.bar{height:8px;background:var(--accent);border-radius:4px;min-width:2px;opacity:.85}'
      . 'td.empty{color:var(--soft);font-style:italic;padding:10px 4px;border-bottom:none}'
      . '.note{color:var(--soft);font-size:.75rem;margin-top:16px;line-height:1.5}'
      . '.linechart{width:100%;height:220px;display:block}'
      . '.linechart .area{fill:var(--accent);opacity:.14}'
      . '.linechart .line{fill:none;stroke:var(--accent);stroke-width:2.5;stroke-linejoin:round}'
      . '.linechart .pt{fill:var(--surface);stroke:var(--accent);stroke-width:2}'
      . '.linechart .gridline{stroke:var(--line);stroke-width:1}'
      . '.linechart .axislbl{fill:var(--soft);font-size:9px}'
      . '</style></head><body>'
      . '<div class="topbar"><div><h1>ViceGuide Statistik</h1>'
      . '<p class="sub">Eigenes, cookiefreies Tracking. Zaehlt echte Seitenaufrufe (jeden Ansichtswechsel in der App), nicht jeden einzelnen Klick. Dein eigener Admin-Login zaehlt nicht mit.</p></div>'
      . '<button class="resetbtn" onclick="vgResetStats()">🗑 Alle Daten zuruecksetzen</button></div>'
      . '<div class="tabs">' . $tabs . '</div>'
      . '<div class="sectionlbl">Uebersicht</div>'
      . '<div class="tiles">'
      . '<div class="tile"><div class="n">' . $d['total'] . '</div><div class="l">Seitenaufrufe gesamt</div>' . vg_delta_html($d['total'], $d['prev_total']) . '</div>'
      . '<div class="tile"><div class="n">' . $d['instagram']['by_utm'] . '</div><div class="l">Instagram (per UTM-Tag)</div>' . vg_delta_html($d['instagram']['by_utm'], $d['instagram']['prev_by_utm']) . '</div>'
      . '<div class="tile"><div class="n small">' . vg_e($topSourceLabel) . '</div><div class="l">Top-Quelle (' . $topSourceCount . ')</div><div class="l2">Woher die meisten Besucher mit UTM-Tag kamen.</div></div>'
      . '<div class="tile"><div class="n small">' . vg_e($topPathLabel) . '</div><div class="l">Top-Seite (' . $topPathCount . ')</div><div class="l2">Am haeufigsten aufgerufene Seite/Artikel.</div></div>'
      . '</div>'
      . '<div class="chartcard"><h2>Verlauf pro Tag</h2><p class="help">Seitenaufrufe je Kalendertag, mit Gitterlinien-Skala. Punkt anfahren zeigt Datum und genaue Zahl.</p>' . $chart . '</div>'
      . '<div class="sectionlbl">Akquise &amp; Verhalten, Reihenfolge per Ziehen anpassbar</div>'
      . '<div id="vg-cards">' . $cardHtml . '</div>'
      . '<p class="note">' . vg_e($d['note']) . ' Die Kachel-Reihenfolge wird nur in diesem Browser gemerkt.</p>'
      . '</body>'
      . '<script>'
      . 'function vgResetStats(){'
      . 'if(!confirm("Wirklich ALLE bisher gesammelten Statistik-Daten unwiderruflich loeschen? Das kann nicht rueckgaengig gemacht werden."))return;'
      . 'fetch(location.pathname,{method:"DELETE"}).then(function(r){if(r.ok){location.reload();}else{alert("Loeschen fehlgeschlagen.");}}).catch(function(){alert("Loeschen fehlgeschlagen.");});'
      . '}'
      . '(function(){'
      . 'var box=document.getElementById("vg-cards");if(!box)return;'
      . 'var KEY="vg_stats_card_order";'
      . 'try{'
      . 'var saved=JSON.parse(localStorage.getItem(KEY)||"[]");'
      . 'saved.slice().reverse().forEach(function(id){'
      . 'var el=box.querySelector(\'[data-id="\'+id+\'"]\');'
      . 'if(el)box.insertBefore(el,box.firstChild);'
      . '});'
      . '}catch(e){}'
      . 'function saveOrder(){'
      . 'var ids=Array.prototype.map.call(box.children,function(c){return c.getAttribute("data-id");});'
      . 'try{localStorage.setItem(KEY,JSON.stringify(ids));}catch(e){}'
      . '}'
      . 'var dragEl=null;'
      . 'box.querySelectorAll(".card").forEach(function(card){'
      . 'card.addEventListener("dragstart",function(){dragEl=card;card.classList.add("dragging");});'
      . 'card.addEventListener("dragend",function(){card.classList.remove("dragging");box.querySelectorAll(".card").forEach(function(c){c.classList.remove("over");});saveOrder();});'
      . 'card.addEventListener("dragover",function(e){'
      . 'e.preventDefault();'
      . 'if(card===dragEl)return;'
      . 'var rect=card.getBoundingClientRect();'
      . 'var before=(e.clientY-rect.top)<rect.height/2;'
      . 'box.querySelectorAll(".card").forEach(function(c){c.classList.remove("over");});'
      . 'card.classList.add("over");'
      . 'if(before){box.insertBefore(dragEl,card);}else{box.insertBefore(dragEl,card.nextSibling);}'
      . '});'
      . '});'
      . '})();'
      . '</script>'
      . '</html>';
    exit;
}

vg_out_h(['error' => 'Methode nicht unterstuetzt'], 405);
