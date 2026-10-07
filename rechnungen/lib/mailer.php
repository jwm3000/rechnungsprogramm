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
	public static function send( array $cfg, array $m ) {
		$smtp = new self();
		try {
			$smtp->connect( $cfg );
			$smtp->transmit( $cfg, $m );
			$smtp->cmd( 'QUIT', array( 221 ) );
		} finally {
			if ( $smtp->sock ) {
				@fclose( $smtp->sock );
			}
		}
		return true;
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

	private function transmit( array $c, array $m ) {
		$from = $m['from'];
		$this->cmd( 'MAIL FROM:<' . $from . '>', array( 250 ) );
		foreach ( array_unique( array_merge( $m['to'], $m['cc'] ?? array(), $m['bcc'] ?? array() ) ) as $rcpt ) {
			$this->cmd( 'RCPT TO:<' . $rcpt . '>', array( 250, 251 ) );
		}
		$this->cmd( 'DATA', array( 354 ) );
		$data = self::build( $m );
		$data = preg_replace( '/^\./m', '..', $data ); // Dot-Stuffing
		fwrite( $this->sock, $data . "\r\n.\r\n" );
		$this->expect( array( 250 ) );
	}

	public static function header_encode( $s ) {
		return preg_match( '/[^\x20-\x7e]/', $s ) ? '=?UTF-8?B?' . base64_encode( $s ) . '?=' : $s;
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
			$h[] = 'Reply-To: ' . $m['reply_to'];
		}
		$h[] = 'Subject: ' . self::header_encode( $m['subject'] );
		$h[] = 'Message-ID: <' . bin2hex( random_bytes( 12 ) ) . '@' . $domain . '>';
		$h[] = 'MIME-Version: 1.0';
		$h[] = 'Content-Type: multipart/mixed; boundary="' . $mixed . '"';

		$body  = '--' . $mixed . "\r\n";
		$body .= 'Content-Type: multipart/alternative; boundary="' . $alt . "\"\r\n\r\n";
		$body .= '--' . $alt . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
		$body .= chunk_split( base64_encode( $m['text'] ) );
		if ( ! empty( $m['html'] ) ) {
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

/* ==================================================================== Rechnungsmails */

function nw_mail_configured() {
	$c = nw_config( 'smtp' );
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
	);
}

function nw_mail_compose( array $inv, $type = 'invoice' ) {
	$s = nw_settings();
	$v = nw_mail_vars( $inv );
	if ( 'offer' === $inv['kind'] ) {
		return array( 'subject' => strtr( $s['offer_subject'], $v ), 'body' => strtr( $s['offer_body'], $v ) );
	}
	return array(
		'subject' => strtr( 'reminder' === $type ? $s['remind_subject'] : $s['mail_subject'], $v ),
		'body'    => strtr( 'reminder' === $type ? $s['remind_body'] : $s['mail_body'], $v ),
	);
}

function nw_mail_html( $text, array $inv ) {
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
		. '<div style="font-size:20px;letter-spacing:-.02em;margin-bottom:22px">[ ' . htmlspecialchars( mb_strtolower( $s['company'] ), ENT_QUOTES, 'UTF-8' ) . ' ]</div>'
		. $paras
		. '</div><div style="font-size:12px;color:#6b6e76;text-align:center;padding:14px">' . $foot . '</div></div></body></html>';
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
	$cfg = nw_config( 'smtp' );
	$to  = nw_emails( $o['to'] ?? ( $inv['recipient']['email'] ?? '' ) );
	$cc  = nw_emails( $o['cc'] ?? ( $inv['customer']['email_cc'] ?? '' ) );
	if ( ! $to ) {
		return array( 'ok' => false, 'error' => 'Keine gültige E-Mail-Adresse.', 'to' => '' );
	}
	$mail    = nw_mail_compose( $inv, $type );
	$subject = trim( (string) ( $o['subject'] ?? '' ) ) ?: $mail['subject'];
	$body    = trim( (string) ( $o['body'] ?? '' ) ) ?: $mail['body'];
	$from    = $cfg['from'] ?: $cfg['user'];
	$error   = '';
	try {
		if ( ! nw_mail_configured() ) {
			throw new RuntimeException( 'E-Mail-Versand ist noch nicht eingerichtet (SMTP in config.php).' );
		}
		NW_SMTP::send(
			$cfg,
			array(
				'from'        => $from,
				'from_name'   => $cfg['from_name'] ?: nw_setting( 'company' ),
				'reply_to'    => nw_is_email( nw_setting( 'email' ) ) && nw_setting( 'email' ) !== $from ? nw_setting( 'email' ) : '',
				'to'          => $to,
				'cc'          => $cc,
				'bcc'         => nw_emails( $cfg['bcc'] ),
				'subject'     => $subject,
				'text'        => $body,
				'html'        => nw_mail_html( $body, $inv ),
				'attachments' => array( array( 'name' => NW_Document::filename( $inv ), 'type' => 'application/pdf', 'data' => NW_Document::render( $inv ) ) ),
			)
		);
	} catch ( Throwable $e ) {
		$error = $e->getMessage();
	}
	$ok = '' === $error;
	nw_insert( 'mail_log', array( 'invoice_id' => $inv['id'], 'kind' => $type, 'to_addr' => implode( ', ', array_merge( $to, $cc ) ), 'subject' => $subject, 'ok' => $ok ? 1 : 0, 'error' => $error, 'created_at' => nw_now() ) );
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
