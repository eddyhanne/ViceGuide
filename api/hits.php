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
 * GET  (Admin) -> interaktives Dashboard (HTML) oder, mit ?format=json, die
 *      aggregierten Rohdaten. Zeitraum ueber ?days=N ODER ?from=YYYY-MM-DD&
 *      to=YYYY-MM-DD (Berliner Kalendertage, inklusive). Die Zeitreihe kommt
 *      stundengenau in Europe/Berlin zurueck, der Client bucketet daraus Tag,
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

vg_out_h(['error' => 'Methode nicht unterstuetzt'], 405);

/* ---------------------------------------------------------------------------
 * Datenaufbereitung
 * ------------------------------------------------------------------------- */

/* Bekannte Schreibweisen derselben Herkunft zu einer Zeile zusammenfassen, nur
   fuer die Anzeige, die Rohdaten bleiben unangetastet (analog canonCat() im
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

function vg_build_stats(PDO $pdo, array $cfg): array {
    $isSqlite = str_starts_with($cfg['db_dsn'], 'sqlite:');
    $berlin = new DateTimeZone('Europe/Berlin');
    $utc = new DateTimeZone('UTC');

    $preset = (string)($_GET['preset'] ?? '');
    $fromP = (string)($_GET['from'] ?? '');
    $toP = (string)($_GET['to'] ?? '');
    $custom = (bool)(preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromP) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toP));

    if ($preset === 'yesterday') {
        // Gestern als voller Kalendertag (Berlin), fuer den direkten
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
    // Europe/Berlin umgerechnet und in eine luckenlose Stundenreihe gefuellt
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

    $topPaths = $run("SELECT path, COUNT(*) c FROM hits WHERE created_at >= ? AND created_at < ? GROUP BY path ORDER BY c DESC LIMIT 25", [$startU, $endU])->fetchAll();

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
        'series' => $series,
        'top_paths' => $topPaths,
        'top_referrers' => $topRef,
        'top_utm_sources' => $topSrc,
        'top_utm_campaigns' => $topCmp,
        'note' => 'Instagram haengt den echten Referrer meist ab. Der Wert "Instagram (per UTM-Tag)" aus dem Bio-Link/Story-Sticker (?utm_source=instagram) ist verlaesslich, der Referrer nur zur Kontrolle. Google und Instagram sind in den Referrern jeweils zu einer Zeile zusammengefasst.',
    ];
}

/* ---------------------------------------------------------------------------
 * Dashboard-Huelle. Statisches HTML plus CSS/JS, die Daten holt der Client
 * selbst per ?format=json, damit Zeitraum, Granularitaet und Tagesdetail ohne
 * Server-Reload wechseln koennen.
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
.empty2{color:var(--soft);font-style:italic;padding:40px 0;text-align:center}
.detailmeta{display:flex;gap:26px;flex-wrap:wrap;margin-top:10px}
.detailmeta .mini{min-width:220px;flex:1}
.detailmeta .mini h3{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--soft);margin:0 0 6px}
.tip{position:fixed;z-index:50;display:none;pointer-events:none;background:var(--text);color:#fff;padding:7px 10px;border-radius:8px;font-size:.75rem;line-height:1.35;box-shadow:0 6px 18px -6px rgba(0,0,0,.4)}
.tip b{font-size:.9rem}
.tip span{opacity:.85}
#vg-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start}
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
td.barcell{width:100%}
.bar{height:8px;background:var(--accent);border-radius:4px;min-width:2px;opacity:.85}
td.empty{color:var(--soft);font-style:italic;padding:10px 4px;border-bottom:none}
.note{color:var(--soft);font-size:.75rem;margin-top:16px;line-height:1.5}
.loading{color:var(--soft);font-size:.85rem;padding:40px 0;text-align:center}
</style></head><body>
<div class="topbar"><div><h1>ViceGuide Statistik</h1>
<p class="sub">Eigenes, cookiefreies Tracking. Zaehlt echte Seitenaufrufe (jeden Ansichtswechsel in der App), nicht jeden einzelnen Klick. Dein eigener Admin-Login zaehlt nicht mit. Zeiten in Europe/Berlin.</p></div>
<button class="resetbtn" onclick="vgResetStats()">Alle Daten zuruecksetzen</button></div>

<div class="controls">
  <div class="ctrlgrp"><span class="ctrllbl">Schnellauswahl</span>
    <div class="tabs" id="quicktabs">
      <span class="tab" data-days="1">Heute</span>
      <span class="tab" data-preset="yesterday">Gestern</span>
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

<script>
var API=location.pathname;
var DATA=null, DET=null, GRAN='day', GRAN_LOCK=false;
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
function showTip(e,el){ensureTip();tip.innerHTML='<b>'+esc(el.getAttribute('data-c'))+'</b> Aufrufe<br><span>'+esc(el.getAttribute('data-full'))+'</span>';tip.style.display='block';moveTip(e);}
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
  var rawMax=1;buckets.forEach(function(b){if(b.c>rawMax)rawMax=b.c;});
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
    bars+='<rect x="'+(padL+slot*i).toFixed(1)+'" y="'+padT+'" width="'+slot.toFixed(1)+'" height="'+plotH+'" fill="transparent" class="hit'+(opts.clickable&&b.day?' clk':'')+'" data-full="'+esc(b.full)+'" data-c="'+b.c+'"'+(b.day?' data-day="'+esc(b.day)+'"':'')+'></rect>';
    if(nn<=28&&b.c>0)labels+='<text x="'+cx.toFixed(1)+'" y="'+(y-4).toFixed(1)+'" class="vl" text-anchor="middle">'+b.c+'</text>';
    if(i%step===0||i===nn-1){
      labels+='<text x="'+cx.toFixed(1)+'" y="'+(H-(opts.subLabels?18:12)).toFixed(1)+'" class="xl" text-anchor="middle">'+esc(b.label)+'</text>';
      if(opts.subLabels&&b.sub)labels+='<text x="'+cx.toFixed(1)+'" y="'+(H-5)+'" class="xs" text-anchor="middle">'+esc(b.sub)+'</text>';
    }
  });
  // Passt alles rein, wird das SVG auf 100% Breite gerendert, damit es bei
  // Subpixel-Rundung oder auftauchendem Seiten-Scrollbalken nie uebersteht
  // (kein ungewollter horizontaler Scrollbalken). Nur wenn mehr Balken als
  // Platz da sind, feste Pixelbreite plus horizontales Scrollen.
  var wAttr=fits?'100%':W;
  var par=fits?'none':'xMidYMid meet';
  host.innerHTML='<svg width="'+wAttr+'" height="'+H+'" viewBox="0 0 '+W+' '+H+'" preserveAspectRatio="'+par+'" class="bars">'+g+bars+labels+'</svg>';
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
  series.forEach(function(s){var d=s.t.slice(0,10);m.set(d,(m.get(d)||0)+s.c);});
  var out=[];m.forEach(function(c,d){out.push({day:d,label:fmtDay(d),full:wdDay(d)+', '+fmtFullDay(d),c:c});});
  return out;
}
function bucket6h(series){
  var m=new Map();
  series.forEach(function(s){
    var d=s.t.slice(0,10),h=+s.t.slice(11,13),blk=Math.floor(h/6)*6,key=d+' '+blk;
    if(!m.has(key))m.set(key,{day:d,blk:blk,c:0});
    m.get(key).c+=s.c;
  });
  var out=[];m.forEach(function(o){
    var end=o.blk+6;
    out.push({day:o.day,label:pad2(o.blk),sub:fmtDay(o.day),full:fmtDay(o.day)+' '+pad2(o.blk)+' bis '+pad2(end===24?24:end)+' Uhr',c:o.c});
  });
  return out;
}
function bucketHour(series){
  return series.map(function(s){
    var d=s.t.slice(0,10),h=+s.t.slice(11,13);
    return {day:d,label:pad2(h),sub:fmtDay(d),full:fmtDay(d)+' '+pad2(h)+':00 bis '+pad2((h+1)%24)+':00 Uhr',c:s.c};
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
  if(p>0)return '<span class="delta up">+'+p+'% ggue. Vorperiode</span>';
  if(p<0)return '<span class="delta down">'+p+'% ggue. Vorperiode</span>';
  return '<span class="delta flat">0% ggue. Vorperiode</span>';
}
function barRows(rows,labelFn){
  if(!rows||!rows.length)return '<tr><td colspan="3" class="empty">Noch keine Daten in diesem Zeitraum.</td></tr>';
  var max=1;rows.forEach(function(r){if(+r.c>max)max=+r.c;});
  return rows.map(function(r){
    var lab=labelFn(r),pct=Math.round(r.c/max*100);
    return '<tr><td class="lbl" title="'+esc(lab)+'">'+esc(lab)+'</td><td class="barcell"><div class="bar" style="width:'+pct+'%"></div></td><td class="num">'+r.c+'</td></tr>';
  }).join('');
}

/* ---- Rendering ---- */
function defaultGran(days){if(days<=1)return 'hour';if(days<=3)return '6h';return 'day';}

function renderDash(){
  var d=DATA;
  var topSrc=(d.top_utm_sources[0]||{}),topPath=(d.top_paths[0]||{});
  var h='';
  h+='<div class="sectionlbl">Uebersicht ('+esc(rangeLabel())+')</div>';
  h+='<div class="tiles">';
  h+='<div class="tile"><div class="n">'+d.total+'</div><div class="l">Seitenaufrufe gesamt</div>'+deltaHtml(d.total,d.prev_total)+'</div>';
  h+='<div class="tile"><div class="n">'+d.instagram.by_utm+'</div><div class="l">Instagram (per UTM-Tag)</div>'+deltaHtml(d.instagram.by_utm,d.instagram.prev_by_utm)+'</div>';
  h+='<div class="tile"><div class="n small">'+esc(topSrc.utm_source||'noch keine')+'</div><div class="l">Top-Quelle ('+(topSrc.c||0)+')</div><div class="l2">Woher die meisten Besucher mit UTM-Tag kamen.</div></div>';
  h+='<div class="tile"><div class="n small">'+esc(topPath.path?pathLabel(topPath.path):'noch keine')+'</div><div class="l">Top-Seite ('+(topPath.c||0)+')</div><div class="l2">Am haeufigsten aufgerufene Seite/Artikel.</div></div>';
  h+='</div>';

  h+='<div class="chartcard">';
  h+='<div class="charthead"><h2>Verlauf</h2>';
  h+='<div class="seg" id="gran"><button data-g="hour">Stunde</button><button data-g="6h">6 Std</button><button data-g="day">Tag</button></div></div>';
  h+='<p class="help">Balken pro Zeitfenster, saubere Skala rechts abgelesen. Balken anfahren zeigt Fenster und genaue Zahl. Balken anklicken oeffnet den 24-Stunden-Verlauf des Tages unten.</p>';
  h+='<div class="chartscroll"><div id="mainchart"></div></div></div>';

  h+='<div class="chartcard">';
  h+='<div class="charthead"><h2 id="detailtitle">Tages-Detail</h2>';
  h+='<div class="daterow"><span class="ctrllbl">Tag</span><input type="date" id="detailDate"></div></div>';
  h+='<p class="help">Voller 24-Stunden-Verlauf eines einzelnen Tages. Zeigt genau, zu welcher Uhrzeit die Aufrufe reinkamen. Tag frei waehlbar, unabhaengig vom Zeitraum oben.</p>';
  h+='<div class="chartscroll"><div id="detailchart"></div></div>';
  h+='<div class="detailmeta" id="detailmeta"></div></div>';

  h+='<div class="sectionlbl">Akquise &amp; Verhalten, Reihenfolge per Ziehen anpassbar</div>';
  h+='<div id="vg-cards"></div>';
  h+='<p class="note">'+esc(d.note)+' Die Kachel-Reihenfolge wird nur in diesem Browser gemerkt.</p>';
  document.getElementById('dash').innerHTML=h;

  // Granularitaet
  if(!GRAN_LOCK)GRAN=defaultGran(d.range.days);
  document.querySelectorAll('#gran button').forEach(function(b){
    b.classList.toggle('active',b.getAttribute('data-g')===GRAN);
    b.addEventListener('click',function(){GRAN=b.getAttribute('data-g');GRAN_LOCK=true;document.querySelectorAll('#gran button').forEach(function(x){x.classList.toggle('active',x===b);});renderMain();});
  });
  renderMain();
  renderCards();

  // Tagesdetail
  var di=document.getElementById('detailDate');
  var lastDay=lastActiveDay();
  di.value=lastDay;
  di.addEventListener('change',function(){if(di.value)loadDetail(di.value);});
  loadDetail(lastDay);
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
  drawBars(host,b,{clickable:true,minSlot:minSlot,subLabels:GRAN!=='day',onClick:function(day){var di=document.getElementById('detailDate');di.value=day;loadDetail(day);document.getElementById('detailtitle').scrollIntoView({behavior:'smooth',block:'center'});}});
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
  var cards={
    top_pages:{tag:'Verhalten',title:'Top-Seiten',help:'Welche Seiten/Artikel tatsaechlich aufgerufen wurden, unabhaengig davon woher der Besuch kam.',rows:barRows(d.top_paths,function(r){return pathLabel(r.path);})},
    top_sources:{tag:'Akquise',title:'Top-Quellen (UTM-Source)',help:'Gruppiert nach dem Tag im geklickten Link, unabhaengig vom technischen Referrer.',rows:barRows(d.top_utm_sources,function(r){return r.utm_source;})},
    top_campaigns:{tag:'Akquise',title:'Top-Kampagnen',help:'Quelle und Kampagnenname zusammen, zeigt welcher einzelne Post/Link wie viel gebracht hat.',rows:barRows(d.top_utm_campaigns,function(r){return r.utm_source+' / '+r.utm_campaign;})},
    top_referrers:{tag:'Akquise',title:'Top-Referrer',help:'Technische Herkunfts-Domain laut Browser, Google und Instagram jeweils zusammengefasst. Zeigt auch Besuche ohne UTM-Link.',rows:barRows(d.top_referrers,function(r){return r.ref_host;})}
  };
  var box=document.getElementById('vg-cards');
  var html='';
  Object.keys(cards).forEach(function(id){
    var c=cards[id];
    html+='<div class="card" draggable="true" data-id="'+id+'"><div class="draghandle" title="Ziehen zum Verschieben">&#10287;</div><h2>'+esc(c.title)+' <span class="cardtag">'+esc(c.tag)+'</span></h2><p class="help">'+esc(c.help)+'</p><table>'+c.rows+'</table></div>';
  });
  box.innerHTML=html;
  applyCardOrder(box);
  wireDrag(box);
}

/* ---- Kachel-Reihenfolge (nur lokal) ---- */
function applyCardOrder(box){
  var KEY='vg_stats_card_order';
  try{
    var saved=JSON.parse(localStorage.getItem(KEY)||'[]');
    saved.slice().reverse().forEach(function(id){
      var el=box.querySelector('[data-id="'+id+'"]');
      if(el)box.insertBefore(el,box.firstChild);
    });
  }catch(e){}
}
function wireDrag(box){
  var KEY='vg_stats_card_order';
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
    .then(function(d){DATA=d;syncControls();renderDash();})
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
  if(!f||!t){alert('Bitte Von- und Bis-Datum waehlen.');return;}
  var q={from:f,to:t};setUrl(q);load(q);
});
document.getElementById('applyDay').addEventListener('click',function(){
  var f=document.getElementById('fromDate').value;
  if(!f){alert('Bitte ein Von-Datum waehlen.');return;}
  var q={from:f,to:f};setUrl(q);load(q);
});

var reTimer;
window.addEventListener('resize',function(){clearTimeout(reTimer);reTimer=setTimeout(function(){if(DATA){renderMain();if(DET&&document.getElementById('detailDate'))renderDetail(document.getElementById('detailDate').value);}},180);});

function vgResetStats(){
  if(!confirm('Wirklich ALLE bisher gesammelten Statistik-Daten unwiderruflich loeschen? Das kann nicht rueckgaengig gemacht werden.'))return;
  fetch(API,{method:'DELETE'}).then(function(r){if(r.ok){load(currentQuery());}else{alert('Loeschen fehlgeschlagen.');}}).catch(function(){alert('Loeschen fehlgeschlagen.');});
}

load(currentQuery());
</script>
</body></html>
HTML;
}
