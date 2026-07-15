#!/usr/bin/env sh
# Holt den aktuellen Live-Datenstand direkt aus der oeffentlichen API nach
# snapshots/. Funktioniert nur, wenn die Netzwerk-Policy der Umgebung
# ausgehende Zugriffe auf viceguide.de erlaubt (sonst blockt der Proxy mit
# 403, dann uebernimmt der taegliche GitHub-Workflow den Abgleich).
set -eu
BASE="${1:-https://viceguide.de}"
mkdir -p snapshots
curl -fsS "$BASE/api/articles.php"   -o snapshots/articles.json
curl -fsS "$BASE/api/db_entries.php" -o snapshots/database.json
echo "Live-Stand nach snapshots/ geholt."
