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
];
