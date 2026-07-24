<?php
/*
 * Twitch-Live-Status fuer Creator-Profile (Stufe 3 des Creator-Bereichs).
 *
 * GET ?login=<twitch_login> -> {configured:bool, live:bool, viewers?, title?, game?}
 *
 * Wird clientseitig von der Creator-Seite (creator.php) aufgerufen, um die
 * LIVE-Anzeige zu setzen, ohne das Server-Rendering zu blockieren. Der Browser
 * spricht NUR diesen eigenen Endpunkt an, nie Twitch direkt (DSGVO: kein
 * Drittanbieter-Kontakt ohne Klick; der echte Stream-Embed laedt weiterhin
 * erst per Klick).
 *
 * Zugangsdaten kommen aus config.php (twitch_client_id/twitch_client_secret,
 * eine App unter dev.twitch.tv). Fehlen sie, liefert der Endpunkt sauber
 * {configured:false} und die Seite bleibt beim illustrativen Zustand.
 *
 * Schutz gegen Missbrauch als offener Twitch-Proxy: es werden nur Logins
 * echter, aktiver Creator abgefragt. Ergebnis wird kurz (90s) je Login und der
 * App-Token bis kurz vor Ablauf in site_settings gecacht (schont die API).
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
[$pdo, $cfg] = vg_db();

function tw_out($d, int $code = 200): never {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

$clientId = trim((string)($cfg['twitch_client_id'] ?? ''));
$clientSecret = trim((string)($cfg['twitch_client_secret'] ?? ''));
if ($clientId === '' || $clientSecret === '') {
    header('Cache-Control: public, max-age=300');
    tw_out(['configured' => false]);
}

$login = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_GET['login'] ?? '')));
$login = substr($login, 0, 30);
if ($login === '') tw_out(['error' => 'login fehlt'], 400);

// Nur Logins echter, aktiver Creator zulassen (kein offener Proxy).
$chk = $pdo->prepare("SELECT 1 FROM creators WHERE LOWER(twitch_login) = ? AND active = 1 LIMIT 1");
$chk->execute([$login]);
if (!$chk->fetch()) {
    header('Cache-Control: public, max-age=300');
    tw_out(['configured' => true, 'live' => false]);
}

// Kleiner Key-Value-Cache (dieselbe Tabelle wie api/settings.php).
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

// Ergebnis-Cache je Login (90s): schont die API, macht Reloads schnell.
$cacheKey = 'tw_live_' . $login;
$cached = $getS($cacheKey);
if ($cached) {
    $c = json_decode($cached, true);
    if (is_array($c) && isset($c['t']) && (time() - (int)$c['t'] < 90)) {
        unset($c['t']);
        header('Cache-Control: public, max-age=60');
        tw_out($c);
    }
}

function tw_http(string $url, array $headers = [], ?string $post = null): array {
    if (!function_exists('curl_init')) return [0, ''];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, (string)$body];
}

// App-Access-Token (client_credentials), bis kurz vor Ablauf gecacht.
$token = null;
$te = $getS('tw_token');
if ($te) {
    $t = json_decode($te, true);
    if (is_array($t) && isset($t['exp']) && (int)$t['exp'] > time() + 60) $token = $t['tok'];
}
if (!$token) {
    [$code, $body] = tw_http('https://id.twitch.tv/oauth2/token', [], http_build_query([
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'grant_type'    => 'client_credentials',
    ]));
    $j = json_decode($body, true);
    if ($code === 200 && !empty($j['access_token'])) {
        $token = $j['access_token'];
        $setS('tw_token', json_encode(['tok' => $token, 'exp' => time() + (int)($j['expires_in'] ?? 3600)]));
    }
}
if (!$token) {
    header('Cache-Control: public, max-age=60');
    tw_out(['configured' => true, 'live' => false]);
}

[$code, $body] = tw_http('https://api.twitch.tv/helix/streams?user_login=' . urlencode($login), [
    'Client-Id: ' . $clientId,
    'Authorization: Bearer ' . $token,
]);
$out = ['configured' => true, 'live' => false];
if ($code === 200) {
    $j = json_decode($body, true);
    if (!empty($j['data'][0])) {
        $s = $j['data'][0];
        $out['live'] = true;
        $out['viewers'] = (int)($s['viewer_count'] ?? 0);
        $out['title'] = (string)($s['title'] ?? '');
        $out['game'] = (string)($s['game_name'] ?? '');
    }
}
$setS($cacheKey, json_encode(array_merge($out, ['t' => time()])));
header('Cache-Control: public, max-age=60');
tw_out($out);
