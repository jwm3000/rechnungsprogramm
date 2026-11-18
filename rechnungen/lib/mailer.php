<?php
/**
 * E-Mail-Versand über SMTP (ohne Bibliotheken): TLS mit Zertifikatsprüfung, AUTH PLAIN/LOGIN,
 * Text + HTML, PDF im Anhang. Zugangsdaten nur aus config.php.
 */
defined( 'NW_APP' ) || exit;

class NW_SMTP {

	private $sock;
	private $log = array();

	/**
	 * @param array $m from, from_name, to[], cc[], bcc[], reply_to, subject, text, html, attachments[{name, type, data}]
	 */
	/**
	 * @return array{message_id:string, bytes:int, reply:string} Message-ID, Größe und Antwort des Servers („250 queued as …“)
	 */
	public static function send( array $cfg, array $m ) {
		$domain           = substr( strrchr( (string) $m['from'], '@' ), 1 ) ?: 'localhost';
		$m['message_id']  = $m['message_id'] ?? bin2hex( random_bytes( 12 ) ) . '@' . $domain;
		$data             = self::build( $m );
		$smtp             = new self();
		try {
			$smtp->connect( $cfg );
			$reply = $smtp->transmit( $cfg, $m, $data );
			$smtp->cmd( 'QUIT', array( 221 ) );
		} finally {
			if ( $smtp->sock ) {
				@fclose( $smtp->sock );
			}
		}
		return array( 'message_id' => $m['message_id'], 'bytes' => strlen( $data ), 'reply' => trim( (string) $reply ) );
	}

	private function connect( array $c ) {
		if ( '' === trim( (string) $c['host'] ) ) {
			throw new RuntimeException( 'Kein SMTP-Server eingerichtet (config.php).' );
		}
		$secure = strtolower( (string) $c['secure'] );
		$ctx    = stream_context_create(
			array(
				'ssl' => array(
					'verify_peer'       => ! isset( $c['verify'] ) || false !== $c['verify'],
					'verify_peer_name'  => ! isset( $c['verify'] ) || false !== $c['verify'],
					'allow_self_signed' => false,
					'peer_name'         => $c['host'],
					'SNI_enabled'       => true,
				),
			)
		);
		$target     = ( 'ssl' === $secure ? 'ssl://' : 'tcp://' ) . $c['host'] . ':' . (int) $c['port'];
		$this->sock = @stream_socket_client( $target, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx );
		if ( ! $this->sock ) {
			$last = error_get_last()['message'] ?? '';
			if ( '' === trim( (string) $errstr ) && preg_match( '/certificate|SSL|crypto/i', $last ) ) {
				$errstr = 'TLS-Verbindung abgelehnt – das Zertifikat des Servers ist ungültig oder passt nicht zum Servernamen. Port und Verschlüsselung prüfen (465 = SSL/TLS, 587 = STARTTLS).';
			} elseif ( '' === trim( (string) $errstr ) ) {
				$errstr = $last ? $last : 'keine Antwort – Servername, Port und Verschlüsselung prüfen.';
			}
			throw new RuntimeException( 'Verbindung zu ' . $c['host'] . ':' . $c['port'] . ' fehlgeschlagen: ' . $errstr );
		}
		stream_set_timeout( $this->sock, 30 );
		$this->expect( array( 220 ) );
		$host = gethostname() ?: 'localhost';
		$this->cmd( 'EHLO ' . $host, array( 250 ) );
		if ( 'tls' === $secure ) {
			$this->cmd( 'STARTTLS', array( 220 ) );
			if ( ! stream_socket_enable_crypto( $this->sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT' ) ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0 ) ) ) {
				throw new RuntimeException( 'STARTTLS fehlgeschlagen.' );
			}
			$this->cmd( 'EHLO ' . $host, array( 250 ) );
		} elseif ( 'ssl' !== $secure ) {
			throw new RuntimeException( 'Unverschlüsselter Versand ist abgeschaltet – bitte secure = ssl oder tls.' );
		}
		if ( '' !== (string) $c['user'] ) {
			$this->cmd( 'AUTH PLAIN ' . base64_encode( "\0" . $c['user'] . "\0" . $c['pass'] ), array( 235 ), 'AUTH PLAIN ***' );
		}
	}

	private function transmit( array $c, array $m, $data ) {
		$from = $m['from'];
		$this->cmd( 'MAIL FROM:<' . $from . '>', array( 250 ) );
		foreach ( array_unique( array_merge( $m['to'], $m['cc'] ?? array(), $m['bcc'] ?? array() ) ) as $rcpt ) {
			$this->cmd( 'RCPT TO:<' . $rcpt . '>', array( 250, 251 ) );
		}
		$this->cmd( 'DATA', array( 354 ) );
		$data = preg_replace( '/^\./m', '..', $data ); // Dot-Stuffing
		fwrite( $this->sock, $data . "\r\n.\r\n" );
		return $this->expect( array( 250 ) );
	}

	/**
	 * Kopfzeilen-Text: Zeilenumbrüche entfernen (kein Einschleusen weiterer Kopfzeilen) und Umlaute
	 * nach RFC 2047 kodieren – in Stücken von höchstens 75 Zeichen, ohne ein Zeichen zu zerteilen.
	 */
	public static function header_encode( $s ) {
		$s = trim( preg_replace( '/[\r\n\t]+/', ' ', (string) $s ) );
		if ( ! preg_match( '/[^\x20-\x7e]/', $s ) ) {
			return $s;
		}
		$parts = array();
		$chunk = '';
		foreach ( preg_split( '//u', $s, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
			if ( strlen( $chunk . $ch ) > 45 ) { // 45 Bytes → 60 Zeichen Base64 + 12 Zeichen Rahmen
				$parts[] = $chunk;
				$chunk   = '';
			}
			$chunk .= $ch;
		}
		$parts[] = $chunk;
		return implode( "\r\n ", array_map( function ( $p ) { return '=?UTF-8?B?' . base64_encode( $p ) . '?='; }, $parts ) );
	}

	public static function address( $email, $name = '' ) {
		return '' !== $name ? self::header_encode( $name ) . ' <' . $email . '>' : $email;
	}

	public static function build( array $m ) {
		$domain = substr( strrchr( $m['from'], '@' ), 1 ) ?: 'localhost';
		$mixed  = 'mix_' . bin2hex( random_bytes( 8 ) );
		$alt    = 'alt_' . bin2hex( random_bytes( 8 ) );
		$h      = array(
			'Date: ' . date( 'r' ),
			'From: ' . self::address( $m['from'], $m['from_name'] ?? '' ),
			'To: ' . implode( ', ', $m['to'] ),
		);
		if ( ! empty( $m['cc'] ) ) {
			$h[] = 'Cc: ' . implode( ', ', $m['cc'] );
		}
		if ( ! empty( $m['reply_to'] ) ) {
			$h[] = 'Reply-To: ' . preg_replace( '/[\r\n]+/', '', $m['reply_to'] );
		}
		$h[] = 'Subject: ' . self::header_encode( $m['subject'] );
		$h[] = 'Message-ID: <' . ( $m['message_id'] ?? bin2hex( random_bytes( 12 ) ) . '@' . $domain ) . '>';
		$h[] = 'MIME-Version: 1.0';
		$h[] = 'Content-Type: multipart/mixed; boundary="' . $mixed . '"';

		$body  = '--' . $mixed . "\r\n";
		$body .= 'Content-Type: multipart/alternative; boundary="' . $alt . "\"\r\n\r\n";
		$body .= '--' . $alt . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
		$body .= chunk_split( base64_encode( $m['text'] ) );
		if ( ! empty( $m['html'] ) && ! empty( $m['inline'] ) ) {
			// HTML mit eingebetteten Bildern (cid:) – werden mitgeschickt, nicht aus dem Netz geladen
			$rel   = 'rel_' . bin2hex( random_bytes( 8 ) );
			$body .= '--' . $alt . "\r\nContent-Type: multipart/related; boundary=\"" . $rel . "\"\r\n\r\n";
			$body .= '--' . $rel . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
			$body .= chunk_split( base64_encode( $m['html'] ) );
			foreach ( $m['inline'] as $i ) {
				$name  = str_replace( '"', '', $i['name'] );
				$body .= '--' . $rel . "\r\nContent-Type: " . $i['type'] . '; name="' . $name . "\"\r\nContent-Transfer-Encoding: base64\r\n";
				$body .= 'Content-ID: <' . $i['cid'] . ">\r\nContent-Disposition: inline; filename=\"" . $name . "\"\r\n\r\n";
				$body .= chunk_split( base64_encode( $i['data'] ) );
			}
			$body .= '--' . $rel . "--\r\n";
		} elseif ( ! empty( $m['html'] ) ) {
			$body .= '--' . $alt . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
			$body .= chunk_split( base64_encode( $m['html'] ) );
		}
		$body .= '--' . $alt . "--\r\n";
		foreach ( $m['attachments'] ?? array() as $a ) {
			$name  = str_replace( '"', '', $a['name'] );
			$body .= '--' . $mixed . "\r\n";
			$body .= 'Content-Type: ' . $a['type'] . '; name="' . $name . "\"\r\n";
			$body .= "Content-Transfer-Encoding: base64\r\n";
			$body .= 'Content-Disposition: attachment; filename="' . $name . "\"\r\n\r\n";
			$body .= chunk_split( base64_encode( $a['data'] ) );
		}
		$body .= '--' . $mixed . '--';
		return implode( "\r\n", $h ) . "\r\n\r\n" . $body;
	}

	private function cmd( $line, array $codes, $log = null ) {
		$this->log[] = '> ' . ( $log ? $log : $line );
		fwrite( $this->sock, $line . "\r\n" );
		return $this->expect( $codes );
	}

	private function expect( array $codes ) {
		$resp = '';
		while ( ( $l = fgets( $this->sock, 1024 ) ) !== false ) {
			$resp .= $l;
			if ( strlen( $l ) < 4 || ' ' === $l[3] ) {
				break;
			}
		}
		$this->log[] = '< ' . trim( $resp );
		$code        = (int) substr( $resp, 0, 3 );
		if ( ! in_array( $code, $codes, true ) ) {
			throw new RuntimeException( 'SMTP: ' . ( '' !== trim( $resp ) ? trim( $resp ) : 'keine Antwort' ) );
		}
		return $resp;
	}
}

/* ==================================================================== Zugangsdaten */

/** Geheimer Schlüssel für das SMTP-Passwort – liegt als Datei im Datenordner, nicht in der Datenbank. */
function nw_secret_key() {
	$f = nw_data_dir() . '/.secret';
	if ( ! is_file( $f ) ) {
		file_put_contents( $f, bin2hex( random_bytes( 32 ) ) );
		@chmod( $f, 0600 );
	}
	return hex2bin( trim( (string) file_get_contents( $f ) ) );
}

function nw_encrypt( $plain ) {
	$iv  = random_bytes( 12 );
	$ct  = openssl_encrypt( (string) $plain, 'aes-256-gcm', nw_secret_key(), OPENSSL_RAW_DATA, $iv, $tag );
	return base64_encode( $iv . $tag . $ct );
}

function nw_decrypt( $enc ) {
	$raw = base64_decode( (string) $enc, true );
	if ( ! $raw || strlen( $raw ) < 29 ) {
		return '';
	}
	$p = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', nw_secret_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
	return false === $p ? '' : $p;
}

/**
 * SMTP-Zugang: aus config.php (falls dort ein Server steht – hat Vorrang) oder aus den Einstellungen.
 *
 * @return array host, port, secure, user, pass, from, from_name, bcc, source (config|app)
 */
function nw_smtp_config() {
	$c = nw_config( 'smtp' );
	if ( '' !== trim( (string) $c['host'] ) ) {
		return $c + array( 'source' => 'config' );
	}
	$s = nw_settings();
	return array(
		'host'      => (string) ( $s['smtp_host'] ?? '' ),
		'port'      => (int) ( $s['smtp_port'] ?? 465 ) ?: 465,
		'secure'    => in_array( $s['smtp_secure'] ?? 'ssl', array( 'ssl', 'tls' ), true ) ? $s['smtp_secure'] : 'ssl',
		'user'      => (string) ( $s['smtp_user'] ?? '' ),
		'pass'      => nw_decrypt( $s['smtp_pass_enc'] ?? '' ),
		'from'      => (string) ( $s['smtp_from'] ?? '' ),
		'from_name' => (string) ( $s['smtp_from_name'] ?? '' ),
		'bcc'       => (string) ( $s['smtp_bcc'] ?? '' ),
		'reply_to'  => (string) ( $s['smtp_reply_to'] ?? '' ),
		'source'    => 'app',
	);
}

/** Antwortadresse: aus dem E-Mail-Zugang, sonst die E-Mail der Firmendaten (wenn sie vom Absender abweicht). */
function nw_reply_to( array $cfg, $from ) {
	$r = trim( (string) ( $cfg['reply_to'] ?? '' ) );
	if ( nw_is_email( $r ) ) {
		return $r;
	}
	$e = nw_setting( 'email' );
	return nw_is_email( $e ) && strtolower( $e ) !== strtolower( (string) $from ) ? $e : '';
}

/* ==================================================================== Versand mit Protokoll */

/**
 * Jede Mail geht hier durch: senden und vollständig protokollieren (auch Fehlschläge).
 *
 * @param array $m    Nachricht für NW_SMTP::send
 * @param array $meta kind (invoice|reminder|offer|test), invoice_id, number
 * @return array{ok:bool, error:string, message_id:string}
 */
function nw_mail_send( array $cfg, array $m, array $meta ) {
	$error = '';
	$res   = array( 'message_id' => '', 'bytes' => 0, 'reply' => '' );
	try {
		if ( '' === trim( (string) $cfg['host'] ) ) {
			throw new RuntimeException( 'E-Mail-Versand ist noch nicht eingerichtet (Einstellungen → E-Mail).' );
		}
		$res = NW_SMTP::send( $cfg, $m );
	} catch ( Throwable $e ) {
		$error = $e->getMessage();
	}
	$att = array_map( function ( $a ) { return $a['name'] . ' (' . round( strlen( $a['data'] ) / 1024 ) . ' KB)'; }, $m['attachments'] ?? array() );
	nw_insert(
		'mail_log',
		array(
			'invoice_id'   => $meta['invoice_id'] ?? null,
			'number'       => $meta['number'] ?? null,
			'kind'         => $meta['kind'] ?? 'invoice',
			'to_addr'      => implode( ', ', $m['to'] ),
			'cc'           => implode( ', ', $m['cc'] ?? array() ),
			'bcc'          => implode( ', ', $m['bcc'] ?? array() ),
			'from_addr'    => '' !== trim( (string) ( $m['from_name'] ?? '' ) ) ? $m['from_name'] . ' <' . $m['from'] . '>' : $m['from'],
			'reply_to'     => (string) ( $m['reply_to'] ?? '' ),
			'subject'      => (string) $m['subject'],
			'attachment'   => implode( ', ', $att ),
			'bytes'        => (int) $res['bytes'],
			'message_id'   => (string) $res['message_id'],
			'server_reply' => mb_substr( (string) $res['reply'], 0, 300 ),
			'smtp_host'    => trim( (string) $cfg['host'] ) . ( $cfg['host'] ? ':' . (int) $cfg['port'] : '' ),
			'source'       => defined( 'NW_CRON' ) ? 'automatisch' : 'von Hand',
			'ok'           => '' === $error ? 1 : 0,
			'error'        => $error,
			'created_at'   => nw_now(),
		)
	);
	return array( 'ok' => '' === $error, 'error' => $error, 'message_id' => (string) $res['message_id'] );
}

/** Protokoll lesen (neueste zuerst). */
function nw_mail_log( array $f = array() ) {
	$where = array( '1=1' );
	$args  = array();
	if ( ! empty( $f['errors'] ) ) {
		$where[] = 'ok = 0';
	}
	if ( '' !== trim( (string) ( $f['q'] ?? '' ) ) ) {
		$where[] = '(to_addr LIKE ? OR cc LIKE ? OR subject LIKE ? OR number LIKE ?)';
		$like    = '%' . trim( $f['q'] ) . '%';
		array_push( $args, $like, $like, $like, $like );
	}
	$limit = max( 1, min( 500, (int) ( $f['limit'] ?? 100 ) ) );
	$rows  = q_all( 'SELECT * FROM mail_log WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT ' . ( $limit + 1 ) . ' OFFSET ' . max( 0, (int) ( $f['offset'] ?? 0 ) ), $args );
	return array(
		'rows'  => array_slice( $rows, 0, $limit ),
		'more'  => count( $rows ) > $limit,
		'stats' => q_row( "SELECT COUNT(*) AS total, SUM(ok = 0) AS failed, MAX(created_at) AS last FROM mail_log" ),
	);
}

/* ==================================================================== Rechnungsmails */

function nw_mail_configured() {
	$c = nw_smtp_config();
	return '' !== trim( (string) $c['host'] ) && nw_is_email( $c['from'] ?: $c['user'] );
}

function nw_mail_vars( array $inv ) {
	$s = nw_settings();
	return array(
		'{ANREDE}'  => $inv['greeting'] ? $inv['greeting'] : $s['greeting'],
		'{NUMMER}'  => (string) $inv['number'],
		'{DATUM}'   => nw_date( $inv['invoice_date'] ),
		'{BETRAG}'  => nw_money( $inv['gross'] ),
		'{OFFEN}'   => nw_money( $inv['open'] ? $inv['open'] : $inv['gross'] ),
		'{FAELLIG}' => nw_date( $inv['due_date'] ),
		'{ZAHLUNG}' => $inv['payment_days'] > 0 ? 'Bitte überweisen Sie den Betrag bis ' . nw_date( $inv['due_date'] ) . ' auf das angegebene Konto.' : 'Bitte überweisen Sie den Betrag auf das angegebene Konto.',
		'{ZEITRAUM}' => $inv['period_from'] && $inv['period_to'] ? nw_date( $inv['period_from'] ) . ' – ' . nw_date( $inv['period_to'] ) : '',
		'{FIRMA}'   => $s['company'],
		'{INHABER}' => $s['owner'],
		'{KUNDE}'   => (string) ( $inv['recipient']['name'] ?? '' ),
		'{GUELTIG}' => nw_date( $inv['valid_until'] ?? '' ),
		'{MONAT}'   => ! empty( $inv['period_from'] ) ? nw_month_name( (int) substr( $inv['period_from'], 5, 2 ) ) . ' ' . substr( $inv['period_from'], 0, 4 ) : '',
		'{JAHR}'    => substr( (string) ( ! empty( $inv['period_from'] ) ? $inv['period_from'] : $inv['invoice_date'] ), 0, 4 ),
	);
}

/**
 * Betreff und Text einer Mail. Rechnungen aus einer Dauerrechnung mit eigenem E-Mail-Text nehmen diesen
 * (Zahlungserinnerungen immer den Standard). $custom: null = aus der Dauerrechnung, false = Standard,
 * array(subject, body) = vorgegeben (Vorschau ungespeicherter Werte).
 */
function nw_mail_compose( array $inv, $type = 'invoice', $custom = null ) {
	$s = nw_settings();
	$v = nw_mail_vars( $inv );
	if ( 'offer' === $inv['kind'] ) {
		return array( 'subject' => strtr( $s['offer_subject'], $v ), 'body' => strtr( $s['offer_body'], $v ) );
	}
	if ( null === $custom && 'reminder' !== $type && ! empty( $inv['recurring_id'] ) ) {
		$rc     = q_row( 'SELECT mail_custom, mail_subject AS subject, mail_body AS body FROM recurring WHERE id = ?', array( (int) $inv['recurring_id'] ) );
		$custom = $rc && $rc['mail_custom'] ? $rc : false;
	}
	if ( is_array( $custom ) && 'reminder' !== $type && '' !== trim( (string) $custom['body'] ) ) {
		return array(
			'subject' => strtr( '' !== trim( (string) $custom['subject'] ) ? $custom['subject'] : $s['mail_subject'], $v ),
			'body'    => strtr( $custom['body'], $v ),
		);
	}
	return array(
		'subject' => strtr( 'reminder' === $type ? $s['remind_subject'] : $s['mail_subject'], $v ),
		'body'    => strtr( 'reminder' === $type ? $s['remind_body'] : $s['mail_body'], $v ),
	);
}

/** E-Mail-Vorlagen: Standard (HTML mit Firmenname), Schlicht (nur Text), Mit Logo (HTML mit eingebettetem Logo). */
function nw_mail_templates() {
	return array( 'standard' => 'Standard', 'text' => 'Schlicht', 'logo' => 'Mit Logo' );
}

function nw_mail_template() {
	$t = nw_setting( 'mail_template' );
	return isset( nw_mail_templates()[ $t ] ) ? $t : 'standard';
}

/**
 * Mailtext → HTML-Teil und eingebettete Bilder je nach Vorlage.
 * $preview: Logo als data:-URL statt cid (für die Vorschau in den Einstellungen).
 *
 * @return array{html:string, inline:array}
 */
function nw_mail_parts( $text, $tpl = null, $preview = false ) {
	$tpl = $tpl ?? nw_mail_template();
	if ( 'text' === $tpl ) {
		return array( 'html' => '', 'inline' => array() );
	}
	$logo = 'logo' === $tpl ? nw_mail_logo() : null;
	if ( ! $logo ) {
		return array( 'html' => nw_mail_html( $text ), 'inline' => array() );
	}
	$cid = 'logo-' . bin2hex( random_bytes( 6 ) ) . '@rechnungen';
	$src = $preview ? 'data:' . $logo['type'] . ';base64,' . base64_encode( $logo['data'] ) : 'cid:' . $cid;
	return array(
		'html'   => nw_mail_html( $text, $src, $logo['w'], $logo['h'] ),
		'inline' => $preview ? array() : array( array( 'cid' => $cid, 'name' => 'logo.' . $logo['ext'], 'type' => $logo['type'], 'data' => $logo['data'] ) ),
	);
}

/** HTML-Fassung: Karte mit Firmenname als Kopf – oder mit Logo, wenn $logo_src gesetzt ist. */
function nw_mail_html( $text, $logo_src = '', $w = 0, $h = 0 ) {
	$s      = nw_settings();
	$accent = preg_match( '/^#[0-9a-f]{6}$/i', $s['accent'] ) ? $s['accent'] : '#16171a';
	$paras  = '';
	foreach ( preg_split( "/\n{2,}/", trim( $text ) ) as $p ) {
		$paras .= '<p style="margin:0 0 14px">' . nl2br( htmlspecialchars( $p, ENT_QUOTES, 'UTF-8' ) ) . '</p>';
	}
	$foot = htmlspecialchars( implode( ' · ', array_filter( array( $s['company'], $s['street'], trim( $s['zip'] . ' ' . $s['city'] ), $s['web'] ) ) ), ENT_QUOTES, 'UTF-8' );
	return '<!doctype html><html lang="de"><body style="margin:0;background:#f4f5f7;font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#16171a">'
		. '<div style="max-width:560px;margin:0 auto;padding:28px 16px">'
		. '<div style="background:#fff;border-radius:10px;border-top:4px solid ' . $accent . ';padding:28px 28px 14px;font-size:15px;line-height:1.55">'
		. ( '' !== $logo_src
			? '<div style="margin-bottom:24px">' . nw_mail_logo_img( $logo_src, $w, $h, $s['company'] ) . '</div>'
			: '<div style="font-size:20px;letter-spacing:-.02em;margin-bottom:22px">[ ' . htmlspecialchars( mb_strtolower( $s['company'] ), ENT_QUOTES, 'UTF-8' ) . ' ]</div>' )
		. $paras
		. '</div><div style="font-size:12px;color:#6b6e76;text-align:center;padding:14px">' . $foot . '</div></div></body></html>';
}

/** Logo-Bild mit fester Größe (Outlook braucht width/height): höchstens 56 px hoch und 260 px breit. */
function nw_mail_logo_img( $src, $w, $h, $alt ) {
	$h2 = 56;
	$w2 = $h ? (int) round( $w * $h2 / $h ) : 160;
	if ( $w2 > 260 ) {
		$h2 = (int) max( 1, round( $h2 * 260 / $w2 ) );
		$w2 = 260;
	}
	return '<img src="' . htmlspecialchars( $src, ENT_QUOTES, 'UTF-8' ) . '" width="' . $w2 . '" height="' . $h2 . '" alt="' . htmlspecialchars( (string) $alt, ENT_QUOTES, 'UTF-8' ) . '" style="display:block;border:0;width:' . $w2 . 'px;height:' . $h2 . 'px">';
}

/**
 * Rechnung (oder Zahlungserinnerung) mit PDF senden.
 *
 * @param array $o to, cc, subject, body
 * @return array{ok:bool, error:string, to:string}
 */
function nw_mail_invoice( $id, $type = 'invoice', array $o = array() ) {
	$inv = nw_invoice_get( $id );
	if ( 'draft' === $inv['status'] ) {
		return array( 'ok' => false, 'error' => 'Entwürfe können nicht versendet werden – bitte zuerst ausstellen.', 'to' => '' );
	}
	$cfg = nw_smtp_config();
	$to  = nw_emails( $o['to'] ?? ( $inv['recipient']['email'] ?? '' ) );
	$cc  = nw_emails( $o['cc'] ?? ( $inv['customer']['email_cc'] ?? '' ) );
	if ( ! $to ) {
		return array( 'ok' => false, 'error' => 'Keine gültige E-Mail-Adresse.', 'to' => '' );
	}
	$mail    = nw_mail_compose( $inv, $type );
	$subject = trim( (string) ( $o['subject'] ?? '' ) ) ?: $mail['subject'];
	$body    = trim( (string) ( $o['body'] ?? '' ) ) ?: $mail['body'];
	$from    = $cfg['from'] ?: $cfg['user'];
	$parts   = nw_mail_parts( $body );
	$res     = nw_mail_send(
		$cfg,
		array(
			'from'        => $from,
			'from_name'   => $cfg['from_name'] ?: nw_setting( 'company' ),
			'reply_to'    => nw_reply_to( $cfg, $from ),
			'to'          => $to,
			'cc'          => $cc,
			'bcc'         => nw_emails( $cfg['bcc'] ),
			'subject'     => $subject,
			'text'        => $body,
			'html'        => $parts['html'],
			'inline'      => $parts['inline'],
			'attachments' => array( array( 'name' => NW_Document::filename( $inv ), 'type' => 'application/pdf', 'data' => NW_Document::render( $inv ) ) ),
		),
		array( 'invoice_id' => $inv['id'], 'number' => $inv['number'], 'kind' => 'offer' === $inv['kind'] ? 'offer' : $type )
	);
	$ok    = $res['ok'];
	$error = $res['error'];
	if ( $ok ) {
		if ( 'reminder' === $type ) {
			nw_update( 'invoices', $inv['id'], array( 'reminder_level' => (int) $inv['reminder_level'] + 1, 'reminded_at' => nw_now() ) );
			nw_log( 'Zahlungserinnerung an ' . implode( ', ', $to ) . ' gesendet', $inv['id'], $inv['customer_id'] );
		} else {
			nw_update( 'invoices', $inv['id'], array( 'sent_at' => nw_now() ) );
			nw_log( 'Per E-Mail an ' . implode( ', ', $to ) . ' gesendet', $inv['id'], $inv['customer_id'] );
		}
	} else {
		nw_log( 'E-Mail fehlgeschlagen: ' . $error, $inv['id'], $inv['customer_id'] );
	}
	return array( 'ok' => $ok, 'error' => $error, 'to' => implode( ', ', $to ) );
}
