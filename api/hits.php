<?php
/*
 * Eigenes, cookiefreies Analytics für ViceGuide (kein Drittanbieter, kein
 * Consent-Banner nötig). Speichert bewusst NUR Pfad, Referrer und UTM-Werte,
 * keine IP, keine Client-ID, kein Cookie, kein Personenbezug. Reicht, um zu
 * sehen, über welche Quelle Besucher kommen (z.B. Instagram), aber nicht,
 * um einzelne Besucher wiederzuerkennen.
 *
 * POST {path, referrer?, utm_source?, utm_medium?, utm_campaign?} -> loggt
 *      einen Seitenaufruf. Oeffentlich, kein Login nötig, feuert bei jeder
 *      SPA-Navigation aus index.html (siehe syncHash()).
 * GET  (Admin) -> interaktives Dashboard (HTML) oder, mit ?format=json, die
 *      aggregierten Rohdaten. Zeitraum über ?days=N ODER ?from=YYYY-MM-DD&
 *      to=YYYY-MM-DD (Berliner Kalendertage, inklusive). Die Zeitreihe kommt
 *      stundengenau in Europe/Berlin zurück, der Client bucketet daraus Tag,
 *      6-Stunden- oder Stundenansicht und den 24-Stunden-Tagesverlauf.
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
    try { $pdo->exec('DELETE FROM events'); } catch (Throwable $e) {}
    vg_out_h(['ok' => true]);
}

if ($method === 'GET') {
    vg_require_admin($cfg);
    if (($_GET['format'] ?? '') === 'json') {
        vg_out_h(vg_build_stats($pdo, $cfg));
    }
    vg_render_shell();
    exit;
}

vg_out_h(['error' => 'Methode nicht unterstützt'], 405);

/* ---------------------------------------------------------------------------
 * Datenaufbereitung
 * ------------------------------------------------------------------------- */

/* Bekannte Schreibweisen derselben Herkunft zu einer Zeile zusammenfassen, nur
   für die Anzeige, die Rohdaten bleiben unangetastet (analog canonCat() im
   Frontend). Google (google.com, search.google.com, news.google.com, google.de
   usw.) und Instagram (instagram.com, l.instagram.com, lm.instagram.com) landen
   jeweils in einer Zeile. */
function vg_ref_group(?string $host): string {
    if ($host === null || $host === '') return '(direkt / kein Referrer)';
    $h = strtolower($host);
    if (strpos($h, 'google') !== false) return 'Google';
    if (strpos($h, 'instagram') !== false) return 'Instagram';
    return $host;
}

/* Kanal-Gruppierung wie bei Google Analytics: jeder Aufruf wird aus Referrer
   und UTM-Tag in Suche / Social / Verweis / Direkt einsortiert. Das ist die
   Kernfrage fürs SEO ("kommt der Traffic aus der Suche?"). */
function vg_search_hosts(): array {
    return ['google', 'bing', 'duckduckgo', 'ecosia', 'yahoo', 'yandex', 'qwant', 'startpage', 'brave', 'search.'];
}
function vg_channel(?string $host, string $us): string {
    $socialUtm = ['instagram', 'ig', 'facebook', 'fb', 'meta', 'tiktok', 'youtube', 'yt', 'twitter', 'x', 'threads', 'reddit', 'pinterest', 'linkedin', 'snapchat'];
    $socialHosts = ['instagram', 'facebook', 'fb.', 'tiktok', 'youtube', 'youtu.be', 't.co', 'twitter', 'x.com', 'threads', 'reddit', 'pinterest', 'linkedin', 'snapchat', 'lm.facebook'];
    if ($us !== '' && in_array($us, $socialUtm, true)) return 'social';
    $h = $host ? strtolower($host) : '';
    if ($h !== '') {
        if ($h === 'viceguide.de') return 'internal'; // interne Klicks von Seite zu Seite, nicht als fremder Verweis zaehlen
        foreach (vg_search_hosts() as $s) if (strpos($h, $s) !== false) return 'search';
        foreach ($socialHosts as $s) if (strpos($h, $s) !== false) return 'social';
        return 'referral';
    }
    if ($us !== '') return 'referral'; // getaggter Link ohne Referrer-Host (z.B. Newsletter)
    return 'direct';
}

function vg_build_stats(PDO $pdo, array $cfg): array {
    $isSqlite = str_starts_with($cfg['db_dsn'], 'sqlite:');
    $berlin = new DateTimeZone('Europe/Berlin');
    $utc = new DateTimeZone('UTC');

    $preset = (string)($_GET['preset'] ?? '');
    $fromP = (string)($_GET['from'] ?? '');
    $toP = (string)($_GET['to'] ?? '');
    $custom = (bool)(preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromP) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toP));

    if ($preset === 'yesterday') {
        // Gestern als voller Kalendertag (Berlin), für den direkten
        // Tag-zu-Tag-Vergleich. Vorperiode ist dann automatisch vorgestern.
        $custom = false;
        $today0 = (new DateTime('now', $berlin))->setTime(0, 0, 0);
        $endB = clone $today0;                       // heute 00:00 = exklusives Ende
        $startB = (clone $today0)->modify('-1 day'); // gestern 00:00
        $days = 1;
    } elseif ($custom) {
        $startB = new DateTime($fromP . ' 00:00:00', $berlin);
        $endB = new DateTime($toP . ' 00:00:00', $berlin);
        if ($endB < $startB) { $tmp = $startB; $startB = $endB; $endB = $tmp; }
        $endB = (clone $endB)->modify('+1 day'); // exklusives Ende, "to" ist inklusive
        $days = (int)round(($endB->getTimestamp() - $startB->getTimestamp()) / 86400);
        $days = max(1, min(370, $days));
    } else {
        $days = max(1, min(180, (int)($_GET['days'] ?? 30)));
        $end0 = (new DateTime('now', $berlin))->setTime(0, 0, 0)->modify('+1 day');
        $endB = $end0;
        $startB = (clone $endB)->modify('-' . $days . ' day');
    }
    $prevEndB = clone $startB;
    $prevStartB = (clone $startB)->modify('-' . $days . ' day');

    $toUtc = fn(DateTime $d) => (clone $d)->setTimezone($utc)->format('Y-m-d H:i:s');
    $startU = $toUtc($startB); $endU = $toUtc($endB);
    $prevStartU = $toUtc($prevStartB); $prevEndU = $toUtc($prevEndB);

    $run = function (string $sql, array $params) use ($pdo) {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st;
    };
    $count = function (string $where, array $params) use ($run): int {
        return (int)$run("SELECT COUNT(*) c FROM hits WHERE $where", $params)->fetch()['c'];
    };

    $total = $count('created_at >= ? AND created_at < ?', [$startU, $endU]);
    $prevTotal = $count('created_at >= ? AND created_at < ?', [$prevStartU, $prevEndU]);

    // Stundengenaue Zeitreihe. In der DB liegt created_at als UTC, hier auf
    // Europe/Berlin umgerechnet und in eine luckenlose Stundenreihe gefüllt
    // (fehlende Stunden als 0), damit der Client sauber weiterbucketen kann.
    $hourExpr = $isSqlite
        ? "strftime('%Y-%m-%d %H:00:00', created_at)"
        : "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')";
    $hrows = $run("SELECT $hourExpr AS t, COUNT(*) AS c FROM hits WHERE created_at >= ? AND created_at < ? GROUP BY t", [$startU, $endU])->fetchAll();
    $bcounts = [];
    foreach ($hrows as $r) {
        $dt = new DateTime($r['t'], $utc);
        $dt->setTimezone($berlin);
        $k = $dt->format('Y-m-d H');
        $bcounts[$k] = ($bcounts[$k] ?? 0) + (int)$r['c'];
    }
    $series = [];
    $cur = clone $startB;
    $guard = 0;
    while ($cur < $endB && $guard < 200000) {
        $k = $cur->format('Y-m-d H');
        $series[] = ['t' => $k, 'c' => $bcounts[$k] ?? 0];
        $cur->modify('+1 hour');
        $guard++;
    }

    // Vorperioden-Zeitreihe (gleiche Länge, direkt davor), parallel zur
    // aktuellen Reihe für den Overlay-Vergleich. Als reines Zahlen-Array,
    // Index i deckt sich mit series[i].
    $phrows = $run("SELECT $hourExpr AS t, COUNT(*) AS c FROM hits WHERE created_at >= ? AND created_at < ? GROUP BY t", [$prevStartU, $prevEndU])->fetchAll();
    $pbc = [];
    foreach ($phrows as $r) {
        $dt = new DateTime($r['t'], $utc); $dt->setTimezone($berlin);
        $k = $dt->format('Y-m-d H');
        $pbc[$k] = ($pbc[$k] ?? 0) + (int)$r['c'];
    }
    $prevSeries = [];
    $pcur = clone $prevStartB; $pg = 0;
    while ($pcur < $prevEndB && $pg < 200000) {
        $prevSeries[] = $pbc[$pcur->format('Y-m-d H')] ?? 0;
        $pcur->modify('+1 hour'); $pg++;
    }

    $topPaths = $run("SELECT path, COUNT(*) c FROM hits WHERE created_at >= ? AND created_at < ? GROUP BY path ORDER BY c DESC LIMIT 25", [$startU, $endU])->fetchAll();
    // Vorperiode je Seite für Trend-Kennzeichnung im Leaderboard.
    $prevPathRows = $run("SELECT path, COUNT(*) c FROM hits WHERE created_at >= ? AND created_at < ? GROUP BY path", [$prevStartU, $prevEndU])->fetchAll();
    $prevPathMap = [];
    foreach ($prevPathRows as $r) { $prevPathMap[$r['path']] = (int)$r['c']; }
    foreach ($topPaths as &$tp) { $tp['prev'] = $prevPathMap[$tp['path']] ?? 0; }
    unset($tp);

    // Aufrufe der Akquise-Seiten (/creator/... und /partner), damit sichtbar
    // wird, ob ein verschickter Link wirklich geoeffnet wurde. Eigene Sektion,
    // weil creator.php und partner.html eigenstaendig sind und der Name hinter
    // /creator/ interessiert.
    $creatorPages = $run("SELECT path, COUNT(*) c FROM hits WHERE created_at >= ? AND created_at < ? AND (path LIKE '/creator%' OR path LIKE '/partner%') GROUP BY path ORDER BY c DESC LIMIT 50", [$startU, $endU])->fetchAll();

    // Einzelne Aufrufe mit Uhrzeit (Protokoll). created_at liegt in UTC vor,
    // wird hier nach Europe/Berlin umgerechnet und minutengenau formatiert.
    $fmtHits = function(array $rows) use ($utc, $berlin) {
        foreach ($rows as &$r) {
            $dt = new DateTime($r['created_at'], $utc); $dt->setTimezone($berlin);
            $r['ts'] = $dt->format('d.m.Y, H:i:s');
        }
        unset($r);
        return $rows;
    };
    $creatorHits = $fmtHits($run("SELECT created_at, path, ref_host, utm_source FROM hits WHERE created_at >= ? AND created_at < ? AND (path LIKE '/creator%' OR path LIKE '/partner%') ORDER BY created_at DESC LIMIT 500", [$startU, $endU])->fetchAll());
    $recentHits = $fmtHits($run("SELECT created_at, path, ref_host, utm_source FROM hits WHERE created_at >= ? AND created_at < ? ORDER BY created_at DESC LIMIT 300", [$startU, $endU])->fetchAll());

    // Kanal-Gruppierung für aktuelle Periode und Vorperiode.
    $chan = function (string $a, string $b) use ($run): array {
        $rows = $run("SELECT ref_host, LOWER(COALESCE(utm_source,'')) us, COUNT(*) c FROM hits WHERE created_at >= ? AND created_at < ? GROUP BY ref_host, LOWER(COALESCE(utm_source,''))", [$a, $b])->fetchAll();
        $o = ['search' => 0, 'social' => 0, 'referral' => 0, 'internal' => 0, 'direct' => 0];
        foreach ($rows as $r) { $o[vg_channel($r['ref_host'], (string)$r['us'])] += (int)$r['c']; }
        return $o;
    };
    $channelsCur = $chan($startU, $endU);
    $channelsPrev = $chan($prevStartU, $prevEndU);

    // Einstiegsseiten aus der Suche: welche Seite holt Besucher aus Suchmaschinen rein.
    $searchLike = implode(' OR ', array_map(fn($s) => "ref_host LIKE '%" . $s . "%'", vg_search_hosts()));
    $entriesSearch = $run("SELECT path, COUNT(*) c FROM hits WHERE created_at >= ? AND created_at < ? AND ($searchLike) GROUP BY path ORDER BY c DESC LIMIT 15", [$startU, $endU])->fetchAll();

    // Ereignis-Auswertung (interne Suche, Engagement). Tabelle kann auf einem
    // frischen Stand fehlen, daher defensiv.
    $searches = []; $engRows = []; $engTotals = ['c' => 0, 'avg_sec' => 0, 'avg_depth' => 0];
    try {
        $sr = $run("SELECT q, COUNT(*) c, MAX(num) maxres FROM events WHERE type='search' AND created_at >= ? AND created_at < ? GROUP BY q ORDER BY c DESC LIMIT 20", [$startU, $endU])->fetchAll();
        foreach ($sr as $r) { $searches[] = ['q' => $r['q'], 'c' => (int)$r['c'], 'maxres' => (int)$r['maxres']]; }
        $er = $run("SELECT path, COUNT(*) c, AVG(num) avg_sec, AVG(num2) avg_depth FROM events WHERE type='engage' AND created_at >= ? AND created_at < ? GROUP BY path ORDER BY c DESC LIMIT 20", [$startU, $endU])->fetchAll();
        foreach ($er as $r) { $engRows[] = ['path' => $r['path'], 'c' => (int)$r['c'], 'avg_sec' => (int)round((float)$r['avg_sec']), 'avg_depth' => (int)round((float)$r['avg_depth'])]; }
        $et = $run("SELECT COUNT(*) c, AVG(num) avg_sec, AVG(num2) avg_depth FROM events WHERE type='engage' AND created_at >= ? AND created_at < ?", [$startU, $endU])->fetch();
        if ($et) $engTotals = ['c' => (int)$et['c'], 'avg_sec' => (int)round((float)$et['avg_sec']), 'avg_depth' => (int)round((float)$et['avg_depth'])];
    } catch (Throwable $e) { /* Tabelle noch nicht vorhanden */ }

    // Referrer roh holen und in PHP zusammenfassen (Google/Instagram-Varianten).
    $refRows = $run("SELECT ref_host, COUNT(*) c FROM hits WHERE created_at >= ? AND created_at < ? GROUP BY ref_host", [$startU, $endU])->fetchAll();
    $refMerged = [];
    foreach ($refRows as $r) {
        $g = vg_ref_group($r['ref_host']);
        $refMerged[$g] = ($refMerged[$g] ?? 0) + (int)$r['c'];
    }
    arsort($refMerged);
    $topRef = [];
    foreach ($refMerged as $host => $c) { $topRef[] = ['ref_host' => $host, 'c' => $c]; }
    $topRef = array_slice($topRef, 0, 20);

    $srcNorm = "CASE WHEN LOWER(utm_source) IN ('ig') THEN 'instagram' ELSE utm_source END";
    $topSrc = $run("SELECT $srcNorm AS utm_source, COUNT(*) c FROM hits WHERE created_at >= ? AND created_at < ? AND utm_source IS NOT NULL AND utm_source != '' GROUP BY $srcNorm ORDER BY c DESC LIMIT 20", [$startU, $endU])->fetchAll();
    $topCmp = $run("SELECT $srcNorm AS utm_source, utm_campaign, COUNT(*) c FROM hits WHERE created_at >= ? AND created_at < ? AND utm_campaign IS NOT NULL AND utm_campaign != '' GROUP BY $srcNorm, utm_campaign ORDER BY c DESC LIMIT 30", [$startU, $endU])->fetchAll();

    $instaByRef = $count("created_at >= ? AND created_at < ? AND ref_host LIKE '%instagram%'", [$startU, $endU]);
    $instaByUtm = $count("created_at >= ? AND created_at < ? AND LOWER(utm_source) IN ('instagram','ig')", [$startU, $endU]);
    $prevInstaUtm = $count("created_at >= ? AND created_at < ? AND LOWER(utm_source) IN ('instagram','ig')", [$prevStartU, $prevEndU]);

    return [
        'range' => [
            'from' => $startB->format('Y-m-d'),
            'to' => (clone $endB)->modify('-1 day')->format('Y-m-d'),
            'days' => $days,
            'custom' => $custom,
            'preset' => $preset === 'yesterday' ? 'yesterday' : null,
        ],
        'total' => $total,
        'prev_total' => $prevTotal,
        'instagram' => ['by_referrer' => $instaByRef, 'by_utm' => $instaByUtm, 'prev_by_utm' => $prevInstaUtm],
        'channels' => ['cur' => $channelsCur, 'prev' => $channelsPrev],
        'entries_search' => $entriesSearch,
        'searches' => $searches,
        'engagement' => ['rows' => $engRows, 'totals' => $engTotals],
        'series' => $series,
        'prev_series' => $prevSeries,
        'top_paths' => $topPaths,
        'creator_pages' => $creatorPages,
        'creator_hits' => $creatorHits,
        'recent_hits' => $recentHits,
        'top_referrers' => $topRef,
        'top_utm_sources' => $topSrc,
        'top_utm_campaigns' => $topCmp,
        'note' => 'Instagram hängt den echten Referrer meist ab. Der Wert "Instagram (per UTM-Tag)" aus dem Bio-Link/Story-Sticker (?utm_source=instagram) ist verlässlich, der Referrer nur zur Kontrolle. Google und Instagram sind in den Referrern jeweils zu einer Zeile zusammengefasst.',
    ];
}

/* ---------------------------------------------------------------------------
 * Dashboard-Hülle. Statisches HTML plus CSS/JS, die Daten holt der Client
 * selbst per ?format=json, damit Zeitraum, Granularität und Tagesdetail ohne
 * Server-Reload wechseln können.
 * ------------------------------------------------------------------------- */
function vg_render_shell(): void {
    header('Content-Type: text/html; charset=utf-8');
    echo <<<'HTML'
<!doctype html><html lang="de"><head><meta charset="utf-8">
<meta name="robots" content="noindex,nofollow">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ViceGuide Statistik</title>
<style>
:root{--bg:#FBF3E7;--bg-2:#F4E8D6;--surface:#FFFDFB;--text:#221041;--soft:#6B5E85;--accent:#D00059;--accent-soft:rgba(208,0,89,.12);--line:rgba(34,16,65,.12);--ok:#0F7A3D;--ok-bg:rgba(15,122,61,.1);--bad:#C0264B;--bad-bg:rgba(192,38,75,.1);}
*{box-sizing:border-box}
html{overflow-y:scroll}
body{margin:0;background:var(--bg);color:var(--text);font-family:"Inter",-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:24px 16px 60px}
.topbar{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px}
h1{font-size:1.4rem;margin:0 0 4px;font-weight:700}
.sub{color:var(--soft);font-size:.85rem;margin:0;max-width:640px;line-height:1.5}
.resetbtn{background:var(--surface);color:var(--bad);border:1px solid var(--line);border-radius:20px;padding:7px 16px;font-size:.8rem;font-weight:600;cursor:pointer;white-space:nowrap}
.resetbtn:hover{background:var(--bad-bg)}
.controls{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:14px 16px;margin-bottom:20px;box-shadow:0 8px 20px -16px rgba(34,16,65,.3);display:flex;flex-wrap:wrap;align-items:center;gap:18px}
.ctrlgrp{display:flex;flex-direction:column;gap:6px}
.ctrllbl{font-size:.66rem;text-transform:uppercase;letter-spacing:.06em;color:var(--soft);font-weight:700}
.tabs{display:flex;gap:6px;flex-wrap:wrap}
.tab{color:var(--soft);cursor:pointer;padding:6px 14px;border-radius:20px;border:1px solid var(--line);font-size:.85rem;background:var(--surface);user-select:none}
.tab:hover{border-color:var(--accent)}
.tab.active{background:var(--accent);color:#fff;border-color:var(--accent);font-weight:700}
.daterow{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
input[type=date]{font-family:inherit;font-size:.85rem;padding:6px 10px;border:1px solid var(--line);border-radius:10px;background:var(--surface);color:var(--text)}
.applybtn{background:var(--accent);color:#fff;border:none;border-radius:10px;padding:7px 16px;font-size:.82rem;font-weight:700;cursor:pointer}
.applybtn.ghost{background:var(--surface);color:var(--accent);border:1px solid var(--accent)}
.seg{display:inline-flex;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:var(--surface)}
.seg button{border:none;background:transparent;color:var(--soft);font-family:inherit;font-size:.82rem;padding:6px 14px;cursor:pointer;font-weight:600}
.seg button.active{background:var(--accent);color:#fff}
.sep{color:var(--soft)}
.sectionlbl{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--soft);font-weight:700;margin:22px 2px 10px}
.tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:8px}
.tile{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:16px 18px;box-shadow:0 8px 20px -14px rgba(34,16,65,.3)}
.tile .n{font-size:1.7rem;font-weight:700;color:var(--accent);line-height:1.2}
.tile .n.small{font-size:1.05rem;line-height:1.3;word-break:break-word}
.tile .nrow{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}
.tile .ncmp{font-size:.9rem;font-weight:600;color:var(--soft);line-height:1.2}
.tile .l{font-size:.78rem;color:var(--text);font-weight:600;margin-top:4px}
.tile .l2{font-size:.7rem;color:var(--soft);margin-top:6px;line-height:1.4}
.delta{display:inline-block;font-size:.68rem;font-weight:700;margin-top:6px;padding:2px 7px;border-radius:10px}
.delta.up{color:var(--ok);background:var(--ok-bg)}
.delta.down{color:var(--bad);background:var(--bad-bg)}
.delta.flat{color:var(--soft);background:var(--bg-2)}
.chartcard{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:18px 20px;margin-bottom:14px;box-shadow:0 8px 20px -14px rgba(34,16,65,.25)}
.charthead{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:4px}
.chartcard h2{font-size:1rem;margin:0;font-weight:700}
.chartcard .help{font-size:.75rem;color:var(--soft);margin:2px 0 10px;line-height:1.5}
.chartscroll{overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch}
.bars{display:block}
.bars .gl{stroke:var(--line);stroke-width:1}
.bars .yl{fill:var(--soft);font-size:10px}
.bars .xl{fill:var(--soft);font-size:10px}
.bars .xs{fill:var(--soft);font-size:8.5px;opacity:.8}
.bars .vl{fill:var(--soft);font-size:9px;font-weight:600}
.bars .gbar{fill:var(--accent);opacity:.85;transition:opacity .1s}
.bars .hit:hover + .gbar,.bars .gbar:hover{opacity:1}
.bars .hit.clk{cursor:pointer}
.bars .cmpline{fill:none;stroke:var(--text);stroke-width:1.6;stroke-dasharray:4 3;opacity:.45}
.bars .cmpdot{fill:var(--text);opacity:.45}
.bars.heat .hmlbl{fill:var(--soft);font-size:10px}
.bars.heat .hmcell{cursor:default}
.headctrls{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.toggle{border:1px solid var(--line);background:var(--surface);color:var(--soft);font-family:inherit;font-size:.8rem;font-weight:600;padding:6px 12px;border-radius:20px;cursor:pointer}
.toggle.on{background:var(--accent);color:#fff;border-color:var(--accent)}
.leg{display:inline-flex;align-items:center;gap:6px;margin-left:10px;color:var(--soft)}
.legbar{display:inline-block;width:11px;height:11px;border-radius:2px;background:var(--accent);opacity:.85;margin-right:2px}
.legline{display:inline-block;width:16px;border-top:2px dashed var(--text);opacity:.5;margin-right:2px}
.twocol{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-bottom:8px}
.twocol .chartcard{margin-bottom:0}
td.num2{text-align:right;white-space:nowrap;padding-left:8px}
td.num2 .delta{margin-top:0}
.bar.chan-search{background:#0F7A3D}
.bar.chan-social{background:#D00059}
.bar.chan-referral{background:#B26A00}
.bar.chan-internal{background:#2B6C8F}
.bar.chan-direct{background:#6B5E85}
.trend{display:inline-block;font-size:.6rem;font-weight:700;padding:1px 6px;border-radius:8px;margin-left:6px;vertical-align:middle}
.trend.up,.trend.new{color:var(--ok);background:var(--ok-bg)}
.trend.down{color:var(--bad);background:var(--bad-bg)}
.trend.flat{color:var(--soft);background:var(--bg-2)}
tr.thead td{color:var(--soft);font-weight:700;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid var(--line)}
tr.thead td.sorth{cursor:pointer;user-select:none;white-space:nowrap}
tr.thead td.sorth:hover{color:var(--accent)}
.minih{font-size:.78rem;font-weight:700;margin:0 0 6px}
.gscup{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
.uplbl{font-size:.78rem;color:var(--soft);font-weight:600;display:flex;flex-direction:column;gap:4px}
.uplbl input[type=file]{font-size:.75rem;color:var(--text)}
.gscrange{font-family:inherit;font-size:.82rem;padding:6px 10px;border:1px solid var(--line);border-radius:10px;background:var(--surface);color:var(--text);min-width:200px}
.gscmsg{font-size:.78rem;color:var(--soft)}
.gscmsg.ok{color:var(--ok)}
.gscmsg.err{color:var(--bad)}
.trend.opp{color:#8a5a00;background:rgba(178,106,0,.14)}
tr.opp td{background:rgba(178,106,0,.06)}
.caret{border:none;background:transparent;color:var(--soft);font-size:.9rem;cursor:pointer;padding:0 4px 0 0;line-height:1;transition:transform .12s}
.caret.closed{transform:rotate(-90deg)}
.gscdrop{border:2px dashed var(--line);border-radius:12px;padding:16px;text-align:center;color:var(--soft);margin-bottom:12px;display:flex;flex-direction:column;gap:2px;transition:border-color .12s,background .12s}
.gscdrop b{color:var(--text);font-size:.9rem}
.gscdrop span{font-size:.78rem}
.gscdrop.over{border-color:var(--accent);background:var(--accent-soft)}
.tblwrap{max-height:440px;overflow:auto}
td.sortable{cursor:pointer;user-select:none;white-space:nowrap}
td.sortable:hover{color:var(--accent)}
td.activesort{color:var(--accent)}
td.potcell{color:#8a5a00;font-weight:700}
.topbtns{display:flex;gap:10px;flex-wrap:wrap}
.expbtn{background:var(--accent);color:#fff;border:none;border-radius:20px;padding:7px 16px;font-size:.8rem;font-weight:700;cursor:pointer;white-space:nowrap}
.expmodal{position:fixed;inset:0;background:rgba(34,16,65,.45);display:flex;align-items:center;justify-content:center;z-index:100;padding:20px}
.expbox{background:var(--surface);border:1px solid var(--line);border-radius:14px;max-width:840px;width:100%;max-height:90vh;display:flex;flex-direction:column;padding:18px 20px;box-shadow:0 20px 60px -20px rgba(0,0,0,.5)}
.exphead{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.exphead b{font-size:1rem}
.expx{border:none;background:transparent;font-size:1.1rem;cursor:pointer;color:var(--soft)}
.exphint{font-size:.78rem;color:var(--soft);margin:0 0 10px;line-height:1.5}
.exparea{width:100%;flex:1;min-height:320px;font-family:"Space Mono",ui-monospace,monospace;font-size:.72rem;line-height:1.4;padding:12px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--text);resize:vertical}
.expbtns{display:flex;gap:10px;align-items:center;margin-top:12px;flex-wrap:wrap}
.empty2{color:var(--soft);font-style:italic;padding:40px 0;text-align:center}
.detailmeta{display:flex;gap:26px;flex-wrap:wrap;margin-top:10px}
.detailmeta .mini{min-width:220px;flex:1}
.detailmeta .mini h3{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--soft);margin:0 0 6px}
.tip{position:fixed;z-index:50;display:none;pointer-events:none;background:var(--text);color:#fff;padding:7px 10px;border-radius:8px;font-size:.75rem;line-height:1.35;box-shadow:0 6px 18px -6px rgba(0,0,0,.4)}
.tip b{font-size:.9rem}
.tip span{opacity:.85}
#cards-sources,#cards-pages{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start}
.card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:18px 20px 18px 34px;box-shadow:0 8px 20px -14px rgba(34,16,65,.25);position:relative;cursor:grab}
.card.dragging{opacity:.4}
.card.over{outline:2px dashed var(--accent);outline-offset:2px}
.draghandle{position:absolute;left:10px;top:18px;color:var(--soft);font-size:1rem;line-height:1}
.card h2{font-size:1rem;margin:0 0 2px;font-weight:700;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.cardtag{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--accent);background:var(--accent-soft);padding:2px 8px;border-radius:8px}
.card .help{font-size:.75rem;color:var(--soft);margin:0 0 14px;line-height:1.5}
table{width:100%;border-collapse:collapse}
td{padding:7px 4px;font-size:.85rem;vertical-align:middle;border-bottom:1px solid var(--line)}
tr:last-child td{border-bottom:none}
td.lbl{color:var(--text);white-space:nowrap;padding-right:12px;max-width:220px;overflow:hidden;text-overflow:ellipsis}
td.num{text-align:right;color:var(--soft);font-variant-numeric:tabular-nums;padding-left:10px;white-space:nowrap;font-weight:600}
td.barcell{width:150px}
.bar{height:8px;background:var(--accent);border-radius:4px;min-width:2px;opacity:.85;max-width:150px}
td.empty{color:var(--soft);font-style:italic;padding:10px 4px;border-bottom:none}
.note{color:var(--soft);font-size:.75rem;margin-top:16px;line-height:1.5}
.loading{color:var(--soft);font-size:.85rem;padding:40px 0;text-align:center}

/* Anpassbares Widget-Board (Uebersicht): Kacheln per Griff verschiebbar, Groesse
   ueber S/M/L, Anordnung im Browser gemerkt (wie iOS-Widgets). */
#board{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;align-items:start;margin-bottom:20px}
.wg{background:var(--surface);border:1px solid var(--line);border-radius:12px;box-shadow:0 8px 20px -14px rgba(34,16,65,.25);padding:13px 15px;grid-column:span 1;min-height:110px;display:flex;flex-direction:column}
.wg.m{grid-column:span 2}.wg.l{grid-column:span 4}
.wg.dragging{opacity:.4}.wg.over{outline:2px dashed var(--accent);outline-offset:2px}
.wgh{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.wgh .t{font-size:.68rem;letter-spacing:.05em;text-transform:uppercase;color:var(--soft);font-weight:700}
.wgh .grip{cursor:grab;color:var(--soft);opacity:.55;font-size:.95rem;line-height:1;user-select:none;padding:1px 2px}
.wgh .grip:active{cursor:grabbing}
.wgsz{margin-left:auto;display:inline-flex;border:1px solid var(--line);border-radius:7px;overflow:hidden}
.wgsz button{border:none;background:var(--surface);color:var(--soft);font:inherit;font-size:.62rem;font-weight:700;width:23px;height:20px;cursor:pointer}
.wgsz button.on{background:var(--accent);color:#fff}
.wg .kbig{font-size:1.9rem;font-weight:800;letter-spacing:-.02em;line-height:1.05;color:var(--accent)}
.wg .kbig.sm{font-size:1.1rem;color:var(--text);word-break:break-word}
.wg .kcmp{font-size:.82rem;font-weight:600;color:var(--soft);margin-left:7px}
.wg .klbl{font-size:.78rem;color:var(--soft);margin-top:7px}
.wg table{margin-top:2px}
@media(max-width:820px){#board{grid-template-columns:repeat(2,1fr)}.wg.l{grid-column:span 2}}

/* GSC-artige Shell: obere Leiste, linke Navigation, Bereich pro Nav-Punkt */
.appbar{display:flex;align-items:center;gap:14px;padding:0 2px 14px;border-bottom:1px solid var(--line)}
.appbar .brand{display:flex;align-items:center;gap:9px;font-weight:700;font-size:1.05rem}
.appbar .brand .vi{width:28px;height:28px;border-radius:8px;background:var(--accent);color:#fff;display:grid;place-items:center;font-weight:800;font-size:.72rem;letter-spacing:.02em}
.appbar .brand .sub2{color:var(--soft);font-weight:500;font-size:.82rem;border-left:1px solid var(--line);padding-left:12px}
.appbar .spacer{flex:1}
.shell{display:grid;grid-template-columns:212px 1fr;gap:24px;align-items:start;margin-top:18px}
.sidebar{position:sticky;top:16px;display:flex;flex-direction:column;gap:2px}
.sidebar .grp{font-size:.62rem;letter-spacing:.09em;text-transform:uppercase;color:var(--soft);font-weight:700;padding:14px 12px 6px}
.navi{display:flex;align-items:center;gap:11px;padding:9px 12px;border-radius:10px;color:var(--soft);font-size:.86rem;font-weight:600;cursor:pointer;border:none;background:none;text-align:left;width:100%;font-family:inherit}
.navi svg{width:18px;height:18px;flex:none;stroke:currentColor;fill:none;stroke-width:1.9}
.navi:hover{background:var(--bg-2)}
.navi.on{background:var(--accent-soft);color:var(--accent)}
.mainarea{min-width:0}
.view{display:none}
.view.on{display:block}
/* Zugriffs-Protokoll (einzelne Aufrufe mit Uhrzeit) */
.logwrap{max-height:540px;overflow:auto;border:1px solid var(--line);border-radius:10px}
table.logtbl{width:100%;border-collapse:collapse}
table.logtbl td{padding:7px 12px;font-size:.82rem;border-bottom:1px solid var(--line);vertical-align:middle}
table.logtbl tr:last-child td{border-bottom:none}
table.logtbl td.t{white-space:nowrap;color:var(--soft);font-variant-numeric:tabular-nums;width:1%}
table.logtbl td.p{color:var(--text);word-break:break-word;font-weight:600}
table.logtbl td.s{white-space:nowrap;text-align:right;color:var(--soft);width:1%}
@media(max-width:820px){
  .shell{grid-template-columns:1fr;gap:14px}
  .sidebar{position:static;flex-direction:row;overflow-x:auto;gap:6px;padding-bottom:6px}
  .sidebar .grp{display:none}
  .navi{white-space:nowrap;width:auto;border:1px solid var(--line)}
}
</style></head><body>
<div class="appbar">
  <div class="brand"><span class="vi">VI</span> ViceGuide <span class="sub2">Statistik</span></div>
  <div class="spacer"></div>
  <div class="topbtns"><button class="expbtn" onclick="vgExportOpen()">Für Claude exportieren</button><button class="resetbtn" onclick="vgResetStats()">Alle Daten zurücksetzen</button></div>
</div>

<div class="shell">
  <aside class="sidebar" id="sidebar">
    <button class="navi on" data-view="overview"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Übersicht</button>
    <button class="navi" data-view="verlauf"><svg viewBox="0 0 24 24"><path d="M3 17l5-5 4 4 8-8"/><path d="M16 8h5v5"/></svg>Verlauf</button>
    <button class="navi" data-view="sources"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 3v9l6 3"/></svg>Quellen &amp; Kanäle</button>
    <button class="navi" data-view="pages"><svg viewBox="0 0 24 24"><path d="M4 5h16M4 12h16M4 19h10"/></svg>Seiten</button>
    <button class="navi" data-view="creator"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>Creator &amp; Partner</button>
    <button class="navi" data-view="search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>Interne Suche</button>
    <button class="navi" data-view="log"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Zugriffe (Uhrzeit)</button>
    <div class="grp">Extern</div>
    <button class="navi" data-view="gsc"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></svg>Google Search Console</button>
  </aside>
  <main class="mainarea">
    <div class="controls">
      <div class="ctrlgrp"><span class="ctrllbl">Schnellauswahl</span>
        <div class="tabs" id="quicktabs">
          <span class="tab" data-days="1">Heute</span>
          <span class="tab" data-preset="yesterday">Gestern</span>
          <span class="tab" data-days="3">3 Tage</span>
          <span class="tab" data-days="7">7 Tage</span>
          <span class="tab" data-days="30">30 Tage</span>
          <span class="tab" data-days="90">90 Tage</span>
        </div>
      </div>
      <div class="ctrlgrp"><span class="ctrllbl">Zeitraum vom Kalender</span>
        <div class="daterow">
          <input type="date" id="fromDate"><span class="sep">bis</span><input type="date" id="toDate">
          <button class="applybtn" id="applyRange">Anwenden</button>
          <button class="applybtn ghost" id="applyDay" title="Nur den Von-Tag als einzelnen Tag anzeigen">Nur dieser Tag</button>
        </div>
      </div>
    </div>
    <div id="dash"><div class="loading">Lade Daten...</div></div>
  </main>
</div>

<script>
var API=location.pathname;
var DATA=null, DET=null, GRAN='day', GRAN_LOCK=false, COMPARE=false, BOARD_WS=[];
var CUR_VIEW=(function(){try{return localStorage.getItem('vg_stats_view')||'overview';}catch(e){return 'overview';}})();
var WD=['So','Mo','Di','Mi','Do','Fr','Sa'];

function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function pad2(n){return (n<10?'0':'')+n;}
function fmtDay(day){var p=day.split('-');return p[2]+'.'+p[1]+'.';}
function fmtFullDay(day){var p=day.split('-');return p[2]+'.'+p[1]+'.'+p[0];}
function wdDay(day){return WD[new Date(day+'T00:00:00').getDay()];}
function pathLabel(p){return p==='/'?'Startseite':p;}
function niceNum(x){if(x<=5)return 5;var p=Math.pow(10,Math.floor(Math.log10(x)));var f=x/p,nf;if(f<=1)nf=1;else if(f<=2)nf=2;else if(f<=2.5)nf=2.5;else if(f<=5)nf=5;else nf=10;return nf*p;}

/* ---- Tooltip ---- */
var tip;
function ensureTip(){if(!tip){tip=document.createElement('div');tip.className='tip';document.body.appendChild(tip);}}
function showTip(e,el){ensureTip();var p=el.getAttribute('data-p');var extra=p!=null?'<br><span>Vorperiode: '+esc(p)+'</span>':'';tip.innerHTML='<b>'+esc(el.getAttribute('data-c'))+'</b> Aufrufe<br><span>'+esc(el.getAttribute('data-full'))+'</span>'+extra;tip.style.display='block';moveTip(e);}
function moveTip(e){if(!tip)return;var w=tip.offsetWidth||160;var x=e.clientX+14;if(x+w>window.innerWidth-8)x=e.clientX-w-14;tip.style.left=x+'px';tip.style.top=(e.clientY+16)+'px';}
function hideTip(){if(tip)tip.style.display='none';}

/* ---- Balkendiagramm, exakt auf die Containerbreite gerendert (keine Verzerrung) ---- */
function drawBars(host,buckets,opts){
  opts=opts||{};
  if(!buckets.length){host.innerHTML='<div class="empty2">Noch keine Daten in diesem Zeitraum.</div>';return;}
  var H=opts.height||260,padT=18,padB=opts.subLabels?46:38,padL=44,padR=10;
  var plotH=H-padT-padB;
  var minSlot=opts.minSlot||20;
  var hostW=Math.max(280,Math.floor(host.clientWidth||760));
  var nn=buckets.length;
  var needW=padL+padR+nn*minSlot;
  var fits=needW<=hostW;               // passt ohne Scrollen in die Breite
  var W=fits?hostW:needW;
  var plotW=W-padL-padR;
  var cmp=!!opts.compare;
  var rawMax=1;buckets.forEach(function(b){if(b.c>rawMax)rawMax=b.c;if(cmp&&(b.p||0)>rawMax)rawMax=b.p;});
  var max=niceNum(rawMax);
  var slot=plotW/nn;
  var barW=Math.min(slot*0.68,44);
  var yOf=function(v){return padT+plotH-(v/max)*plotH;};
  var g='';
  [0,.25,.5,.75,1].forEach(function(f){
    var y=padT+plotH*(1-f),val=Math.round(max*f);
    g+='<line x1="'+padL+'" y1="'+y.toFixed(1)+'" x2="'+(W-padR)+'" y2="'+y.toFixed(1)+'" class="gl"></line>';
    g+='<text x="'+(padL-8)+'" y="'+(y+3).toFixed(1)+'" class="yl" text-anchor="end">'+val+'</text>';
  });
  var step=Math.max(1,Math.ceil(nn/(opts.maxLabels||12)));
  var bars='',labels='';
  buckets.forEach(function(b,i){
    var cx=padL+slot*i+slot/2,x=cx-barW/2,y=yOf(b.c),bh=padT+plotH-y;
    bars+='<rect x="'+x.toFixed(1)+'" y="'+y.toFixed(1)+'" width="'+barW.toFixed(1)+'" height="'+Math.max(0,bh).toFixed(1)+'" rx="3" class="gbar"></rect>';
    bars+='<rect x="'+(padL+slot*i).toFixed(1)+'" y="'+padT+'" width="'+slot.toFixed(1)+'" height="'+plotH+'" fill="transparent" class="hit'+(opts.clickable&&b.day?' clk':'')+'" data-full="'+esc(b.full)+'" data-c="'+b.c+'"'+(cmp?' data-p="'+(b.p||0)+'"':'')+(b.day?' data-day="'+esc(b.day)+'"':'')+'></rect>';
    if(nn<=28&&b.c>0)labels+='<text x="'+cx.toFixed(1)+'" y="'+(y-4).toFixed(1)+'" class="vl" text-anchor="middle">'+b.c+'</text>';
    if(i%step===0||i===nn-1){
      labels+='<text x="'+cx.toFixed(1)+'" y="'+(H-(opts.subLabels?18:12)).toFixed(1)+'" class="xl" text-anchor="middle">'+esc(b.label)+'</text>';
      if(opts.subLabels&&b.sub)labels+='<text x="'+cx.toFixed(1)+'" y="'+(H-5)+'" class="xs" text-anchor="middle">'+esc(b.sub)+'</text>';
    }
  });
  // Vorperiode als helle Vergleichslinie über die Balken legen.
  var overlay='';
  if(cmp){
    var pts=buckets.map(function(b,i){return (padL+slot*i+slot/2).toFixed(1)+','+yOf(b.p||0).toFixed(1);}).join(' ');
    overlay+='<polyline points="'+pts+'" class="cmpline"></polyline>';
    if(nn<=40)buckets.forEach(function(b,i){overlay+='<circle cx="'+(padL+slot*i+slot/2).toFixed(1)+'" cy="'+yOf(b.p||0).toFixed(1)+'" r="2.5" class="cmpdot"></circle>';});
  }
  // Passt alles rein, wird das SVG auf 100% Breite gerendert, damit es bei
  // Subpixel-Rundung oder auftauchendem Seiten-Scrollbalken nie übersteht
  // (kein ungewollter horizontaler Scrollbalken). Nur wenn mehr Balken als
  // Platz da sind, feste Pixelbreite plus horizontales Scrollen.
  var wAttr=fits?'100%':W;
  var par=fits?'none':'xMidYMid meet';
  host.innerHTML='<svg width="'+wAttr+'" height="'+H+'" viewBox="0 0 '+W+' '+H+'" preserveAspectRatio="'+par+'" class="bars">'+g+bars+overlay+labels+'</svg>';
  host.querySelectorAll('.hit').forEach(function(el){
    el.addEventListener('mouseenter',function(e){showTip(e,el);});
    el.addEventListener('mousemove',moveTip);
    el.addEventListener('mouseleave',hideTip);
    if(opts.clickable&&el.getAttribute('data-day'))el.addEventListener('click',function(){opts.onClick(el.getAttribute('data-day'));});
  });
}

/* ---- Bucketing aus der Stundenreihe ---- */
function bucketsDay(series){
  var m=new Map();
  series.forEach(function(s){var d=s.t.slice(0,10);if(!m.has(d))m.set(d,{c:0,p:0});var o=m.get(d);o.c+=s.c;o.p+=(s.p||0);});
  var out=[];m.forEach(function(o,d){out.push({day:d,label:fmtDay(d),full:wdDay(d)+', '+fmtFullDay(d),c:o.c,p:o.p});});
  return out;
}
function bucket6h(series){
  var m=new Map();
  series.forEach(function(s){
    var d=s.t.slice(0,10),h=+s.t.slice(11,13),blk=Math.floor(h/6)*6,key=d+' '+blk;
    if(!m.has(key))m.set(key,{day:d,blk:blk,c:0,p:0});
    var o=m.get(key);o.c+=s.c;o.p+=(s.p||0);
  });
  var out=[];m.forEach(function(o){
    var end=o.blk+6;
    out.push({day:o.day,label:pad2(o.blk),sub:fmtDay(o.day),full:fmtDay(o.day)+' '+pad2(o.blk)+' bis '+pad2(end===24?24:end)+' Uhr',c:o.c,p:o.p});
  });
  return out;
}
function bucketHour(series){
  return series.map(function(s){
    var d=s.t.slice(0,10),h=+s.t.slice(11,13);
    return {day:d,label:pad2(h),sub:fmtDay(d),full:fmtDay(d)+' '+pad2(h)+':00 bis '+pad2((h+1)%24)+':00 Uhr',c:s.c,p:(s.p||0)};
  });
}
function bucket24(series){
  return series.map(function(s){
    var h=+s.t.slice(11,13);
    return {label:pad2(h),full:pad2(h)+':00 bis '+pad2((h+1)%24)+':00 Uhr',c:s.c};
  });
}

function deltaHtml(cur,prev){
  if(prev<=0)return cur>0?'<span class="delta up">neu</span>':'<span class="delta flat">ohne Vergleichswert</span>';
  var p=Math.round((cur-prev)/prev*100);
  if(p>0)return '<span class="delta up">+'+p+'% ggü. Vorperiode</span>';
  if(p<0)return '<span class="delta down">'+p+'% ggü. Vorperiode</span>';
  return '<span class="delta flat">0% ggü. Vorperiode</span>';
}
// Label der Vergleichsperiode, wandert mit dem gewaehlten Zeitraum mit:
// "Heute" vergleicht mit gestern, "Gestern" mit vorgestern, sonst allgemein.
function cmpLabel(){
  var r=DATA.range;
  if(r.preset==='yesterday')return 'vorgestern';
  if(!r.custom&&r.days===1)return 'gestern';
  return 'Vorperiode';
}
// Kleiner absoluter Vergleichswert neben der grossen Tageszahl (Direktvergleich).
function nCmp(prev){
  if(prev==null||prev<=0)return '';
  return '<span class="ncmp">'+cmpLabel()+' '+prev+'</span>';
}
function barRows(rows,labelFn){
  if(!rows||!rows.length)return '<tr><td colspan="3" class="empty">Noch keine Daten in diesem Zeitraum.</td></tr>';
  var max=1;rows.forEach(function(r){if(+r.c>max)max=+r.c;});
  return rows.map(function(r){
    var lab=labelFn(r),pct=Math.round(r.c/max*100);
    return '<tr><td class="lbl" title="'+esc(lab)+'">'+esc(lab)+'</td><td class="barcell"><div class="bar" style="width:'+pct+'%"></div></td><td class="num">'+r.c+'</td></tr>';
  }).join('');
}
function trendChip(cur,prev){
  if(prev<=0)return cur>0?'<span class="trend new">neu</span>':'';
  var p=Math.round((cur-prev)/prev*100);
  if(p>=15)return '<span class="trend up">steigt '+p+'%</span>';
  if(p<=-15)return '<span class="trend down">fällt '+p+'%</span>';
  return '<span class="trend flat">stabil</span>';
}
function pathTrendRows(rows){
  if(!rows||!rows.length)return '<tr><td colspan="3" class="empty">Noch keine Daten in diesem Zeitraum.</td></tr>';
  var max=1;rows.forEach(function(r){if(+r.c>max)max=+r.c;});
  return rows.map(function(r){
    var lab=pathLabel(r.path),pct=Math.round(r.c/max*100);
    return '<tr><td class="lbl" title="'+esc(lab)+'">'+esc(lab)+' '+trendChip(r.c,r.prev||0)+'</td><td class="barcell"><div class="bar" style="width:'+pct+'%"></div></td><td class="num">'+r.c+'</td></tr>';
  }).join('');
}

/* ---- Rendering ---- */
function defaultGran(days){if(days<=1)return 'hour';if(days<=3)return '6h';return 'day';}

function renderDash(){
  var d=DATA;
  var topSrc=(d.top_utm_sources[0]||{}),topPath=(d.top_paths[0]||{});
  var h='';
  // ---- Bereich: Übersicht (anpassbares Widget-Board) ----
  h+='<section class="view on" data-view="overview" id="v-overview">';
  h+='<div class="sectionlbl">Übersicht ('+esc(rangeLabel())+')</div>';
  h+='<div id="board"></div>';
  h+='</section>';

  // ---- Bereich: Verlauf (Zeitreihe, Tagesdetail, Heatmap) ----
  h+='<section class="view" data-view="verlauf" id="v-verlauf">';
  h+='<div class="chartcard">';
  h+='<div class="charthead"><h2>Verlauf</h2>';
  h+='<div class="headctrls"><button id="cmpBtn" class="toggle" title="Vorperiode als Vergleichslinie einblenden">Vorperiode vergleichen</button>';
  h+='<div class="seg" id="gran"><button data-g="hour">Stunde</button><button data-g="6h">6 Std</button><button data-g="day">Tag</button></div></div></div>';
  h+='<p class="help">Balken pro Zeitfenster, saubere Skala rechts abgelesen. Balken anfahren zeigt Fenster und genaue Zahl. Balken anklicken öffnet den 24-Stunden-Verlauf des Tages unten.<span id="cmplegend"></span></p>';
  h+='<div class="chartscroll"><div id="mainchart"></div></div></div>';
  h+='<div class="chartcard">';
  h+='<div class="charthead"><h2 id="detailtitle">Tages-Detail</h2>';
  h+='<div class="daterow"><span class="ctrllbl">Tag</span><input type="date" id="detailDate"></div></div>';
  h+='<p class="help">Voller 24-Stunden-Verlauf eines einzelnen Tages. Zeigt genau, zu welcher Uhrzeit die Aufrufe reinkamen. Tag frei wählbar, unabhängig vom Zeitraum oben.</p>';
  h+='<div class="chartscroll"><div id="detailchart"></div></div>';
  h+='<div class="detailmeta" id="detailmeta"></div></div>';
  h+='<div class="sectionlbl">Aktivität nach Wochentag und Uhrzeit</div>';
  h+='<div class="chartcard"><p class="help">Wann kommen die Aufrufe rein (Berlin-Zeit). Dunkler heißt mehr. Zeigt dir den besten Zeitpunkt fürs Veröffentlichen und für Instagram-Posts.</p><div class="chartscroll"><div id="heatmap"></div></div></div>';
  h+='</section>';

  // ---- Bereich: Quellen & Kanäle ----
  h+='<section class="view" data-view="sources" id="v-sources">';
  h+='<div class="sectionlbl">Kanäle</div>';
  h+='<div class="chartcard"><div class="charthead"><h2>Kanäle</h2></div><p class="help">Jeder Aufruf einsortiert in Suche, Social, Verweis oder Direkt. Die wichtigste SEO-Kennzahl: wächst der Anteil aus der Suche?</p><table id="chanTbl"></table></div>';
  h+='<div class="sectionlbl">Quellen im Detail, Reihenfolge per Ziehen anpassbar</div>';
  h+='<div id="cards-sources"></div>';
  h+='</section>';

  // ---- Bereich: Seiten ----
  h+='<section class="view" data-view="pages" id="v-pages">';
  h+='<div class="sectionlbl">Meistbesuchte Seiten</div>';
  h+='<div id="cards-pages"></div>';
  h+='<div class="sectionlbl">Einstiege aus der Suche</div>';
  h+='<div class="chartcard"><div class="charthead"><h2>Einstiege aus der Suche</h2></div><p class="help">Welche Seite Besucher aus Suchmaschinen (Google und Co.) direkt reinholt. Das sind deine rankenden Inhalte.</p><table id="searchTbl"></table></div>';
  h+='</section>';

  // ---- Bereich: Creator & Partner ----
  h+='<section class="view" data-view="creator" id="v-creator">';
  h+='<div class="sectionlbl">Creator- und Partner-Seiten</div>';
  h+='<div class="chartcard"><div class="charthead"><h2>Aufrufe der Akquise-Seiten</h2></div><p class="help">Jeder Aufruf einer Creator-Seite (/creator/) und der Partnerseite (/partner). Zeigt, ob ein verschickter Link (z.B. aus einer Ansprache-Mail) wirklich geoeffnet wurde. Bei Creator-Seiten steht hinter dem Doppelpunkt der Name aus der URL. Leer heisst: in diesem Zeitraum noch kein Aufruf.</p><table id="creatorTbl"></table></div>';
  h+='<div class="sectionlbl">Einzelne Aufrufe mit Uhrzeit</div>';
  h+='<div class="chartcard"><div class="charthead"><h2>Wann wurde geöffnet</h2></div><p class="help">Jeder einzelne Aufruf einer Creator- oder Partnerseite mit genauer Uhrzeit (Europe/Berlin), neueste zuerst. So siehst du, wann genau ein verschickter Link geoeffnet wurde.</p><div class="logwrap"><div id="crHitsTbl"></div></div></div>';
  h+='</section>';

  // ---- Bereich: Zugriffe (Protokoll aller Aufrufe mit Uhrzeit) ----
  h+='<section class="view" data-view="log" id="v-log">';
  h+='<div class="sectionlbl">Zugriffe mit Uhrzeit</div>';
  h+='<div class="chartcard"><div class="charthead"><h2>Einzelne Seitenaufrufe</h2></div><p class="help">Jeder einzelne Aufruf mit genauer Uhrzeit (Europe/Berlin) und aufgerufener Seite, neueste zuerst. Zeigt wann welche Seite geoeffnet wurde. Dein eigener Admin-Login zaehlt nicht mit. Die letzten 300 im gewaehlten Zeitraum.</p><div class="logwrap"><div id="logTbl"></div></div></div>';
  h+='</section>';

  // ---- Bereich: Interne Suche & Lese-Engagement ----
  h+='<section class="view" data-view="search" id="v-search">';
  h+='<div class="sectionlbl">Was Besucher suchen und lesen</div>';
  h+='<div class="twocol">';
  h+='<div class="chartcard"><div class="charthead"><h2>Interne Suche</h2></div><p class="help">Wonach auf der Seite gesucht wird. Rot markiert = null Treffer, also ein direkter Hinweis, welchen Artikel du als Nächstes schreiben solltest.</p><table id="intSearchTbl"></table></div>';
  h+='<div class="chartcard"><div class="charthead"><h2>Lese-Engagement <span id="engAvg" class="cardtag"></span></h2></div><p class="help">Verweildauer und maximale Scrolltiefe pro Artikel. Zeigt, ob Artikel wirklich gelesen oder sofort weggeklickt werden. Verweildauer ist grob, ein offener Tab im Hintergrund kann sie verlängern.</p><table id="engTbl"></table></div>';
  h+='</div>';
  h+='</section>';

  // ---- Bereich: Google Search Console ----
  h+='<section class="view" data-view="gsc" id="v-gsc">';
  h+='<div class="chartcard gsc" id="gscCard">';
  h+='<div class="charthead"><h2><button class="caret" id="gscSecCaret" onclick="gscToggle(\'section\')" title="Ein-/ausklappen">&#9662;</button> Suchleistung bei Google <span id="gscMeta" class="cardtag"></span></h2></div>';
  h+='<div id="gscBody">';
  h+='<p class="help">Impressionen, Klicks, CTR und Durchschnittsposition direkt aus Google, inklusive der echten Suchbegriffe. Das First-Party-Tracking kann das nicht sehen. Am einfachsten die ganze Zip aus der Search Console (Leistung, Exportieren) hochladen, Seiten und Suchanfragen werden automatisch erkannt. Überschreibt den vorherigen Stand. Gelb markiert = viele Impressionen, aber Position schlechter als 10 oder CTR unter 2%, also Potenzial zum Nachschärfen. Die Spalte Potenzial schätzt grob die ungenutzten Klicks (Impressionen mit verbesserbarer Position und schwacher CTR), zum Sortieren draufklicken. Grobe Priorisierungshilfe, keine exakte Prognose.</p>';
  h+='<div class="gscdrop" id="gscDrop"><b>Zip hierher ziehen</b><span>oder unten eine Datei wählen</span></div>';
  h+='<div class="gscup">';
  h+='<label class="uplbl">Ganze Zip (empfohlen) <input type="file" accept=".zip,application/zip" id="gscZipFile"></label>';
  h+='<label class="uplbl">oder Seiten-CSV <input type="file" accept=".csv,text/csv" id="gscPageFile"></label>';
  h+='<label class="uplbl">oder Suchanfragen-CSV <input type="file" accept=".csv,text/csv" id="gscQueryFile"></label>';
  h+='<input type="text" id="gscRange" placeholder="Zeitraum-Notiz, z.B. letzte 3 Monate" class="gscrange">';
  h+='<span id="gscMsg" class="gscmsg"></span>';
  h+='</div>';
  h+='<div class="twocol" style="margin-top:14px">';
  h+='<div class="gsctbl"><h3 class="minih"><button class="caret" id="gscPageCaret" onclick="gscToggle(\'page\')" title="Ein-/ausklappen">&#9662;</button> Top-Seiten (Google)</h3><div class="tblwrap" id="gscPageWrap"><table id="gscPageTbl"></table></div></div>';
  h+='<div class="gsctbl"><h3 class="minih"><button class="caret" id="gscQueryCaret" onclick="gscToggle(\'query\')" title="Ein-/ausklappen">&#9662;</button> Top-Suchanfragen (Google)</h3><div class="tblwrap" id="gscQueryWrap"><table id="gscQueryTbl"></table></div></div>';
  h+='</div></div></div>';
  h+='</section>';
  document.getElementById('dash').innerHTML=h;
  wireNav();

  // Granularität
  if(!GRAN_LOCK)GRAN=defaultGran(d.range.days);
  document.querySelectorAll('#gran button').forEach(function(b){
    b.classList.toggle('active',b.getAttribute('data-g')===GRAN);
    b.addEventListener('click',function(){GRAN=b.getAttribute('data-g');GRAN_LOCK=true;document.querySelectorAll('#gran button').forEach(function(x){x.classList.toggle('active',x===b);});renderMain();});
  });
  // Vorperioden-Vergleich
  var cb=document.getElementById('cmpBtn');
  cb.classList.toggle('on',COMPARE);
  cb.addEventListener('click',function(){COMPARE=!COMPARE;cb.classList.toggle('on',COMPARE);renderMain();});

  renderBoard();
  renderMain();
  renderChannels();
  renderSearchEntries();
  renderCreatorPages();
  renderCreatorHits();
  renderLog();
  renderInternalSearch();
  renderEngagement();
  renderHeatmap();
  renderCards();
  wireGsc();
  loadGsc();

  // Tagesdetail
  var di=document.getElementById('detailDate');
  var lastDay=lastActiveDay();
  di.value=lastDay;
  di.addEventListener('change',function(){if(di.value)loadDetail(di.value);});
  loadDetail(lastDay);

  applyView();
}

/* ---- Bereichs-Navigation (GSC-artig): pro Nav-Punkt nur ein Bereich sichtbar.
   Breitenabhaengige Diagramme werden erst beim Anzeigen gezeichnet, sonst
   messen sie im ausgeblendeten Zustand die Breite 0. ---- */
function wireNav(){
  document.querySelectorAll('#sidebar .navi').forEach(function(b){
    if(b._wired)return;b._wired=true;
    b.addEventListener('click',function(){navTo(b.getAttribute('data-view'));});
  });
}
function navTo(v){
  CUR_VIEW=v;try{localStorage.setItem('vg_stats_view',v);}catch(e){}
  applyView();
}
function applyView(){
  document.querySelectorAll('#dash .view').forEach(function(s){s.classList.toggle('on',s.getAttribute('data-view')===CUR_VIEW);});
  document.querySelectorAll('#sidebar .navi').forEach(function(b){b.classList.toggle('on',b.getAttribute('data-view')===CUR_VIEW);});
  drawViewCharts(CUR_VIEW);
}
function drawViewCharts(v){
  // Erst beim Sichtbarwerden zeichnen, damit die Breite korrekt gemessen wird.
  if(v==='verlauf'){
    renderMain();renderHeatmap();
    var di=document.getElementById('detailDate');
    if(di&&di.value)loadDetail(di.value);
  }
}

function rangeLabel(){
  var r=DATA.range;
  if(r.preset==='yesterday')return 'Gestern, '+wdDay(r.from)+' '+fmtFullDay(r.from);
  if(!r.custom){
    if(r.days===1)return 'Heute';
    return 'letzte '+r.days+' Tage';
  }
  if(r.from===r.to)return wdDay(r.from)+', '+fmtFullDay(r.from);
  return fmtFullDay(r.from)+' bis '+fmtFullDay(r.to);
}
function lastActiveDay(){
  var days=bucketsDay(DATA.series),last=null;
  days.forEach(function(b){if(b.c>0)last=b.day;});
  return last||DATA.range.to;
}
function renderMain(){
  var host=document.getElementById('mainchart');if(!host)return;
  var b,minSlot;
  if(GRAN==='hour'){b=bucketHour(DATA.series);minSlot=13;}
  else if(GRAN==='6h'){b=bucket6h(DATA.series);minSlot=18;}
  else{b=bucketsDay(DATA.series);minSlot=28;}
  drawBars(host,b,{clickable:true,compare:COMPARE,minSlot:minSlot,subLabels:GRAN!=='day',onClick:function(day){var di=document.getElementById('detailDate');di.value=day;loadDetail(day);document.getElementById('detailtitle').scrollIntoView({behavior:'smooth',block:'center'});}});
  var leg=document.getElementById('cmplegend');
  if(leg)leg.innerHTML=COMPARE?' <span class="leg"><span class="legbar"></span>Zeitraum &nbsp; <span class="legline"></span>Vorperiode ('+(DATA.range.days)+' Tage davor)</span>':'';
}
/* ---- Google Search Console ---- */
function gscApi(){return API.replace(/hits\.php$/,'gsc.php');}
var GSC_DATA={pages:[],queries:[],meta:{}};
var GSC_COLS=[{k:'clicks',l:'Klicks'},{k:'impressions',l:'Impr.'},{k:'ctr',l:'CTR'},{k:'position',l:'Pos.'},{k:'pot',l:'Potenzial'}];
var GSC_FMT={clicks:function(v){return v;},impressions:function(v){return v;},ctr:function(v){return (+v).toFixed(1)+'%';},position:function(v){return (+v).toFixed(1);},pot:function(v){return v>0?'~'+v:'0';}};
var GSC_SORT={page:{col:'impressions',dir:'desc'},query:{col:'impressions',dir:'desc'}};
function gscShortPath(u){try{if(/^https?:\/\//.test(u)){return new URL(u).pathname||'/';}}catch(e){}return u;}
/* Potenzial-Heuristik: geschätzte ungenutzte Klicks. Viele Impressionen mal
   ein Positionsgewicht (Platz 4 bis 20 hat am meisten Luft, Platz 1 bis 3 kaum)
   mal der Abstand zu einer realistisch erreichbaren CTR von rund 30%. Bewusst
   grob, nur zum Priorisieren, keine exakte Prognose. */
function gscPot(r){
  var pos=+r.position||0, ctr=(+r.ctr||0)/100, imp=+r.impressions||0;
  var pf=pos<=3?0.2:(pos<=10?1.0:(pos<=20?0.8:0.4));
  return Math.round(imp*pf*Math.max(0,0.30-ctr));
}
function gscSetCollapsed(which,on){
  var map={section:['gscBody','gscSecCaret'],page:['gscPageWrap','gscPageCaret'],query:['gscQueryWrap','gscQueryCaret']};
  var m=map[which];if(!m)return;
  var body=document.getElementById(m[0]),car=document.getElementById(m[1]);
  if(body)body.style.display=on?'none':'';
  if(car)car.classList.toggle('closed',on);
}
function gscToggle(which){
  var map={section:'gscBody',page:'gscPageWrap',query:'gscQueryWrap'};
  var body=document.getElementById(map[which]);if(!body)return;
  var on=body.style.display!=='none';
  gscSetCollapsed(which,on);
  try{var c=JSON.parse(localStorage.getItem('vg_gsc_collapse')||'{}');c[which]=on;localStorage.setItem('vg_gsc_collapse',JSON.stringify(c));}catch(e){}
}
function gscSort(kind,col){
  var s=GSC_SORT[kind];
  if(s.col===col){s.dir=s.dir==='asc'?'desc':'asc';}else{s.col=col;s.dir=(col==='position')?'asc':'desc';}
  try{localStorage.setItem('vg_gsc_sort',JSON.stringify(GSC_SORT));}catch(e){}
  renderGscTable(kind);
}
function renderGscTable(kind){
  var isQuery=kind==='query';
  var tbl=document.getElementById(isQuery?'gscQueryTbl':'gscPageTbl');if(!tbl)return;
  var arr=(isQuery?GSC_DATA.queries:GSC_DATA.pages)||[];
  if(!arr.length){tbl.innerHTML='<tr><td colspan="6" class="empty">Noch kein CSV/Zip hochgeladen.</td></tr>';return;}
  var maxImp=1;arr.forEach(function(r){if(+r.impressions>maxImp)maxImp=+r.impressions;});
  var work=arr.map(function(r){var o={};for(var k in r)o[k]=r[k];o.pot=gscPot(r);return o;});
  var s=GSC_SORT[kind];
  var sorted=work.sort(function(a,b){var d=(+a[s.col])-(+b[s.col]);return s.dir==='asc'?d:-d;});
  var arrow=function(col){return s.col===col?(s.dir==='asc'?' ▲':' ▼'):'';};
  var head='<tr class="thead"><td class="lbl">'+(isQuery?'Suchanfrage':'Seite')+'</td>'+
    GSC_COLS.map(function(c){return '<td class="num sortable'+(s.col===c.k?' activesort':'')+'" onclick="gscSort(\''+kind+'\',\''+c.k+'\')">'+c.l+arrow(c.k)+'</td>';}).join('')+'</tr>';
  var body=sorted.slice(0,100).map(function(r){
    var opp=(+r.impressions>=maxImp*0.15)&&(+r.position>10||+r.ctr<2);
    var lab=isQuery?r.label:pathLabel(gscShortPath(r.label));
    var cells=GSC_COLS.map(function(c){return '<td class="num'+(c.k==='pot'&&r.pot>0?' potcell':'')+'">'+GSC_FMT[c.k](r[c.k])+'</td>';}).join('');
    return '<tr class="'+(opp?'opp':'')+'"><td class="lbl" title="'+esc(r.label)+'">'+esc(lab)+(opp?' <span class="trend opp">Potenzial</span>':'')+'</td>'+cells+'</tr>';
  }).join('');
  tbl.innerHTML=head+body;
}
function loadGsc(){
  fetch(gscApi(),{headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(d){
    GSC_DATA={pages:d.pages||[],queries:d.queries||[],meta:d.meta||{}};
    renderGscTable('page');renderGscTable('query');
    var m=document.getElementById('gscMeta');
    if(m){var parts=[];if(GSC_DATA.meta.page)parts.push('Seiten: '+(GSC_DATA.meta.page.range||GSC_DATA.meta.page.imported||''));if(GSC_DATA.meta.query)parts.push('Anfragen: '+(GSC_DATA.meta.query.range||GSC_DATA.meta.query.imported||''));m.textContent=parts.join('  |  ');}
  }).catch(function(){});
}
function gscMsg(txt,cls){var m=document.getElementById('gscMsg');if(m){m.textContent=txt;m.className='gscmsg'+(cls?' '+cls:'');}}
function gscRange(){var el=document.getElementById('gscRange');return el?el.value||'':'';}
function gscPost(payload,okmsg){
  gscMsg('Lade hoch...','');
  fetch(gscApi(),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
    .then(function(r){return r.json();})
    .then(function(res){if(res.ok){gscMsg(okmsg(res),'ok');loadGsc();}else{gscMsg(res.error||'Fehler','err');}})
    .catch(function(){gscMsg('Upload fehlgeschlagen.','err');});
}
function gscUploadZip(file){
  if(!/\.zip$/i.test(file.name||'')){gscMsg('Bitte eine .zip aus der Search Console (oder unten die einzelne CSV).','err');return;}
  var rd=new FileReader();
  rd.onload=function(){gscPost({zip:rd.result,range:gscRange()},function(res){return 'Import: '+res.pages+' Seiten, '+res.queries+' Suchanfragen.';});};
  rd.readAsDataURL(file);
}
function gscUploadCsv(kind,file){
  var rd=new FileReader();
  rd.onload=function(){gscPost({kind:kind,csv:rd.result,range:gscRange()},function(res){return res.rows+' Zeilen importiert.';});};
  rd.readAsText(file);
}
function wireGsc(){
  var zf=document.getElementById('gscZipFile'),pf=document.getElementById('gscPageFile'),qf=document.getElementById('gscQueryFile');
  if(zf)zf.addEventListener('change',function(){if(zf.files[0])gscUploadZip(zf.files[0]);});
  if(pf)pf.addEventListener('change',function(){if(pf.files[0])gscUploadCsv('page',pf.files[0]);});
  if(qf)qf.addEventListener('change',function(){if(qf.files[0])gscUploadCsv('query',qf.files[0]);});
  var dz=document.getElementById('gscDrop');
  if(dz){
    ['dragenter','dragover'].forEach(function(ev){dz.addEventListener(ev,function(e){e.preventDefault();e.stopPropagation();dz.classList.add('over');});});
    ['dragleave','dragend','drop'].forEach(function(ev){dz.addEventListener(ev,function(e){e.preventDefault();dz.classList.remove('over');});});
    dz.addEventListener('drop',function(e){e.preventDefault();var f=e.dataTransfer&&e.dataTransfer.files&&e.dataTransfer.files[0];if(f)gscUploadZip(f);});
  }
  // Klapp- und Sortier-Zustand aus dem Browser wiederherstellen.
  try{
    var c=JSON.parse(localStorage.getItem('vg_gsc_collapse')||'{}');
    ['section','page','query'].forEach(function(k){if(c[k])gscSetCollapsed(k,true);});
    var ss=JSON.parse(localStorage.getItem('vg_gsc_sort')||'null');if(ss)GSC_SORT=ss;
  }catch(e){}
}
var CHAN_LABELS={search:'Suche (SEO)',social:'Social',referral:'Verweis',internal:'Intern (eigene Seite)',direct:'Direkt'};
var ENG_SORT={k:'c',d:-1};
function engSortBy(k){ if(ENG_SORT.k===k){ENG_SORT.d=-ENG_SORT.d;}else{ENG_SORT.k=k;ENG_SORT.d=-1;} renderEngagement(); }
function renderChannels(){
  var t=document.getElementById('chanTbl');if(!t)return;
  var cur=DATA.channels.cur,prev=DATA.channels.prev;
  var keys=['search','social','referral','internal','direct'];
  var max=1;keys.forEach(function(k){if(cur[k]>max)max=cur[k];});
  t.innerHTML=keys.map(function(k){
    var pct=Math.round(cur[k]/max*100);
    return '<tr><td class="lbl">'+esc(CHAN_LABELS[k])+'</td><td class="barcell"><div class="bar chan-'+k+'" style="width:'+pct+'%"></div></td><td class="num">'+cur[k]+'</td><td class="num2">'+deltaHtml(cur[k],prev[k])+'</td></tr>';
  }).join('');
}
function renderSearchEntries(){
  var t=document.getElementById('searchTbl');if(!t)return;
  t.innerHTML=barRows(DATA.entries_search,function(r){return pathLabel(r.path);});
}
function creatorLabel(path){
  var p=String(path||'').replace(/\/$/,'');
  if(p==='/partner'||p.indexOf('/partner')===0)return 'Partnerseite (/partner)';
  var s=String(path||'').replace(/^\/creator\/?/,'').replace(/\/$/,'');
  return s?('Creator: '+s):'Creator-Übersicht';
}
function renderCreatorPages(){
  var t=document.getElementById('creatorTbl');if(!t)return;
  t.innerHTML=barRows(DATA.creator_pages,function(r){return creatorLabel(r.path);});
}
/* Zugriffs-Protokoll: einzelne Aufrufe mit Uhrzeit (neueste zuerst). */
function hitSource(r){ if(r.utm_source)return r.utm_source; if(r.ref_host){ return r.ref_host==='viceguide.de' ? 'intern (eigene Seite)' : r.ref_host; } return 'direkt'; }
function logTableHtml(rows,labelFn){
  if(!rows||!rows.length)return '<table class="logtbl"><tr><td class="p" style="color:var(--soft);font-style:italic;font-weight:400">Noch keine Aufrufe in diesem Zeitraum.</td></tr></table>';
  return '<table class="logtbl">'+rows.map(function(r){
    return '<tr><td class="t">'+esc(r.ts)+'</td><td class="p">'+esc(labelFn(r.path))+'</td><td class="s">'+esc(hitSource(r))+'</td></tr>';
  }).join('')+'</table>';
}
function renderCreatorHits(){
  var t=document.getElementById('crHitsTbl');if(!t)return;
  t.innerHTML=logTableHtml(DATA.creator_hits,function(p){return creatorLabel(p);});
}
function renderLog(){
  var t=document.getElementById('logTbl');if(!t)return;
  t.innerHTML=logTableHtml(DATA.recent_hits,function(p){return pathLabel(p);});
}

/* ---- Anpassbares Widget-Board (Uebersicht) ----
   Kacheln per Griff verschiebbar, Groesse ueber S/M/L, Anordnung im Browser
   gemerkt (vg_board_layout). Die Listen-Widgets enthalten dieselben Tabellen
   (chanTbl/creatorTbl/searchTbl), die renderChannels/renderCreatorPages/
   renderSearchEntries danach befuellen. */
var BOARD_KEY='vg_board_layout';
function boardWidgets(){
  var d=DATA;
  var topSrc=(d.top_utm_sources[0]||{}),topPath=(d.top_paths[0]||{});
  return [
    {id:'pv',size:'s',t:'Seitenaufrufe',body:'<div class="kbig tnum">'+d.total+'</div>'+(d.prev_total>0?'<div class="klbl tnum">'+cmpLabel()+' '+d.prev_total+'</div>':'')+deltaHtml(d.total,d.prev_total)},
    {id:'ig',size:'s',t:'Instagram (UTM)',body:'<div class="kbig tnum">'+d.instagram.by_utm+'</div>'+deltaHtml(d.instagram.by_utm,d.instagram.prev_by_utm)},
    {id:'src',size:'s',t:'Top-Quelle',body:'<div class="kbig sm">'+esc(topSrc.utm_source||'noch keine')+'</div><div class="klbl">'+(topSrc.c||0)+' Aufrufe mit UTM-Tag</div>'},
    {id:'page',size:'s',t:'Top-Seite',body:'<div class="kbig sm">'+esc(topPath.path?pathLabel(topPath.path):'noch keine')+'</div><div class="klbl">'+(topPath.c||0)+' Aufrufe</div>'},
    {id:'chan',size:'m',t:'Kanäle',body:chanRowsHtml()},
    {id:'cr',size:'m',t:'Creator & Partner',body:'<table>'+barRows(d.creator_pages,function(r){return creatorLabel(r.path);})+'</table>'},
    {id:'sea',size:'m',t:'Einstiege aus der Suche',body:'<table>'+barRows(d.entries_search,function(r){return pathLabel(r.path);})+'</table>'}
  ];
}
/* Kanaele als fertige Tabellenzeilen fuers Board-Widget (eigenstaendig, keine
   geteilte ID mit dem Detail-Bereich Quellen & Kanaele). */
function chanRowsHtml(){
  var cur=(DATA.channels&&DATA.channels.cur)||{},keys=['search','social','referral','internal','direct'];
  var max=1;keys.forEach(function(k){if((cur[k]||0)>max)max=cur[k];});
  return '<table>'+keys.map(function(k){var v=cur[k]||0,pct=Math.round(v/max*100);
    return '<tr><td class="lbl">'+esc(CHAN_LABELS[k])+'</td><td class="barcell"><div class="bar chan-'+k+'" style="width:'+pct+'%"></div></td><td class="num">'+v+'</td></tr>';
  }).join('')+'</table>';
}
function renderBoard(){
  var box=document.getElementById('board');if(!box)return;
  var ws=boardWidgets();
  try{
    var saved=JSON.parse(localStorage.getItem(BOARD_KEY)||'[]');
    if(saved.length){
      var map={};ws.forEach(function(w){map[w.id]=w;});
      var out=[];
      saved.forEach(function(o){if(map[o.id]){if(o.size)map[o.id].size=o.size;out.push(map[o.id]);delete map[o.id];}});
      ws.forEach(function(w){if(map[w.id])out.push(w);});
      ws=out;
    }
  }catch(e){}
  BOARD_WS=ws;
  box.innerHTML=ws.map(function(w){
    return '<div class="wg '+w.size+'" draggable="true" data-id="'+w.id+'"><div class="wgh"><span class="grip" title="Ziehen zum Verschieben">&#10287;</span><span class="t">'+esc(w.t)+'</span>'+
      '<span class="wgsz">'+['s','m','l'].map(function(sz){return '<button class="'+(w.size===sz?'on':'')+'" data-sz="'+sz+'">'+sz.toUpperCase()+'</button>';}).join('')+'</span></div><div class="wgbody">'+w.body+'</div></div>';
  }).join('');
  wireBoard(box);
}
function boardSave(){try{localStorage.setItem(BOARD_KEY,JSON.stringify(BOARD_WS.map(function(w){return {id:w.id,size:w.size};})));}catch(e){}}
function fillBoardTables(){renderChannels();renderCreatorPages();renderSearchEntries();}
function wireBoard(box){
  box.querySelectorAll('.wgsz button').forEach(function(btn){
    btn.addEventListener('click',function(){var id=btn.closest('.wg').getAttribute('data-id'),sz=btn.getAttribute('data-sz');BOARD_WS.forEach(function(w){if(w.id===id)w.size=sz;});boardSave();renderBoard();});
  });
  var dragId=null;
  box.querySelectorAll('.wg').forEach(function(el){
    el.addEventListener('dragstart',function(){dragId=el.getAttribute('data-id');el.classList.add('dragging');});
    el.addEventListener('dragend',function(){dragId=null;el.classList.remove('dragging');box.querySelectorAll('.wg').forEach(function(x){x.classList.remove('over');});});
    el.addEventListener('dragover',function(e){e.preventDefault();if(el.getAttribute('data-id')===dragId)return;box.querySelectorAll('.wg').forEach(function(x){x.classList.remove('over');});el.classList.add('over');});
    el.addEventListener('dragleave',function(){el.classList.remove('over');});
    el.addEventListener('drop',function(e){e.preventDefault();if(!dragId||dragId===el.getAttribute('data-id'))return;
      var from=BOARD_WS.findIndex(function(w){return w.id===dragId;});
      var to=BOARD_WS.findIndex(function(w){return w.id===el.getAttribute('data-id');});
      if(from<0||to<0)return;
      var mv=BOARD_WS.splice(from,1)[0];BOARD_WS.splice(to,0,mv);boardSave();renderBoard();});
  });
}
function fmtSecs(s){s=+s||0;if(s<60)return s+' s';var m=Math.floor(s/60),r=s%60;return m+':'+pad2(r)+' min';}
function renderInternalSearch(){
  var t=document.getElementById('intSearchTbl');if(!t)return;
  var rows=DATA.searches||[];
  if(!rows.length){t.innerHTML='<tr><td class="empty">Noch keine internen Suchen erfasst (Daten sammeln sich ab jetzt).</td></tr>';return;}
  var max=1;rows.forEach(function(r){if(r.c>max)max=r.c;});
  t.innerHTML=rows.map(function(r){
    var pct=Math.round(r.c/max*100);
    var zero=r.maxres===0?' <span class="trend down">0 Treffer</span>':'';
    return '<tr><td class="lbl" title="'+esc(r.q)+'">'+esc(r.q)+zero+'</td><td class="barcell"><div class="bar" style="width:'+pct+'%"></div></td><td class="num">'+r.c+'</td></tr>';
  }).join('');
}
function renderEngagement(){
  var t=document.getElementById('engTbl');if(!t)return;
  var e=DATA.engagement||{rows:[],totals:{c:0,avg_sec:0,avg_depth:0}};
  var tag=document.getElementById('engAvg');
  if(tag)tag.textContent=e.totals.c?('Schnitt '+fmtSecs(e.totals.avg_sec)+' / '+e.totals.avg_depth+'%'):'';
  var rows=(e.rows||[]).slice();
  if(!rows.length){t.innerHTML='<tr><td class="empty">Noch keine Lesedaten erfasst (Daten sammeln sich ab jetzt).</td></tr>';return;}
  var sk=ENG_SORT.k,sd=ENG_SORT.d;
  rows.sort(function(a,b){
    var x,y;
    if(sk==='path'){x=pathLabel(a.path).toLowerCase();y=pathLabel(b.path).toLowerCase();return x<y?-sd:(x>y?sd:0);}
    x=+a[sk]||0;y=+b[sk]||0;return (x-y)*sd;
  });
  function arw(k){ return ENG_SORT.k===k ? (ENG_SORT.d<0?' ▼':' ▲') : ''; }
  t.innerHTML='<tr class="thead"><td class="lbl sorth" onclick="engSortBy(\'path\')">Artikel'+arw('path')+'</td><td class="num sorth" onclick="engSortBy(\'c\')">Aufrufe'+arw('c')+'</td><td class="num sorth" onclick="engSortBy(\'avg_sec\')">Verweildauer'+arw('avg_sec')+'</td><td class="num sorth" onclick="engSortBy(\'avg_depth\')">Scrolltiefe'+arw('avg_depth')+'</td></tr>'+
    rows.map(function(r){
      return '<tr><td class="lbl" title="'+esc(pathLabel(r.path))+'">'+esc(pathLabel(r.path))+'</td><td class="num">'+r.c+'</td><td class="num">'+fmtSecs(r.avg_sec)+'</td><td class="num">'+r.avg_depth+'%</td></tr>';
    }).join('');
}
function renderHeatmap(){
  var host=document.getElementById('heatmap');if(!host)return;
  // 7x24-Matrix (Mo..So x 0..23) aus der Berlin-Stundenreihe.
  var grid=[];for(var r=0;r<7;r++){grid.push(new Array(24).fill(0));}
  DATA.series.forEach(function(s){
    var day=new Date(s.t.slice(0,10)+'T00:00:00').getDay(); // 0=So..6=Sa
    var row=(day+6)%7; // 0=Mo..6=So
    var hr=+s.t.slice(11,13);
    grid[row][hr]+=s.c;
  });
  var max=1;grid.forEach(function(row){row.forEach(function(v){if(v>max)max=v;});});
  var rows=['Mo','Di','Mi','Do','Fr','Sa','So'];
  // Zellgröße aus der Containerbreite, quadratisch gehalten und begrenzt, damit
  // die Zellen die Breite füllen ohne verzerrt (breitgezogen) zu werden. Echte
  // Pixelbreite statt 100%-Stretch, daher keine Verzerrung.
  var padL=34,padT=18;
  var hostW=Math.max(280,Math.floor(host.clientWidth||760));
  var cell=Math.floor((hostW-padL-6)/24);
  cell=Math.max(16,Math.min(cell,46));
  var W=padL+24*cell+2, H=padT+7*cell+6;
  var svg='';
  var lstep=cell<26?4:2; // bei schmalen Zellen weniger Stundenlabels
  for(var c=0;c<24;c++){if(c%lstep===0)svg+='<text x="'+(padL+c*cell+cell/2).toFixed(1)+'" y="'+(padT-6)+'" class="hmlbl" text-anchor="middle">'+pad2(c)+'</text>';}
  for(var rr=0;rr<7;rr++){
    svg+='<text x="'+(padL-8)+'" y="'+(padT+rr*cell+cell/2+3).toFixed(1)+'" class="hmlbl" text-anchor="end">'+rows[rr]+'</text>';
    for(var cc=0;cc<24;cc++){
      var v=grid[rr][cc];var op=v===0?0.05:(0.15+0.85*(v/max));
      svg+='<rect x="'+(padL+cc*cell+1).toFixed(1)+'" y="'+(padT+rr*cell+1).toFixed(1)+'" width="'+(cell-2)+'" height="'+(cell-2)+'" rx="3" class="hmcell" fill="var(--accent)" fill-opacity="'+op.toFixed(3)+'" data-full="'+esc(rows[rr]+' '+pad2(cc)+':00 bis '+pad2((cc+1)%24)+':00 Uhr')+'" data-c="'+v+'"></rect>';
    }
  }
  host.innerHTML='<svg width="'+W+'" height="'+H+'" viewBox="0 0 '+W+' '+H+'" class="bars heat" style="display:block;margin:0 auto">'+svg+'</svg>';
  host.querySelectorAll('.hmcell').forEach(function(el){
    el.addEventListener('mouseenter',function(e){showTip(e,el);});
    el.addEventListener('mousemove',moveTip);
    el.addEventListener('mouseleave',hideTip);
  });
}
function renderDetail(day){
  var host=document.getElementById('detailchart');if(!host)return;
  drawBars(host,bucket24(DET.series),{minSlot:24,maxLabels:24});
  document.getElementById('detailtitle').textContent='Tages-Detail, '+wdDay(day)+' '+fmtFullDay(day)+' ('+DET.total+' Aufrufe)';
  var mp=barRows(DET.top_paths.slice(0,6),function(r){return pathLabel(r.path);});
  var mr=barRows(DET.top_referrers.slice(0,6),function(r){return r.ref_host;});
  document.getElementById('detailmeta').innerHTML=
    '<div class="mini"><h3>Top-Seiten an diesem Tag</h3><table>'+mp+'</table></div>'+
    '<div class="mini"><h3>Herkunft an diesem Tag</h3><table>'+mr+'</table></div>';
}
function renderCards(){
  var d=DATA;
  var pages={
    top_pages:{tag:'Verhalten',title:'Top-Seiten',help:'Welche Seiten/Artikel tatsächlich aufgerufen wurden, mit Trend gegen die Vorperiode.',rows:pathTrendRows(d.top_paths)}
  };
  var sources={
    top_sources:{tag:'Akquise',title:'Top-Quellen (UTM-Source)',help:'Gruppiert nach dem Tag im geklickten Link, unabhängig vom technischen Referrer.',rows:barRows(d.top_utm_sources,function(r){return r.utm_source;})},
    top_campaigns:{tag:'Akquise',title:'Top-Kampagnen',help:'Quelle und Kampagnenname zusammen, zeigt welcher einzelne Post/Link wie viel gebracht hat.',rows:barRows(d.top_utm_campaigns,function(r){return r.utm_source+' / '+r.utm_campaign;})},
    top_referrers:{tag:'Akquise',title:'Top-Referrer',help:'Technische Herkunfts-Domain laut Browser, Google und Instagram jeweils zusammengefasst. Zeigt auch Besuche ohne UTM-Link.',rows:barRows(d.top_referrers,function(r){return r.ref_host;})}
  };
  function build(box,defs,key){
    if(!box)return;
    box.innerHTML=Object.keys(defs).map(function(id){
      var c=defs[id];
      return '<div class="card" draggable="true" data-id="'+id+'"><div class="draghandle" title="Ziehen zum Verschieben">&#10287;</div><h2>'+esc(c.title)+' <span class="cardtag">'+esc(c.tag)+'</span></h2><p class="help">'+esc(c.help)+'</p><table>'+c.rows+'</table></div>';
    }).join('');
    applyCardOrder(box,key);wireDrag(box,key);
  }
  build(document.getElementById('cards-pages'),pages,'vg_cards_pages');
  build(document.getElementById('cards-sources'),sources,'vg_cards_sources');
}

/* ---- Kachel-Reihenfolge (nur lokal) ---- */
function applyCardOrder(box,key){
  var KEY=key||'vg_stats_card_order';
  try{
    var saved=JSON.parse(localStorage.getItem(KEY)||'[]');
    saved.slice().reverse().forEach(function(id){
      var el=box.querySelector('[data-id="'+id+'"]');
      if(el)box.insertBefore(el,box.firstChild);
    });
  }catch(e){}
}
function wireDrag(box,key){
  var KEY=key||'vg_stats_card_order';
  function saveOrder(){try{localStorage.setItem(KEY,JSON.stringify(Array.prototype.map.call(box.children,function(c){return c.getAttribute('data-id');})));}catch(e){}}
  var dragEl=null;
  box.querySelectorAll('.card').forEach(function(card){
    card.addEventListener('dragstart',function(){dragEl=card;card.classList.add('dragging');});
    card.addEventListener('dragend',function(){card.classList.remove('dragging');box.querySelectorAll('.card').forEach(function(c){c.classList.remove('over');});saveOrder();});
    card.addEventListener('dragover',function(e){
      e.preventDefault();if(card===dragEl)return;
      var rect=card.getBoundingClientRect(),before=(e.clientY-rect.top)<rect.height/2;
      box.querySelectorAll('.card').forEach(function(c){c.classList.remove('over');});
      card.classList.add('over');
      if(before)box.insertBefore(dragEl,card);else box.insertBefore(dragEl,card.nextSibling);
    });
  });
}

/* ---- Laden ---- */
function currentQuery(){
  var p=new URLSearchParams(location.search);
  var q={};
  if(p.get('preset'))q.preset=p.get('preset');
  else if(p.get('from')&&p.get('to')){q.from=p.get('from');q.to=p.get('to');}
  else q.days=p.get('days')||'30';
  return q;
}
function load(q){
  GRAN_LOCK=false;
  var usp=new URLSearchParams();usp.set('format','json');
  Object.keys(q).forEach(function(k){usp.set(k,q[k]);});
  document.getElementById('dash').innerHTML='<div class="loading">Lade Daten...</div>';
  fetch(API+'?'+usp.toString(),{headers:{'Accept':'application/json'}})
    .then(function(r){return r.json();})
    .then(function(d){DATA=d;if(DATA.prev_series){DATA.series.forEach(function(s,i){s.p=DATA.prev_series[i]||0;});}syncControls();renderDash();})
    .catch(function(){document.getElementById('dash').innerHTML='<div class="loading">Laden fehlgeschlagen.</div>';});
}
function loadDetail(day){
  fetch(API+'?format=json&from='+day+'&to='+day,{headers:{'Accept':'application/json'}})
    .then(function(r){return r.json();})
    .then(function(d){DET=d;renderDetail(day);})
    .catch(function(){});
}
function setUrl(q){
  var usp=new URLSearchParams();
  Object.keys(q).forEach(function(k){usp.set(k,q[k]);});
  history.replaceState(null,'',location.pathname+'?'+usp.toString());
}
function syncControls(){
  var r=DATA.range;
  document.querySelectorAll('#quicktabs .tab').forEach(function(t){
    var pre=t.getAttribute('data-preset');
    if(pre)t.classList.toggle('active',r.preset===pre);
    else t.classList.toggle('active',!r.custom&&!r.preset&&+t.getAttribute('data-days')===r.days);
  });
  document.getElementById('fromDate').value=r.from;
  document.getElementById('toDate').value=r.to;
}

document.getElementById('quicktabs').addEventListener('click',function(e){
  var t=e.target.closest('.tab');if(!t)return;
  var q=t.getAttribute('data-preset')?{preset:t.getAttribute('data-preset')}:{days:t.getAttribute('data-days')};
  setUrl(q);load(q);
});
document.getElementById('applyRange').addEventListener('click',function(){
  var f=document.getElementById('fromDate').value,t=document.getElementById('toDate').value;
  if(!f||!t){alert('Bitte Von- und Bis-Datum wählen.');return;}
  var q={from:f,to:t};setUrl(q);load(q);
});
document.getElementById('applyDay').addEventListener('click',function(){
  var f=document.getElementById('fromDate').value;
  if(!f){alert('Bitte ein Von-Datum wählen.');return;}
  var q={from:f,to:f};setUrl(q);load(q);
});

var reTimer;
window.addEventListener('resize',function(){clearTimeout(reTimer);reTimer=setTimeout(function(){if(DATA){renderMain();renderHeatmap();if(DET&&document.getElementById('detailDate'))renderDetail(document.getElementById('detailDate').value);}},180);});

/* ---- Kompletter Analytics-Export als Markdown für Claude ---- */
function exportTopSlots(){
  var grid=[];for(var r=0;r<7;r++)grid.push(new Array(24).fill(0));
  (DATA.series||[]).forEach(function(s){var day=new Date(s.t.slice(0,10)+'T00:00:00').getDay();grid[(day+6)%7][+s.t.slice(11,13)]+=s.c;});
  var rows=['Mo','Di','Mi','Do','Fr','Sa','So'],flat=[];
  for(var r=0;r<7;r++)for(var h=0;h<24;h++)if(grid[r][h]>0)flat.push({d:rows[r],h:h,c:grid[r][h]});
  flat.sort(function(a,b){return b.c-a.c;});
  return flat.slice(0,5);
}
function buildExport(){
  var d=DATA, g=GSC_DATA||{pages:[],queries:[],meta:{}};
  var pipe=function(s){return String(s==null?'':s).replace(/\|/g,'/').replace(/\n/g,' ');};
  var md=function(hs,rs){return '| '+hs.join(' | ')+' |\n|'+hs.map(function(){return '---';}).join('|')+'|\n'+rs.map(function(r){return '| '+r.join(' | ')+' |';}).join('\n')+'\n';};
  var trend=function(c,p){if(p<=0)return c>0?'neu':'-';var x=Math.round((c-p)/p*100);return (x>0?'+':'')+x+'%';};
  var withPot=function(arr){return (arr||[]).map(function(r){var o={};for(var k in r)o[k]=r[k];o.pot=gscPot(r);return o;}).sort(function(a,b){return b.pot-a.pot;});};
  var o=[];
  o.push('# ViceGuide Analytics-Export');
  o.push('Erzeugt: '+new Date().toLocaleString('de-DE')+'  |  Zeitraum First-Party-Statistik: '+rangeLabel());
  o.push('');
  o.push('## Auftrag');
  o.push('Du bist SEO- und Content-Partner für ViceGuide (deutschsprachiges GTA-6-Fanportal). Analysiere die folgenden Zahlen und leite konkrete Empfehlungen ab: welche bestehenden Artikel nachgeschärft werden sollten (Titel, Meta-Description, interne Verlinkung, um CTR und Position zu verbessern), welche neuen Themen fehlen (aus internen Suchen mit 0 Treffern und aus Google-Suchanfragen ohne passende eigene Seite), worauf bei kommenden Artikeln zu achten ist, und welche gut laufenden Seiten ausgebaut werden sollten. Halte dich an die ViceGuide-Regeln aus CLAUDE.md (Ton eines Gaming-Redakteurs, keine Gedankenstriche).');
  o.push('');
  o.push('### Hinweise zur Interpretation');
  o.push('- First-Party-Zahlen zählen Seitenaufrufe (keine Unique Visitors), cookiefrei, Zeiten in Europe/Berlin.');
  o.push('- Instagram ist nur über den UTM-Tag verlässlich, der technische Referrer wird von Instagram meist entfernt.');
  o.push('- Google-Suchanfragen samt CTR und Position kommen aus der Search Console und sind der beste SEO-Hebel.');
  o.push('- Spalte Potenzial: grobe Schätzung ungenutzter Klicks (viele Impressionen, verbesserbare Position/CTR), nur zur Priorisierung.');
  o.push('');
  o.push('## Überblick');
  o.push('- Seitenaufrufe gesamt: '+d.total+' (Vorperiode '+d.prev_total+', '+trend(d.total,d.prev_total)+')');
  o.push('- Instagram per UTM-Tag: '+d.instagram.by_utm+' (Vorperiode '+d.instagram.prev_by_utm+')');
  var ch=d.channels.cur; o.push('- Kanäle: Suche '+ch.search+', Social '+ch.social+', Verweis '+ch.referral+', Direkt '+ch.direct);
  o.push('');
  var slots=exportTopSlots();
  if(slots.length){o.push('## Beste Zeiten (Wochentag und Stunde, meiste Aufrufe)');o.push(slots.map(function(s){return '- '+s.d+' '+pad2(s.h)+':00 Uhr: '+s.c;}).join('\n'));o.push('');}
  if(d.top_paths&&d.top_paths.length){o.push('## Top-Seiten First-Party (mit Trend gegen Vorperiode)');o.push(md(['Seite','Aufrufe','Trend'],d.top_paths.slice(0,20).map(function(r){return [pipe(pathLabel(r.path)),r.c,trend(r.c,r.prev||0)];})));}
  if(d.entries_search&&d.entries_search.length){o.push('## Einstiege aus Suchmaschinen (First-Party)');o.push(md(['Seite','Einstiege'],d.entries_search.slice(0,15).map(function(r){return [pipe(pathLabel(r.path)),r.c];})));}
  if(d.searches&&d.searches.length){o.push('## Interne Suche auf der Seite (Content-Nachfrage)');o.push(md(['Suchbegriff','Anzahl','Treffer'],d.searches.slice(0,20).map(function(r){return [pipe(r.q),r.c,(r.maxres===0?'0 (LUECKE)':'ja')];})));}
  var eng=(d.engagement&&d.engagement.rows)||[];
  if(eng.length){o.push('## Lese-Engagement (Verweildauer, Scrolltiefe)');o.push(md(['Artikel','Aufrufe','Verweildauer','Scrolltiefe'],eng.slice(0,20).map(function(r){return [pipe(pathLabel(r.path)),r.c,fmtSecs(r.avg_sec),r.avg_depth+'%'];})));}
  if(d.top_referrers&&d.top_referrers.length){o.push('## Herkunft (Referrer, Google und Instagram zusammengefasst)');o.push(md(['Quelle','Aufrufe'],d.top_referrers.slice(0,12).map(function(r){return [pipe(r.ref_host),r.c];})));}
  if(d.top_utm_campaigns&&d.top_utm_campaigns.length){o.push('## Kampagnen (UTM)');o.push(md(['Quelle / Kampagne','Aufrufe'],d.top_utm_campaigns.slice(0,15).map(function(r){return [pipe(r.utm_source+' / '+r.utm_campaign),r.c];})));}
  var gq=withPot(g.queries), gp=withPot(g.pages), mp=g.meta||{};
  if(gq.length||gp.length){
    o.push('## Google Search Console');
    o.push('Stand: '+((mp.query&&(mp.query.range||mp.query.imported))||(mp.page&&(mp.page.range||mp.page.imported))||'unbekannt'));
    o.push('');
    if(gq.length){o.push('### Google-Suchanfragen (nach Potenzial)');o.push(md(['Suchanfrage','Klicks','Impr','CTR','Pos','Potenzial'],gq.slice(0,30).map(function(r){return [pipe(r.label),r.clicks,r.impressions,(+r.ctr).toFixed(1)+'%',(+r.position).toFixed(1),r.pot];})));}
    if(gp.length){o.push('### Google-Seiten (nach Potenzial)');o.push(md(['Seite','Klicks','Impr','CTR','Pos','Potenzial'],gp.slice(0,30).map(function(r){return [pipe(pathLabel(gscShortPath(r.label))),r.clicks,r.impressions,(+r.ctr).toFixed(1)+'%',(+r.position).toFixed(1),r.pot];})));}
  } else {
    o.push('## Google Search Console');o.push('Noch keine Search-Console-Daten importiert.');
  }
  return o.join('\n');
}
function vgExportOpen(){
  if(!DATA){alert('Daten noch nicht geladen.');return;}
  var txt=buildExport();
  var ov=document.createElement('div');ov.className='expmodal';
  ov.innerHTML='<div class="expbox"><div class="exphead"><b>Analytics-Export für Claude</b><button class="expx" title="Schließen">&#10005;</button></div>'
    +'<p class="exphint">Kopier den Text in deinen ViceGuide-Claude-Chat oder lad ihn als Datei ins Projekt. Tipp: für einen aussagekräftigen Export vorher oben einen längeren Zeitraum wählen (z.B. 30 oder 90 Tage). Die Search-Console-Zahlen sind unabhängig vom Zeitraum und immer der zuletzt hochgeladene Stand.</p>'
    +'<textarea class="exparea" readonly></textarea>'
    +'<div class="expbtns"><button class="applybtn" data-a="copy">In Zwischenablage kopieren</button><button class="applybtn ghost" data-a="dl">Als .md-Datei herunterladen</button><span class="gscmsg" data-r></span></div></div>';
  document.body.appendChild(ov);
  var ta=ov.querySelector('.exparea');ta.value=txt;
  var res=ov.querySelector('[data-r]');
  ov.querySelector('.expx').onclick=function(){ov.remove();};
  ov.addEventListener('click',function(e){if(e.target===ov)ov.remove();});
  ov.querySelector('[data-a="copy"]').onclick=function(){
    ta.focus();ta.select();
    var done=function(){res.textContent='Kopiert.';res.className='gscmsg ok';};
    if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(txt).then(done).catch(function(){try{document.execCommand('copy');done();}catch(e){res.textContent='Bitte manuell markieren und kopieren.';res.className='gscmsg err';}});}
    else{try{document.execCommand('copy');done();}catch(e){res.textContent='Bitte manuell markieren und kopieren.';res.className='gscmsg err';}}
  };
  ov.querySelector('[data-a="dl"]').onclick=function(){
    var blob=new Blob([txt],{type:'text/markdown'});var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='viceguide-analytics-'+(DATA.range.to||'export')+'.md';document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(a.href);a.remove();},100);
    res.textContent='Datei erzeugt.';res.className='gscmsg ok';
  };
}
function vgResetStats(){
  if(!confirm('Wirklich ALLE bisher gesammelten Statistik-Daten unwiderruflich löschen? Das kann nicht rückgängig gemacht werden.'))return;
  fetch(API,{method:'DELETE'}).then(function(r){if(r.ok){load(currentQuery());}else{alert('Löschen fehlgeschlagen.');}}).catch(function(){alert('Löschen fehlgeschlagen.');});
}

load(currentQuery());
</script>
</body></html>
HTML;
}
