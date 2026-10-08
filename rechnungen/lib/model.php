<?php
/**
 * Fachlogik: Kunden, Artikel, Rechnungen, Dauerrechnungen, Ausgaben, Übersicht.
 */
defined( 'NW_APP' ) || exit;

class NW_Error extends Exception {}

function nw_fail( $msg ) {
	throw new NW_Error( $msg );
}

/* ==================================================================== Kunden */

function nw_customer_name( array $c ) {
	$n = trim( (string) ( $c['company'] ?? '' ) );
	return '' !== $n ? $n : trim( (string) ( $c['person'] ?? '' ) );
}

/** Adresszeilen für das Anschriftfeld. */
function nw_customer_lines( array $c ) {
	$sal   = array( 'herr' => 'Herrn', 'frau' => 'Frau', 'familie' => 'Familie' );
	$lines = array();
	foreach ( array( 'company', 'company2' ) as $k ) {
		if ( '' !== trim( (string) $c[ $k ] ) ) {
			$lines[] = trim( $c[ $k ] );
		}
	}
	if ( '' !== trim( (string) $c['person'] ) ) {
		$lines[] = trim( ( $sal[ $c['salutation'] ] ?? '' ) . ' ' . trim( $c['person'] ) );
	}
	if ( '' !== trim( (string) $c['street'] ) ) {
		$lines[] = trim( $c['street'] );
	}
	$city = trim( $c['zip'] . ' ' . $c['city'] );
	if ( '' !== $city ) {
		$lines[] = $city;
	}
	$country = trim( (string) $c['country'] );
	if ( '' !== $country && ! in_array( mb_strtolower( $country ), array( 'österreich', 'austria', 'at' ), true ) ) {
		$lines[] = mb_strtoupper( $country );
	}
	if ( '' !== trim( (string) $c['vat_id'] ) ) {
		$lines[] = 'UID: ' . trim( $c['vat_id'] );
	}
	return $lines;
}

/** „Sehr geehrter Herr Holzmann,“ – Nachname = letztes Wort, nur bei genau einer Person. */
function nw_customer_greeting( array $c ) {
	$p = trim( (string) $c['person'] );
	if ( '' !== $p && false === strpos( $p, ',' ) && false === stripos( $p, ' und ' ) ) {
		$last = preg_replace( '/^.*\s/', '', $p );
		if ( 'herr' === $c['salutation'] ) {
			return 'Sehr geehrter Herr ' . $last . ',';
		}
		if ( 'frau' === $c['salutation'] ) {
			return 'Sehr geehrte Frau ' . $last . ',';
		}
	}
	return nw_setting( 'greeting' );
}

function nw_recipient( array $c ) {
	return array(
		'name'   => nw_customer_name( $c ),
		'number' => (string) $c['number'],
		'lines'  => nw_customer_lines( $c ),
		'email'  => (string) $c['email'],
	);
}

function nw_customer_get( $id ) {
	$c = q_row( 'SELECT * FROM customers WHERE id = ?', array( (int) $id ) );
	if ( ! $c ) {
		nw_fail( 'Kunde nicht gefunden.' );
	}
	$c['name']  = nw_customer_name( $c );
	$c['lines'] = nw_customer_lines( $c );
	return $c;
}

function nw_customers_list() {
	$rows = q_all(
		"SELECT c.*,
			(SELECT COUNT(*) FROM invoices i WHERE i.customer_id = c.id AND i.status != 'draft' AND i.kind = 'invoice') AS invoice_count,
			(SELECT COALESCE(SUM(gross),0) FROM invoices i WHERE i.customer_id = c.id AND i.status != 'draft' AND i.kind != 'offer') AS revenue,
			(SELECT COALESCE(SUM(gross - COALESCE(paid_amount,0)),0) FROM invoices i WHERE i.customer_id = c.id AND i.status = 'issued' AND i.kind = 'invoice' AND i.paid_at IS NULL) AS open_amount,
			(SELECT MAX(invoice_date) FROM invoices i WHERE i.customer_id = c.id AND i.status != 'draft' AND i.kind != 'offer') AS last_invoice,
			(SELECT COUNT(*) FROM invoices i WHERE i.customer_id = c.id AND i.kind = 'offer' AND i.status = 'issued' AND COALESCE(i.offer_state,'open') = 'open') AS offer_count,
			(SELECT COUNT(*) FROM recurring r WHERE r.customer_id = c.id AND r.active = 1) AS recurring_count
		FROM customers c ORDER BY c.archived, COALESCE(NULLIF(c.company,''), c.person) COLLATE NOCASE"
	);
	foreach ( $rows as &$r ) {
		$r['name']  = nw_customer_name( $r );
		$r['lines'] = nw_customer_lines( $r );
	}
	return $rows;
}

function nw_customer_save( array $d ) {
	$id     = (int) ( $d['id'] ?? 0 );
	$fields = array( 'company', 'company2', 'salutation', 'person', 'street', 'zip', 'city', 'country', 'vat_id', 'email', 'email_cc', 'phone', 'website', 'note' );
	$row    = array();
	foreach ( $fields as $f ) {
		$row[ $f ] = trim( (string) ( $d[ $f ] ?? '' ) );
	}
	if ( '' === $row['company'] && '' === $row['person'] ) {
		nw_fail( 'Bitte Firma oder Name angeben.' );
	}
	foreach ( array( 'email', 'email_cc' ) as $f ) {
		foreach ( preg_split( '/[,;\s]+/', $row[ $f ], -1, PREG_SPLIT_NO_EMPTY ) as $e ) {
			if ( ! nw_is_email( $e ) ) {
				nw_fail( 'Ungültige E-Mail-Adresse: ' . $e );
			}
		}
	}
	$row['payment_days'] = isset( $d['payment_days'] ) && '' !== (string) $d['payment_days'] ? max( 0, (int) $d['payment_days'] ) : null;
	$row['hour_rate']    = isset( $d['hour_rate'] ) && '' !== trim( (string) $d['hour_rate'] ) ? max( 0, round( (float) str_replace( ',', '.', (string) $d['hour_rate'] ), 2 ) ) : null;
	$row['archived']     = empty( $d['archived'] ) ? 0 : 1;
	$row['updated_at']   = nw_now();
	$number              = trim( (string) ( $d['number'] ?? '' ) );
	if ( '' !== $number && q_val( 'SELECT id FROM customers WHERE number = ? AND id != ?', array( $number, $id ) ) ) {
		nw_fail( 'Kundennummer ' . $number . ' ist schon vergeben.' );
	}
	if ( $id ) {
		if ( '' !== $number ) {
			$row['number'] = $number;
		}
		nw_update( 'customers', $id, $row );
	} else {
		$row['number']     = '' !== $number ? $number : nw_next_customer_number();
		$row['created_at'] = nw_now();
		$id                = nw_insert( 'customers', $row );
		nw_log( 'Kunde angelegt: ' . nw_customer_name( $row ), null, $id );
	}
	return nw_customer_get( $id );
}

function nw_next_customer_number() {
	$max = (int) q_val( "SELECT MAX(CAST(number AS INTEGER)) FROM customers WHERE number GLOB '[0-9]*'" );
	return (string) max( (int) nw_setting( 'next_customer' ), $max + 1 );
}

function nw_customer_delete( $id ) {
	$n = (int) q_val( 'SELECT COUNT(*) FROM invoices WHERE customer_id = ?', array( (int) $id ) );
	if ( $n > 0 ) {
		q( 'UPDATE customers SET archived = 1 WHERE id = ?', array( (int) $id ) );
		q( 'UPDATE recurring SET active = 0 WHERE customer_id = ?', array( (int) $id ) );
		return 'archived';
	}
	q( 'DELETE FROM recurring WHERE customer_id = ?', array( (int) $id ) );
	q( 'DELETE FROM customers WHERE id = ?', array( (int) $id ) );
	return 'deleted';
}

/* ==================================================================== Artikel */

function nw_products_list() {
	return q_all(
		"SELECT p.*, (SELECT COUNT(*) FROM invoice_items it JOIN invoices i ON i.id = it.invoice_id WHERE it.sku = p.sku AND p.sku != '' AND i.status != 'draft' AND i.kind != 'offer') AS used,
			(SELECT MAX(i.invoice_date) FROM invoice_items it JOIN invoices i ON i.id = it.invoice_id WHERE it.sku = p.sku AND p.sku != '' AND i.status != 'draft' AND i.kind != 'offer') AS last_used
		FROM products p ORDER BY p.archived, p.category, p.name COLLATE NOCASE"
	);
}

function nw_product_save( array $d ) {
	$id  = (int) ( $d['id'] ?? 0 );
	$row = array(
		'sku'         => trim( (string) ( $d['sku'] ?? '' ) ),
		'name'        => trim( (string) ( $d['name'] ?? '' ) ),
		'description' => trim( (string) ( $d['description'] ?? '' ) ),
		'unit'        => trim( (string) ( $d['unit'] ?? '' ) ),
		'price'       => round( (float) ( $d['price'] ?? 0 ), 4 ),
		'tax_rate'    => (float) ( $d['tax_rate'] ?? nw_setting( 'default_tax' ) ),
		'category'    => trim( (string) ( $d['category'] ?? '' ) ),
		'recurring'   => empty( $d['recurring'] ) ? 0 : 1,
		'archived'    => empty( $d['archived'] ) ? 0 : 1,
		'updated_at'  => nw_now(),
	);
	if ( '' === $row['name'] ) {
		nw_fail( 'Bitte eine Bezeichnung angeben.' );
	}
	if ( '' === $row['sku'] ) {
		$max        = (int) q_val( "SELECT MAX(CAST(sku AS INTEGER)) FROM products WHERE sku GLOB '[0-9]*'" );
		$used       = (int) q_val( "SELECT MAX(CAST(sku AS INTEGER)) FROM invoice_items WHERE sku GLOB '[0-9]*'" );
		$row['sku'] = (string) max( (int) nw_setting( 'next_sku' ), $max + 1, $used + 1 );
	} elseif ( q_val( 'SELECT id FROM products WHERE sku = ? AND id != ?', array( $row['sku'], $id ) ) ) {
		nw_fail( 'Artikelnummer ' . $row['sku'] . ' ist schon vergeben.' );
	}
	if ( $id ) {
		nw_update( 'products', $id, $row );
	} else {
		$row['created_at'] = nw_now();
		$id                = nw_insert( 'products', $row );
	}
	return q_row( 'SELECT * FROM products WHERE id = ?', array( $id ) );
}

function nw_product_delete( $id ) {
	$p    = q_row( 'SELECT * FROM products WHERE id = ?', array( (int) $id ) );
	$used = $p ? (int) q_val( 'SELECT COUNT(*) FROM invoice_items WHERE product_id = ? OR (sku = ? AND sku != \'\')', array( (int) $id, $p['sku'] ) ) : 0;
	if ( $used ) {
		q( 'UPDATE products SET archived = 1 WHERE id = ?', array( (int) $id ) );
		return 'archived';
	}
	q( 'DELETE FROM products WHERE id = ?', array( (int) $id ) );
	return 'deleted';
}

/* ==================================================================== Rechnungen: Berechnung */

function nw_small_business() {
	return '1' === (string) nw_setting( 'small_business' );
}

function nw_items_clean( array $items, $small ) {
	$out = array();
	$pos = 0;
	foreach ( $items as $it ) {
		$name = trim( (string) ( $it['name'] ?? '' ) );
		$desc = rtrim( (string) ( $it['description'] ?? '' ) );
		if ( '' === $name && '' === trim( $desc ) ) {
			continue;
		}
		$qty      = round( (float) str_replace( ',', '.', (string) ( $it['qty'] ?? 1 ) ), 4 );
		$price    = round( (float) str_replace( ',', '.', (string) ( $it['price'] ?? 0 ) ), 4 );
		$discount = min( 100, max( 0, round( (float) str_replace( ',', '.', (string) ( $it['discount'] ?? 0 ) ), 4 ) ) );
		$out[]    = array(
			'pos'         => ++$pos,
			'product_id'  => ! empty( $it['product_id'] ) ? (int) $it['product_id'] : null,
			'sku'         => trim( (string) ( $it['sku'] ?? '' ) ),
			'name'        => $name,
			'description' => $desc,
			'qty'         => $qty,
			'unit'        => trim( (string) ( $it['unit'] ?? '' ) ),
			'price'       => $price,
			'discount'    => $discount,
			'tax_rate'    => $small ? 0.0 : (float) ( $it['tax_rate'] ?? nw_setting( 'default_tax' ) ),
			'net'         => round( $qty * $price * ( 1 - $discount / 100 ), 2 ),
		);
	}
	return $out;
}

function nw_totals( array $items ) {
	$net    = 0.0;
	$groups = array();
	foreach ( $items as $it ) {
		$net += $it['net'];
		$k    = (string) $it['tax_rate'];
		if ( ! isset( $groups[ $k ] ) ) {
			$groups[ $k ] = array( 'rate' => (float) $it['tax_rate'], 'net' => 0.0, 'tax' => 0.0 );
		}
		$groups[ $k ]['net'] += $it['net'];
	}
	$tax   = 0.0;
	$taxes = array();
	foreach ( $groups as $g ) {
		if ( $g['rate'] > 0 ) {
			$g['tax'] = round( $g['net'] * $g['rate'] / 100, 2 );
			$tax     += $g['tax'];
			$taxes[]  = $g;
		}
	}
	$net = round( $net, 2 );
	return array( 'net' => $net, 'tax' => round( $tax, 2 ), 'gross' => round( $net + $tax, 2 ), 'taxes' => $taxes );
}

/* ==================================================================== Rechnungen: Lesen */

/**
 * Zustand: draft · open · overdue · partial · paid · cancelled (storniert) · storno (Stornorechnung).
 */
function nw_invoice_state( array $r ) {
	if ( 'draft' === $r['status'] ) {
		return 'draft';
	}
	if ( 'offer' === $r['kind'] ) {
		$st = $r['offer_state'] ?? 'open';
		if ( 'accepted' === $st || 'declined' === $st ) {
			return $st;
		}
		return ! empty( $r['valid_until'] ) && $r['valid_until'] < nw_today() ? 'expired' : 'sent';
	}
	if ( 'storno' === $r['kind'] ) {
		return 'storno';
	}
	if ( 'cancelled' === $r['status'] ) {
		return 'cancelled';
	}
	if ( $r['paid_at'] ) {
		return 'paid';
	}
	if ( (float) ( $r['paid_amount'] ?? 0 ) > 0 ) {
		return 'partial'; // teilweise bezahlt
	}
	if ( $r['due_date'] && $r['due_date'] < nw_today() ) {
		return 'overdue';
	}
	return 'open';
}

/**
 * Wiederkehrende Rechnungen: aus einer Dauerrechnung erzeugt oder mit einem wiederkehrenden Artikel
 * (Hosting, Lizenzen, Betreuung …). Einmal pro Anfrage geladen; $fresh nach Änderungen.
 */
function nw_recurring_invoice_ids( $fresh = false ) {
	static $ids = null;
	if ( null === $ids || $fresh ) {
		$ids = array_flip(
			array_map(
				'intval',
				array_column(
					q_all(
						"SELECT DISTINCT it.invoice_id AS id FROM invoice_items it JOIN products p ON p.sku = it.sku AND it.sku != '' WHERE p.recurring = 1
						UNION SELECT id FROM invoices WHERE recurring_id IS NOT NULL"
					),
					'id'
				)
			)
		);
	}
	return $ids;
}

function nw_invoice_row( array $r ) {
	$r['recipient'] = json_decode( (string) $r['recipient'], true ) ?: array( 'name' => '', 'lines' => array(), 'number' => '', 'email' => '' );
	foreach ( array( 'net', 'tax', 'gross' ) as $k ) {
		$r[ $k ] = (float) $r[ $k ];
	}
	$r['paid_amount'] = null === $r['paid_amount'] ? null : (float) $r['paid_amount'];
	$r['state']       = nw_invoice_state( $r );
	$r['is_recurring'] = isset( nw_recurring_invoice_ids()[ (int) $r['id'] ] );
	$r['paid']        = (float) ( $r['paid_amount'] ?? 0 );
	$r['open']        = in_array( $r['state'], array( 'open', 'overdue', 'partial' ), true ) ? round( $r['gross'] - $r['paid'], 2 ) : 0.0;
	$late             = in_array( $r['state'], array( 'overdue', 'partial' ), true ) && $r['due_date'] && $r['due_date'] < nw_today();
	$r['days_overdue'] = $late ? (int) floor( ( strtotime( nw_today() ) - strtotime( $r['due_date'] ) ) / 86400 ) : 0;
	return $r;
}

function nw_invoice_get( $id ) {
	$r = q_row( 'SELECT * FROM invoices WHERE id = ?', array( (int) $id ) );
	if ( ! $r ) {
		nw_fail( 'Rechnung nicht gefunden.' );
	}
	nw_recurring_invoice_ids( true );
	$r          = nw_invoice_row( $r );
	$r['items'] = q_all( 'SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY pos', array( $r['id'] ) );
	foreach ( $r['items'] as &$it ) {
		foreach ( array( 'qty', 'price', 'discount', 'tax_rate', 'net' ) as $k ) {
			$it[ $k ] = (float) $it[ $k ];
		}
	}
	unset( $it );
	$t          = nw_totals( $r['items'] );
	$r['taxes'] = $t['taxes'];
	$r['ref']   = $r['ref_id'] ? q_row( 'SELECT id, number, invoice_date FROM invoices WHERE id = ?', array( $r['ref_id'] ) ) : null;
	$r['storno'] = q_row( "SELECT id, number, invoice_date FROM invoices WHERE ref_id = ? AND kind = 'storno'", array( $r['id'] ) );
	$r['converted'] = ! empty( $r['converted_id'] ) ? q_row( 'SELECT id, number, status FROM invoices WHERE id = ?', array( $r['converted_id'] ) ) : null;
	$r['from_offer'] = q_row( "SELECT id, number FROM invoices WHERE kind = 'offer' AND converted_id = ?", array( $r['id'] ) );
	$r['customer'] = $r['customer_id'] ? q_row( 'SELECT id, number, company, person, email, email_cc FROM customers WHERE id = ?', array( $r['customer_id'] ) ) : null;
	$r['time_ids'] = array_map( 'intval', array_column( q_all( 'SELECT id FROM time_entries WHERE invoice_id = ?', array( $r['id'] ) ), 'id' ) );
	$r['payments'] = q_all( 'SELECT id, date, amount, note FROM payments WHERE invoice_id = ? ORDER BY date, id', array( $r['id'] ) );
	$r['mails']    = q_all( 'SELECT kind, to_addr, subject, ok, error, created_at FROM mail_log WHERE invoice_id = ? ORDER BY id DESC', array( $r['id'] ) );
	$r['activity'] = q_all( 'SELECT text, created_at FROM activity WHERE invoice_id = ? ORDER BY id DESC LIMIT 30', array( $r['id'] ) );
	return $r;
}

function nw_invoices_list( array $f = array() ) {
	$where = array( '1=1' );
	$args  = array();
	if ( ! empty( $f['year'] ) ) {
		$where[] = "substr(i.invoice_date,1,4) = ?";
		$args[]  = (string) $f['year'];
	}
	$where[] = ! empty( $f['offers'] ) ? "i.kind = 'offer'" : "i.kind != 'offer'";
	if ( ! empty( $f['customer_id'] ) ) {
		$where[] = 'i.customer_id = ?';
		$args[]  = (int) $f['customer_id'];
	}
	$rows = q_all(
		'SELECT i.*, (SELECT COUNT(*) FROM invoice_items it WHERE it.invoice_id = i.id) AS item_count,
			(SELECT GROUP_CONCAT(name, " · ") FROM (SELECT name FROM invoice_items it WHERE it.invoice_id = i.id ORDER BY pos LIMIT 3)) AS item_names
		FROM invoices i WHERE ' . implode( ' AND ', $where ) . "
		ORDER BY CASE WHEN i.status = 'draft' THEN 0 ELSE 1 END, i.invoice_date DESC, CAST(i.number AS INTEGER) DESC, i.id DESC",
		$args
	);
	return array_map( 'nw_invoice_row', $rows );
}

/* ==================================================================== Rechnungen: Schreiben */

function nw_next_offer_number() {
	$max = (int) q_val( "SELECT MAX(CAST(substr(number,3) AS INTEGER)) FROM invoices WHERE kind = 'offer' AND number LIKE 'A-%'" );
	return 'A-' . max( (int) nw_setting( 'next_offer' ), $max + 1 );
}

function nw_next_number() {
	$max = (int) q_val( "SELECT MAX(CAST(number AS INTEGER)) FROM invoices WHERE number GLOB '[0-9]*'" );
	return (string) max( (int) nw_setting( 'next_number' ), $max + 1 );
}

/**
 * Rechnungsdaten aus Formulardaten bilden (ohne zu speichern) – für Speichern und Live-Vorschau.
 *
 * @return array{0:array, 1:array}  Zeile für invoices, Positionen
 */
function nw_invoice_build( array $d ) {
	$offer    = 'offer' === ( $d['kind'] ?? '' );
	$customer = ! empty( $d['customer_id'] ) ? nw_customer_get( (int) $d['customer_id'] ) : null;
	$small    = nw_small_business();
	$items    = nw_items_clean( (array) ( $d['items'] ?? array() ), $small );
	$sum      = nw_totals( $items );

	$recipient = isset( $d['recipient'] ) && is_array( $d['recipient'] ) ? $d['recipient'] : array();
	if ( $customer && ( empty( $recipient['lines'] ) || ! empty( $d['refresh_recipient'] ) ) ) {
		$recipient = nw_recipient( $customer );
	}
	$recipient = array(
		'name'   => trim( (string) ( $recipient['name'] ?? ( $customer ? nw_customer_name( $customer ) : '' ) ) ),
		'number' => trim( (string) ( $recipient['number'] ?? ( $customer['number'] ?? '' ) ) ),
		'lines'  => array_values( array_filter( array_map( 'trim', (array) ( $recipient['lines'] ?? array() ) ), 'strlen' ) ),
		'email'  => trim( (string) ( $recipient['email'] ?? ( $customer['email'] ?? '' ) ) ),
	);

	$date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $d['invoice_date'] ?? '' ) ) ? $d['invoice_date'] : nw_today();
	$days = isset( $d['payment_days'] ) && '' !== (string) $d['payment_days'] ? max( 0, (int) $d['payment_days'] ) : (int) ( $customer['payment_days'] ?? nw_setting( 'payment_days' ) );
	$valid = function ( $v ) {
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $v ) ? $v : null;
	};
	$row = array(
		'customer_id'    => $customer ? (int) $customer['id'] : null,
		'recipient'      => json_encode( $recipient, JSON_UNESCAPED_UNICODE ),
		'invoice_date'   => $date,
		'service_date'   => $valid( $d['service_date'] ?? null ),
		'period_from'    => $valid( $d['period_from'] ?? null ),
		'period_to'      => $valid( $d['period_to'] ?? null ),
		'payment_days'   => $days,
		'due_date'       => nw_add_days( $date, $days ),
		'subject'        => trim( (string) ( $d['subject'] ?? '' ) ),
		'greeting'       => '' !== trim( (string) ( $d['greeting'] ?? '' ) ) ? trim( $d['greeting'] ) : ( $customer ? nw_customer_greeting( $customer ) : nw_setting( 'greeting' ) ),
		'intro'          => (string) ( $d['intro'] ?? nw_setting( $offer ? 'offer_intro' : 'intro' ) ),
		'outro'          => (string) ( $d['outro'] ?? nw_setting( $offer ? 'offer_outro' : 'outro' ) ),
		'valid_until'    => $offer ? ( $valid( $d['valid_until'] ?? null ) ?: nw_add_days( $date, (int) nw_setting( 'offer_days' ) ) ) : null,
		'net'            => $sum['net'],
		'tax'            => $sum['tax'],
		'gross'          => $sum['gross'],
		'small_business' => $small ? 1 : 0,
		'note'           => (string) ( $d['note'] ?? '' ),
		'compact'        => in_array( $d['compact'] ?? '', array( 'auto', 'on', 'off' ), true ) ? $d['compact'] : 'auto',
		'updated_at'     => nw_now(),
	);
	if ( $row['period_from'] && $row['period_to'] && $row['period_to'] < $row['period_from'] ) {
		nw_fail( 'Der Leistungszeitraum endet vor seinem Beginn.' );
	}
	return array( $row, $items );
}

/** Live-Vorschau eines Entwurfs als Rechnungsarray (nicht gespeichert). */
function nw_invoice_preview( array $d ) {
	list( $row, $items ) = nw_invoice_build( $d );
	$row = array_merge(
		$row,
		array( 'id' => (int) ( $d['id'] ?? 0 ), 'number' => null, 'kind' => 'offer' === ( $d['kind'] ?? '' ) ? 'offer' : 'invoice', 'status' => 'draft', 'paid_at' => null, 'paid_amount' => null, 'ref_id' => null, 'offer_state' => null, 'converted_id' => null )
	);
	$inv          = nw_invoice_row( $row );
	$inv['items'] = $items;
	$inv['taxes'] = nw_totals( $items )['taxes'];
	$inv['ref']   = null;
	$inv['storno'] = null;
	$inv['converted'] = null;
	return $inv;
}

/**
 * Entwurf anlegen oder ändern. Ausgestellte Rechnungen sind gesperrt – nur interne Notiz und E-Mail.
 */
function nw_invoice_save( array $d ) {
	$id  = (int) ( $d['id'] ?? 0 );
	$old = $id ? q_row( 'SELECT * FROM invoices WHERE id = ?', array( $id ) ) : null;
	if ( $id && ! $old ) {
		nw_fail( 'Rechnung nicht gefunden.' );
	}
	if ( $old && 'draft' !== $old['status'] ) {
		$rec          = json_decode( $old['recipient'], true );
		$rec['email'] = trim( (string) ( $d['recipient']['email'] ?? $rec['email'] ?? '' ) );
		nw_update( 'invoices', $id, array( 'note' => (string) ( $d['note'] ?? $old['note'] ), 'recipient' => json_encode( $rec, JSON_UNESCAPED_UNICODE ), 'updated_at' => nw_now() ) );
		return nw_invoice_get( $id );
	}
	$d['kind'] = $old ? $old['kind'] : ( 'offer' === ( $d['kind'] ?? '' ) ? 'offer' : 'invoice' );
	list( $row, $items ) = nw_invoice_build( $d );
	db()->beginTransaction();
	try {
		if ( $id ) {
			nw_update( 'invoices', $id, $row );
			q( 'DELETE FROM invoice_items WHERE invoice_id = ?', array( $id ) );
		} else {
			$row['status']       = 'draft';
			$row['kind']         = $d['kind'];
			$row['recurring_id'] = ! empty( $d['recurring_id'] ) ? (int) $d['recurring_id'] : null;
			$row['created_at']   = nw_now();
			$id                  = nw_insert( 'invoices', $row );
			nw_log( 'Entwurf angelegt', $id, $row['customer_id'] );
		}
		foreach ( $items as $it ) {
			$it['invoice_id'] = $id;
			nw_insert( 'invoice_items', $it );
		}
		if ( isset( $d['time_ids'] ) && is_array( $d['time_ids'] ) ) {
			nw_time_link( $id, $row['customer_id'], $d['time_ids'] );
		}
		db()->commit();
	} catch ( Throwable $e ) {
		db()->rollBack();
		throw $e;
	}
	return nw_invoice_get( $id );
}

/** Ausstellen: fortlaufende Nummer vergeben, ab dann unveränderlich. */
function nw_invoice_issue( $id ) {
	$inv = nw_invoice_get( $id );
	if ( 'draft' !== $inv['status'] ) {
		nw_fail( 'Die Rechnung ist bereits ausgestellt.' );
	}
	if ( ! $inv['items'] ) {
		nw_fail( 'Die Rechnung hat keine Positionen.' );
	}
	if ( ! $inv['recipient']['lines'] ) {
		nw_fail( 'Bitte einen Empfänger angeben.' );
	}
	$offer = 'offer' === $inv['kind'];
	db()->beginTransaction();
	try {
		$number = $offer ? nw_next_offer_number() : nw_next_number();
		nw_update(
			'invoices',
			$id,
			array(
				'number'      => $number,
				'status'      => 'issued',
				'issued_at'   => nw_now(),
				'due_date'    => nw_add_days( $inv['invoice_date'], (int) $inv['payment_days'] ),
				'offer_state' => $offer ? 'open' : null,
				'token'       => bin2hex( random_bytes( 12 ) ),
			)
		);
		if ( $offer ) {
			nw_set_setting( 'next_offer', (string) ( (int) substr( $number, 2 ) + 1 ) );
		} else {
			nw_set_setting( 'next_number', (string) ( (int) $number + 1 ) );
		}
		db()->commit();
	} catch ( Throwable $e ) {
		db()->rollBack();
		throw $e;
	}
	if ( ! $offer ) {
		$n = q( 'UPDATE time_entries SET billed = 1, billed_at = ? WHERE invoice_id = ? AND billed = 0', array( nw_now(), $id ) )->rowCount();
		if ( $n ) {
			nw_log( $n . ' Stundeneinträge abgerechnet', $id, $inv['customer_id'] );
		}
	}
	nw_log( ( $offer ? 'Angebot ' : 'Rechnung ' ) . $number . ' ausgestellt', $id, $inv['customer_id'] );
	return nw_invoice_get( $id );
}

/** Zahlungseingang erfassen – voller Restbetrag (Standard) oder Teilzahlung. */
function nw_invoice_pay( $id, $date = null, $amount = null, $note = '' ) {
	$inv = nw_invoice_get( $id );
	if ( 'issued' !== $inv['status'] || 'invoice' !== $inv['kind'] ) {
		nw_fail( 'Nur ausgestellte Rechnungen können als bezahlt markiert werden.' );
	}
	if ( 'paid' === $inv['state'] ) {
		nw_fail( 'Die Rechnung ist bereits vollständig bezahlt.' );
	}
	$date   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ? $date : nw_today();
	$amount = null === $amount || '' === $amount ? $inv['open'] : round( (float) $amount, 2 );
	if ( $amount <= 0 ) {
		nw_fail( 'Bitte einen Betrag größer als 0 angeben.' );
	}
	nw_insert( 'payments', array( 'invoice_id' => $inv['id'], 'date' => $date, 'amount' => $amount, 'note' => (string) $note, 'created_at' => nw_now() ) );
	$after = nw_payments_sync( $inv['id'] );
	nw_log(
		$after['paid_at']
			? 'Zahlung ' . nw_money( $amount ) . ' am ' . nw_date( $date ) . ' – vollständig bezahlt'
			: 'Teilzahlung ' . nw_money( $amount ) . ' am ' . nw_date( $date ) . ' – offen ' . nw_money( $inv['gross'] - $after['sum'] ),
		$inv['id'],
		$inv['customer_id']
	);
	return nw_invoice_get( $id );
}

/** Summe der Zahlungen auf die Rechnung schreiben: vollständig → paid_at = letzte Zahlung. */
function nw_payments_sync( $id ) {
	$inv  = q_row( 'SELECT gross FROM invoices WHERE id = ?', array( (int) $id ) );
	$sum  = round( (float) q_val( 'SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ?', array( (int) $id ) ), 2 );
	$last = q_val( 'SELECT MAX(date) FROM payments WHERE invoice_id = ?', array( (int) $id ) );
	$full = $sum > 0 && $sum >= round( (float) $inv['gross'], 2 ) - 0.005;
	nw_update( 'invoices', (int) $id, array( 'paid_at' => $full ? $last : null, 'paid_amount' => $sum > 0 ? $sum : null, 'updated_at' => nw_now() ) );
	return array( 'sum' => $sum, 'paid_at' => $full ? $last : null );
}

/** Eine Zahlung löschen (Standard: die letzte). */
function nw_invoice_unpay( $id, $payment_id = null ) {
	$inv = nw_invoice_get( $id );
	$pid = $payment_id ? (int) $payment_id : (int) q_val( 'SELECT id FROM payments WHERE invoice_id = ? ORDER BY date DESC, id DESC LIMIT 1', array( $inv['id'] ) );
	$p   = q_row( 'SELECT * FROM payments WHERE id = ? AND invoice_id = ?', array( $pid, $inv['id'] ) );
	if ( $p ) {
		q( 'DELETE FROM payments WHERE id = ?', array( $pid ) );
		nw_log( 'Zahlung ' . nw_money( $p['amount'] ) . ' vom ' . nw_date( $p['date'] ) . ' zurückgenommen', $inv['id'], $inv['customer_id'] );
	} else {
		nw_update( 'invoices', $inv['id'], array( 'paid_at' => null, 'paid_amount' => null ) );
	}
	nw_payments_sync( $inv['id'] );
	return nw_invoice_get( $id );
}

/** Stornieren: Stornorechnung mit negativen Beträgen und eigener Nummer. */
function nw_invoice_cancel( $id ) {
	$inv = nw_invoice_get( $id );
	if ( 'issued' !== $inv['status'] || 'invoice' !== $inv['kind'] ) {
		nw_fail( 'Nur ausgestellte Rechnungen können storniert werden.' );
	}
	db()->beginTransaction();
	try {
		$number = nw_next_number();
		$sid    = nw_insert(
			'invoices',
			array(
				'number'         => $number,
				'kind'           => 'storno',
				'ref_id'         => $inv['id'],
				'customer_id'    => $inv['customer_id'],
				'recipient'      => json_encode( $inv['recipient'], JSON_UNESCAPED_UNICODE ),
				'status'         => 'issued',
				'invoice_date'   => nw_today(),
				'service_date'   => $inv['service_date'],
				'period_from'    => $inv['period_from'],
				'period_to'      => $inv['period_to'],
				'payment_days'   => 0,
				'due_date'       => nw_today(),
				'greeting'       => $inv['greeting'],
				'intro'          => 'hiermit stornieren wir die Rechnung ' . $inv['number'] . ' vom ' . nw_date( $inv['invoice_date'] ) . '.',
				'outro'          => '',
				'net'            => -$inv['net'],
				'tax'            => -$inv['tax'],
				'gross'          => -$inv['gross'],
				'small_business' => $inv['small_business'],
				'token'          => bin2hex( random_bytes( 12 ) ),
				'created_at'     => nw_now(),
				'updated_at'     => nw_now(),
				'issued_at'      => nw_now(),
			)
		);
		foreach ( $inv['items'] as $it ) {
			unset( $it['id'] );
			$it['invoice_id'] = $sid;
			$it['qty']        = -$it['qty'];
			$it['net']        = -$it['net'];
			nw_insert( 'invoice_items', $it );
		}
		nw_update( 'invoices', $inv['id'], array( 'status' => 'cancelled', 'updated_at' => nw_now() ) );
		nw_set_setting( 'next_number', (string) ( (int) $number + 1 ) );
		db()->commit();
	} catch ( Throwable $e ) {
		db()->rollBack();
		throw $e;
	}
	nw_log( 'Storniert mit Stornorechnung ' . $number, $inv['id'], $inv['customer_id'] );
	nw_log( 'Stornorechnung zu ' . $inv['number'] . ' ausgestellt', $sid, $inv['customer_id'] );
	return nw_invoice_get( $sid );
}

/** Kopie als neuer Entwurf (heutiges Datum, aktuelle Kundenadresse). */
function nw_invoice_duplicate( $id ) {
	$inv = nw_invoice_get( $id );
	$new = array(
		'kind'              => 'offer' === $inv['kind'] ? 'offer' : 'invoice',
		'customer_id'       => $inv['customer_id'],
		'recipient'         => $inv['recipient'],
		'refresh_recipient' => (bool) $inv['customer_id'],
		'invoice_date'      => nw_today(),
		'payment_days'      => null,
		'subject'           => $inv['subject'],
		'greeting'          => $inv['greeting'],
		'intro'             => 'import' === $inv['source'] ? nw_setting( 'intro' ) : $inv['intro'],
		'outro'             => $inv['outro'],
		'items'             => array_map(
			function ( $it ) {
				$it['qty'] = abs( $it['qty'] );
				return $it;
			},
			$inv['items']
		),
	);
	if ( 'import' === $inv['source'] ) {
		unset( $new['greeting'] ); // aus den Kundendaten neu bilden
	}
	return nw_invoice_save( $new );
}

/** Angebot: Zusage / Absage / wieder offen. */
function nw_offer_state( $id, $state ) {
	$inv = nw_invoice_get( $id );
	if ( 'offer' !== $inv['kind'] || 'issued' !== $inv['status'] ) {
		nw_fail( 'Nur ausgestellte Angebote haben einen Status.' );
	}
	if ( ! in_array( $state, array( 'open', 'accepted', 'declined' ), true ) ) {
		nw_fail( 'Unbekannter Status.' );
	}
	nw_update( 'invoices', $inv['id'], array( 'offer_state' => $state, 'updated_at' => nw_now() ) );
	$t = array( 'open' => 'wieder offen', 'accepted' => 'angenommen', 'declined' => 'abgelehnt' );
	nw_log( 'Angebot ' . $t[ $state ], $inv['id'], $inv['customer_id'] );
	return nw_invoice_get( $inv['id'] );
}

/** Angebot in eine Rechnung (Entwurf) umwandeln – Positionen, Kunde und Betreff werden übernommen. */
function nw_offer_convert( $id ) {
	$offer = nw_invoice_get( $id );
	if ( 'offer' !== $offer['kind'] ) {
		nw_fail( 'Das ist kein Angebot.' );
	}
	if ( $offer['converted'] ) {
		nw_fail( 'Aus diesem Angebot wurde schon die Rechnung ' . ( $offer['converted']['number'] ?: '(Entwurf)' ) . ' erstellt.' );
	}
	$inv = nw_invoice_save(
		array(
			'kind'              => 'invoice',
			'customer_id'       => $offer['customer_id'],
			'recipient'         => $offer['recipient'],
			'refresh_recipient' => (bool) $offer['customer_id'],
			'invoice_date'      => nw_today(),
			'subject'           => $offer['subject'],
			'greeting'          => $offer['greeting'],
			'items'             => $offer['items'],
			'note'              => 'Aus Angebot ' . ( $offer['number'] ?: 'Entwurf' ),
		)
	);
	nw_update( 'invoices', $offer['id'], array( 'converted_id' => $inv['id'], 'offer_state' => 'issued' === $offer['status'] ? 'accepted' : $offer['offer_state'], 'updated_at' => nw_now() ) );
	nw_log( 'In Rechnung umgewandelt', $offer['id'], $offer['customer_id'] );
	nw_log( 'Aus Angebot ' . ( $offer['number'] ?: 'Entwurf' ) . ' erstellt', $inv['id'], $offer['customer_id'] );
	return nw_invoice_get( $inv['id'] );
}

/**
 * Rechnung, Angebot oder Entwurf löschen. War es die zuletzt vergebene Nummer, wird sie wieder frei;
 * sonst bleibt die Lücke und es wird weitergezählt. Verknüpfungen (Storno, Angebot, Stunden, Zahlungen) werden aufgelöst.
 *
 * @return array{number:?string, reused:bool}
 */
function nw_invoice_delete( $id ) {
	$inv = nw_invoice_get( $id );
	if ( $inv['storno'] ) {
		nw_fail( 'Zu dieser Rechnung gibt es die Stornorechnung ' . $inv['storno']['number'] . ' – bitte zuerst diese löschen.' );
	}
	$reused = false;
	db()->beginTransaction();
	try {
		if ( 'storno' === $inv['kind'] && $inv['ref_id'] ) {
			nw_update( 'invoices', (int) $inv['ref_id'], array( 'status' => 'issued', 'updated_at' => nw_now() ) ); // Storno weg → Original gilt wieder
			nw_log( 'Stornorechnung ' . $inv['number'] . ' gelöscht – Rechnung gilt wieder', (int) $inv['ref_id'], $inv['customer_id'] );
		}
		q( "UPDATE invoices SET converted_id = NULL, offer_state = 'open' WHERE converted_id = ?", array( $inv['id'] ) );
		q( 'UPDATE recurring SET last_invoice_id = NULL WHERE last_invoice_id = ?', array( $inv['id'] ) );
		q( 'UPDATE time_entries SET invoice_id = NULL, billed = 0, billed_at = NULL WHERE invoice_id = ?', array( $inv['id'] ) );
		q( 'UPDATE mail_log SET invoice_id = NULL WHERE invoice_id = ?', array( $inv['id'] ) );
		q( 'DELETE FROM activity WHERE invoice_id = ?', array( $inv['id'] ) );
		q( 'DELETE FROM invoices WHERE id = ?', array( $inv['id'] ) ); // Positionen und Zahlungen werden mitgelöscht

		// Nummernkreis: nur die zuletzt vergebene Nummer wird wieder frei
		$n = (string) $inv['number'];
		if ( '' !== $n ) {
			if ( 'offer' === $inv['kind'] && preg_match( '/^A-(\d+)$/', $n, $m ) ) {
				if ( (int) nw_setting( 'next_offer' ) === (int) $m[1] + 1 ) {
					nw_set_setting( 'next_offer', $m[1] );
					$reused = true;
				}
			} elseif ( ctype_digit( $n ) && (int) nw_setting( 'next_number' ) === (int) $n + 1
				&& ! q_val( "SELECT 1 FROM invoices WHERE number GLOB '[0-9]*' AND CAST(number AS INTEGER) > ?", array( (int) $n ) ) ) {
				nw_set_setting( 'next_number', $n );
				$reused = true;
			}
		}
		db()->commit();
	} catch ( Throwable $e ) {
		db()->rollBack();
		throw $e;
	}
	$what = 'offer' === $inv['kind'] ? 'Angebot' : ( 'storno' === $inv['kind'] ? 'Stornorechnung' : 'Rechnung' );
	nw_log( 'draft' === $inv['status'] ? $what . 'sentwurf gelöscht' : $what . ' ' . $inv['number'] . ' gelöscht' . ( $reused ? ' – Nummer wird wieder vergeben' : '' ), null, $inv['customer_id'] );
	return array( 'number' => $inv['number'], 'reused' => $reused );
}

/* ==================================================================== Stunden */

/** „1,5“, „1.5“, „1:30“, „90m“, „2h“, „1h 15m“ → Stunden. */
function nw_parse_hours( $v ) {
	$v = strtolower( trim( str_replace( ',', '.', (string) $v ) ) );
	if ( preg_match( '/^(\d+):(\d{1,2})$/', $v, $m ) ) {
		return round( (int) $m[1] + (int) $m[2] / 60, 2 );
	}
	if ( preg_match( '/^(?:(\d+(?:\.\d+)?)\s*h)?\s*(?:(\d+)\s*m(?:in)?)?$/', $v, $m ) && '' !== $v && ( ! empty( $m[1] ) || ! empty( $m[2] ) ) ) {
		return round( (float) ( $m[1] ?? 0 ) + (int) ( $m[2] ?? 0 ) / 60, 2 );
	}
	return is_numeric( $v ) ? round( (float) $v, 2 ) : 0.0;
}

function nw_hour_rate( $customer_id ) {
	$c = $customer_id ? q_row( 'SELECT hour_rate FROM customers WHERE id = ?', array( (int) $customer_id ) ) : null;
	return null !== ( $c['hour_rate'] ?? null ) ? (float) $c['hour_rate'] : (float) str_replace( ',', '.', nw_setting( 'hour_rate' ) ?: '0' );
}

function nw_time_row( array $r ) {
	$r['hours'] = (float) $r['hours'];
	$r['state'] = $r['billed'] ? 'billed' : ( $r['invoice_id'] ? 'draft' : 'open' );
	return $r;
}

/** Einträge: customer_id, state (open|billed|all), from, to. */
function nw_time_list( array $f = array() ) {
	$where = array( '1=1' );
	$args  = array();
	if ( ! empty( $f['customer_id'] ) ) {
		$where[] = 't.customer_id = ?';
		$args[]  = (int) $f['customer_id'];
	}
	if ( 'open' === ( $f['state'] ?? '' ) ) {
		$where[] = 't.billed = 0';
	} elseif ( 'billed' === ( $f['state'] ?? '' ) ) {
		$where[] = 't.billed = 1';
	}
	return array_map(
		'nw_time_row',
		q_all(
			"SELECT t.*, COALESCE(NULLIF(c.company,''), c.person) AS customer_name, i.number AS invoice_number, i.status AS invoice_status
			FROM time_entries t LEFT JOIN customers c ON c.id = t.customer_id LEFT JOIN invoices i ON i.id = t.invoice_id
			WHERE " . implode( ' AND ', $where ) . ' ORDER BY t.date DESC, t.id DESC LIMIT 2000',
			$args
		)
	);
}

function nw_time_get( $id ) {
	$r = q_row( 'SELECT * FROM time_entries WHERE id = ?', array( (int) $id ) );
	if ( ! $r ) {
		nw_fail( 'Eintrag nicht gefunden.' );
	}
	return nw_time_row( $r );
}

function nw_time_save( array $d ) {
	$id  = (int) ( $d['id'] ?? 0 );
	$old = $id ? nw_time_get( $id ) : null;
	$row = array(
		'customer_id' => (int) ( $d['customer_id'] ?? 0 ),
		'date'        => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $d['date'] ?? '' ) ) ? $d['date'] : nw_today(),
		'hours'       => nw_parse_hours( $d['hours'] ?? 0 ),
		'project'     => mb_substr( trim( (string) ( $d['project'] ?? '' ) ), 0, 120 ),
		'note'        => mb_substr( trim( (string) ( $d['note'] ?? '' ) ), 0, 4000 ),
		'updated_at'  => nw_now(),
	);
	if ( ! $row['customer_id'] || ! q_val( 'SELECT 1 FROM customers WHERE id = ?', array( $row['customer_id'] ) ) ) {
		nw_fail( 'Bitte einen Kunden wählen.' );
	}
	if ( $row['hours'] <= 0 || $row['hours'] > 24 ) {
		nw_fail( 'Bitte eine Dauer zwischen 0 und 24 Stunden angeben (z. B. 1,5 oder 1:30).' );
	}
	if ( $old && $old['billed'] && ( $old['hours'] !== $row['hours'] || (int) $old['customer_id'] !== $row['customer_id'] ) ) {
		nw_fail( 'Abgerechnete Stunden lassen sich nicht mehr ändern – nur die Notiz.' );
	}
	if ( $old ) {
		nw_update( 'time_entries', $id, $row );
	} else {
		$row['created_at'] = nw_now();
		$id                = nw_insert( 'time_entries', $row );
	}
	return nw_time_get( $id );
}

function nw_time_delete( $id ) {
	$t = nw_time_get( $id );
	if ( $t['billed'] && $t['invoice_id'] ) {
		nw_fail( 'Dieser Eintrag ist mit einer ausgestellten Rechnung abgerechnet und bleibt erhalten.' );
	}
	q( 'DELETE FROM time_entries WHERE id = ?', array( (int) $id ) );
}

/** Von Hand als abgerechnet markieren (ohne Rechnung) oder wieder öffnen. */
function nw_time_mark( array $ids, $billed ) {
	$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
	if ( ! $ids ) {
		return 0;
	}
	$in = implode( ',', array_fill( 0, count( $ids ), '?' ) );
	if ( $billed ) {
		return q( "UPDATE time_entries SET billed = 1, billed_at = ? WHERE billed = 0 AND id IN ($in)", array_merge( array( nw_now() ), $ids ) )->rowCount();
	}
	// wieder öffnen – nicht wenn eine ausgestellte Rechnung dranhängt
	return q( "UPDATE time_entries SET billed = 0, billed_at = NULL WHERE billed = 1 AND invoice_id IS NULL AND id IN ($in)", $ids )->rowCount();
}

/** Stunden einem Entwurf zuordnen (nur offene des Kunden); nicht mehr gewählte werden freigegeben. */
function nw_time_link( $invoice_id, $customer_id, array $ids ) {
	$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
	q( 'UPDATE time_entries SET invoice_id = NULL WHERE invoice_id = ? AND billed = 0', array( (int) $invoice_id ) );
	if ( $ids && $customer_id ) {
		$in = implode( ',', array_fill( 0, count( $ids ), '?' ) );
		q( "UPDATE time_entries SET invoice_id = ? WHERE billed = 0 AND (invoice_id IS NULL OR invoice_id = ?) AND customer_id = ? AND id IN ($in)", array_merge( array( (int) $invoice_id, (int) $invoice_id, (int) $customer_id ), $ids ) );
	}
}

/** Übersicht: offen je Kunde, Woche/Monat, zuletzt genutzte Projekte. */
function nw_time_summary() {
	$open = q_all(
		"SELECT t.customer_id, COALESCE(NULLIF(c.company,''), c.person) AS customer_name, ROUND(SUM(t.hours),2) AS hours, COUNT(*) AS entries, MIN(t.date) AS since
		FROM time_entries t JOIN customers c ON c.id = t.customer_id WHERE t.billed = 0 GROUP BY t.customer_id ORDER BY hours DESC"
	);
	foreach ( $open as &$o ) {
		$o['hours'] = (float) $o['hours'];
		$o['rate']  = nw_hour_rate( $o['customer_id'] );
		$o['value'] = round( $o['hours'] * $o['rate'], 2 );
	}
	unset( $o );
	$monday = date( 'Y-m-d', strtotime( 'monday this week' ) );
	return array(
		'open'     => $open,
		'open_h'   => round( array_sum( array_column( $open, 'hours' ) ), 2 ),
		'open_val' => round( array_sum( array_column( $open, 'value' ) ), 2 ),
		'week'     => (float) q_val( 'SELECT COALESCE(SUM(hours),0) FROM time_entries WHERE date >= ?', array( $monday ) ),
		'month'    => (float) q_val( 'SELECT COALESCE(SUM(hours),0) FROM time_entries WHERE date >= ?', array( date( 'Y-m-01' ) ) ),
		'projects' => array_column( q_all( "SELECT project, MAX(date) AS d FROM time_entries WHERE project != '' GROUP BY project ORDER BY d DESC LIMIT 30" ), 'project' ),
		'rate'     => (float) str_replace( ',', '.', nw_setting( 'hour_rate' ) ?: '0' ),
	);
}

/* ==================================================================== Dauerrechnungen */

function nw_recurring_row( array $r ) {
	$r['items']   = json_decode( (string) $r['items'], true ) ?: array();
	$sum          = nw_totals( nw_items_clean( $r['items'], nw_small_business() ) );
	$r['gross']   = $sum['gross'];
	$r['yearly']  = round( $sum['gross'] * 12 / max( 1, (int) $r['interval_months'] ), 2 );
	$r['due']     = $r['active'] && $r['next_date'] && $r['next_date'] <= nw_today();
	$r['email_to'] = '' !== trim( (string) $r['email'] ) ? $r['email'] : (string) ( $r['customer_email'] ?? '' );
	$r['email_missing'] = 'send' === $r['mode'] && ! nw_emails( $r['email_to'] );
	return $r;
}

function nw_recurring_list() {
	$rows = q_all(
		"SELECT r.*, COALESCE(NULLIF(c.company,''), c.person) AS customer_name, c.number AS customer_number, c.email AS customer_email,
			(SELECT number FROM invoices WHERE id = r.last_invoice_id) AS last_number
		FROM recurring r LEFT JOIN customers c ON c.id = r.customer_id
		ORDER BY r.active DESC, r.next_date, customer_name COLLATE NOCASE"
	);
	return array_map( 'nw_recurring_row', $rows );
}

function nw_recurring_get( $id ) {
	$r = q_row(
		"SELECT r.*, COALESCE(NULLIF(c.company,''), c.person) AS customer_name, c.number AS customer_number, c.email AS customer_email
		FROM recurring r LEFT JOIN customers c ON c.id = r.customer_id WHERE r.id = ?",
		array( (int) $id )
	);
	if ( ! $r ) {
		nw_fail( 'Dauerrechnung nicht gefunden.' );
	}
	$r             = nw_recurring_row( $r );
	$r['invoices'] = array_map( 'nw_invoice_row', q_all( 'SELECT * FROM invoices WHERE recurring_id = ? ORDER BY invoice_date DESC', array( $r['id'] ) ) );
	return $r;
}

function nw_recurring_save( array $d ) {
	$id = (int) ( $d['id'] ?? 0 );
	if ( empty( $d['customer_id'] ) ) {
		nw_fail( 'Bitte einen Kunden wählen.' );
	}
	$c     = nw_customer_get( (int) $d['customer_id'] );
	$items = nw_items_clean( (array) ( $d['items'] ?? array() ), false );
	if ( ! $items ) {
		nw_fail( 'Bitte mindestens eine Position angeben.' );
	}
	foreach ( $items as &$it ) {
		unset( $it['net'], $it['pos'] );
	}
	unset( $it );
	$mode = in_array( $d['mode'] ?? '', array( 'send', 'issue', 'draft' ), true ) ? $d['mode'] : 'send';
	$next = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $d['next_date'] ?? '' ) ) ? $d['next_date'] : null;
	if ( ! $next ) {
		nw_fail( 'Bitte das nächste Rechnungsdatum angeben.' );
	}
	foreach ( preg_split( '/[,;\s]+/', (string) ( $d['email'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY ) as $e ) {
		if ( ! nw_is_email( $e ) ) {
			nw_fail( 'Ungültige E-Mail-Adresse: ' . $e );
		}
	}
	$row = array(
		'customer_id'     => (int) $c['id'],
		'title'           => trim( (string) ( $d['title'] ?? '' ) ),
		'interval_months' => in_array( (int) ( $d['interval_months'] ?? 12 ), array( 1, 2, 3, 6, 12, 24, 36 ), true ) ? (int) ( $d['interval_months'] ?? 12 ) : 12,
		'next_date'       => $next,
		'end_date'        => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $d['end_date'] ?? '' ) ) ? $d['end_date'] : null,
		'mode'            => $mode,
		'email'           => trim( (string) ( $d['email'] ?? '' ) ),
		'items'           => json_encode( $items, JSON_UNESCAPED_UNICODE ),
		'intro'           => (string) ( $d['intro'] ?? '' ),
		'note'            => (string) ( $d['note'] ?? '' ),
		'active'          => empty( $d['active'] ) ? 0 : 1,
		'updated_at'      => nw_now(),
	);
	if ( $id ) {
		nw_update( 'recurring', $id, $row );
	} else {
		$row['created_at'] = nw_now();
		$id                = nw_insert( 'recurring', $row );
		nw_log( 'Dauerrechnung angelegt: ' . ( $row['title'] ?: nw_customer_name( $c ) ), null, $c['id'] );
	}
	return nw_recurring_get( $id );
}

function nw_recurring_delete( $id ) {
	q( 'UPDATE invoices SET recurring_id = NULL WHERE recurring_id = ?', array( (int) $id ) );
	q( 'DELETE FROM recurring WHERE id = ?', array( (int) $id ) );
}

/** Platzhalter in Positionstexten: {JAHR} {VORJAHR} {MONAT} {ZEITRAUM} {VON} {BIS}. */
function nw_recurring_vars( $from, $to ) {
	return array(
		'{JAHR}'     => substr( $from, 0, 4 ),
		'{VORJAHR}'  => (string) ( (int) substr( $from, 0, 4 ) - 1 ),
		'{MONAT}'    => nw_month_name( (int) substr( $from, 5, 2 ) ) . ' ' . substr( $from, 0, 4 ),
		'{ZEITRAUM}' => nw_date( $from ) . ' – ' . nw_date( $to ),
		'{VON}'      => nw_date( $from ),
		'{BIS}'      => nw_date( $to ),
	);
}

/**
 * Eine Dauerrechnung ausführen: Rechnung erzeugen (Entwurf / ausgestellt / ausgestellt + Mail)
 * und das nächste Datum weiterschalten.
 *
 * @return array{invoice:array, mail:?array, note:string}
 */
function nw_recurring_run( $id, $mode = null ) {
	$r    = nw_recurring_get( $id );
	$c    = nw_customer_get( $r['customer_id'] );
	$mode = $mode ? $mode : $r['mode'];
	$from = $r['next_date'];
	$to   = nw_add_days( nw_add_months( $from, (int) $r['interval_months'] ), -1 );
	$vars = nw_recurring_vars( $from, $to );
	$items = array_map(
		function ( $it ) use ( $vars ) {
			$it['name']        = strtr( (string) $it['name'], $vars );
			$it['description'] = strtr( (string) $it['description'], $vars );
			return $it;
		},
		$r['items']
	);
	$data = array(
		'customer_id'  => $c['id'],
		'invoice_date' => nw_today(),
		'period_from'  => $from,
		'period_to'    => $to,
		'items'        => $items,
		'recurring_id' => $r['id'],
		'note'         => 'Aus Dauerrechnung „' . ( $r['title'] ?: $c['name'] ) . '“',
	);
	if ( '' !== trim( $r['intro'] ) ) {
		$data['intro'] = $r['intro'];
	}
	$inv  = nw_invoice_save( $data );
	$note = '';
	$mail = null;
	if ( 'draft' !== $mode ) {
		$inv = nw_invoice_issue( $inv['id'] );
		if ( 'send' === $mode ) {
			$to_addr = $r['email_to'];
			if ( ! nw_emails( $to_addr ) ) {
				$note = 'Keine E-Mail-Adresse – Rechnung ausgestellt, aber nicht versendet.';
			} else {
				$mail = nw_mail_invoice( $inv['id'], 'invoice', array( 'to' => $to_addr ) );
				$inv  = nw_invoice_get( $inv['id'] );
				if ( ! $mail['ok'] ) {
					$note = 'Versand fehlgeschlagen: ' . $mail['error'];
				}
			}
		}
	}
	$next   = nw_add_months( $from, (int) $r['interval_months'] );
	$active = $r['end_date'] && $next > $r['end_date'] ? 0 : 1;
	nw_update( 'recurring', $r['id'], array( 'next_date' => $next, 'last_invoice_id' => $inv['id'], 'last_run' => nw_now(), 'active' => $active ) );
	nw_log( 'Dauerrechnung „' . ( $r['title'] ?: $c['name'] ) . '“ ausgeführt' . ( $note ? ' – ' . $note : '' ), $inv['id'], $c['id'] );
	return array( 'invoice' => $inv, 'mail' => $mail, 'note' => $note );
}

/** Alle fälligen Dauerrechnungen (für Cron und den Knopf in der Übersicht). */
function nw_recurring_run_due() {
	$done = array();
	foreach ( q_all( 'SELECT id FROM recurring WHERE active = 1 AND next_date <= ? ORDER BY next_date', array( nw_today() ) ) as $r ) {
		// Mehrfach fällig (z. B. monatlich und Cron lief lange nicht)? Höchstens 12 Durchläufe.
		for ( $i = 0; $i < 12; $i++ ) {
			$cur = q_row( 'SELECT active, next_date FROM recurring WHERE id = ?', array( $r['id'] ) );
			if ( ! $cur || ! $cur['active'] || $cur['next_date'] > nw_today() ) {
				break;
			}
			try {
				$res    = nw_recurring_run( $r['id'] );
				$done[] = array( 'recurring_id' => $r['id'], 'invoice' => $res['invoice']['number'] ?: 'Entwurf', 'customer' => $res['invoice']['recipient']['name'], 'note' => $res['note'], 'mailed' => ! empty( $res['mail']['ok'] ) );
			} catch ( Throwable $e ) {
				$done[] = array( 'recurring_id' => $r['id'], 'error' => $e->getMessage() );
				break;
			}
		}
	}
	nw_set_setting( 'cron_last', nw_now() );
	return $done;
}

/* ==================================================================== Ausgaben */

function nw_expenses_list( $year = null ) {
	if ( $year ) {
		return q_all( 'SELECT * FROM expenses WHERE substr(date,1,4) = ? ORDER BY date DESC, id DESC', array( (string) $year ) );
	}
	return q_all( 'SELECT * FROM expenses ORDER BY date DESC, id DESC' );
}

function nw_expense_save( array $d ) {
	$id  = (int) ( $d['id'] ?? 0 );
	$row = array(
		'date'        => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $d['date'] ?? '' ) ) ? $d['date'] : nw_today(),
		'vendor'      => trim( (string) ( $d['vendor'] ?? '' ) ),
		'description' => trim( (string) ( $d['description'] ?? '' ) ),
		'category'    => trim( (string) ( $d['category'] ?? '' ) ),
		'amount'      => round( (float) str_replace( ',', '.', (string) ( $d['amount'] ?? 0 ) ), 2 ),
		'updated_at'  => nw_now(),
	);
	if ( '' === $row['vendor'] && '' === $row['description'] ) {
		nw_fail( 'Bitte Lieferant oder Beschreibung angeben.' );
	}
	if ( $id ) {
		nw_update( 'expenses', $id, $row );
	} else {
		$row['created_at'] = nw_now();
		$id                = nw_insert( 'expenses', $row );
	}
	return q_row( 'SELECT * FROM expenses WHERE id = ?', array( $id ) );
}

function nw_expense_delete( $id ) {
	$e = q_row( 'SELECT * FROM expenses WHERE id = ?', array( (int) $id ) );
	if ( $e && $e['file'] ) {
		@unlink( nw_data_dir( 'files' ) . '/' . $e['file'] );
	}
	q( 'DELETE FROM expenses WHERE id = ?', array( (int) $id ) );
}

/* ==================================================================== Übersicht */

function nw_dashboard() {
	$year  = (int) date( 'Y' );
	$today = nw_today();
	$rev   = function ( $y ) {
		return (float) q_val( "SELECT COALESCE(SUM(gross),0) FROM invoices WHERE status != 'draft' AND kind != 'offer' AND substr(invoice_date,1,4) = ?", array( (string) $y ) );
	};
	$months = function ( $y ) {
		$m = array_fill( 0, 12, 0.0 );
		foreach ( q_all( "SELECT CAST(substr(invoice_date,6,2) AS INTEGER) AS m, SUM(gross) AS s FROM invoices WHERE status != 'draft' AND kind != 'offer' AND substr(invoice_date,1,4) = ? GROUP BY m", array( (string) $y ) ) as $r ) {
			$m[ (int) $r['m'] - 1 ] = round( (float) $r['s'], 2 );
		}
		return $m;
	};
	$open = array_values(
		array_filter(
			array_map( 'nw_invoice_row', q_all( "SELECT * FROM invoices WHERE status = 'issued' AND kind = 'invoice' AND paid_at IS NULL ORDER BY due_date" ) ),
			function ( $r ) { return in_array( $r['state'], array( 'open', 'overdue', 'partial' ), true ); }
		)
	);
	$overdue = array_values( array_filter( $open, function ( $r ) { return $r['days_overdue'] > 0; } ) );
	$years   = q_all( "SELECT substr(invoice_date,1,4) AS y, ROUND(SUM(gross),2) AS revenue, COUNT(CASE WHEN kind = 'invoice' THEN 1 END) AS n FROM invoices WHERE status != 'draft' AND kind != 'offer' GROUP BY y ORDER BY y" );
	$exp     = (float) q_val( 'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE substr(date,1,4) = ?', array( (string) $year ) );
	$rec     = nw_recurring_list();
	$soon    = nw_add_days( $today, 60 );
	$upcoming = array_values( array_filter( $rec, function ( $r ) use ( $soon ) { return $r['active'] && $r['next_date'] <= $soon; } ) );
	$top     = q_all(
		"SELECT c.id, COALESCE(NULLIF(c.company,''), c.person) AS name, ROUND(SUM(i.gross),2) AS revenue
		FROM invoices i JOIN customers c ON c.id = i.customer_id
		WHERE i.status != 'draft' AND i.kind != 'offer' AND substr(i.invoice_date,1,4) = ? GROUP BY c.id ORDER BY revenue DESC LIMIT 5",
		array( (string) $year )
	);
	$active_rec = array_values( array_filter( $rec, function ( $r ) { return $r['active']; } ) );
	return array(
		'year'          => $year,
		'revenue'       => $rev( $year ),
		'revenue_prev'  => $rev( $year - 1 ),
		'revenue_prev_ytd' => (float) q_val( "SELECT COALESCE(SUM(gross),0) FROM invoices WHERE status != 'draft' AND kind != 'offer' AND invoice_date BETWEEN ? AND ?", array( ( $year - 1 ) . '-01-01', ( $year - 1 ) . substr( $today, 4 ) ) ),
		'months'        => $months( $year ),
		'months_prev'   => $months( $year - 1 ),
		'years'         => $years,
		'open_sum'      => round( array_sum( array_column( $open, 'open' ) ), 2 ),
		'open'          => array_slice( $open, 0, 8 ),
		'open_count'    => count( $open ),
		'overdue_sum'   => round( array_sum( array_column( $overdue, 'open' ) ), 2 ),
		'overdue_count' => count( $overdue ),
		'drafts'        => array_map( 'nw_invoice_row', q_all( "SELECT * FROM invoices WHERE status = 'draft' ORDER BY updated_at DESC LIMIT 6" ) ),
		'offers_open'   => array_values(
			array_filter(
				array_map( 'nw_invoice_row', q_all( "SELECT * FROM invoices WHERE kind = 'offer' AND status = 'issued' AND COALESCE(offer_state,'open') = 'open' ORDER BY valid_until" ) ),
				function ( $r ) { return 'sent' === $r['state']; }
			)
		),
		'expenses'      => $exp,
		'recurring_due' => array_values( array_filter( $rec, function ( $r ) { return $r['due']; } ) ),
		'recurring_upcoming' => $upcoming,
		'recurring_yearly'   => round( array_sum( array_column( $active_rec, 'yearly' ) ), 2 ),
		'recurring_count'    => count( $active_rec ),
		'top_customers' => $top,
		'customers'     => (int) q_val( 'SELECT COUNT(*) FROM customers WHERE archived = 0' ),
		'limit'         => (float) nw_setting( 'revenue_limit' ),
		'small_business' => nw_small_business(),
		'activity'      => q_all(
			"SELECT a.text, a.created_at, a.invoice_id, a.customer_id, i.number, COALESCE(NULLIF(c.company,''), c.person) AS customer
			FROM activity a LEFT JOIN invoices i ON i.id = a.invoice_id LEFT JOIN customers c ON c.id = COALESCE(a.customer_id, i.customer_id)
			ORDER BY a.id DESC LIMIT 12"
		),
		'cron_last'     => nw_setting( 'cron_last' ),
		'hours'         => nw_time_summary(),
	);
}
