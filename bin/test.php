<?php
/**
 * Tests der Fachlogik – laufen in einem eigenen, leeren Datenordner (echte Daten bleiben unberührt).
 *
 *   bin/php bin/test.php
 */
$tmp = sys_get_temp_dir() . '/nwtest-' . bin2hex( random_bytes( 4 ) );
mkdir( $tmp );
putenv( 'NW_DATA_DIR=' . $tmp );
putenv( 'NW_CONFIG=/nonexistent' );
require __DIR__ . '/../rechnungen/lib/bootstrap.php';

$fails = 0;
$count = 0;
function ok( $cond, $msg ) {
	global $fails, $count;
	$count++;
	if ( ! $cond ) {
		$fails++;
		echo "  ✗ $msg\n";
	}
}
function eq( $a, $b, $msg ) {
	ok( $a === $b, $msg . ' (erwartet ' . var_export( $b, true ) . ', bekommen ' . var_export( $a, true ) . ')' );
}
function throws( callable $f, $msg ) {
	try {
		$f();
		ok( false, $msg . ' (keine Ausnahme)' );
	} catch ( Throwable $e ) {
		ok( true, $msg );
	}
}
function section( $t ) {
	echo "· $t\n";
}

nw_set_setting( 'company', 'Test GmbH' );
nw_set_setting( 'iban', 'AT61 1904 3002 3457 3201' );
nw_set_setting( 'bic', 'BKAUATWW' );
nw_set_setting( 'next_number', '500' );

section( 'Kunden & Artikel' );
$c = nw_customer_save( array( 'company' => 'Muster OG', 'salutation' => 'frau', 'person' => 'Eva Muster', 'street' => 'Weg 1', 'zip' => '5020', 'city' => 'Salzburg', 'email' => 'eva@example.com' ) );
eq( $c['lines'], array( 'Muster OG', 'Frau Eva Muster', 'Weg 1', '5020 Salzburg' ), 'Anschriftzeilen' );
eq( nw_customer_greeting( $c ), 'Sehr geehrte Frau Muster,', 'Anrede' );
throws( function () { nw_customer_save( array( 'company' => 'X', 'email' => 'kein-mail' ) ); }, 'ungültige E-Mail abgelehnt' );
throws( function () { nw_customer_save( array() ); }, 'leerer Kunde abgelehnt' );
$p = nw_product_save( array( 'name' => 'Hosting', 'price' => 100, 'unit' => 'Jahr', 'recurring' => 1 ) );
ok( '' !== $p['sku'], 'Artikelnummer automatisch' );

section( 'Rechnung: Summen, Nummern, Sperre' );
nw_set_setting( 'small_business', '0' );
$inv = nw_invoice_save( array( 'customer_id' => $c['id'], 'items' => array( array( 'sku' => $p['sku'], 'name' => 'Hosting', 'qty' => 1, 'price' => 100, 'discount' => 50, 'tax_rate' => 20 ), array( 'name' => 'Stunde', 'qty' => '2,5', 'price' => '40', 'tax_rate' => 20 ) ) ) );
eq( $inv['net'], 150.0, 'Netto mit Rabatt und Komma-Menge' );
eq( $inv['tax'], 30.0, 'USt. 20 %' );
eq( $inv['gross'], 180.0, 'Brutto' );
eq( $inv['number'], null, 'Entwurf ohne Nummer' );
$inv = nw_invoice_issue( $inv['id'] );
eq( $inv['number'], '500', 'erste Nummer aus Einstellung' );
$inv2 = nw_invoice_issue( nw_invoice_save( array( 'customer_id' => $c['id'], 'items' => array( array( 'name' => 'A', 'qty' => 1, 'price' => 10 ) ) ) )['id'] );
eq( $inv2['number'], '501', 'fortlaufende Nummer' );
throws( function () use ( $inv ) { nw_invoice_issue( $inv['id'] ); }, 'doppelt ausstellen verhindert' );
nw_invoice_save( array( 'id' => $inv['id'], 'items' => array( array( 'name' => 'Manipuliert', 'qty' => 1, 'price' => 1 ) ), 'note' => 'nur Notiz' ) );
$re = nw_invoice_get( $inv['id'] );
eq( $re['gross'], 180.0, 'ausgestellte Rechnung bleibt unverändert' );
eq( $re['note'], 'nur Notiz', 'interne Notiz änderbar' );
nw_set_setting( 'small_business', '1' );
$ku = nw_invoice_save( array( 'items' => array( array( 'name' => 'A', 'qty' => 1, 'price' => 100, 'tax_rate' => 20 ) ) ) );
eq( $ku['tax'], 0.0, 'Kleinunternehmer: keine USt.' );
nw_invoice_delete( $ku['id'] );

section( 'Zahlungen' );
$r = nw_invoice_pay( $inv['id'], '2026-01-10', 100 );
eq( $r['state'], 'partial', 'Teilzahlung → teilweise bezahlt' );
eq( $r['open'], 80.0, 'Restbetrag' );
$r = nw_invoice_pay( $inv['id'], '2026-01-20' );
eq( $r['state'], 'paid', 'Rest bezahlt → bezahlt' );
eq( $r['paid_at'], '2026-01-20', 'Bezahlt-Datum = letzte Zahlung' );
eq( count( $r['payments'] ), 2, 'zwei Zahlungen gespeichert' );
throws( function () use ( $inv ) { nw_invoice_pay( $inv['id'] ); }, 'bezahlte Rechnung nicht nochmals bezahlbar' );
$r = nw_invoice_unpay( $inv['id'] );
eq( $r['state'], 'partial', 'letzte Zahlung zurück → wieder teilweise' );
throws( function () use ( $inv ) { nw_invoice_pay( $inv['id'], null, -5 ); }, 'negativer Betrag abgelehnt' );

section( 'Storno & Kopie' );
$st = nw_invoice_cancel( $inv2['id'] );
eq( $st['kind'], 'storno', 'Stornorechnung angelegt' );
eq( $st['gross'], -$inv2['gross'], 'Storno hebt den Betrag genau auf' );
eq( $st['number'], '502', 'Storno bekommt eigene Nummer' );
eq( nw_invoice_get( $inv2['id'] )['state'], 'cancelled', 'Original storniert' );
$dup = nw_invoice_duplicate( $inv['id'] );
eq( $dup['status'], 'draft', 'Kopie ist Entwurf' );
eq( count( $dup['items'] ), 2, 'Kopie mit Positionen' );

section( 'Angebote' );
$o = nw_invoice_issue( nw_invoice_save( array( 'kind' => 'offer', 'customer_id' => $c['id'], 'items' => array( array( 'name' => 'Website', 'qty' => 1, 'price' => 2000 ) ) ) )['id'] );
ok( 0 === strpos( $o['number'], 'A-' ), 'Angebotsnummer A-…' );
eq( $o['state'], 'sent', 'Angebot offen' );
ok( (bool) $o['valid_until'], 'gültig bis gesetzt' );
$conv = nw_offer_convert( $o['id'] );
eq( $conv['kind'], 'invoice', 'umgewandelt in Rechnung' );
eq( $conv['gross'], 2000.0, 'Betrag übernommen' );
eq( nw_invoice_get( $o['id'] )['state'], 'accepted', 'Angebot angenommen' );
throws( function () use ( $o ) { nw_offer_convert( $o['id'] ); }, 'nicht doppelt umwandelbar' );
eq( (int) q_val( "SELECT COUNT(*) FROM invoices WHERE kind = 'offer' AND number NOT LIKE 'A-%'" ), 0, 'Angebote nie mit Rechnungsnummer' );
$dash = nw_dashboard();
ok( abs( $dash['revenue'] ) < 1e6 && ! in_array( 'offer', array_column( $dash['open'], 'kind' ), true ), 'Angebote zählen nicht als offene Rechnungen' );

section( 'Dauerrechnungen' );
$rec = nw_recurring_save( array( 'customer_id' => $c['id'], 'title' => 'Hosting', 'next_date' => '2026-01-01', 'interval_months' => 12, 'mode' => 'issue', 'active' => 1, 'items' => array( array( 'name' => 'Hosting {JAHR}', 'description' => 'Zeitraum {ZEITRAUM}', 'qty' => 1, 'price' => 100 ) ) ) );
$run = nw_recurring_run( $rec['id'] );
eq( $run['invoice']['items'][0]['name'], 'Hosting 2026', 'Platzhalter {JAHR}' );
eq( $run['invoice']['items'][0]['description'], 'Zeitraum 01.01.2026 – 31.12.2026', 'Platzhalter {ZEITRAUM}' );
eq( $run['invoice']['status'], 'issued', 'Modus ausstellen' );
eq( nw_recurring_get( $rec['id'] )['next_date'], '2027-01-01', 'nächstes Datum weitergeschaltet' );
eq( nw_add_months( '2026-01-31', 1 ), '2026-02-28', 'Monatsende korrekt' );
$send = nw_recurring_save( array( 'customer_id' => nw_customer_save( array( 'company' => 'Ohne Mail' ) )['id'], 'next_date' => '2026-01-01', 'mode' => 'send', 'active' => 1, 'items' => array( array( 'name' => 'X', 'qty' => 1, 'price' => 1 ) ) ) );
ok( '' !== nw_recurring_run( $send['id'] )['note'], 'ohne E-Mail: Hinweis statt Versand' );

section( 'Stunden' );
eq( nw_parse_hours( '1:30' ), 1.5, 'Dauer 1:30' );
eq( nw_parse_hours( '1,25' ), 1.25, 'Dauer 1,25' );
eq( nw_parse_hours( '90m' ), 1.5, 'Dauer 90m' );
eq( nw_parse_hours( '1h 15m' ), 1.25, 'Dauer 1h 15m' );
throws( function () use ( $c ) { nw_time_save( array( 'customer_id' => $c['id'], 'hours' => '0' ) ); }, 'Dauer 0 abgelehnt' );
throws( function () { nw_time_save( array( 'customer_id' => 999999, 'hours' => 1 ) ); }, 'unbekannter Kunde abgelehnt' );
$t1 = nw_time_save( array( 'customer_id' => $c['id'], 'date' => '2026-03-01', 'hours' => '2', 'project' => 'Relaunch', 'note' => 'Startseite' ) );
$t2 = nw_time_save( array( 'customer_id' => $c['id'], 'date' => '2026-03-02', 'hours' => '1:30', 'note' => 'Kontaktformular' ) );
$t3 = nw_time_save( array( 'customer_id' => $c['id'], 'date' => '2026-03-03', 'hours' => '1' ) );
eq( nw_time_summary()['open_h'], 4.5, 'offene Stunden summiert' );
$dr = nw_invoice_save( array( 'customer_id' => $c['id'], 'items' => array( array( 'name' => 'Arbeitsstunden', 'qty' => 3.5, 'price' => 80 ) ), 'time_ids' => array( $t1['id'], $t2['id'] ) ) );
eq( nw_time_get( $t1['id'] )['state'], 'draft', 'Stunden im Entwurf reserviert' );
eq( $dr['time_ids'], array( $t1['id'], $t2['id'] ), 'Entwurf kennt seine Stunden' );
$other = nw_customer_save( array( 'company' => 'Fremd' ) );
$t4    = nw_time_save( array( 'customer_id' => $other['id'], 'hours' => 1 ) );
nw_invoice_save( array( 'id' => $dr['id'], 'customer_id' => $c['id'], 'items' => $dr['items'], 'time_ids' => array( $t1['id'], $t4['id'] ) ) );
eq( nw_time_get( $t2['id'] )['state'], 'open', 'abgewählte Stunden wieder frei' );
eq( nw_time_get( $t4['id'] )['state'], 'open', 'Stunden fremder Kunden nicht verknüpfbar' );
nw_invoice_issue( $dr['id'] );
eq( nw_time_get( $t1['id'] )['state'], 'billed', 'beim Ausstellen abgerechnet' );
throws( function () use ( $t1 ) { nw_time_save( array( 'id' => $t1['id'], 'customer_id' => $t1['customer_id'], 'hours' => 5 ) ); }, 'abgerechnete Dauer nicht änderbar' );
throws( function () use ( $t1 ) { nw_time_delete( $t1['id'] ); }, 'abgerechnete Stunden nicht löschbar' );
$tc = nw_time_save( array( 'customer_id' => $c['id'], 'hours' => '2' ) );
$pa = nw_invoice_save( array( 'customer_id' => $c['id'], 'items' => array( array( 'name' => 'Pauschale', 'qty' => 1, 'price' => 300 ) ), 'time_ids' => array( $tc['id'] ) ) );
eq( array( $pa['time_hours'], count( $pa['items'] ) ), array( 2.0, 1 ), 'Stunden ohne eigene Position bestätigt' );
nw_invoice_issue( $pa['id'] );
eq( array( nw_time_get( $tc['id'] )['state'], (int) nw_time_get( $tc['id'] )['invoice_id'] ), array( 'billed', (int) $pa['id'] ), 'bestätigte Stunden mit der Rechnung verrechnet' );
ok( ! in_array( $tc['id'], array_column( nw_time_list( array( 'state' => 'open' ) ), 'id' ), true ), 'bestätigte Stunden nicht mehr offen' );
eq( nw_time_mark( array( $t3['id'] ), true ), 1, 'von Hand als abgerechnet markiert' );
eq( nw_time_mark( array( $t3['id'] ), false ), 1, 'wieder geöffnet' );

section( 'Löschen & Nummernkreis' );
$next = (int) nw_setting( 'next_number' );
$a    = nw_invoice_issue( nw_invoice_save( array( 'customer_id' => $c['id'], 'items' => array( array( 'name' => 'A', 'qty' => 1, 'price' => 1 ) ) ) )['id'] );
$b    = nw_invoice_issue( nw_invoice_save( array( 'customer_id' => $c['id'], 'items' => array( array( 'name' => 'B', 'qty' => 1, 'price' => 1 ) ) ) )['id'] );
eq( (int) $b['number'], (int) $a['number'] + 1, 'zwei Rechnungen hintereinander' );
$r = nw_invoice_delete( $a['id'] );
ok( ! $r['reused'], 'mittlere Rechnung gelöscht – Lücke bleibt' );
eq( nw_next_number(), (string) ( (int) $b['number'] + 1 ), 'es wird weitergezählt' );
$r = nw_invoice_delete( $b['id'] );
ok( $r['reused'], 'letzte Rechnung gelöscht – Nummer wird frei' );
eq( nw_next_number(), $b['number'], 'gelöschte letzte Nummer wird wieder vergeben' );
$x  = nw_invoice_issue( nw_invoice_save( array( 'customer_id' => $c['id'], 'items' => array( array( 'name' => 'X', 'qty' => 1, 'price' => 5 ) ) ) )['id'] );
nw_invoice_pay( $x['id'], null, 2 );
$xs = nw_invoice_cancel( $x['id'] );
throws( function () use ( $x ) { nw_invoice_delete( $x['id'] ); }, 'Rechnung mit Storno nicht direkt löschbar' );
nw_invoice_delete( $xs['id'] );
eq( nw_invoice_get( $x['id'] )['status'], 'issued', 'Storno gelöscht → Rechnung gilt wieder' );
nw_invoice_delete( $x['id'] );
eq( (int) q_val( 'SELECT COUNT(*) FROM payments WHERE invoice_id = ?', array( $x['id'] ) ), 0, 'Zahlungen mitgelöscht' );
$tb = nw_time_save( array( 'customer_id' => $c['id'], 'hours' => 1 ) );
$y  = nw_invoice_issue( nw_invoice_save( array( 'customer_id' => $c['id'], 'items' => array( array( 'name' => 'Y', 'qty' => 1, 'price' => 1 ) ), 'time_ids' => array( $tb['id'] ) ) )['id'] );
nw_invoice_delete( $y['id'] );
eq( nw_time_get( $tb['id'] )['state'], 'open', 'Stunden gelöschter Rechnung wieder offen' );
$ao = nw_invoice_issue( nw_invoice_save( array( 'kind' => 'offer', 'customer_id' => $c['id'], 'items' => array( array( 'name' => 'O', 'qty' => 1, 'price' => 1 ) ) ) )['id'] );
$on = nw_next_offer_number();
ok( nw_invoice_delete( $ao['id'] )['reused'] && nw_next_offer_number() === $ao['number'], 'letztes Angebot gelöscht – Nummer frei (' . $on . ' → ' . $ao['number'] . ')' );

section( 'E-Mail-Protokoll' );
$res = nw_mail_send( array( 'host' => '', 'port' => 465 ), array( 'from' => 'a@b.at', 'from_name' => 'Test', 'to' => array( 'c@d.at' ), 'cc' => array( 'e@f.at' ), 'bcc' => array( 'g@h.at' ), 'subject' => 'Betreff', 'text' => 't', 'attachments' => array( array( 'name' => 'R.pdf', 'type' => 'application/pdf', 'data' => str_repeat( 'x', 2048 ) ) ) ), array( 'kind' => 'invoice', 'number' => '42', 'invoice_id' => null ) );
ok( ! $res['ok'], 'ohne Server: Fehler zurückgemeldet' );
$log = nw_mail_log()['rows'][0];
eq( array( $log['to_addr'], $log['cc'], $log['bcc'], $log['number'], (int) $log['ok'] ), array( 'c@d.at', 'e@f.at', 'g@h.at', '42', 0 ), 'Versuch mit Empfängern, Beleg und Status protokolliert' );
ok( false !== strpos( $log['attachment'], 'R.pdf' ) && '' !== $log['error'], 'Anhang und Fehlermeldung protokolliert' );
eq( count( nw_mail_log( array( 'errors' => true ) )['rows'] ), 1, 'Filter „nur Fehler“' );

section( 'Speicherplatz' );
$st = nw_storage_info();
ok( $st['db']['bytes'] > 0 && $st['total'] >= $st['db']['bytes'], 'Datenbankgröße ermittelt' );
ok( $st['db']['ok'], 'Datenbank-Prüfung in Ordnung' );
$tn = array_column( $st['tables'], 'rows', 'name' );
eq( $tn['time_entries'], (int) q_val( 'SELECT COUNT(*) FROM time_entries' ), 'Einträge je Bereich gezählt' );
q( "INSERT INTO activity (invoice_id, text, created_at) SELECT NULL, printf('%.2000c', 'x'), datetime('now') FROM invoice_items LIMIT 200" );
q( "DELETE FROM activity WHERE length(text) = 2000" );
$op = nw_db_optimize();
eq( $op['db']['free'], 0, 'Optimieren gibt freien Platz zurück' );

section( 'PDF & Schrumpfen' );
$pdf = NW_Document::render( nw_invoice_get( $inv['id'] ) );
ok( 0 === strpos( $pdf, '%PDF-1.4' ) && false !== strpos( $pdf, '%%EOF' ), 'gültiges PDF' );
$long = array();
for ( $i = 0; $i < 4; $i++ ) {
	$long[] = array( 'name' => 'Position ' . $i, 'description' => str_repeat( "Zeile mit Beschreibung\n", 3 ), 'qty' => 1, 'price' => 10 );
}
$li  = nw_invoice_issue( nw_invoice_save( array( 'customer_id' => $c['id'], 'items' => $long ) )['id'] );
$cnt = function ( $pdf ) { return preg_match_all( '#/Type /Page[^s]#', $pdf ); };
$li['compact'] = 'off';
$off           = $cnt( NW_Document::render( $li ) );
$li['compact'] = 'auto';
$auto          = $cnt( NW_Document::render( $li ) );
ok( $auto <= $off, "Schrumpfen spart Seiten ($off → $auto)" );
echo "    Seiten normal $off, geschrumpft $auto\n";
ok( 1 === $auto, 'lange Rechnung passt geschrumpft auf eine Seite' );
ok( null !== NW_QR::epc_payload( 'Test GmbH', 'AT61 1904 3002 3457 3201', 'BKAUATWW', 12.5, 'Rechnung 1' ), 'SEPA-QR-Inhalt' );
eq( NW_QR::epc_payload( 'X', 'kaputt', '', 1, '' ), null, 'ungültige IBAN → kein QR' );
$pdftext = function ( $pdf ) {
	preg_match_all( '/stream\n(.*?)\nendstream/s', $pdf, $m );
	return implode( '', array_map( function ( $x ) { return @gzuncompress( $x ) ?: $x; }, $m[1] ) );
};
$nu = nw_invoice_get( nw_invoice_save( array( 'items' => array( array( 'name' => 'Ohne Einheit', 'qty' => 1, 'price' => 1 ) ) ) )['id'] );
ok( false === strpos( $pdftext( NW_Document::render( $nu ) ), '(Einheit)' ), 'ohne Einheiten keine Spalte „Einheit“' );
$wu = nw_invoice_get( nw_invoice_save( array( 'items' => array( array( 'name' => 'Mit', 'qty' => 1, 'unit' => 'Std.', 'price' => 1 ) ) ) )['id'] );
ok( false !== strpos( $pdftext( NW_Document::render( $wu ) ), '(Einheit)' ), 'mit Einheit erscheint die Spalte' );

section( 'Logo: SVG bereinigen, PNG/JPG lesen' );
$evil = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)"><script>alert(2)</script><a href="javascript:x"><rect width="10" height="10" fill="#ff0000" onclick="alert(3)"/></a><text>t</text></svg>';
nw_logo_save( $evil );
$html = nw_logo_html();
ok( false === stripos( $html, 'script' ) && false === stripos( $html, 'onload' ) && false === stripos( $html, 'onclick' ) && false === stripos( $html, 'javascript' ), 'SVG ohne Skripte/Ereignisse/Links' );
ok( false !== strpos( $html, '#ff0000' ), 'Formen und Farbe bleiben' );
throws( function () { nw_logo_save( '<svg xmlns="http://www.w3.org/2000/svg"><text>nur Text</text></svg>' ); }, 'SVG ohne Formen abgelehnt' );
throws( function () { nw_logo_save( '<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><svg>&e;</svg>' ); }, 'XML-Entities (XXE) abgelehnt' );
$png = nw_png_to_pdf( file_get_contents( __DIR__ . '/../docs/design.png' ) );
ok( $png['w'] > 0 && strlen( $png['data'] ) > 100, 'PNG gelesen' );
throws( function () { nw_logo_save( "\x89PNGkaputt" ); }, 'kaputtes PNG abgelehnt' );
nw_logo_delete();

section( 'Hilfsfunktionen & Sicherheit' );
$zip = nw_zip( array( 'a.txt' => 'Hallo', 'ordner/b.bin' => random_bytes( 3000 ) ) );
$un  = nw_unzip( $zip );
eq( $un['a.txt'], 'Hallo', 'ZIP schreiben und lesen' );
$enc = nw_encrypt( 'Geheim!' );
eq( nw_decrypt( $enc ), 'Geheim!', 'Verschlüsselung' );
ok( false === strpos( base64_decode( $enc ), 'Geheim' ), 'kein Klartext' );
$bad = base64_decode( $enc );
$bad[ strlen( $bad ) - 1 ] = chr( ord( $bad[ strlen( $bad ) - 1 ] ) ^ 1 );
eq( nw_decrypt( base64_encode( $bad ) ), '', 'manipulierte Daten werden verworfen' );
ok( ! preg_grep( '/[\r\n:]/', nw_emails( "a@b.at, böse\r\nBcc: x@y.at; c@d.at" ) ), 'Adressen einzeln geprüft – keine Header-Injection' );
$mail = NW_SMTP::build( array( 'from' => 'a@b.at', 'to' => array( 'c@d.at' ), 'subject' => "Hallo\r\nBcc: x@y.at", 'text' => 't' ) );
ok( false === strpos( $mail, "\r\nBcc:" ), 'Betreff ohne Header-Injection' );
eq( strlen( nw_setup_code() ), 8, 'Einrichtungscode' );
eq( nw_slug( 'Rechnung "../x" Ä' ), 'Rechnung-.-x-Ae', 'Dateinamen bereinigt' );
ok( false === strpos( nw_slug( '../../etc/passwd' ), '..' ) && false === strpos( nw_slug( 'a/b\\c' ), '/' ), 'keine Pfadanteile in Dateinamen' );

// Aufräumen
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tmp, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $f ) {
	$f->isDir() ? rmdir( $f ) : unlink( $f );
}
rmdir( $tmp );

echo $fails ? "\n$fails von $count Tests FEHLGESCHLAGEN\n" : "\nAlle $count Tests bestanden\n";
exit( $fails ? 1 : 0 );
