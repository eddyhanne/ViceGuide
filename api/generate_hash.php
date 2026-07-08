<?php
/*
 * Einmal-Werkzeug, um ein neues Admin-Passwort in einen Hash umzuwandeln.
 *
 * Benutzung: /api/generate_hash.php?pw=DEINNEUESPASSWORT im Browser aufrufen,
 * den ausgegebenen Hash in config.php bei admin_hash eintragen.
 *
 * Wichtig: Diese Datei danach wieder loeschen (im Hostinger Dateimanager),
 * sie soll nicht dauerhaft online erreichbar bleiben.
 */

header('Content-Type: text/plain; charset=utf-8');

$pw = $_GET['pw'] ?? '';
if ($pw === '') {
    echo "Aufruf mit ?pw=DEINPASSWORT anhaengen.";
    exit;
}

echo password_hash($pw, PASSWORD_BCRYPT);
