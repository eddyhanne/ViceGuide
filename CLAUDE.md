# CLAUDE.md — ViceGuide

> Arbeitsanweisung und Projektgedächtnis für Claude. Diese Datei ist so geschrieben, dass ein Claude auf einem anderen Account, Rechner oder in einem separaten Chat (z. B. einem eigenen Chat nur für Artikel-Erstellung) sofort produktiv weiterarbeiten kann, ohne die bisherige Chat-Historie zu kennen. Alles Wichtige steht hier direkt drin.

---

## 0. Wichtigste Regeln zuerst

**Regel 1, keine Gedankenstriche.** Weder "–" (Halbgeviert) noch "—" (Geviert) dürfen irgendwo auftauchen: nicht im Website-Text, nicht im Code, nicht in Commit-Messages, nicht in KI-Prompts, nicht in KI-generierten Artikeln. Ersetze sie durch Komma, Doppelpunkt oder Punkt. Normale Bindestriche in zusammengesetzten Wörtern (z. B. "Schritt-für-Schritt", "Money-Methods") sind erlaubt. Diese Regel gilt ausnahmslos für das gesamte Projekt.

**Regel 2, natürlicher Redaktions-Ton, nicht generisch KI-klingend.** Jeder Artikel, jede Beschreibung, jeder Text auf der Seite soll klingen, als hätte ihn ein ausgebildeter Redakteur geschrieben, der sich mit GTA auskennt und Spaß am Thema hat, nicht wie eine austauschbare KI-Zusammenfassung. Konkret heißt das:
- Keine leeren Füllfloskeln ("Es ist wichtig zu beachten, dass...", "Zusammenfassend lässt sich sagen...", "Tauche ein in die Welt von...").
- Keine übertriebene Marketing-Sprache oder Ausrufezeichen-Enthusiasmus. Sachlich-locker statt reißerisch.
- Variabler Satzbau statt gleichförmiger Drei-Punkte-Aufzählungsrhythmus in jedem Absatz.
- Konkrete Fakten und Details statt vager Verallgemeinerungen. Lieber eine spezifische Quelle nennen als "Berichten zufolge".
- Ein Artikel darf eine eigene Position/Einordnung haben ("wirkt eher wie ein Gerücht, weil..."), das ist genau der USP von ViceGuide gegenüber reinem Leak-Kopieren.
- Das gilt für alle Texte: Artikel-Inhalte, Teaser, Datenbank-Beschreibungen, Meta-Texte.

---

## 1. Projektzweck, Ziel, Zielgruppe

**ViceGuide** ist ein inoffizielles, deutschsprachiges Fan-Portal rund um **GTA 6 (Grand Theft Auto VI)**. Domain: **viceguide.de**, live und erreichbar.

**Geschäftsidee:** Ein Content-Hub, der als zweites Standbein Einnahmen generieren soll (Nebeneinkommen, langfristiger Aufbau, kein Vollzeit-Ersatz). Monetarisierung geplant über Display-Ads (AdSense, später Premium-Netzwerke) und Affiliate-Marketing (der Betreiber hat beruflichen Hintergrund in Cost-per-Sale und Amazon-Affiliate, das ist der Wettbewerbsvorteil gegenüber typischen Fanseiten). Aktuell (Stand dieser Datei) sind noch keine Ads oder Affiliate-Links live geschaltet.

**Strategischer Ankerpunkt:** GTA-6-Release am **19. November 2026**. Davor Traffic über News und Leaks aufbauen, danach mit immergrünen In-Game-Guides nachhaltig monetarisieren. News und Leaks bringen Traffic, monetarisieren aber schlecht. Guides nach Release bringen dauerhaften Long-Tail-Umsatz.

**Zwei-Phasen-Konzept:**
- **Phase 1 (vor Release, jetzt live):** News und Updates, Gerüchte und Leaks, Trailer-Analysen, Charaktere und Story, Map und Setting, Community und Infohäppchen.
- **Phase 2 (ab Release-Day, gesperrt bis dahin):** Money Methods, Missionen und Walkthroughs, Fahrzeuge und Fundorte, Waffen, Secrets und Easter Eggs, Online-Modus, Anfänger-Guides.

**Zielgruppe:** Deutschsprachige GTA-6-Fans, die aktuell auf englische Quellen angewiesen sind. USP: Deutschsprachige Einordnung ("Echt oder Fake?") statt blindes Teilen von Leaks, kompakt und ohne Clickbait. Im deutschsprachigen GTA-6-Nischenmarkt gibt es kaum Konkurrenz, das ist eine First-Mover-Position.

**Rechtlicher Rahmen (fest):** Klarer Fan-Disclaimer auf jeder Seite. Keine offiziellen Rockstar-Bilder oder -Logos. Nur eigene, KI-generierte oder lizenzierte Grafiken. Die Vice-City-Farbwelt und Stimmung dürfen nachgebaut werden (Farben und Verläufe sind nicht schützbar), konkrete Rockstar-Artworks (Key-Art, VI-Logo, Charakter-Illustrationen) nicht. Standard-Disclaimer-Text (wörtlich auf der Seite verwendet):

> ViceGuide ist ein inoffizielles, von Fans erstelltes Guide-Portal und steht in keiner Verbindung zu Rockstar Games oder Take-Two Interactive. Alle Marken, Namen und Bezüge gehören ihren jeweiligen Eigentümern. Inhalte vor Release basieren teils auf Leaks und Gerüchten und sind nicht offiziell bestätigt.

Impressum und Datenschutzerklärung sind mit echten Betreiberdaten befüllt (Eddy Hanné, Privatperson, kein Gewerbe, Stand dieser Datei). Sobald echte Werbeeinnahmen fließen, mit nachhaltiger Gewinnerzielungsabsicht, wird in der Regel eine Gewerbeanmeldung nötig, unabhängig vom Impressum-Text, das rechtzeitig prüfen.

---

## 2. Tech-Stack, Architektur, Ordnerstruktur

### Tech-Stack
- **Frontend:** Eine einzige, in sich geschlossene **HTML-Datei** (`index.html`, nicht `viceguide.html`, kein Build-Skript, kein `build_site.py`, direkt editieren). Kein Framework, kein Bundler. Vanilla HTML, CSS (CSS Custom Properties für Theming), Vanilla JavaScript, alles inline in der einen Datei.
- **Backend:** Kleine **PHP-Skripte** im Ordner `api/`, laufen direkt auf dem Hostinger-Webspace (PHP ist dort standardmäßig verfügbar, kein extra Setup). Sprechen mit einer **MySQL-Datenbank**, ebenfalls bei Hostinger. Siehe Abschnitt "Backend / API" unten.
- **Fonts:** **Selbst gehostet**, nicht mehr von Google geladen (DSGVO-Grund). Oswald, Inter, Space Mono, liegen als `.woff2` unter `assets/fonts/` und werden per `@font-face` im `<style>`-Block eingebunden. Oswald und Inter sind Variable Fonts (je eine Datei deckt alle genutzten Schnitte ab), Space Mono liegt als zwei statische Dateien vor (400/700).
- **Hosting:** Domain viceguide.de bei **Hostinger**, Premium Web Hosting, per Git mit dem GitHub-Repo `eddyhanne/ViceGuide` verbunden (Root ist `public_html`). Deployment: Auto-Deploy ist aktiv, ein Push auf `main` zieht sich automatisch auf den Server.

### Architektur
Die Seite ist eine Single-Page-Anwendung mit echtem URL-Routing über `location.hash` (siehe `buildHash()`/`restoreFromLocation()`/`syncHash()` im Script). Jede Ansicht (Sektion, offener Artikel, offenes Datenbank-Detail) spiegelt sich in der URL, dadurch bleibt ein Reload auf derselben Ansicht und der Browser-Zurück-Button navigiert innerhalb der Seite statt sie zu verlassen.

**Datenfluss (wichtig, hat sich grundlegend geändert):** Früher lebten alle Inhalte nur in `articles.json`/`database.json`, die manuell hoch- und heruntergeladen wurden. Jetzt ist die **MySQL-Datenbank die Quelle der Wahrheit**. Der Ladevorgang (`loadExternal()`) fragt zuerst `api/articles.php` und `api/db_entries.php` ab, und fällt nur zurück auf die statischen JSON-Dateien, falls die API nicht erreichbar ist (Notfall-Absicherung, kein aktiver Sync-Mechanismus). `articles.json`/`database.json` im Repo sind seitdem nur noch ein eingefrorener Stand von der Migration, keine laufend gepflegte Datenquelle mehr.

**Hauptbereiche der Seite (`SECTIONS`):** News & Gerüchte (`home`, Typ `guides`), Charaktere, Fahrzeuge, Waffen, Wildtiere, Gangs, Radio, Aktivitäten, Orte (alle Typ `db`), Karte (Typ `map`), Videos (Typ `videos`), Community (Typ `community`). Phase-2-Kategorien sind gesperrt dargestellt.

1. **Startseite / Guides:** Akkordeon-Struktur, zwei Phasen-Gruppen (`GROUPS`: `pre`/`rel`).
2. **Artikel-Detailseite:** Titel, Lead, Content-Absätze, optionale Quellenliste, Kommentarbereich, Inline-Disclaimer. Charakternamen im Text werden automatisch zu Links auf deren Charakter-Detail verlinkt (siehe `linkifyChars()`).
3. **Datenbank:** Detail-Modals aus `DB` (Charaktere, Fahrzeuge, Waffen, Wildtiere, Gangs, Radio, Aktivitäten, Orte).
4. **Karte:** Kartenansicht mit Kartenpunkten.
5. **Radio, Aktivitäten:** eigene Datenbank-Kategorien mit Detail-Modals.
6. **Videos:** eingebettete YouTube-Videos (nocookie-Modus).
7. **Community:** Freitextbereich/Hinweise für Besucher.
8. **Kommentare:** pro Artikel, dauerhaft in der Datenbank, mit Antworten (eine Ebene, mit Zitat der Elternantwort), Upvote/Downvote (ein Vote pro Browser, per `localStorage` gemerkt), Löschen nur im Editiermodus mit Admin-Login.
9. **Admin-Panel / Redaktion (intern, versteckt):** Overlay, erreichbar über `Shift+Alt+R`, die URL `#redaktion`/`#admin`, oder dreifachen Klick auf einen versteckten Footer-Bereich (`foot-secret`). Für Besucher unsichtbar. Enthält: Claude-Entwurf veröffentlichen (siehe unten), Editiermodus, JSON-Sicherungsexport.
10. **Editiermodus:** Schalter im Admin-Panel, fragt beim ersten Einschalten pro Browser das Admin-Passwort ab (serverseitig geprüft, Session hält 90 Tage). Danach macht Kachel-Klick den Bild- und Text-Editor auf (`openImgEd()`), Speichern (`ieApply()`) schreibt sofort und dauerhaft in die Datenbank, kein Datei-Umweg mehr.

### Guide-/Artikel-Datenmodell (JSON, wie es die API liefert und erwartet)
```json
{
  "id": "stabiler-slug-aus-dem-titel",
  "cat": "news",
  "title": "prägnanter Titel, max. 8 Wörter",
  "date": "2026-01-01T00:00",
  "summary": "1 Satz Teaser, max. 20 Wörter",
  "meta": "kurzes Label, z. B. Analyse oder News",
  "lead": "1 einleitender Satz",
  "content": ["Absatz 1", "Absatz 2", "Absatz 3"],
  "sources": [{ "title": "Quellenname", "url": "https://..." }],
  "img": "data:image/webp;base64,...",
  "imgfit": { "zoom": 1, "x": 50, "y": 50 },
  "credit": "optionale Bildquelle",
  "image_queries": ["Suchbegriff 1", "Suchbegriff 2", "Suchbegriff 3"],
  "tldr": ["Kernaussage 1", "Kernaussage 2", "Kernaussage 3"]
}
```
`cat` ist eine von: `news`, `leaks`, `trailer`, `story`, `map`, `community` (Phase 1) bzw. `money`, `missions`, `vehicles`, `weapons`, `secrets`, `online`, `beginner` (Phase 2, aktuell gesperrt). Es gibt **kein** `tag`-Feld mehr, das sichtbare Label kommt aus `GCAT[cat].name` im Code. `id` wird beim Anlegen einmalig aus dem Titel generiert (Slug) und bleibt danach fix, auch wenn der Titel später bearbeitet wird, damit Kommentare nicht verwaisen. `image_queries` ist optional, nur für die Bildsuche-Vorschläge im Admin-Panel, wird nicht mit gespeichert wenn nicht vorhanden. `date` im Entwurf ist nur ein Platzhalter, das Admin-Panel überschreibt es beim tatsächlichen Veröffentlichen automatisch mit dem echten Zeitpunkt, hier muss also keine Zeit recherchiert oder exakt getroffen werden.

**Strukturelemente innerhalb von `content` (bei Bedarf, nicht in jedem Artikel nötig).** Jeder Eintrag im `content`-Array ist normalerweise ein normaler Absatz (String). Zusaetzlich werden folgende Praefixe erkannt und speziell dargestellt:
- `"### Zwischenüberschrift"` , eigene Zwischenüberschrift im Artikel. Bekommt automatisch eine Anker-ID (aus dem Text generiert) fuers Inhaltsverzeichnis.
- `"img:https://bild-url|Bildunterschrift"` , weiteres Bild mitten im Artikeltext (Bildunterschrift optional, dann einfach `"img:https://bild-url"`). Optional ein drittes Segment `"img:https://bild-url|Bildunterschrift|narrow"` fuer ein schmaleres, zentriertes Bild statt voller Breite. In der Claude-Entwurfsvorschau laesst sich die Breite direkt per Knopf am Bild umschalten, ohne den Text neu einzufuegen.
- `"- Ein Punkt"` , Aufzählungspunkt. Mehrere aufeinanderfolgende `"- ..."`-Zeilen ergeben automatisch eine gemeinsame Bulletpoint-Liste.
- `"faq:Frage?|Antworttext"` , ein aufklappbarer FAQ-Eintrag (Akkordeon). Mehrere aufeinanderfolgende `"faq:..."`-Zeilen ergeben automatisch einen gemeinsamen FAQ-Block, UND automatisch strukturierte Daten (`FAQPage`-Schema, siehe unten) fuers Google-Snippet.

Diese Elemente sind Werkzeuge fuer mehr Struktur (siehe Vorbild-Artikel wie bei GIGA: Bulletpoints, Zwischenueberschriften, aufklappbares FAQ), nicht Pflicht. Bei kurzen News-Meldungen reichen normale Absaetze, bei laenglichen Guides/Erklaerartikeln lohnt sich die Gliederung.

**Interne Verlinkung auf andere Artikel, mitten im Fliesstext (in jedem normalen Absatz, Listenpunkt oder FAQ-Text nutzbar, nicht nur als eigener Block).** Syntax: `[[artikel-id|Anzeigetext]]`, z. B. `"Welche Plattformen GTA 6 zum Release bekommt, erfaehrst du in [[plattformen-pc-version-gesichert-und-offen|unserem Plattformen-Guide]]."`. `artikel-id` ist die stabile `id` des Zielartikels (der Slug, nicht der Titel, siehe Feld `id` oben), `Anzeigetext` ist der sichtbare, klickbare Linktext. Existiert die id nicht oder nicht mehr, wird beim Anzeigen automatisch nur der reine Anzeigetext ohne Link dargestellt (kein kaputter Link sichtbar). Diese Syntax ersetzt reine Text-Erwaehnungen wie "dazu gibt es einen eigenen Artikel auf ViceGuide", nach Moeglichkeit bei thematisch passenden Erwaehnungen verwenden, aber sparsam (nicht in jedem Satz), damit der Text nicht ueberladen wirkt. Charakternamen werden bereits automatisch verlinkt (siehe unten bei "Automatische Verlinkung"), dafuer diese Syntax nicht verwenden.

**Trigger-Phrase "Verlinkungs-Check" (fuer Coding-Sessions mit Code-Zugriff, nicht fuer den reinen Artikel-Chat).** Sagt Eddy in einer Coding-Session nur "Verlinkungs-Check" (plus die aktuelle `articles.json` aus dem Editiermodus-Download als Anhang), ist damit gemeint: alle aktuellen Artikel durchlesen, mit bereits vorhandenen `[[id|text]]`-Verweisen abgleichen (nichts doppelt verlinken), sparsam neue, thematisch passende Verweise ergaenzen (max. 1 bis 2 pro Artikel, nur wo es inhaltlich wirklich passt, Formulierung in die bestehenden Saetze einweben statt generischer "Mehr dazu"-Floskel), das Ganze lokal testen (PHP/SQLite), dann ein einmaliges, admin-geschuetztes PHP-Werkzeug bauen das den aktuellen Datenbank-Stand pro Artikel gegen den Ausgangstext prueft (nicht ueberschreiben, falls der Artikel zwischenzeitlich anderweitig bearbeitet wurde) und die neuen Verweise eintraegt. Eddy ruft das Werkzeug einmal eingeloggt im Browser auf, danach wird es wieder aus dem Repo entfernt (siehe "Einmal-Werkzeuge" in den Stolperfallen unten). Kein direkter Live-Zugriff auf viceguide.de moeglich, die aktuelle articles.json muss Eddy jedes Mal frisch als Anhang mitschicken.

**`tldr` (optional, top-level Feld, kein Praefix im content-Array).** Array aus 2 bis 5 kurzen Stichpunkten, die den Artikel in Sekunden zusammenfassen ("Auf einen Blick"). Wird automatisch als eigene Box direkt unter dem Lead angezeigt, wenn vorhanden, sonst bleibt die Box unsichtbar. Sinnvoll bei laengeren oder faktenreichen Artikeln (z. B. Vorbesteller-Boni, Editionen-Vergleich), bei kurzen News-Meldungen meist nicht noetig, dann einfach weglassen.

**Automatisch, ohne eigenes Feld:**
- **Inhaltsverzeichnis** ("Direkt zu"): erscheint automatisch, sobald ein Artikel mindestens 3 `### `-Zwischenueberschriften hat. Kein Redaktions-Aufwand noetig, ergibt sich rein aus der Gliederung.
- **Lesezeit-Anzeige**: wird aus der Wortzahl von `lead` und `content` errechnet (ca. 200 Woerter/Minute) und neben dem Datum angezeigt.
- **FAQ-Schema (`FAQPage` JSON-LD)**: sobald ein Artikel `faq:`-Eintraege enthaelt, wird beim Oeffnen automatisch strukturiertes Datenmarkup in den `<head>` geschrieben (fuer Google Rich Snippets), beim Schliessen wieder entfernt. Kein Redaktions-Aufwand noetig.

**Hinweis fuer kuenftige Coding-Sessions (SEO/Struktur-Kontinuitaet):** Die Artikelstruktur wird bewusst schrittweise verfeinert, mit dem Ziel, Artikel scanbarer/interaktiver zu machen (Leser lesen selten bis zum Ende) und gleichzeitig SEO-Signale zu verbessern (Rich Snippets, interne Verlinkung, Struktur, die zu echten Suchanfragen passt). Bereits umgesetzt: Bulletpoints, aufklappbares FAQ mit Schema-Markup, TL;DR-Box, automatisches Inhaltsverzeichnis, Lesezeit. Denkbare naechste Schritte, wenn wieder an diesem Thema gearbeitet wird: Article-Schema (JSON-LD) fuer alle Artikel zusaetzlich zum FAQ-Schema, gezielte interne Verlinkung zwischen thematisch verwandten Artikeln im Fliesstext, Zwischenueberschriften konsequent an echten Suchanfragen ausrichten. Bei jeder Erweiterung: sparsam bleiben, nicht mehr Feature-Wucht als noetig, Kernrichtung ist "besser lesbar und besser auffindbar", nicht "moeglichst viele Spielereien".

### Datenbank-Eintrag-Datenmodell (Charaktere, Fahrzeuge, usw.)
```json
{
  "name": "Jason Duval",
  "sub": "Protagonist",
  "cat": "Hauptfiguren",
  "src": "Trailer 1-2",
  "desc": "Beschreibungstext.",
  "fields": { "Rolle": "Protagonist", "Erstmals": "Trailer 1" },
  "img": "data:image/webp;base64,...",
  "imgfit": { "zoom": 1, "x": 50, "y": 50 },
  "credit": "optional"
}
```
Aus der API kommt zusätzlich `_id` (interne Datenbank-Zeilen-ID, wird für Updates gebraucht, nicht redaktionell relevant).

### Ordnerstruktur (verifiziert gegen echtes Repo)
```
/                       Projektwurzel
├─ CLAUDE.md            Diese Datei
├─ SOCIAL.md            Social-Media-Strategie und Status
├─ index.html           Die komplette, deploybare Seite (direkt editieren)
├─ articles.json        Eingefrorener Anfangsstand, keine laufende Datenquelle mehr
├─ database.json        Eingefrorener Anfangsstand, keine laufende Datenquelle mehr
├─ og-image.jpg         Link-Vorschaubild (Open Graph/Twitter Card), 1200x630px
├─ robots.txt           Erlaubt allen Bots alles, verweist auf sitemap.xml
├─ sitemap.xml          Statisch, aktuell nur die Startseite (siehe Offene Aufgaben, Punkt 1)
├─ google*.html         Google-Search-Console-Verifizierungsdatei, NICHT loeschen, sonst verliert Search Console die Inhaberschafts-Bestaetigung
├─ .gitignore           Schliesst api/config.php und lokale *.sqlite aus
├─ assets/
│  ├─ img_1.jpg         Palmen-Wallpaper (als base64 in index.html eingebettet)
│  ├─ logo*.*           Eigene Logo-Dateien (als base64 eingebettet)
│  └─ fonts/            Selbst gehostete .woff2 Schriftdateien
└─ api/
   ├─ config.sample.php Vorlage fuer Datenbank-Zugangsdaten und Admin-Passwort-Hash
   ├─ config.php         Echte Zugangsdaten, NICHT im Git (siehe .gitignore), liegt nur auf dem Server
   ├─ db.php             Gemeinsame PDO-Verbindung, legt Tabellen automatisch an (CREATE TABLE IF NOT EXISTS)
   ├─ auth.php           Login/Logout/Status, session-basiert, 90 Tage
   ├─ articles.php       GET/POST/PUT/DELETE fuer Artikel (DELETE loescht auch zugehoerige Kommentare)
   ├─ db_entries.php     GET/PUT/DELETE fuer Datenbank-Eintraege
   └─ comments.php       GET/POST/PATCH/DELETE fuer Kommentare
```
Logo, Wallpaper und alle DB-/Artikel-Bilder liegen als **base64-Data-URIs** direkt in der HTML bzw. in der Datenbank, nicht als separate Bild-Dateien im Repo (Ausnahme: Fonts, die liegen als echte Dateien in `assets/fonts/`, weil das fuers Caching besser ist und Fonts sich nicht staendig aendern).

---

## 3. Konventionen

### Code-Stil
- CSS und JS bleiben inline in `index.html`. Assets als base64-Data-URI, ausser Fonts (siehe oben).
- **Theming ausschließlich über CSS Custom Properties.** Niemals feste Farbwerte hart in Regeln schreiben, immer die `--variable` nutzen. Dark Mode ist die Basis (`:root`), Light Mode überschreibt (`:root[data-theme="light"]`). Light Mode ist Standard-Theme, wird per `localStorage` (`vg-theme`) über Reloads hinweg gemerkt.
- Vanilla JS, keine Build-Time-Transpilation. Funktionsnamen sprechend (`openAdmin`, `toggleEdit`, `ieApply`, `renderView`, `toggleTheme`).
- **Keine Gedankenstriche** und **kein generischer KI-Ton** (siehe Abschnitt 0).
- PHP-Dateien: einfache, kleine Skripte pro Endpunkt, PDO mit Prepared Statements (nie String-Konkatenation fuer SQL), `vg_require_admin($cfg)` als Gate vor jeder schreibenden Aktion.

### Naming
- Kategorie-IDs klein und knapp: `news`, `leaks`, `trailer`, `story`, `map`, `community`, `money`, `missions`, `vehicles`, `weapons`, `secrets`, `online`, `beginner`.
- DB-Sektionen: `characters`, `vehicles`, `weapons`, `wildlife`, `gangs`, `radio`, `activities`, `locations`.
- Sichtbare Labels deutsch und ausgeschrieben ("Gerüchte und Leaks", "Trailer-Analysen").
- Datenkonstanten in JS in GROSSBUCHSTABEN (`SECTIONS`, `DB`, `GUIDES`, `GROUPS`, `GCAT`).

### Branch-, Commit-, Test-Regeln
- Branch für laufende Arbeit: `claude/viceguide-gta6-portal-z49wib`, wird nach Bestätigung durch den Betreiber per Fast-Forward (oder Cherry-Pick bei parallelen Datenänderungen) nach `main` gemergt. `main` ist der Deploy-Branch.
- Commit-Messages: kurz, deutsch, sachlich, **ohne Gedankenstriche**.
- Reine Datenänderungen (`database.json`/`articles.json` Uploads vom Betreiber) werden automatisch validiert (JSON gueltig, keine Gedankenstriche, keine verlorenen Eintraege), committed und gemergt, ohne Rückfrage. Code-/Design-Änderungen werden vorher als Vorschau (Zip zum lokalen Öffnen oder Screenshots) gezeigt, erst nach Bestätigung gemergt.
- Tests: keine automatisierten Tests. Vor dem Commit lokal mit `php -S localhost:PORT -t .` und Playwright/Chromium gegentesten, besonders bei Backend-Aenderungen (Login-Zustand, Speichern, Persistenz nach Reload).

---

## 4. Wichtige Befehle

Kein Build-Schritt. `index.html` ist direkt die ausgelieferte Datei.

**Lokal ansehen (nur Frontend, ohne Backend-Funktionen):**
```bash
python3 -m http.server 8000
# dann http://localhost:8000/index.html
```

**Lokal mit Backend testen (Login, Kommentare, Speichern):** braucht PHP mit PDO/SQLite, `api/config.php` lokal auf SQLite zeigen lassen (siehe `config.sample.php` fuer die Struktur, `db_dsn` auf `sqlite:...` statt `mysql:...` setzen):
```bash
php -S localhost:8000 -t .
# dann http://localhost:8000/index.html
```
`api/db.php` legt die Tabellen beim ersten Aufruf automatisch an.

**Deploy:** Push nach `main` auf GitHub, Hostinger Auto-Deploy zieht sich das automatisch (kann im Hostinger-Dashboard unter "Letzte Bereitstellung" geprüft werden). `api/config.php` liegt **nicht** im Git und muss einmalig manuell über den Hostinger-Dateimanager im Ordner `api/` angelegt werden (Vorlage: `config.sample.php`).

---

## 5. Getroffene Entscheidungen und Begründung

- **Single-File-HTML statt Framework.** Einfach zu hosten, sofort lauffähig, kein Build-Toolchain-Overhead.
- **Zwei-Phasen-Struktur (pre/rel), Phase 2 gesperrt.** An den Spiel-Lebenszyklus gekoppelt.
- **Dual-Theme mit Umschalter**, Light Mode als Standard, per `localStorage` gemerkt.
- **Eigene Grafik statt Rockstar-Material.** Logo, Palmen-Wallpaper, alle Artikel-/Datenbankbilder sind eigenes oder KI-generiertes Material.
- **Fonts selbst gehostet statt Google Fonts.** DSGVO-Grund (keine Datenübertragung an Google), zusätzlich Performance-Vorteil durch Browser-Caching separater Dateien.
- **Bilder automatisch komprimiert.** Jedes im Editiermodus hochgeladene Bild wird clientseitig per Canvas auf max. 1100px Kantenlänge verkleinert und als WebP kodiert (Fallback JPEG falls ein Browser kein WebP-Encoding kann), ohne dass die Redaktion daran denken muss. Grund: eine fruehe Version ohne Kompression liess `database.json` auf ueber 40 MB anwachsen.
- **Direktes Speichern in die Datenbank statt JSON-Download/Upload.** Ab dem Punkt, wo eine MySQL-Datenbank sowieso fuer Kommentare noetig war, wurde dieselbe Infrastruktur auch fuer Artikel und Datenbank-Eintraege genutzt. Ergebnis: Editiermodus speichert sofort und dauerhaft, auch vom Handy aus, ohne Datei-Umweg.
- **Echtes Login statt reinem Client-Schalter.** Der Editiermodus-Knopf allein war nie eine echte Sperre (nur ein JS-Flag). Sobald echte Schreibzugriffe auf eine gemeinsame Datenbank moeglich wurden, wurde ein serverseitig geprueftes Passwort-Login (PHP-Session, 90 Tage) noetig, sonst haette theoretisch jeder Besucher mit Entwicklertools schreiben koennen.
- **KI-Artikel ueber Copy-Paste-JSON statt eigenem Anthropic-API-Schluessel (aktueller Stand).** Der Betreiber wollte die KI-Recherche/Texterstellung weiterhin kostenlos ueber einen normalen Claude-Chat laufen lassen, statt einen bezahlten eigenen API-Key auf dem Server einzurichten. Deshalb: Ergebnis aus dem Chat als JSON ins Admin-Panel einfuegen ("Claude-Entwurf veroeffentlichen"), Vorschau pruefen und bei Bedarf bearbeiten (Titel, Teaser, Text, **und Bild direkt per Klick/Einfuegen/Drag&Drop mit Zoom und Ausschnitt**), dann freigeben. Die Variante mit direkter Live-Recherche gegen die Anthropic-API bleibt als Code-Pfad bestehen (`generateGuide()`), ist aber ohne eigenen Schluessel in `config.php` (`anthropic_api_key`, noch nicht angelegt) nicht nutzbar. Falls die Seite waechst und der Betreiber die volle Handy-Unabhaengigkeit will, kann das jederzeit nachgeruestet werden.
- **Automatische Verlinkung in Texten.** Namen aus Charaktere, Fahrzeuge, Wildtiere, Gangs und Orte (deutscher Genitiv bei Charakteren, z. B. "Jasons") werden in Datenbank-Beschreibungen und Artikeltexten automatisch klickbar zum jeweiligen Detail-Eintrag verlinkt (`linkifyChars()`/`buildEntityAliases()`), inklusive einer Ausschlussliste fuer Namensueberschneidungen mit normalen Woertern (`ENT_LINK_SKIP`, z. B. "Leonida", der Bundesstaat, vs. der Nebencharakter "Leonida Joker"). Waffen, Radio und Aktivitaeten sind bewusst aussen vor, deren Namen kollidieren zu haeufig mit normalen Woertern. In Artikeln wird jeder Name nur beim ersten Vorkommen im gesamten Artikel verlinkt (nicht bei jeder Wiederholung), damit der Text nicht mit Links zugepflastert wird.
- **URL-Routing per Hash.** Reload behaelt die aktuelle Ansicht, Browser-Zurueck navigiert innerhalb der Seite statt sie zu verlassen.
- **Inline-Texteditor im Editiermodus.** Titel/Name, Unterzeile/Teaser und Beschreibung/Text sind direkt im selben Dialog wie der Bild-Editor bearbeitbar, kein separates JSON-Handling fuer kleine Korrekturen mehr noetig.

---

## 6. Aktueller Stand, offene Aufgaben, Stolperfallen

### Aktueller Stand (Stand dieser Datei)
- Seite ist live auf viceguide.de, technisch voll funktionsfaehig, Datenbank-Backend inklusive Login, Kommentare, direktes Speichern.
- Impressum und Datenschutzerklaerung sind mit echten Daten befuellt.
- Fonts selbst gehostet, Bildkompression automatisch, Altlast-Bilder nachtraeglich komprimiert.
- Grundstock an Artikeln existiert, wird laufend per Claude-Entwurf-Workflow erweitert.
- Getrennter Chat/Session-Vorschlag fuer reine Artikel-Erstellung, damit diese Coding-Session nicht mit Content-Arbeit vollgestopft wird. Diese Datei sollte in einem solchen Chat als Projekt-Wissen hinterlegt sein.

### Offene Aufgaben

**Erledigt (Stand dieser Datei):**
- ~~Google Search Console einrichten, Sitemap einreichen~~ (erledigt: Inhaberschaft bestaetigt, Sitemap erfolgreich eingereicht)
- ~~Echtes OG-Image (`og-image.jpg`) erstellen und bereitstellen~~ (erledigt, aus Logo + Wallpaper zusammengesetzt, 1200x630px)
- ~~`robots.txt`/`sitemap.xml` anlegen~~ (erledigt, Sitemap listet aktuell nur die Startseite, siehe Punkt 2 unten zur Ursache)
- ~~Discord-Server aufsetzen~~ (erledigt, Community-Sektion verlinkt live)
- ~~Interne Artikel-Verlinkung (`[[id|text]]`)~~ (erledigt, siehe oben, "Verlinkungs-Check" als wiederkehrender Trigger)

**Offen, nach Prioritaet:**
1. **Groessere Baustelle, noch nicht angegangen: echte, einzeln aufloesbare Artikel-URLs.** Aktuell rein Hash-basiertes Routing (`location.hash`, z. B. `#/home?a=3`, Artikel-Index statt Slug in der URL). Dadurch kann Google einzelne Artikel nicht als eigene Suchtreffer listen, nur die Startseite. Fuer echten Content-SEO-Erfolg (einzelne Artikel ranken bei Suchanfragen) waere eine Umstellung auf slug-basierte, serverseitig aufloesbare URLs noetig, groesseres Architektur-Thema, bewusst zurueckgestellt fuer eine eigene, gut durchdachte Session.
2. **Discord tiefer einbinden:** "Discord öffnen" zusaetzlich zum bestehenden "Discord beitreten" (direkter Sprung statt erneuter Einladungslink), Discord-Widget (Live-Mitgliederzahl, zurueckgestellt bis der Server aktiver ist), eigenes Server-Icon/Branding auf der Seite zeigen, Bot-Anbindung fuer automatisches Posten neuer Artikel (Kurzfassung + Link) in einen Discord-Kanal ueber Webhook, ausgeloest beim Anlegen eines Artikels in `api/articles.php`.
3. Grundstock an Artikeln weiter ausbauen (laufend).
4. Social-Kanäle bespielen (Instagram als @viceguide aktiv, siehe SOCIAL.md), Website-Link erst nach offiziellem Launch in die Bio.
5. Rechtliche Absicherung im Blick behalten: "VI" im Logo und der Wortstamm "Vice" sind Markenrecht-Grauzonen (Naehe zu VICE Media, zu Rockstars "Vice City"). DPMA/EUIPO pruefen, wenn es kommerziell ernster wird.
6. Gewerbeanmeldung pruefen, sobald echte Werbe-/Affiliate-Einnahmen fliessen.
7. Neue Datenbank-Eintraege komplett neu anlegen (z. B. ein bisher unbekanntes Fahrzeug) geht aktuell noch nicht ueber den Editiermodus, nur bestehende Eintraege bearbeiten. Bei Bedarf nachruesten (weiterer API-Endpunkt plus UI).
8. Optional: eigener Anthropic-API-Schluessel fuer echte Live-Recherche direkt auf der Seite (`config.php` Feld `anthropic_api_key`, `generateGuide()` müsste auf einen serverseitigen Proxy-Endpunkt umgestellt werden statt direkt gegen die Anthropic-API zu fetchen), falls der Copy-Paste-Workflow irgendwann zu langsam wird.
9. Discord-Server-Pflege (Regeln, Moderation, Aktivitaet): bewusst als eigenes Projekt/eigener Chat gefuehrt, nicht Teil dieser Coding-Session.

### Stolperfallen
- **Gedankenstriche schleichen sich leicht ein**, besonders in KI-generierten Texten. Nach jeder Generierung prüfen.
- **Generischer KI-Ton schleicht sich ein**, siehe Regel 2 in Abschnitt 0. Vor dem Veroeffentlichen laut vorlesen: klingt das wie ein Mensch mit Ahnung, oder wie eine KI-Zusammenfassung?
- **`api/config.php` nie committen.** Steht in `.gitignore`, muss nach jedem frischen Server-Setup manuell im Hostinger-Dateimanager angelegt werden.
- **Einmal-Werkzeuge (z. B. ein Passwort-Hash-Generator oder ein Migrations-Skript) gehoeren nach Gebrauch nicht nur vom Server geloescht, sondern auch aus dem Git-Repo entfernt (`git rm`).** Sonst kommen sie beim naechsten Deploy automatisch zurueck, weil Hostinger den kompletten Ordner aus dem Repo-Stand wiederherstellt.
- **Artikel-`id` ist fix, der Titel nicht.** Beim Bearbeiten eines Artikeltitels bleibt die zugrunde liegende `id` (und damit die Kommentar-Zuordnung) unveraendert, das ist Absicht.
- **`database.json`/`articles.json` sind kein Live-Speicher mehr.** Falls doch mal jemand denkt, dort etwas aendern zu muessen: es hat keinen Effekt auf die echte Seite, die Datenbank ist massgeblich.
- **Ich (Claude) habe keinen direkten Netzwerkzugriff auf viceguide.de, Google Drive oder die Hostinger-Datenbank** aus dieser Umgebung heraus (Sandbox-Netzwerkrichtlinie blockt fremde Domains). Grosse Dateien oder Live-Checks laufen über den Betreiber, der Ergebnisse/Screenshots zurückmeldet.
- **Content-Aenderungen, die der Betreiber direkt im Browser macht (ohne mich), sind mir in einer neuen Chat-Session nicht automatisch bekannt.** Bei Bedarf kurz nachfragen oder zeigen lassen.

---

## 7. Backend / API (Details)

### Authentifizierung
- `api/auth.php`: `GET` gibt `{loggedIn:bool}` zurueck, `POST {password}` prueft gegen den in `config.php` hinterlegten bcrypt-Hash (`admin_hash`) und startet bei Erfolg eine PHP-Session (Cookie, 90 Tage, HttpOnly, Secure). `DELETE` beendet die Session.
- Jeder schreibende Endpunkt ruft `vg_require_admin($cfg)` auf (in `db.php` definiert), das ohne gueltige Session mit HTTP 403 abbricht.
- Passwort-Hash aendern: kein dauerhaftes Tool im Repo (bewusst geloescht, siehe Stolperfallen). Bei Bedarf lokal `password_hash('neuesPasswort', PASSWORD_BCRYPT)` in PHP ausfuehren (oder Claude fragen) und den Wert in `config.php` eintragen.

### Endpunkte
- `api/articles.php`: `GET` alle Artikel, `POST` neuer Artikel (admin), `PUT {id,...}` Update (admin).
- `api/db_entries.php`: `GET` alle Eintraege gruppiert nach `section`, `PUT {id,...}` Update (admin), `id` ist hier die interne Zeilen-ID (`_id` im GET-Ergebnis), nicht der Name.
- `api/comments.php`: `GET ?article=<id>` Kommentarbaum, `POST {article,name,text,parentId?,quote?}` neuer Kommentar/Antwort, `PATCH {id,dir}` Upvote/Downvote, `DELETE {id}` loeschen (admin).

### KI-Guide-Generator, aktueller Stand
**Primärer Weg (aktiv genutzt):** Ein separater Claude-Chat (idealerweise mit dieser Datei als Projekt-Wissen) recherchiert und schreibt den Artikel als JSON im oben stehenden Format. Der Betreiber fuegt das JSON im Admin-Panel unter "Claude-Entwurf veroeffentlichen" ein (`submitDraftJson()`), bekommt eine editierbare Vorschau (Titel, Teaser, Text, Bild per Klick/Einfuegen/Drag&Drop mit Zoom/Ausschnitt), und veroeffentlicht direkt in die Datenbank ueber die bestehende Admin-Session. Kein API-Schluessel, keine Zusatzkosten.

**Sekundärer, aktuell inaktiver Weg:** `generateGuide()` ruft direkt `https://api.anthropic.com/v1/messages` auf (Modell `claude-sonnet-4-6`, `web_search_20250305` Tool). Das funktioniert nur, wenn ein eigener Anthropic-API-Key vorhanden und (noch zu bauen) serverseitig ueber einen `api/`-Endpunkt eingebunden ist, niemals direkt im Frontend, das waere ein Sicherheitsproblem. Aktuell nicht eingerichtet, im Admin-Panel entsprechend als "braucht eigenen API-Schluessel" gekennzeichnet.

### Fonts
Selbst gehostet unter `assets/fonts/`, per `@font-face` im `<style>`-Block referenziert. Keine Verbindung zu Google mehr.

### Domain und Hosting
- **Domain:** viceguide.de, bei Hostinger, live.
- **Kanonische URL:** `https://viceguide.de/`
- **Datenbank:** MySQL bei Hostinger, ueber den Dateimanager/Datenbank-Bereich im Hostinger-Dashboard verwaltet.

### Social
- **Instagram/Threads:** Handle **@viceguide**. Details siehe `SOCIAL.md`.
- **Weitere Handles:** @viceguide fuer YouTube, TikTok, X fruehzeitig gesichert.

### Verifizierte GTA-6-Eckdaten (bei neuen Artikeln immer frisch pruefen)
- **Release:** 19. November 2026.
- **Vorbesteller:** seit 25. Juni.
- **Protagonisten:** Jason und Lucia (erstes spielbares Duo der Reihe).
- **Setting:** Bundesstaat Leonida, Vice City (Miami-Analog).
- Bei jedem News-Artikel Aktualitaet ueber Web-Suche verifizieren, GTA-6-Berichterstattung aendert sich schnell.

---

## Für den nächsten Claude: Arbeitsweise mit dem Betreiber

Der Betreiber (Eddy) kommuniziert direkt und iterativ, gibt pro Runde konkretes Feedback. Duzen, deutsch. Ehrlich gegenhalten statt nur zustimmen, wenn etwas fachlich oder rechtlich schiefliegt. Produkt-Vision beachten: internes Tooling bleibt fuer Besucher unsichtbar, Struktur ist an den Spiel-Lebenszyklus gekoppelt, alles bleibt im sauberen Fan-Rahmen.

**Zwei absolute Regeln: keine Gedankenstriche, und jeder Text klingt nach einem echten Redakteur, nicht nach KI (siehe Abschnitt 0).**

**Falls dies ein separater Chat nur fuer Artikel-Erstellung ist:** Du musst kein Git, keine Deploys und kein Code anfassen. Deine Aufgabe ist, auf Zuruf ein Thema zu recherchieren (Web-Suche nutzen, GTA-6-Infos aendern sich schnell) und einen Artikel im oben stehenden JSON-Format zu liefern, fertig zum Copy-Paste in den Admin-Panel-Workflow. Kein Talk drumherum noetig, das JSON in einem Codeblock reicht.
