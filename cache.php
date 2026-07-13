<?php
/*
 * Leichter serverseitiger Full-Page-Cache fuer die SSR-Fassaden (article.php,
 * entry.php, category.php, legal.php, section.php, sitemap.php). Ziel: die
 * hohe TTFB senken, indem eine einmal gerenderte Seite als fertige Datei
 * ausgeliefert wird, komplett ohne Datenbankverbindung und ohne erneutes
 * Rendern. Der Cache-Check laeuft bewusst VOR require api/db.php, ein Treffer
 * fasst die Datenbank also gar nicht erst an.
 *
 * Invalidierung, dreifach abgesichert:
 *  1. Explizit: jede veroeffentlichende oder loeschende Schreibaktion in
 *     api/articles.php bzw. api/db_entries.php ruft vg_cache_flush(), weil sich
 *     dann oeffentlicher Inhalt aus der Datenbank geaendert hat. Reine
 *     Entwuerfe (PUT) veroeffentlichen nichts und leeren den Cache daher nicht.
 *  2. Deploy: index.html ist die gemeinsame Shell aller Fassaden. Ist sie
 *     neuer als eine Cache-Datei (frischer Push auf main), gilt die Datei als
 *     veraltet und wird neu gerendert. Gleicher Dateiname, also ohne Altlasten.
 *  3. TTL als Auffangnetz, falls doch mal ein Invalidierungssignal fehlt.
 *
 * Nie gecacht: eingeloggte Admins (Session-Cookie vorhanden), nicht-GET-
 * Anfragen und alles, was nicht mit HTTP 200 endet (z.B. 404 fuer unbekannte
 * Slugs). So sieht ein Admin nie eine veraltete Seite und 404 wird nicht
 * konserviert.
 */

function vg_cache_dir(): string { return __DIR__ . '/cache'; }

/* Cache-Schluessel nur aus dem Pfad (ohne Query-String): die echten URLs
   tragen keine Query, ein angehaengtes ?x=1 wuerde sonst denselben Inhalt
   unter beliebig vielen Schluesseln ablegen (Platte volllaufen lassen). */
function vg_cache_path(): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return vg_cache_dir() . '/' . sha1($path) . '.html';
}

function vg_cache_fresh(string $file, int $ttl): bool {
    if (!is_file($file)) return false;
    $mtime = filemtime($file);
    if ((time() - $mtime) >= $ttl) return false;
    // Nach einem Deploy ist index.html neuer als die Cache-Datei -> verwerfen.
    $shell = __DIR__ . '/index.html';
    if (is_file($shell) && filemtime($shell) > $mtime) return false;
    return true;
}

function vg_cache_serve(int $ttl = 600, string $contentType = 'text/html; charset=utf-8'): void {
    if (!empty($_COOKIE[session_name()])) return;                 // eingeloggter Admin
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;   // nur GET
    header('Content-Type: ' . $contentType);
    $file = vg_cache_path();
    if (vg_cache_fresh($file, $ttl)) {
        header('Cache-Control: public, max-age=' . $ttl);
        header('X-VG-Cache: HIT');
        readfile($file);
        exit;
    }
    header('X-VG-Cache: MISS');
    $GLOBALS['__vg_cache_file'] = $file;
    $GLOBALS['__vg_cache_ttl']  = $ttl;
    ob_start();
    register_shutdown_function('vg_cache_save');
}

function vg_cache_save(): void {
    if (empty($GLOBALS['__vg_cache_file'])) return;
    if (http_response_code() !== 200) return;   // 404/500 nicht speichern
    $ttl = (int)($GLOBALS['__vg_cache_ttl'] ?? 600);
    // Headers werden erst mit dem ersten Ausgabe-Byte gesendet, die Ausgabe
    // liegt noch im Puffer, daher darf hier (im Shutdown) noch ein Header
    // gesetzt werden, bevor PHP den Puffer flusht.
    header('Cache-Control: public, max-age=' . $ttl);
    $html = ob_get_contents();
    if (!is_string($html) || $html === '') return;
    $dir = vg_cache_dir();
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($GLOBALS['__vg_cache_file'], $html, LOCK_EX);
}

function vg_cache_flush(): void {
    $dir = vg_cache_dir();
    if (!is_dir($dir)) return;
    foreach (glob($dir . '/*.html') ?: [] as $f) @unlink($f);
}
