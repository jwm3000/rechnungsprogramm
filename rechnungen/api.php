<?php
/**
 * JSON-API der Rechnungs-App. Aufruf: api.php?a=<aktion>
 * Lesen per GET, Schreiben per POST (JSON) mit CSRF-Token im Header X-CSRF.
 */
require __DIR__ . '/lib/bootstrap.php';

header( 'X-Content-Type-Options: nosniff' );
header( 'Referrer-Policy: same-origin' );
header( 'Cache-Control: no-store' );

function out( $data, $code = 200 ) {
	http_response_code( $code );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
	exit;
}

function send_file( $data, $name, $type, $download ) {
	header( 'Content-Type: ' . $type );
	header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="' . str_replace( '"', '', $name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
	header( 'Content-Length: ' . strlen( $data ) );
	echo $data;
	exit;
}

$a      = preg_replace( '/[^a-z_]/', '', (string) ( $_GET['a'] ?? '' ) );
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$in     = array();
if ( 'POST' === $method ) {
	$raw = file_get_contents( 'php://input' );
	if ( false !== strpos( (string) ( $_SERVER['CONTENT_TYPE'] ?? '' ), 'application/json' ) ) {
		$in = json_decode( $raw, true ) ?: array();
	} else {
		$in = $_POST;
	}
}

try {
	nw_session();

	/* ---------------------------------------------------------------- ohne Anmeldung */
	if ( 'session' === $a ) {
		out( array( 'logged_in' => nw_logged_in(), 'has_password' => nw_has_password(), 'csrf' => $_SESSION['csrf'], 'version' => NW_APP ) );
	}
	if ( 'POST' === $method && ( $_SERVER['HTTP_X_CSRF'] ?? '' ) !== $_SESSION['csrf'] ) {
		out( array( 'error' => 'Sitzung abgelaufen – bitte Seite neu laden.' ), 403 );
	}
	if ( 'setup' === $a && 'POST' === $method ) {
		if ( nw_has_password() ) {
			out( array( 'error' => 'Bereits eingerichtet.' ), 403 );
		}
		$r = nw_set_password( (string) ( $in['password'] ?? '' ) );
		if ( true !== $r ) {
			out( array( 'error' => $r ), 400 );
		}
		nw_login( (string) $in['password'] );
		out( array( 'ok' => true ) );
	}
	if ( 'login' === $a && 'POST' === $method ) {
		$r = nw_login( (string) ( $in['password'] ?? '' ) );
		if ( true !== $r ) {
			out( array( 'error' => $r ), 401 );
		}
		out( array( 'ok' => true, 'csrf' => $_SESSION['csrf'] ) );
	}
	if ( ! nw_logged_in() ) {
		out( array( 'error' => 'Bitte anmelden.', 'auth' => false ), 401 );
	}

	/* ---------------------------------------------------------------- angemeldet */
	$id = (int) ( $_GET['id'] ?? $in['id'] ?? 0 );

	switch ( $a ) {
		case 'logout':
			$_SESSION = array();
			session_destroy();
			out( array( 'ok' => true ) );

		case 'bootstrap':
			$s = nw_settings();
			foreach ( nw_private_settings() as $k ) {
				unset( $s[ $k ] );
			}
			$smtp = nw_smtp_config();
			out(
				array(
					'settings'        => $s,
					'customers'       => nw_customers_list(),
					'products'        => nw_products_list(),
					'invoices'        => nw_invoices_list(),
					'offers'          => nw_invoices_list( array( 'offers' => true ) ),
					'next_offer'      => nw_next_offer_number(),
					'recurring'       => nw_recurring_list(),
					'next_number'     => nw_next_number(),
					'mail'            => array( 'configured' => nw_mail_configured(), 'from' => $smtp['from'] ?: $smtp['user'], 'host' => $smtp['host'], 'bcc' => $smtp['bcc'], 'reply_to' => nw_reply_to( $smtp, $smtp['from'] ?: $smtp['user'] ), 'source' => $smtp['source'], 'has_pass' => '' !== $smtp['pass'] ),
					'cron_url'        => 'cron.php?key=' . nw_cron_key(),
					'today'           => nw_today(),
					'version'         => NW_APP,
					'logo'            => nw_logo_html(),
				)
			);

		case 'dashboard':
			out( nw_dashboard() );

		/* ------------------------------------------------ Kunden */
		case 'customer':
			$c              = nw_customer_get( $id );
			$c['invoices']  = nw_invoices_list( array( 'customer_id' => $id ) );
			$c['offers']    = nw_invoices_list( array( 'customer_id' => $id, 'offers' => true ) );
			$c['recurring'] = array_values( array_filter( nw_recurring_list(), function ( $r ) use ( $id ) { return (int) $r['customer_id'] === $id; } ) );
			out( $c );
		case 'customer_save':
			out( nw_customer_save( $in ) );
		case 'customer_delete':
			out( array( 'result' => nw_customer_delete( $id ) ) );

		/* ------------------------------------------------ Artikel */
		case 'product_save':
			out( nw_product_save( $in ) );
		case 'product_delete':
			out( array( 'result' => nw_product_delete( $id ) ) );

		/* ------------------------------------------------ Rechnungen */
		case 'invoices':
			out( nw_invoices_list() );
		case 'invoice':
			out( nw_invoice_get( $id ) );
		case 'invoice_save':
			out( nw_invoice_save( $in ) );
		case 'invoice_issue':
			if ( ! empty( $in['data'] ) ) {
				nw_invoice_save( $in['data'] );
			}
			out( nw_invoice_issue( $id ) );
		case 'invoice_pay':
			out( nw_invoice_pay( $id, $in['date'] ?? null, $in['amount'] ?? null ) );
		case 'invoice_unpay':
			out( nw_invoice_unpay( $id ) );
		case 'invoice_cancel':
			out( nw_invoice_cancel( $id ) );
		case 'invoice_duplicate':
			out( nw_invoice_duplicate( $id ) );
		case 'offer_state':
			out( nw_offer_state( $id, (string) ( $in['state'] ?? '' ) ) );
		case 'offer_convert':
			out( nw_offer_convert( $id ) );
		case 'invoice_delete':
			nw_invoice_delete( $id );
			out( array( 'ok' => true ) );
		case 'mail_preview':
			$inv = nw_invoice_get( $id );
			$m   = nw_mail_compose( $inv, ( $_GET['type'] ?? '' ) === 'reminder' ? 'reminder' : 'invoice' );
			out( $m + array( 'to' => $inv['recipient']['email'] ?: ( $inv['customer']['email'] ?? '' ), 'cc' => $inv['customer']['email_cc'] ?? '', 'configured' => nw_mail_configured(), 'filename' => NW_Document::filename( $inv ) ) );
		case 'invoice_mail':
			$r = nw_mail_invoice( $id, ( $in['type'] ?? '' ) === 'reminder' ? 'reminder' : 'invoice', array_intersect_key( $in, array_flip( array( 'to', 'cc', 'subject', 'body' ) ) ) );
			if ( ! $r['ok'] ) {
				out( array( 'error' => $r['error'] ), 400 );
			}
			out( array( 'ok' => true, 'to' => $r['to'], 'invoice' => nw_invoice_get( $id ) ) );
		case 'invoice_mark_sent':
			nw_update( 'invoices', $id, array( 'sent_at' => nw_now() ) );
			nw_log( 'Als versendet markiert', $id );
			out( nw_invoice_get( $id ) );

		case 'pdf':
			$inv = nw_invoice_get( $id );
			send_file( NW_Document::render( $inv ), NW_Document::filename( $inv ), 'application/pdf', ! empty( $_GET['dl'] ) );
		case 'preview':
			$inv = nw_invoice_preview( $in );
			$pdf = NW_Document::render( $inv );
			header( 'X-NW-Compact: ' . NW_Document::$last_level );
			send_file( $pdf, 'Vorschau.pdf', 'application/pdf', false );
		case 'original':
			$inv  = nw_invoice_get( $id );
			$file = $inv['original_file'] ? nw_data_dir( 'files' ) . '/' . $inv['original_file'] : '';
			if ( ! $file || ! is_file( $file ) || false !== strpos( $inv['original_file'], '..' ) ) {
				out( array( 'error' => 'Kein Original vorhanden.' ), 404 );
			}
			send_file( file_get_contents( $file ), basename( $file ), 'application/pdf', ! empty( $_GET['dl'] ) );

		/* ------------------------------------------------ Dauerrechnungen */
		case 'recurring':
			out( nw_recurring_get( $id ) );
		case 'recurring_save':
			out( nw_recurring_save( $in ) );
		case 'recurring_delete':
			nw_recurring_delete( $id );
			out( array( 'ok' => true ) );
		case 'recurring_run':
			$mode = in_array( $in['mode'] ?? '', array( 'send', 'issue', 'draft' ), true ) ? $in['mode'] : null;
			out( nw_recurring_run( $id, $mode ) );
		case 'recurring_run_due':
			out( array( 'done' => nw_recurring_run_due() ) );

		/* ------------------------------------------------ Ausgaben */
		case 'expenses':
			out( nw_expenses_list() );
		case 'expense_save':
			$e = nw_expense_save( $in );
			if ( ! empty( $_FILES['file'] ) && UPLOAD_ERR_OK === $_FILES['file']['error'] ) {
				$f   = $_FILES['file'];
				$ext = strtolower( pathinfo( $f['name'], PATHINFO_EXTENSION ) );
				if ( ! in_array( $ext, array( 'pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic' ), true ) ) {
					nw_fail( 'Nur PDF oder Bilder als Beleg.' );
				}
				if ( $f['size'] > 15 * 1024 * 1024 ) {
					nw_fail( 'Beleg ist größer als 15 MB.' );
				}
				$rel = 'belege/' . substr( $e['date'], 0, 4 ) . '/' . $e['id'] . '-' . nw_slug( pathinfo( $f['name'], PATHINFO_FILENAME ) ) . '.' . $ext;
				nw_data_dir( 'files/' . dirname( $rel ) );
				if ( $e['file'] ) {
					@unlink( nw_data_dir( 'files' ) . '/' . $e['file'] );
				}
				move_uploaded_file( $f['tmp_name'], nw_data_dir( 'files' ) . '/' . $rel );
				nw_update( 'expenses', $e['id'], array( 'file' => $rel ) );
				$e['file'] = $rel;
			}
			out( $e );
		case 'expense_delete':
			nw_expense_delete( $id );
			out( array( 'ok' => true ) );
		case 'expense_file':
			$e    = q_row( 'SELECT * FROM expenses WHERE id = ?', array( $id ) );
			$file = $e && $e['file'] ? nw_data_dir( 'files' ) . '/' . $e['file'] : '';
			if ( ! $file || ! is_file( $file ) || false !== strpos( $e['file'], '..' ) ) {
				out( array( 'error' => 'Kein Beleg vorhanden.' ), 404 );
			}
			$types = array( 'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic' );
			send_file( file_get_contents( $file ), basename( $file ), $types[ strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ] ?? 'application/octet-stream', ! empty( $_GET['dl'] ) );

		/* ------------------------------------------------ Einstellungen */
		case 'settings_save':
			$allowed = array_keys( nw_settings_defaults() );
			foreach ( $in as $k => $v ) {
				if ( in_array( $k, $allowed, true ) && ! in_array( $k, array( 'cron_last', 'smtp_pass_enc' ), true ) && ! is_array( $v ) ) {
					nw_set_setting( $k, trim( (string) $v ) );
				}
			}
			if ( isset( $in['ui_theme'] ) || isset( $in['ui_accent_pdf'] ) ) {
				// Akzent auf Rechnungen: Farbe des Designs oder Schwarz
				if ( ! isset( nw_ui_themes()[ nw_setting( 'ui_theme' ) ] ) ) {
					nw_set_setting( 'ui_theme', 'schlicht' );
				}
				nw_set_setting( 'accent', '1' === nw_setting( 'ui_accent_pdf' ) ? nw_ui_themes()[ nw_ui_theme() ] : '#16171a' );
			}
			out( array( 'ok' => true ) );
		case 'logo_upload':
			if ( empty( $_FILES['logo'] ) || UPLOAD_ERR_OK !== $_FILES['logo']['error'] ) {
				nw_fail( 'Keine Datei empfangen.' );
			}
			try {
				nw_logo_save( (string) file_get_contents( $_FILES['logo']['tmp_name'] ) );
			} catch ( RuntimeException $e ) {
				nw_fail( $e->getMessage() );
			}
			nw_log( 'Logo geändert' );
			out( array( 'logo' => nw_logo_html() ) );
		case 'logo_delete':
			nw_logo_delete();
			out( array( 'logo' => '' ) );
		case 'password':
			if ( ! password_verify( (string) ( $in['old'] ?? '' ), nw_setting( 'password_hash' ) ) ) {
				out( array( 'error' => 'Aktuelles Passwort stimmt nicht.' ), 400 );
			}
			$r = nw_set_password( (string) ( $in['new'] ?? '' ) );
			if ( true !== $r ) {
				out( array( 'error' => $r ), 400 );
			}
			$_SESSION['pw'] = substr( nw_setting( 'password_hash' ), -12 );
			out( array( 'ok' => true ) );
		case 'smtp_save':
		case 'mail_test':
			// Formularwerte (auch ungespeichert) – leeres Passwort = gespeichertes verwenden
			$cur = nw_smtp_config();
			$cfg = $cur;
			if ( isset( $in['smtp_host'] ) && 'config' !== $cur['source'] ) {
				$cfg = array(
					'host'      => trim( (string) $in['smtp_host'] ),
					'port'      => max( 1, min( 65535, (int) ( $in['smtp_port'] ?? 465 ) ) ),
					'secure'    => in_array( $in['smtp_secure'] ?? '', array( 'ssl', 'tls' ), true ) ? $in['smtp_secure'] : 'ssl',
					'user'      => trim( (string) ( $in['smtp_user'] ?? '' ) ),
					'pass'      => '' !== (string) ( $in['smtp_pass'] ?? '' ) ? (string) $in['smtp_pass'] : $cur['pass'],
					'from'      => trim( (string) ( $in['smtp_from'] ?? '' ) ),
					'from_name' => trim( (string) ( $in['smtp_from_name'] ?? '' ) ),
					'bcc'       => trim( (string) ( $in['smtp_bcc'] ?? '' ) ),
					'reply_to'  => trim( (string) ( $in['smtp_reply_to'] ?? '' ) ),
					'source'    => 'app',
				);
				if ( '' !== $cfg['host'] && ! preg_match( '/^[a-z0-9.-]+$/i', $cfg['host'] ) ) {
					nw_fail( 'Ungültiger Servername.' );
				}
				foreach ( array( 'from', 'user' ) as $k ) {
					if ( '' !== $cfg[ $k ] && 'from' === $k && ! nw_is_email( $cfg[ $k ] ) ) {
						nw_fail( 'Ungültige Absenderadresse.' );
					}
				}
				if ( '' !== $cfg['reply_to'] && ! nw_is_email( $cfg['reply_to'] ) ) {
					nw_fail( 'Ungültige Antwortadresse.' );
				}
				foreach ( preg_split( '/[,;\s]+/', $cfg['bcc'], -1, PREG_SPLIT_NO_EMPTY ) as $e ) {
					if ( ! nw_is_email( $e ) ) {
						nw_fail( 'Ungültige Kopie-Adresse: ' . $e );
					}
				}
			}
			if ( 'smtp_save' === $a ) {
				if ( 'config' === $cur['source'] ) {
					nw_fail( 'Der E-Mail-Zugang ist in der config.php festgelegt.' );
				}
				foreach ( array( 'host', 'port', 'secure', 'user', 'from', 'from_name', 'bcc', 'reply_to' ) as $k ) {
					nw_set_setting( 'smtp_' . $k, (string) $cfg[ $k ] );
				}
				if ( ! empty( $in['smtp_clear_pass'] ) ) {
					nw_set_setting( 'smtp_pass_enc', '' );
				} elseif ( '' !== (string) ( $in['smtp_pass'] ?? '' ) ) {
					nw_set_setting( 'smtp_pass_enc', nw_encrypt( (string) $in['smtp_pass'] ) );
				}
				nw_log( 'E-Mail-Zugang gespeichert' );
				out( array( 'ok' => true ) );
			}
			$to = nw_emails( $in['to'] ?? nw_setting( 'email' ) );
			if ( ! $to ) {
				out( array( 'error' => 'Keine Empfängeradresse.' ), 400 );
			}
			if ( '' === trim( (string) $cfg['host'] ) ) {
				out( array( 'error' => 'Bitte zuerst einen SMTP-Server eintragen.' ), 400 );
			}
			try {
				NW_SMTP::send( $cfg, array( 'from' => $cfg['from'] ?: $cfg['user'], 'from_name' => $cfg['from_name'] ?: nw_setting( 'company' ), 'to' => $to, 'reply_to' => nw_reply_to( $cfg, $cfg['from'] ?: $cfg['user'] ), 'subject' => 'Testmail aus dem Rechnungsprogramm', 'text' => "Der E-Mail-Versand funktioniert.\n\n" . date( 'd.m.Y H:i' ) ) );
			} catch ( Throwable $e ) {
				out( array( 'error' => $e->getMessage() ), 400 );
			}
			out( array( 'ok' => true ) );

		/* ------------------------------------------------ Export / Sicherung */
		case 'export':
			$year = preg_replace( '/\D/', '', (string) ( $_GET['year'] ?? '' ) );
			$rows = array_reverse( nw_invoices_list( $year ? array( 'year' => $year ) : array() ) );
			$fh   = fopen( 'php://temp', 'w+' );
			fwrite( $fh, "\xEF\xBB\xBF" );
			fputcsv( $fh, array( 'Nummer', 'Art', 'Datum', 'Kundennr.', 'Kunde', 'Netto', 'USt.', 'Brutto', 'Status', 'Fällig', 'Bezahlt am' ), ';' );
			$labels = array( 'draft' => 'Entwurf', 'open' => 'offen', 'overdue' => 'überfällig', 'paid' => 'bezahlt', 'cancelled' => 'storniert', 'storno' => 'Storno' );
			foreach ( $rows as $r ) {
				if ( 'draft' === $r['status'] ) {
					continue;
				}
				fputcsv( $fh, array( $r['number'], 'storno' === $r['kind'] ? 'Stornorechnung' : 'Rechnung', nw_date( $r['invoice_date'] ), $r['recipient']['number'], $r['recipient']['name'], nw_money( $r['net'], false ), nw_money( $r['tax'], false ), nw_money( $r['gross'], false ), $labels[ $r['state'] ], nw_date( $r['due_date'] ), nw_date( $r['paid_at'] ) ), ';' );
			}
			rewind( $fh );
			send_file( stream_get_contents( $fh ), 'Rechnungen' . ( $year ? '-' . $year : '' ) . '.csv', 'text/csv; charset=utf-8', true );
		case 'export_expenses':
			$year = preg_replace( '/\D/', '', (string) ( $_GET['year'] ?? '' ) );
			$fh   = fopen( 'php://temp', 'w+' );
			fwrite( $fh, "\xEF\xBB\xBF" );
			fputcsv( $fh, array( 'Datum', 'Lieferant', 'Beschreibung', 'Kategorie', 'Betrag', 'Beleg' ), ';' );
			foreach ( array_reverse( nw_expenses_list( $year ?: null ) ) as $e ) {
				fputcsv( $fh, array( nw_date( $e['date'] ), $e['vendor'], $e['description'], $e['category'], nw_money( $e['amount'], false ), $e['file'] ? basename( $e['file'] ) : '' ), ';' );
			}
			rewind( $fh );
			send_file( stream_get_contents( $fh ), 'Ausgaben' . ( $year ? '-' . $year : '' ) . '.csv', 'text/csv; charset=utf-8', true );
		case 'export_pdfs':
			@set_time_limit( 300 );
			$year  = preg_replace( '/\D/', '', (string) ( $_GET['year'] ?? '' ) );
			$files = array();
			foreach ( nw_invoices_list( $year ? array( 'year' => $year ) : array() ) as $r ) {
				if ( 'draft' === $r['status'] ) {
					continue;
				}
				$inv = nw_invoice_get( $r['id'] );
				$files[ substr( $inv['invoice_date'], 0, 4 ) . '/' . NW_Document::filename( $inv ) ] = NW_Document::render( $inv );
			}
			send_file( nw_zip( $files ), 'Rechnungen' . ( $year ? '-' . $year : '' ) . '.zip', 'application/zip', true );
		case 'backup':
			$tmp = nw_data_dir() . '/backup-' . bin2hex( random_bytes( 6 ) ) . '.sqlite';
			db()->exec( 'VACUUM INTO ' . db()->quote( $tmp ) );
			$data = file_get_contents( $tmp );
			@unlink( $tmp );
			send_file( $data, 'rechnungen-' . date( 'Y-m-d' ) . '.sqlite', 'application/vnd.sqlite3', true );
	}
	out( array( 'error' => 'Unbekannte Aktion.' ), 404 );
} catch ( NW_Error $e ) {
	out( array( 'error' => $e->getMessage() ), 400 );
} catch ( Throwable $e ) {
	error_log( 'Rechnungen: ' . $e );
	out( array( 'error' => 'Serverfehler: ' . $e->getMessage() ), 500 );
}
