#!/usr/bin/env bash
# Alle Prüfungen: Syntax & API-Abgleich, Fachlogik, Sicherheit über HTTP. Läuft auch vor jedem Release.
set -euo pipefail
cd "$(dirname "$0")/.."
echo "== Syntax & API-Abgleich";  bin/check.sh
echo "== Fachlogik";              bin/php bin/test.php
echo "== Sicherheit (HTTP)";      bin/test-http.sh
