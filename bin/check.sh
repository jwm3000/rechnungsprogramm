#!/usr/bin/env bash
# Schnelle Prüfung vor jedem Release:
#  1. PHP-Syntax aller Dateien
#  2. jede API-Aktion, die die Oberfläche aufruft, gibt es in api.php
#  3. JavaScript lässt sich parsen
set -euo pipefail
DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$DIR"
fail=0
for f in rechnungen/*.php rechnungen/lib/*.php bin/*.php; do
	out=$(bin/php -l "$f" 2>&1) || { echo "$out"; fail=1; }
done
for a in $(grep -oE "api\('[a-z_]+'" rechnungen/assets/app.js | sed "s/api('//; s/'//" | sort -u) cron; do
	[[ "$a" == cron ]] && continue
	{ grep -qF "case '$a':" rechnungen/api.php || grep -qF "'$a' === \$a" rechnungen/api.php; } || { echo "API-Aktion fehlt: $a"; fail=1; }
done
for a in $(grep -oE "api\.php\?a=[a-z_]+" rechnungen/assets/app.js | sed 's/api.php?a=//' | sort -u); do
	{ grep -qF "case '$a':" rechnungen/api.php || grep -qF "'$a' === \$a" rechnungen/api.php; } || { echo "API-Aktion fehlt: $a"; fail=1; }
done
node -e "new Function(require('fs').readFileSync('rechnungen/assets/app.js','utf8'))" || fail=1
[[ $fail == 0 ]] && echo "Prüfung ok" || { echo "Prüfung FEHLGESCHLAGEN"; exit 1; }
