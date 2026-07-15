# snapshots/

Automatisch erzeugte Spiegel des **Live-Datenstands** (MySQL), gezogen aus den
oeffentlichen API-Endpunkten durch den Workflow `.github/workflows/data-snapshot.yml`
(taeglich plus manuell ausloesbar).

- `articles.json`  — Antwort von `GET /api/articles.php`  (`{ "articles": [ ... ] }`)
- `database.json`  — Antwort von `GET /api/db_entries.php` (`{ "sections": { ... } }`)

**Zweck:** Damit ein frisch geklonter Repo-Stand (etwa fuer die Arbeit mit
Claude Code) den aktuellen Inhalt kennt, ohne dass jemand die Dateien von Hand
exportieren und hochladen muss.

**Wichtig:**
- Das ist **kein** Live-Speicher und **nicht** der Notfall-Fallback. Die Quelle
  der Wahrheit bleibt die Datenbank. Der Notfall-Fallback sind weiterhin die
  base64-haltigen `articles.json` / `database.json` im Repo-Wurzelverzeichnis.
- Bilder stehen hier als URL (nicht als Base64), der Snapshot bleibt dadurch klein.
- Nicht von Hand bearbeiten, wird beim naechsten Lauf ueberschrieben.
