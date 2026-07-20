<?php
/*
 * Creator-API fuer ViceGuide (Partner-Creator, siehe /creator).
 *
 * GET                   -> alle Creator (Besucher: nur aktive; Admin: alle inkl.
 *                          Entwurfsstand). Jeder Creator bekommt seine Favoriten
 *                          gegen db_entries aufgeloest (Name, Bild, Slug).
 * POST {name,...}        -> neuen Creator anlegen (Admin, sofort live)
 * PUT  {id|slug,...}     -> Aenderung als Entwurf in draft_json (Admin)
 * POST ?action=publish   -> alle offenen Entwuerfe veroeffentlichen (Admin)
 * POST ?action=discard   -> alle offenen Entwuerfe verwerfen (Admin)
 * DELETE {id}            -> Creator loeschen (Admin, Favoriten per Cascade)
 *
 * Datenmodell und Entwurf/Veroeffentlichen bewusst analog zu db_entries.php.
 * Favoriten (Tabelle creator_favorites) werden als Teil des Creator-Objekts
 * mitgefuehrt: sowohl im Entwurf (draft_json.favorites) als auch live.
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

[$pdo, $cfg] = vg_db();
$method = $_SERVER['REQUEST_METHOD'];

require __DIR__ . '/../cache.php';
if ($method === 'POST' || $method === 'DELETE') {
    register_shutdown_function(function () {
        $c = http_response_code();
        if ($c >= 200 && $c < 300) vg_cache_flush();
    });
}

function vg_c_out($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function vg_c_body(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

/* Eindeutigen Slug fuer einen Creator erzeugen (aus name oder gewuenschtem
   slug), $excludeId schliesst den eigenen Datensatz beim Update aus. */
function vg_creator_slug(PDO $pdo, string $base, int $excludeId = 0): string {
    $base = vg_entry_slugify($base) ?: 'creator';
    $slug = $base; $n = 2;
    $q = $pdo->prepare('SELECT id FROM creators WHERE slug = ? AND id <> ? LIMIT 1');
    while (true) {
        $q->execute([$slug, $excludeId]);
        if (!$q->fetch()) return $slug;
        $slug = $base . '-' . $n; $n++;
    }
}

/* Loest eine Favoriten-Liste ([{section,slug,label?,quote?}]) gegen die
   db_entries auf, damit die Creator-Seite Name, Unterzeile und Bild jedes
   Lieblings-Eintrags zeigen kann, ohne dass der Client selbst joinen muss. */
function vg_resolve_favorites(PDO $pdo, array $favs): array {
    $out = [];
    $q = $pdo->prepare('SELECT id, name, sub, slug, img, updated_at FROM db_entries WHERE section = ? AND slug = ? LIMIT 1');
    foreach ($favs as $f) {
        if (!is_array($f)) continue;
        $section = trim((string)($f['section'] ?? ''));
        $slug    = trim((string)($f['slug'] ?? ''));
        if ($section === '' || $slug === '') continue;
        $q->execute([$section, $slug]);
        $e = $q->fetch();
        $item = [
            'section' => $section,
            'slug'    => $slug,
            'label'   => isset($f['label']) ? (string)$f['label'] : null,
            'quote'   => isset($f['quote']) ? (string)$f['quote'] : null,
            'name'    => $e ? $e['name'] : null,
            'sub'     => $e && $e['sub'] ? $e['sub'] : null,
            'img'     => ($e && !empty($e['img'])) ? ('api/entry_image.php?id=' . (int)$e['id'] . '&v=' . urlencode((string)($e['updated_at'] ?? ''))) : null,
            'exists'  => (bool)$e,
        ];
        $out[] = $item;
    }
    return $out;
}

/* Liest die live gespeicherten Favoriten eines Creators als einfache Liste
   [{section,slug,label,quote}] (noch nicht aufgeloest). */
function vg_creator_fav_rows(PDO $pdo, int $creatorId): array {
    $q = $pdo->prepare('SELECT section, entry_slug, label, quote FROM creator_favorites WHERE creator_id = ? ORDER BY sort_order, id');
    $q->execute([$creatorId]);
    $out = [];
    foreach ($q->fetchAll() as $r) {
        $out[] = ['section' => $r['section'], 'slug' => $r['entry_slug'], 'label' => $r['label'], 'quote' => $r['quote']];
    }
    return $out;
}

/* Schreibt die Favoriten-Liste eines Creators neu (ersetzt alle bestehenden). */
function vg_write_creator_favs(PDO $pdo, int $creatorId, array $favs): void {
    $pdo->prepare('DELETE FROM creator_favorites WHERE creator_id = ?')->execute([$creatorId]);
    $ins = $pdo->prepare('INSERT INTO creator_favorites (creator_id, section, entry_slug, label, quote, sort_order) VALUES (?,?,?,?,?,?)');
    $i = 0;
    foreach ($favs as $f) {
        if (!is_array($f)) continue;
        $section = trim((string)($f['section'] ?? ''));
        $slug    = trim((string)($f['slug'] ?? ''));
        if ($section === '' || $slug === '') continue;
        $ins->execute([$creatorId, $section, $slug, $f['label'] ?? null, $f['quote'] ?? null, $i]);
        $i++;
    }
}

/* Wandelt eine creators-Zeile ins Client-/Ausgabe-Format. $full liefert den
   rohen Avatar-Base64 (Backup/Bearbeiten), sonst die schlanke Bild-URL. */
function vg_creatorRow(PDO $pdo, array $r, bool $full = false): array {
    $hasAvatar = !empty($r['avatar']);
    $out = [
        'id'       => (int)$r['id'],
        'slug'     => $r['slug'],
        'name'     => $r['name'],
        'tagline'  => $r['tagline'] ?: null,
        'bio'      => $r['bio'] ?: null,
        'platforms'=> $r['platforms_json'] ? (json_decode($r['platforms_json'], true) ?: []) : [],
        'videos'   => $r['videos_json'] ? (json_decode($r['videos_json'], true) ?: []) : [],
        'avatar'   => $hasAvatar ? ($full ? $r['avatar'] : ('api/creator_image.php?id=' . (int)$r['id'] . '&v=' . urlencode((string)($r['updated_at'] ?? '')))) : null,
        'twitch'   => $r['twitch_login'] ?: null,
        'accent'   => $r['accent'] ?: null,
        'active'   => !empty($r['active']),
        'seo'      => !empty($r['seo_index']),
        'favorites'=> vg_resolve_favorites($pdo, vg_creator_fav_rows($pdo, (int)$r['id'])),
    ];
    if ($r['avatarfit_json']) $out['avatarfit'] = json_decode($r['avatarfit_json'], true);
    return $out;
}

/* Schreibt Creator-Scalarfelder (und optional Favoriten) in die echten Spalten.
   Genutzt beim Anlegen (POST) und beim Veroeffentlichen eines Entwurfs. */
function vg_writeCreatorFields(PDO $pdo, int $id, array $d, bool $clearDraft): void {
    $map = [
        'name' => 'name', 'tagline' => 'tagline', 'bio' => 'bio', 'twitch' => 'twitch_login', 'accent' => 'accent',
    ];
    $sets = []; $vals = [];
    foreach ($map as $jsonKey => $col) {
        if (array_key_exists($jsonKey, $d)) { $sets[] = "$col = ?"; $vals[] = $d[$jsonKey]; }
    }
    if (array_key_exists('platforms', $d)) { $sets[] = 'platforms_json = ?'; $vals[] = $d['platforms'] ? json_encode($d['platforms'], JSON_UNESCAPED_UNICODE) : null; }
    if (array_key_exists('videos', $d))    { $sets[] = 'videos_json = ?';    $vals[] = $d['videos'] ? json_encode($d['videos'], JSON_UNESCAPED_UNICODE) : null; }
    if (array_key_exists('avatar', $d))    { $sets[] = 'avatar = ?';         $vals[] = $d['avatar']; }
    if (array_key_exists('avatarfit', $d)) { $sets[] = 'avatarfit_json = ?'; $vals[] = $d['avatarfit'] ? json_encode($d['avatarfit'], JSON_UNESCAPED_UNICODE) : null; }
    if (array_key_exists('active', $d))    { $sets[] = 'active = ?';         $vals[] = !empty($d['active']) ? 1 : 0; }
    if (array_key_exists('seo', $d))       { $sets[] = 'seo_index = ?';      $vals[] = !empty($d['seo']) ? 1 : 0; }
    if ($sets) {
        $sets[] = 'updated_at = CURRENT_TIMESTAMP';
        if ($clearDraft) $sets[] = 'draft_json = NULL';
        $vals[] = $id;
        $pdo->prepare('UPDATE creators SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    } elseif ($clearDraft) {
        $pdo->prepare('UPDATE creators SET draft_json = NULL WHERE id = ?')->execute([$id]);
    }
    if (array_key_exists('favorites', $d) && is_array($d['favorites'])) {
        vg_write_creator_favs($pdo, $id, $d['favorites']);
    }
}

if ($method === 'GET') {
    $full = !empty($_GET['full']);
    if ($full) vg_require_admin($cfg);
    $admin = vg_is_admin();
    if (!$admin) {
        header('Cache-Control: public, max-age=120, stale-while-revalidate=600');
        header('Vary: Cookie');
    }
    $rows = $pdo->query('SELECT * FROM creators ORDER BY sort_order, id')->fetchAll();
    $list = [];
    foreach ($rows as $r) {
        if (!$admin && empty($r['active'])) continue; // Besucher sehen nur aktive
        $c = vg_creatorRow($pdo, $r, $full);
        if ($admin && !empty($r['draft_json'])) {
            $draft = json_decode($r['draft_json'], true);
            if (is_array($draft)) {
                // Favoriten im Entwurf ggf. aufloesen, sonst Scalars ueberlagern.
                if (array_key_exists('favorites', $draft) && is_array($draft['favorites'])) {
                    $c['favorites'] = vg_resolve_favorites($pdo, $draft['favorites']);
                    unset($draft['favorites']);
                }
                $c = array_merge($c, $draft);
                $c['_draft'] = true;
            }
        }
        $list[] = $c;
    }
    vg_c_out(['creators' => $list]);
}

if ($method === 'POST' && ($_GET['action'] ?? '') === 'publish') {
    vg_require_admin($cfg);
    $rows = $pdo->query("SELECT id, draft_json FROM creators WHERE draft_json IS NOT NULL AND draft_json <> ''")->fetchAll();
    foreach ($rows as $r) {
        $d = json_decode($r['draft_json'], true);
        if (is_array($d)) vg_writeCreatorFields($pdo, (int)$r['id'], $d, true);
    }
    vg_c_out(['ok' => true, 'published' => count($rows)]);
}

if ($method === 'POST' && ($_GET['action'] ?? '') === 'discard') {
    vg_require_admin($cfg);
    $n = $pdo->exec("UPDATE creators SET draft_json = NULL WHERE draft_json IS NOT NULL AND draft_json <> ''");
    vg_c_out(['ok' => true, 'discarded' => $n]);
}

if ($method === 'POST') {
    vg_require_admin($cfg);
    $b = vg_c_body();
    $name = trim((string)($b['name'] ?? ''));
    if ($name === '') vg_c_out(['error' => 'name erforderlich'], 400);

    $slug = vg_creator_slug($pdo, trim((string)($b['slug'] ?? '')) ?: $name);
    $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) m FROM creators')->fetch()['m'] + 1;

    $stmt = $pdo->prepare('INSERT INTO creators (slug, name, tagline, bio, platforms_json, videos_json, avatar, avatarfit_json, twitch_login, accent, active, seo_index, sort_order)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $slug,
        $name,
        $b['tagline'] ?? null,
        $b['bio'] ?? null,
        isset($b['platforms']) ? json_encode($b['platforms'], JSON_UNESCAPED_UNICODE) : null,
        isset($b['videos']) ? json_encode($b['videos'], JSON_UNESCAPED_UNICODE) : null,
        $b['avatar'] ?? null,
        isset($b['avatarfit']) ? json_encode($b['avatarfit'], JSON_UNESCAPED_UNICODE) : null,
        $b['twitch'] ?? null,
        $b['accent'] ?? null,
        array_key_exists('active', $b) ? (!empty($b['active']) ? 1 : 0) : 1,
        array_key_exists('seo', $b) ? (!empty($b['seo']) ? 1 : 0) : 1,
        $maxOrder,
    ]);
    $id = (int)$pdo->lastInsertId();
    if (isset($b['favorites']) && is_array($b['favorites'])) vg_write_creator_favs($pdo, $id, $b['favorites']);
    vg_c_out(['ok' => true, 'id' => $id, 'slug' => $slug], 201);
}

if ($method === 'PUT') {
    vg_require_admin($cfg);
    $b = vg_c_body();
    $id = (int)($b['id'] ?? 0);
    if (!$id && !empty($b['slug'])) {
        $q = $pdo->prepare('SELECT id FROM creators WHERE slug = ? LIMIT 1');
        $q->execute([$b['slug']]);
        $row = $q->fetch();
        if ($row) $id = (int)$row['id'];
    }
    if (!$id) vg_c_out(['error' => 'id oder slug erforderlich'], 400);

    $check = $pdo->prepare('SELECT draft_json FROM creators WHERE id = ?');
    $check->execute([$id]);
    $row = $check->fetch();
    if (!$row) vg_c_out(['error' => 'Creator nicht gefunden'], 404);

    $draft = $row['draft_json'] ? (json_decode($row['draft_json'], true) ?: []) : [];
    $allowed = ['name','tagline','bio','platforms','videos','avatar','avatarfit','twitch','accent','active','seo','favorites'];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $b)) { $draft[$key] = $b[$key]; }
    }
    if (!$draft) vg_c_out(['error' => 'keine Felder zum Aktualisieren']);

    $pdo->prepare('UPDATE creators SET draft_json = ? WHERE id = ?')
        ->execute([json_encode($draft, JSON_UNESCAPED_UNICODE), $id]);
    vg_c_out(['ok' => true, 'draft' => true]);
}

if ($method === 'DELETE') {
    vg_require_admin($cfg);
    $b = vg_c_body();
    $id = (int)($b['id'] ?? 0);
    if (!$id) vg_c_out(['error' => 'id erforderlich'], 400);
    // Favoriten explizit mitloeschen (SQLite ohne aktivierte FK-Cascade absichern).
    $pdo->prepare('DELETE FROM creator_favorites WHERE creator_id = ?')->execute([$id]);
    $stmt = $pdo->prepare('DELETE FROM creators WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) vg_c_out(['error' => 'Creator nicht gefunden'], 404);
    vg_c_out(['ok' => true]);
}

vg_c_out(['error' => 'Methode nicht unterstuetzt'], 405);
