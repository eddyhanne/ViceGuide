<?php
/*
 * Partner-Kontaktformular fuer ViceGuide (/partner).
 *
 * POST {name, channel, email, message?, website?}
 *   -> schickt die Anfrage per Mail an notify_email (bzw. den Standard-Absender
 *      info@), Reply-To ist die Adresse des Creators, sodass ein einfaches
 *      "Antworten" direkt bei ihm landet. Kein Speichern in der Datenbank, reine
 *      Weiterleitung ueber die bestehende vg_send_mail-Infrastruktur.
 *
 * "website" ist ein Honeypot-Feld: fuer Menschen per CSS unsichtbar, nur Bots
 * fuellen es. Ist es gefuellt, antwortet der Endpunkt neutral mit ok, sendet
 * aber nichts.
 */

require __DIR__ . '/db.php';
require __DIR__ . '/mail.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

[$pdo, $cfg] = vg_db();

function vg_p_out($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vg_p_out(['error' => 'Nur POST erlaubt.'], 405);
}

$raw = file_get_contents('php://input');
$b = json_decode($raw, true);
if (!is_array($b)) $b = [];

// Honeypot: gefuellt = Bot. Neutral bestaetigen, aber nichts versenden.
if (trim((string)($b['website'] ?? '')) !== '') vg_p_out(['ok' => true]);

// Steuerzeichen raus (Header-Injection-Schutz), dann Laenge begrenzen.
$clean = function ($v, int $max): string {
    $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$v);
    return mb_substr(trim($v), 0, $max);
};
$name    = $clean($b['name'] ?? '', 120);
$channel = $clean($b['channel'] ?? '', 300);
$email   = $clean($b['email'] ?? '', 190);
// Nachricht darf Zeilenumbrueche behalten, nur andere Steuerzeichen entfernen.
$message = mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', (string)($b['message'] ?? ''))), 0, 3000);

if ($name === '' || $channel === '' || $email === '') {
    vg_p_out(['error' => 'Bitte Name, Kanal und E-Mail ausfuellen.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    vg_p_out(['error' => 'Bitte eine gueltige E-Mail-Adresse angeben.'], 400);
}

$to = trim((string)($cfg['notify_email'] ?? '')) ?: vg_extract_addr(vg_mail_from($cfg));
if ($to === '') vg_p_out(['error' => 'Kontakt derzeit nicht moeglich.'], 500);

$esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$msgHtml = $message !== ''
    ? nl2br($esc($message))
    : '<span class="m-soft" style="color:#6B5E85">(keine Nachricht angegeben)</span>';

$inner =
    '<h1 class="m-h" style="font-family:Oswald,Arial,sans-serif;font-weight:700;font-size:22px;margin:0 0 6px;color:#221041">Neue Partner-Anfrage</h1>'
  . '<p class="m-soft" style="font-family:Inter,Arial,sans-serif;font-size:13px;color:#6B5E85;margin:0 0 16px">ueber das Formular auf viceguide.de/partner</p>'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:Inter,Arial,sans-serif;font-size:15px;color:#221041">'
  . '<tr><td class="m-soft" valign="top" style="padding:5px 12px 5px 0;color:#6B5E85;white-space:nowrap">Name</td><td class="m-tx" style="padding:5px 0;color:#221041">' . $esc($name) . '</td></tr>'
  . '<tr><td class="m-soft" valign="top" style="padding:5px 12px 5px 0;color:#6B5E85;white-space:nowrap">Kanal</td><td class="m-tx" style="padding:5px 0;color:#221041">' . $esc($channel) . '</td></tr>'
  . '<tr><td class="m-soft" valign="top" style="padding:5px 12px 5px 0;color:#6B5E85;white-space:nowrap">E-Mail</td><td class="m-tx" style="padding:5px 0"><a class="m-acc" href="mailto:' . $esc($email) . '" style="color:#D00059;text-decoration:none">' . $esc($email) . '</a></td></tr>'
  . '</table>'
  . '<div class="m-box" style="margin:16px 0 0;padding:14px 16px;background:#FFF6EA;border:1px solid #ecdfca;border-radius:12px;font-family:Inter,Arial,sans-serif;font-size:15px;line-height:1.6;color:#221041">' . $msgHtml . '</div>'
  . '<p class="m-soft" style="font-family:Inter,Arial,sans-serif;font-size:13px;color:#6B5E85;margin:16px 0 0">Zum Antworten einfach auf diese Mail antworten, sie geht direkt an den Creator.</p>';

$footer = '<p style="font-family:Inter,Arial,sans-serif;font-size:12px;color:#8a7fa3;margin:0;text-align:center">Partner-Anfrage von viceguide.de/partner</p>';

// Reply-To bewusst nur die validierte Adresse (kein Name), damit keine
// Steuerzeichen aus dem Namen in den Header gelangen koennen.
$sent = vg_send_mail(
    $cfg,
    $to,
    'Partner-Anfrage: ' . $name,
    vg_mail_shell($inner, $footer, $cfg),
    ['Reply-To: <' . $email . '>']
);

if (!$sent) vg_p_out(['error' => 'Senden fehlgeschlagen. Schreib uns direkt an info@viceguide.de.'], 502);
vg_p_out(['ok' => true]);
