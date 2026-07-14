<?php
/*
 * Gemeinsamer Mailversand fuer ViceGuide (Kommentar-Benachrichtigung an den
 * Admin und Newsletter).
 *
 * Zwei Wege, automatisch gewaehlt:
 *  1. Authentifiziertes SMTP ueber ein echtes Postfach (empfohlen, z.B.
 *     info@viceguide.de bei Hostinger). Dann signiert der Hostinger-Mailserver
 *     die Mail mit DKIM, die Zustellbarkeit ist deutlich besser. Aktiv, sobald
 *     smtp_host und smtp_user in config.php gesetzt sind.
 *  2. Fallback auf die PHP-Funktion mail(), wenn kein SMTP konfiguriert ist.
 *     Ohne saubere SPF/DKIM/DMARC-Eintraege landet das leicht im Spam.
 *
 * vg_send_mail() gibt true/false zurueck und wirft nie: ein fehlgeschlagener
 * Versand darf nie den aufrufenden Endpunkt abbrechen.
 */

function vg_mail_from(array $cfg): string {
    return trim((string)($cfg['mail_from'] ?? '')) ?: 'ViceGuide <no-reply@viceguide.de>';
}

function vg_site_url(array $cfg): string {
    return rtrim(trim((string)($cfg['site_url'] ?? 'https://viceguide.de')), '/');
}

/* Zieht die reine E-Mail-Adresse aus einem "Name <mail@domain>"-Header. */
function vg_extract_addr(string $s): string {
    if (preg_match('/<([^>]+)>/', $s, $m)) return trim($m[1]);
    $s = trim($s);
    return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : '';
}

function vg_send_mail(array $cfg, string $to, string $subject, string $html, array $extraHeaders = []): bool {
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    if (!empty($cfg['smtp_host']) && !empty($cfg['smtp_user'])) {
        return vg_smtp_send($cfg, $to, $subject, $html, $extraHeaders);
    }
    return vg_mail_native($cfg, $to, $subject, $html, $extraHeaders);
}

/* Klassischer Versand ueber PHP mail(). */
function vg_mail_native(array $cfg, string $to, string $subject, string $html, array $extraHeaders): bool {
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . vg_mail_from($cfg),
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
   dependency-freier Mini-Client. Unterstuetzt drei Modi (smtp_secure):
   'ssl' (implizites TLS, meist Port 465), 'tls' (STARTTLS, meist Port 587)
   und 'none' (ungesichert, nur fuer lokale Tests). */
function vg_smtp_send(array $cfg, string $to, string $subject, string $html, array $extraHeaders): bool {
    try {
        $host   = (string)$cfg['smtp_host'];
        $port   = (int)($cfg['smtp_port'] ?? 465);
        $user   = (string)$cfg['smtp_user'];
        $pass   = (string)($cfg['smtp_pass'] ?? '');
        $secure = strtolower((string)($cfg['smtp_secure'] ?? ($port === 587 ? 'tls' : 'ssl')));
        $timeout = 15;

        $fromHeader = vg_mail_from($cfg);
        $fromAddr   = vg_extract_addr($fromHeader) ?: $user;
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
