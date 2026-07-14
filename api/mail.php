<?php
/*
 * Gemeinsamer Mailversand fuer ViceGuide (Kommentar-Benachrichtigung an den
 * Admin und Newsletter). Nutzt die PHP-Funktion mail() auf dem Hostinger-
 * Webspace. Absender, Empfaenger und Basis-URL kommen aus config.php.
 *
 * Hinweis zur Zustellbarkeit: mail() ueber Shared Hosting landet ohne saubere
 * SPF/DKIM/DMARC-Eintraege fuer die Domain leichter im Spam. Fuer die einzelne
 * Admin-Benachrichtigung unkritisch, fuer den Newsletter im Blick behalten
 * (siehe CLAUDE.md, offene Aufgaben).
 */

function vg_mail_from(array $cfg): string {
    return trim((string)($cfg['mail_from'] ?? '')) ?: 'ViceGuide <no-reply@viceguide.de>';
}

function vg_site_url(array $cfg): string {
    return rtrim(trim((string)($cfg['site_url'] ?? 'https://viceguide.de')), '/');
}

/* Verschickt eine HTML-Mail. Gibt true/false zurueck und wirft nie: ein
   fehlgeschlagener Versand darf nie den aufrufenden Endpunkt abbrechen. */
function vg_send_mail(array $cfg, string $to, string $subject, string $html, array $extraHeaders = []): bool {
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . vg_mail_from($cfg),
    ];
    foreach ($extraHeaders as $h) {
        if (is_string($h) && $h !== '') $headers[] = $h;
    }

    // Betreff RFC-2047-kodieren, damit Umlaute sauber ankommen.
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    try {
        return @mail($to, $encSubject, $html, implode("\r\n", $headers));
    } catch (Throwable $e) {
        return false;
    }
}
