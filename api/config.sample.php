<?php
/*
 * Vorlage fuer die Datenbank- und Admin-Zugangsdaten.
 *
 * So richtest du es ein:
 * 1. Diese Datei kopieren und in config.php umbenennen (im selben /api Ordner).
 * 2. Die drei db_* Werte mit deinen echten Hostinger-Datenbankdaten fuellen
 *    (Hostinger Dashboard, Website, Datenbank verwalten).
 * 3. config.php NIEMALS ins Git-Repo hochladen, sie steht bewusst in .gitignore.
 *    Du laedst sie einmalig manuell ueber den Hostinger Dateimanager in den
 *    Ordner /api hoch.
 * 4. admin_hash ist bereits ein fertiger Hash deines Admin-Passworts, nicht
 *    das Passwort selbst. Willst du das Passwort spaeter aendern, ruf einmal
 *    /api/generate_hash.php?pw=DEINNEUESPASSWORT im Browser auf, kopier den
 *    ausgegebenen Hash hier rein und loesch generate_hash.php danach wieder.
 */

return [
    'db_dsn'     => 'mysql:host=localhost;dbname=DEIN_DATENBANKNAME;charset=utf8mb4',
    'db_user'    => 'DEIN_DB_BENUTZER',
    'db_pass'    => 'DEIN_DB_PASSWORT',
    'admin_hash' => '$2y$10$ersetzeMichMitEinemEchtenHashAusGenerateHashPhp',

    // E-Mail an diese Adresse bei jedem neuen (fremden) Kommentar.
    // Leer lassen ('') schaltet die Kommentar-Benachrichtigung ab.
    // Kann dein privates Gmail sein oder info@viceguide.de.
    'notify_email' => 'hanneeddy@gmail.com',

    // Absender fuer alle ausgehenden Mails (Kommentar-Benachrichtigung und
    // Newsletter). Sollte eine echte Adresse deiner Domain sein.
    'mail_from'    => 'ViceGuide <info@viceguide.de>',

    // Basis-URL fuer Links in Mails (Artikel-Link, Newsletter-Bestaetigung
    // und -Abmeldung). Ohne abschliessenden Schraegstrich.
    'site_url'     => 'https://viceguide.de',

    // Authentifizierter SMTP-Versand ueber ein echtes Postfach (empfohlen,
    // bessere Zustellbarkeit dank DKIM). Sobald smtp_host und smtp_user
    // gesetzt sind, laeuft der Versand darueber statt ueber PHP mail().
    // Zugangsdaten stehen im Hostinger-Panel unter E-Mail, Konfigurationseinstellungen.
    // Hostinger: Host smtp.hostinger.com, Port 465 mit smtp_secure 'ssl'
    // (alternativ Port 587 mit 'tls'). smtp_user ist die volle Adresse.
    'smtp_host'    => '',                    // z.B. 'smtp.hostinger.com'
    'smtp_port'    => 465,
    'smtp_user'    => '',                    // z.B. 'info@viceguide.de'
    'smtp_pass'    => '',                    // Passwort des Postfachs
    'smtp_secure'  => 'ssl',                 // 'ssl' (465), 'tls' (587) oder 'none'
];
