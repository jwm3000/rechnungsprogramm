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
throws( function () use ( $inv ) { nw_invoice_delete( $inv['id'] ); }, 'ausgestellte Rechnung nicht löschbar' );
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
