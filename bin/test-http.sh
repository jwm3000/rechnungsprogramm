#!/usr/bin/env bash
# Sicherheitstests über HTTP: eigener Server (Docker) mit leerem Datenordner – echte Daten bleiben unberührt.
#   bin/test-http.sh
set -uo pipefail
DIR="$(cd "$(dirname "$0")/.." && pwd)"
PORT=8097
NAME="rechnungen-test-$$"
BASE="http://localhost:$PORT/rechnungen"
JAR="$(mktemp)"
fails=0
count=0
ok() { count=$((count + 1)); if [[ "$1" == "0" ]]; then :; else fails=$((fails + 1)); echo "  ✗ $2"; fi; }
check() { [[ "$1" == "$2" ]]; ok $? "$3 (erwartet $2, bekommen $1)"; }
contains() { grep -q -- "$2" <<<"$1"; ok $? "$3"; }
lacks() { ! grep -q -- "$2" <<<"$1"; ok $? "$3"; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
php_in() { docker exec "$NAME" php -r "putenv('NW_DATA_DIR=/tmp/nwdata'); require '/app/rechnungen/lib/bootstrap.php'; $1"; }

docker run -d --rm --name "$NAME" -e NW_DATA_DIR=/tmp/nwdata -e NW_CONFIG=/nonexistent -p "$PORT:8090" -v "$DIR":/app:ro -w /app php:8.3-cli \
	php -S 0.0.0.0:8090 -t /app bin/router.php >/dev/null
trap 'docker rm -f "$NAME" >/dev/null 2>&1; rm -f "$JAR"' EXIT
for i in $(seq 30); do curl -s -o /dev/null "$BASE/" && break; sleep 0.2; done

echo "· Auslieferung & Header"
H=$(curl -s -D - -o /dev/null "$BASE/")
contains "$H" "Content-Security-Policy: default-src 'self'" "CSP auf der Seite"
contains "$H" "X-Frame-Options: SAMEORIGIN" "kein Einbetten in fremde Seiten"
check "$(code "$BASE/data/probe.txt")" 403 "Datenordner gesperrt"
check "$(code "$BASE/lib/bootstrap.php")" 403 "lib/ gesperrt"
check "$(code "$BASE/config.sample.php")" 403 "config.sample.php gesperrt"
check "$(code "$BASE/cron.php")" 403 "cron.php ohne Schlüssel gesperrt"
check "$(code "$BASE/cron.php?key=falsch")" 403 "cron.php mit falschem Schlüssel gesperrt"

echo "· Ersteinrichtung nur mit Code"
S=$(curl -s -c "$JAR" -b "$JAR" "$BASE/api.php?a=session")
CSRF=$(python3 -c "import json,sys;print(json.loads(sys.argv[1])['csrf'])" "$S")
contains "$S" '"has_password":false' "frische Installation ohne Passwort"
post() { curl -s -c "$JAR" -b "$JAR" -H "X-CSRF: $CSRF" -H 'Content-Type: application/json' -d "$2" -w '\n%{http_code}' "$BASE/api.php?a=$1"; }
R=$(curl -s -c "$JAR" -b "$JAR" -H 'Content-Type: application/json' -d '{"password":"testtest123"}' -w '\n%{http_code}' "$BASE/api.php?a=setup")
check "$(tail -1 <<<"$R")" 403 "ohne CSRF-Token abgelehnt"
R=$(post setup '{"password":"testtest123","code":"FALSCH12"}')
check "$(tail -1 <<<"$R")" 403 "falscher Einrichtungscode abgelehnt"
SETUP=$(docker exec "$NAME" head -1 /tmp/nwdata/SETUP-CODE.txt)
R=$(post setup "{\"password\":\"testtest123\",\"code\":\"$SETUP\"}")
check "$(tail -1 <<<"$R")" 200 "richtiger Code richtet ein"
docker exec "$NAME" test -f /tmp/nwdata/SETUP-CODE.txt; ok $(( $? == 0 )) "Code-Datei danach gelöscht"
R=$(post setup "{\"password\":\"neuesPasswort1\",\"code\":\"$SETUP\"}")
check "$(tail -1 <<<"$R")" 403 "zweite Einrichtung unmöglich"

echo "· Angemeldet"
B=$(curl -s -b "$JAR" -D - "$BASE/api.php?a=bootstrap")
contains "$B" "default-src 'none'" "API-Antworten mit strenger CSP"
lacks "$B" "password_hash" "Passwort-Hash nie an den Browser"
lacks "$B" "smtp_pass_enc" "SMTP-Passwort nie an den Browser"
lacks "$B" "cron_key\"" "Cron-Schlüssel nicht in den Einstellungen"
R=$(post settings_save '{"company":"Test","update_cache":"{\"asset\":\"https://evil.example/x.zip\"}","password_hash":"x"}')
check "$(tail -1 <<<"$R")" 200 "Einstellungen speichern"
check "$(php_in 'echo nw_setting("update_cache");')" "" "interner Update-Cache nicht überschreibbar"
check "$(php_in 'echo strlen(nw_setting("password_hash")) > 20 ? "ok" : "kaputt";')" ok "Passwort-Hash nicht überschreibbar"
php_in 'nw_insert("invoices", array("number"=>"X1","status"=>"issued","kind"=>"invoice","recipient"=>"{}","invoice_date"=>"2026-01-01","original_file"=>"../../config.sample.php"));' >/dev/null
IID=$(php_in 'echo q_val("SELECT id FROM invoices WHERE number = ?", array("X1"));')
check "$(code -b "$JAR" "$BASE/api.php?a=original&id=$IID")" 404 "Pfad-Ausbruch bei Originalen blockiert"
F=$(mktemp --suffix=.php); echo '<?php echo 1;' >"$F"
R=$(curl -s -b "$JAR" -H "X-CSRF: $CSRF" -F "vendor=Test" -F "amount=1" -F "file=@$F" -w '\n%{http_code}' "$BASE/api.php?a=expense_save"); rm -f "$F"
check "$(tail -1 <<<"$R")" 400 "PHP-Datei als Beleg abgelehnt"
R=$(post invoice '{"id":999999}')
contains "$R" "nicht gefunden" "unbekannte Rechnung sauber gemeldet"
check "$(code -X POST -b "$JAR" -H 'Content-Type: application/json' -d '{}' "$BASE/api.php?a=logout")" 403 "Abmelden ohne CSRF abgelehnt"

echo "· Ohne Anmeldung"
check "$(code "$BASE/api.php?a=bootstrap")" 401 "Daten ohne Anmeldung gesperrt"
check "$(code "$BASE/api.php?a=backup")" 401 "Sicherung ohne Anmeldung gesperrt"
check "$(code "$BASE/api.php?a=pdf&id=$IID")" 401 "PDF ohne Anmeldung gesperrt"

echo "· Schutz gegen Passwort-Raten"
J2="$(mktemp)"
S2=$(curl -s -c "$J2" -b "$J2" "$BASE/api.php?a=session")
C2=$(python3 -c "import json,sys;print(json.loads(sys.argv[1])['csrf'])" "$S2")
for i in $(seq 8); do curl -s -o /dev/null -c "$J2" -b "$J2" -H "X-CSRF: $C2" -H 'Content-Type: application/json' -d '{"password":"falsch"}' "$BASE/api.php?a=login"; done
R=$(curl -s -c "$J2" -b "$J2" -H "X-CSRF: $C2" -H 'Content-Type: application/json' -d '{"password":"testtest123"}' "$BASE/api.php?a=login")
contains "$R" "Zu viele Fehlversuche" "nach 8 Fehlversuchen gesperrt – auch mit richtigem Passwort"
rm -f "$J2"

if [[ $fails == 0 ]]; then echo; echo "Alle $count Sicherheitstests bestanden"; else echo; echo "$fails von $count Sicherheitstests FEHLGESCHLAGEN"; exit 1; fi
