<?php
/**
 * Rechnung als PDF (A4) – mit Logo als Vektorgrafik, Zahlschein und SEPA-QR-Code.
 */
defined( 'NW_APP' ) || exit;

class NW_Document {

	const L = 56;   // linker Rand
	const R = 539;  // rechter Rand (595 − 56)

	private static $ink   = '#16171a';
	private static $muted = '#6b6e76';
	private static $line  = '#e3e4e8';
	private static $soft  = '#f5f6f8';

	/** Dateiname, z. B. „Rechnung-1017-Tischlerei-Holzmann-GmbH.pdf“. */
	public static function filename( array $inv ) {
		$kind = 'storno' === $inv['kind'] ? 'Stornorechnung' : ( 'draft' === $inv['status'] ? 'Rechnungsentwurf' : 'Rechnung' );
		$name = $kind . '-' . ( $inv['number'] ? $inv['number'] : $inv['id'] ) . '-' . ( $inv['recipient']['name'] ?? '' );
		return nw_slug( $name ) . '.pdf';
	}

	public static function service_label( array $inv ) {
		if ( $inv['period_from'] && $inv['period_to'] ) {
			return array( 'Leistungszeitraum', nw_date( $inv['period_from'] ) . ' – ' . nw_date( $inv['period_to'] ) );
		}
		return array( 'Leistungsdatum', nw_date( $inv['service_date'] ? $inv['service_date'] : $inv['invoice_date'] ) );
	}

	public static function payment_terms( array $inv ) {
		return $inv['payment_days'] > 0 ? 'Zahlbar bis ' . nw_date( $inv['due_date'] ) . ' ohne Abzug' : 'Zahlbar sofort ohne Abzug';
	}

	public static function reference( array $inv ) {
		return 'Rechnung ' . $inv['number'];
	}

	public static function epc( array $inv, array $s ) {
		if ( 'qr' !== $s['pay_box'] || 'invoice' !== $inv['kind'] || 'issued' !== $inv['status'] || $inv['open'] <= 0 ) {
			return null;
		}
		return NW_QR::epc_payload( $s['bank_owner'] ? $s['bank_owner'] : $s['company'], $s['iban'], $s['bic'], $inv['open'], self::reference( $inv ) );
	}

	public static function render( array $inv ) {
		$s      = nw_settings();
		$accent = preg_match( '/^#[0-9a-f]{6}$/i', $s['accent'] ) ? $s['accent'] : '#16171a';
		$storno = 'storno' === $inv['kind'];
		$draft  = 'draft' === $inv['status'];
		$L      = self::L;
		$R      = self::R;
		$title  = $storno ? 'Stornorechnung' : 'Rechnung';
		$small  = ! empty( $inv['small_business'] );

		$pdf = new NW_PDF( $title . ' ' . ( $inv['number'] ? $inv['number'] : 'Entwurf' ) . ' – ' . $s['company'] );
		$pdf->add_page();
		self::page_frame( $pdf, $s, $accent );

		// ---------------------------------------------------------------- Kopf: Logo links, Titel rechts
		$pdf->logo( $L - 2, 46, 178, self::$ink );
		$pdf->text( $R, 66, mb_strtoupper( $title ), $storno ? 20 : 24, true, $accent, 'right' );
		$pdf->text( $R, 84, $draft ? 'ENTWURF – noch nicht ausgestellt' : 'Nr. ' . $inv['number'], 10, $draft, $draft ? '#b54708' : self::$muted, 'right' );

		// ---------------------------------------------------------------- Absenderzeile + Empfänger (Fensterposition)
		$top    = 150;
		$sender = implode( ' · ', array_filter( array( $s['company'], $s['street'], trim( $s['zip'] . ' ' . $s['city'] ) ) ) );
		$pdf->text( $L, $top, $sender, 7, false, self::$muted );
		$pdf->line( $L, $top + 4, $L + min( 260, $pdf->width( $sender, 7 ) ), $top + 4, self::$line, 0.5 );
		$y = $top + 22;
		foreach ( $inv['recipient']['lines'] as $i => $row ) {
			$pdf->text( $L, $y, $row, 10.5, 0 === $i, self::$ink );
			$y += 14.5;
		}

		// ---------------------------------------------------------------- Metadaten rechts
		$svc  = self::service_label( $inv );
		$meta = array(
			( $storno ? 'Stornorechnung Nr.' : 'Rechnung Nr.' ) => $inv['number'] ? $inv['number'] : '—',
		);
		if ( $storno && ! empty( $inv['ref'] ) ) {
			$meta['zu Rechnung Nr.'] = $inv['ref']['number'];
		}
		$meta['Kunden Nr.']  = (string) ( $inv['recipient']['number'] ?? '' );
		$meta['Datum']       = nw_date( $inv['invoice_date'] );
		$meta[ $svc[0] ]     = $svc[1];
		if ( ! $storno && $inv['payment_days'] > 0 ) {
			$meta['Fällig am'] = nw_date( $inv['due_date'] );
		}
		$mx = 340;
		$my = $top + 4;
		foreach ( $meta as $label => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			$pdf->text( $mx, $my, $label, 8.5, false, self::$muted );
			$pdf->text( $R, $my, $value, 9.5, true, self::$ink, 'right' );
			$my += 16;
		}

		// ---------------------------------------------------------------- Betreff + Einleitung
		$y = max( $y, $my ) + 40;
		$y = max( $y, 290 );
		$head = $title . ' ' . ( $inv['number'] ? $inv['number'] : '(Entwurf)' );
		if ( '' !== trim( (string) $inv['subject'] ) ) {
			$head .= ' · ' . trim( $inv['subject'] );
		}
		foreach ( $pdf->wrap( $head, $R - $L, 15, true ) as $row ) {
			$pdf->text( $L, $y, $row, 15, true, self::$ink );
			$y += 19;
		}
		$y += 6;
		if ( '' !== trim( (string) $inv['greeting'] ) ) {
			$pdf->text( $L, $y, $inv['greeting'], 10, false, self::$ink );
			$y += 17;
		}
		foreach ( $pdf->wrap( (string) $inv['intro'], $R - $L, 10 ) as $row ) {
			if ( '' === $row ) {
				$y += 6;
				continue;
			}
			$pdf->text( $L, $y, $row, 10, false, self::$ink );
			$y += 14;
		}

		// ---------------------------------------------------------------- Positionen
		$y   += 12;
		$cols = self::columns();
		$y    = self::table_head( $pdf, $y, $cols );
		foreach ( $inv['items'] as $it ) {
			$desc = array();
			foreach ( preg_split( "/\r\n|\n/", (string) $it['description'] ) as $d ) {
				if ( '' === trim( $d ) ) {
					$desc[] = '';
					continue;
				}
				foreach ( $pdf->wrap( rtrim( $d ), $cols['desc_w'], 8.8 ) as $w ) {
					$desc[] = $w;
				}
			}
			while ( $desc && '' === end( $desc ) ) {
				array_pop( $desc );
			}
			$name_rows = $pdf->wrap( $it['name'], $cols['name_w'], 10, true );
			$h         = 14 + count( $name_rows ) * 13 + count( $desc ) * 11.2 + 5;
			if ( $it['discount'] > 0 ) {
				$h = max( $h, 14 + 13 + 11.2 + 5 );
			}
			if ( $y + min( $h, 64 ) > 715 ) {
				$pdf->add_page();
				self::page_frame( $pdf, $s, $accent );
				$pdf->text( $L, 60, $head . ' – Fortsetzung', 9, true, self::$muted );
				$y = self::table_head( $pdf, 74, $cols );
			}
			$ry = $y + 17;
			$pdf->text( $cols['pos'], $ry, (string) $it['pos'], 9.5, false, self::$muted );
			$pdf->text( $cols['sku'], $ry, (string) $it['sku'], 9, false, self::$muted );
			foreach ( $name_rows as $k => $nr ) {
				$pdf->text( $cols['name'], $ry + $k * 13, $nr, 10, true, self::$ink );
			}
			$pdf->text( $cols['qty'], $ry, nw_qty( $it['qty'] ), 10, false, self::$ink, 'right' );
			$pdf->text( $cols['unit'], $ry, $it['unit'], 9, false, self::$muted );
			$pdf->text( $cols['price'], $ry, nw_money( $it['price'] ), 9.5, false, self::$ink, 'right' );
			if ( $it['discount'] > 0 ) {
				$pdf->text( $cols['price'], $ry + 12, '– ' . nw_percent( round( $it['discount'], 2 ) ) . ' Rabatt', 8, false, $accent, 'right' );
			}
			$pdf->text( $cols['sum'], $ry, nw_money( $it['net'] ), 10, true, self::$ink, 'right' );
			// Beschreibung – darf über Seiten laufen (z. B. lange Betreuungslisten)
			$dy = $ry + count( $name_rows ) * 13 - 1;
			foreach ( $desc as $d ) {
				if ( $dy > 728 ) {
					$pdf->add_page();
					self::page_frame( $pdf, $s, $accent );
					$pdf->text( $L, 60, $head . ' – Fortsetzung Pos. ' . $it['pos'], 9, true, self::$muted );
					$dy = 84;
				}
				if ( '' !== $d ) {
					$pdf->text( $cols['name'], $dy, $d, 8.8, false, self::$muted );
				}
				$dy += 11.2;
			}
			$y = max( $y + $h, $dy - 6 );
			$pdf->line( $L, $y, $R, $y, self::$line );
		}
		$pdf->text( $L, $y + 13, $small ? '' : 'Preise und Positionssummen netto.', 7.5, false, self::$muted );

		// ---------------------------------------------------------------- Summen
		if ( $y > 640 ) {
			$pdf->add_page();
			self::page_frame( $pdf, $s, $accent );
			$y = 60;
		}
		$y += 30;
		$tx = 340;
		$sy = $y;
		if ( ! $small ) {
			$pdf->text( $tx, $sy, 'Nettobetrag', 9.5, false, self::$muted );
			$pdf->text( $R - 10, $sy, nw_money( $inv['net'] ), 9.5, false, self::$ink, 'right' );
			foreach ( $inv['taxes'] as $g ) {
				$sy += 16;
				$pdf->text( $tx, $sy, nw_percent( $g['rate'] ) . ' USt.' . ( count( $inv['taxes'] ) > 1 ? ' von ' . nw_money( $g['net'] ) : '' ), 9.5, false, self::$muted );
				$pdf->text( $R - 10, $sy, nw_money( $g['tax'] ), 9.5, false, self::$ink, 'right' );
			}
			$sy += 14;
		} else {
			$sy -= 12;
		}
		$pdf->rrect( $tx - 12, $sy, $R - $tx + 12, 36, 4, $accent );
		$pdf->text( $tx, $sy + 23, $storno ? 'Stornobetrag' : 'Gesamtbetrag', 11, true, '#ffffff' );
		$pdf->text( $R - 10, $sy + 23.5, nw_money( $inv['gross'] ), 13.5, true, '#ffffff', 'right' );
		$sy += 36;

		// Links neben den Summen: Zahlungsbedingung, Leistungsdatum.
		$ly = $y;
		if ( ! $storno ) {
			$pdf->text( $L, $ly, self::payment_terms( $inv ), 9.5, true, self::$ink );
			$ly += 14;
		}
		$pdf->text( $L, $ly, $svc[0] . ': ' . $svc[1], 9.5, false, self::$ink );
		$ly += 14;

		$state = $inv['state'];
		if ( 'paid' === $state ) {
			self::stamp( $pdf, $L, $ly + 8, 'BEZAHLT', 'am ' . nw_date( $inv['paid_at'] ) . ' – vielen Dank!', '#1a7f4b', '#e7f5ec' );
			$ly += 46;
		} elseif ( 'cancelled' === $state ) {
			self::stamp( $pdf, $L, $ly + 8, 'STORNIERT', ! empty( $inv['storno'] ) ? 'mit Stornorechnung ' . $inv['storno']['number'] : '', '#9b1c1c', '#fdecec' );
			$ly += 46;
		}

		// ---------------------------------------------------------------- Hinweise und Schlusstext
		$y     = max( $sy, $ly ) + 26;
		$notes = array();
		if ( $storno && ! empty( $inv['ref'] ) ) {
			$notes[] = array( 'Diese Stornorechnung hebt die Rechnung ' . $inv['ref']['number'] . ' vom ' . nw_date( $inv['ref']['invoice_date'] ) . ' vollständig auf.', false );
		}
		if ( $small && '' !== trim( $s['small_business_text'] ) ) {
			$notes[] = array( $s['small_business_text'], true );
		}
		foreach ( array_filter( array( trim( (string) $inv['outro'] ), trim( (string) $s['footer_note'] ) ) ) as $note ) {
			$notes[] = array( $note, false );
		}
		if ( ! $storno && ! $draft && 'paid' !== $state && 'cancelled' !== $state ) {
			$notes[] = array( 'Vielen Dank für Ihren Auftrag!', false );
		}
		foreach ( $notes as $n ) {
			foreach ( $pdf->wrap( $n[0], $R - $L, 9.5 ) as $row ) {
				if ( $y > 735 ) {
					$pdf->add_page();
					self::page_frame( $pdf, $s, $accent );
					$y = 70;
				}
				$pdf->text( $L, $y, $row, 9.5, false, $n[1] ? self::$muted : self::$ink );
				$y += 13.5;
			}
			$y += 5;
		}

		// ---------------------------------------------------------------- Zahlschein (unten über der Fußzeile)
		$mode = in_array( $s['pay_box'], array( 'qr', 'plain', 'off' ), true ) ? $s['pay_box'] : 'qr';
		$due  = $draft || ( 'issued' === $inv['status'] && $inv['open'] > 0 );
		if ( 'off' !== $mode && ! $storno && $due && '' !== trim( $s['iban'] ) ) {
			$epc = 'qr' === $mode && ! $draft ? self::epc( $inv, $s ) : null;
			$m   = $epc ? NW_QR::matrix( $epc ) : null;
			$h   = $m ? 124 : 92;
			$by  = 744 - $h;
			if ( $y + 8 > $by ) {
				$pdf->add_page();
				self::page_frame( $pdf, $s, $accent );
				$by = 70;
			}
			self::pay_box( $pdf, $by, $h, $inv, $s, $accent, $m, $draft );
		}

		return $pdf->output();
	}

	private static function pay_box( NW_PDF $pdf, $by, $h, array $inv, array $s, $accent, $m, $draft ) {
		$L = self::L;
		$R = self::R;
		$pdf->rrect( $L, $by, $R - $L, $h, 7, self::$soft, self::$line, 0.6 );

		$x1     = $L + 18;
		$amount = $draft ? $inv['gross'] : $inv['open'];
		$cy     = $by + $h / 2;
		$pdf->text( $x1, $cy - 14, 'BITTE ÜBERWEISEN', 7.5, true, $accent );
		$pdf->text( $x1, $cy + 9, nw_money( $amount ), 17, true, self::$ink );
		$pdf->text( $x1, $cy + 24, $inv['payment_days'] > 0 ? 'bis ' . nw_date( $inv['due_date'] ) : 'sofort, ohne Abzug', 8.5, false, self::$muted );

		$c2 = $L + 146;
		$pdf->line( $c2 - 14, $by + 16, $c2 - 14, $by + $h - 16, self::$line, 0.8 );
		$rows = array_filter(
			array(
				'Empfänger'        => $s['bank_owner'] ? $s['bank_owner'] : $s['company'],
				'IBAN'             => $s['iban'],
				'BIC'              => $s['bic'],
				'Verwendungszweck' => $draft ? 'Rechnung (Nummer folgt)' : self::reference( $inv ),
			),
			function ( $v ) { return '' !== trim( (string) $v ); }
		);
		$ry = $by + ( $h - count( $rows ) * 15 ) / 2 + 9;
		foreach ( $rows as $k => $v ) {
			$pdf->text( $c2, $ry, $k, 7.5, false, self::$muted );
			$pdf->text( $c2 + 74, $ry, $v, 9.5, true, self::$ink );
			$ry += 15;
		}

		if ( $m ) {
			$pw = 120;
			$cx = $R - $pw / 2;
			$pdf->line( $R - $pw, $by + 16, $R - $pw, $by + $h - 16, self::$line, 0.8 );
			$qs   = 72;
			$card = $qs + 10;
			$pdf->rrect( $cx - $card / 2, $by + 11, $card, $card, 5, '#ffffff', self::$line, 0.6 );
			$pdf->qr( $cx - $qs / 2, $by + 16, $qs, $m );
			$pdf->text( $cx, $by + $h - 19, 'ZAHLEN MIT CODE', 7, true, $accent, 'center' );
			$pdf->text( $cx, $by + $h - 9, 'Banking-App öffnen & scannen', 7, false, self::$muted, 'center' );
		}
	}

	private static function columns() {
		$L = self::L;
		$R = self::R;
		return array(
			'pos'    => $L + 8,
			'sku'    => $L + 30,
			'name'   => $L + 72,
			'name_w' => 196,
			'desc_w' => 262,     // Beschreibung darf unter Menge/Einheit laufen
			'qty'    => 354,     // rechtsbündig
			'unit'   => 361,
			'price'  => 462,     // rechtsbündig
			'sum'    => $R - 8,  // rechtsbündig
		);
	}

	private static function table_head( NW_PDF $pdf, $y, array $c ) {
		$pdf->rrect( self::L, $y, self::R - self::L, 24, 4, self::$soft );
		$ty = $y + 15.5;
		$pdf->text( $c['pos'], $ty, 'Pos.', 8, true, self::$muted );
		$pdf->text( $c['sku'], $ty, 'Art.Nr.', 8, true, self::$muted );
		$pdf->text( $c['name'], $ty, 'Leistung', 8, true, self::$muted );
		$pdf->text( $c['qty'], $ty, 'Menge', 8, true, self::$muted, 'right' );
		$pdf->text( $c['unit'], $ty, 'Einheit', 8, true, self::$muted );
		$pdf->text( $c['price'], $ty, 'Einzelpreis', 8, true, self::$muted, 'right' );
		$pdf->text( $c['sum'], $ty, 'Gesamt', 8, true, self::$muted, 'right' );
		return $y + 24;
	}

	private static function stamp( NW_PDF $pdf, $x, $y, $label, $sub, $color, $bg ) {
		$w = max( $pdf->width( $label, 13, true ), $pdf->width( $sub, 8.5 ) ) + 30;
		$pdf->rrect( $x, $y, $w, 36, 4, $bg );
		$pdf->rect( $x, $y, 3, 36, $color );
		$pdf->text( $x + 15, $y + 16, $label, 13, true, $color );
		if ( $sub ) {
			$pdf->text( $x + 15, $y + 28, $sub, 8.5, false, $color );
		}
	}

	/** Akzentlinie oben und Fußzeile mit Firmen-, Kontakt- und Bankdaten – auf jeder Seite. */
	private static function page_frame( NW_PDF $pdf, array $s, $accent ) {
		$L = self::L;
		$R = self::R;
		$pdf->rect( 0, 0, NW_PDF::W, 5, $accent );
		$fy = 776;
		$pdf->line( $L, $fy - 14, $R, $fy - 14, self::$line );
		$cols = array(
			array_filter( array( $s['company'], $s['tagline'], $s['street'], trim( $s['zip'] . ' ' . $s['city'] ) ) ),
			array_filter(
				array(
					$s['phone'] ? 'Tel. ' . $s['phone'] : '',
					$s['email'],
					$s['web'],
					$s['vat_id'] ? 'UID: ' . $s['vat_id'] : ( $s['tax_number'] ? 'St.-Nr.: ' . $s['tax_number'] : '' ),
				)
			),
			array_filter(
				array(
					$s['bank'] ? $s['bank'] : 'Bankverbindung',
					$s['iban'] ? 'IBAN ' . $s['iban'] : '',
					$s['bic'] ? 'BIC ' . $s['bic'] : '',
					$s['bank_owner'] && $s['bank_owner'] !== $s['company'] ? 'Empfänger: ' . $s['bank_owner'] : '',
				)
			),
		);
		$cw = ( $R - $L ) / 3;
		foreach ( $cols as $ci => $rows ) {
			$ry = $fy;
			foreach ( array_slice( array_values( $rows ), 0, 4 ) as $k => $row ) {
				$pdf->text( $L + $ci * $cw, $ry, $row, 7.5, 0 === $k && 0 === $ci, 0 === $k && 0 === $ci ? self::$ink : self::$muted );
				$ry += 10.5;
			}
		}
	}
}
