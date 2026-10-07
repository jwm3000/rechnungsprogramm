<?php
/**
 * Rechnungen – Grundgerüst: Konfiguration, Datenbank (SQLite), Einstellungen, Anmeldung, Hilfsfunktionen.
 */
define( 'NW_ROOT', dirname( __DIR__ ) );
define( 'NW_APP', trim( (string) @file_get_contents( NW_ROOT . '/VERSION' ) ) ?: '0.0.0' );

date_default_timezone_set( 'Europe/Vienna' );
mb_internal_encoding( 'UTF-8' );

/* ==================================================================== Konfiguration */

/**
 * config.php (nicht im Git) überschreibt die Vorgaben. SMTP-Zugangsdaten stehen nur dort,
 * nie in der Datenbank – ein Datenbank-Backup enthält also kein Mail-Passwort.
 */
function nw_config( $key = null ) {
	static $c = null;
	if ( null === $c ) {
		$c = array(
			'data_dir' => NW_ROOT . '/data',
			'smtp'     => array(
				'host'      => '',
				'port'      => 465,
				'secure'    => 'ssl',   // ssl (Port 465) · tls (STARTTLS, Port 587)
				'user'      => '',
				'pass'      => '',
				'from'      => '',
				'from_name' => '',
				'bcc'       => '',      // Kopie jeder Rechnung an mich
			),
			'cron_key' => '',
			'base_url' => '',
			// Software-Update über GitHub-Releases (token nur bei privatem Repo nötig, Leserecht „Contents“ genügt)
			'update'   => array(
				'repo'  => 'jwm3000/rechnungsprogramm',
				'token' => '',
			),
		);
		$file = getenv( 'NW_CONFIG' ) ?: NW_ROOT . '/config.php';
		if ( is_file( $file ) ) {
			$user = require $file;
			if ( is_array( $user ) ) {
				$c = array_replace_recursive( $c, $user );
			}
		}
		if ( getenv( 'NW_DATA_DIR' ) ) {
			$c['data_dir'] = getenv( 'NW_DATA_DIR' ); // z. B. für die Demo-Daten (bin/demo.php)
		}
	}
	return null === $key ? $c : ( $c[ $key ] ?? null );
}

function nw_data_dir( $sub = '' ) {
	$dir = rtrim( nw_config( 'data_dir' ), '/' ) . ( $sub ? '/' . trim( $sub, '/' ) : '' );
	if ( ! is_dir( $dir ) ) {
		@mkdir( $dir, 0770, true );
	}
	return $dir;
}

/* ==================================================================== Datenbank */

function db() {
	static $pdo = null;
	if ( null === $pdo ) {
		$dir = nw_data_dir();
		$pdo = new PDO( 'sqlite:' . $dir . '/rechnungen.sqlite' );
		$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$pdo->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
		$pdo->exec( 'PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;' );
		nw_migrate( $pdo );
	}
	return $pdo;
}

function q( $sql, array $args = array() ) {
	$st = db()->prepare( $sql );
	$st->execute( $args );
	return $st;
}

function q_all( $sql, array $args = array() ) {
	return q( $sql, $args )->fetchAll();
}

function q_row( $sql, array $args = array() ) {
	$r = q( $sql, $args )->fetch();
	return $r ? $r : null;
}

function q_val( $sql, array $args = array() ) {
	$r = q( $sql, $args )->fetchColumn();
	return false === $r ? null : $r;
}

function nw_insert( $table, array $row ) {
	$cols = array_keys( $row );
	q( 'INSERT INTO ' . $table . ' (' . implode( ',', $cols ) . ') VALUES (' . implode( ',', array_fill( 0, count( $cols ), '?' ) ) . ')', array_values( $row ) );
	return (int) db()->lastInsertId();
}

function nw_update( $table, $id, array $row ) {
	if ( ! $row ) {
		return;
	}
	$set = implode( ',', array_map( function ( $c ) { return $c . ' = ?'; }, array_keys( $row ) ) );
	q( 'UPDATE ' . $table . ' SET ' . $set . ' WHERE id = ?', array_merge( array_values( $row ), array( $id ) ) );
}

function nw_migrate( PDO $pdo ) {
	$v = (int) $pdo->query( 'PRAGMA user_version' )->fetchColumn();
	if ( $v < 1 ) {
		$pdo->exec(
			"CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT);
			CREATE TABLE customers (
				id INTEGER PRIMARY KEY, number TEXT UNIQUE, company TEXT DEFAULT '', company2 TEXT DEFAULT '',
				salutation TEXT DEFAULT '', person TEXT DEFAULT '', street TEXT DEFAULT '', zip TEXT DEFAULT '',
				city TEXT DEFAULT '', country TEXT DEFAULT '', vat_id TEXT DEFAULT '', email TEXT DEFAULT '',
				email_cc TEXT DEFAULT '', phone TEXT DEFAULT '', website TEXT DEFAULT '', note TEXT DEFAULT '',
				payment_days INTEGER, archived INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT);
			CREATE TABLE products (
				id INTEGER PRIMARY KEY, sku TEXT, name TEXT NOT NULL, description TEXT DEFAULT '', unit TEXT DEFAULT '',
				price REAL DEFAULT 0, tax_rate REAL DEFAULT 0, category TEXT DEFAULT '', recurring INTEGER DEFAULT 0,
				archived INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT);
			CREATE TABLE invoices (
				id INTEGER PRIMARY KEY, number TEXT UNIQUE, kind TEXT DEFAULT 'invoice', ref_id INTEGER,
				customer_id INTEGER REFERENCES customers(id), recipient TEXT, status TEXT DEFAULT 'draft',
				invoice_date TEXT, service_date TEXT, period_from TEXT, period_to TEXT, payment_days INTEGER DEFAULT 14,
				due_date TEXT, subject TEXT DEFAULT '', greeting TEXT DEFAULT '', intro TEXT DEFAULT '', outro TEXT DEFAULT '',
				net REAL DEFAULT 0, tax REAL DEFAULT 0, gross REAL DEFAULT 0, small_business INTEGER DEFAULT 1,
				paid_at TEXT, paid_amount REAL, note TEXT DEFAULT '', recurring_id INTEGER, source TEXT DEFAULT 'app',
				original_file TEXT, sent_at TEXT, reminder_level INTEGER DEFAULT 0, reminded_at TEXT,
				token TEXT, created_at TEXT, updated_at TEXT, issued_at TEXT);
			CREATE INDEX inv_customer ON invoices(customer_id);
			CREATE INDEX inv_date ON invoices(invoice_date);
			CREATE TABLE invoice_items (
				id INTEGER PRIMARY KEY, invoice_id INTEGER REFERENCES invoices(id) ON DELETE CASCADE, pos INTEGER,
				product_id INTEGER, sku TEXT DEFAULT '', name TEXT DEFAULT '', description TEXT DEFAULT '',
				qty REAL DEFAULT 1, unit TEXT DEFAULT '', price REAL DEFAULT 0, discount REAL DEFAULT 0,
				tax_rate REAL DEFAULT 0, net REAL DEFAULT 0);
			CREATE INDEX item_invoice ON invoice_items(invoice_id);
			CREATE TABLE recurring (
				id INTEGER PRIMARY KEY, customer_id INTEGER REFERENCES customers(id), title TEXT DEFAULT '',
				interval_months INTEGER DEFAULT 12, next_date TEXT, end_date TEXT, mode TEXT DEFAULT 'send',
				email TEXT DEFAULT '', items TEXT DEFAULT '[]', intro TEXT DEFAULT '', note TEXT DEFAULT '',
				active INTEGER DEFAULT 1, last_invoice_id INTEGER, last_run TEXT, created_at TEXT, updated_at TEXT);
			CREATE TABLE expenses (
				id INTEGER PRIMARY KEY, date TEXT, vendor TEXT DEFAULT '', description TEXT DEFAULT '',
				category TEXT DEFAULT '', amount REAL DEFAULT 0, file TEXT, created_at TEXT, updated_at TEXT);
			CREATE TABLE mail_log (
				id INTEGER PRIMARY KEY, invoice_id INTEGER, kind TEXT, to_addr TEXT, subject TEXT, ok INTEGER,
				error TEXT, created_at TEXT);
			CREATE TABLE activity (
				id INTEGER PRIMARY KEY, invoice_id INTEGER, customer_id INTEGER, text TEXT, created_at TEXT);
			CREATE TABLE logins (ip TEXT, ok INTEGER, created_at TEXT);
			PRAGMA user_version = 1;"
		);
	}
	if ( $v < 2 ) {
		// Angebote: gleiche Tabelle, kind = 'offer'
		$pdo->exec(
			"ALTER TABLE invoices ADD COLUMN valid_until TEXT;
			ALTER TABLE invoices ADD COLUMN offer_state TEXT;
			ALTER TABLE invoices ADD COLUMN converted_id INTEGER;
			CREATE INDEX IF NOT EXISTS inv_kind ON invoices(kind);
			PRAGMA user_version = 2;"
		);
	}
	if ( $v < 3 ) {
		// Schrumpfen: auto · on · off
		$pdo->exec( "ALTER TABLE invoices ADD COLUMN compact TEXT DEFAULT 'auto'; PRAGMA user_version = 3;" );
	}
}

/* ==================================================================== Einstellungen */

function nw_settings_defaults() {
	return array(
		'company'            => '',
		'tagline'            => '',
		'owner'              => '',
		'street'             => '',
		'zip'                => '',
		'city'               => '',
		'country'            => 'Österreich',
		'phone'              => '',
		'email'              => '',
		'web'                => '',
		'bank'               => '',
		'iban'               => '',
		'bic'                => '',
		'bank_owner'         => '',
		'vat_id'             => '',
		'tax_number'         => '',
		'small_business'     => '1',
		'small_business_text'=> 'Umsatzsteuerbefreit – Kleinunternehmer gemäß § 6 Abs. 1 Z 27 UStG.',
		'revenue_limit'      => '55000',
		'default_tax'        => '20',
		'payment_days'       => '14',
		'next_number'        => '10001',
		'next_customer'      => '10001',
		'next_sku'           => '10001',
		'next_offer'         => '1001',
		'offer_days'         => '30',
		'offer_intro'        => 'vielen Dank für Ihre Anfrage. Gerne biete ich Ihnen die folgenden Leistungen an:',
		'offer_outro'        => 'Ich freue mich auf Ihre Zusage. Bei Fragen bin ich gerne für Sie da.',
		'offer_subject'      => 'Angebot {NUMMER} – {FIRMA}',
		'offer_body'         => "{ANREDE}\n\nvielen Dank für Ihre Anfrage. Im Anhang finden Sie mein Angebot {NUMMER} über {BETRAG}. Es ist gültig bis {GUELTIG}.\n\nIch freue mich auf Ihre Rückmeldung.\n\nMit freundlichen Grüßen\n{INHABER}",
		'greeting'           => 'Sehr geehrte Damen und Herren,',
		'intro'              => 'wir bedanken uns für den Auftrag und erlauben uns, die nachfolgenden Leistungen in Rechnung zu stellen.',
		'outro'              => '',
		'footer_note'        => '',
		'accent'             => '#16171a',
		'ui_theme'           => 'schlicht',
		'ui_accent_pdf'      => '0',
		'smtp_host'          => '',
		'smtp_port'          => '465',
		'smtp_secure'        => 'ssl',
		'smtp_user'          => '',
		'smtp_pass_enc'      => '',
		'smtp_from'          => '',
		'smtp_from_name'     => '',
		'smtp_bcc'           => '',
		'pay_box'            => 'qr',
		'mail_subject'       => 'Rechnung {NUMMER} – {FIRMA}',
		'mail_body'          => "{ANREDE}\n\nim Anhang finden Sie die Rechnung {NUMMER} vom {DATUM} über {BETRAG}.\n{ZAHLUNG}\n\nVielen Dank für die gute Zusammenarbeit!\n\nMit freundlichen Grüßen\n{INHABER}",
		'remind_subject'     => 'Zahlungserinnerung zu Rechnung {NUMMER}',
		'remind_body'        => "{ANREDE}\n\nsicher ist es Ihrer Aufmerksamkeit entgangen: Die Rechnung {NUMMER} vom {DATUM} über {OFFEN} ist seit {FAELLIG} fällig. Die Rechnung liegt nochmals bei.\n\nBitte überweisen Sie den Betrag in den nächsten Tagen. Sollte die Zahlung bereits unterwegs sein, betrachten Sie dieses Schreiben bitte als gegenstandslos.\n\nMit freundlichen Grüßen\n{INHABER}",
		'cron_last'          => '',
		'update_cache'       => '',
	);
}

/** Designs der Oberfläche: id => Akzentfarbe. */
function nw_ui_themes() {
	return array(
		'schlicht' => '#16171a',
		'modern'   => '#4f46e5',
		'blau'     => '#1f4fd1',
		'tanne'    => '#1d6b4f',
		'bordeaux' => '#8e1f2a',
		'kupfer'   => '#b4531f',
		'petrol'   => '#0f6b78',
	);
}

function nw_ui_theme() {
	$t = nw_setting( 'ui_theme' );
	return isset( nw_ui_themes()[ $t ] ) ? $t : 'schlicht';
}

function nw_settings( $refresh = false ) {
	static $s = null;
	if ( null === $s || $refresh ) {
		$s = nw_settings_defaults();
		foreach ( q_all( 'SELECT key, value FROM settings' ) as $r ) {
			$s[ $r['key'] ] = $r['value'];
		}
	}
	return $s;
}

function nw_setting( $key ) {
	$s = nw_settings();
	return $s[ $key ] ?? '';
}

function nw_set_setting( $key, $value ) {
	q( 'INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', array( $key, (string) $value ) );
	nw_settings( true );
}

/** Geheime Werte, die nie an den Browser gehen. */
function nw_private_settings() {
	return array( 'password_hash', 'session_secret', 'cron_key', 'smtp_pass_enc' );
}

function nw_cron_key() {
	$k = (string) nw_config( 'cron_key' );
	if ( '' !== $k ) {
		return $k;
	}
	$k = nw_setting( 'cron_key' );
	if ( '' === $k ) {
		$k = bin2hex( random_bytes( 16 ) );
		nw_set_setting( 'cron_key', $k );
	}
	return $k;
}

/* ==================================================================== Anmeldung */

function nw_session() {
	if ( PHP_SESSION_ACTIVE === session_status() ) {
		return;
	}
	$https = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) || ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) === 'https';
	$path  = rtrim( dirname( $_SERVER['SCRIPT_NAME'] ?? '/' ), '/' ) . '/';
	session_name( 'nw_rechnungen' );
	session_set_cookie_params(
		array(
			'lifetime' => 60 * 60 * 24 * 30,
			'path'     => $path,
			'secure'   => $https,
			'httponly' => true,
			'samesite' => 'Strict',
		)
	);
	ini_set( 'session.use_strict_mode', '1' );
	ini_set( 'session.gc_maxlifetime', (string) ( 60 * 60 * 24 * 30 ) );
	$dir = nw_data_dir( 'sessions' );
	if ( is_writable( $dir ) ) {
		session_save_path( $dir );
	}
	session_start();
	if ( empty( $_SESSION['csrf'] ) ) {
		$_SESSION['csrf'] = bin2hex( random_bytes( 16 ) );
	}
}

function nw_logged_in() {
	nw_session();
	return ! empty( $_SESSION['user'] ) && ( $_SESSION['pw'] ?? '' ) === substr( nw_setting( 'password_hash' ), -12 );
}

function nw_has_password() {
	return '' !== nw_setting( 'password_hash' );
}

function nw_client_ip() {
	return $_SERVER['REMOTE_ADDR'] ?? '';
}

/** Max. 8 Fehlversuche je IP in 15 Minuten. */
function nw_login_blocked() {
	$since = gmdate( 'Y-m-d H:i:s', time() - 900 );
	return (int) q_val( 'SELECT COUNT(*) FROM logins WHERE ip = ? AND ok = 0 AND created_at > ?', array( nw_client_ip(), $since ) ) >= 8;
}

function nw_login( $password ) {
	if ( nw_login_blocked() ) {
		return 'Zu viele Fehlversuche – bitte in 15 Minuten nochmals probieren.';
	}
	$ok = password_verify( (string) $password, nw_setting( 'password_hash' ) );
	q( 'INSERT INTO logins (ip, ok, created_at) VALUES (?, ?, ?)', array( nw_client_ip(), $ok ? 1 : 0, gmdate( 'Y-m-d H:i:s' ) ) );
	q( 'DELETE FROM logins WHERE created_at < ?', array( gmdate( 'Y-m-d H:i:s', time() - 86400 * 30 ) ) );
	if ( ! $ok ) {
		usleep( 400000 );
		return 'Passwort falsch.';
	}
	session_regenerate_id( true );
	$_SESSION['user'] = 'admin';
	$_SESSION['pw']   = substr( nw_setting( 'password_hash' ), -12 ); // Passwortwechsel meldet andere Geräte ab
	return true;
}

function nw_set_password( $password ) {
	if ( mb_strlen( (string) $password ) < 8 ) {
		return 'Das Passwort braucht mindestens 8 Zeichen.';
	}
	nw_set_setting( 'password_hash', password_hash( $password, PASSWORD_DEFAULT ) );
	return true;
}

/* ==================================================================== Hilfsfunktionen */

function nw_now() {
	return date( 'Y-m-d H:i:s' );
}

function nw_today() {
	return date( 'Y-m-d' );
}

function nw_add_days( $date, $days ) {
	return date( 'Y-m-d', strtotime( $date . ' ' . ( $days >= 0 ? '+' : '' ) . (int) $days . ' days' ) );
}

/** Monate addieren, Tag bleibt erhalten bzw. wird auf das Monatsende gekürzt (31.1. + 1 Monat = 28./29.2.). */
function nw_add_months( $date, $months ) {
	list( $y, $m, $d ) = array_map( 'intval', explode( '-', $date ) );
	$m += $months;
	$y += (int) floor( ( $m - 1 ) / 12 );
	$m  = ( ( $m - 1 ) % 12 + 12 ) % 12 + 1;
	$d  = min( $d, (int) date( 't', mktime( 0, 0, 0, $m, 1, $y ) ) );
	return sprintf( '%04d-%02d-%02d', $y, $m, $d );
}

function nw_date( $iso ) {
	if ( ! $iso ) {
		return '';
	}
	$t = strtotime( substr( $iso, 0, 10 ) );
	return $t ? date( 'd.m.Y', $t ) : '';
}

function nw_money( $v, $symbol = true ) {
	$s = number_format( (float) $v, 2, ',', '.' );
	return $symbol ? $s . ' €' : $s;
}

function nw_qty( $v ) {
	$v = (float) $v;
	if ( abs( $v - round( $v ) ) < 0.00001 ) {
		return number_format( $v, 0, ',', '.' );
	}
	return rtrim( rtrim( number_format( $v, 3, ',', '.' ), '0' ), ',' );
}

function nw_percent( $v ) {
	return nw_qty( $v ) . ' %';
}

function nw_month_name( $m ) {
	$n = array( 1 => 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember' );
	return $n[ (int) $m ];
}

function nw_slug( $s ) {
	$s = strtr( (string) $s, array( 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss' ) );
	$t = @iconv( 'UTF-8', 'ASCII//TRANSLIT', $s );
	$s = false === $t ? $s : $t;
	return trim( preg_replace( '/[^A-Za-z0-9._-]+/', '-', $s ), '-' );
}

function nw_is_email( $s ) {
	return (bool) filter_var( trim( (string) $s ), FILTER_VALIDATE_EMAIL );
}

/** Mehrere Adressen, getrennt durch Komma/Strichpunkt. */
function nw_emails( $s ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/[,;\s]+/', (string) $s ) ), 'nw_is_email' ) );
}

/**
 * Minimaler ZIP-Schreiber (Deflate), damit der PDF-Export auch ohne ZipArchive-Erweiterung geht.
 *
 * @param array $files Pfad im Archiv => Inhalt
 */
function nw_zip( array $files ) {
	$data = '';
	$dir  = '';
	$n    = 0;
	$t    = getdate();
	$dt   = ( ( $t['year'] - 1980 ) << 25 ) | ( $t['mon'] << 21 ) | ( $t['mday'] << 16 ) | ( $t['hours'] << 11 ) | ( $t['minutes'] << 5 ) | ( $t['seconds'] >> 1 );
	foreach ( $files as $name => $content ) {
		$crc    = crc32( $content );
		$zipped = gzdeflate( $content, 6 );
		$head   = pack( 'VvvvVVVVvv', 0x04034b50, 20, 0x0800, 8, $dt, $crc, strlen( $zipped ), strlen( $content ), strlen( $name ), 0 );
		$offset = strlen( $data );
		$data  .= $head . $name . $zipped;
		$dir   .= pack( 'VvvvvVVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 8, $dt, $crc, strlen( $zipped ), strlen( $content ), strlen( $name ), 0, 0, 0, 0, 32, $offset ) . $name;
		$n++;
	}
	return $data . $dir . pack( 'VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen( $dir ), strlen( $data ), 0 );
}

function nw_log( $text, $invoice_id = null, $customer_id = null ) {
	nw_insert( 'activity', array( 'invoice_id' => $invoice_id, 'customer_id' => $customer_id, 'text' => $text, 'created_at' => nw_now() ) );
}

require_once __DIR__ . '/pdf.php';
require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/model.php';
require_once __DIR__ . '/document.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/updater.php';
require_once __DIR__ . '/logo.php';
