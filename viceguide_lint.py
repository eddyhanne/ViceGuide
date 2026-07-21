#!/usr/bin/env python3
# ViceGuide Artikel-Filter. Prueft jedes Artikel-JSON gegen die harten Projektregeln.
# Aufruf: python3 viceguide_lint.py articles.json
import json, re, sys

VALID_CAT = {"news","leaks","trailer","story","map","community",
             "money","missions","vehicles","weapons","secrets","online","beginner"}

HARD_PHRASES = [
    # Meta-Ansagen ueber den Text
    "Wichtig fuer die Einordnung","Wichtig für die Einordnung","Zur Einordnung",
    "Fuer die Einordnung","Für die Einordnung","Wichtig dabei","Eins vorweg",
    "So viel sei gesagt","Fuer Spieler heisst das","Für Spieler heißt das","bleibt festzuhalten",
    # Aufsatz-Klammern
    "Zusammenfassend laesst sich sagen","Zusammenfassend lässt sich sagen","Kurz gesagt",
    "Unser Fazit","Alles in allem","Es ist wichtig zu beachten","Es ist erwaehnenswert",
    "Es ist erwähnenswert","hilft bei der Einordnung",
    # Leerlauf-Hedges
    "Es bleibt abzuwarten","Man darf gespannt sein","Nur die Zeit wird es zeigen","Wie sich zeigen wird",
    # Marketing-Enthusiasmus
    "Tauche ein","Mach dich bereit fuer","Mach dich bereit für","Freu dich auf",
    # Vage Sammel-Quellen
    "Beobachter werten","Berichten zufolge","in der Berichterstattung gilt",
    "mehrere Quellen","Experten sagen","Experten gehen davon aus",
    # Fuell-Uebergaenge
    "Doch damit nicht genug","Und das ist noch nicht alles","Aber was bedeutet das eigentlich",
    # Klischee-Eroeffnungen
    "Kaum ein Spiel wird so sehnlich","so sehnlich erwartet","Fans auf der ganzen Welt",
    # Rhetorische Leserfragen als Absatzabschluss
    "Was denkt ihr?","Freut ihr euch?","Was meint ihr?","Seid ihr gespannt?",
]
SOFT_PHRASES = ["Wichtig:","Wichtig zu wissen","Die kurze Antwort vorweg","der Beobachter","die Beobachter","Mehrheit der Beobachter",
    # Weichmacher und Hype-Adjektive ohne Beleg
    "quasi","regelrecht","gefuehlt","gefühlt","im wahrsten Sinne","atemberaubend","wunderschoen","wunderschön","gigantisch"]

def sentences(text):
    return [s.strip() for s in re.split(r'(?<=[.!?])\s+', text) if s.strip()]

def lint(a):
    hard, soft = [], []
    content = a.get("content") or []
    ctext = [c for c in content if isinstance(c,str)]
    blob = " ".join([str(a.get("summary","")), str(a.get("lead","")), *ctext])

    # 1 Schema
    for f in ["id","cat","title","summary","lead","content","sources"]:
        if not a.get(f): hard.append(f"Feld fehlt oder leer: {f}")
    if a.get("cat") not in VALID_CAT: hard.append(f"cat ungueltig: {a.get('cat')}")
    if a.get("cat") in {"money","missions","vehicles","weapons","secrets","online","beginner"}:
        soft.append(f"cat ist gesperrte Phase-2-Kategorie: {a.get('cat')}")
    if len(str(a.get("title",""))) > 65: soft.append(f"Titel laenger als 65 Zeichen ({len(str(a.get('title','')))}), SEO-Anzeige pruefen")
    if len(str(a.get("summary","")).split())>22: soft.append("Summary laenger als ~20 Woerter")
    if "faq" in a: hard.append("Separates faq-Feld vorhanden (wird ignoriert, muss ins content)")

    # 2 FAQ (weich: flaggt, blockiert aber legitime Kurz-News nicht)
    faqs = [c for c in ctext if c.strip().lower().startswith("faq:")]
    if len(faqs) < 2: soft.append(f"FAQ unter 2 Eintraegen (gefunden: {len(faqs)}). Guide/Reference: nachruesten. News/Analyse: nur bewusst weglassen.")
    for fq in faqs:
        if "|" not in fq: hard.append(f"FAQ ohne | Trenner: {fq[:50]}")

    # 3 Gedankenstriche
    dash = blob.count("\u2013")+blob.count("\u2014")
    if dash: hard.append(f"Gedankenstriche gefunden: {dash}")

    # 4 Quellen benannt (mind. 1)
    if not (a.get("sources") or []): hard.append("Keine Quelle im sources-Feld")

    # 5 Floskeln
    for p in HARD_PHRASES:
        if p.lower() in blob.lower(): hard.append(f'Verbotene Floskel: "{p}"')
    for p in SOFT_PHRASES:
        if p.lower() in blob.lower(): soft.append(f'Grenzfall-Floskel: "{p}"')

    # 6 Komma-Ketten (Sing-Sang-Heuristik)
    long_comma = []
    for c in ctext:
        if c.strip().lower().startswith(("faq:","img:","### ","- ","table:","step:")): continue
        for s in sentences(c):
            if s.count(",") >= 4: long_comma.append(s)
    if long_comma:
        soft.append(f"{len(long_comma)} Satz/Saetze mit 4+ Kommas (Sing-Sang pruefen)")

    return hard, soft

def main():
    arts = json.load(open(sys.argv[1] if len(sys.argv)>1 else "articles.json", encoding="utf-8"))
    total_hard=0; total_soft=0; clean=0
    for i,a in enumerate(arts,1):
        h,s = lint(a); total_hard+=len(h); total_soft+=len(s)
        if not h and not s: clean+=1
        status = "SAUBER" if not h and not s else ("HARTE FEHLER" if h else "nur Grenzfaelle")
        print(f"[{i:2}] {status:14} | {a.get('title','?')}")
        for x in h: print(f"       HART: {x}")
        for x in s: print(f"       soft: {x}")
    print("="*60)
    print(f"Artikel: {len(arts)} | komplett sauber: {clean} | harte Fehler gesamt: {total_hard} | Grenzfaelle: {total_soft}")

if __name__=="__main__": main()
