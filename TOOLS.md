# TOOLS.md: ViceGuide Werkzeuge und Design-Standards

> Begleitdokument zu CLAUDE.md (Website) und SOCIAL.md (Instagram). Hier stehen die festen Design-Standards für Instagram-Karussells und Artikelbilder. Das eigentliche Produktions-Tool (ViceGuide-Generator) ist noch in Arbeit und wird hier ergänzt, sobald es bereit ist. Projektregel gilt auch hier: keine Gedankenstriche, weder "–" noch "—", nutze Komma, Doppelpunkt oder Punkt.
>
> Stand: Juli 2026.

---

## 1. Überblick

Dieses Dokument hält aktuell zwei feste Design-Standards fest:
- den **Karussell-Standard** für Instagram (Abschnitt 2),
- das **Artikelbild-System** mit Temperatur-Farbsystem (Abschnitt 3).

Der **ViceGuide-Generator**, das HTML-Tool zum Bauen von Karussells und Thumbnails, ist noch in Arbeit. Sobald er bereit ist, kommen Bedienung, Layout-Varianten und Übergabe-Workflow hier als eigener Abschnitt dazu. Bis dahin bewusst nicht dokumentiert, um keinen veralteten Stand festzuschreiben.

Wichtig vorweg, damit nichts durcheinandergerät: Website und Karussell nutzen **unterschiedliche Fonts**. Die Website (index.html) läuft auf Oswald, Inter und Space Mono. Das Karussell hat sein eigenes Font-Set (Abschnitt 2). Nie verwechseln.

---

## 2. Karussell-Standard (Instagram, 1080x1350)

Fester Standard für ViceGuide-Karussell-Slides. Immer so anwenden, außer es wird ausdrücklich abgewichen.

- **Format:** 1080x1350 px (4:5).
- **Hintergrund:** das echte Website-Wallpaper (Palmen bei Sonnenuntergang), nach oben nahtlos verlängert mit einem dunkelvioletten Verlauf. Keine programmatisch gezeichneten Palmen.
- **Headline:** TeX Gyre Heros Cn Bold, alles Großbuchstaben. Zweite Zeile in Hot Pink `#FF2D95` als Akzent.
- **Fließtext:** Poppins.
- **Labels, Tag-Zeile, Footer, Chips:** DejaVu Sans Mono, alles Großbuchstaben, weite Laufweite.
- **Header:** VICEGUIDE-Wortzeichen links, Fortschrittspunkte rechts (aktuelle Slide pink gefüllt).
- **Chips:** Pill-Badges am unteren Rand des Inhaltsblocks.
- **Rahmen:** dünne, helle Linie, leicht vom Rand eingerückt.
- **Cover-Slide:** großes `VI.`-Textzeichen statt Logo-Bild.

**Markenfarben (projektweit):** Hot Pink `#FF2D95` (Akzent), Creme `#FDF3E6` (Primärtext), Dunkelviolett `#1A0B2E` (Grundton/Hintergrundbasis).

---

## 3. Artikelbild-System

Regelwerk für die eigentlichen Artikelbilder. Diese Bilder erzeugt Claude nicht selbst, sondern schreibt fertige, copy-fertige Prompts für ChatGPT oder Gemini.

### Stil
- Illustrierter Keyart-Look: hell, satt, gesättigt.
- Symbolische Objekte oder Orte, **keine menschlichen Figuren**.
- **Kein Text und keine Logos ins Bild eingebrannt.**
- **Kein Rockstar-Material** (keine Key-Art, kein VI-Logo, keine Charakter-Illustrationen).

### Temperatur-Farbsystem (Farbton nach Kategorie)
- **Warm** (Orange, Rosé, Amber): News und Release.
- **Kühl** (Türkis, Aqua, Hellblau): Plattform und Technik.
- **Violett/Magenta:** Story, Charaktere, Online.

### Weitere feste Regeln
- Das cremefarbene VICEGUIDE.DE-Wortzeichen wird nachträglich hinzugefügt, **nicht in den Prompt** geschrieben.
- Jeder Bild-Prompt ist **vollständig in sich geschlossen und copy-fertig**. Keine abgetrennten Stil-Anhänge, die erst manuell zusammengesetzt werden müssen.
- **Dunkle und fotorealistische Bilder sind für Artikel-Thumbnails raus.** Der helle Creme-Hintergrund der Seite mit dunkelblauer Schrift verträgt sich nicht mit dunklen Bildern.
- Das Artikel-Layout zeigt die Headline bereits über dem Bild, Text im Bild wäre bei Artikeln also doppelt. Volle Textbehandlung ist **nur für Instagram-Content** gedacht.

### Rechtlicher Rahmen (kurz, Details in CLAUDE.md)
Weder Rockstar-Material noch fremde redaktionelle Grafiken werden durch Quellenangabe nutzbar. Abgeleitete Bearbeitung eines womöglich lizenzierten Assets trägt dasselbe rechtliche Risiko wie das Original. Der saubere Weg ist immer eine wirklich eigene Neuschöpfung.

---

## 4. Ideen-Backlog

- **GTA-6-Faktenblatt:** umgesetzt als feste Sektion in CLAUDE.md, damit Zahlen zwischen Website und Instagram nie auseinanderdriften. Kein eigenes Tool.
- **Content-Gap-Tracker:** zurückgestellt. Ein zweites HTML-Tool bringt aktuell keinen Traffic. Erst sinnvoll, wenn Content-Menge und SEO laufen.
