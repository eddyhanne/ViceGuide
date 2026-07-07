# CLAUDE.md — ViceGuide

> Arbeitsanweisung und Projektgedächtnis für Claude Code. Diese Datei ist so geschrieben, dass ein Claude auf einem anderen Account oder Rechner sofort produktiv weiterarbeiten kann, ohne die bisherige Chat-Historie zu kennen. Alles Wichtige steht hier direkt drin.

---

## 0. Wichtigste Regel zuerst

**Keine Gedankenstriche.** Weder "–" (Halbgeviert) noch "—" (Geviert) dürfen irgendwo auftauchen: nicht im Website-Text, nicht im Code, nicht in Commit-Messages, nicht in den KI-Prompts, die die Seite an die API schickt, nicht in KI-generierten Artikeln. Ersetze sie durch Komma, Doppelpunkt oder Punkt. Normale Bindestriche in zusammengesetzten Wörtern (z. B. "Schritt-für-Schritt", "Money-Methods") sind erlaubt. Diese Regel gilt ausnahmslos für das gesamte Projekt.

---

## 1. Projektzweck, Ziel, Zielgruppe

**ViceGuide** ist ein inoffizielles, deutschsprachiges Fan-Portal rund um **GTA 6 (Grand Theft Auto VI)**. Domain: **viceguide.de**.

**Geschäftsidee:** Ein Content-Hub, der als zweites Standbein Einnahmen generieren soll (Nebeneinkommen, langfristiger Aufbau, kein Vollzeit-Ersatz). Monetarisierung geplant über Display-Ads (AdSense, später Premium-Netzwerke) und Affiliate-Marketing (der Betreiber hat beruflichen Hintergrund in Cost-per-Sale und Amazon-Affiliate, das ist der Wettbewerbsvorteil gegenüber typischen Fanseiten).

**Strategischer Ankerpunkt:** GTA-6-Release am **19. November 2026**. Davor Traffic über News und Leaks aufbauen, danach mit immergrünen In-Game-Guides nachhaltig monetarisieren. News und Leaks bringen Traffic, monetarisieren aber schlecht. Guides nach Release bringen dauerhaften Long-Tail-Umsatz.

**Zwei-Phasen-Konzept:**
- **Phase 1 (vor Release, jetzt live):** News und Updates, Gerüchte und Leaks, Trailer-Analysen, Charaktere und Story, Map und Setting, Community und Infohäppchen.
- **Phase 2 (ab Release-Day, gesperrt bis dahin):** Money Methods, Missionen und Walkthroughs, Fahrzeuge und Fundorte, Waffen, Secrets und Easter Eggs, Online-Modus, Anfänger-Guides.

**Zielgruppe:** Deutschsprachige GTA-6-Fans, die aktuell auf englische Quellen angewiesen sind. USP: Deutschsprachige Einordnung ("Echt oder Fake?") statt blindes Teilen von Leaks, kompakt und ohne Clickbait. Im deutschsprachigen GTA-6-Nischenmarkt gibt es kaum Konkurrenz, das ist eine First-Mover-Position.

**Rechtlicher Rahmen (fest):** Klarer Fan-Disclaimer auf jeder Seite. Keine offiziellen Rockstar-Bilder oder -Logos. Nur eigene, KI-generierte oder lizenzierte Grafiken. Die Vice-City-Farbwelt und Stimmung dürfen nachgebaut werden (Farben und Verläufe sind nicht schützbar), konkrete Rockstar-Artworks (Key-Art, VI-Logo, Charakter-Illustrationen) nicht. Standard-Disclaimer-Text (wörtlich auf der Seite verwendet):

> ViceGuide ist ein inoffizielles, von Fans erstelltes Guide-Portal und steht in keiner Verbindung zu Rockstar Games oder Take-Two Interactive. Alle Marken, Namen und Bezüge gehören ihren jeweiligen Eigentümern. Inhalte vor Release basieren teils auf Leaks und Gerüchten und sind nicht offiziell bestätigt.

---

## 2. Tech-Stack, Architektur, Ordnerstruktur

### Tech-Stack
- **Frontend:** Eine einzige, in sich geschlossene **HTML-Datei** (`viceguide.html`). Kein Framework, kein Build-Bundler. Vanilla HTML, CSS (CSS Custom Properties für Theming), Vanilla JavaScript. Alles inline in der einen Datei.
- **Build:** Ein **Python-Skript** (`build_site.py`) erzeugt die finale `viceguide.html`, indem es Platzhalter im HTML-Template durch Daten ersetzt. Kein npm-Build.
- **Fonts:** Google Fonts, per `<link>` geladen: **Anton** (Display/Headlines), **Outfit** (Fließtext, Gewichte 300 bis 700), **Space Mono** (Labels, Mono-Akzente).
- **KI-Funktion:** Direkter `fetch` auf die Anthropic Messages API (siehe Abschnitt 7). Im Live-Betrieb über ein kleines Backend mit eigenem API-Key vorgesehen.
- **Hosting:** Domain viceguide.de bei **Hostinger** (Verfügbarkeit bestätigt). Als statische Seite deploybar.

### Architektur
Die Seite ist eine Single-Page-Anwendung ohne echtes Routing: mehrere "Ansichten" (Startseite, Artikel-Detail, Datenbank-Detail-Modal, Admin-Overlay) werden per JavaScript-Sichtwechsel ein- und ausgeblendet.

`build_site.py` injiziert Daten in das HTML-Template über String-Platzhalter. Im generierten JS stehen am Anfang des `<script>`-Blocks die Datenobjekte, z. B.:

```javascript
const SECTIONS   = __SECTIONS__;   // Seitenstruktur/Navigation
const DB         = __DB__;         // Datenbank-Einträge (Detail-Modals)
const MAP_POINTS = __MAP__;        // Punkte für die Map/Setting-Ansicht
let   GUIDES     = __GUIDES__;     // Artikel/Guides
```

`build_site.py` ersetzt diese Platzhalter durch echtes JSON aus den Datenquellen und schreibt die fertige `viceguide.html`.

**Hauptbereiche der Seite:**
1. **Startseite / Guides:** Akkordeon-Struktur, zwei Phasen-Gruppen (`GROUPS`: `pre` und `rel`). Pro Kategorie ein aufklappbarer Akkordeon-Block mit animiertem Expand/Collapse. Phase-2-Kategorien sind mit `locked:true` gesperrt dargestellt.
2. **Artikel-Detailseite:** Öffnet den vollen Artikel (Titel, Lead, Content-Absätze, optionale Quellenliste, Inline-Disclaimer).
3. **Datenbank:** Detail-Modals aus `DB` (z. B. Charaktere, Fahrzeuge, Orte).
4. **Map und Setting:** Kartenansicht mit `MAP_POINTS`.
5. **Admin-Panel (intern, versteckt):** Overlay, erreichbar über einen dezenten Footer-Link "⚙ Redaktion". Nur für den Betreiber, für Besucher unsichtbar. Erzeugt KI-Guides (siehe Abschnitt 5 und 7).

**Datenmodell eines Guides/Artikels (JSON):**
```json
{
  "cat": "news",
  "tag": "News",
  "title": "prägnanter Titel, max. 8 Wörter",
  "summary": "1 Satz Teaser, max. 20 Wörter",
  "meta": "kurzes Label, z. B. Analyse oder News",
  "lead": "1 einleitender Satz",
  "content": ["Absatz 1", "Absatz 2", "Absatz 3"],
  "sources": [{ "title": "Quellenname", "url": "https://..." }],
  "ai": true
}
```
`cat` ist die interne Kategorie-ID (Zuordnung zur Akkordeon-Gruppe), `tag` das sichtbare Label. Erlaubte Tags: News, Charaktere und Story, Map, Money Methods, Fahrzeuge, Waffen, Secrets und Easter Eggs, Online, Anfänger.

### Ordnerstruktur (Arbeitsstand aus der Historie, gegen echtes Repo prüfen)
```
/                       Projektwurzel
├─ CLAUDE.md            Diese Datei
├─ build_site.py        Build-Skript: injiziert Daten, schreibt viceguide.html
├─ viceguide.html       Generierte, deploybare Single-File-Seite (Build-Artefakt)
├─ articles.json        Artikel-/Guide-Daten (vom Admin-Panel-Workflow befüllt)
└─ assets/              Bild-Assets
   ├─ img_1.jpg         Palmen-Wallpaper (Hero-Hintergrund)
   └─ logo*.*           Eigene Logo-Dateien (ersetzen die frühere CSS-Sonne)
```
Hinweis: Logo und Palmen-Wallpaper werden als **base64-Data-URIs** direkt in die HTML eingebettet, damit die Seite eine echte Single-File-Auslieferung bleibt. Die Roh-Assets liegen zusätzlich in `assets/`. `articles.json` als separate Datenquelle ergibt sich aus dem Admin-Workflow ("In articles.json einfügen"), der genaue Dateiname sollte gegen das echte Repo verifiziert werden.

---

## 3. Konventionen

### Code-Stil
- **Single-File-Prinzip:** Die ausgelieferte Seite bleibt eine eigenständige HTML-Datei. CSS und JS inline, keine externen lokalen Dateien außer Google Fonts. Assets als base64-Data-URI einbetten.
- **Theming ausschließlich über CSS Custom Properties.** Niemals feste Farbwerte hart in Regeln schreiben, immer die `--variable` nutzen, damit Dark und Light Mode automatisch mitziehen. Dark Mode ist die Basis (`:root`), Light Mode überschreibt (`:root[data-theme="light"]`).
- **Kein `localStorage`/`sessionStorage` in der Claude.ai-Vorschau** (dort gesperrt, führt zu Fehlern). Für die Live-Seite ist die Theme-Persistenz eine Ein-Zeilen-Ergänzung, die dort ergänzt werden kann.
- Vanilla JS, keine Build-Time-Transpilation. Funktionsnamen sprechend (`openAdmin`, `generateGuide`, `renderGrid`, `renderOrganized`, `toggleTheme`).
- **Keine Gedankenstriche** (siehe Abschnitt 0).

### Naming
- Kategorie-IDs klein und knapp: `news`, `leaks`, `trailer`, `story`, `map`, `community`, `money`, `missions`, `vehicles`, `weapons`, `secrets`, `online`, `beginner`.
- Sichtbare Labels deutsch und ausgeschrieben ("Gerüchte und Leaks", "Trailer-Analysen").
- Datenkonstanten in JS in GROSSBUCHSTABEN (`SECTIONS`, `DB`, `MAP_POINTS`, `GUIDES`, `GROUPS`, `CATEGORIES`).

### Branch-, Commit-, Test-Regeln
Aus der Historie ist **kein Git-Workflow, keine Branch-Strategie und keine Test-Suite dokumentiert.** Bis der Betreiber etwas anderes festlegt, gilt als pragmatischer Standard:
- Commit-Messages: kurz, deutsch oder englisch, sachlich, **ohne Gedankenstriche**. Beispiel: `Dark Mode Farbverlauf angepasst` oder `Akkordeon Animation gefixt`.
- Kleine, thematisch getrennte Commits pro Änderung.
- Vor jeder Änderung an `viceguide.html`: prüfen, ob die Änderung eigentlich in `build_site.py` gehört (siehe Stolperfalle in Abschnitt 6).
- Tests: keine automatisierten Tests vorhanden. Manueller Test = Datei im Browser öffnen, beide Themes durchklicken, Akkordeon auf/zu, Admin-Panel öffnen, einen KI-Guide generieren. Vor Auslieferung immer beide Modi und Mobile-Breite prüfen.

---

## 4. Wichtige Befehle

Es gibt keinen npm-basierten Build. Der Build läuft über Python.

**Seite bauen (Platzhalter befüllen, `viceguide.html` erzeugen):**
```bash
python3 build_site.py
```

**Seite lokal ansehen:** `viceguide.html` einfach im Browser öffnen. Alternativ ein simpler lokaler Server:
```bash
python3 -m http.server 8000
# dann http://localhost:8000/viceguide.html
```

**Zeilenzahl / schneller Sanity-Check der generierten Datei:**
```bash
wc -l viceguide.html
```

**Deploy:** Statisches Hosting bei Hostinger. `viceguide.html` (und `assets/`, falls nicht vollständig eingebettet) auf den Webspace laden, als `index.html` bzw. unter viceguide.de bereitstellen. Für die KI-Funktion im Live-Betrieb zusätzlich ein kleines Backend, das den API-Key hält (siehe Abschnitt 7). Ein konkreter automatisierter Deploy-Befehl ist bisher nicht eingerichtet.

---

## 5. Getroffene Entscheidungen und Begründung

- **Single-File-HTML statt Framework.** Begründung: einfach zu hosten, sofort lauffähig, kein Build-Toolchain-Overhead. Der Betreiber baut iterativ mit Claude, eine Datei ist dafür am handlichsten.
- **Zwei-Phasen-Struktur (pre / rel), Phase 2 gesperrt.** Begründung: an den Spiel-Lebenszyklus gekoppelt. Vor Release Hype und SEO aufbauen, zum Release-Day die echten Guides freischalten und den Traffic-Peak abgreifen.
- **Dual-Theme mit Umschalter.** Dark Mode im Rockstar-VI-Stil (dunkles Violett zu Magenta zu Koralle, Creme-Überschriften, Hot-Pink-Buttons), Light Mode "Miami by Day" (warme Creme-/Sand-Palette). Begründung: VI-Wiedererkennung plus heller Miami-Vibe, ohne geschützte Assets. Der **Light Mode wurde als Standard** gesetzt (ursprünglich war Dark der Default). Der Toggle zeigt jeweils das Zielmodus-Icon.
- **Eigene Grafik statt Rockstar-Material.** Palmen-Silhouetten als selbst gezeichnetes SVG, später ergänzt/ersetzt durch das eigene Palmen-Wallpaper und die eigenen Logo-Dateien des Betreibers (rechtlich sauber, weil eigenes Material). Rockstar-Key-Art wurde bewusst nicht eingebunden.
- **Logo statt CSS-Sonne.** Die frühere gezeichnete Retro-Sonne wurde durch die echten Logo-Dateien ersetzt. Der Logo-Schatten wurde für die Light-Mode-Lesbarkeit auf ein dunkles, fast violettes Grau umgefärbt.
- **Akkordeon statt flachem Karten-Raster.** Begründung: bessere Übersicht bei vielen Kategorien, animiertes Auf-/Zuklappen wirkt hochwertiger.
- **Dezente rosa Akzentlinie beim Hover** statt der früheren Regenbogen-Gradient-Balken. Ruhigerer, edlerer Look.
- **Zeitstempel** auf Kategorie-Headern und pro Artikel (zeigt Datum/Uhrzeit des neuesten Inhalts), wirkt aktuell und gepflegt.
- **Verstecktes Admin-Panel statt sichtbarem Editor.** Begründung: Besucher sollen nur fertige Inhalte sehen, nie das interne Tooling. Der KI-Generator ist der interne Redaktions-Workflow des Betreibers.
- **KI-On-Demand statt Voll-Automatik.** Eine reine HTML-Seite kann nicht selbstständig im Hintergrund das Web durchsuchen und posten (dafür bräuchte es Server plus Scheduler). Entschieden wurde die On-Demand-Variante: Button drücken, Claude recherchiert live und liefert einen fertigen Guide als JSON, das der Betreiber in die Datenquelle übernimmt. Voll-Automatik (Backend plus täglicher Auto-Post) bleibt als spätere Ausbaustufe offen.
- **SEO von Anfang an.** `<title>`, Meta-Description, Keywords, Canonical, Open Graph, Twitter Cards und JSON-LD (Schema.org `WebSite` mit SearchAction und `Organization`) sind eingebaut. Sprache `de-DE`.
- **Content-Reihenfolge:** Vor Launch Grundstock von 20 bis 30 geprüften Artikeln aufbauen, damit die Seite lebendig wirkt und Google sie ernst nimmt. Danach laufend nachlegen, besonders bei jeder News. Zum Release die gesperrten Kategorien freischalten und echte In-Game-Guides produzieren.

---

## 6. Aktueller Stand, offene Aufgaben, Stolperfallen

### Aktueller Stand
- Kern-Architektur steht: Single-File-Seite mit Dual-Theme, Akkordeon-Struktur, Zwei-Phasen-Kategorien, Datenbank-Modals, Map-Ansicht, verstecktes Admin-Panel mit KI-Generator, SEO-Grundausstattung.
- Eigene Logo-Dateien und Palmen-Wallpaper sind als base64 eingebettet.
- Light Mode ist Standard-Theme.
- Die Seite läuft in der Claude.ai-Vorschau inklusive live funktionierendem KI-Button (ohne eigenen API-Key, über die Vorschau-Umgebung).

### Offene Aufgaben
1. Domain viceguide.de bei Hostinger final sichern und die Seite live deployen.
2. Speicher-/Backend-Frage klären: einfache statische Auslieferung mit manuell gepflegtem `articles.json` versus kleines Backend, das den Anthropic-API-Key hält und generierte Guides dauerhaft speichert.
3. Grundstock von 20 bis 30 Artikeln erzeugen und gegenlesen (vor Launch).
4. Google Search Console einrichten, Sitemap einreichen.
5. Echtes OG-Image (`og-image.jpg`) erstellen und unter viceguide.de bereitstellen (aktuell nur referenziert).
6. Theme-Persistenz für die Live-Seite ergänzen (Ein-Zeilen-`localStorage`-Logik, in der Vorschau bewusst weggelassen).
7. Social-Kanal(e) bespielen (Instagram bereits aktiv, siehe Abschnitt 7), Website-Link erst nach offiziellem Launch in die Bio.
8. Impressum und Kontaktangaben ergänzen (erst zum offiziellen Launch, DE-Pflicht beachten).
9. Rechtliche Absicherung im Blick behalten, je kommerzieller die Seite wird: Das an die Marke angelehnte "VI" im Logo und der Wortstamm "Vice" (Nähe zu VICE Media und zu Rockstars "Vice City") sind Grauzonen. DPMA und EUIPO auf "Vice"/"ViceGuide" prüfen, wenn es ernst wird.

### Stolperfallen
- **Nie direkt an der generierten `viceguide.html` editieren, wenn die Änderung eigentlich ins Template gehört.** Der Build läuft über `build_site.py`, das per String-Replace arbeitet. Änderungen an der Ausgabe werden beim nächsten Build überschrieben. Erst prüfen: Datenänderung (dann Datenquelle/`articles.json`) oder Struktur-/Design-Änderung (dann `build_site.py`)?
- **`build_site.py` nutzt exakte String-Ersetzungen.** Wenn du eine Codezeile im Template umformulierst, brechen `str.replace`-Aufrufe, die auf den alten Wortlaut zielen, stillschweigend. Nach Änderungen immer neu bauen und im Browser gegenchecken.
- **Kein Browser-Storage in der Vorschau.** `localStorage`/`sessionStorage` schlagen in der Claude.ai-Vorschau fehl. Theme-Persistenz nur für die Live-Umgebung einbauen, nicht in der Vorschau testen.
- **Gedankenstriche schleichen sich leicht ein**, besonders in KI-generierten Artikeln und in Prompts. Nach jeder KI-Generierung prüfen. Der Prompt an die API sollte "keine Gedankenstriche" explizit fordern.
- **KI-Key nie ins Frontend.** Der API-Key darf nicht öffentlich in der ausgelieferten HTML stehen. Live läuft der Aufruf über ein Backend.
- **Rockstar-Assets sind tabu.** Nur eigenes, KI-generiertes oder lizenziertes Material. Farbwelt nachbauen ist okay, Original-Artwork nicht.

---

## 7. Externe Anbindungen

### Anthropic Messages API (KI-Guide-Generator)
Der Admin-Button `generateGuide()` ruft direkt die Anthropic API auf:
- **Endpoint:** `https://api.anthropic.com/v1/messages`
- **Modell:** `claude-sonnet-4-6`
- **max_tokens:** 1500
- **Tools:** Web Search, Typ `web_search_20250305`, Name `web_search`
- **In der Claude.ai-Vorschau:** funktioniert ohne mitgelieferten Key (die Umgebung stellt die Auth bereit, es wird bewusst kein Key im Code übergeben).
- **Auf der Live-Seite (viceguide.de):** Dieser `fetch` muss gegen ein eigenes Backend laufen, das den API-Key serverseitig hält und die generierten Guides dauerhaft speichert. So bleibt der Key geheim und Artikel überstehen einen Reload.

**Der Prompt** (deutsch) weist Claude an: einen deutschsprachigen Fan-Guide-Artikel für ViceGuide zu recherchieren, Gerüchte und Leaks klar als unbestätigt zu kennzeichnen und **ausschließlich ein JSON-Objekt** zurückzugeben (kein Markdown, keine Backticks, kein Text davor oder danach) im Guide-Datenmodell aus Abschnitt 2. Die Antwort wird geparst: Text-Blöcke filtern, ` ```json `-Fences entfernen, vom ersten `{` bis zum letzten `}` schneiden, `JSON.parse`. Ungültige `tag`-Werte werden auf "News" gesetzt.

**Response-Handling:** Nur Blöcke mit `type === "text"` einsammeln und joinen. Bei Fehlern Toast anzeigen und Button zurücksetzen.

**Admin-Workflow (Redaktion):** Footer-Link "⚙ Redaktion" öffnet das Overlay. Thema eingeben, "Recherchieren und erstellen". In einer Variante wird der Guide sofort in `GUIDES` eingefügt und angezeigt, in einer anderen wird das JSON zum Kopieren ausgegeben ("In articles.json einfügen") und dann in die Datenquelle übernommen. Besucher sehen von all dem nichts.

### Fonts
Google Fonts per `<link>`: Anton, Outfit (300 bis 700), Space Mono. Preconnect auf `fonts.googleapis.com` und `fonts.gstatic.com`.

### Domain und Hosting
- **Domain:** viceguide.de, Verfügbarkeit bei **Hostinger** bestätigt.
- **Kanonische URL:** `https://viceguide.de/`

### Social
- **Instagram/Threads:** Handle **@viceguide** gesichert (der Handle gilt für Instagram und Threads geteilt). Als Professional-/Creator-Konto eingerichtet, Insights aktiv. Marken-Hashtag **#viceguide** auf jedem Post. Website-Link kommt erst nach offiziellem Launch in die Bio. Content-Strategie: eigenständige Teaser-Posts (Karussells, Reels), die für sich Wert liefern, mit Verweis auf die Seite als Vertiefung. Kadenz 3 bis 4 Posts pro Woche, wiederkehrende Countdown-Posts. Geplante Story-Highlights: Trailer, Map, Charaktere, Leaks, FAQ.
- **Weitere Handles:** @viceguide für YouTube, TikTok und X frühzeitig sichern, um Namensklau zu verhindern (Bespielung später).

### Verifizierte GTA-6-Eckdaten (Stand der letzten Recherche, bei neuen Artikeln immer frisch prüfen)
- **Release:** 19. November 2026.
- **Vorbesteller:** seit 25. Juni.
- **Protagonisten:** Jason und Lucia (erstes spielbares Duo der Reihe, Bonnie-und-Clyde-Dynamik).
- **Setting:** Bundesstaat Leonida, Vice City (Miami-Analog).
- **Trailer 3:** wurde erwartet, bei jedem News-Artikel Aktualität über Web-Suche verifizieren.

---

## Für den nächsten Claude: Arbeitsweise mit dem Betreiber

Der Betreiber (Eddy) kommuniziert direkt und iterativ, gibt pro Runde konkretes visuelles Feedback plus geschäftliche Begründung. Duzen, deutsch. Ehrlich gegenhalten statt nur zustimmen, wenn etwas fachlich oder rechtlich schiefliegt (z. B. bei Urheberrecht oder unrealistischen Technik-Versprechen). Produkt-Vision beachten: internes Tooling bleibt für Besucher unsichtbar, Struktur ist an den Spiel-Lebenszyklus gekoppelt, alles bleibt im sauberen Fan-Rahmen. Und: **keine Gedankenstriche.**
