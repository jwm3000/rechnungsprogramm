#!/usr/bin/env bash
# Baut dist/rechnungen.zip zum Hochladen auf den Webserver
#   bin/export.sh          → Programm + aktuelle Daten (Datenbank, Original-PDFs, Belege)
#   bin/export.sh --leer   → nur das Programm (ohne Daten) – so sieht auch ein Release aus
set -euo pipefail
DIR="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$DIR/dist"
rm -rf "$OUT/rechnungen" "$OUT/rechnungen.zip"
mkdir -p "$OUT"
rsync -a --exclude 'config.php' --exclude 'data/*' "$DIR/rechnungen/" "$OUT/rechnungen/"
mkdir -p "$OUT/rechnungen/data/files"
cp "$DIR/rechnungen/data/.htaccess" "$DIR/rechnungen/data/index.php" "$DIR/rechnungen/data/probe.txt" "$OUT/rechnungen/data/"
if [[ "${1:-}" != "--leer" && -f "$DIR/rechnungen/data/rechnungen.sqlite" ]]; then
	# Konsistente Kopie der Datenbank (auch wenn der Server gerade läuft) + Dateien
	sqlite3 "$DIR/rechnungen/data/rechnungen.sqlite" ".backup '$OUT/rechnungen/data/rechnungen.sqlite'"
	rsync -a "$DIR/rechnungen/data/files/" "$OUT/rechnungen/data/files/"
fi
(cd "$OUT" && zip -qr rechnungen.zip rechnungen)
echo "Fertig: $OUT/rechnungen.zip ($(du -h "$OUT/rechnungen.zip" | cut -f1))"
