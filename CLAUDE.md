# CLAUDE.md: ViceGuide

> Arbeitsanweisung und Projektgedächtnis für Claude. So geschrieben, dass ein Claude auf einem anderen Account, Rechner oder in einem separaten Chat sofort produktiv weiterarbeiten kann, ohne die bisherige Historie zu kennen. Alles Wichtige steht hier direkt drin.
>
> **Dokumentstruktur des Projekts:**
> - **CLAUDE.md** (diese Datei): Website-Technik plus die projektweit verbindlichen Grundlagen (Marke, Ton, Faktenblatt).
> - **SOCIAL.md:** Instagram-Strategie, Algorithmus-Wissen, Captions, Bio, Hashtags.
> - **TOOLS.md:** Karussell-Standard und Artikelbild-System (der ViceGuide-Generator folgt dort, sobald er fertig ist).
> - **KANAELE.md:** Übersicht aller externen Kanäle (Instagram, Discord, Reddit, YouTube, TikTok, X) mit Status und Priorität.
>
> **Eine Wahrheit pro Thema, an einem Ort.** Marke, Ton und das GTA-6-Faktenblatt sind hier kanonisch und gelten für Website UND Social. Die anderen Dateien verweisen darauf, statt es zu doppeln. Bei Konflikt gewinnt CLAUDE.md. So läuft nichts mehr still auseinander.

---

## 0. Wichtigste Regeln zuerst

**Regel 1, keine Gedankenstriche.** Weder "–" (Halbgeviert) noch "—" (Geviert) dürfen irgendwo auftauchen: nicht im Website-Text, nicht im Code, nicht in Commit-Messages, nicht in KI-Prompts, nicht in generierten Artikeln. Ersetze sie durch Komma, Doppelpunkt oder Punkt. Normale Bindestriche in zusammengesetzten Wörtern (z. B. "Schritt-für-Schritt", "Money-Methods") sind erlaubt. Gilt ausnahmslos fürs gesamte Projekt.

**Regel 2, Ton eines Gaming-Redakteurs, nicht generisch KI-klingend.** Jeder Artikel, jede Beschreibung, jeder Text soll klingen, als hätte ihn ein echter Gaming-Redakteur geschrieben: jemand, der selbst zockt, die Materie kennt, weiß was Gamer wirklich wissen wollen und wie man sie packt und dranhält. Nicht "Redakteur allgemein", sondern ein Gaming-Journalist, von dessen Beitrag ein professioneller Chefredakteur den Text nicht unterscheiden könnte. Konkret:
- Keine leeren Füllfloskeln ("Es ist wichtig zu beachten, dass...", "Zusammenfassend lässt sich sagen...", "Tauche ein in die Welt von...").
- Keine übertriebene Marketing-Sprache oder Ausrufezeichen-Enthusiasmus. Sachlich-locker statt reißerisch.
- Konkrete Fakten und Details statt vager Verallgemeinerungen. **Steht eine Quelle im `sources`-Feld, wird sie im Text namentlich genannt** ("laut Forbes", "wie ComicBook.com berichtet", "Analyst Mat Piscatella verweist darauf"), nie über eine anonyme Sammelformel wie "Beobachter werten", "mehrere Quellen" oder "Berichten zufolge".
- Ein Artikel darf eine eigene Position und Einordnung haben ("wirkt eher wie ein Gerücht, weil..."), das ist der USP gegenüber reinem Leak-Kopieren.
- Gilt für alle Texte: Artikel, Teaser, Datenbank-Beschreibungen, Meta-Texte, Captions.

**Satzrhythmus, der häufigste KI-Verräter (besonders beachten).** Der typische KI-Fehler ist der Drei-Teile-Komma-Satz: Hauptaussage, Komma, Zusatz, Komma, noch ein Zusatz, und das zehnmal hintereinander. Ein einzelner Satz dieser Art liest sich okay, in Serie erzeugt er genau den Sing-Sang, an dem man KI-Text sofort erkennt. Gegenmittel: Satzlängen bewusst mischen. Ein kurzer Satz, dann ein längerer. Wo drei Gedanken zusammengehören, lieber einen Punkt setzen als ein drittes Komma. **Wichtig, weil hausgemacht:** Regel 1 verbietet Gedankenstriche, das darf nicht dazu führen, dass stattdessen alles mit Kommas aneinandergehängt wird. Wo ein Gedankenstrich stünde, gehört meistens ein Punkt hin, kein Komma.

**Verbotene Floskeln (harte Liste, nie verwenden):**
- Meta-Ansagen über den Text: "Wichtig für die Einordnung", "Zur Einordnung", "Wichtig dabei", "Eins vorweg", "So viel sei gesagt", "Für Spieler heißt das konkret".
- Aufsatz-Klammern: "Zusammenfassend lässt sich sagen", "Kurz gesagt", "Unser Fazit", "Alles in allem", "Es ist wichtig zu beachten, dass", "Es ist erwähnenswert, dass", "Ein Blick auf X hilft bei der Einordnung".
- Leerlauf-Hedges: "Es bleibt abzuwarten", "Man darf gespannt sein", "Nur die Zeit wird es zeigen", "Wie sich zeigen wird".
- Marketing-Enthusiasmus: "Tauche ein in", "Mach dich bereit für", "Freu dich auf".
- Vage Sammel-Quellen: "Beobachter werten", "mehrere Quellen", "Berichten zufolge", "in der Berichterstattung gilt". Stattdessen die konkrete Quelle namentlich nennen (siehe oben).
- Füll-Übergänge: "Doch damit nicht genug", "Und das ist noch nicht alles", "Aber was bedeutet das eigentlich?".
- Klischee-Eröffnungen: "Kaum ein Spiel wird so sehnlich erwartet", "so sehnlich erwartet", "Fans auf der ganzen Welt".
- Rhetorische Leserfragen als Absatzabschluss: "Was denkt ihr?", "Freut ihr euch?", "Was meint ihr?", "Seid ihr gespannt?". Das ist Fan-Blog-Stil, kein Redaktionston.
- Weichmacher und Hype-Adjektive ohne Beleg (sparsam, nie als Autoren-Wertung im Leerlauf): "quasi", "regelrecht", "gefühlt", "im wahrsten Sinne", "atemberaubend", "wunderschön", "gigantisch". Ein Adjektiv, das man nicht belegen kann, gehört raus.
- Konjunktiv-Spekulationsteppiche: nicht mehrere "könnte/dürfte/würde/möglicherweise" in Folge stapeln. Einmal sauber kennzeichnen, dann Position beziehen.

**Prinzip:** nichts ankündigen, was gleich kommt, sondern es direkt sagen. Nicht ins Vage hedgen, sondern Fakten nennen und Position beziehen. Führe mit der bestätigten News, nicht mit der Spekulation: steht eine belegte Tatsache im Raum, gehört die nach vorne, das Gerücht ist der Nebenaspekt.

**Vorher/Nachher (aus echten Testartikeln, alle vier Fehlertypen):**
- Meta-Ansage. Schwach: "Wichtig dabei, das ist reine Fan-Spekulation." Stark: "Das ist reine Fan-Spekulation, bestätigt ist daran nichts."
- Aufsatz-Klammer. Schwach: "Kurz gesagt, ein Termin im Sommer ist plausibel." Stark: "Ein Termin im Sommer ist plausibel, das WM-Finale als Bühne eher nicht."
- Vage Quelle. Schwach: "Beobachter werten das als möglichen Hinweis." Stark: "Forbes-Autor Brian Mazique wertet das als möglichen Hinweis."
- Komma-Kette. Schwach: "Take-Two präsentiert am 7. August die Zahlen, an einem Freitag statt wie sonst zwischen Dienstag und Donnerstag, was Beobachter als Hinweis werten." Stark: "Take-Two präsentiert am 7. August die Zahlen. Der Termin fällt auf einen Freitag, ungewöhnlich für einen Investorencall."

Diese beiden Regeln sind projektweit und stehen über allem anderen.

---

## 1. Projektzweck, Marke, Zielgruppe (verbindlich für alle Kanäle)

**ViceGuide** ist ein inoffizielles, deutschsprachiges Fan-Portal rund um **GTA 6 (Grand Theft Auto VI)**. Domain: **viceguide.de**, live und erreichbar.

### Positionierung (dauerhaft)
ViceGuide ist die **deutschsprachige GTA-6-Zentrale**, ein komplettes Guide-Portal, das mit dem Spiel mitwächst. Das ist die dauerhafte Identität, nicht die aktuelle Leak-Phase.

- **Phase 1 (vor Release, jetzt):** News und Updates, Gerüchte und Leaks, Trailer-Analysen, Charaktere und Story, Map und Setting, Community und Infohäppchen.
- **Phase 2 (ab Release-Day):** täglich frische Guides quer durch alle Bereiche: Online-Modus, Geld verdienen und Money-Methods, Missionen und Walkthroughs, Tipps und Tricks, Secrets und Easter Eggs, Sammelobjekte und Fundorte, Fahrzeuge und Tuning, Waffen und Ausrüstung, Trophäen und Erfolge, Immobilien und Business, Charakter und Anpassung, Anfänger-Guides.

**Wichtig für die Außenwahrnehmung, von Anfang an:** Es muss erkennbar sein, dass ViceGuide mehr ist als Trailer-Analysen. Besucher sollen die Seite als dauerhafte Anlaufstelle merken und folgen, nicht als Pre-Release-Leak-Kanal, der nach dem Launch verwaist. Die aktuelle Leak-Phase ist der Einstieg, nicht die Marke.

### USP, phasenabhängig
- **Jetzt (Phase 1):** Einordnung statt blindes Teilen. "Echt oder Fake?" als Hook, solange das Spiel unveröffentlicht ist. Kein Clickbait, keine Fake-Leaks.
- **Ab Release (Phase 2):** die beste deutschsprachige Guide-Anlaufstelle, täglich frisch, während der Rest auf englische Quellen angewiesen ist. Da liegt der eigentliche, dauerhafte Wert. Im deutschsprachigen GTA-6-Nischenmarkt gibt es kaum Konkurrenz, das ist die First-Mover-Position.

### Ziel und Zielgruppe
- **Nordstern:** viceguide.de maximal sichtbar machen und Traffic auf die Seite bringen. Der mit Abstand stärkste Hebel dafür ist **deutsche Google-SEO**, nicht Social (siehe KANAELE.md für die ehrliche Kanal-Rangfolge). Instagram ist Marke und Community, kein Traffic-Motor.
- **Zielgruppe:** deutschsprachige GTA-6-Fans, die aktuell auf englische Quellen angewiesen sind.
- **Geschäftsidee:** Content-Hub als zweites Standbein (Nebeneinkommen, langfristiger Aufbau). Monetarisierung geplant über Display-Ads (AdSense, später Premium-Netzwerke) und Affiliate-Marketing (der Betreiber hat beruflichen Hintergrund in Cost-per-Sale und Amazon-Affiliate, das ist der Wettbewerbsvorteil). **Monetarisierung ist bewusst pausiert, bis echter, messbarer Traffic läuft.** Erst Reichweite, dann Umsatz.
- **Strategischer Ankerpunkt:** Release am **19. November 2026** (Details im Faktenblatt, Abschnitt 8). Davor Traffic über News und Leaks aufbauen, danach mit immergrünen Guides nachhaltig monetarisieren. News bringen Traffic, monetarisieren schlecht. Guides nach Release bringen dauerhaften Long-Tail-Umsatz.

### Rechtlicher Rahmen (fest)
Klarer Fan-Disclaimer auf jeder Seite. Keine offiziellen Rockstar-Bilder oder -Logos. Nur eigene, KI-generierte oder lizenzierte Grafiken. Die Vice-City-Farbwelt und Stimmung dürfen nachgebaut werden (Farben und Verläufe sind nicht schützbar), konkrete Rockstar-Artworks (Key-Art, VI-Logo, Charakter-Illustrationen) nicht. Standard-Disclaimer-Text (wörtlich auf der Seite):

> ViceGuide ist ein inoffizielles, von Fans erstelltes Guide-Portal und steht in keiner Verbindung zu Rockstar Games oder Take-Two Interactive. Alle Marken, Namen und Bezüge gehören ihren jeweiligen Eigentümern. Inhalte vor Release basieren teils auf Leaks und Gerüchten und sind nicht offiziell bestätigt.

Impressum und Datenschutzerklärung sind mit echten Betreiberdaten befüllt (Eddy Hanné, Privatperson, kein Gewerbe, Stand dieser Datei). Sobald echte Werbeeinnahmen mit nachhaltiger Gewinnerzielungsabsicht fließen, wird in der Regel eine Gewerbeanmeldung nötig, das rechtzeitig prüfen. Das "VI"-Element im Logo und der Wortstamm "Vice" (Nähe zu VICE Media und zu Rockstars "Vice City") sind Markenrecht-Grauzonen, je kommerzieller es wird, desto genauer im Blick behalten (DPMA/EUIPO). Bislang nicht geprüft.

---

## 2. Tech-Stack, Architektur, Ordnerstruktur

### Tech-Stack
- **Frontend:** eine einzige, in sich geschlossene **HTML-Datei** (`index.html`, kein Build-Skript, direkt editieren). Kein Framework, kein Bundler. Vanilla HTML, CSS (Custom Properties für Theming), Vanilla JavaScript, alles inline.
- **Backend:** kleine **PHP-Skripte** im Ordner `api/`, laufen direkt auf dem Hostinger-Webspace, sprechen mit einer **MySQL-Datenbank** bei Hostinger. Siehe Abschnitt 7.
- **Fonts:** **selbst gehostet** (DSGVO-Grund, keine Google-Verbindung). **Oswald, Inter, Space Mono**, als `.woff2` unter `assets/fonts/`, per `@font-face` eingebunden. Oswald und Inter sind Variable Fonts (je eine Datei), Space Mono liegt statisch als 400/700 vor. (Hinweis: das sind die Website-Fonts. Das Instagram-Karussell nutzt andere Fonts, siehe TOOLS.md, nicht verwechseln.)
- **Hosting:** Domain viceguide.de bei **Hostinger** (Premium Web Hosting), per Git mit dem GitHub-Repo `eddyhanne/ViceGuide` verbunden (Root ist `public_html`). Auto-Deploy aktiv, Push auf `main` zieht sich automatisch auf den Server.

### Architektur
Die Seite nutzt **ausschließlich echte URLs**, kein Hash-Routing mehr für irgendeinen Bereich (Stand dieser Datei, zuletzt komplett durchgezogen). Übersicht aller Praefixe:

| Bereich | URL-Muster | Serverseitige Fassade |
|---|---|---|
| Artikel-Detail | `/artikel/<id>` | `article.php` |
| Datenbank-Eintrag | `/charaktere/<slug>`, `/fahrzeuge/<slug>`, `/waffen/<slug>`, `/wildtiere/<slug>`, `/gangs/<slug>`, `/radio/<slug>`, `/aktivitaeten/<slug>`, `/orte/<slug>` | `entry.php` |
| Datenbank-Kategorie (Liste) | `/charaktere/`, `/fahrzeuge/`, `/waffen/`, `/wildtiere/`, `/gangs/`, `/radio/`, `/aktivitaeten/`, `/orte/` | `category.php` |
| Videos, Community, Karte | `/videos`, `/community`, `/karte` | `section.php` |
| Impressum, Datenschutz | `/impressum`, `/datenschutz` | `legal.php` |
| Startseite | `/` | `index.html` direkt |

Folgendes ist gegen den echten Code bestätigt:

- **`.htaccess`** leitet jedes der obigen Muster intern an die passende PHP-Datei weiter, dazu `/sitemap.xml` an `sitemap.php`. Die sichtbare Adresse bleibt dabei unverändert (internes Rewrite, kein Redirect). `.htaccess` setzt außerdem lange Cache-Zeiten (`mod_expires`/`mod_headers`, in `<IfModule>` gekapselt) für `.woff2`-Fonts (1 Jahr) und `.jpg`/`.jpeg`/`.png` (1 Monat), `index.html` bleibt davon unberührt.
- **`article.php`** liest den Artikel per PDO direkt aus der `articles`-Tabelle, lädt `index.html` als String und ersetzt per gezieltem `str_replace()` nur die `<head>`-Metadaten (Title, Description, og:*, twitter:*, canonical, `Article`-JSON-LD inkl. echter Bildmaße/Mime-Type) sowie den sichtbaren Artikelbereich durch eine vereinfachte Text-Fassung (Zwischenüberschriften, Absätze, FAQ als Frage/Antwort, `[[id|text]]` nur als Klartext, keine Bildergalerie, kein Akkordeon). Kein zweites vollständiges Rendering, Googlebot führt JavaScript zuverlässig aus, für normale Besucher übernimmt `openArticle()` beim Laden sofort und rendert komplett nach.
- **`entry.php`** ist das Pendant für Datenbank-Einträge (Abfrage per `section` + `slug`), liefert Name als `<h1>`, Unterzeile, Kategorie/Quelle-Chip, Felder-Liste, Beschreibung als Absätze, dazu `Thing`-JSON-LD. `openModal()` übernimmt beim Laden sofort und rendert vollständig nach.
- **`category.php`** rendert die Listenebene über den Einzel-Einträgen (z. B. alle Charaktere unter `/charaktere/`), die vorher fehlte: echte Links zu jedem Eintrag der Sektion plus `CollectionPage`/`ItemList`-JSON-LD. Ohne diese Ebene hatte Google keinen indexierbaren Einstieg in eine Kategorie, nur die Einzelseiten.
- **`section.php`** deckt die drei Sektionen ohne Datenbank-Backing ab (Videos, Community, Karte). Der Inhalt kommt aus den bestehenden JS-Konstanten `VIDEOS`/`COMMUNITY` in `index.html` (per Regex ausgelesen, keine zweite Pflegestelle), Karte hat mangels eigener Datenbank nur einen Verweis auf `/orte/`. Die interne section-id `map` bekommt hier ihr deutsches URL-Präfix `karte` über eine kleine Zuordnungstabelle (`VG_SECTION_URL_PREFIX`), bei `videos`/`community` ist deutsches Wort und id zufällig identisch.
- **`legal.php`** liefert Impressum/Datenschutz unter eigener URL aus, liest den Text aber weiterhin einzig aus dem `LEGAL`-Objekt in `index.html` (per Regex extrahiert), keine zweite Pflegestelle.
- **Alle serverseitigen Fassaden liefern `http_response_code(404)`** für unbekannte Slugs/Sektionen (mit weiterhin freundlichem Inhalt für Besucher, kein Soft-404 für Suchmaschinen).
- **`api/article_image.php?id={id}`** und **`api/entry_image.php?id={interne_id}`** liefern das jeweilige Bild (in der Datenbank nur als base64-Data-URI gespeichert) als echte, abrufbare Bild-URL aus, nötig weil `og:image` kein `data:`-URI sein kann. Beide setzen `Cache-Control: public, max-age=86400`. Die von `api/articles.php`/`api/db_entries.php` ausgelieferte Bild-URL hängt zusätzlich `&v=<updated_at>` als Cache-Buster an, damit ein neu veröffentlichtes Bild nicht bis zu 24 Stunden im Browser-Cache hängen bleibt.
- **Logo einmalig statt doppelt:** Header- und Hero-Logo referenzieren beide `logo.png` als echte Datei, nicht mehr zwei identische eingebettete Base64-Kopien (waren zusammen über 800 KB reiner HTML-Text).
- **Client-seitig:** `articleHref(id)` und `entryHref(secId, slug)` liefern die echte Pfad-URL fürs `href`-Attribut, `articleClick()`/`entryClick()`/`legalClick()` sind die gemeinsamen Klick-Handler auf allen Artikel-/Datenbank-Kacheln und internen Verweisen (normaler Linksklick: `preventDefault()` plus In-App-Navigation ohne Reload; Strg/Cmd/Shift-Klick und Mittelklick: normaler neuer Tab, da echte `<a href>`-Links). `SECTION_PREFIX`/`SECTION_PREFIX_REV` mappen zwischen interner section-id und deutschem URL-Präfix (nur für die acht Datenbank-Sektionen, `map`→`karte` läuft separat). `restoreFromLocation()` prüft nacheinander Artikel-Pfad, Eintrags-Pfad, Kategorie-Hub-Pfad, Impressum/Datenschutz, Videos/Community/Karte, bevor sie auf das alte Hash-Schema zurückfällt (praktisch nur noch als Fallback relevant). `syncHash()` schreibt umgekehrt bei jeder Navigation die passende echte URL in die Adresszeile, auch beim reinen Sektionswechsel ohne offenen Artikel/Eintrag (z. B. Sidebar-Klick auf "Charaktere" → `/charaktere/`, Klick auf "News" → `/`).
- **`<base href="/">`** ist als erstes Element im `<head>` gesetzt, damit alle relativen Pfade (Fonts, `fetch('api/...')`) unabhängig vom aufgerufenen Pfad korrekt zur Domainwurzel auflösen. Ohne das würden Fonts und API-Calls unter einer Artikel-/Eintrags-URL fehlerhaft relativ zu deren Pfad statt zur Wurzel aufgelöst.
- **Admin-Panel und Bild-Editor sind nicht mehr als statisches HTML im Quelltext.** Beide Overlays (`#adm`, `#imged`) sind leere Container, ihr Inhalt liegt als JS-Template-String (`ADM_HTML`, `IMGED_HTML`) und wird erst beim ersten echten Öffnen injiziert (`openAdmin()`/`openImgEd()`), analog zum bestehenden `LEGAL`/`openLegal()`-Muster. Grund: vorher las Google die Redaktionswerkzeug-Texte ("Claude-Entwurf veröffentlichen" etc.) auf jeder ausgelieferten Seite mit, obwohl sie für Besucher nie sichtbar wurden.

Die interaktive Navigation innerhalb der Seite läuft weiter über die Single-Page-Logik in `index.html`, die serverseitigen Seiten sind reine SEO-/Vorschau-Fassaden für Crawler und Link-Vorschauen ohne JavaScript.

**Datenfluss (wichtig):** Die **MySQL-Datenbank ist die Quelle der Wahrheit.** `loadExternal()` fragt zuerst `api/articles.php` und `api/db_entries.php` ab und fällt nur auf die statischen JSON-Dateien zurück, falls die API nicht erreichbar ist (Notfall-Absicherung, kein aktiver Sync). `articles.json`/`database.json` im Repo sind nur ein eingefrorener Migrationsstand, keine gepflegte Datenquelle mehr.

**Entwurf/Veröffentlichen-Modell:** Änderungen im Editiermodus (`ieApply()`) landen zunächst in einer `draft_json`-Spalte auf `articles`/`db_entries`, nicht direkt in den öffentlichen Feldern. Nur der eingeloggte Admin sieht seinen eigenen Entwurf (GET merged `draft_json` über die Live-Werte, Antwort trägt `_draft:true`), Besucher sehen weiterhin den zuletzt veröffentlichten Stand. Ein Klick auf "Fertigstellen" ruft `POST ?action=publish` auf (schreibt alle offenen Entwürfe in die echten Spalten, löscht danach `draft_json`), "Verwerfen" ruft `POST ?action=discard` auf (löscht `draft_json` ersatzlos). Siehe Abschnitt 7 für die Endpunkt-Details.

**DB-Schema-Migration nur bei Bedarf:** `vg_db()` prüfte früher bei jedem einzelnen Request per `CREATE TABLE`/`ALTER TABLE`, ob das Schema passt. Jetzt läuft zuerst ein leichter Probe-Query (`SELECT ... LIMIT 1` auf die kritischen Spalten), die teure Migration nur noch, wenn der fehlschlägt (frisches Deployment oder ausstehendes Upgrade). Per Instrumentierung bestätigt: läuft nur beim allerersten Aufruf nach einer Schema-Änderung.

**Hauptbereiche (`SECTIONS`):** News und Gerüchte (`home`, Typ `guides`), Charaktere, Fahrzeuge, Waffen, Wildtiere, Gangs, Radio, Aktivitäten, Orte (alle Typ `db`), Karte (`map`), Videos (`videos`), Community (`community`). Phase-2-Kategorien sind gesperrt dargestellt.

Artikel-Detailseiten haben Titel, Lead, Content-Absätze, optionale Quellen, Kommentare, Inline-Disclaimer. Charakternamen im Text werden automatisch zu Links auf ihr Detail (`linkifyChars()`). Kommentare liegen dauerhaft in der Datenbank, mit einer Antwortebene, Upvote/Downvote (eine Stimme pro Kommentar pro Wähler, **serverseitig erzwungen** über die Tabelle `comment_votes` mit `UNIQUE(comment_id, voter)`), Löschen nur im Editiermodus mit Admin-Login. Der "Wähler" ist ein anonymes, dauerhaftes Browser-Token (`localStorage` `vg-voter`), das bei jeder Stimme mitgeschickt wird, fehlt es (direkter API-Aufruf), fällt der Server auf einen IP-Hash zurück. Das lokale `vg-voted` sperrt zusätzlich sofort die Buttons, ist aber nur UI-Komfort, die maßgebliche Grenze zieht der Server. (Früher zählte `comments.php` jede Stimme ungeprüft hoch, der localStorage-Check allein war durch Speicher-Leeren, anderen Browser oder curl beliebig umgehbar.)

**Admin-Panel (intern, versteckt):** Overlay, erreichbar über `Shift+Alt+R`, die URL `#redaktion`/`#admin`, oder dreifachen Klick auf einen versteckten Footer-Bereich (`foot-secret`). Zwei klar getrennte Import-Karten oben: **Artikel** (`submitDraftJson()`, Artikel-JSON mit `title`/`content`) und **Datenbank-Einträge** (`submitDbImport()`). Darunter Editiermodus, JSON-Sicherungsexport und ein Wartungsknopf. Editiermodus fragt beim ersten Einschalten pro Browser das Admin-Passwort ab (serverseitig geprüft, Session 90 Tage), danach öffnet Kachel-Klick den Bild- und Text-Editor (`openImgEd()`), Speichern (`ieApply()`) schreibt in den Entwurf (siehe oben), "Fertigstellen" veröffentlicht dauerhaft in die Datenbank.

**Datenbank-Eintrag-Import per JSON (`submitDbImport()`).** Nimmt ein Objekt oder eine Liste, jeder Eintrag braucht ein Feld `section` (z. B. `characters`). Zuordnung per `section`+`name` zu bestehenden Einträgen: Treffer werden als Entwurf gespeichert (PUT, inkl. `fields`, `name` wird nie überschrieben) und mit einer 1:1-Vorschau (`dbImportPreviewCard()`) angezeigt, dann über "Veröffentlichen" live. Nicht gefundene Namen werden nicht übersprungen, sondern als "neu anlegen?" gesammelt und per bewusstem Extra-Klick (`dbImportCreateNew()`) über POST sofort live angelegt (kein Entwurf, wie "+ Neuer Eintrag"). Der Extra-Klick verhindert Tippfehler-Dubletten. Das ist der Standardweg, um DB-Einträge (auch mit `fields`) zu pflegen, der Kachel-Editor kann `fields` nicht setzen.

**Wartung: Bildquellen vereinheitlichen (`bulkCredit()`).** Setzt bei allen Artikeln und DB-Einträgen mit Bild die Quelle auf "Rockstar" (früher teils "Rockstar Games" oder leer) und veröffentlicht direkt. Das ist ein bewusster Einmal-Knopf. Der Bild-Editor selbst hat **keinen** automatischen "Rockstar"-Default mehr: eine leere Quelle bleibt leer, eine bewusst entfernte Quelle wird als leerer Wert gespeichert und schlägt bis in die Datenbank durch. "Rockstar" steht im Feld nur noch als Platzhalter-Hinweis. (Früher belegte der Editor eine leere Quelle beim Öffnen mit "Rockstar" vor und schrieb sie beim Speichern zurück, dadurch ließ sich eine Quelle gar nicht dauerhaft entfernen, siehe Stolperfallen.)

**DB-Listen: Sortierung und Kategorie-Zusammenführung.** In jeder Datenbank-Rubrik gibt es einen Sortier-Umschalter (`dbSort`: Kategorie-Gruppen, Name A bis Z, Name Z bis A). Standard ist Gruppierung nach Kategorie in fester Reihenfolge je Rubrik (`DB_CAT_ORDER` für `characters`, `gangs`, `locations`), darin alphabetisch. Überlappende Kategorienamen werden für Anzeige, Filter und Sortierung auf einen kanonischen Namen normalisiert (`canonCat()`/`DB_CAT_ALIAS`, z. B. `vehicles` "SUVs"→"SUVs & Trucks"), ohne die gespeicherten Daten zu ändern.

**Trailer- und Gameplay-Auto-Verlinkung (`linkifyVideoRefs()`).** Erwähnungen von "Trailer 1/2/3..." und "Gameplay(-Material/...)" in Artikel- und DB-Texten verlinken render-seitig automatisch auf die passende Karte der Videos-Rubrik (Anker `videoAnchor()`, Sprung per `goVideo()`). Existiert das Video noch nicht (z. B. Trailer 3), führt der Link zur Videos-Übersicht und greift automatisch, sobald das Video in `VIDEOS` angelegt ist. Läuft in `renderText()` neben `linkifyChars()`/`linkifyArticleRefs()`. DB-Beschreibungen werden dabei über `renderDescHtml()` an Leerzeilen in echte Absätze umbrochen (wie serverseitig in `entry.php`).

### Guide-/Artikel-Datenmodell (JSON, wie die API liefert und erwartet)
```json
{
  "id": "stabiler-slug-aus-dem-titel",
  "cat": "news",
  "title": "prägnanter Titel, Keyword vorne, rund 55 bis 65 Zeichen",
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
`cat` ist eine von: `news`, `leaks`, `trailer`, `story`, `map`, `community` (Phase 1) bzw. `money`, `missions`, `vehicles`, `weapons`, `secrets`, `online`, `beginner` (Phase 2, aktuell gesperrt). **Achtung:** die dauerhaft angestrebte Kategorienbreite (Abschnitt 1) ist größer als diese festen `cat`-Werte. Trophäen, Immobilien und Business, Charakter und Anpassung, Sammelobjekte, Tipps und Tricks haben noch keinen eigenen `cat`-Wert. Das ist eine offene Code-Frage (siehe Abschnitt 6), kein Freibrief, im JSON erfundene `cat`-Werte zu nutzen.

Es gibt **kein** `tag`-Feld, das sichtbare Label kommt aus `GCAT[cat].name`. `id` wird beim Anlegen einmalig als Slug aus dem Titel generiert und bleibt fix, auch wenn der Titel später bearbeitet wird (sonst verwaisen Kommentare). `image_queries` ist optional (nur für Bildsuche-Vorschläge im Admin-Panel). `date` im Entwurf ist Platzhalter, das Admin-Panel überschreibt es beim Veröffentlichen automatisch mit dem echten Zeitpunkt.

**Strukturelemente innerhalb von `content` (bei Bedarf):**
- `"### Zwischenüberschrift"`: eigene Zwischenüberschrift, bekommt automatisch eine Anker-ID fürs Inhaltsverzeichnis.
- `"img:https://bild-url|Bildunterschrift"`: weiteres Bild im Text (Unterschrift optional). Drittes Segment `|narrow` für ein schmaleres, zentriertes Bild.
- `"- Ein Punkt"`: Aufzählungspunkt, mehrere ergeben automatisch eine Liste.
- `"faq:Frage?|Antworttext"`: aufklappbarer FAQ-Eintrag. **Pflicht in jedem Artikel, siehe eigener Block unten.** Mehrere ergeben einen FAQ-Block plus automatisch `FAQPage`-Schema fürs Google-Snippet.

**`tldr`** (optional, top-level, kein Präfix): Array aus 2 bis 5 Stichpunkten, wird als "Auf einen Blick"-Box unter dem Lead angezeigt. Sinnvoll bei längeren oder faktenreichen Artikeln, sonst weglassen.

**FAQ, starker Standard mit klarer Format-Pflicht.** FAQ wird als `faq:Frage?|Antwort`-Zeilen am Ende des `content`-Arrays geschrieben. **Es gibt KEIN separates `faq`-Feld im Datenmodell.** Ein eigenes `"faq": [...]`-Feld im JSON wird vom Code stillschweigend ignoriert und taucht auf der Live-Seite gar nicht auf (dieser Fehler ist schon passiert, siehe Stolperfallen). Wann FAQ Pflicht ist und wann es entfallen darf, steht typabhängig im Artikel-Muster unten (Guide/Reference immer, News/Analyse als Standard mit begründeter Ausnahme). Grund für den hohen Stellenwert: die `faq:`-Zeilen triggern automatisch das `FAQPage`-Schema fürs Google-Snippet, das ist gerade für eine kleine Seite ein direkter Sichtbarkeits-Hebel, nicht nur redaktionelle Kür. Die Fragen an echten Suchanfragen orientieren ("Wann kommt...", "Stimmt es, dass...", "Was kostet...").

### Verbindliches Artikel-Muster (alle Artikel, alle Oberflächen)
Jeder Artikel folgt demselben Aufbau, egal ob über Claude Chat, Claude Code oder Cowork erstellt. "Dasselbe Muster" heißt gleiche Pflicht-Bausteine und gleiche Qualitätslatte, nicht identisches Skelett unabhängig vom Inhalt.

**Pflicht-Bausteine in jedem Artikel:**
- **title:** Keyword in den ersten Wörtern, Gesamtlänge rund 55 bis 65 Zeichen. Doppelpunkt-Konstruktionen und Zitat-Aufhänger sind erlaubt und ranken oft besser als eine starre Kurzform. Kein reißerischer Clickbait, aber gern konkret und suchnah.
- **lead:** ein Satz, führt mit der bestätigten Tatsache oder dem Indiz, keine Floskel.
- **tldr:** "Auf einen Blick"-Box, 2 bis 4 Punkte. Bei jedem faktenreichen Artikel dabei, also praktisch immer. **Punkt 1 darf die Lead-Aussage nicht wortgleich wiederholen**, sondern liefert die Konsequenz oder Einordnung, sonst ist die Box redundant.
- **content:** öffnet mit dem bestätigten Kern (nicht der Spekulation), Bullets beim Aufzählen, jede genutzte Quelle im Text namentlich ("laut Forbes"). Zwischenüberschriften und interne Verlinkung nach den Regeln unten, nicht als Pflicht-Quote.
- **faq:** an echten Suchfragen orientiert. Starker Standard, aber typabhängig (siehe unten), nicht bei jeder Kurzmeldung erzwungen.
- **sources:** mindestens 2, benannt und real.
- **image_queries:** 3 Vorschläge. Bildfelder (`img`, `imgfit`, `credit`) beim Neuschreiben bestehender Artikel weglassen, wenn die Bilder separat gepflegt werden, sonst überschreibt ein leeres `img` das vorhandene Bild.

**Zwischenüberschriften (`###`), gestaffelt nach Typ:**
- Kurz-News (unter ~500 Wörter): 0 bis 1 Zwischenüberschrift, oft läuft der Text sauber ohne durch.
- Analyse (ca. 800 bis 1.500 Wörter): 2 bis 4 Zwischenüberschriften.
- Hub/Guide (mehrteilig): 4+ plus Inhaltsverzeichnis (kommt automatisch ab 3 `###`).
Keine erzwungene Mindest-Quote, kurze Meldungen nicht künstlich mit Überschriften aufblähen.

**FAQ, typabhängig statt pauschal:**
- **Guide, Reference, "Alles was wir wissen"-Hub:** FAQ Pflicht, 2 bis 3 Einträge. Hier passt die Suchintention und das FAQPage-Schema greift natürlich.
- **News und Analyse:** FAQ als starker Standard, aber verzichtbar, wenn der Artikel keine zwei echten, distinkten Suchfragen hergibt (reine Kurzmeldung). Dann im Chat kurz begründen, warum kein FAQ. Der Filter flaggt fehlendes FAQ weiterhin, damit die Entscheidung bewusst fällt und nicht aus Versehen.

**Interne Verlinkung, gedeckelt statt gefordert:**
- Maximal 3 Cross-Links `[[id|text]]` im Fließtext plus ein Hub-Verweis. Priorität ist kontextuelle Relevanz, nicht Menge. Lieber zwei sinnvolle Links als fünf gezwungene.

**Längen-Zielkorridore pro Typ:** Kurz-News 300 bis 500 Wörter, Analyse 800 bis 1.500, Hub/Guide mehrteilig. Grober Richtwert, kein Selbstzweck, der Inhalt bestimmt die Länge.

**Flexibel nach Artikeltyp:**
- **Analyse/News mit Wertung** (z. B. Trailer-Termin, Streik, Online-Modus): eigene Position ausdrücklich erwünscht, als normaler Absatz oder eigener `###`-Abschnitt (etwa "Was das bedeutet"). Die Meinung darf klar eingeleitet werden ("Meiner Einschätzung nach", "Wir halten das für unwahrscheinlich, weil..."), aber NIE mit Aufsatz-Klammer wie "Unser Fazit:" oder "Kurz gesagt:". Haltung immer auf benannte Fakten stützen, nie als bloße Behauptung. Das ist der USP gegenüber reinem Leak-Kopieren.
- **Reference** (z. B. Karte, Plattformen, Editionen): nüchtern, keine erzwungene Meinung, dafür maximal vollständig und sauber strukturiert.

### Pflicht-Filter vor jeder Übergabe: `viceguide_lint.py`
Vor der Übergabe eines Artikels läuft das Prüfskript `viceguide_lint.py` (liegt im Repo-Wurzelverzeichnis) über das JSON. Aufruf: `python3 viceguide_lint.py <datei>.json`. Es prüft hart auf vollständige Schema-Felder, gültige `cat`, kein separates `faq`-Feld, null Gedankenstriche, mindestens eine Quelle und die komplette Floskel-Verbotsliste aus Abschnitt 0. Dazu weiche Warnungen: fehlendes FAQ (bei Guide/Reference nachrüsten, bei Kurz-News bewusst entscheiden), Titel länger als ~65 Zeichen, und Sätze mit 4+ Kommas (Sing-Sang). **Ein Artikel wird erst übergeben, wenn der Filter 0 harte Fehler meldet und die weichen Warnungen redaktionell geprüft sind.** Das ist der maschinelle Teil der Qualitätssicherung, die Tonalität "liest sich wie ein Gaming-Redakteur" bleibt zusätzlich redaktionelle Beurteilung.

**Automatisch, ohne eigenes Feld:** Inhaltsverzeichnis ("Direkt zu") ab 3 `### `-Überschriften, Lesezeit-Anzeige aus der Wortzahl, FAQ-Schema (`FAQPage` JSON-LD) sobald `faq:`-Einträge da sind.

**Richtung für künftige Struktur-Arbeit:** Artikel scanbarer und interaktiver machen (Leser lesen selten bis zum Ende) und SEO-Signale verbessern (Rich Snippets, interne Verlinkung, Struktur passend zu echten Suchanfragen). Umgesetzt: Bulletpoints, FAQ mit Schema, TL;DR, Inhaltsverzeichnis, Lesezeit. Denkbare nächste Schritte: Article-Schema (JSON-LD) für alle Artikel, gezielte interne Verlinkung im Fließtext, Zwischenüberschriften an echten Suchanfragen ausrichten. Immer sparsam bleiben, Kernrichtung ist "besser lesbar und besser auffindbar", nicht "möglichst viele Spielereien".

### Datenbank-Eintrag-Datenmodell
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
Aus der API kommt zusätzlich `_id` (interne Zeilen-ID, für Updates gebraucht) und, sobald vergeben, `slug` (stabiler URL-Bezeichner, siehe Architektur oben).

### Ordnerstruktur (Stand laut Repo, Juli 2026)
```
/                          Projektwurzel
├─ CLAUDE.md               Diese Datei
├─ SOCIAL.md               Instagram-Strategie
├─ TOOLS.md                Design-Standards (Karussell, Artikelbilder)
├─ KANAELE.md              Kanal-Übersicht
├─ viceguide_lint.py       Prüfskript für Artikel-JSON (Pflicht-Filter vor Übergabe, siehe Abschnitt 2)
├─ index.html              Die Single-Page-App (direkt editieren)
├─ article.php             Server-gerendertes Artikel-Detail (echte URL /artikel/id, für SEO)
├─ entry.php               Server-gerendertes Datenbank-Detail (echte URL /praefix/slug, für SEO)
├─ category.php            Server-gerenderte Kategorie-Uebersicht pro Sektion (echte URL /praefix/, für SEO)
├─ section.php             Server-gerenderte Videos/Community/Karte-Seiten (echte URL /videos, /community, /karte)
├─ legal.php               Server-gerendertes Impressum/Datenschutz (echte URL /impressum, /datenschutz)
├─ sitemap.php             Erzeugt die Sitemap dynamisch aus der Datenbank (ersetzt die alte statische sitemap.xml)
├─ robots.txt              Suchmaschinen-Regeln
├─ og-image.jpg            Social-Preview-Bild (Fallback, wenn Artikel/Eintrag kein eigenes Bild hat)
├─ logo.png                Logo-Datei (u. a. fürs OG-/Social-Preview, JSON-LD-Logo)
├─ favicon-16x16.png       Favicon (Browser-Tab), aus logo.png generiert (quadratisch, transparent)
├─ favicon-32x32.png       Favicon, siehe favicon-16x16.png
├─ favicon-48x48.png       Favicon, Google verlangt fürs Suchergebnis-Icon ein Vielfaches von 48px
├─ apple-touch-icon.png    iOS-Home-Bildschirm-Icon, wie Favicons aber mit deckendem Hintergrund statt Transparenz
├─ googlec9a955...html     Google-Search-Console-Verifizierungsdatei, nicht löschen
├─ .htaccess               Server-Regeln (URL-Rewriting auf article.php/entry.php/sitemap.php, Cache-Zeiten)
├─ .gitignore              Schließt api/config.php und lokale *.sqlite aus
├─ articles.json           Eingefrorener Anfangsstand, keine laufende Datenquelle
├─ database.json           Eingefrorener Anfangsstand, keine laufende Datenquelle
├─ assets/fonts/           Selbst gehostete .woff2 (oswald-variable, inter-variable, spacemono-400/700)
└─ api/
   ├─ config.sample.php    Vorlage für DB-Zugang und Admin-Passwort-Hash
   ├─ config.php           Echte Zugangsdaten, NICHT im Git, nur auf dem Server
   ├─ db.php               Gemeinsame PDO-Verbindung, legt Tabellen automatisch an, Slug-Migration/Helper
   ├─ auth.php             Login/Logout/Status, session-basiert, 90 Tage
   ├─ articles.php         GET/POST/PUT/DELETE für Artikel, inkl. Entwurf/Veröffentlichen (?action=publish/discard)
   ├─ db_entries.php       GET/PUT/DELETE für Datenbank-Einträge, inkl. Entwurf/Veröffentlichen (?action=publish/discard)
   ├─ comments.php         GET/POST/PATCH/DELETE für Kommentare
   ├─ article_image.php    Liefert das base64-Artikelbild als echte, abrufbare Bild-URL aus (für og:image)
   └─ entry_image.php      Liefert das base64-Bild eines Datenbank-Eintrags als echte Bild-URL aus
```
Logo, Wallpaper und alle DB-/Artikel-Bilder liegen als **base64-Data-URIs** direkt in der HTML bzw. in der Datenbank (Ausnahme: Fonts, `logo.png`, `og-image.jpg`). Die Design-Quelldateien mit den ViceGuide-Namen (z. B. das Karussell-Wallpaper) liegen nicht im Repo, sondern im Design-Material.

---

## 3. Konventionen

### Code-Stil
- CSS und JS bleiben inline in `index.html`. Assets als base64, außer Fonts.
- **Theming ausschließlich über CSS Custom Properties.** Nie feste Farbwerte hart schreiben, immer die `--variable`. Dark Mode ist die CSS-Basis (`:root`), Light Mode überschreibt (`:root[data-theme="light"]`). Light Mode ist Standard-Theme, per `localStorage` (`vg-theme`) gemerkt.
- **Akzentfarbe ist bewusst modusabhängig (nicht eine Farbe für beide Modi).** Light Mode nutzt Pink (`--accent:#D00059`), Dark Mode Blau (`--accent:#88B8C5`). Die Sekundärfarbe `--accent-2` ist in beiden Modi an die jeweilige Hauptfarbe angeglichen (Light `#D00059`, Dark `#88B8C5`), damit keine dritte, fahle Farbe (früher Minz-Türkis `#12B5B0` bzw. Purple `#8B5CF6`) dazwischenfunkt. Auch die Chip-Farben (`--ntag-*`) folgen pro Modus der Hauptfarbe (Light pinkstichig, Dark blaustichig). Das ist eine bewusste Design-Entscheidung ("Pink am Tag, Blau in der Nacht", siehe Abschnitt 5), kein Versehen: einen Akzent nicht auf eine einzige Farbe für beide Modi zurückvereinheitlichen.
- Vanilla JS, keine Transpilation. Funktionsnamen sprechend (`openAdmin`, `toggleEdit`, `ieApply`, `renderView`, `toggleTheme`).
- **Keine Gedankenstriche, kein generischer KI-Ton** (Abschnitt 0).
- PHP: kleine Skripte pro Endpunkt, PDO mit Prepared Statements (nie String-Konkatenation für SQL), `vg_require_admin($cfg)` als Gate vor jeder schreibenden Aktion.

### Naming
- Kategorie-IDs klein und knapp (siehe `cat`-Liste oben).
- DB-Sektionen: `characters`, `vehicles`, `weapons`, `wildlife`, `gangs`, `radio`, `activities`, `locations`.
- Sichtbare Labels deutsch und ausgeschrieben.
- Datenkonstanten in JS in GROSSBUCHSTABEN (`SECTIONS`, `DB`, `GUIDES`, `GROUPS`, `GCAT`).

### Branch-, Commit-, Test-Regeln
- Arbeits-Branch: `claude/viceguide-gta6-portal-z49wib`, nach Bestätigung per Fast-Forward (oder Cherry-Pick bei parallelen Datenänderungen) nach `main`. `main` ist Deploy-Branch.
- Commit-Messages: kurz, deutsch, sachlich, ohne Gedankenstriche.
- Reine Datenänderungen (Uploads vom Betreiber) werden automatisch validiert (JSON gültig, keine Gedankenstriche, keine verlorenen Einträge), committed und gemergt, ohne Rückfrage. Code-/Design-Änderungen vorher als Vorschau zeigen, erst nach Bestätigung mergen.
- Keine automatisierten Tests. Vor dem Commit lokal mit `php -S localhost:PORT -t .` und Playwright/Chromium gegentesten, besonders bei Backend-Änderungen.

---

## 4. Wichtige Befehle

Kein Build-Schritt. `index.html` ist direkt die ausgelieferte Datei.

**Lokal ansehen (nur Frontend):**
```bash
python3 -m http.server 8000
# dann http://localhost:8000/index.html
```

**Lokal mit Backend (Login, Kommentare, Speichern):** PHP mit PDO/SQLite, `api/config.php` lokal auf SQLite zeigen lassen (`db_dsn` auf `sqlite:...`):
```bash
php -S localhost:8000 -t .
```
`api/db.php` legt die Tabellen beim ersten Aufruf automatisch an. Da `.htaccess` lokal ohne Apache nicht greift, braucht das Testen echter Artikel-/Eintrags-URLs (`/artikel/...`, `/charaktere/...`) einen kleinen Router-Wrapper für `php -S`, der dieselben Rewrite-Regeln nachbildet.

**Deploy:** Push nach `main`, Hostinger Auto-Deploy zieht automatisch. `api/config.php` liegt **nicht** im Git und muss einmalig manuell über den Hostinger-Dateimanager im Ordner `api/` angelegt werden (Vorlage: `config.sample.php`).

---

## 5. Getroffene Entscheidungen und Begründung

- **Single-File-HTML statt Framework.** Einfach zu hosten, sofort lauffähig, kein Build-Overhead.
- **Zwei-Phasen-Struktur (pre/rel), Phase 2 gesperrt.** An den Spiel-Lebenszyklus gekoppelt.
- **Dual-Theme mit Umschalter,** Light Mode als Standard, per `localStorage`.
- **Modusabhängige Akzentfarbe (Pink am Tag, Blau in der Nacht).** Ursprünglich war der Akzent Pink/Magenta in beiden Modi. Nach einem Redesign-Durchlauf mit dem Betreiber ist der Akzent jetzt bewusst je Modus verschieden: Light Mode Pink (`#D00059`), Dark Mode Blau (`#88B8C5`). Grund: ein einzelnes Blau wirkte im Light Mode zu blass, ein einzelnes Pink im Dark Mode zu grell. Die frühere Zweitfarbe (`--accent-2`, Minz-Türkis im Light, Purple im Dark) fiel dabei weg und wurde an die Hauptfarbe angeglichen, weil sie sonst als dritte Farbe fahl dazwischenwirkte (u. a. bei der "ab 19.11.2026"-Pille). Die pinken Text-Labels, die der Betreiber selbst in die Titelbilder einbrennt, werden künftig weggelassen bzw. neutral gehalten, damit sie in beiden Modi passen.
- **Eigene Grafik statt Rockstar-Material.**
- **Fonts selbst gehostet statt Google Fonts.** DSGVO plus Caching-Vorteil.
- **Bilder automatisch komprimiert.** Jedes im Editiermodus hochgeladene Bild wird clientseitig per Canvas auf max. 1100px verkleinert und als WebP kodiert (Fallback JPEG). Grund: eine frühe Version ohne Kompression ließ `database.json` auf über 40 MB anwachsen.
- **Direktes Speichern in die Datenbank statt JSON-Download/Upload.** Dieselbe MySQL-Infrastruktur, die für Kommentare nötig war, trägt auch Artikel und DB-Einträge. Editiermodus speichert sofort und dauerhaft, auch vom Handy.
- **Echtes Login statt reinem Client-Schalter.** Sobald Schreibzugriffe auf eine gemeinsame Datenbank möglich waren, wurde ein serverseitig geprüftes Passwort-Login (PHP-Session, 90 Tage) nötig.
- **Echte URLs statt Hash-Routing, mit serverseitigem Rendering für Crawler.** Artikel und Datenbank-Einträge waren ursprünglich nur über Hash-Parameter erreichbar, Suchmaschinen konnten einzelne Seiten nicht indexieren, Mittelklick/Strg-Klick für einen neuen Tab ging nicht. Gelöst über `.htaccess`-Rewrites plus `article.php`/`entry.php` als schlanke SSR-Fassade (nur Metadaten und eine vereinfachte Textfassung), ohne eine zweite vollständige Rendering-Engine zu bauen. Details siehe Architektur oben.
- **Entwurf/Veröffentlichen-Modell statt sofortigem Live-Schreiben.** Frühere Version schrieb jede Bearbeitung im Editiermodus sofort in die öffentlichen Spalten, sichtbar für alle Besucher sofort nach dem Speichern. Jetzt sammelt der Editiermodus Änderungen in `draft_json` (nur der eingeloggte Admin sieht seine eigene Vorschau), bis bewusst auf "Fertigstellen" geklickt wird. Grund: während einer Editier-Sitzung sollten Besucher nicht unfertige Zwischenstände sehen.
- **Bild-URL-Cache-Busting (`&v=<updated_at>`).** Die leichten Bild-URLs (`api/article_image.php`/`api/entry_image.php`) werden mit `max-age=86400` gecacht, ohne Versionsanhang zeigte ein Browser nach einer Bildänderung bis zu 24 Stunden weiter das alte Bild. Gelöst, indem sich die URL bei jeder echten Änderung durch den angehängten Zeitstempel ändert.
- **KI-Artikel über Copy-Paste-JSON statt eigenem API-Schlüssel.** Recherche und Texterstellung laufen kostenlos über einen normalen Claude-Chat, das Ergebnis wird als JSON im Admin-Panel eingefügt ("Claude-Entwurf veröffentlichen"), Vorschau geprüft, bei Bedarf bearbeitet, dann freigegeben. Der Live-Recherche-Pfad (`generateGuide()`) bleibt im Code, ist aber ohne eigenen Schlüssel inaktiv.
- **Automatische Verlinkung in Texten.** Namen aus Charaktere, Fahrzeuge, Wildtiere, Gangs und Orte werden automatisch klickbar zum Detail-Eintrag verlinkt (`linkifyChars()`/`buildEntityAliases()`), mit Ausschlussliste (`ENT_LINK_SKIP`). Waffen, Radio und Aktivitäten bewusst außen vor (Namenskollisionen). In Artikeln wird jeder Name nur beim ersten Vorkommen verlinkt.
- **DB-Einträge per JSON-Import statt nur Kachel-Editor.** Der Kachel-Editor konnte das `fields`-Objekt gar nicht speichern, und für DB-Einträge fehlte ein Paste-Weg wie bei Artikeln. Deshalb `submitDbImport()`: Zuordnung per `section`+`name`, Treffer als Entwurf mit Vorschau, nicht gefundene Namen auf bewussten Extra-Klick neu anlegen (siehe Abschnitt 2). Update-only wäre zu eng gewesen, reines Auto-Create hätte Tippfehler-Dubletten riskiert, daher der Zwei-Wege-Ansatz mit getrenntem Bestätigungsklick fürs Anlegen.
- **DB-Sortierung, Kategorie-Zusammenführung, Trailer-Verlinkung, Bildquellen-Vereinheitlichung.** In einem Rutsch beim großen Datenbank-Ausbau gebaut: Sortier-Umschalter plus Gruppen-Reihenfolge (`dbSort`/`DB_CAT_ORDER`), Normalisierung doppelter Kategorienamen rein für die Anzeige (`canonCat()`/`DB_CAT_ALIAS`, Daten bleiben unangetastet), automatische Trailer/Gameplay-Links auf die Videos-Rubrik (`linkifyVideoRefs()`) und ein einmaliger Wartungsknopf `bulkCredit()`, der alle Bildquellen auf "Rockstar" vereinheitlicht (der Bild-Editor selbst hat keinen automatischen "Rockstar"-Default mehr, siehe Abschnitt 2). DB-Beschreibungen brechen im Modal jetzt über `renderDescHtml()` an Leerzeilen in Absätze um, wie serverseitig. Details in Abschnitt 2.
- **Inline-Texteditor im Editiermodus.** Titel, Teaser und Beschreibung direkt im selben Dialog wie der Bild-Editor.
- **Ausnahmslos echte URLs, auch für Kategorie-Listen, Videos, Community, Karte und Home.** Nach einer externen SEO-Analyse (ChatGPT) blieben noch Lücken: `/charaktere/` als Liste existierte gar nicht (nur einzelne Eintrags-URLs), Videos/Community/Karte liefen nur über Hash (`/#/videos`), Home schrieb `/#/home` statt der sauberen Wurzel-URL. Alles über `category.php`/`section.php` plus Erweiterungen an `syncHash()`/`restoreFromLocation()` geschlossen, siehe Architektur oben. Grund fürs eigene `section.php` statt Erweiterung von `category.php`: Videos/Community/Karte haben keine Datenbank-Einträge dahinter, der Inhalt kommt aus JS-Konstanten in `index.html`.
- **Admin-Panel/Bild-Editor als JS-Template statt statisches HTML.** Dieselbe externe SEO-Analyse fand, dass Google die Redaktionswerkzeug-Texte im Quelltext jeder Seite mitlas, obwohl das Overlay für Besucher nie sichtbar wurde. Beide Overlays werden jetzt erst beim ersten echten Öffnen ins DOM injiziert (`ADM_HTML`/`IMGED_HTML`), analog zum bestehenden `LEGAL`-Muster.
- **Logo einmalig als Datei statt doppelt als Base64.** Header- und Hero-Logo waren als zwei identische, je rund 313 KB große Base64-Kopien direkt im HTML eingebettet. Jetzt referenzieren beide die ohnehin vorhandene `logo.png`, spart über 800 KB reinen HTML-Text pro Seitenaufruf.
- **Kanonischer 301-Redirect auf `https://viceguide.de/`.** `www.viceguide.de`, `viceguide.de` und `viceguide.de/index.html` waren alle drei separat mit Status 200 erreichbar (Duplicate-Content-Risiko, externe SEO-Analyse). Jetzt leitet `.htaccess` ganz oben (vor allen anderen Rewrite-Regeln) `http`, `www` und `/index.html` per 301 auf die Wurzel-URL um.
- **Artikel-/Eintrags-Titel bekommen den " - ViceGuide"-Suffix nur noch, wenn er noch unter der rund 60-Zeichen-Grenze bleibt.** Vorher hängte `article.php`/`entry.php` den Suffix ausnahmslos an, bei ohnehin langen Titeln schnitt Google das sichtbare Ende im Suchergebnis ab. Jetzt fällt zuerst der Branding-Suffix weg, bei Datenbank-Einträgen danach zur Not auch die Unterzeile, der eigentliche Titel (das Keyword) bleibt immer erhalten.
- **Echter Autor (Eddy Hanné) statt generischer "ViceGuide Redaktion".** Auf Betreiberentscheidung: Klarname statt Pseudonym/Team-Seite, stärkeres E-E-A-T-Signal. Fallback in `article.php` (sichtbarer Autor-Span plus Article-JSON-LD, dort zusätzlich `@type` von `Organization` auf `Person` geändert) und in `index.html`s clientseitigem Rendering geändert. Nur der Fallback für Artikel ohne eigenes `author`-Feld, ein individuell gesetzter Autor überschreibt das weiterhin.
- **Sichtbares Aktualisierungsdatum plus `dateModified` im Article-JSON-LD.** `updated_at` existierte pro Artikelzeile bereits (bisher nur als Cache-Buster für Bild-URLs genutzt), jetzt reicht `api/articles.php` es zusätzlich als `updated`-Feld an den Client durch. `article.php` und `index.html` zeigen ein "Aktualisiert: ..."-Badge neben dem Datum, aber nur wenn zwischen `article_date` und `updated_at` wirklich mehr als ein Tag liegt, sonst würde der minimale Zeitversatz zwischen Browser- und Server-Zeitstempel jeden frisch veröffentlichten Artikel fälschlich als bearbeitet markieren. `dateModified` im JSON-LD wird immer mitgeschrieben (fällt auf `article_date` zurück, wenn kein `updated_at` da ist).
- **Neue Datenbank-Einträge lassen sich jetzt direkt im Editiermodus anlegen,** nicht nur bestehende bearbeiten. Neuer Endpunkt `POST api/db_entries.php` (Admin, sofort live wie bei neuen Artikeln, kein Entwurf), dazu ein "+ Neuer Eintrag"-Knopf in der Kategorie-Ansicht, der Name und optional Kategorie abfragt und danach direkt den Bild-/Text-Editor öffnet. Der Bild-/Text-Editor bekommt dabei ein neues Kategorie-Textfeld für Datenbank-Einträge (`ie-t-dbcat`): das fehlte bisher komplett, `cat` liess sich nach dem Anlegen gar nicht mehr aendern.
- **Favicon ergänzt (fehlte komplett).** Weder `<link rel="icon">` noch eine `favicon.ico` waren vorhanden, Google zeigt ohne Favicon kein Icon im Suchergebnis, Browser-Tabs blieben ohne Symbol. Aus `logo.png` per PHP/GD ein quadratisches Icon-Set generiert (auf transparenter Fläche zentriert statt hart beschnitten, damit die "VI"-Form nicht seitlich abgeschnitten wird): `favicon-16x16.png`, `favicon-32x32.png`, `favicon-48x48.png` (Google verlangt fürs Suchergebnis-Icon ein Vielfaches von 48px) sowie `apple-touch-icon.png` mit deckendem dunkellila Hintergrund statt Transparenz (iOS rendert Transparenz beim Home-Bildschirm-Icon unvorhersehbar).
- **Sitemap-`lastmod` für Artikel nutzt jetzt `updated_at` statt `article_date`.** Direkte Folgeinkonsistenz aus der `dateModified`-Änderung: die Sitemap zeigte Google weiterhin nur das Veröffentlichungsdatum als "zuletzt geändert", selbst wenn ein Artikel Tage später inhaltlich überarbeitet wurde. `sitemap.php` liest jetzt zusätzlich `updated_at` und nimmt es (mit Fallback auf `article_date`) als `lastmod`.

---

## 6. Aktueller Stand, offene Aufgaben, Stolperfallen

### Aktueller Stand (Juli 2026)
- Seite live auf viceguide.de, technisch voll funktionsfähig, Datenbank-Backend inklusive Login, Kommentare, direktes Speichern, Entwurf/Veröffentlichen-Workflow.
- Impressum und Datenschutzerklärung mit echten Daten befüllt.
- Fonts selbst gehostet, Bildkompression automatisch, Altlast-Bilder komprimiert.
- **18 Artikel live.**
- **Datenbank komplett auf Redaktionsniveau ausgebaut.** Alle acht Rubriken (Charaktere, Fahrzeuge, Waffen, Wildtiere, Gangs, Radio, Aktivitäten, Orte) haben echte Redaktionstexte plus strukturierte `fields`, sind nach Kategorie gruppiert sortiert und dublettenbereinigt. Bildquellen einheitlich auf "Rockstar". Grober Bestand: Charaktere ~23, Fahrzeuge ~37, Waffen ~39, Wildtiere 54, Gangs 8, Radio 3, Aktivitäten 12, Orte ~24. Reference-Felder wie Fundort, Missions-Belohnung, Kaufpreis sind bewusst noch leer und werden ab Release (Phase 2) nachgezogen, da vor Launch nicht bekannt.
- **SEO-Grundlagen erledigt:** Google Search Console eingerichtet, Sitemap eingereicht, OG-Image vorhanden, echte URLs für ausnahmslos jeden Bereich (Artikel, Datenbank-Einträge, Kategorie-Listen, Videos, Community, Karte, Impressum/Datenschutz, Home) mit serverseitigem Rendering, kein Hash-Routing mehr aktiv. PageSpeed-Audit durchgeführt (SEO 100/100 mobil und Desktop, Performance Desktop 90, Mobil im mittleren Bereich, siehe Stolperfallen zu Messwert-Schwankungen). Externe SEO-Analyse per ChatGPT eingeholt und die konkreten Funde (Admin-Panel im HTML, doppeltes Logo, fehlende Kategorie-Hubs, DB-Migration pro Request, Impressum/Datenschutz ohne eigene URL) abgearbeitet, siehe Abschnitt 5.
- **Internes Artikel-Cross-Linking ist gebaut, wird aber noch manuell angestoßen** (Claude schlägt passende Verlinkungen zu Bestandsartikeln vor, kein Automatismus).
- Bio-Link auf viceguide.de im Instagram-Profil gesetzt.

### Offene Aufgaben
1. Artikel-Grundstock weiter ausbauen (laufend), Ziel ist Content-Menge auf echte deutsche Suchanfragen.
2. **Kategorien-Taxonomie klären:** die angestrebte Phase-2-Breite (Trophäen, Immobilien und Business, Charakter und Anpassung, Sammelobjekte, Tipps und Tricks) geht über die festen `cat`-Werte im Code hinaus. Entweder auf bestehende Kategorien mappen oder die Sektionen im Code erweitern. Vor der ersten Phase-2-Welle entscheiden. **Stand jetzt bewusst zurückgestellt** (noch keine Phase-2-Welle in Sicht, Entscheidung erst kurz davor treffen).
3. **Cowork-Automation für tägliche Artikel-Recherche einrichten:** ein geplanter Cowork-Task durchsucht 1x täglich seriöse Quellen nach GTA-6-News, gleicht gegen Faktenblatt und bestehende Artikel ab, entwirft neue Beiträge als fertiges JSON und führt den Redaktionsplan. **Kein Auto-Publish, Freigabe bleibt manuell** (siehe Abschnitt 9).
4. Rechtliche Absicherung im Blick behalten: "VI" und "Vice" als Markenrecht-Grauzonen (DPMA/EUIPO), prüfen wenn es kommerziell ernster wird. Bislang nicht geprüft.
5. Gewerbeanmeldung prüfen, sobald echte Werbe-/Affiliate-Einnahmen fließen.
6. Kanal-Aufbau über Instagram hinaus (YouTube, TikTok, X, Reddit, Discord): Status und Priorität in KANAELE.md. Handles außer Instagram sind Stand jetzt nicht gesichert.
7. Optional: eigener Anthropic-API-Schlüssel für Live-Recherche direkt auf der Seite (`config.php` Feld `anthropic_api_key`, `generateGuide()` auf serverseitigen Proxy umstellen), falls der Copy-Paste-Workflow zu langsam wird.

### Stolperfallen
- **Gedankenstriche schleichen sich leicht ein,** besonders in KI-Texten. Nach jeder Generierung prüfen.
- **Generischer KI-Ton schleicht sich ein** (Regel 2). Häufigster konkreter Fehler: die Drei-Teile-Komma-Kette (Sing-Sang) und eingeschlichene Floskeln wie "Kurz gesagt", "Wichtig dabei", "Beobachter werten". Vor dem Veröffentlichen laut vorlesen: klingt das wie ein Gaming-Redakteur, oder wie eine KI-Zusammenfassung? Die Selbstprüfliste in Abschnitt 9 durchgehen.
- **FAQ im falschen Format verschwindet spurlos.** FAQ muss als `faq:Frage?|Antwort`-Zeile im `content`-Array stehen. Ein separates `"faq": [...]`-Feld wird ohne Fehlermeldung ignoriert und erscheint nicht auf der Seite (ist schon passiert). Nach dem Veröffentlichen einmal auf der Live-Seite prüfen, ob der FAQ-Block wirklich da ist.
- **Geleerte Felder müssen explizit an den Server, sonst bleibt der alte Wert stehen.** Der Entwurf-Merge in `api/articles.php`/`api/db_entries.php` übernimmt nur Keys, die im PUT-Payload tatsächlich vorhanden sind (`array_key_exists`). Wird ein Feld beim Speichern per `delete` aus dem lokalen Objekt entfernt, fehlt es im Payload und der Entwurf behält den alten Wert, die Leerung schlägt nie durch. Deshalb im Bild-/Text-Editor geleerte Werte als leeren String (nicht per `delete`) mitschicken. Konkret schon passiert bei der Bildquelle: leere Quelle wurde beim Öffnen auf "Rockstar" vorbelegt und beim Speichern zurückgeschrieben, sodass sich "Rockstar" nicht entfernen ließ (behoben, `ieState.credit` ohne Default, `d.credit=cr` immer setzen).
- **`api/config.php` nie committen.** Steht in `.gitignore`, muss nach frischem Server-Setup manuell im Hostinger-Dateimanager angelegt werden.
- **Einmal-Werkzeuge** (Passwort-Hash-Generator, Migrations-Skript) nach Gebrauch nicht nur vom Server, sondern auch aus dem Git-Repo entfernen (`git rm`). Sonst kommen sie beim nächsten Deploy zurück.
- **Artikel-`id` ist fix, der Titel nicht.** Beim Bearbeiten des Titels bleibt die `id` (und Kommentar-Zuordnung) unverändert, Absicht.
- **`database.json`/`articles.json` sind kein Live-Speicher mehr.** Änderungen dort haben keinen Effekt, die Datenbank ist maßgeblich.
- **PageSpeed-Messwerte schwanken zwischen Testläufen,** teils deutlich (in einer Messreihe schwankte der Mobil-Score bei komplett unverändertem Code um über 20 Punkte). Live-Messungen gegen den echten Server, nie nur einem einzelnen Lauf hinterherjagen, immer mehrfach hintereinander testen.
- **`str_replace()`/Regex-Extraktion in `article.php`/`entry.php`/`category.php`/`section.php`/`legal.php` bricht still, wenn sich die gesuchten `index.html`-Fragmente ändern** (kein Fehler bei ausbleibendem Treffer, das Feld bleibt in der SSR-Fassung einfach leer/unsichtbar). Betrifft auch `section.php`s Regex-Auslesen von `VIDEOS`/`COMMUNITY` und `legal.php`s Auslesen von `LEGAL`, alle drei erwarten ein bestimmtes, unquotiertes JS-Objekt-Format. Nach Änderungen an den betroffenen `index.html`-Elementen oder JS-Konstanten die SSR-Ausgabe der jeweiligen PHP-Datei gegenprüfen.
- **Genaue Repo-Dateinamen der Bild-Assets** sind aus dem Sandbox-Chat nicht sichtbar, bei Coding-Sessions gegen das echte Repo prüfen.
- **Claude hat aus der Sandbox keinen Netzwerkzugriff** auf viceguide.de, Google Drive oder die Hostinger-Datenbank. Große Dateien oder Live-Checks laufen über den Betreiber.
- **Direkt im Browser gemachte Content-Änderungen** sind einem neuen Chat nicht automatisch bekannt. Bei Bedarf nachfragen.

---

## 7. Backend / API (Details)

### Authentifizierung
- `api/auth.php`: `GET` gibt `{loggedIn:bool}`, `POST {password}` prüft gegen den bcrypt-Hash (`admin_hash`) in `config.php` und startet bei Erfolg eine PHP-Session (Cookie, 90 Tage, HttpOnly, Secure). `DELETE` beendet sie.
- Jeder schreibende Endpunkt ruft `vg_require_admin($cfg)` (in `db.php`) auf, das ohne gültige Session mit HTTP 403 abbricht. `vg_is_admin()` ist die nicht abbrechende Variante davon, genutzt um bei `GET` einem eingeloggten Admin zusätzlich seinen eigenen Entwurfsstand zu zeigen.
- Passwort-Hash ändern: kein dauerhaftes Tool im Repo (bewusst gelöscht). Bei Bedarf lokal `password_hash('neuesPasswort', PASSWORD_BCRYPT)` ausführen und den Wert in `config.php` eintragen.

### Endpunkte
- `api/articles.php`: `GET` alle Artikel (für eingeloggte Admins inklusive eigenem Entwurfsstand, `_draft:true` markiert), `POST` neuer Artikel (admin, sofort live, kein Entwurf), `PUT {id,...}` speichert als Entwurf in `draft_json` (admin), `DELETE {id}` löscht Artikel und zugehörige Kommentare (admin), `POST ?action=publish` veröffentlicht alle offenen Entwürfe (admin), `POST ?action=discard` verwirft alle offenen Entwürfe ersatzlos (admin).
- `api/db_entries.php`: `GET` alle Einträge gruppiert nach `section` (mit Entwurfsstand für Admins), `PUT {id,...}` speichert als Entwurf (admin), `id` ist hier die interne Zeilen-ID (`_id` im GET-Ergebnis), nicht der Name. `DELETE {id}` löscht den Eintrag (admin). `POST ?action=publish`/`?action=discard` analog zu `articles.php`. `GET` vergibt außerdem fehlenden Einträgen einmalig einen `slug` (`vg_ensure_entry_slugs()`).
- `api/comments.php`: `GET ?article=<id>` Kommentarbaum, `POST {article,name,text,parentId?,quote?}`, `PATCH {id,dir}` Vote, `DELETE {id,password}` löschen (admin).

### KI-Guide-Generator
**Primär (aktiv):** ein separater Claude-Chat (mit dieser Datei als Projekt-Wissen) recherchiert und schreibt den Artikel als JSON. Der Betreiber fügt es im Admin-Panel unter "Claude-Entwurf veröffentlichen" ein (`submitDraftJson()`), bekommt eine editierbare Vorschau (Titel, Teaser, Text, Bild per Klick/Einfügen/Drag&Drop mit Zoom/Ausschnitt) und veröffentlicht direkt. Kein API-Schlüssel, keine Kosten.

**Sekundär (inaktiv):** `generateGuide()` ruft direkt `https://api.anthropic.com/v1/messages` (Modell `claude-sonnet-4-6`, `web_search`-Tool). Braucht einen eigenen Schlüssel, serverseitig über einen `api/`-Endpunkt eingebunden, nie im Frontend. Aktuell nicht eingerichtet.

### Domain und Hosting
- Domain viceguide.de bei Hostinger, live. Kanonische URL `https://viceguide.de/`.
- MySQL bei Hostinger, über das Dashboard verwaltet.

### Social und Kanäle
- **Instagram/Threads:** Handle **@viceguide**, aktiv. Details in SOCIAL.md.
- **Weitere Kanäle** (YouTube, TikTok, X, Reddit, Discord): Status und Priorität in KANAELE.md. **Stand jetzt ist außer Instagram nichts gesichert.** (Frühere Notiz, diese Handles seien "gesichert", war falsch und ist korrigiert.)

---

## 8. GTA-6-Faktenblatt (verbindlich, projektweit)

> **Zuletzt geprüft: 11. Juli 2026.** Diese Zahlen gelten für Website UND Social, damit nichts auseinanderdriftet. GTA-6-Berichterstattung ändert sich schnell: bei jedem neuen News-Artikel die relevanten Punkte per Websuche gegenprüfen und bei Änderung dieses Datum und die betroffene Zeile aktualisieren.

- **Release:** Donnerstag, 19. November 2026. Von Rockstar am 6. November 2025 bestätigt, von Take-Two am 21. Mai 2026 nochmals bekräftigt.
- **Plattformen:** PS5 und Xbox Series X/S. **Kein PC** und **kein Switch 2** zum Launch bestätigt (PC historisch meist später).
- **Preis:** Standard 79,99 US-Dollar, Ultimate 99,99 US-Dollar (rund 80 bzw. 100 Euro).
- **Vorbestellung:** live seit 25. Juni 2026. Gratis "Vintage Vice City Pack" bei Vorbestellung vor dem 20. November 2026.
- **Protagonisten:** Jason (Duval) und Lucia (Caminos), kriminelles Paar, erstes spielbares Duo der GTA-Reihe.
- **Setting:** Bundesstaat Leonida (fiktiv, nach Vorbild Florida), Herzstück Vice City (Miami-Analog).
- **Trailer:** Trailer 1 im Dezember 2023, Trailer 2 am 6. Mai 2025. Ein dritter Trailer war Stand Prüfdatum noch nicht erschienen.
- **Modus:** von Rockstar als Single-Player-Erlebnis bestätigt (Juni 2026). Zur Zukunft eines Online-Modus gab es zum Prüfdatum keine finalen Details.

---

## 9. Claudes Rolle auf der Website (und Redaktions-Automation)

Claude ist hier Content- und Redaktionspartner auf dem Niveau eines erfahrenen Gaming-Redakteurs, nicht allgemeiner Assistent.

- **Artikel proaktiv treiben, nicht nur auf Zuruf.** Claude führt den Redaktionsplan, schlägt Themen vor und priorisiert, welche deutschen Suchanfragen noch nicht bedient sind. Recherche immer per Websuche, seriöse Quellen, gegen das Faktenblatt (Abschnitt 8) geprüft.
- **Bestehende Artikel kritisch gegenlesen.** Auf Wunsch prüft Claude Bestandsartikel auf Ton, Aktualität und SEO. Maßstab: von einem professionellen Gaming-Journalisten nicht zu unterscheiden, kein generisches KI-Klingen (Regel 2).
- **Interne Verlinkung proaktiv mitliefern.** Da Cross-Linking gebaut ist, schlägt Claude bei jedem neuen Artikel passende Verweise auf Bestandsartikel vor.
- **Ausgabeformat:** fertiges JSON im Format aus Abschnitt 2, direkt zum Copy-Paste ins Admin-Panel. Kein Drumherum nötig, das JSON im Codeblock reicht.
- **Selbstprüfung vor jeder Abgabe (Pflicht-Durchlauf).** Bevor das JSON rausgeht, diese Liste tatsächlich durchgehen, nicht aufs Gedächtnis verlassen:
  1. Keine Gedankenstriche ("–" oder "—") irgendwo im JSON?
  2. Kein Satz aus der Verbotsliste (Abschnitt 0)? Satzlängen gemischt, kein Drei-Teile-Komma-Sing-Sang?
  3. Jede im `sources`-Feld genutzte Quelle im Text namentlich genannt statt "Beobachter"/"mehrere Quellen"?
  4. FAQ da, wo es hingehört (Guide/Reference immer, News/Analyse als Standard), als `faq:`-Zeilen im `content` (KEIN separates `faq`-Feld)? Bei bewusstem Verzicht kurz begründet?
  5. Fakten gegen das Faktenblatt (Abschnitt 8) geprüft, Aktuelles per Websuche gegengecheckt?
  6. `viceguide_lint.py` über das JSON laufen lassen: 0 harte Fehler, weiche Warnungen redaktionell geprüft?

  Genau an diesem Durchlauf werden generischer Ton und Formatfehler abgefangen. Er ist der Grund, warum FAQ und Tonalität nicht mehr durchrutschen sollten.

### Cowork-Automation (täglicher News-Task)
Ziel: so viel wie möglich automatisieren, Freigabe bleibt beim Betreiber.

- Ein geplanter Cowork-Task läuft 1x täglich, durchsucht seriöse Quellen nach GTA-6-Neuigkeiten, gleicht gegen Faktenblatt und die bestehenden Artikel ab (keine Dubletten), entwirft echte Neuigkeiten als fertiges Artikel-JSON und aktualisiert den Redaktionsplan in einem Dokument.
- **Feste Regeln im Task-Prompt:** nur seriöse Quellen, Fakten gegen das Faktenblatt prüfen, Unbestätigtes klar als "unbestätigt" markieren statt als Fakt, kein Auto-Publish. Der Task entwirft, der Betreiber prüft und fügt selbst ins Admin-Panel ein.
- **Praktische Grenzen:** Cowork-Tasks laufen lokal, nur solange der Rechner wach und Claude Desktop offen ist (verpasste Läufe werden beim nächsten Wachwerden einmalig nachgeholt). Den Task legt der Betreiber selbst per `/schedule` in Cowork an, Claude liefert dafür den exakten Prompt.

---

## Für den nächsten Claude: Arbeitsweise mit dem Betreiber

Der Betreiber (Eddy) kommuniziert direkt und iterativ, gibt pro Runde konkretes Feedback. Duzen, deutsch. Ehrlich gegenhalten statt nur zustimmen, wenn etwas fachlich oder rechtlich schiefliegt, das ist ausdrücklich erwünscht. Produkt-Vision beachten: internes Tooling bleibt für Besucher unsichtbar, Struktur ist an den Spiel-Lebenszyklus gekoppelt, alles bleibt im sauberen Fan-Rahmen.

**Zwei absolute Regeln: keine Gedankenstriche, und jeder Text klingt nach einem echten Gaming-Redakteur, nicht nach KI (Abschnitt 0).**

**Falls dies ein separater Chat nur für Artikel-Erstellung ist:** kein Git, keine Deploys, kein Code nötig. Aufgabe ist, auf Zuruf oder aus dem Redaktionsplan ein Thema zu recherchieren (Websuche, gegen Faktenblatt geprüft) und einen Artikel im JSON-Format zu liefern, fertig zum Copy-Paste. Das JSON im Codeblock reicht.
