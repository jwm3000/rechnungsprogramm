<?php
/**
 * Demo-Datenbestand „jwm3000 GmbH“ – erfundene Firma, erfundene Kunden, Seitei und Schnapsei.
 * Für Screenshots in der README und zum Ausprobieren.
 *
 *   NW_DATA_DIR=/app/demo/data bin/php bin/demo.php      (Passwort: demo1234)
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
		'company'        => 'jwm3000 GmbH',
		'tagline'        => 'Getränke & Ausschank seit 1897',
		'owner'          => 'Gustl Durstberger',
		'street'         => 'Brauhausgasse 3',
		'zip'            => '1070',
		'city'           => 'Wien',
		'phone'          => '+43 1 234 56 78',
		'email'          => 'prost@jwm3000.example',
		'web'            => 'www.jwm3000.example',
		'vat_id'         => 'ATU99999999',
		'bank'           => 'Hopfenbank',
		'iban'           => 'AT61 1904 3002 3457 3201',
		'bic'            => 'BKAUATWW',
		'bank_owner'     => 'jwm3000 GmbH',
		'small_business' => '0',
		'default_tax'    => '20',
		'next_number'    => '1001',
		'next_customer'  => '501',
		'next_sku'       => '101',
		'payment_days'   => '14',
		'greeting'       => 'Servus,',
		'intro'          => 'vielen Dank für den durstigen Auftrag – wir erlauben uns, Folgendes in Rechnung zu stellen:',
		'outro'          => 'Prost und bis zum nächsten Seitei!',
		'offer_intro'    => 'danke für die Anfrage! Für euer Fest bieten wir gerne an:',
		'offer_outro'    => 'Leergut nehmen wir selbstverständlich wieder mit. Prost!',
		'password_hash'  => password_hash( 'demo1234', PASSWORD_DEFAULT ),
	) as $k => $v
) {
	nw_set_setting( $k, $v );
}
copy( __DIR__ . '/../docs/logo.svg', nw_logo_file() );

$c = array();
foreach (
	array(
		'hirsch' => array( 'company' => 'Stammtisch „Zum Durstigen Hirschen“', 'salutation' => 'herr', 'person' => 'Hias Moosbrugger', 'street' => 'Wirtshausgasse 1', 'zip' => '5541', 'city' => 'Altenmarkt im Pongau', 'email' => 'stammtisch@hirsch.example' ),
		'ffw'    => array( 'company' => 'Freiwillige Feuerwehr Unterdorf', 'salutation' => 'herr', 'person' => 'Sepp Grabner', 'street' => 'Zeughausplatz 2', 'zip' => '4861', 'city' => 'Unterdorf', 'email' => 'kommando@ff-unterdorf.example' ),
		'kegel'  => array( 'company' => 'Kegelclub „Alle Neune“', 'salutation' => 'frau', 'person' => 'Resi Kranawetter', 'street' => 'Bahngasse 9', 'zip' => '3100', 'city' => 'St. Pölten', 'email' => 'allezneune@kegeln.example' ),
		'musik'  => array( 'company' => 'Musikkapelle Oberbrunn', 'person' => 'Kapellmeister Toni Blechinger', 'street' => 'Probelokal 4', 'zip' => '6020', 'city' => 'Innsbruck', 'email' => 'kapelle@oberbrunn.example' ),
		'golf'   => array( 'company' => 'Golfclub Am Grünen Hügel', 'company2' => 'Clubhaus', 'salutation' => 'frau', 'person' => 'Dr. Vera Lochner', 'street' => 'Fairway 18', 'zip' => '8010', 'city' => 'Graz', 'vat_id' => 'ATU11111111', 'email' => 'clubhaus@gruenerhuegel.example' ),
		'hochz'  => array( 'person' => 'Lena Pichler & Max Leitner', 'street' => 'Lindenallee 12', 'zip' => '5020', 'city' => 'Salzburg', 'email' => 'lena.max@hochzeit.example' ),
	) as $k => $data
) {
	$c[ $k ] = nw_customer_save( $data + array( 'country' => 'Österreich' ) );
}

$p = array();
foreach (
	array(
		'seitei'   => array( 'Seitei Märzen', '0,3 l frisch gezapft', 'Stk.', 3.20, 'Bier', 0 ),
		'kruegerl' => array( 'Krügerl Märzen', '0,5 l für die ganz Durstigen', 'Stk.', 4.60, 'Bier', 0 ),
		'radler'   => array( 'Seitei Radler', '0,3 l, halb Bier, halb Kracherl', 'Stk.', 3.20, 'Bier', 0 ),
		'obstler'  => array( 'Schnapsei Obstler', '2 cl, hausgebrannt', 'Stk.', 2.50, 'Schnaps', 0 ),
		'marille'  => array( 'Schnapsei Marille', '2 cl, aus der Wachau', 'Stk.', 2.90, 'Schnaps', 0 ),
		'runde'    => array( 'Runde Schnapsei', '10 × 2 cl gemischt – fürs ganze Lokal', 'Runde', 24.00, 'Schnaps', 0 ),
		'fass'     => array( 'Fass Märzen 50 l', 'inkl. Kühlung und Kohlensäure', 'Fass', 165.00, 'Fest & Ausschank', 0 ),
		'zapf'     => array( 'Zapfanlage', 'Leihgebühr pro Wochenende', 'Wochenende', 60.00, 'Fest & Ausschank', 0 ),
		'glas'     => array( 'Gläser-Leihpaket', '100 Seitei-Gläser, gewaschen retour', 'Paket', 25.00, 'Fest & Ausschank', 0 ),
		'liefer'   => array( 'Lieferung & Abholung', 'inkl. Leergut', 'pauschal', 35.00, 'Fest & Ausschank', 0 ),
		'stamm'    => array( 'Stammtisch-Pauschale', "Reservierter Ecktisch, 1 Seitei pro Kopf und Woche\nSchnapsei zum Geburtstag inklusive", 'Monat', 89.00, 'Abos', 1 ),
		'fassabo'  => array( 'Fassbier-Abo Vereinsheim', '2 Fässer Märzen pro Quartal, geliefert und angeschlossen', 'Quartal', 340.00, 'Abos', 1 ),
	) as $k => $x
) {
	$p[ $k ] = nw_product_save( array( 'name' => $x[0], 'description' => $x[1], 'unit' => $x[2], 'price' => $x[3], 'category' => $x[4], 'recurring' => $x[5], 'tax_rate' => 20 ) );
}

$item = function ( $k, $qty = 1, $discount = 0, $desc = null ) use ( $p ) {
	return array( 'product_id' => $p[ $k ]['id'], 'sku' => $p[ $k ]['sku'], 'name' => $p[ $k ]['name'], 'description' => null === $desc ? $p[ $k ]['description'] : $desc, 'unit' => $p[ $k ]['unit'], 'qty' => $qty, 'price' => $p[ $k ]['price'], 'discount' => $discount, 'tax_rate' => 20 );
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
$make( $c['hirsch'], '2025-01-31', array( $item( 'stamm' ), $item( 'runde', 2, 0, 'Neujahrs-Runde, zweimal – weil’s so schön war' ) ), '2025-02-05' );
$make( $c['ffw'], '2025-03-15', array( $item( 'fassabo' ) ), '2025-03-28' );
$make( $c['kegel'], '2025-04-12', array( $item( 'seitei', 46, 0, 'Abschlusskegeln Frühjahrsrunde' ), $item( 'obstler', 18, 0, 'Für jede Neun ein Schnapsei' ) ), '2025-04-20' );
$make( $c['musik'], '2025-06-21', array( $item( 'fass', 3, 0, 'Sonnwendfest am Probelokal' ), $item( 'zapf' ), $item( 'glas', 2 ), $item( 'liefer' ) ), '2025-07-02' );
$make( $c['hochz'], '2025-08-09', array( $item( 'kruegerl', 120 ), $item( 'marille', 60, 0, 'Schnapsei-Bar nach dem Tortenanschnitt' ), $item( 'runde', 3, 100, 'Aufs Haus – alles Gute euch zwei!' ) ), '2025-08-20' );
$make( $c['ffw'], '2025-09-06', array( $item( 'fass', 6, 5, 'Feuerwehrfest – Mengenrabatt für die Florianijünger' ), $item( 'zapf', 2 ), $item( 'glas', 4 ), $item( 'liefer' ) ), '2025-09-19' );
$make( $c['golf'], '2025-10-18', array( $item( 'seitei', 80, 0, '19. Loch nach dem Herbstturnier' ), $item( 'radler', 40 ) ), '2025-11-03' );
$make( $c['hirsch'], '2025-12-31', array( $item( 'stamm' ), $item( 'runde', 4, 0, 'Silvester – um Mitternacht, um eins, um zwei, um drei' ) ), '2026-01-08' );

// 2026
$make( $c['hirsch'], '2026-01-31', array( $item( 'stamm' ) ), '2026-02-04' );
$make( $c['kegel'], '2026-02-14', array( $item( 'seitei', 38 ), $item( 'marille', 12, 0, 'Valentins-Schnapsei für die Siegerin' ) ), '2026-02-25' );
$make( $c['ffw'], '2026-03-15', array( $item( 'fassabo' ) ), '2026-03-30' );
$make( $c['musik'], '2026-05-01', array( $item( 'fass', 2, 0, 'Maibaumaufstellen' ), $item( 'obstler', 40, 0, 'Für die Burschen am Seil' ), $item( 'zapf' ), $item( 'liefer' ) ), '2026-05-12' );
$bad = $make( $c['golf'], '2026-06-20', array( $item( 'seitei', 50 ) ) );
nw_invoice_cancel( $bad['id'] );
q( "UPDATE invoices SET invoice_date = '2026-06-22', issued_at = '2026-06-22 09:00:00' WHERE kind = 'storno'" );
$make( $c['golf'], '2026-06-22', array( $item( 'seitei', 50 ), $item( 'radler', 25, 0, 'Nachbestellt – es war heiß am Grün' ) ), '2026-07-03' );
$make( $c['hochz'], '2026-08-15', array( $item( 'kruegerl', 30, 0, 'Polterabend' ), $item( 'runde', 2 ) ), '2026-08-28' );
$ffw = $make( $c['ffw'], '2026-09-05', array( $item( 'fass', 5, 5, 'Feuerwehrfest 2026' ), $item( 'zapf', 2 ), $item( 'glas', 4 ), $item( 'liefer' ) ) );
nw_invoice_pay( $ffw['id'], '2026-09-20', 500, 'Anzahlung aus der Festkasse' );
$make( $c['kegel'], '2026-09-26', array( $item( 'seitei', 52, 0, 'Herbstmeisterschaft' ), $item( 'obstler', 24 ) ) );
$make( $c['hirsch'], '2026-09-30', array( $item( 'stamm' ), $item( 'runde', 1, 0, 'Hias hat Geburtstag' ) ) );

// Entwurf
nw_invoice_save( array( 'customer_id' => $c['golf']['id'], 'invoice_date' => '2026-10-07', 'subject' => 'Saisonabschluss', 'items' => array( $item( 'seitei', 90 ), $item( 'marille', 30, 0, 'Birdie-Schnapsei' ), $item( 'liefer' ) ) ) );

// Angebote
$offer = function ( $cust, $date, $subject, array $items, $valid ) {
	$o = nw_invoice_save( array( 'kind' => 'offer', 'customer_id' => $cust['id'], 'invoice_date' => $date, 'valid_until' => $valid, 'subject' => $subject, 'items' => $items ) );
	return nw_invoice_issue( $o['id'] );
};
$o1 = $offer( $c['hochz'], '2026-06-01', 'Polterabend', array( $item( 'kruegerl', 30 ), $item( 'runde', 2 ) ), '2026-07-01' );
nw_offer_state( $o1['id'], 'accepted' );
nw_offer_convert( $o1['id'] );
$offer( $c['musik'], '2026-09-28', 'Herbstkonzert mit Ausschank', array( $item( 'fass', 2 ), $item( 'obstler', 30, 0, 'Pausen-Schnapsei für die Bläser' ), $item( 'zapf' ), $item( 'glas' ), $item( 'liefer' ) ), '2026-10-28' );
$offer( $c['golf'], '2026-10-01', 'Weihnachtsfeier Clubhaus', array( $item( 'seitei', 120 ), $item( 'marille', 60, 10, 'Glühwein? Nein – Schnapsei!' ), $item( 'liefer' ) ), '2026-11-15' );
$d3 = $offer( $c['kegel'], '2026-05-10', 'Kegelreise Wachau', array( $item( 'marille', 200, 15, 'Verkostung direkt beim Brenner' ) ), '2026-06-10' );
nw_offer_state( $d3['id'], 'declined' );
nw_invoice_save( array( 'kind' => 'offer', 'customer_id' => $c['ffw']['id'], 'invoice_date' => '2026-10-07', 'subject' => 'Florianifeier 2027', 'items' => array( $item( 'fass', 4 ), $item( 'zapf' ), $item( 'liefer' ) ) ) );

// Dauerrechnungen
foreach (
	array(
		array( 'hirsch', 'Stammtisch', '2026-10-31', 'send', array( $item( 'stamm', 1, 0, "Stammtisch {MONAT}\nReservierter Ecktisch, 1 Seitei pro Kopf und Woche" ) ), 1 ),
		array( 'ffw', 'Fassbier-Abo', '2026-12-15', 'send', array( $item( 'fassabo', 1, 0, 'Quartal ab {MONAT}' ) ), 3 ),
		array( 'kegel', 'Kegelabend', '2026-10-24', 'issue', array( $item( 'seitei', 40, 0, 'Kegelabend {MONAT} (Richtwert, wird angepasst)' ), $item( 'obstler', 12 ) ), 1 ),
		array( 'golf', 'Clubhaus-Kühlschrank', '2027-04-01', 'draft', array( $item( 'seitei', 300, 10, 'Saisonvorrat {JAHR}' ) ), 12 ),
	) as $r
) {
	nw_recurring_save( array( 'customer_id' => $c[ $r[0] ]['id'], 'title' => $r[1], 'next_date' => $r[2], 'mode' => $r[3], 'items' => $r[4], 'interval_months' => $r[5], 'active' => 1 ) );
}

// Stunden (Schankdienst, Lieferungen, Beratung)
nw_set_setting( 'hour_rate', '38' );
nw_set_setting( 'hour_name', 'Schankdienst & Service' );
foreach (
	array(
		array( 'golf', '2026-09-29', 4.5, 'Saisonabschluss', 'Ausschank Clubhaus-Terrasse, Fässer gewechselt' ),
		array( 'golf', '2026-10-02', 2, 'Saisonabschluss', 'Weinverkostung vorbereitet, Gläser poliert' ),
		array( 'golf', '2026-10-05', 1.25, 'Weihnachtsfeier', 'Vorbesprechung mit Dr. Lochner, Menge kalkuliert' ),
		array( 'ffw', '2026-10-03', 6, 'Feuerwehrfest', 'Zapfanlage aufgebaut und betreut' ),
		array( 'ffw', '2026-10-04', 3.5, 'Feuerwehrfest', 'Abbau, Leergut sortiert' ),
		array( 'musik', '2026-10-06', 1.5, 'Herbstkonzert', 'Lagerplatz besichtigt, Kühlung geplant' ),
	) as $h
) {
	nw_time_save( array( 'customer_id' => $c[ $h[0] ]['id'], 'date' => $h[1], 'hours' => $h[2], 'project' => $h[3], 'note' => $h[4] ) );
}
$billedT = nw_time_save( array( 'customer_id' => $c['hirsch']['id'], 'date' => '2026-09-12', 'hours' => 2, 'project' => 'Stammtisch', 'note' => 'Geburtstagsrunde betreut' ) );
nw_time_mark( array( $billedT['id'] ), true );

// Ausgaben
foreach (
	array(
		array( '2026-01-08', 'Brauerei Hopfenstark', '40 Fässer Märzen', 'Wareneinkauf', 4280 ),
		array( '2026-02-02', 'Obstbrennerei Tropfenweis', 'Obstler & Marille, 120 Flaschen', 'Wareneinkauf', 1950 ),
		array( '2026-03-12', 'Gläserwäscherei Blitzblank', 'Reinigung Leihgläser Q1', 'Dienstleistung', 180 ),
		array( '2026-06-30', 'Zapftechnik Kühl & Frisch', 'Service Zapfanlagen', 'Wartung', 245 ),
	) as $e
) {
	nw_expense_save( array( 'date' => $e[0], 'vendor' => $e[1], 'description' => $e[2], 'category' => $e[3], 'amount' => $e[4] ) );
}
q( "DELETE FROM activity WHERE text LIKE 'Kunde angelegt%' OR text LIKE 'Dauerrechnung angelegt%'" );
echo "Demo „jwm3000 GmbH“ angelegt – Passwort demo1234\n";
