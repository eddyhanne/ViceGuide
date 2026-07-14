<?php
/*
 * Newsletter-API fuer ViceGuide (selbst gehostet, Double-Opt-In).
 *
 * POST   {email}                 -> Anmeldung, verschickt Bestaetigungsmail (Double-Opt-In)
 * GET    ?confirm=<token>        -> Anmeldung bestaetigen (Klick aus der Mail), HTML-Seite
 * GET    ?unsubscribe=<token>    -> Abmelden (Klick aus der Mail), HTML-Seite
 * GET                            -> Abonnentenliste + Zahlen (nur Admin), JSON
 * POST   ?action=send {subject,body} -> Newsletter an bestaetigte Abonnenten (nur Admin)
 *
 * DSGVO: Es wird nur mit ausdruecklicher, per Double-Opt-In bestaetigter
 * Einwilligung versendet (Art. 6 Abs. 1 lit. a). Jede Newsletter-Mail traegt
 * einen Abmeldelink. Zur Einwilligungsdokumentation wird ein gekuerzter,
 * nicht umkehrbarer IP-Hash und der Zeitpunkt gespeichert, nicht die IP selbst.
 */

require __DIR__ . '/db.php';
require __DIR__ . '/mail.php';

[$pdo, $cfg] = vg_db();
$method = $_SERVER['REQUEST_METHOD'];

function nl_json($data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Kleine, nicht indexierbare HTML-Bestaetigungsseite fuer die Klick-Links aus
   der Mail (Bestaetigen/Abmelden landen im Browser, nicht in einem API-Client). */
function nl_page(string $title, string $msg, array $cfg): never {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $site = vg_site_url($cfg);
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex">'
       . '<title>' . $t . ' &middot; ViceGuide</title>'
       . '<style>body{margin:0;background:#12101a;color:#f2f0f7;font-family:system-ui,Arial,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}'
       . '.card{max-width:440px;text-align:center;background:#1c1930;border:1px solid #2e2a45;border-radius:18px;padding:34px}'
       . '.br{font-weight:800;font-size:22px;margin-bottom:18px}.vc{color:#88B8C5}'
       . 'h1{font-size:21px;margin:0 0 12px}p{color:#c9c5d8;line-height:1.6;margin:0 0 20px}'
       . 'a.btn{display:inline-block;color:#12101a;background:#88B8C5;text-decoration:none;font-weight:700;padding:11px 22px;border-radius:11px}</style></head>'
       . '<body><div class="card"><div class="br">Vice<span class="vc">Guide</span></div>'
       . '<h1>' . $t . '</h1><p>' . $msg . '</p>'
       . '<a class="btn" href="' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '/">Zu ViceGuide</a></div></body></html>';
    exit;
}

function nl_token(): string {
    return bin2hex(random_bytes(20));
}

/* Absender-Identitaet fuer alle Newsletter-Mails: ausschliesslich
   newsletter@viceguide.de (eigenes Postfach mit eigenen SMTP-Zugangsdaten).
   Faellt nur zurueck, wenn die newsletter_*-Schluessel in config.php fehlen. */
function vg_newsletter_opts(array $cfg): array {
    $from = trim((string)($cfg['newsletter_from'] ?? '')) ?: 'ViceGuide <newsletter@viceguide.de>';
    $opts = ['from' => $from];
    $user = trim((string)($cfg['newsletter_smtp_user'] ?? ''));
    if ($user !== '') {
        $opts['smtp_user'] = $user;
        $opts['smtp_pass'] = (string)($cfg['newsletter_smtp_pass'] ?? '');
    }
    return $opts;
}

/* Gemeinsame Mail-Huelle fuer alle Newsletter-Mails (Versand und
   Bestaetigung): heller Hintergrund wie die Website, Kopf-Bild (freigestelltes
   Logo auf Palmen-Hintergrund, gerendert aus dem Homepage-Hero), Website-
   Schriften per @font-face. $inner ist der Inhalt, $footer der Fusszeilen-HTML
   (beim Newsletter der Abmeldelink, bei der Bestaetigung nur der Fan-Hinweis). */
function vg_mail_shell(string $inner, string $footer, array $cfg): string {
    $base = vg_site_url($cfg);
    $ff = "@font-face{font-family:'Oswald';font-weight:200 700;font-display:swap;src:url('$base/assets/fonts/oswald-variable.woff2') format('woff2')}"
        . "@font-face{font-family:'Inter';font-weight:100 900;font-display:swap;src:url('$base/assets/fonts/inter-variable.woff2') format('woff2')}";
    // color-scheme + bgcolor-Attribute halten den hellen Hintergrund auch in
    // Mail-Clients mit Dark-Mode (Outlook faerbt reine style-Backgrounds sonst
    // dunkel um). Kein Garant bei jedem Client, aber der beste verfuegbare Weg.
    return '<!doctype html><html lang="de"><head><meta charset="utf-8">'
         . '<meta name="viewport" content="width=device-width,initial-scale=1">'
         . '<meta name="color-scheme" content="light">'
         . '<meta name="supported-color-schemes" content="light">'
         . '<style>:root{color-scheme:light}' . $ff . '</style></head>'
         . '<body style="margin:0;padding:0;background:#FBF3E7">'
         . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#FBF3E7" style="background:#FBF3E7"><tr><td align="center" bgcolor="#FBF3E7" style="padding:24px 14px">'
         . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px">'
         . '<tr><td style="padding:0"><a href="' . $base . '/"><img src="' . $base . '/newsletter-header.jpg" width="600" alt="ViceGuide" style="width:100%;display:block;border-radius:18px 18px 0 0"></a></td></tr>'
         . '<tr><td bgcolor="#FFF9EF" style="padding:24px 26px 28px;background:#FFF9EF;border:1px solid #ecdfca;border-top:none;border-radius:0 0 18px 18px">'
         . $inner
         . '</td></tr>'
         . '<tr><td bgcolor="#FBF3E7" style="padding:16px 26px 6px">' . $footer . '</td></tr>'
         . '</table></td></tr></table></body></html>';
}

/* Newsletter-Versand: Huelle mit Abmeldelink (pro Empfaenger eigen). */
function vg_newsletter_wrap(string $inner, string $unsubUrl, array $cfg): string {
    $BODY = "font-family:'Inter',Arial,Helvetica,sans-serif";
    $unsub = htmlspecialchars($unsubUrl, ENT_QUOTES, 'UTF-8');
    $footer = '<div style="' . $BODY . ';font-size:12px;color:#9a90ac;line-height:1.6">Du bekommst diese Mail, weil du dich beim ViceGuide-Newsletter angemeldet hast. <a href="' . $unsub . '" style="color:#9a90ac">Hier abmelden</a>.</div>'
            . '<div style="' . $BODY . ';font-size:12px;color:#9a90ac;line-height:1.6;margin-top:8px">ViceGuide ist ein inoffizielles Fan-Portal und steht in keiner Verbindung zu Rockstar Games oder Take-Two Interactive.</div>';
    return vg_mail_shell($inner, $footer, $cfg);
}

/* ---- Anmeldung bestaetigen (Double-Opt-In) ---- */
if ($method === 'GET' && isset($_GET['confirm'])) {
    $token = substr(trim((string)$_GET['confirm']), 0, 64);
    if ($token === '') nl_page('Link unvollständig', 'Dieser Bestätigungslink ist unvollständig.', $cfg);
    $st = $pdo->prepare('SELECT id, status FROM newsletter_subscribers WHERE token = ? LIMIT 1');
    $st->execute([$token]);
    $row = $st->fetch();
    if (!$row) nl_page('Link ungültig', 'Dieser Bestätigungslink ist nicht mehr gültig. Melde dich bei Bedarf einfach erneut an.', $cfg);
    if ($row['status'] !== 'confirmed') {
        $pdo->prepare("UPDATE newsletter_subscribers SET status='confirmed', confirmed_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$row['id']]);
    }
    nl_page('Anmeldung bestätigt', 'Danke, deine Anmeldung zum ViceGuide-Newsletter ist bestätigt. Du bekommst ab jetzt die wichtigen GTA-6-News.', $cfg);
}

/* ---- Abmelden ---- */
if ($method === 'GET' && isset($_GET['unsubscribe'])) {
    $token = substr(trim((string)$_GET['unsubscribe']), 0, 64);
    if ($token === '') nl_page('Link unvollständig', 'Dieser Abmeldelink ist unvollständig.', $cfg);
    $st = $pdo->prepare('SELECT id FROM newsletter_subscribers WHERE token = ? LIMIT 1');
    $st->execute([$token]);
    $row = $st->fetch();
    if ($row) {
        $pdo->prepare("UPDATE newsletter_subscribers SET status='unsubscribed' WHERE id=?")->execute([$row['id']]);
    }
    nl_page('Abgemeldet', 'Du bekommst ab jetzt keinen ViceGuide-Newsletter mehr. Schade, dass du gehst, du kannst dich jederzeit wieder anmelden.', $cfg);
}

/* ---- Admin: Abonnentenliste und Zahlen ---- */
if ($method === 'GET') {
    vg_require_admin($cfg);
    $counts = ['pending' => 0, 'confirmed' => 0, 'unsubscribed' => 0];
    foreach ($pdo->query("SELECT status, COUNT(*) AS c FROM newsletter_subscribers GROUP BY status") as $r) {
        $counts[$r['status']] = (int)$r['c'];
    }
    $list = $pdo->query("SELECT email, status, created_at, confirmed_at FROM newsletter_subscribers ORDER BY created_at DESC LIMIT 500")->fetchAll();
    nl_json(['counts' => $counts, 'subscribers' => $list]);
}

/* ---- Admin: Newsletter versenden ---- */
if ($method === 'POST' && ($_GET['action'] ?? '') === 'send') {
    vg_require_admin($cfg);
    $b = json_decode(file_get_contents('php://input'), true) ?: [];
    $subject = trim((string)($b['subject'] ?? ''));
    $body = trim((string)($b['body'] ?? ''));
    if ($subject === '' || $body === '') nl_json(['error' => 'Betreff und Inhalt sind erforderlich.'], 400);
    // Reine Textzeilen sollen als Absaetze ankommen, eingefuegtes HTML bleibt erhalten.
    $bodyHtml = (strip_tags($body) === $body) ? nl2br($body) : $body;
    $rows = $pdo->query("SELECT email, token FROM newsletter_subscribers WHERE status='confirmed'")->fetchAll();
    $nlOpts = vg_newsletter_opts($cfg);
    $sent = 0; $failed = 0;
    foreach ($rows as $r) {
        $unsub = vg_site_url($cfg) . '/api/newsletter.php?unsubscribe=' . rawurlencode($r['token']);
        $html = vg_newsletter_wrap($bodyHtml, $unsub, $cfg);
        $ok = vg_send_mail($cfg, $r['email'], $subject, $html, ['List-Unsubscribe: <' . $unsub . '>'], $nlOpts);
        if ($ok) $sent++; else $failed++;
    }
    nl_json(['ok' => true, 'sent' => $sent, 'failed' => $failed]);
}

/* ---- Besucher: Anmeldung ---- */
if ($method === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = strtolower(trim((string)($b['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        nl_json(['error' => 'Bitte eine gültige E-Mail-Adresse angeben.'], 400);
    }
    // Neutrale Antwort, egal ob die Adresse neu ist oder schon existiert, damit
    // ueber die API niemand pruefen kann, wer angemeldet ist (E-Mail-Enumeration).
    $neutral = ['ok' => true, 'message' => 'Fast geschafft. Bitte bestätige den Link in der E-Mail, die wir dir gerade geschickt haben.'];

    $ipHash = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($cfg['admin_hash'] ?? '')), 0, 40);
    $token = nl_token();

    $st = $pdo->prepare('SELECT id, status FROM newsletter_subscribers WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $row = $st->fetch();
    if ($row) {
        if ($row['status'] === 'confirmed') {
            // Schon bestaetigt: keine zweite Mail, aber neutral antworten.
            nl_json($neutral);
        }
        // pending oder abgemeldet: neuen Token, wieder auf pending, Mail erneut.
        $pdo->prepare("UPDATE newsletter_subscribers SET status='pending', token=?, consent_ip=?, created_at=CURRENT_TIMESTAMP, confirmed_at=NULL WHERE id=?")
            ->execute([$token, $ipHash, $row['id']]);
    } else {
        $pdo->prepare("INSERT INTO newsletter_subscribers (email, status, token, consent_ip) VALUES (?, 'pending', ?, ?)")
            ->execute([$email, $token, $ipHash]);
    }

    $base = vg_site_url($cfg);
    $confirmUrl = $base . '/api/newsletter.php?confirm=' . rawurlencode($token);
    $discordUrl = 'https://discord.gg/MNmMG3xNfA';   // gleicher Invite wie in der Community-Sektion (index.html)
    $HEAD = "font-family:'Oswald','Arial Narrow',Arial,sans-serif";
    $BODY = "font-family:'Inter',Arial,Helvetica,sans-serif";
    $vgLink = '<a href="' . $base . '/" style="color:#D00059;font-weight:700;text-decoration:none">ViceGuide</a>';
    $inner = '<div style="' . $HEAD . ';font-size:24px;font-weight:700;color:#221041;line-height:1.2;margin:0 0 12px">Fast geschafft!</div>'
           . '<div style="' . $BODY . ';font-size:15px;color:#4a4458;line-height:1.65;margin:0 0 22px">Danke für dein Interesse am ' . $vgLink . '-Newsletter. Bestätige deine Anmeldung mit einem Klick, dann bekommst du ab sofort die wichtigsten GTA-6-News direkt per Mail.</div>'
           . '<div style="margin:0 0 24px"><a href="' . htmlspecialchars($confirmUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#D00059;color:#ffffff;text-decoration:none;' . $BODY . ';font-size:15px;font-weight:700;padding:13px 26px;border-radius:10px">Anmeldung bestätigen</a></div>'
           . '<div style="' . $BODY . ';font-size:14px;color:#4a4458;line-height:1.6;margin:0 0 10px">Tritt auch gerne unserem Discord bei:</div>'
           . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="background:#ffffff;border:1px solid #ecdfca;border-radius:12px;margin:0 0 22px"><tr><td style="padding:13px 15px">'
           . '<a href="' . $discordUrl . '" style="text-decoration:none;display:block"><table role="presentation" cellpadding="0" cellspacing="0" width="100%"><tr>'
           . '<td width="50" style="vertical-align:middle"><div style="width:40px;height:40px;border-radius:9px;background:#5865F2;color:#ffffff;text-align:center;line-height:40px;font-size:20px">&#128172;</div></td>'
           . '<td style="vertical-align:middle;padding-left:12px">'
           . '<div style="' . $HEAD . ';font-size:17px;font-weight:700;color:#221041;line-height:1.2">Discord beitreten</div>'
           . '<div style="' . $BODY . ';font-size:13px;color:#6b6478;line-height:1.4">Chatten, Fragen und News in Echtzeit mit anderen GTA-6-Fans</div>'
           . '</td></tr></table></a></td></tr></table>'
           . '<div style="' . $BODY . ';font-size:13px;color:#8a7fa5;line-height:1.6">Wenn du dich nicht angemeldet hast, ignorier diese Mail einfach, dann passiert nichts.</div>';
    $footer = '<div style="' . $BODY . ';font-size:12px;color:#9a90ac;line-height:1.6">ViceGuide ist ein inoffizielles Fan-Portal und steht in keiner Verbindung zu Rockstar Games oder Take-Two Interactive.</div>';
    vg_send_mail($cfg, $email, 'Bitte bestätige deine Newsletter-Anmeldung', vg_mail_shell($inner, $footer, $cfg), [], vg_newsletter_opts($cfg));
    nl_json($neutral);
}

nl_json(['error' => 'Methode nicht unterstuetzt'], 405);
