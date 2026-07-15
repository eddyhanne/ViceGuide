<?php
/*
 * Kleiner, generischer Einstellungs-Speicher fuer seitenweite Kuration, die
 * fuer ALLE Besucher gelten soll (nicht nur im Browser des Admins).
 *
 * GET  -> aktuelle Einstellungen als JSON (oeffentlich, kurz gecacht).
 * PUT  -> Einstellungen setzen (Admin). Body darf enthalten:
 *           news_cat_order  (Array von Kategorie-IDs, Reihenfolge der News-Liste)
 *           news_pinned_cat (Kategorie-ID, wird bei "Alle News" oben aufgeklappt)
 *
 * Ablage in einer schlanken Key-Value-Tabelle (site_settings), portabel fuer
 * MySQL (Prod) und SQLite (lokal), daher SELECT-dann-UPDATE/INSERT statt
 * datenbankspezifischem UPSERT (analog sections.php).
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
[$pdo, $cfg] = vg_db();
$method = $_SERVER['REQUEST_METHOD'];

require __DIR__ . '/../cache.php';
if ($method === 'PUT') {
    register_shutdown_function(function () {
        $c = http_response_code();
        if ($c >= 200 && $c < 300) vg_cache_flush();
    });
}

$pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (skey VARCHAR(64) PRIMARY KEY, sval TEXT)");

function vg_out_set($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function vg_setting_get($pdo, $k) {
    $st = $pdo->prepare("SELECT sval FROM site_settings WHERE skey = ?");
    $st->execute([$k]);
    $r = $st->fetch();
    return $r ? $r['sval'] : null;
}
function vg_setting_set($pdo, $k, $v) {
    $st = $pdo->prepare("SELECT skey FROM site_settings WHERE skey = ?");
    $st->execute([$k]);
    if ($st->fetch()) {
        $pdo->prepare("UPDATE site_settings SET sval = ? WHERE skey = ?")->execute([$v, $k]);
    } else {
        $pdo->prepare("INSERT INTO site_settings (skey, sval) VALUES (?, ?)")->execute([$k, $v]);
    }
}

if ($method === 'GET') {
    $order = vg_setting_get($pdo, 'news_cat_order');
    $pin   = vg_setting_get($pdo, 'news_pinned_cat');
    header('Cache-Control: public, max-age=120, stale-while-revalidate=600');
    vg_out_set([
        'news_cat_order'  => $order ? (json_decode($order, true) ?: []) : [],
        'news_pinned_cat' => $pin ?: '',
    ]);
}

if ($method === 'PUT') {
    vg_require_admin($cfg);
    $b = json_decode(file_get_contents('php://input'), true);
    if (!is_array($b)) $b = [];
    if (array_key_exists('news_cat_order', $b)) {
        vg_setting_set($pdo, 'news_cat_order', json_encode(array_values((array)$b['news_cat_order']), JSON_UNESCAPED_UNICODE));
    }
    if (array_key_exists('news_pinned_cat', $b)) {
        vg_setting_set($pdo, 'news_pinned_cat', (string)$b['news_pinned_cat']);
    }
    vg_out_set(['ok' => true]);
}

vg_out_set(['error' => 'Methode nicht unterstuetzt'], 405);
