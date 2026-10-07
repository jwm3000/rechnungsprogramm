# Rechnungsprogramm

Schlankes Rechnungsprogramm für Selbstständige und kleine Agenturen in Österreich.
**PHP + SQLite, keine Abhängigkeiten, kein Build-Schritt** – läuft auf jedem gewöhnlichen Webhosting
und fühlt sich trotzdem an wie eine moderne App, am Desktop wie am Handy.

![Übersicht](docs/uebersicht.png)

<table>
<tr>
<td width="50%"><img src="docs/rechnungen.png" alt="Rechnungsliste"></td>
<td width="50%"><img src="docs/dauerrechnungen.png" alt="Dauerrechnungen"></td>
</tr>
<tr>
<td><img src="docs/editor.png" alt="Rechnung bearbeiten"></td>
<td><img src="docs/dunkel.png" alt="Dunkles Design"></td>
</tr>
</table>

## Beispielrechnung

<img src="docs/beispiel-rechnung.png" alt="Beispielrechnung" width="420" align="right">

Das PDF entsteht direkt in PHP, ohne Bibliothek:

- Logo als Vektorgrafik, gestochen scharf beim Drucken
- Zahlschein mit **SEPA-QR-Code** – Banking-App öffnen, scannen, fertig
- Stempel „Bezahlt“ bzw. „Storniert“
- Kleinunternehmer-Hinweis oder USt.-Ausweis
- mehrseitig mit Fortsetzungsköpfen

→ [Beispielrechnung als PDF](docs/beispiel-rechnung.pdf)

<br clear="right">

## Funktionen

**Rechnungen**
- Editor mit **Live-PDF-Vorschau** beim Tippen
- Artikel beim Tippen suchen und übernehmen, Rabatt pro Position, Leistungsdatum oder -zeitraum
- Fortlaufende Nummer erst beim Ausstellen, danach unveränderlich; Korrektur per **Stornorechnung**
- **Zahlungseingang abhaken** direkt in der Liste – mit „Rückgängig“
- Filter (offen, überfällig, bezahlt, Entwürfe, storniert), Jahr, Volltextsuche
- Per E-Mail senden (PDF im Anhang), Zahlungserinnerung, Teilen am Handy, Kopieren als neue Rechnung

**Dauerrechnungen**
- Übersicht, wer regelmäßig eine Rechnung bekommt – mit Jahresleiste der nächsten 12 Monate
- monatlich bis alle 3 Jahre; Platzhalter wie `{JAHR}` oder `{ZEITRAUM}` in Positionstexten
- pro Kunde wählbar: **automatisch senden**, nur ausstellen oder als Entwurf zur Prüfung
- täglicher Cronjob erstellt und versendet fällige Rechnungen
- Wiederkehrende Rechnungen und Kunden sind in allen Listen mit einem Symbol gekennzeichnet

**Kunden & Artikel**
- Kundenstamm mit Umsatz, offenen Beträgen, Verlauf, eigener Zahlungsfrist und E-Mail-Kopie
- Artikelkatalog mit Kategorien; wiederkehrende Leistungen markierbar

**Übersicht & Auswertung**
- Umsatz im Jahr mit Vorjahresvergleich, Monatsdiagramm, Umsatz pro Jahr, Top-Kunden
- **Kleinunternehmergrenze** im Blick (55.000 €)
- Ausgaben mit Belegfoto vom Handy, Überschuss je Jahr
- Export als CSV (Steuerberatung) oder alle Rechnungen als PDF in einer ZIP-Datei

**Bedienung**
- Suche über alles mit <kbd>Strg</kbd>/<kbd>⌘</kbd> + <kbd>K</kbd>, neue Rechnung mit <kbd>N</kbd>
- Helles und dunkles Design, einklappbares Menü, als App auf den Home-Bildschirm legbar

**Sicherheit & Betrieb**
- Anmeldung mit Passwort, Sperre nach Fehlversuchen, CSRF-Schutz, strenge Content-Security-Policy
- SMTP-Versand nur verschlüsselt mit Zertifikatsprüfung; Zugangsdaten nur in `config.php`, nie in der Datenbank
- Datenordner per `.htaccess` gesperrt (oder außerhalb des Webverzeichnisses)
- Datenbank-Sicherung per Klick
- **Eingebautes Software-Update** aus den GitHub-Releases – mit automatischer Sicherung und Zurücksetzen

## Installation

Voraussetzungen: PHP ≥ 8.0 mit `pdo_sqlite`, `mbstring`, `openssl` (überall Standard), Apache oder nginx.

1. Neuestes [Release](../../releases/latest) laden, `rechnungen.zip` entpacken und den Ordner `rechnungen/` hochladen (z. B. nach `/rechnungen`).
2. Der Ordner `rechnungen/data/` muss für PHP beschreibbar sein.
3. Seite aufrufen und ein Passwort festlegen, dann unter **Einstellungen** Firmendaten und Bankverbindung eintragen.
4. Für den E-Mail-Versand `config.sample.php` nach `config.php` kopieren und SMTP eintragen.
5. Für Dauerrechnungen einen täglichen Cronjob anlegen: `php /pfad/zu/rechnungen/cron.php`
   (oder die URL aus *Einstellungen → E-Mail & Automatik*).

Unter **Einstellungen → Sicherheit** prüft das Programm, ob der Datenordner von außen erreichbar ist.
Bei nginx greift die `.htaccess` nicht – dann `data_dir` in der `config.php` auf einen Ordner außerhalb des Webverzeichnisses setzen.

### E-Mail-Versand

Am zuverlässigsten über ein eigenes Postfach der Domain (z. B. `rechnung@deine-domain.at`), SMTP Port 465 (`ssl`) oder 587 (`tls`).
Dann passen SPF/DKIM zur Absenderadresse. Mit `bcc` bekommst du eine Kopie jeder versendeten Rechnung.

## Updates

*Einstellungen → Update* zeigt neue Versionen aus diesem Repository samt Änderungen an.
Ein Klick installiert sie – vorher werden Programm und Datenbank nach `data/updates/` gesichert,
jede Sicherung lässt sich dort wieder einspielen. `data/` und `config.php` werden nie überschrieben.

Ein anderes Repository (z. B. ein eigener Fork) lässt sich in der `config.php` einstellen:

```php
'update' => array( 'repo' => 'benutzer/repo', 'token' => '' ), // token nur bei privatem Repo
```

## Entwicklung

```bash
bin/dev.sh                                    # http://localhost:8090/rechnungen/ (PHP 8.3 über Docker)
NW_DATA_DIR=/app/demo/data bin/php bin/demo.php  # Demo-Daten mit erfundenen Kunden (Passwort demo1234)
bin/export.sh [--leer]                        # dist/rechnungen.zip – mit oder ohne Daten
bin/release.sh 1.2.0 "Was ist neu"            # GitHub-Release für das eingebaute Update
```

| Pfad | Inhalt |
|---|---|
| `rechnungen/index.php`, `assets/` | Oberfläche (Vanilla JS, eine Datei, kein Build) |
| `rechnungen/api.php` | JSON-API |
| `rechnungen/cron.php` | Dauerrechnungen erstellen und versenden |
| `rechnungen/lib/` | Datenbank, Fachlogik, PDF, QR-Code, SMTP, ZIP, Updater |
| `rechnungen/data/` | Datenbank, Belege, Sicherungen – **nie im Repository** |
