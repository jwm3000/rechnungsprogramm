"""Erzeugt rechnungen/assets/plz-at.json aus dem GeoNames-Postleitzahlenverzeichnis Österreich.

Quelle: https://download.geonames.org/export/zip/AT.zip (CC BY 4.0, GeoNames)
Aufruf: python3 -I bin/plz-at.py AT.txt rechnungen/assets/plz-at.json

Format: {"5541": ["Altenmarkt im Pongau", "Palfen", …]} – an erster Stelle der Gemeindename
(der übliche Postort), danach weitere Ortschaften derselben Postleitzahl.
"""
import json
import sys

from collections import Counter

rows = {}
for line in open(sys.argv[1], encoding='utf-8'):
    f = line.rstrip('\n').split('\t')
    plz, place, gemeinde = f[1], f[2], f[7]
    if place.startswith('Wien'):
        place, gemeinde = 'Wien', 'Wien'
    place = place.split(',')[0].strip()      # „Graz,01.Bez.:Innere Stadt“ → „Graz“
    gemeinde = gemeinde.split(',')[0].strip()
    r = rows.setdefault(plz, {'g': Counter(), 'p': []})
    r['g'][gemeinde] += 1
    if place not in r['p']:
        r['p'].append(place)
out = {}
for plz in sorted(rows):
    places = rows[plz]['p']
    first = []
    for g, _ in rows[plz]['g'].most_common():  # häufigste Gemeinde = Postort
        first = [p for p in places if p == g] or [p for p in places if g.startswith(p + ' ') or g.startswith(p + ',')]
        if first:
            break
    first = first or places[:1]
    rest = sorted(p for p in places if p not in first)
    out[plz] = first[:1] + rest[:11]
json.dump(out, open(sys.argv[2], 'w', encoding='utf-8'), ensure_ascii=False, separators=(',', ':'))
print(len(out), 'Postleitzahlen')
