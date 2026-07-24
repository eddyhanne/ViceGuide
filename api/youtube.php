<?php
/*
 * Neueste YouTube-Videos eines Creators (Stufe des Creator-Bereichs).
 *
 * GET ?channel=<UC-id oder @handle>&limit=3 -> {configured:bool, videos:[{id,title}]}
 *
 * Wird clientseitig von creator.php aufgerufen und ersetzt bei Erfolg die
 * (weiterhin als Fallback gepflegten) manuellen Videos durch die echten
 * neuesten Uploads. Fehlt der API-Key oder schlaegt der Aufruf fehl, bleibt es
 * bei den manuell gepflegten Videos, nichts bricht.
 *
 * Zugangsdaten: youtube_api_key in config.php (YouTube Data API v3, Google
 * Cloud). Pro Creator wird youtube_channel_id gepflegt (UC-Kanal-ID oder
 * @handle). Effizienter Weg statt der teuren search-Abfrage: ueber channels
 * die Uploads-Playlist bestimmen (lange gecacht) und daraus playlistItems
 * lesen (je 1 Quota-Einheit). Ergebnis 3h gecacht.
 *
 * Schutz gegen Missbrauch als offener API-Proxy: nur Kanaele echter, aktiver
 * Creator werden abgefragt.
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
[$pdo, $cfg] = vg_db();

function yt_out($d, int $code = 200): never {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

$key = trim((string)($cfg['youtube_api_key'] ?? ''));
if ($key === '') {
    header('Cache-Control: public, max-age=300');
    yt_out(['configured' => false]);
}

$channel = preg_replace('/[^A-Za-z0-9_@.\-]/', '', (string)($_GET['channel'] ?? ''));
$channel = substr($channel, 0, 120);
$limit = max(1, min(6, (int)($_GET['limit'] ?? 3)));
if ($channel === '') yt_out(['error' => 'channel fehlt'], 400);

// Nur Kanaele echter, aktiver Creator (kein offener Proxy auf die YT-API).
$chk = $pdo->prepare("SELECT 1 FROM creators WHERE youtube_channel_id = ? AND active = 1 LIMIT 1");
$chk->execute([$channel]);
if (!$chk->fetch()) {
    header('Cache-Control: public, max-age=300');
    yt_out(['configured' => true, 'videos' => []]);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (skey VARCHAR(64) PRIMARY KEY, sval TEXT)");
$getS = function (string $k) use ($pdo) {
    $s = $pdo->prepare("SELECT sval FROM site_settings WHERE skey = ?");
    $s->execute([$k]);
    $r = $s->fetch();
    return $r ? $r['sval'] : null;
};
$setS = function (string $k, string $v) use ($pdo) {
    $e = $pdo->prepare("SELECT skey FROM site_settings WHERE skey = ?");
    $e->execute([$k]);
    if ($e->fetch()) $pdo->prepare("UPDATE site_settings SET sval = ? WHERE skey = ?")->execute([$v, $k]);
    else $pdo->prepare("INSERT INTO site_settings (skey, sval) VALUES (?, ?)")->execute([$k, $v]);
};

// Ergebnis-Cache 3h.
$cacheKey = 'yt_v_' . md5($channel . '|' . $limit);
$cached = $getS($cacheKey);
if ($cached) {
    $c = json_decode($cached, true);
    if (is_array($c) && isset($c['t']) && (time() - (int)$c['t'] < 10800)) {
        header('Cache-Control: public, max-age=1800');
        yt_out(['configured' => true, 'videos' => $c['videos'] ?? []]);
    }
}

function yt_http(string $url): array {
    if (!function_exists('curl_init')) return [0, ''];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, (string)$body];
}

// Uploads-Playlist bestimmen (per UC-id oder @handle), 24h gecacht.
$upKey = 'yt_up_' . md5($channel);
$uploads = null;
$uc = $getS($upKey);
if ($uc) {
    $u = json_decode($uc, true);
    if (is_array($u) && isset($u['exp']) && (int)$u['exp'] > time()) $uploads = $u['pl'];
}
if (!$uploads) {
    $isId = (strpos($channel, 'UC') === 0 && strlen($channel) >= 20);
    $q = $isId ? ('id=' . urlencode($channel)) : ('forHandle=' . urlencode(ltrim($channel, '@')));
    [$code, $body] = yt_http('https://www.googleapis.com/youtube/v3/channels?part=contentDetails&' . $q . '&key=' . urlencode($key));
    $j = json_decode($body, true);
    $uploads = $j['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? null;
    if ($uploads) $setS($upKey, json_encode(['pl' => $uploads, 'exp' => time() + 86400]));
}

$videos = [];
if ($uploads) {
    [$code, $body] = yt_http('https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=' . $limit . '&playlistId=' . urlencode($uploads) . '&key=' . urlencode($key));
    $j = json_decode($body, true);
    foreach (($j['items'] ?? []) as $it) {
        $vid = $it['snippet']['resourceId']['videoId'] ?? '';
        $t = $it['snippet']['title'] ?? '';
        if ($vid !== '') $videos[] = ['id' => $vid, 'title' => $t];
    }
}
// Nur cachen, wenn wir wirklich Videos bekommen haben, sonst bei einem
// voruebergehenden API-Fehler nicht 3h lang eine leere Liste festhalten.
if ($videos) $setS($cacheKey, json_encode(['t' => time(), 'videos' => $videos]));
header('Cache-Control: public, max-age=1800');
yt_out(['configured' => true, 'videos' => $videos]);
