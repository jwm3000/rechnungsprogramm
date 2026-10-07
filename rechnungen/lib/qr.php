<?php
defined( 'NW_APP' ) || exit;

/**
 * Kleiner QR-Code-Generator (Byte-Modus, Fehlerkorrektur M, Version 1–15) mit PNG-Ausgabe ohne GD –
 * und der EPC-/SEPA-QR-Code („GiroCode“) für Überweisungen nach EPC069-12.
 *
 * Aufbau nach ISO/IEC 18004, Umsetzung angelehnt an Project Nayuki „QR Code generator“ (MIT).
 */
class NW_QR {

	/* Level M je Version 1–15: Fehlerkorrektur-Codewörter je Block, Anzahl Blöcke, Codewörter gesamt */
	const ECC_PER_BLOCK = array( 0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24 );
	const BLOCKS        = array( 0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10 );
	const RAW           = array( 0, 26, 44, 70, 100, 134, 172, 196, 242, 292, 346, 404, 466, 532, 581, 655 );
	const ALIGN         = array(
		1  => array(),
		2  => array( 6, 18 ),
		3  => array( 6, 22 ),
		4  => array( 6, 26 ),
		5  => array( 6, 30 ),
		6  => array( 6, 34 ),
		7  => array( 6, 22, 38 ),
		8  => array( 6, 24, 42 ),
		9  => array( 6, 26, 46 ),
		10 => array( 6, 28, 50 ),
		11 => array( 6, 30, 54 ),
		12 => array( 6, 32, 58 ),
		13 => array( 6, 34, 62 ),
		14 => array( 6, 26, 46, 66 ),
		15 => array( 6, 26, 48, 70 ),
	);

	private $size;
	private $m  = array(); // Module [y][x] (true = dunkel)
	private $fn = array(); // Funktionsmuster [y][x]

	/* ================================================================ EPC / SEPA */

	/**
	 * Inhalt eines EPC-QR-Codes (SEPA-Überweisung). Null, wenn die Angaben nicht reichen.
	 */
	public static function epc_payload( $name, $iban, $bic, $amount, $text ) {
		$iban = strtoupper( preg_replace( '/\s+/', '', (string) $iban ) );
		$bic  = strtoupper( preg_replace( '/\s+/', '', (string) $bic ) );
		$name = self::clean( $name, 70 );
		if ( ! preg_match( '/^[A-Z]{2}\d{2}[A-Z0-9]{8,30}$/', $iban ) || '' === $name || $amount < 0.01 || $amount > 999999999.99 ) {
			return null;
		}
		$lines = array( 'BCD', '002', '1', 'SCT', $bic, $name, $iban, 'EUR' . number_format( (float) $amount, 2, '.', '' ), '', '', self::clean( $text, 140 ) );
		return rtrim( implode( "\n", $lines ), "\n" );
	}

	/** PNG eines EPC-QR-Codes oder null. */
	public static function epc_png( $name, $iban, $bic, $amount, $text, $scale = 8 ) {
		$payload = self::epc_payload( $name, $iban, $bic, $amount, $text );
		return $payload ? self::png( $payload, $scale ) : null;
	}

	private static function clean( $s, $max ) {
		$s = trim( preg_replace( '/[\r\n\t]+/', ' ', html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ) );
		$s = str_replace( array( '–', '—' ), '-', $s );
		// SEPA-Basiszeichensatz: Umlaute umschreiben, übrige Akzente entfernen (ältere Banking-Apps).
		$s = strtr( $s, array( 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss' ) );
		if ( function_exists( 'iconv' ) ) {
			$t = @iconv( 'UTF-8', 'ASCII//TRANSLIT', $s );
			if ( false !== $t ) { $s = $t; }
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, $max ) : substr( $s, 0, $max );
	}

	/* ================================================================ Matrix (für Vektor-Ausgabe im PDF und SVG) */

	/** @return array{size:int, modules:array}|null  modules[y][x] = true für dunkel */
	public static function matrix( $text ) {
		$qr = self::encode( $text );
		return $qr ? array( 'size' => $qr->size, 'modules' => $qr->m ) : null;
	}

	/** QR-Code als SVG (für die Online-Ansicht): ein einziger Pfad, gestochen scharf. */
	public static function svg( $text, $quiet = 2 ) {
		$m = self::matrix( $text );
		if ( ! $m ) {
			return '';
		}
		$n = $m['size'];
		$d = '';
		foreach ( $m['modules'] as $y => $row ) {
			foreach ( $row as $x => $dark ) {
				if ( $dark ) {
					$d .= 'M' . ( $x + $quiet ) . ' ' . ( $y + $quiet ) . 'h1v1h-1z';
				}
			}
		}
		$v = $n + 2 * $quiet;
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $v . ' ' . $v . '" shape-rendering="crispEdges" aria-hidden="true"><rect width="100%" height="100%" fill="#fff"/><path d="' . $d . '" fill="#111"/></svg>';
	}

	/* ================================================================ PNG */

	/** QR-Code als PNG (Graustufen, 4 Module Ruhezone). */
	public static function png( $text, $scale = 8 ) {
		$qr = self::encode( $text );
		if ( ! $qr ) {
			return null;
		}
		$quiet = 4;
		$n     = $qr->size;
		$px    = ( $n + 2 * $quiet ) * $scale;
		$raw   = '';
		$white = str_repeat( "\xFF", $px );
		for ( $y = -$quiet; $y < $n + $quiet; $y++ ) {
			if ( $y < 0 || $y >= $n ) {
				$row = $white;
			} else {
				$row = str_repeat( "\xFF", $quiet * $scale );
				for ( $x = 0; $x < $n; $x++ ) {
					$row .= str_repeat( $qr->m[ $y ][ $x ] ? "\x00" : "\xFF", $scale );
				}
				$row .= str_repeat( "\xFF", $quiet * $scale );
			}
			$raw .= str_repeat( "\x00" . $row, $scale ); // Filter 0 je Zeile
		}
		$chunk = function ( $type, $data ) {
			return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) );
		};
		return "\x89PNG\r\n\x1a\n" . $chunk( 'IHDR', pack( 'NNCCCCC', $px, $px, 8, 0, 0, 0, 0 ) ) . $chunk( 'IDAT', gzcompress( $raw, 9 ) ) . $chunk( 'IEND', '' );
	}

	/* ================================================================ QR-Code */

	/** @return self|null */
	public static function encode( $text ) {
		$data = array_values( unpack( 'C*', (string) $text ) );
		$len  = count( $data );
		for ( $v = 1; $v <= 15; $v++ ) {
			$cap  = ( self::RAW[ $v ] - self::ECC_PER_BLOCK[ $v ] * self::BLOCKS[ $v ] ) * 8;
			$need = 4 + ( $v < 10 ? 8 : 16 ) + $len * 8;
			if ( $need <= $cap ) {
				break;
			}
		}
		if ( $v > 15 ) {
			return null;
		}
		// Bitstrom: Byte-Modus, Länge, Daten, Abschluss, Füllbytes
		$bits = array();
		$put  = function ( $val, $n ) use ( &$bits ) {
			for ( $i = $n - 1; $i >= 0; $i-- ) {
				$bits[] = ( $val >> $i ) & 1;
			}
		};
		$put( 4, 4 );
		$put( $len, $v < 10 ? 8 : 16 );
		foreach ( $data as $b ) {
			$put( $b, 8 );
		}
		$put( 0, min( 4, $cap - count( $bits ) ) );
		$put( 0, ( 8 - count( $bits ) % 8 ) % 8 );
		for ( $pad = 0xEC; count( $bits ) < $cap; $pad ^= 0xEC ^ 0x11 ) {
			$put( $pad, 8 );
		}
		$words = array();
		for ( $i = 0; $i < count( $bits ); $i += 8 ) {
			$b = 0;
			for ( $j = 0; $j < 8; $j++ ) {
				$b = ( $b << 1 ) | $bits[ $i + $j ];
			}
			$words[] = $b;
		}

		$qr = new self();
		$qr->build( $v, self::interleave( $v, $words ) );
		return $qr;
	}

	private static function interleave( $v, array $data ) {
		$nb     = self::BLOCKS[ $v ];
		$ecl    = self::ECC_PER_BLOCK[ $v ];
		$raw    = self::RAW[ $v ];
		$short  = $nb - $raw % $nb;
		$slen   = intdiv( $raw, $nb );
		$div    = self::rs_divisor( $ecl );
		$blocks = array();
		for ( $i = 0, $k = 0; $i < $nb; $i++ ) {
			$n   = $slen - $ecl + ( $i < $short ? 0 : 1 );
			$dat = array_slice( $data, $k, $n );
			$k  += $n;
			$ecc = self::rs_remainder( $dat, $div );
			if ( $i < $short ) {
				$dat[] = 0; // Platzhalter, wird beim Verschränken übersprungen
			}
			$blocks[] = array_merge( $dat, $ecc );
		}
		$out = array();
		for ( $i = 0, $l = count( $blocks[0] ); $i < $l; $i++ ) {
			foreach ( $blocks as $j => $blk ) {
				if ( $i !== $slen - $ecl || $j >= $short ) {
					$out[] = $blk[ $i ];
				}
			}
		}
		return $out;
	}

	private static function gf_mul( $x, $y ) {
		$z = 0;
		for ( $i = 7; $i >= 0; $i-- ) {
			$z  = ( ( $z << 1 ) ^ ( ( $z >> 7 ) * 0x11D ) ) & 0xFF;
			$z ^= ( ( $y >> $i ) & 1 ) * $x;
		}
		return $z;
	}

	private static function rs_divisor( $degree ) {
		$r    = array_fill( 0, $degree, 0 );
		$r[ $degree - 1 ] = 1;
		$root = 1;
		for ( $i = 0; $i < $degree; $i++ ) {
			for ( $j = 0; $j < $degree; $j++ ) {
				$r[ $j ] = self::gf_mul( $r[ $j ], $root );
				if ( $j + 1 < $degree ) {
					$r[ $j ] ^= $r[ $j + 1 ];
				}
			}
			$root = self::gf_mul( $root, 0x02 );
		}
		return $r;
	}

	private static function rs_remainder( array $data, array $div ) {
		$r = array_fill( 0, count( $div ), 0 );
		foreach ( $data as $b ) {
			$f = $b ^ array_shift( $r );
			$r[] = 0;
			foreach ( $div as $i => $c ) {
				$r[ $i ] ^= self::gf_mul( $c, $f );
			}
		}
		return $r;
	}

	private function build( $v, array $words ) {
		$this->size = $v * 4 + 17;
		$n          = $this->size;
		$this->m    = array_fill( 0, $n, array_fill( 0, $n, false ) );
		$this->fn   = $this->m;

		// Funktionsmuster: Taktlinien, Suchmuster, Ausrichtungsmuster, Format/Version
		for ( $i = 0; $i < $n; $i++ ) {
			$this->set( 6, $i, 0 === $i % 2 );
			$this->set( $i, 6, 0 === $i % 2 );
		}
		foreach ( array( array( 3, 3 ), array( $n - 4, 3 ), array( 3, $n - 4 ) ) as $c ) {
			for ( $dy = -4; $dy <= 4; $dy++ ) {
				for ( $dx = -4; $dx <= 4; $dx++ ) {
					$d  = max( abs( $dx ), abs( $dy ) );
					$xx = $c[0] + $dx;
					$yy = $c[1] + $dy;
					if ( $xx >= 0 && $xx < $n && $yy >= 0 && $yy < $n ) {
						$this->set( $xx, $yy, 2 !== $d && 4 !== $d );
					}
				}
			}
		}
		$al = self::ALIGN[ $v ];
		$na = count( $al );
		for ( $i = 0; $i < $na; $i++ ) {
			for ( $j = 0; $j < $na; $j++ ) {
				if ( ( 0 === $i && 0 === $j ) || ( 0 === $i && $na - 1 === $j ) || ( $na - 1 === $i && 0 === $j ) ) {
					continue;
				}
				for ( $dy = -2; $dy <= 2; $dy++ ) {
					for ( $dx = -2; $dx <= 2; $dx++ ) {
						$this->set( $al[ $i ] + $dx, $al[ $j ] + $dy, 1 !== max( abs( $dx ), abs( $dy ) ) );
					}
				}
			}
		}
		$this->format( 0 );
		if ( $v >= 7 ) {
			$rem = $v;
			for ( $i = 0; $i < 12; $i++ ) {
				$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
			}
			$bits = ( $v << 12 ) | $rem;
			for ( $i = 0; $i < 18; $i++ ) {
				$bit = 1 === ( ( $bits >> $i ) & 1 );
				$a   = $n - 11 + $i % 3;
				$b   = intdiv( $i, 3 );
				$this->set( $a, $b, $bit );
				$this->set( $b, $a, $bit );
			}
		}

		// Daten im Zickzack eintragen
		$i     = 0;
		$total = count( $words ) * 8;
		for ( $right = $n - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5;
			}
			for ( $vert = 0; $vert < $n; $vert++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x  = $right - $j;
					$up = 0 === ( ( $right + 1 ) & 2 );
					$y  = $up ? $n - 1 - $vert : $vert;
					if ( ! $this->fn[ $y ][ $x ] && $i < $total ) {
						$this->m[ $y ][ $x ] = 1 === ( ( $words[ $i >> 3 ] >> ( 7 - ( $i & 7 ) ) ) & 1 );
						$i++;
					}
				}
			}
		}

		// Beste Maske wählen
		$best = 0;
		$min  = PHP_INT_MAX;
		for ( $k = 0; $k < 8; $k++ ) {
			$this->mask( $k );
			$this->format( $k );
			$p = $this->penalty();
			if ( $p < $min ) {
				$min  = $p;
				$best = $k;
			}
			$this->mask( $k ); // rückgängig (XOR)
		}
		$this->mask( $best );
		$this->format( $best );
	}

	private function set( $x, $y, $dark ) {
		$this->m[ $y ][ $x ]  = (bool) $dark;
		$this->fn[ $y ][ $x ] = true;
	}

	/** Formatinformation (Level M = 00) zweifach eintragen. */
	private function format( $mask ) {
		$data = $mask; // (0 << 3) | mask
		$rem  = $data;
		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
		}
		$bits = ( ( $data << 10 ) | $rem ) ^ 0x5412;
		$b    = function ( $i ) use ( $bits ) {
			return 1 === ( ( $bits >> $i ) & 1 );
		};
		$n = $this->size;
		for ( $i = 0; $i <= 5; $i++ ) {
			$this->set( 8, $i, $b( $i ) );
		}
		$this->set( 8, 7, $b( 6 ) );
		$this->set( 8, 8, $b( 7 ) );
		$this->set( 7, 8, $b( 8 ) );
		for ( $i = 9; $i < 15; $i++ ) {
			$this->set( 14 - $i, 8, $b( $i ) );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$this->set( $n - 1 - $i, 8, $b( $i ) );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$this->set( 8, $n - 15 + $i, $b( $i ) );
		}
		$this->set( 8, $n - 8, true );
	}

	private function mask( $k ) {
		$n = $this->size;
		for ( $y = 0; $y < $n; $y++ ) {
			for ( $x = 0; $x < $n; $x++ ) {
				switch ( $k ) {
					case 0: $inv = 0 === ( $x + $y ) % 2; break;
					case 1: $inv = 0 === $y % 2; break;
					case 2: $inv = 0 === $x % 3; break;
					case 3: $inv = 0 === ( $x + $y ) % 3; break;
					case 4: $inv = 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2; break;
					case 5: $inv = 0 === $x * $y % 2 + $x * $y % 3; break;
					case 6: $inv = 0 === ( $x * $y % 2 + $x * $y % 3 ) % 2; break;
					default: $inv = 0 === ( ( $x + $y ) % 2 + $x * $y % 3 ) % 2;
				}
				if ( $inv && ! $this->fn[ $y ][ $x ] ) {
					$this->m[ $y ][ $x ] = ! $this->m[ $y ][ $x ];
				}
			}
		}
	}

	/** Strafpunkte nach ISO 18004 (Reihen, 2×2-Blöcke, Suchmuster-Ähnlichkeit, Hell/Dunkel-Verhältnis). */
	private function penalty() {
		$n    = $this->size;
		$p    = 0;
		$dark = 0;
		$pat  = array( '10111010000', '00001011101' );
		for ( $pass = 0; $pass < 2; $pass++ ) {
			for ( $a = 0; $a < $n; $a++ ) {
				$line = '';
				$run  = 0;
				$prev = null;
				for ( $b = 0; $b < $n; $b++ ) {
					$c     = $pass ? $this->m[ $b ][ $a ] : $this->m[ $a ][ $b ];
					$line .= $c ? '1' : '0';
					if ( $c === $prev ) {
						$run++;
					} else {
						if ( $run >= 5 ) {
							$p += $run - 2;
						}
						$run  = 1;
						$prev = $c;
					}
				}
				if ( $run >= 5 ) {
					$p += $run - 2;
				}
				foreach ( $pat as $f ) {
					$p += 40 * substr_count( $line, $f );
				}
			}
		}
		for ( $y = 0; $y < $n; $y++ ) {
			for ( $x = 0; $x < $n; $x++ ) {
				$c = $this->m[ $y ][ $x ];
				if ( $c ) {
					$dark++;
				}
				if ( $x < $n - 1 && $y < $n - 1 && $c === $this->m[ $y ][ $x + 1 ] && $c === $this->m[ $y + 1 ][ $x ] && $c === $this->m[ $y + 1 ][ $x + 1 ] ) {
					$p += 3;
				}
			}
		}
		$total = $n * $n;
		$k     = (int) ceil( abs( $dark * 20 - $total * 10 ) / $total ) - 1;
		return $p + max( 0, $k ) * 10;
	}
}
