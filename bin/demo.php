<?php
/**
 * Legt einen Demo-Datenbestand mit erfundenen Kunden an – für Screenshots und zum Ausprobieren.
 *
 *   NW_DATA_DIR=/app/demo/data bin/php bin/demo.php
 *
 * Bricht ab, wenn im Datenordner schon Rechnungen liegen (schützt echte Daten).
 */
if ( ! getenv( 'NW_DATA_DIR' ) ) {
	fwrite( STDERR, "Bitte NW_DATA_DIR auf einen eigenen Demo-Ordner setzen.\n" );
	exit( 1 );
}
require __DIR__ . '/../rechnungen/lib/bootstrap.php';
if ( (int) q_val( 'SELECT COUNT(*) FROM invoices' ) > 0 ) {
	fwrite( STDERR, "Im Demo-Ordner liegen schon Rechnungen – abgebrochen.\n" );
	exit( 1 );
}

foreach (
	array(
		'company'      => 'Norbert Winter',
		'tagline'      => 'webdesign & development',
		'owner'        => 'Norbert Winter',
		'street'       => 'Beispielgasse 12',
		'zip'          => '5541',
		'city'         => 'Altenmarkt im Pongau',
		'email'        => 'office@example.com',
		'web'          => 'www.example.com',
		'iban'         => 'AT61 1904 3002 3457 3201',
		'bic'          => 'BKAUATWW',
		'bank_owner'   => 'Norbert Winter',
		'next_number'  => '1001',
		'next_customer' => '501',
		'next_sku'     => '101',
		'payment_days' => '14',
		'password_hash' => password_hash( 'demo1234', PASSWORD_DEFAULT ),
	) as $k => $v
) {
	nw_set_setting( $k, $v );
}

$c = array();
foreach (
	array(
		'alm'    => array( 'company' => 'Ferienhof Alpenblick', 'salutation' => 'frau', 'person' => 'Anna Gruber', 'street' => 'Sonnenweg 4', 'zip' => '5550', 'city' => 'Radstadt', 'email' => 'info@alpenblick.example' ),
		'tisch'  => array( 'company' => 'Tischlerei Holzmann GmbH', 'salutation' => 'herr', 'person' => 'Martin Holzmann', 'street' => 'Werkstraße 18', 'zip' => '5542', 'city' => 'Flachau', 'vat_id' => 'ATU12345678', 'email' => 'buero@holzmann.example' ),
		'cafe'   => array( 'company' => 'Café Lindenhof', 'salutation' => 'herr', 'person' => 'Peter Lindner', 'street' => 'Marktplatz 3', 'zip' => '5541', 'city' => 'Altenmarkt im Pongau', 'email' => 'cafe@lindenhof.example' ),
		'musik'  => array( 'company' => 'Musikverein Talheim', 'person' => 'Obfrau Sabine Moser', 'street' => 'Schulgasse 1', 'zip' => '5531', 'city' => 'Eben im Pongau' ),
		'arch'   => array( 'company' => 'Studio Kranz Architektur', 'salutation' => 'frau', 'person' => 'Lena Kranz', 'street' => 'Linzer Gasse 22', 'zip' => '5020', 'city' => 'Salzburg', 'vat_id' => 'ATU87654321', 'email' => 'office@kranz.example' ),
		'berg'   => array( 'company' => 'Bergbahnen Sonnalm GmbH', 'company2' => 'Marketing', 'salutation' => 'herr', 'person' => 'Thomas Rainer', 'street' => 'Talstation 1', 'zip' => '5562', 'city' => 'Obertauern', 'email' => 'marketing@sonnalm.example' ),
	) as $k => $data
) {
	$c[ $k ] = nw_customer_save( $data + array( 'country' => 'Österreich' ) );
}

$p = array();
foreach (
	array(
		'host'  => array( 'Hosting & Domain', 'Webserver, 1 Domain, 5 Postfächer', 'Jahr', 120, 'Hosting', 1 ),
		'host2' => array( 'Hosting Plus', 'Webserver, 2 Domains, 20 Postfächer, tägliche Sicherung', 'Jahr', 190, 'Hosting', 1 ),
		'lic'   => array( 'Sicherheitslizenz', "Jahreslizenz Malware-Schutz\nÜberwachung und automatische Bereinigung", 'Jahr', 150, 'Lizenzen', 1 ),
		'care'  => array( 'Wartung & Updates', "Updates von CMS und Plugins\nSicherung und Kontrolle", 'Quartal', 90, 'Betreuung', 1 ),
		'hour'  => array( 'Arbeitsstunde', '', 'Std.', 75, 'Betreuung', 0 ),
		'web'   => array( 'Website CMS', "Konzept, Design und Umsetzung\nResponsive, Inhalte pflegbar", 'pauschal', 2400, 'Webseiten', 0 ),
		'logo'  => array( 'Logo & Basisdesign', '', 'pauschal', 480, 'Webseiten', 0 ),
		'seo'   => array( 'Google-Unternehmensprofil', 'Einrichtung und Optimierung', 'pauschal', 120, 'Webseiten', 0 ),
	) as $k => $x
) {
	$p[ $k ] = nw_product_save( array( 'name' => $x[0], 'description' => $x[1], 'unit' => $x[2], 'price' => $x[3], 'category' => $x[4], 'recurring' => $x[5], 'tax_rate' => 0 ) );
}

$item = function ( $k, $qty = 1, $discount = 0, $desc = null ) use ( $p ) {
	return array( 'product_id' => $p[ $k ]['id'], 'sku' => $p[ $k ]['sku'], 'name' => $p[ $k ]['name'], 'description' => null === $desc ? $p[ $k ]['description'] : $desc, 'unit' => $p[ $k ]['unit'], 'qty' => $qty, 'price' => $p[ $k ]['price'], 'discount' => $discount );
};
$make = function ( $cust, $date, array $items, $paid = null, array $extra = array() ) {
	$inv = nw_invoice_save( array( 'customer_id' => $cust['id'], 'invoice_date' => $date, 'service_date' => $date, 'items' => $items ) + $extra );
	$inv = nw_invoice_issue( $inv['id'] );
	q( 'UPDATE invoices SET issued_at = ?, created_at = ? WHERE id = ?', array( $date . ' 10:00:00', $date . ' 10:00:00', $inv['id'] ) );
	if ( $paid ) {
		nw_invoice_pay( $inv['id'], $paid );
	}
	return $inv;
};

// 2025
$make( $c['alm'], '2025-01-14', array( $item( 'host' ), $item( 'lic' ) ), '2025-01-20' );
$make( $c['tisch'], '2025-02-03', array( $item( 'web', 1, 10, "Neue Website holzmann.example\nKonzept, Design, 8 Unterseiten, Referenzgalerie" ), $item( 'logo' ) ), '2025-02-12' );
$make( $c['cafe'], '2025-03-21', array( $item( 'host' ), $item( 'hour', 2.5, 0, 'Speisekarte als PDF, Öffnungszeiten' ) ), '2025-04-01' );
$make( $c['musik'], '2025-05-08', array( $item( 'host', 1, 50 ) ), '2025-05-30' );
$make( $c['arch'], '2025-06-17', array( $item( 'web', 1, 0, "Portfolio-Website\nProjektseiten mit Bildergalerie, zweisprachig" ), $item( 'host2' ) ), '2025-06-30' );
$make( $c['berg'], '2025-09-02', array( $item( 'hour', 12, 0, "Wintersaison: Liftstatus-Widget, Webcam-Einbindung\nTicketpreise 2025/26" ) ), '2025-09-15' );
$make( $c['tisch'], '2025-11-10', array( $item( 'care', 4 ), $item( 'seo' ) ), '2025-11-19' );
$make( $c['cafe'], '2025-12-04', array( $item( 'hour', 3, 0, 'Gutscheinverkauf online, Weihnachtsangebot' ) ), '2025-12-10' );

// 2026
$make( $c['alm'], '2026-01-12', array( $item( 'host' ), $item( 'lic' ) ), '2026-01-19' );
$make( $c['tisch'], '2026-02-02', array( $item( 'host2' ), $item( 'care', 4 ) ), '2026-02-16' );
$make( $c['cafe'], '2026-03-20', array( $item( 'host' ) ), '2026-03-27' );
$make( $c['musik'], '2026-05-06', array( $item( 'host', 1, 50 ), $item( 'hour', 2, 100, 'Konzertankündigung Frühjahr (gratis)' ) ), '2026-05-20' );
$make( $c['arch'], '2026-06-15', array( $item( 'host2' ), $item( 'lic' ) ), '2026-06-22' );
$bad = $make( $c['berg'], '2026-07-01', array( $item( 'hour', 6, 0, 'Sommer-Relaunch Startseite' ) ) );
nw_invoice_cancel( $bad['id'] );
q( "UPDATE invoices SET invoice_date = '2026-07-03', issued_at = '2026-07-03 09:00:00' WHERE kind = 'storno'" );
$make( $c['berg'], '2026-07-03', array( $item( 'hour', 5, 0, 'Sommer-Relaunch Startseite' ) ), '2026-07-15' );
$make( $c['tisch'], '2026-08-20', array( $item( 'hour', 4, 0, "Neue Referenzen, Karriereseite\nStellenanzeige Lehrling" ) ) );
$make( $c['cafe'], '2026-09-24', array( $item( 'seo' ), $item( 'hour', 1.5 ) ) );
$make( $c['arch'], '2026-10-01', array( $item( 'hour', 6, 0, "Erweiterung Projektarchiv\nFilter nach Jahr und Kategorie" ) ) );

// Entwurf
nw_invoice_save( array( 'customer_id' => $c['berg']['id'], 'invoice_date' => '2026-10-07', 'subject' => 'Winter 2026/27', 'items' => array( $item( 'hour', 10, 0, "Wintersaison 2026/27\nLiftstatus, Pistenplan, Ticketpreise" ), $item( 'lic' ) ) ) );

// Dauerrechnungen
foreach (
	array(
		array( 'alm', 'Hosting & Lizenz', '2027-01-12', 'send', array( $item( 'host', 1, 0, 'Zeitraum {ZEITRAUM}' ), $item( 'lic' ) ) ),
		array( 'tisch', 'Hosting Plus', '2027-02-02', 'send', array( $item( 'host2', 1, 0, 'Zeitraum {ZEITRAUM}' ) ) ),
		array( 'tisch', 'Wartung', '2026-11-01', 'send', array( $item( 'care', 1, 0, 'Quartal ab {MONAT}' ) ), 3 ),
		array( 'cafe', 'Hosting', '2027-03-20', 'send', array( $item( 'host' ) ) ),
		array( 'musik', 'Hosting (Vereinsrabatt)', '2027-05-06', 'issue', array( $item( 'host', 1, 50 ) ) ),
		array( 'arch', 'Hosting & Lizenz', '2027-06-15', 'draft', array( $item( 'host2' ), $item( 'lic' ) ) ),
	) as $r
) {
	nw_recurring_save( array( 'customer_id' => $c[ $r[0] ]['id'], 'title' => $r[1], 'next_date' => $r[2], 'mode' => $r[3], 'items' => $r[4], 'interval_months' => $r[5] ?? 12, 'active' => 1 ) );
}

// Ausgaben
foreach (
	array(
		array( '2026-01-05', 'Hosting-Anbieter', 'Webserver Jahresmiete', 'Hosting & Server', 480 ),
		array( '2026-02-11', 'Domainregistrar', '12 Domains Verlängerung', 'Hosting & Server', 168 ),
		array( '2026-03-02', 'Softwarehaus', 'Designsoftware Jahreslizenz', 'Software & Lizenzen', 290 ),
		array( '2026-06-18', 'Elektronikmarkt', 'Monitor 27"', 'Hardware', 349 ),
	) as $e
) {
	nw_expense_save( array( 'date' => $e[0], 'vendor' => $e[1], 'description' => $e[2], 'category' => $e[3], 'amount' => $e[4] ) );
}
q( "DELETE FROM activity WHERE text LIKE 'Kunde angelegt%' OR text LIKE 'Dauerrechnung angelegt%'" );
echo "Demo angelegt: Passwort demo1234\n";
