<?php
/*
 * Kommentar-API fuer ViceGuide.
 *
 * GET    ?article=<id>              -> Liste aller Kommentare zu einem Artikel (als Baum)
 * POST   {article,name,text,parentId?,quote?} -> neuen Kommentar/Antwort anlegen
 * PATCH  {id,dir:"up"|"down"}        -> Stimme fuer einen Kommentar zaehlen
 * DELETE {id,password}               -> Kommentar loeschen, nur mit Admin-Passwort
 */

require __DIR__ . '/db.php';
require __DIR__ . '/mail.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

[$pdo, $cfg] = vg_db();
$method = $_SERVER['REQUEST_METHOD'];

/* Dauerhaft aus dem Admin-Benachrichtigungs-Popup ausgeblendete Kommentar-IDs.
   Liegt im gemeinsamen site_settings-Key-Value-Store (wie api/settings.php),
   damit "Ausblenden"/"Liste leeren" ueber Sessions und Geraete hinweg haelt und
   nicht nur im localStorage eines Browsers. */
function vg_notif_settings_table($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (skey VARCHAR(64) PRIMARY KEY, sval TEXT)");
}
function vg_notif_dismissed_get($pdo): array {
    vg_notif_settings_table($pdo);
    $st = $pdo->prepare("SELECT sval FROM site_settings WHERE skey = 'notif_dismissed'");
    $st->execute();
    $r = $st->fetch();
    $a = $r ? json_decode($r['sval'], true) : [];
    return is_array($a) ? array_map('intval', $a) : [];
}
function vg_notif_dismissed_add($pdo, array $ids): int {
    $cur = vg_notif_dismissed_get($pdo);
    $merged = array_values(array_unique(array_merge($cur, array_map('intval', $ids))));
    if (count($merged) > 3000) $merged = array_slice($merged, -3000);
    $v = json_encode($merged, JSON_UNESCAPED_UNICODE);
    $st = $pdo->prepare("SELECT skey FROM site_settings WHERE skey = 'notif_dismissed'");
    $st->execute();
    if ($st->fetch()) $pdo->prepare("UPDATE site_settings SET sval = ? WHERE skey = 'notif_dismissed'")->execute([$v]);
    else $pdo->prepare("INSERT INTO site_settings (skey, sval) VALUES ('notif_dismissed', ?)")->execute([$v]);
    return count($merged);
}

function vg_out($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function vg_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/* Serverseitiger Schimpfwort-Filter: ersetzt anstoessige Woerter durch den
   ersten Buchstaben plus Sternchen (z.B. "Scheisse" -> "S*******"). Der
   Kommentar wird trotzdem gepostet, nur zensiert. Serverseitig, damit es nicht
   ueber den Client umgangen werden kann. Wortgrenzen sind Unicode-bewusst,
   damit harmlose Woerter (z.B. "Grafik") nicht getroffen werden. */
function vg_censor(string $s): string {
    static $bad = ['fuck','fucking','motherfucker','shit','bitch','asshole','cunt','bastard',
        'arschloch','arsch','scheisse','scheiße','scheiss','scheiß','wichser','fotze','hurensohn',
        'hure','schlampe','fick','ficken','fickt','nutte','missgeburt','spasti','spast','vollidiot','wixer'];
    foreach ($bad as $w) {
        $s = preg_replace_callback('/(?<!\p{L})' . preg_quote($w, '/') . '(?!\p{L})/iu',
            function ($m) { $len = mb_strlen($m[0]); return mb_substr($m[0], 0, 1) . str_repeat('*', max(1, $len - 1)); },
            $s);
    }
    return $s;
}

function vg_buildTree(array $rows, string $voter = ''): array {
    $byId = [];
    foreach ($rows as $r) {
        $r['id'] = (int)$r['id'];
        $r['likes'] = (int)$r['likes'];
        $r['dislikes'] = (int)$r['dislikes'];
        $r['spoiler'] = !empty($r['spoiler']) ? 1 : 0;
        /* Schimpfwoerter erst beim Ausliefern zensieren. Standard-Anzeige ist
           zensiert, der Rohwert wird als *_full mitgeliefert, damit der Leser
           ihn per Klick aufdecken kann (wie ein Spoiler). */
        $anyCensored = false;
        $rawName = (string)$r['name']; $cName = vg_censor($rawName);
        $r['name'] = $cName; if ($cName !== $rawName) { $r['name_full'] = $rawName; $anyCensored = true; }
        $rawText = (string)$r['text']; $cText = vg_censor($rawText);
        $r['text'] = $cText; if ($cText !== $rawText) { $r['text_full'] = $rawText; $anyCensored = true; }
        if (!empty($r['quote'])) { $rawQ = (string)$r['quote']; $cQ = vg_censor($rawQ);
            $r['quote'] = $cQ; if ($cQ !== $rawQ) { $r['quote_full'] = $rawQ; $anyCensored = true; } }
        $r['censored'] = $anyCensored;
        /* Eigener Kommentar? Vergleich des mitgeschickten Wähler-Tokens mit dem
           beim Anlegen gespeicherten Autor-Token. Das Token selbst wird nie
           ausgeliefert (nur das abgeleitete Flag), damit keine fremden Tokens
           nach aussen sichtbar werden. */
        $r['own'] = ($voter !== '' && !empty($r['author_token']) && hash_equals((string)$r['author_token'], $voter));
        unset($r['author_token']);
        $r['replies'] = [];
        $byId[$r['id']] = $r;
    }
    $roots = [];
    foreach ($byId as $id => &$r) {
        $pid = $r['parent_id'] ? (int)$r['parent_id'] : null;
        if ($pid && isset($byId[$pid])) {
            $byId[$pid]['replies'][] = &$r;
        } else {
            $roots[] = &$r;
        }
    }
    unset($r);
    return $roots;
}

/* Admin-Benachrichtigung: baut und verschickt die Mail an den Betreiber bei
   einem neuen (fremden) Kommentar. Fehler beim Versand duerfen das Anlegen des
   Kommentars nie stoeren, daher komplett in try/catch. */
function vg_comment_notify(array $cfg, PDO $pdo, string $to, string $articleId, string $name, string $text, ?string $quote, ?int $parentId): void {
    try {
        $title = $articleId;
        try {
            $st = $pdo->prepare('SELECT title FROM articles WHERE id = ? LIMIT 1');
            $st->execute([$articleId]);
            $t = $st->fetchColumn();
            if ($t) $title = (string)$t;
        } catch (Throwable $e) { /* Titel nicht kritisch */ }

        $url = vg_site_url($cfg) . '/artikel/' . rawurlencode($articleId);
        $kind = $parentId ? 'Neue Antwort' : 'Neuer Kommentar';
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeText = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $HEAD = "font-family:'Oswald','Arial Narrow',Arial,sans-serif";
        $BODY = "font-family:'Inter',Arial,Helvetica,sans-serif";
        $q = $quote ? '<div class="m-soft" style="' . $BODY . ';font-size:14px;color:#6b6478;border-left:3px solid #ecdfca;padding-left:12px;margin:0 0 14px;line-height:1.5">' . htmlspecialchars($quote, ENT_QUOTES, 'UTF-8') . '</div>' : '';
        $inner = '<div class="m-h" style="' . $HEAD . ';font-size:22px;font-weight:700;color:#221041;line-height:1.2;margin:0 0 6px">' . $kind . '</div>'
               . '<div class="m-soft" style="' . $BODY . ';font-size:13px;color:#8a7fa5;margin:0 0 16px">zu &bdquo;' . $safeTitle . '&ldquo;</div>'
               . '<div class="m-tx" style="' . $BODY . ';font-size:15px;color:#221041;font-weight:700;margin:0 0 8px">' . $safeName . ' schreibt:</div>'
               . $q
               . '<div class="m-tx" style="' . $BODY . ';font-size:15px;color:#4a4458;line-height:1.6;margin:0 0 20px">' . $safeText . '</div>'
               . '<div><a class="m-btn" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#D00059;color:#fff;text-decoration:none;' . $BODY . ';font-size:14px;font-weight:700;padding:12px 22px;border-radius:10px">Zum Artikel und antworten</a></div>';
        $footer = '<div class="m-foot" style="' . $BODY . ';font-size:12px;color:#9a90ac;line-height:1.6">Automatische Benachrichtigung von ViceGuide.</div>';
        vg_send_mail($cfg, $to, $kind . ' auf ViceGuide: ' . $title, vg_mail_shell($inner, $footer, $cfg));
    } catch (Throwable $e) { /* Versand ist Beiwerk, nie den Request abbrechen */ }
}

/* Benachrichtigung an den Verfasser eines Kommentars, wenn jemand darauf
   antwortet. Nur wenn der Verfasser dem beim Schreiben zugestimmt und eine
   Mail hinterlegt hat (notify_replies + author_email). Enthaelt einen
   Abmeldelink (noreply-Token), mit dem er alle weiteren Antwort-Mails
   abstellen kann. Versandfehler duerfen das Anlegen nie stoeren. */
function vg_reply_notify(array $cfg, PDO $pdo, array $parent, string $articleId, string $replyName, string $replyText): void {
    try {
        $to = trim((string)($parent['author_email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return;

        $title = $articleId;
        try {
            $st = $pdo->prepare('SELECT title FROM articles WHERE id = ? LIMIT 1');
            $st->execute([$articleId]);
            $t = $st->fetchColumn();
            if ($t) $title = (string)$t;
        } catch (Throwable $e) { /* Titel nicht kritisch */ }

        $url = vg_site_url($cfg) . '/artikel/' . rawurlencode($articleId) . '#a-comments';
        $optOut = vg_site_url($cfg) . '/api/comments.php?noreply=' . rawurlencode((string)$parent['reply_token']);
        $safeName = htmlspecialchars($replyName, ENT_QUOTES, 'UTF-8');
        $safeText = nl2br(htmlspecialchars($replyText, ENT_QUOTES, 'UTF-8'));
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeYours = nl2br(htmlspecialchars(mb_substr((string)$parent['text'], 0, 200), ENT_QUOTES, 'UTF-8'));
        $HEAD = "font-family:'Oswald','Arial Narrow',Arial,sans-serif";
        $BODY = "font-family:'Inter',Arial,Helvetica,sans-serif";
        $inner = '<div class="m-h" style="' . $HEAD . ';font-size:22px;font-weight:700;color:#221041;line-height:1.2;margin:0 0 6px">Neue Antwort auf deinen Kommentar</div>'
               . '<div class="m-soft" style="' . $BODY . ';font-size:13px;color:#8a7fa5;margin:0 0 16px">zu &bdquo;' . $safeTitle . '&ldquo;</div>'
               . '<div class="m-soft" style="' . $BODY . ';font-size:14px;color:#6b6478;border-left:3px solid #ecdfca;padding-left:12px;margin:0 0 14px;line-height:1.5">Dein Kommentar: ' . $safeYours . '</div>'
               . '<div class="m-tx" style="' . $BODY . ';font-size:15px;color:#221041;font-weight:700;margin:0 0 8px">' . $safeName . ' antwortet:</div>'
               . '<div class="m-tx" style="' . $BODY . ';font-size:15px;color:#4a4458;line-height:1.6;margin:0 0 20px">' . $safeText . '</div>'
               . '<div><a class="m-btn" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#D00059;color:#fff;text-decoration:none;' . $BODY . ';font-size:14px;font-weight:700;padding:12px 22px;border-radius:10px">Zur Antwort und selbst antworten</a></div>';
        $footer = '<div class="m-foot" style="' . $BODY . ';font-size:12px;color:#9a90ac;line-height:1.6">Du bekommst diese Mail, weil du bei diesem Kommentar Antwort-Benachrichtigungen aktiviert hast. <a href="' . htmlspecialchars($optOut, ENT_QUOTES, 'UTF-8') . '" style="color:#9a90ac">Keine Antwort-Mails mehr</a>.</div>';
        vg_send_mail($cfg, $to, 'Neue Antwort auf deinen Kommentar bei ViceGuide', vg_mail_shell($inner, $footer, $cfg), ['List-Unsubscribe: <' . $optOut . '>']);
    } catch (Throwable $e) { /* Versand ist Beiwerk */ }
}

/* Opt-out aus der Antwort-Benachrichtigungs-Mail (Klick landet im Browser).
   Schaltet alle Antwort-Benachrichtigungen fuer die zugehoerige Mailadresse ab,
   nicht nur fuer diesen einen Kommentar, damit ein Klick wirklich Ruhe gibt. */
if ($method === 'GET' && isset($_GET['noreply'])) {
    header('Content-Type: text/html; charset=utf-8');
    $token = substr(trim((string)$_GET['noreply']), 0, 64);
    $done = false;
    if ($token !== '') {
        $st = $pdo->prepare('SELECT author_email FROM comments WHERE reply_token = ? LIMIT 1');
        $st->execute([$token]);
        $email = trim((string)$st->fetchColumn());
        if ($email !== '') {
            $pdo->prepare('UPDATE comments SET notify_replies = 0 WHERE author_email = ?')->execute([$email]);
            $done = true;
        }
    }
    $msg = $done
        ? 'Erledigt. Du bekommst keine Benachrichtigungen mehr, wenn jemand auf deine Kommentare antwortet.'
        : 'Dieser Link ist nicht mehr gültig. Vermutlich sind die Benachrichtigungen bereits aus.';
    $site = htmlspecialchars(vg_site_url($cfg), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex">'
       . '<title>Antwort-Benachrichtigungen &middot; ViceGuide</title>'
       . '<style>body{margin:0;background:#12101a;color:#f2f0f7;font-family:system-ui,Arial,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}'
       . '.card{max-width:440px;text-align:center;background:#1c1930;border:1px solid #2e2a45;border-radius:18px;padding:34px}'
       . '.br{font-weight:800;font-size:22px;margin-bottom:18px}.vc{color:#88B8C5}'
       . 'h1{font-size:21px;margin:0 0 12px}p{color:#c9c5d8;line-height:1.6;margin:0 0 20px}'
       . 'a.btn{display:inline-block;color:#12101a;background:#88B8C5;text-decoration:none;font-weight:700;padding:11px 22px;border-radius:11px}</style></head>'
       . '<body><div class="card"><div class="br">Vice<span class="vc">Guide</span></div>'
       . '<h1>Antwort-Benachrichtigungen</h1><p>' . $msg . '</p>'
       . '<a class="btn" href="' . $site . '/">Zu ViceGuide</a></div></body></html>';
    exit;
}

if ($method === 'GET' && isset($_GET['recent'])) {
    /* Fuer das Admin-Benachrichtigungs-Popup: die letzten Kommentare ueber alle
       Artikel, mit Artikeltitel und own-Flag (eigene Kommentare filtert der
       Client weg). Nur fuer eingeloggte Admins. */
    vg_require_admin($cfg);
    $voter = substr(trim((string)($_GET['voter'] ?? '')), 0, 100);
    $dismissed = array_flip(vg_notif_dismissed_get($pdo)); // dauerhaft ausgeblendete, serverseitig
    // Etwas mehr als 40 laden, weil ausgeblendete danach herausfallen.
    $stmt = $pdo->query('SELECT c.id, c.article_id, c.parent_id, c.name, c.text, c.quote, c.spoiler, c.author_token, c.created_at, a.title AS article_title
                         FROM comments c LEFT JOIN articles a ON a.id = c.article_id
                         ORDER BY c.created_at DESC, c.id DESC LIMIT 120');
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        if (isset($dismissed[(int)$r['id']])) continue; // dauerhaft ausgeblendet
        $own = ($voter !== '' && !empty($r['author_token']) && hash_equals((string)$r['author_token'], $voter));
        $out[] = [
            'id'            => (int)$r['id'],
            'article_id'    => $r['article_id'],
            'article_title' => $r['article_title'] ?: $r['article_id'],
            'parent_id'     => $r['parent_id'] ? (int)$r['parent_id'] : null,
            'name'          => $r['name'],
            'text'          => $r['text'],
            'quote'         => $r['quote'],
            'spoiler'       => (int)($r['spoiler'] ?? 0),
            'created_at'    => $r['created_at'],
            'own'           => $own,
        ];
        if (count($out) >= 40) break;
    }
    vg_out(['comments' => $out]);
}

if ($method === 'POST' && ($_GET['action'] ?? '') === 'notif_dismiss') {
    /* Admin blendet einen oder mehrere Kommentare dauerhaft aus dem Popup aus
       (der Kommentar selbst bleibt bestehen). */
    vg_require_admin($cfg);
    $b = vg_body();
    $ids = array_map('intval', (array)($b['ids'] ?? []));
    if (!$ids) vg_out(['error' => 'ids erforderlich'], 400);
    $n = vg_notif_dismissed_add($pdo, $ids);
    vg_out(['ok' => true, 'count' => $n]);
}

if ($method === 'GET') {
    $articleId = trim($_GET['article'] ?? '');
    if ($articleId === '') vg_out(['error' => 'article fehlt'], 400);
    $voter = substr(trim((string)($_GET['voter'] ?? '')), 0, 100);
    $stmt = $pdo->prepare('SELECT id, parent_id, name, text, quote, author_token, likes, dislikes, created_at, spoiler FROM comments WHERE article_id = ? ORDER BY created_at ASC');
    $stmt->execute([$articleId]);
    vg_out(['comments' => vg_buildTree($stmt->fetchAll(), $voter)]);
}

if ($method === 'POST') {
    $b = vg_body();
    $articleId = trim($b['article'] ?? '');
    /* Rohtext speichern. Die Schimpfwoerter-Zensur passiert erst beim Ausliefern
       (vg_buildTree), damit jeder Leser sie wie einen Spoiler bewusst aufdecken
       kann. Der Server bleibt die maessgebliche Grenze: Standard ist zensiert. */
    $name = mb_substr(trim($b['name'] ?? '') ?: 'Gast', 0, 60);
    $text = mb_substr(trim($b['text'] ?? ''), 0, 800);
    $quote = isset($b['quote']) ? mb_substr(trim((string)$b['quote']), 0, 200) : null;
    $parentId = isset($b['parentId']) && $b['parentId'] ? (int)$b['parentId'] : null;
    $spoiler = !empty($b['spoiler']) ? 1 : 0;

    if ($articleId === '' || $text === '') vg_out(['error' => 'article und text sind erforderlich'], 400);

    /* Anonymes Wähler-Token als Autor-Kennung merken, damit man den eigenen
       Kommentar spaeter nicht selbst bewerten kann (siehe PATCH und GET own). */
    $author = substr(trim((string)($b['voter'] ?? '')), 0, 100) ?: null;

    /* Optionale Antwort-Benachrichtigung: nur speichern, wenn der Verfasser
       zugestimmt (notifyReplies) UND eine gueltige Mail hinterlegt hat. Sonst
       wird bewusst keine Mailadresse gespeichert (Datensparsamkeit). */
    $wantNotify = !empty($b['notifyReplies']);
    $email = strtolower(trim((string)($b['email'] ?? '')));
    $authorEmail = null; $notifyReplies = 0; $replyToken = null;
    if ($wantNotify && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email) <= 190) {
        $authorEmail = $email;
        $notifyReplies = 1;
        $replyToken = bin2hex(random_bytes(20));
    }

    $stmt = $pdo->prepare('INSERT INTO comments (article_id, parent_id, name, text, quote, author_token, author_email, notify_replies, reply_token, spoiler) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$articleId, $parentId, $name, $text, $quote ?: null, $author, $authorEmail, $notifyReplies, $replyToken, $spoiler]);
    $newId = (int)$pdo->lastInsertId();

    /* Benachrichtigung an den Betreiber, aber nicht fuer eigene Kommentare:
       wer eingeloggt ist (Admin), bekommt keine Mail ueber den eigenen Beitrag.
       notify_email leer lassen schaltet die Benachrichtigung komplett ab. */
    $notify = trim((string)($cfg['notify_email'] ?? ''));
    if ($notify !== '' && !vg_is_admin()) {
        vg_comment_notify($cfg, $pdo, $notify, $articleId, $name, $text, $quote ?: null, $parentId);
    }

    /* Antwort-Benachrichtigung an den Verfasser des Eltern-Kommentars, wenn er
       sie aktiviert hat. Nicht an sich selbst schicken (gleiches Autor-Token). */
    if ($parentId) {
        try {
            $ps = $pdo->prepare('SELECT text, author_token, author_email, notify_replies, reply_token FROM comments WHERE id = ? LIMIT 1');
            $ps->execute([$parentId]);
            $parent = $ps->fetch();
            if ($parent && (int)$parent['notify_replies'] === 1 && !empty($parent['author_email'])) {
                $sameAuthor = $author !== null && !empty($parent['author_token']) && hash_equals((string)$parent['author_token'], (string)$author);
                if (!$sameAuthor) {
                    vg_reply_notify($cfg, $pdo, $parent, $articleId, $name, $text);
                }
            }
        } catch (Throwable $e) { /* Versand ist Beiwerk */ }
    }
    vg_out(['ok' => true, 'id' => $newId], 201);
}

if ($method === 'PATCH') {
    $b = vg_body();
    /* Admin schaltet einen Kommentar als Spoiler an/aus (nur eingeloggt). */
    if (array_key_exists('spoiler', $b)) {
        vg_require_admin($cfg);
        $id = (int)($b['id'] ?? 0);
        if (!$id) vg_out(['error' => 'id erforderlich'], 400);
        $sp = !empty($b['spoiler']) ? 1 : 0;
        $pdo->prepare('UPDATE comments SET spoiler = ? WHERE id = ?')->execute([$sp, $id]);
        vg_out(['ok' => true, 'spoiler' => $sp]);
    }
    $id = (int)($b['id'] ?? 0);
    $dir = $b['dir'] ?? '';
    if (!$id || !in_array($dir, ['up', 'down'], true)) vg_out(['error' => 'id und dir (up/down) erforderlich'], 400);
    $col = $dir === 'up' ? 'likes' : 'dislikes';

    /* Eine Stimme pro Wähler pro Kommentar, serverseitig erzwungen. "Wähler"
       ist ein anonymes, dauerhaftes Browser-Token (localStorage vg-voter, vom
       Client mitgeschickt). Fehlt es (z.B. direkter API-Aufruf), fällt der
       Wähler auf einen IP-Hash zurück, damit auch dann nicht beliebig oft
       abgestimmt werden kann. Der reine localStorage-Check im Client war
       trivial umgehbar (Speicher leeren, anderer Browser, curl). */
    $voter = trim((string)($b['voter'] ?? ''));
    if ($voter === '') {
        $voter = 'ip:' . substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($cfg['admin_hash'] ?? '')), 0, 40);
    }
    $voter = substr($voter, 0, 100);

    /* Eigenen Kommentar nicht selbst bewerten. Autor-Token beim Anlegen
       gespeichert (POST), hier gegen den abstimmenden Wähler geprüft. */
    $own = $pdo->prepare('SELECT likes, dislikes, author_token FROM comments WHERE id = ?');
    $own->execute([$id]);
    $ownRow = $own->fetch();
    if (!$ownRow) vg_out(['error' => 'Kommentar nicht gefunden'], 404);
    if (!empty($ownRow['author_token']) && hash_equals((string)$ownRow['author_token'], $voter)) {
        vg_out(['ok' => true, 'own' => true, 'likes' => (int)$ownRow['likes'], 'dislikes' => (int)$ownRow['dislikes']]);
    }

    $chk = $pdo->prepare('SELECT 1 FROM comment_votes WHERE comment_id = ? AND voter = ? LIMIT 1');
    $chk->execute([$id, $voter]);
    $already = (bool)$chk->fetchColumn();

    if (!$already) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO comment_votes (comment_id, voter, dir) VALUES (?,?,?)')
                ->execute([$id, $voter, $dir]);
            $pdo->prepare("UPDATE comments SET $col = $col + 1 WHERE id = ?")->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            /* Unique-Verletzung durch parallele Klicks (Race): als "schon
               abgestimmt" behandeln, nicht doppelt zählen. */
            if ($pdo->inTransaction()) $pdo->rollBack();
            $already = true;
        }
    }

    $cur = $pdo->prepare('SELECT likes, dislikes FROM comments WHERE id = ?');
    $cur->execute([$id]);
    $row = $cur->fetch();
    if (!$row) vg_out(['error' => 'Kommentar nicht gefunden'], 404);
    vg_out(['ok' => true, 'already' => $already, 'likes' => (int)$row['likes'], 'dislikes' => (int)$row['dislikes']]);
}

if ($method === 'DELETE') {
    vg_require_admin($cfg);
    $b = vg_body();
    $id = (int)($b['id'] ?? 0);
    if (!$id) vg_out(['error' => 'id erforderlich'], 400);
    $stmt = $pdo->prepare('DELETE FROM comments WHERE id = ?');
    $stmt->execute([$id]);
    vg_out(['ok' => true]);
}

vg_out(['error' => 'Methode nicht unterstuetzt'], 405);
