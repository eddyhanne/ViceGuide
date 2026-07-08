<?php
/*
 * EINMAL-WERKZEUG, nach Gebrauch wieder aus dem Repo entfernen (git rm),
 * sonst kommt es beim naechsten Deploy automatisch zurueck.
 *
 * Traegt interne Artikel-Verweise ("[[artikel-id|Anzeigetext]]") an vom
 * Redakteur sorgfaeltig ausgewaehlten, thematisch passenden Stellen im
 * content_json bestehender Artikel nach.
 *
 * Sicherheitsnetz: pro Artikel wird der aktuelle Datenbank-Inhalt exakt
 * mit dem Ausgangstext verglichen, auf dessen Basis der Verweis eingebaut
 * wurde. Weicht der aktuelle Inhalt davon ab (Artikel wurde zwischenzeitlich
 * anderweitig bearbeitet), wird dieser Artikel uebersprungen statt die
 * zwischenzeitliche Aenderung zu ueberschreiben. Admin-geschuetzt, gefahrlos
 * mehrfach aufrufbar (bereits aktualisierte Artikel werden erkannt und
 * uebersprungen).
 *
 * Aufruf: einmal im Browser oeffnen (eingeloggt im Editiermodus), Ergebnis
 * ablesen, danach diese Datei wieder loeschen.
 */

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

[$pdo, $cfg] = vg_db();
vg_require_admin($cfg);

$patch = json_decode('{
  "plattformen-zu-release": {
    "expected_current": [
      "Bestätigt sind ausschließlich PlayStation 5 und Xbox Series X/S. Es gibt keine Version für PS4 oder Xbox One, GTA 6 ist damit anders als so manches Rockstar Spiel zuvor kein Cross Generation Titel, sondern konsequent auf die aktuelle Konsolengeneration zugeschnitten.",
      "Auch eine Nintendo Switch 2 Version ist trotz des starken Verkaufsstarts der Konsole nicht geplant. Take Two hat das explizit verneint. Technisch macht das Sinn: die Switch 2 ist trotz des großen Sprungs gegenüber dem Vorgänger ein Hybrid Gerät mit mobilem Chipsatz, das unter der reinen GPU und Speicherleistung von PS5 und Xbox Series X liegt, was bei den dichten NPC Massen und dem dynamischen Wettersystem von GTA 6 schnell zum Flaschenhals würde.",
      "Sony hat zusätzlich PS5 Pro Unterstützung bestätigt, ohne bislang Details zu den konkreten Verbesserungen zu nennen. Eine PC Version ist ausdrücklich nicht Teil des Launches, dazu gibt es einen eigenen Artikel auf ViceGuide.",
      "Für alle, die sich fragen, ob ihre vorhandene Konsole reicht: Standard PS5 und Xbox Series X/S genügen für den Basisbetrieb, laut offiziellem Trailer Hinweis wurde das gesamte Material für Trailer 2 sogar auf einer Standard PS5 ohne Pro Modell aufgenommen.",
      "faq:Kommt GTA 6 auch für PS4 oder Xbox One?|Nein, GTA 6 erscheint ausschließlich für die aktuelle Konsolengeneration, PS5 und Xbox Series X/S.",
      "faq:Gibt es eine Nintendo Switch 2 Version?|Nein, Take Two hat eine Switch 2 Version explizit ausgeschlossen.",
      "faq:Unterstützt GTA 6 die PS5 Pro?|Ja, Sony hat PS5 Pro Unterstützung bestätigt, Details zu den genauen Verbesserungen stehen aber noch aus."
    ],
    "new": [
      "Bestätigt sind ausschließlich PlayStation 5 und Xbox Series X/S. Es gibt keine Version für PS4 oder Xbox One, GTA 6 ist damit anders als so manches Rockstar Spiel zuvor kein Cross Generation Titel, sondern konsequent auf die aktuelle Konsolengeneration zugeschnitten.",
      "Auch eine Nintendo Switch 2 Version ist trotz des starken Verkaufsstarts der Konsole nicht geplant. Take Two hat das explizit verneint. Technisch macht das Sinn: die Switch 2 ist trotz des großen Sprungs gegenüber dem Vorgänger ein Hybrid Gerät mit mobilem Chipsatz, das unter der reinen GPU und Speicherleistung von PS5 und Xbox Series X liegt, was bei den dichten NPC Massen und dem dynamischen Wettersystem von GTA 6 schnell zum Flaschenhals würde.",
      "Sony hat zusätzlich PS5 Pro Unterstützung bestätigt, ohne bislang Details zu den konkreten Verbesserungen zu nennen. Eine PC Version ist ausdrücklich nicht Teil des Launches, wann sie realistisch kommen könnte, ordnen wir in [[wann-pc-version|unserem eigenen Artikel zur PC-Version]] ein.",
      "Für alle, die sich fragen, ob ihre vorhandene Konsole reicht: Standard PS5 und Xbox Series X/S genügen für den Basisbetrieb, laut offiziellem Trailer Hinweis wurde das gesamte Material für Trailer 2 sogar auf einer Standard PS5 ohne Pro Modell aufgenommen.",
      "faq:Kommt GTA 6 auch für PS4 oder Xbox One?|Nein, GTA 6 erscheint ausschließlich für die aktuelle Konsolengeneration, PS5 und Xbox Series X/S.",
      "faq:Gibt es eine Nintendo Switch 2 Version?|Nein, Take Two hat eine Switch 2 Version explizit ausgeschlossen.",
      "faq:Unterstützt GTA 6 die PS5 Pro?|Ja, Sony hat PS5 Pro Unterstützung bestätigt, Details zu den genauen Verbesserungen stehen aber noch aus."
    ]
  },
  "wann-pc-version": {
    "expected_current": [
      "Rockstar hat für GTA 6 bisher ausschließlich PS5 und Xbox Series X/S bestätigt. Zur PC Version gibt es kein Wort, weder zu einem Fenster noch zu Systemanforderungen. Wer im Netz konkrete PC Daten oder Preise liest, bekommt Vermutung als Fakt verkauft.",
      "### Was die Vergangenheit nahelegt",
      "Rockstar lässt PC Spieler traditionell warten. GTA 4 kam rund sieben Monate nach den Konsolenversionen auf den PC, bei GTA 5 waren es rund 18 bis 19 Monate über zwei Konsolengenerationen hinweg, bei Red Dead Redemption 2 etwa 13 Monate. Übertragen auf GTA 6 würde das ein Fenster zwischen Ende 2027 und Anfang 2028 bedeuten.",
      "Take Two CEO Strauss Zelnick hat im Mai 2026 selbst eingeräumt, dass PC bei einem großen Launch für 45 bis 50 Prozent der Verkäufe stehen kann, gleichzeitig aber betont, Rockstar richte sich zuerst an die Konsole als \\"Kernpublikum\\". Das erklärt, warum der PC Release wirtschaftlich Sinn ergibt, aber bewusst später kommt, ein zweiter Verkaufsschub, wenn der Konsolenhype etwas abflaut.",
      "Einzelne Leaker, etwa unter dem Namen Detective Seeds, spekulierten zwischenzeitlich über einen deutlich früheren Termin im Februar 2027. Solche Angaben stammen aus anonymen Quellen ohne offizielle Bestätigung und sollten entsprechend skeptisch behandelt werden.",
      "### Unsere Einschätzung",
      "Realistisch ist ein PC Release irgendwann zwischen Herbst 2027 und Anfang 2028, mit ziemlicher Sicherheit inklusive höherer Framerate, Ultrawide Unterstützung und dem gewohnten Modding Ökosystem, sobald es soweit ist.",
      "faq:Ist eine PC Version von GTA 6 offiziell bestätigt?|Nein, Rockstar hat bislang ausschließlich PS5 und Xbox Series X/S bestätigt, zu einer PC Version gibt es keine offizielle Aussage.",
      "faq:Wann könnte GTA 6 für den PC erscheinen?|Nach Rockstars bisherigem Muster ist ein Fenster zwischen Ende 2027 und Anfang 2028 realistisch, ein fixer Termin steht aber nicht fest.",
      "faq:Wird die PC Version Mod Unterstützung bekommen?|Nicht bestätigt, aber angesichts der langen Modding Geschichte von GTA 5 auf dem PC ist genau das zu erwarten."
    ],
    "new": [
      "Rockstar hat für GTA 6 bisher ausschließlich PS5 und Xbox Series X/S bestätigt, die volle Übersicht dazu gibt es in [[plattformen-zu-release|unserem Artikel zu den bestätigten Plattformen]]. Zur PC Version gibt es kein Wort, weder zu einem Fenster noch zu Systemanforderungen. Wer im Netz konkrete PC Daten oder Preise liest, bekommt Vermutung als Fakt verkauft.",
      "### Was die Vergangenheit nahelegt",
      "Rockstar lässt PC Spieler traditionell warten. GTA 4 kam rund sieben Monate nach den Konsolenversionen auf den PC, bei GTA 5 waren es rund 18 bis 19 Monate über zwei Konsolengenerationen hinweg, bei Red Dead Redemption 2 etwa 13 Monate. Übertragen auf GTA 6 würde das ein Fenster zwischen Ende 2027 und Anfang 2028 bedeuten.",
      "Take Two CEO Strauss Zelnick hat im Mai 2026 selbst eingeräumt, dass PC bei einem großen Launch für 45 bis 50 Prozent der Verkäufe stehen kann, gleichzeitig aber betont, Rockstar richte sich zuerst an die Konsole als \\"Kernpublikum\\". Das erklärt, warum der PC Release wirtschaftlich Sinn ergibt, aber bewusst später kommt, ein zweiter Verkaufsschub, wenn der Konsolenhype etwas abflaut.",
      "Einzelne Leaker, etwa unter dem Namen Detective Seeds, spekulierten zwischenzeitlich über einen deutlich früheren Termin im Februar 2027. Solche Angaben stammen aus anonymen Quellen ohne offizielle Bestätigung und sollten entsprechend skeptisch behandelt werden.",
      "### Unsere Einschätzung",
      "Realistisch ist ein PC Release irgendwann zwischen Herbst 2027 und Anfang 2028, mit ziemlicher Sicherheit inklusive höherer Framerate, Ultrawide Unterstützung und dem gewohnten Modding Ökosystem, sobald es soweit ist.",
      "faq:Ist eine PC Version von GTA 6 offiziell bestätigt?|Nein, Rockstar hat bislang ausschließlich PS5 und Xbox Series X/S bestätigt, zu einer PC Version gibt es keine offizielle Aussage.",
      "faq:Wann könnte GTA 6 für den PC erscheinen?|Nach Rockstars bisherigem Muster ist ein Fenster zwischen Ende 2027 und Anfang 2028 realistisch, ein fixer Termin steht aber nicht fest.",
      "faq:Wird die PC Version Mod Unterstützung bekommen?|Nicht bestätigt, aber angesichts der langen Modding Geschichte von GTA 5 auf dem PC ist genau das zu erwarten."
    ]
  },
  "release-endlich-bekannt": {
    "expected_current": [
      "Rockstar Games hat den Release über die eigene Newswire bestätigt. Der Weg dahin war lang: Ursprünglich stand ein Fenster für 2025 im Raum, danach wurde ein Termin im Mai 2026 genannt, ehe Rockstar Ende 2025 noch einmal nachjustierte und den 19. November 2026 als finalen Termin ausgab. Take Two bestätigte den Termin während des Earnings Calls zum vierten Fiskalquartal am 21. Mai 2026 erneut, CEO Strauss Zelnick betonte gegenüber Investoren, dass die zusätzliche Zeit reine Qualitätsgründe habe, nicht Kalenderdruck.",
      "Für Take Two ist der Termin geschäftlich enorm. Der Konzern hat seine Prognose für das Geschäftsjahr 2027 explizit auf diesen Launch aufgebaut, mit Net Bookings von rund 8,0 bis 8,2 Milliarden Dollar. Das zeigt, wie sehr die gesamte kurzfristige Finanzplanung an diesem einen Spiel hängt.",
      "Bestätigt sind zum Release nur zwei Plattformen: PS5 und Xbox Series X/S. Weder die alten Konsolen der letzten Generation noch eine Switch 2 Version sind geplant, eine PC Fassung folgt Rockstar Tradition zufolge erst deutlich später.",
      "Der 19. November liegt bewusst eine Woche vor Black Friday, mitten in der umsatzstärksten Phase des Handelsjahres. Nach zwei Verschiebungen und Jahren von Leaks und Spekulationen ist das jetzt kein Gerücht mehr, sondern ein fixer Termin von Rockstar selbst."
    ],
    "new": [
      "Rockstar Games hat den Release über die eigene Newswire bestätigt. Der Weg dahin war lang: Ursprünglich stand ein Fenster für 2025 im Raum, danach wurde ein Termin im Mai 2026 genannt, ehe Rockstar Ende 2025 noch einmal nachjustierte und den 19. November 2026 als finalen Termin ausgab. Take Two bestätigte den Termin während des Earnings Calls zum vierten Fiskalquartal am 21. Mai 2026 erneut, CEO Strauss Zelnick betonte gegenüber Investoren, dass die zusätzliche Zeit reine Qualitätsgründe habe, nicht Kalenderdruck.",
      "Für Take Two ist der Termin geschäftlich enorm. Der Konzern hat seine Prognose für das Geschäftsjahr 2027 explizit auf diesen Launch aufgebaut, mit Net Bookings von rund 8,0 bis 8,2 Milliarden Dollar. Das zeigt, wie sehr die gesamte kurzfristige Finanzplanung an diesem einen Spiel hängt.",
      "Bestätigt sind zum Release nur zwei Plattformen: PS5 und Xbox Series X/S, Details dazu gibt es in [[plattformen-zu-release|unserer Plattformen-Übersicht]]. Weder die alten Konsolen der letzten Generation noch eine Switch 2 Version sind geplant, eine PC Fassung folgt Rockstar Tradition zufolge erst deutlich später, wann genau, schätzen wir in [[wann-pc-version|unserem Artikel zur PC-Version]] ein.",
      "Der 19. November liegt bewusst eine Woche vor Black Friday, mitten in der umsatzstärksten Phase des Handelsjahres. Nach zwei Verschiebungen und Jahren von Leaks und Spekulationen ist das jetzt kein Gerücht mehr, sondern ein fixer Termin von Rockstar selbst."
    ]
  },
  "hauptprotagonisten": {
    "expected_current": [
      "### Lucia Caminos",
      "Lucia ist die erste durchgängig spielbare weibliche Protagonistin der gesamten Hauptreihe. Rockstar beschreibt sie als jemanden, dem das Kämpfen von ihrem Vater praktisch von klein auf beigebracht wurde. Zu Beginn der Story sitzt sie in einem Gefängnis in Leonida, nach eigener Aussage, weil sie ihre Familie geschützt hat. Ihr Auftritt in Trailer 1 war 2023 der eigentliche Paukenschlag der ganzen Enthüllung.",
      "### Jason Duval",
      "Jason wuchs nach Rockstars Beschreibung mit einem schwierigen Umfeld und Kleinkriminalität auf und ging deshalb zur Armee, um dem zu entkommen. Nach der Rückkehr gerät er aber erneut in kriminelle Kreise. Eine Szene mit einem Gefängniswärter, der ihn fragt, ob er ihn nicht schon einmal dort gesehen habe, deutet zusätzlich auf eine eigene Vorgeschichte hinter Gittern hin, ohne dass Rockstar das bislang konkretisiert hätte.",
      "### Was die beiden verbindet",
      "Trailer 2 zeigt, wie Jason Lucia abholt und die beiden sich auf einen Neuanfang einlassen wollen, kurz bevor ein eigentlich simpler Coup schiefgeht und sie in eine landesweite Verschwörung hineinzieht. Offiziell bestätigt ist, dass beide im Solospiel wechselbar sind, ähnlich wie schon Michael, Franklin und Trevor in GTA 5, nur eben als festes Paar statt als lose Dreiergruppe.",
      "Für die Einordnung bleibt festzuhalten, dass viele Details zu Motiven und Vorgeschichte weiterhin Interpretation der Trailer Szenen sind, nicht wörtliches Rockstar Zitat. Genau da wird es beim Release erst richtig interessant, wenn aus Andeutungen echte Story wird."
    ],
    "new": [
      "### Lucia Caminos",
      "Lucia ist die erste durchgängig spielbare weibliche Protagonistin der gesamten Hauptreihe. Rockstar beschreibt sie als jemanden, dem das Kämpfen von ihrem Vater praktisch von klein auf beigebracht wurde. Zu Beginn der Story sitzt sie in einem Gefängnis in Leonida, nach eigener Aussage, weil sie ihre Familie geschützt hat. Ihr Auftritt in Trailer 1 war 2023 der eigentliche Paukenschlag der ganzen Enthüllung.",
      "### Jason Duval",
      "Jason wuchs nach Rockstars Beschreibung mit einem schwierigen Umfeld und Kleinkriminalität auf und ging deshalb zur Armee, um dem zu entkommen. Nach der Rückkehr gerät er aber erneut in kriminelle Kreise. Eine Szene mit einem Gefängniswärter, der ihn fragt, ob er ihn nicht schon einmal dort gesehen habe, deutet zusätzlich auf eine eigene Vorgeschichte hinter Gittern hin, ohne dass Rockstar das bislang konkretisiert hätte.",
      "### Was die beiden verbindet",
      "Trailer 2 zeigt, wie Jason Lucia abholt und die beiden sich auf einen Neuanfang einlassen wollen, kurz bevor ein eigentlich simpler Coup schiefgeht und sie in eine landesweite Verschwörung hineinzieht. Offiziell bestätigt ist, dass beide im Solospiel wechselbar sind, ähnlich wie schon Michael, Franklin und Trevor in GTA 5, nur eben als festes Paar statt als lose Dreiergruppe. Wie sich ihre Beziehung im Detail entwickelt, zeigen wir in [[beziehung-jason-lucia|unserem Artikel zur Beziehung von Jason und Lucia]].",
      "Für die Einordnung bleibt festzuhalten, dass viele Details zu Motiven und Vorgeschichte weiterhin Interpretation der Trailer Szenen sind, nicht wörtliches Rockstar Zitat. Genau da wird es beim Release erst richtig interessant, wenn aus Andeutungen echte Story wird."
    ]
  },
  "beziehung-jason-lucia": {
    "expected_current": [
      "Trailer 2 zeigt die Beziehung deutlich intimer als noch der erste Trailer. Jason holt Lucia aus dem Gefängnis ab, danach folgen ruhige Momente auf einem Steg mit Drinks und Gesprächen über einen gemeinsamen Neuanfang, aber auch eine Yacht Szene, in der Lucia in einem goldenen Kleid zu sehen ist. Diese Wechsel zwischen ruhigen und actionreichen Szenen sind bewusst gesetzt, sie sollen zeigen, dass hier nicht nur ein Verbrecherduo, sondern auch ein echtes Paar im Mittelpunkt steht.",
      "Gleichzeitig bleibt die kriminelle Ebene omnipräsent. Die beiden werden bei einem Fluchtversuch aus einem Fenster gezeigt, während ein Polizeihubschrauber kreist, dazu Schusswechsel und eine Verfolgungsjagd. Die Botschaft ist klar, Liebe und gemeinsames Verbrechen lassen sich bei den beiden nicht trennen.",
      "Ein zusätzliches Detail aus der Community Analyse: Eine Szene zeigt eine Frau, die Lucia ähnelt, bei einer Sozialstunde, was auf eine Zeit zwischen zwei kriminellen Phasen hindeuten könnte. Offiziell eingeordnet hat Rockstar dieses Detail bislang nicht.",
      "Spielerisch bestätigt ist bisher nur, dass beide im Solomodus wechselbar sind. Ob es darüber hinaus Beziehungsmechaniken gibt, etwa Entscheidungen, die das Verhältnis der beiden beeinflussen, ist reine Spekulation auf Basis der emotional aufgeladenen Trailer Szenen, keine bestätigte Spielmechanik.",
      "Der Vergleich mit Bonnie und Clyde drängt sich auch deshalb auf, weil das Erscheinungsdatum der ersten Trailer Ankündigung im Mai 2026 zufällig mit dem Jahrestag von Bonnie Parkers Beerdigung zusammenfiel, ein popkulturelles Detail, das viele Fans zusätzlich zur Parallele inspiriert hat, ohne dass es eine offizielle Verbindung dazu gibt."
    ],
    "new": [
      "Trailer 2 zeigt die Beziehung deutlich intimer als noch der erste Trailer. Jason holt Lucia aus dem Gefängnis ab, danach folgen ruhige Momente auf einem Steg mit Drinks und Gesprächen über einen gemeinsamen Neuanfang, aber auch eine Yacht Szene, in der Lucia in einem goldenen Kleid zu sehen ist. Diese Wechsel zwischen ruhigen und actionreichen Szenen sind bewusst gesetzt, sie sollen zeigen, dass hier nicht nur ein Verbrecherduo, sondern auch ein echtes Paar im Mittelpunkt steht.",
      "Gleichzeitig bleibt die kriminelle Ebene omnipräsent. Die beiden werden bei einem Fluchtversuch aus einem Fenster gezeigt, während ein Polizeihubschrauber kreist, dazu Schusswechsel und eine Verfolgungsjagd. Die Botschaft ist klar, Liebe und gemeinsames Verbrechen lassen sich bei den beiden nicht trennen.",
      "Ein zusätzliches Detail aus der Community Analyse: Eine Szene zeigt eine Frau, die Lucia ähnelt, bei einer Sozialstunde, was auf eine Zeit zwischen zwei kriminellen Phasen hindeuten könnte. Offiziell eingeordnet hat Rockstar dieses Detail bislang nicht.",
      "Spielerisch bestätigt ist bisher nur, dass beide im Solomodus wechselbar sind. Ob es darüber hinaus Beziehungsmechaniken gibt, etwa Entscheidungen, die das Verhältnis der beiden beeinflussen, ist reine Spekulation auf Basis der emotional aufgeladenen Trailer Szenen, keine bestätigte Spielmechanik.",
      "Der Vergleich mit Bonnie und Clyde drängt sich auch deshalb auf, weil das Erscheinungsdatum der ersten Trailer Ankündigung im Mai 2026 zufällig mit dem Jahrestag von Bonnie Parkers Beerdigung zusammenfiel, ein popkulturelles Detail, das viele Fans zusätzlich zur Parallele inspiriert hat, ohne dass es eine offizielle Verbindung dazu gibt. Wer Jason und Lucia noch nicht im Detail kennt, findet ein komplettes Porträt beider Figuren in [[hauptprotagonisten|unserem Artikel zu den Hauptprotagonisten]]."
    ]
  },
  "nebencharaktere-im-detail": {
    "expected_current": [
      "### Cal Hampton",
      "Ein Freund von Jason, laut Rockstars eigener Beschreibung eher der Stubenhocker Typ, glücklich mit ein paar Bieren zu Hause oder beim Belauschen von Küstenwache Funk. Auf einem der veröffentlichten Screenshots ist er beim Pool zu sehen, eine der neuen Freizeitaktivitäten des Spiels. Trotz seiner entspannten Art dürfte er als einer der wenigen wirklich vertrauenswürdigen Verbündeten eine Rolle in den Plänen von Jason und Lucia spielen.",
      "### Boobie Ike",
      "Ein Urgestein von Vice City, der es geschafft hat, seine Straßenkarriere in ein halbwegs legitimes Imperium zu verwandeln, mit Immobilien, einem Stripclub und einem Tonstudio. Von außen wirkt er lässig, im Kern ist er aber knallharter Geschäftsmann, der auch vor Drogenhandel über seinen Club nicht zurückschreckt.",
      "### Dre\'Quan Priest",
      "Kein klassischer Gangster, sondern ein Musikproduzent mit Ambitionen, der eng mit Ike zusammenarbeitet. Er hat mit Bae-Luxe und Roxy, die zusammen als Real Dimez auftreten, ein Duo unter Vertrag, das vor allem über virale Videos und Social Media Präsenz bekannt wird, weniger über klassische Radioerfolge.",
      "### Raul Bautista",
      "Ein professioneller Bankräuber, der offenbar mit Jason und Lucia zusammenarbeitet, möglicherweise für größere Heists im späteren Spielverlauf. Raul wird als anpassungsfähig und profitorientiert beschrieben, kein verlässlicher Partner fürs Leben, aber jemand, der an entscheidenden Punkten der Story eine Rolle spielen dürfte.",
      "Daneben taucht im Trailer noch eine namenlose Ermittlerfigur auf, die den beiden offenbar zumindest zeitweise auf den Fersen ist. Wer genau dahintersteckt und wie groß die Rolle wird, ist bislang offen, das wird sich erst mit weiterem Material oder dem Release selbst zeigen."
    ],
    "new": [
      "### Cal Hampton",
      "Ein Freund von Jason, laut Rockstars eigener Beschreibung eher der Stubenhocker Typ, glücklich mit ein paar Bieren zu Hause oder beim Belauschen von Küstenwache Funk. Auf einem der veröffentlichten Screenshots ist er beim Pool zu sehen, eine der neuen Freizeitaktivitäten des Spiels. Trotz seiner entspannten Art dürfte er als einer der wenigen wirklich vertrauenswürdigen Verbündeten eine Rolle in den Plänen von Jason und Lucia spielen.",
      "### Boobie Ike",
      "Ein Urgestein von Vice City, der es geschafft hat, seine Straßenkarriere in ein halbwegs legitimes Imperium zu verwandeln, mit Immobilien, einem Stripclub und einem Tonstudio. Von außen wirkt er lässig, im Kern ist er aber knallharter Geschäftsmann, der auch vor Drogenhandel über seinen Club nicht zurückschreckt.",
      "### Dre\'Quan Priest",
      "Kein klassischer Gangster, sondern ein Musikproduzent mit Ambitionen, der eng mit Ike zusammenarbeitet. Er hat mit Bae-Luxe und Roxy, die zusammen als Real Dimez auftreten, ein Duo unter Vertrag, das vor allem über virale Videos und Social Media Präsenz bekannt wird, weniger über klassische Radioerfolge.",
      "### Raul Bautista",
      "Ein professioneller Bankräuber, der offenbar mit Jason und Lucia zusammenarbeitet, möglicherweise für größere Heists im späteren Spielverlauf. Raul wird als anpassungsfähig und profitorientiert beschrieben, kein verlässlicher Partner fürs Leben, aber jemand, der an entscheidenden Punkten der Story eine Rolle spielen dürfte.",
      "Daneben taucht im Trailer noch eine namenlose Ermittlerfigur auf, die den beiden offenbar zumindest zeitweise auf den Fersen ist. Wer genau dahintersteckt und wie groß die Rolle wird, ist bislang offen, das wird sich erst mit weiterem Material oder dem Release selbst zeigen. Wer Jason und Lucia selbst noch nicht im Detail kennt, dem hilft [[hauptprotagonisten|unser Porträt der beiden Hauptfiguren]] weiter."
    ]
  },
  "trailer-1-analyse": {
    "expected_current": [
      "Eigentlich sollte der Trailer erst am nächsten Morgen per YouTube Premiere online gehen. Weniger als 16 Stunden vorher wurde eine Kopie geleakt, Rockstar reagierte und veröffentlichte den 91 Sekunden langen Clip vorzeitig um 18 Uhr Ostküstenzeit. Innerhalb von 24 Stunden zählte YouTube über 93 Millionen Aufrufe, ein neuer Rekord für die meistgesehene Spiele Ankündigung überhaupt.",
      "### Was der Trailer bestätigt hat",
      "- Rückkehr nach Vice City, diesmal eingebettet in den fiktiven Bundesstaat Leonida, angelehnt an Florida",
      "- Lucia als spielbare Protagonistin, die erste durchgängig spielbare Frau in einem Hauptteil der Reihe",
      "- Ein modernes Setting mit Smartphones und Social Media statt der erwarteten reinen 80er Nostalgie",
      "- Umliegende Regionen im Everglades Stil abseits der Stadt",
      "Musikalisch lief im Hintergrund \\"Love Is a Long Road\\" von Tom Petty, ein Ton, der eher melancholisch und erwachsen wirkt als reißerisch, was zur insgesamt ruhigeren Inszenierung passt. Der Trailer zeigt fast ausschließlich zusammengeschnittene Alltagsszenen aus Leonida, kein Gameplay im klassischen Sinn, dafür jede Menge Atmosphäre.",
      "Die Community ging danach in den Detektiv Modus. Aus Straßenschildern und Nachrichtenmeldungen ließen sich Ortsnamen wie Kelly County, die VCI Flughafen Abkürzung, Port VC und Stockyard herauslesen, außerdem tauchten mit Starfish Island erkennbare Rückgriffe auf das Vice City von 2002 auf. Wer genau hinschaute, erkannte auch schon Hinweise auf Freizeitaktivitäten wie Autotreffen, Stripclubs oder den Thrillbilly Mud Club, ohne dass Rockstar zu dem Zeitpunkt bestätigt hätte, was davon tatsächlich spielbar wird.",
      "Für die Einordnung wichtig: der Trailer selbst enthielt keine einzige Spielszene mit HUD oder Steuerung. Alles, was danach an Spekulation über Mechaniken kursierte, blieb bis zu Trailer 2 im Bereich der Vermutung."
    ],
    "new": [
      "Eigentlich sollte der Trailer erst am nächsten Morgen per YouTube Premiere online gehen. Weniger als 16 Stunden vorher wurde eine Kopie geleakt, Rockstar reagierte und veröffentlichte den 91 Sekunden langen Clip vorzeitig um 18 Uhr Ostküstenzeit. Innerhalb von 24 Stunden zählte YouTube über 93 Millionen Aufrufe, ein neuer Rekord für die meistgesehene Spiele Ankündigung überhaupt.",
      "### Was der Trailer bestätigt hat",
      "- Rückkehr nach Vice City, diesmal eingebettet in den fiktiven Bundesstaat Leonida, angelehnt an Florida",
      "- Lucia als spielbare Protagonistin, die erste durchgängig spielbare Frau in einem Hauptteil der Reihe",
      "- Ein modernes Setting mit Smartphones und Social Media statt der erwarteten reinen 80er Nostalgie",
      "- Umliegende Regionen im Everglades Stil abseits der Stadt",
      "Musikalisch lief im Hintergrund \\"Love Is a Long Road\\" von Tom Petty, ein Ton, der eher melancholisch und erwachsen wirkt als reißerisch, was zur insgesamt ruhigeren Inszenierung passt. Der Trailer zeigt fast ausschließlich zusammengeschnittene Alltagsszenen aus Leonida, kein Gameplay im klassischen Sinn, dafür jede Menge Atmosphäre.",
      "Die Community ging danach in den Detektiv Modus. Aus Straßenschildern und Nachrichtenmeldungen ließen sich Ortsnamen wie Kelly County, die VCI Flughafen Abkürzung, Port VC und Stockyard herauslesen, außerdem tauchten mit Starfish Island erkennbare Rückgriffe auf das Vice City von 2002 auf. Wer genau hinschaute, erkannte auch schon Hinweise auf Freizeitaktivitäten wie Autotreffen, Stripclubs oder den Thrillbilly Mud Club, ohne dass Rockstar zu dem Zeitpunkt bestätigt hätte, was davon tatsächlich spielbar wird. Eine vollständige Sammlung solcher Details aus beiden Trailern gibt es in [[easter-eggs-trailer-details|unserer Easter-Egg-Übersicht]].",
      "Für die Einordnung wichtig: der Trailer selbst enthielt keine einzige Spielszene mit HUD oder Steuerung. Alles, was danach an Spekulation über Mechaniken kursierte, blieb bis zu Trailer 2 im Bereich der Vermutung. Was der zweite Trailer anderthalb Jahre später zusätzlich zeigte, lest ihr in [[trailer-2-analyse|unserer Trailer-2-Analyse]]."
    ]
  },
  "trailer-2-analyse": {
    "expected_current": [
      "Die Informationspause zwischen den beiden Trailern war für Rockstar Verhältnisse extrem lang, rund 17 Monate praktisch ohne offizielle Neuigkeiten. Entsprechend groß war der Ansturm: über 90 Millionen Aufrufe in den ersten 24 Stunden, laut Hollywood Reporter zeitweise sogar über 475 Millionen Views plattformübergreifend.",
      "### Die Story im Kern",
      "Offiziell bestätigt Rockstar nur den groben Rahmen: Jason und Lucia wissen, dass die Chancen gegen sie stehen, doch ein eigentlich einfacher Coup geht schief und zieht die beiden in eine Verschwörung, die sich über ganz Leonida erstreckt. Der Trailer zeigt, wie Jason Lucia aus dem Gefängnis abholt, wo sie laut eigener Aussage für den Schutz ihrer Familie einsitzt. Danach folgen Liebesszenen, gemeinsame Drinks auf einem Steg und Gespräche über einen Neuanfang, aber auch klassische GTA Action wie Schießereien, eine Verfolgungsjagd mit Hubschrauber und eine Explosion mit mehreren Streifenwagen.",
      "### Neue Nebencharaktere",
      "- Cal Hampton, Jasons Kumpel, eher Stubenhocker mit Vorliebe für Bier",
      "- Boobie Ike, Vice City Urgestein mit Stripclub, Immobilien und Tonstudio",
      "- Dre\'Quan Priest, ambitionierter Musikproduzent, arbeitet mit Ike zusammen",
      "- Raul Bautista, professioneller Bankräuber, offenbar als Partner bei Heists im Gespräch",
      "Auch neue Regionen wurden sichtbar: die Leonida Keys als Inselkette im Stil der Florida Keys, die Grassrivers als Everglades artige Feuchtgebiete, Port Gellhorn als Industriehafen und Little Cuba als Viertel nach Vorbild von Miamis Little Havana. Ein an Miamis Metrorail erinnerndes Bahnsystem ist ebenfalls zu erkennen.",
      "Ein Detail für die Akte Spekulation statt Fakt: Kennzeichen im Trailer erinnern an Liberty City und ein Arizona ähnliches Design, was manche als Hinweis auf eine Welt jenseits von Leonida deuten. Offiziell bestätigt ist davon nichts, hier bleibt es bei einer unbestätigten Fan Theorie."
    ],
    "new": [
      "Die Informationspause zwischen den beiden Trailern war für Rockstar Verhältnisse extrem lang, rund 17 Monate praktisch ohne offizielle Neuigkeiten. Entsprechend groß war der Ansturm: über 90 Millionen Aufrufe in den ersten 24 Stunden, laut Hollywood Reporter zeitweise sogar über 475 Millionen Views plattformübergreifend. Was der erste Trailer im Dezember 2023 bereits gezeigt hatte, fassen wir in [[trailer-1-analyse|unserer Trailer-1-Analyse]] zusammen.",
      "### Die Story im Kern",
      "Offiziell bestätigt Rockstar nur den groben Rahmen: Jason und Lucia wissen, dass die Chancen gegen sie stehen, doch ein eigentlich einfacher Coup geht schief und zieht die beiden in eine Verschwörung, die sich über ganz Leonida erstreckt. Der Trailer zeigt, wie Jason Lucia aus dem Gefängnis abholt, wo sie laut eigener Aussage für den Schutz ihrer Familie einsitzt. Danach folgen Liebesszenen, gemeinsame Drinks auf einem Steg und Gespräche über einen Neuanfang, aber auch klassische GTA Action wie Schießereien, eine Verfolgungsjagd mit Hubschrauber und eine Explosion mit mehreren Streifenwagen.",
      "### Neue Nebencharaktere",
      "- Cal Hampton, Jasons Kumpel, eher Stubenhocker mit Vorliebe für Bier",
      "- Boobie Ike, Vice City Urgestein mit Stripclub, Immobilien und Tonstudio",
      "- Dre\'Quan Priest, ambitionierter Musikproduzent, arbeitet mit Ike zusammen",
      "- Raul Bautista, professioneller Bankräuber, offenbar als Partner bei Heists im Gespräch",
      "Auch neue Regionen wurden sichtbar: die Leonida Keys als Inselkette im Stil der Florida Keys, die Grassrivers als Everglades artige Feuchtgebiete, Port Gellhorn als Industriehafen und Little Cuba als Viertel nach Vorbild von Miamis Little Havana. Ein an Miamis Metrorail erinnerndes Bahnsystem ist ebenfalls zu erkennen. Alle bestätigten Regionen im Überblick gibt es in [[die-karte-von-gta-6|unserer Karten-Übersicht zu Leonida]].",
      "Ein Detail für die Akte Spekulation statt Fakt: Kennzeichen im Trailer erinnern an Liberty City und ein Arizona ähnliches Design, was manche als Hinweis auf eine Welt jenseits von Leonida deuten. Offiziell bestätigt ist davon nichts, hier bleibt es bei einer unbestätigten Fan Theorie."
    ]
  },
  "easter-eggs-trailer-details": {
    "expected_current": [
      "### Aus Trailer 1",
      "- Ein Funkturm in den Eröffnungsbildern ähnelt dem höchsten realen Funkturm Floridas, ein typischer Rockstar Verweis auf die reale Vorlage der fiktiven Welt",
      "- NPCs filmen sich gegenseitig mit dem Handy, ein kleines Detail, das viel über die geplante Lebendigkeit der Welt verrät",
      "- Straßenschilder verraten Ortsnamen wie Kelly County, Catalan Boulevard und die VCI Flughafen Abkürzung, lange bevor Rockstar dazu etwas sagte",
      "- Mit Starfish Island taucht eine erkennbare Anspielung auf das Vice City von 2002 auf",
      "### Aus Trailer 2",
      "- Auf einem Fernseher im Hintergrund ist laut Community Analyse ein Boot zu erkennen, das an das Boot von Michael aus GTA 5 erinnert, inklusive der Theorie, es könnte in Leonida wieder auftauchen",
      "- Eine Werbeeinblendung für \\"Phil\'s Ammu-Nation\\" mit dem Spruch, man habe mehr Waffen als das Gesetz erlaubt, wird von vielen als möglicher Bezug zu Phil Cassidy aus dem Original gelesen, bestätigt ist das nicht",
      "- Geldscheine in Nahaufnahmen zeigen Präsidentenmotive aus Red Dead Redemption 2, ein hübsches Universum übergreifendes Detail",
      "- Ein Werbeplakat mit dem Namen \\"Salty\'s Crackers\\" reiht sich in die lange GTA Tradition ein, reale Konsumkultur satirisch zu überzeichnen",
      "- Kennzeichen, die an Liberty City und Arizona erinnern, befeuern die Theorie, dass die Spielwelt größer sein könnte als nur Leonida",
      "Wichtig für die Einordnung: die meisten dieser Punkte stammen aus genauer Community Bildanalyse einzelner Frames, nicht aus offiziellen Rockstar Statements. Manches davon wird sich als reines Detail entpuppen, anderes könnte tatsächlich eine Spur zu Missionen oder wiederkehrenden Figuren sein. Bis Rockstar selbst etwas bestätigt, bleibt es das, was es ist: eine gut begründete Vermutung."
    ],
    "new": [
      "### Aus Trailer 1",
      "- Ein Funkturm in den Eröffnungsbildern ähnelt dem höchsten realen Funkturm Floridas, ein typischer Rockstar Verweis auf die reale Vorlage der fiktiven Welt",
      "- NPCs filmen sich gegenseitig mit dem Handy, ein kleines Detail, das viel über die geplante Lebendigkeit der Welt verrät",
      "- Straßenschilder verraten Ortsnamen wie Kelly County, Catalan Boulevard und die VCI Flughafen Abkürzung, lange bevor Rockstar dazu etwas sagte",
      "- Mit Starfish Island taucht eine erkennbare Anspielung auf das Vice City von 2002 auf",
      "### Aus Trailer 2",
      "- Auf einem Fernseher im Hintergrund ist laut Community Analyse ein Boot zu erkennen, das an das Boot von Michael aus GTA 5 erinnert, inklusive der Theorie, es könnte in Leonida wieder auftauchen",
      "- Eine Werbeeinblendung für \\"Phil\'s Ammu-Nation\\" mit dem Spruch, man habe mehr Waffen als das Gesetz erlaubt, wird von vielen als möglicher Bezug zu Phil Cassidy aus dem Original gelesen, bestätigt ist das nicht",
      "- Geldscheine in Nahaufnahmen zeigen Präsidentenmotive aus Red Dead Redemption 2, ein hübsches Universum übergreifendes Detail",
      "- Ein Werbeplakat mit dem Namen \\"Salty\'s Crackers\\" reiht sich in die lange GTA Tradition ein, reale Konsumkultur satirisch zu überzeichnen",
      "- Kennzeichen, die an Liberty City und Arizona erinnern, befeuern die Theorie, dass die Spielwelt größer sein könnte als nur Leonida",
      "Wichtig für die Einordnung: die meisten dieser Punkte stammen aus genauer Community Bildanalyse einzelner Frames, nicht aus offiziellen Rockstar Statements. Manches davon wird sich als reines Detail entpuppen, anderes könnte tatsächlich eine Spur zu Missionen oder wiederkehrenden Figuren sein. Bis Rockstar selbst etwas bestätigt, bleibt es das, was es ist: eine gut begründete Vermutung. Die vollständigen Analysen beider Trailer gibt es in [[trailer-1-analyse|unserer Trailer-1-Analyse]] und [[trailer-2-analyse|unserer Trailer-2-Analyse]]."
    ]
  },
  "vorbesteller-boni": {
    "expected_current": [
      "Der zentrale Vorbesteller Bonus heißt Vintage Vice City Pack und ist an kein bestimmtes Edition gebunden. Wer GTA 6 vor dem 20. November 2026 kauft, egal ob Standard oder Ultimate, bekommt ihn automatisch dazu.",
      "### Inhalt des Vintage Vice City Pack",
      "- Ein 55er Vapid Stanier im klassischen Amerikana Look",
      "- Die Shore Court Garage nahe Ocean Beach, mit Waffenschrank und Möglichkeit, gestohlene Ware zu verhehlen",
      "- Outfits und Frisuren für Jason und Lucia im Stil des ursprünglichen Vice City von 2002",
      "- Ein exklusives Waffenmuster im Stil von Tommy Vercettis Hawaiihemd",
      "Wer digital über PlayStation Store oder Microsoft Store vorbestellt, bekommt außerdem einen Monat GTA Plus geschenkt, nutzbar sofort im bestehenden GTA Online. Das umfasst unter anderem eine monatliche Ausschüttung von 500.000 GTA Dollar, einen 15 Prozent Bonus auf bestimmte Shark Cards sowie kostenlose oder vergünstigte Fahrzeuge. Wichtig: das ist ein Bonus für das aktuelle GTA Online, kein Vorgeschmack auf einen künftigen GTA 6 Online Modus.",
      "Wer sich fürs digitale Preload interessiert: das startet am 12. November 2026, eine Woche vor Release, damit am Launchtag direkt losgespielt werden kann. Die physische Fassung ist ebenfalls ab dem 12. November erhältlich, enthält aber wie erwähnt nur einen Downloadcode statt einer Disc.",
      "faq:Muss ich die Ultimate Edition kaufen, um den Vorbesteller Bonus zu bekommen?|Nein, das Vintage Vice City Pack gibt es unabhängig von der gewählten Edition, solange vor dem 20. November 2026 vorbestellt wird.",
      "faq:Bekomme ich den Bonus auch bei einer physischen Vorbestellung?|Ja, der Bonus ist an den Kaufzeitpunkt gebunden, nicht an digital oder physisch.",
      "faq:Gilt der geschenkte GTA Plus Monat auch für GTA 6?|Nein, der Monat GTA Plus gilt für das bestehende GTA Online, nicht für einen künftigen GTA 6 Online Modus."
    ],
    "new": [
      "Der zentrale Vorbesteller Bonus heißt Vintage Vice City Pack und ist an kein bestimmtes Edition gebunden. Wer GTA 6 vor dem 20. November 2026 kauft, egal ob Standard oder Ultimate, bekommt ihn automatisch dazu. Was genau in den beiden Editionen selbst steckt, erklären wir in [[editionen-zu-release|unserem Artikel zu den Editionen]].",
      "### Inhalt des Vintage Vice City Pack",
      "- Ein 55er Vapid Stanier im klassischen Amerikana Look",
      "- Die Shore Court Garage nahe Ocean Beach, mit Waffenschrank und Möglichkeit, gestohlene Ware zu verhehlen",
      "- Outfits und Frisuren für Jason und Lucia im Stil des ursprünglichen Vice City von 2002",
      "- Ein exklusives Waffenmuster im Stil von Tommy Vercettis Hawaiihemd",
      "Wer digital über PlayStation Store oder Microsoft Store vorbestellt, bekommt außerdem einen Monat GTA Plus geschenkt, nutzbar sofort im bestehenden GTA Online. Das umfasst unter anderem eine monatliche Ausschüttung von 500.000 GTA Dollar, einen 15 Prozent Bonus auf bestimmte Shark Cards sowie kostenlose oder vergünstigte Fahrzeuge. Wichtig: das ist ein Bonus für das aktuelle GTA Online, kein Vorgeschmack auf einen künftigen GTA 6 Online Modus.",
      "Wer sich fürs digitale Preload interessiert: das startet am 12. November 2026, eine Woche vor Release, damit am Launchtag direkt losgespielt werden kann. Die physische Fassung ist ebenfalls ab dem 12. November erhältlich, enthält aber wie erwähnt nur einen Downloadcode statt einer Disc.",
      "faq:Muss ich die Ultimate Edition kaufen, um den Vorbesteller Bonus zu bekommen?|Nein, das Vintage Vice City Pack gibt es unabhängig von der gewählten Edition, solange vor dem 20. November 2026 vorbestellt wird.",
      "faq:Bekomme ich den Bonus auch bei einer physischen Vorbestellung?|Ja, der Bonus ist an den Kaufzeitpunkt gebunden, nicht an digital oder physisch.",
      "faq:Gilt der geschenkte GTA Plus Monat auch für GTA 6?|Nein, der Monat GTA Plus gilt für das bestehende GTA Online, nicht für einen künftigen GTA 6 Online Modus."
    ]
  },
  "editionen-zu-release": {
    "expected_current": [
      "### Standard Edition, rund 80 Euro",
      "Enthält die komplette Story mit Jason und Lucia in vollem Umfang. Keine Inhalte fehlen hier, es handelt sich um das vollständige Hauptspiel ohne Zusatzinhalte.",
      "### Ultimate Edition, rund 100 Euro",
      "Bringt zusätzlich eine Sammlung an Fahrzeugen, Waffen und Kosmetik mit, freigeschaltet Stück für Stück im Verlauf der Kapitel. Konkret dabei: der 95er Grotti Cheetah, die Dinka Enduro, der Crest Kayak, der 67er Vapid Dominator Buggy samt eigener Garage in Watson Bay, ein Mod Kit für Jasons Vapid Ganado, der Shitzu Squalo mit Waffenkiste, die Hawk & Little Morgan Revolver in Herren und Damen Variante sowie personalisierte Pistolen für beide Hauptfiguren. Dazu kommen exklusive Läden wie Rideout Customs, One Eyed Willie\'s, Stock 305, das Tattoo Studio Electric Fang und ein eigener Salon, außerdem eine exklusive Mission zur Erstürmung eines Gang Verstecks der PTT Youngin$.",
      "Wichtig zu wissen: wer zunächst die Standard Edition kauft, kann das Ultimate Upgrade jederzeit später separat nachkaufen, auch bei der physischen Fassung nach Einlösen des Downloadcodes. Man verpasst also nichts, wenn man sich später umentscheidet.",
      "Eine physische Fassung gibt es zwar im Handel, enthält aber keine Disc, sondern nur einen Downloadcode in der Box. Eine klassische Collector\'s oder Deluxe Edition mit Steelbook oder Merchandise ist zum Launch nicht vorgesehen, wer Sammlerstücke will, muss auf mögliche spätere Angebote hoffen.",
      "faq:Was ist der Unterschied zwischen Standard und Ultimate Edition?|Die Standard Edition enthält die komplette Story ohne Abstriche, die Ultimate Edition legt zusätzlich exklusive Fahrzeuge, Waffen, Läden und eine Bonus Mission obendrauf.",
      "faq:Kann ich später von Standard auf Ultimate upgraden?|Ja, das Ultimate Upgrade lässt sich jederzeit separat nachkaufen, auch bei einer physisch gekauften Fassung nach Einlösen des Downloadcodes.",
      "faq:Gibt es eine physische Version mit Disc?|Nein, die Box enthält nur einen Downloadcode, eine klassische Disc gibt es bei GTA 6 nicht."
    ],
    "new": [
      "### Standard Edition, rund 80 Euro",
      "Enthält die komplette Story mit Jason und Lucia in vollem Umfang. Keine Inhalte fehlen hier, es handelt sich um das vollständige Hauptspiel ohne Zusatzinhalte.",
      "### Ultimate Edition, rund 100 Euro",
      "Bringt zusätzlich eine Sammlung an Fahrzeugen, Waffen und Kosmetik mit, freigeschaltet Stück für Stück im Verlauf der Kapitel. Konkret dabei: der 95er Grotti Cheetah, die Dinka Enduro, der Crest Kayak, der 67er Vapid Dominator Buggy samt eigener Garage in Watson Bay, ein Mod Kit für Jasons Vapid Ganado, der Shitzu Squalo mit Waffenkiste, die Hawk & Little Morgan Revolver in Herren und Damen Variante sowie personalisierte Pistolen für beide Hauptfiguren. Dazu kommen exklusive Läden wie Rideout Customs, One Eyed Willie\'s, Stock 305, das Tattoo Studio Electric Fang und ein eigener Salon, außerdem eine exklusive Mission zur Erstürmung eines Gang Verstecks der PTT Youngin$. Eine sortenreine Übersicht, was davon offiziell bestätigt ist, gibt es in [[bestaetigte-fahrzeuge-waffen-charaktere|unserem Artikel zu bestätigten Fahrzeugen, Waffen und Charakteren]].",
      "Wichtig zu wissen: wer zunächst die Standard Edition kauft, kann das Ultimate Upgrade jederzeit später separat nachkaufen, auch bei der physischen Fassung nach Einlösen des Downloadcodes. Man verpasst also nichts, wenn man sich später umentscheidet.",
      "Eine physische Fassung gibt es zwar im Handel, enthält aber keine Disc, sondern nur einen Downloadcode in der Box. Eine klassische Collector\'s oder Deluxe Edition mit Steelbook oder Merchandise ist zum Launch nicht vorgesehen, wer Sammlerstücke will, muss auf mögliche spätere Angebote hoffen.",
      "faq:Was ist der Unterschied zwischen Standard und Ultimate Edition?|Die Standard Edition enthält die komplette Story ohne Abstriche, die Ultimate Edition legt zusätzlich exklusive Fahrzeuge, Waffen, Läden und eine Bonus Mission obendrauf.",
      "faq:Kann ich später von Standard auf Ultimate upgraden?|Ja, das Ultimate Upgrade lässt sich jederzeit separat nachkaufen, auch bei einer physisch gekauften Fassung nach Einlösen des Downloadcodes.",
      "faq:Gibt es eine physische Version mit Disc?|Nein, die Box enthält nur einen Downloadcode, eine klassische Disc gibt es bei GTA 6 nicht."
    ]
  },
  "bestaetigte-fahrzeuge-waffen-charaktere": {
    "expected_current": [
      "### Charaktere",
      "Fest bestätigt sind bislang Jason Duval und Lucia Caminos als Hauptprotagonisten, dazu aus Trailer 2 die Nebenfiguren Cal Hampton, Boobie Ike, Dre\'Quan Priest und Raul Bautista, außerdem das Musik Duo Bae-Luxe und Roxy, das unter dem Namen Real Dimez auftritt. Eine Werbeeinblendung für \\"Phil\'s Ammu-Nation\\" lässt eine Verbindung zu Phil Cassidy aus dem Original Vice City vermuten, das hat Rockstar aber nie bestätigt, hier bleibt es Spekulation.",
      "### Fahrzeuge",
      "Offiziell mit Namen versehen hat Rockstar bisher vor allem die Fahrzeuge aus der Ultimate Edition und dem Vorbesteller Paket: den 95er Grotti Cheetah, die Dinka Enduro, den Crest Kayak, den 67er Vapid Dominator Buggy, den Shitzu Squalo sowie den 55er Vapid Stanier aus dem Vintage Vice City Pack. Darüber hinaus kursieren in Fan Datenbanken deutlich längere Listen mit über 100 Fahrzeugen, die aus Trailern und Screenshots herausgelesen wurden, darunter Marken wie Pfister, Invetero, Ubermacht, Annis, Benefactor und Karin. Diese Listen sind Community Arbeit, keine offizielle Rockstar Liste, auch wenn viele Details darin plausibel und mit Trailer Screenshots belegt sind.",
      "### Waffen",
      "Bei Waffen hält sich Rockstar noch bedeckter. Konkret benannt sind die Hawk & Little Morgan Revolver aus der Ultimate Edition in einer Herren und Damen Variante mit Vice City Optik, dazu personalisierte Pistolen, Jasons Girardi ES9 und Lucias Klose K17. Aus dem Vintage Vice City Pack kommt außerdem ein Waffenmuster im Stil von Tommy Vercettis Hawaiihemd. Eine vollständige Waffenliste hat Rockstar bislang nicht veröffentlicht.",
      "Kurz gesagt: alles, was im Ultimate Edition Katalog oder im Vorbesteller Paket auftaucht, ist zu hundert Prozent bestätigt. Alles darüber hinaus stammt aus Trailer Standbildern und Community Recherche, spannend, aber mit Vorsicht zu genießen."
    ],
    "new": [
      "### Charaktere",
      "Fest bestätigt sind bislang Jason Duval und Lucia Caminos als Hauptprotagonisten, dazu aus Trailer 2 die Nebenfiguren Cal Hampton, Boobie Ike, Dre\'Quan Priest und Raul Bautista, außerdem das Musik Duo Bae-Luxe und Roxy, das unter dem Namen Real Dimez auftritt. Eine Werbeeinblendung für \\"Phil\'s Ammu-Nation\\" lässt eine Verbindung zu Phil Cassidy aus dem Original Vice City vermuten, das hat Rockstar aber nie bestätigt, hier bleibt es Spekulation.",
      "### Fahrzeuge",
      "Offiziell mit Namen versehen hat Rockstar bisher vor allem die Fahrzeuge aus der Ultimate Edition und dem Vorbesteller Paket: den 95er Grotti Cheetah, die Dinka Enduro, den Crest Kayak, den 67er Vapid Dominator Buggy, den Shitzu Squalo sowie den 55er Vapid Stanier aus dem Vintage Vice City Pack. Darüber hinaus kursieren in Fan Datenbanken deutlich längere Listen mit über 100 Fahrzeugen, die aus Trailern und Screenshots herausgelesen wurden, darunter Marken wie Pfister, Invetero, Ubermacht, Annis, Benefactor und Karin. Diese Listen sind Community Arbeit, keine offizielle Rockstar Liste, auch wenn viele Details darin plausibel und mit Trailer Screenshots belegt sind.",
      "### Waffen",
      "Bei Waffen hält sich Rockstar noch bedeckter. Konkret benannt sind die Hawk & Little Morgan Revolver aus der Ultimate Edition in einer Herren und Damen Variante mit Vice City Optik, dazu personalisierte Pistolen, Jasons Girardi ES9 und Lucias Klose K17. Aus dem Vintage Vice City Pack kommt außerdem ein Waffenmuster im Stil von Tommy Vercettis Hawaiihemd. Eine vollständige Waffenliste hat Rockstar bislang nicht veröffentlicht.",
      "Kurz gesagt: alles, was im Ultimate Edition Katalog oder im Vorbesteller Paket auftaucht, ist zu hundert Prozent bestätigt. Alles darüber hinaus stammt aus Trailer Standbildern und Community Recherche, spannend, aber mit Vorsicht zu genießen. Was genau im Vorbesteller Paket steckt, steht in [[vorbesteller-boni|unserem Artikel zu den Vorbesteller-Boni]]."
    ]
  },
  "gta-6-online-modus": {
    "expected_current": [
      "Sowohl die offiziellen Store Einträge bei PlayStation und Xbox als auch Rockstars eigenes Pressematerial verwenden fast wortgleich die Formulierung, GTA 6 sei ein Singleplayer Erlebnis. Von einem Mehrspielermodus zum Start ist nirgends die Rede, das hat im Sommer 2026 für einige Unruhe bei Fans gesorgt, kurz nachdem Vorbestellungen bereits gestartet waren.",
      "Wirtschaftlich wäre ein komplettes Fehlen von Online Inhalten trotzdem überraschend, GTA Online zählt weiterhin zu den wichtigsten Umsatzbringern von Take Two. Genau deshalb geht die Mehrheit der Beobachter davon aus, dass ein neuer Online Baustein irgendwann nach dem 19. November folgt, ähnlich wie schon bei GTA 5 und Red Dead Redemption 2, wo der Mehrspielermodus jeweils erst nach dem Story Release kam.",
      "### Was aktuell nur Vermutung ist",
      "- Ein möglicher Start des Online Modus wenige Wochen nach Release",
      "- Kein direkter Fortschritts oder Charaktertransfer aus dem bisherigen GTA Online",
      "- Mögliche Bonus Belohnungen für bestehende GTA Online Spieler, bisher nicht im Detail bestätigt",
      "- Stärkerer Fokus auf Roleplay und Creator Tools, befeuert durch Rockstars Übernahme des FiveM und RedM Teams Cfx.re",
      "### Was dagegen belegt ist",
      "Das aktuelle GTA Online läuft laut Gerichtsunterlagen mit bis zu 32 Spielern pro Session und wird laut Take Two CEO Strauss Zelnick weiter unterstützt, unabhängig davon, was mit GTA 6 passiert. Wer beim Vorbestellen digital über PlayStation oder Microsoft Store kauft, bekommt zudem einen Monat GTA Plus für das bestehende GTA Online geschenkt, das ist ein reiner Vorbesteller Bonus für das alte Spiel, kein Vorgeschmack auf den neuen Modus.",
      "Unser Fazit: ein Online Modus für GTA 6 kommt so gut wie sicher, nur eben nicht am Launchtag und ohne offiziellen Termin. Alles, was nach genauem Datum oder konkreten Features klingt, ist bis auf Weiteres Einordnung, kein bestätigtes Feature.",
      "faq:Hat GTA 6 zum Release einen Online Modus?|Nein, Rockstar bezeichnet GTA 6 offiziell als Singleplayer Erlebnis, ein Mehrspielermodus zum Start ist nicht bestätigt.",
      "faq:Übernehme ich meinen Fortschritt aus dem bisherigen GTA Online?|Nach aktuellem Stand nicht, ein direkter Transfer von Charakteren, Geld oder Fahrzeugen ist nicht zu erwarten.",
      "faq:Wird das alte GTA Online weiter unterstützt?|Ja, laut Take Two CEO Strauss Zelnick läuft die Unterstützung unabhängig von GTA 6 weiter."
    ],
    "new": [
      "Sowohl die offiziellen Store Einträge bei PlayStation und Xbox als auch Rockstars eigenes Pressematerial verwenden fast wortgleich die Formulierung, GTA 6 sei ein Singleplayer Erlebnis. Von einem Mehrspielermodus zum Start ist nirgends die Rede, das hat im Sommer 2026 für einige Unruhe bei Fans gesorgt, kurz nachdem Vorbestellungen bereits gestartet waren.",
      "Wirtschaftlich wäre ein komplettes Fehlen von Online Inhalten trotzdem überraschend, GTA Online zählt weiterhin zu den wichtigsten Umsatzbringern von Take Two. Genau deshalb geht die Mehrheit der Beobachter davon aus, dass ein neuer Online Baustein irgendwann nach dem 19. November folgt, ähnlich wie schon bei GTA 5 und Red Dead Redemption 2, wo der Mehrspielermodus jeweils erst nach dem Story Release kam.",
      "### Was aktuell nur Vermutung ist",
      "- Ein möglicher Start des Online Modus wenige Wochen nach Release",
      "- Kein direkter Fortschritts oder Charaktertransfer aus dem bisherigen GTA Online",
      "- Mögliche Bonus Belohnungen für bestehende GTA Online Spieler, bisher nicht im Detail bestätigt",
      "- Stärkerer Fokus auf Roleplay und Creator Tools, befeuert durch Rockstars Übernahme des FiveM und RedM Teams Cfx.re",
      "### Was dagegen belegt ist",
      "Das aktuelle GTA Online läuft laut Gerichtsunterlagen mit bis zu 32 Spielern pro Session und wird laut Take Two CEO Strauss Zelnick weiter unterstützt, unabhängig davon, was mit GTA 6 passiert. Wer beim Vorbestellen digital über PlayStation oder Microsoft Store kauft, bekommt zudem einen Monat GTA Plus für das bestehende GTA Online geschenkt, das ist ein reiner Vorbesteller Bonus für das alte Spiel, kein Vorgeschmack auf den neuen Modus. Alle Vorbesteller-Boni im Überblick gibt es in [[vorbesteller-boni|unserem Artikel dazu]].",
      "Unser Fazit: ein Online Modus für GTA 6 kommt so gut wie sicher, nur eben nicht am Launchtag und ohne offiziellen Termin. Alles, was nach genauem Datum oder konkreten Features klingt, ist bis auf Weiteres Einordnung, kein bestätigtes Feature.",
      "faq:Hat GTA 6 zum Release einen Online Modus?|Nein, Rockstar bezeichnet GTA 6 offiziell als Singleplayer Erlebnis, ein Mehrspielermodus zum Start ist nicht bestätigt.",
      "faq:Übernehme ich meinen Fortschritt aus dem bisherigen GTA Online?|Nach aktuellem Stand nicht, ein direkter Transfer von Charakteren, Geld oder Fahrzeugen ist nicht zu erwarten.",
      "faq:Wird das alte GTA Online weiter unterstützt?|Ja, laut Take Two CEO Strauss Zelnick läuft die Unterstützung unabhängig von GTA 6 weiter."
    ]
  },
  "der-grosse-leak-von-2022": {
    "expected_current": [
      "### Was am 18. September 2022 passierte",
      "Ein Nutzer namens teapotuberhacker veröffentlichte auf GTAForums einen Download Link zu über 90 Entwicklervideos, angeblich aus einer frühen Version des nächsten GTA Teils. Innerhalb weniger Stunden verbreitete sich das Material über Reddit, Twitter und Dutzende weitere Plattformen, trotz sofortiger Takedown Versuche von Take Two.",
      "Bereits am Folgetag bestätigte Rockstar Games den Vorfall offiziell und sprach von einer unautorisierten Netzwerkeindringung, bei der vertrauliche Informationen entwendet worden seien. Man betonte gleichzeitig, dass keine Auswirkungen auf laufende Live Dienste oder die Entwicklung selbst zu erwarten seien.",
      "### Wer dahintersteckte",
      "Hinter dem Konto stand Arion Kurtaj, ein damals minderjähriger Brite aus Oxford und Mitglied der Hackergruppe Lapsus$, die zuvor bereits Microsoft, Nvidia, Samsung und Uber angegriffen hatte. Kurtaj befand sich zum Zeitpunkt des Hacks eigentlich schon wegen früherer Vorwürfe auf Kaution und unter Internetverbot, umging das aber, indem er über einen Amazon Fire Stick am Hotelfernseher eines Travelodge Zimmers online ging. Ein Gericht verurteilte ihn später wegen Hacking, Betrug und Erpressung, aufgrund seiner schweren Autismus Diagnose wurde er allerdings für nicht verhandlungsfähig erklärt und in eine geschlossene Einrichtung eingewiesen.",
      "### Was die Videos zeigten und was daraus wurde",
      "Zu sehen waren frühe Alpha Aufnahmen mit zwei spielbaren Figuren, darunter eine weibliche Protagonistin, dazu ein modernes Vice City Setting, UI Elemente, Missionsskripte und vertonte NPC Dialoge. Als zwei Monate später der offizielle Trailer 1 erschien, bestätigte sich fast jedes überprüfbare Detail aus den geleakten Clips, vom Setting bis zu bestimmten Gebäuden. Genau das macht diesen Leak bis heute zum einzigen großen GTA 6 Leak, den Rockstar selbst offiziell als echt eingestuft hat, jeder andere kursierende Leak seitdem bewegt sich unterhalb dieser Bestätigungsstufe.",
      "Ein zweiter, deutlich kleinerer Vorfall ereignete sich im April 2026, als die Gruppe ShinyHunters über einen Drittanbieter Dienst namens Anodot Zugriff auf einen Teil von Rockstars Unternehmensdaten erhielt. Rockstar bestätigte diesen Vorfall ebenfalls, betonte aber, es seien nur nicht materielle Unternehmensinformationen betroffen gewesen, keine Spieldaten oder Quellcode. Für die Einordnung wichtig: dieser Vorfall hat nichts mit dem großen Leak von 2022 zu tun und lieferte auch keine neuen Inhalte zum Spiel selbst.",
      "faq:War der Leak von 2022 wirklich echt?|Ja, Rockstar hat den Vorfall selbst am Folgetag bestätigt, es ist der einzige große GTA 6 Leak mit offizieller Bestätigung.",
      "faq:Wer steckte hinter dem Hack?|Arion Kurtaj, ein Teenager aus Oxford und Mitglied der Hackergruppe Lapsus$, wurde dafür später gerichtlich verurteilt.",
      "faq:Hat der Leak von 2022 etwas mit dem Vorfall von 2026 zu tun?|Nein, der Vorfall im April 2026 durch die Gruppe ShinyHunters betraf nur Unternehmensdaten und ist ein komplett separater Vorfall ohne Bezug zu den 2022 geleakten Spielinhalten."
    ],
    "new": [
      "### Was am 18. September 2022 passierte",
      "Ein Nutzer namens teapotuberhacker veröffentlichte auf GTAForums einen Download Link zu über 90 Entwicklervideos, angeblich aus einer frühen Version des nächsten GTA Teils. Innerhalb weniger Stunden verbreitete sich das Material über Reddit, Twitter und Dutzende weitere Plattformen, trotz sofortiger Takedown Versuche von Take Two.",
      "Bereits am Folgetag bestätigte Rockstar Games den Vorfall offiziell und sprach von einer unautorisierten Netzwerkeindringung, bei der vertrauliche Informationen entwendet worden seien. Man betonte gleichzeitig, dass keine Auswirkungen auf laufende Live Dienste oder die Entwicklung selbst zu erwarten seien.",
      "### Wer dahintersteckte",
      "Hinter dem Konto stand Arion Kurtaj, ein damals minderjähriger Brite aus Oxford und Mitglied der Hackergruppe Lapsus$, die zuvor bereits Microsoft, Nvidia, Samsung und Uber angegriffen hatte. Kurtaj befand sich zum Zeitpunkt des Hacks eigentlich schon wegen früherer Vorwürfe auf Kaution und unter Internetverbot, umging das aber, indem er über einen Amazon Fire Stick am Hotelfernseher eines Travelodge Zimmers online ging. Ein Gericht verurteilte ihn später wegen Hacking, Betrug und Erpressung, aufgrund seiner schweren Autismus Diagnose wurde er allerdings für nicht verhandlungsfähig erklärt und in eine geschlossene Einrichtung eingewiesen.",
      "### Was die Videos zeigten und was daraus wurde",
      "Zu sehen waren frühe Alpha Aufnahmen mit zwei spielbaren Figuren, darunter eine weibliche Protagonistin, dazu ein modernes Vice City Setting, UI Elemente, Missionsskripte und vertonte NPC Dialoge. Als zwei Monate später der offizielle Trailer 1 erschien, bestätigte sich fast jedes überprüfbare Detail aus den geleakten Clips, vom Setting bis zu bestimmten Gebäuden. Genau das macht diesen Leak bis heute zum einzigen großen GTA 6 Leak, den Rockstar selbst offiziell als echt eingestuft hat, jeder andere kursierende Leak seitdem bewegt sich unterhalb dieser Bestätigungsstufe. Was der offizielle Trailer 1 im Detail zeigte, lest ihr in [[trailer-1-analyse|unserer Trailer-1-Analyse]].",
      "Ein zweiter, deutlich kleinerer Vorfall ereignete sich im April 2026, als die Gruppe ShinyHunters über einen Drittanbieter Dienst namens Anodot Zugriff auf einen Teil von Rockstars Unternehmensdaten erhielt. Rockstar bestätigte diesen Vorfall ebenfalls, betonte aber, es seien nur nicht materielle Unternehmensinformationen betroffen gewesen, keine Spieldaten oder Quellcode. Für die Einordnung wichtig: dieser Vorfall hat nichts mit dem großen Leak von 2022 zu tun und lieferte auch keine neuen Inhalte zum Spiel selbst.",
      "faq:War der Leak von 2022 wirklich echt?|Ja, Rockstar hat den Vorfall selbst am Folgetag bestätigt, es ist der einzige große GTA 6 Leak mit offizieller Bestätigung.",
      "faq:Wer steckte hinter dem Hack?|Arion Kurtaj, ein Teenager aus Oxford und Mitglied der Hackergruppe Lapsus$, wurde dafür später gerichtlich verurteilt.",
      "faq:Hat der Leak von 2022 etwas mit dem Vorfall von 2026 zu tun?|Nein, der Vorfall im April 2026 durch die Gruppe ShinyHunters betraf nur Unternehmensdaten und ist ein komplett separater Vorfall ohne Bezug zu den 2022 geleakten Spielinhalten."
    ]
  }
}', true);
if (!is_array($patch)) {
    http_response_code(500);
    echo json_encode(['error' => 'Interne Patch-Daten konnten nicht gelesen werden.']);
    exit;
}

$select = $pdo->prepare('SELECT content_json FROM articles WHERE id = ?');
$update = $pdo->prepare('UPDATE articles SET content_json = ? WHERE id = ?');

$updated = [];
$skipped_missing = [];
$skipped_already = [];
$skipped_changed = [];

foreach ($patch as $id => $entry) {
    $select->execute([$id]);
    $row = $select->fetch();
    if (!$row) { $skipped_missing[] = $id; continue; }

    $current = json_decode($row['content_json'] ?? '[]', true) ?: [];
    $expected = $entry['expected_current'];
    $newContent = $entry['new'];
    $newJson = json_encode($newContent, JSON_UNESCAPED_UNICODE);

    if ($row['content_json'] === $newJson) { $skipped_already[] = $id; continue; }
    if ($current !== $expected) { $skipped_changed[] = $id; continue; }

    $update->execute([$newJson, $id]);
    $updated[] = $id;
}

echo json_encode([
    'ok' => true,
    'updated_count' => count($updated),
    'updated' => $updated,
    'skipped_already_count' => count($skipped_already),
    'skipped_already' => $skipped_already,
    'skipped_changed_count' => count($skipped_changed),
    'skipped_changed' => $skipped_changed,
    'skipped_missing_count' => count($skipped_missing),
    'skipped_missing' => $skipped_missing,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
