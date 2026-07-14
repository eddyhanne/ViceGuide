<?php
/*
 * Gemeinsamer Mailversand fuer ViceGuide.
 *
 * Zwei Absender-Identitaeten:
 *  - Standard (info@viceguide.de): Kommentar-Benachrichtigung an den Admin.
 *  - Newsletter (newsletter@viceguide.de): alle Newsletter-Mails. Die
 *    Newsletter-Identitaet wird per $opts an vg_send_mail() uebergeben
 *    (from + eigene SMTP-Zugangsdaten), siehe api/newsletter.php.
 *
 * Zwei Versandwege, automatisch gewaehlt:
 *  1. Authentifiziertes SMTP ueber ein echtes Postfach (empfohlen, DKIM-signiert
 *     durch Hostinger, bessere Zustellbarkeit). Aktiv, sobald smtp_host gesetzt
 *     und ein SMTP-Benutzer vorhanden ist.
 *  2. Fallback auf PHP mail(), wenn kein SMTP konfiguriert ist.
 *
 * vg_send_mail() gibt true/false zurueck und wirft nie: ein fehlgeschlagener
 * Versand darf nie den aufrufenden Endpunkt abbrechen.
 */

function vg_mail_from(array $cfg): string {
    return trim((string)($cfg['mail_from'] ?? '')) ?: 'ViceGuide <info@viceguide.de>';
}

function vg_site_url(array $cfg): string {
    return rtrim(trim((string)($cfg['site_url'] ?? 'https://viceguide.de')), '/');
}

/* Gemeinsame Mail-Huelle fuer ALLE gebrandeten Mails (Newsletter, Bestaetigung,
   Admin-Benachrichtigungen): heller Website-Hintergrund, Kopf-Bild aus dem
   Homepage-Hero, Website-Schriften, und Dark-Mode ueber prefers-color-scheme
   (Dunkel-Optik der Website in Apple Mail/iOS; Gmail/Outlook bleiben hell).
   $inner = Inhalt, $footer = Fusszeile (pro Mailtyp unterschiedlich). Liegt im
   gemeinsamen Helfer, damit newsletter.php UND comments.php sie nutzen koennen. */
function vg_mail_shell(string $inner, string $footer, array $cfg): string {
    $base = vg_site_url($cfg);
    $ff = "@font-face{font-family:'Oswald';font-weight:200 700;font-display:swap;src:url('$base/assets/fonts/oswald-variable.woff2') format('woff2')}"
        . "@font-face{font-family:'Inter';font-weight:100 900;font-display:swap;src:url('$base/assets/fonts/inter-variable.woff2') format('woff2')}";
    $dark = '@media (prefers-color-scheme:dark){'
        . '.m-page{background:#0D0A1A!important}'
        . '.m-card{background:#1B1436!important;border-color:rgba(255,255,255,.12)!important}'
        . '.m-box{background:#241a45!important;border-color:rgba(255,255,255,.14)!important}'
        . '.m-h{color:#F7E7C4!important}'
        . '.m-tx{color:#ECE6F7!important}'
        . '.m-soft{color:#A99CC4!important}'
        . '.m-acc,a.m-acc{color:#88B8C5!important}'
        . '.m-btn{background:#88B8C5!important;color:#1a0f28!important}'
        . '.m-foot,.m-foot a{color:#8f86a6!important}'
        . '}';
    return '<!doctype html><html lang="de"><head><meta charset="utf-8">'
         . '<meta name="viewport" content="width=device-width,initial-scale=1">'
         . '<meta name="color-scheme" content="light dark">'
         . '<meta name="supported-color-schemes" content="light dark">'
         . '<style>' . $dark . $ff . '</style></head>'
         . '<body class="m-page" style="margin:0;padding:0;background:#FBF3E7">'
         . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#FBF3E7" class="m-page" style="background:#FBF3E7"><tr><td align="center" bgcolor="#FBF3E7" class="m-page" style="padding:24px 14px">'
         . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px">'
         . '<tr><td style="padding:0"><a href="' . $base . '/"><img src="' . $base . '/newsletter-header.jpg" width="600" alt="ViceGuide" style="width:100%;max-width:100%;height:auto;display:block;border-radius:18px 18px 0 0"></a></td></tr>'
         . '<tr><td bgcolor="#FFF9EF" class="m-card" style="padding:24px 26px 28px;background:#FFF9EF;border:1px solid #ecdfca;border-top:none;border-radius:0 0 18px 18px">'
         . $inner
         . '</td></tr>'
         . '<tr><td bgcolor="#FBF3E7" class="m-page" style="padding:16px 26px 6px">' . $footer . '</td></tr>'
         . '</table></td></tr></table></body></html>';
}

/* Zieht die reine E-Mail-Adresse aus einem "Name <mail@domain>"-Header. */
function vg_extract_addr(string $s): string {
    if (preg_match('/<([^>]+)>/', $s, $m)) return trim($m[1]);
    $s = trim($s);
    return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : '';
}

/* $opts kann die Absender-Identitaet fuer diesen einen Versand ueberschreiben:
   'from'      -> Absender-Header (z.B. 'ViceGuide <newsletter@viceguide.de>')
   'smtp_user' -> SMTP-Benutzer, unter dem authentifiziert wird
   'smtp_pass' -> zugehoeriges Passwort
   Fehlt ein Wert, greift der jeweilige Standard aus config.php. */
function vg_send_mail(array $cfg, string $to, string $subject, string $html, array $extraHeaders = [], array $opts = []): bool {
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $from     = trim((string)($opts['from'] ?? '')) ?: vg_mail_from($cfg);
    $smtpUser = trim((string)($opts['smtp_user'] ?? ($cfg['smtp_user'] ?? '')));
    $smtpPass = (string)($opts['smtp_pass'] ?? ($cfg['smtp_pass'] ?? ''));

    if (!empty($cfg['smtp_host']) && $smtpUser !== '') {
        return vg_smtp_send($cfg, $to, $subject, $html, $extraHeaders, $from, $smtpUser, $smtpPass);
    }
    return vg_mail_native($from, $to, $subject, $html, $extraHeaders);
}

/* Klassischer Versand ueber PHP mail(). */
function vg_mail_native(string $from, string $to, string $subject, string $html, array $extraHeaders): bool {
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from,
    ];
    foreach ($extraHeaders as $h) {
        if (is_string($h) && $h !== '') $headers[] = $h;
    }
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    try {
        return @mail($to, $encSubject, $html, implode("\r\n", $headers));
    } catch (Throwable $e) {
        return false;
    }
}

/* Versand ueber authentifiziertes SMTP (AUTH LOGIN). Selbst gehaltener,
   dependency-freier Mini-Client. Absender und Zugangsdaten werden uebergeben,
   damit verschiedene Kanaele (info@ / newsletter@) je eigene Identitaeten
   nutzen koennen. Modi (smtp_secure): 'ssl' (implizites TLS, meist Port 465),
   'tls' (STARTTLS, meist Port 587), 'none' (ungesichert, nur fuer Tests). */
function vg_smtp_send(array $cfg, string $to, string $subject, string $html, array $extraHeaders, string $fromHeader, string $user, string $pass): bool {
    try {
        $host   = (string)$cfg['smtp_host'];
        $port   = (int)($cfg['smtp_port'] ?? 465);
        $secure = strtolower((string)($cfg['smtp_secure'] ?? ($port === 587 ? 'tls' : 'ssl')));
        $timeout = 15;

        $fromAddr = vg_extract_addr($fromHeader) ?: $user;
        $ehlo = parse_url(vg_site_url($cfg), PHP_URL_HOST) ?: 'localhost';

        $transport = ($secure === 'ssl') ? "ssl://$host:$port" : "tcp://$host:$port";
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $fp = @stream_socket_client($transport, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) return false;
        stream_set_timeout($fp, $timeout);

        $read = function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                // Letzte Zeile eines (evtl. mehrzeiligen) Replies: Leerzeichen nach dem 3-stelligen Code.
                if (strlen($line) >= 4 && $line[3] === ' ') break;
            }
            return $data;
        };
        $cmd = function (string $c) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            return $read();
        };
        $ok = fn(string $resp, int $code): bool => $resp !== '' && strncmp($resp, (string)$code, 3) === 0;

        if (!$ok($read(), 220)) { fclose($fp); return false; }
        if (!$ok($cmd("EHLO $ehlo"), 250)) { fclose($fp); return false; }

        if ($secure === 'tls') {
            if (!$ok($cmd('STARTTLS'), 220)) { fclose($fp); return false; }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return false; }
            if (!$ok($cmd("EHLO $ehlo"), 250)) { fclose($fp); return false; }
        }

        if (!$ok($cmd('AUTH LOGIN'), 334)) { fclose($fp); return false; }
        if (!$ok($cmd(base64_encode($user)), 334)) { fclose($fp); return false; }
        if (!$ok($cmd(base64_encode($pass)), 235)) { fclose($fp); return false; }

        if (!$ok($cmd("MAIL FROM:<$fromAddr>"), 250)) { fclose($fp); return false; }
        $rcpt = $cmd("RCPT TO:<$to>");
        if (!$ok($rcpt, 250) && !$ok($rcpt, 251)) { fclose($fp); return false; }
        if (!$ok($cmd('DATA'), 354)) { fclose($fp); return false; }

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $fromHeader,
            'To: ' . $to,
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        foreach ($extraHeaders as $h) {
            if (is_string($h) && $h !== '') $headers[] = $h;
        }
        $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($html));
        // Dot-Stuffing: Zeilen, die mit einem Punkt beginnen, verdoppeln (RFC 5321).
        $message = preg_replace('/^\./m', '..', $message);
        fwrite($fp, $message . "\r\n.\r\n");
        if (!$ok($read(), 250)) { fclose($fp); return false; }

        $cmd('QUIT');
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
