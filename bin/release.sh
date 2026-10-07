#!/usr/bin/env bash
# Neue Version veröffentlichen (GitHub-Release mit Paket für das eingebaute Update)
#   bin/release.sh 1.1.0 "Was ist neu – kurze Liste"
set -euo pipefail
DIR="$(cd "$(dirname "$0")/.." && pwd)"
VER="${1:?Version angeben, z. B. 1.1.0}"
NOTES="${2:-}"
cd "$DIR"
[[ "$VER" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Version im Format 1.2.3 angeben"; exit 1; }
[[ -z "$(git status --porcelain)" ]] || { echo "Erst alle Änderungen committen."; exit 1; }
bin/test.sh
echo "$VER" > rechnungen/VERSION
git add rechnungen/VERSION
git diff --cached --quiet || git commit -qm "Version $VER"
git tag "v$VER"
git push -q origin HEAD "v$VER"
bin/export.sh --leer
gh release create "v$VER" dist/rechnungen.zip --title "Version $VER" --notes "${NOTES:-Version $VER}"
echo "Release v$VER veröffentlicht – die Installationen zeigen das Update unter Einstellungen → Update."
