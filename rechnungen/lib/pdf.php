<?php
defined( 'NW_APP' ) || exit;

/**
 * Minimaler PDF-Generator (A4, Helvetica) ohne externe Abhängigkeiten.
 *
 * Koordinaten in Punkt, Ursprung oben links. Text wird nach Windows-1252 kodiert,
 * damit Umlaute, ß und € mit den PDF-Standardschriften funktionieren.
 */
class NW_PDF {

	const W = 595.28;
	const H = 841.89;

	private $pages = array();
	private $cur   = -1;
	private $title = '';

	/** Zeichenbreiten (1/1000 em) für ASCII 32–126: Helvetica und Helvetica-Bold. */
	private static $ascii = array(
		'r' => array( 278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584 ),
		'b' => array( 278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584 ),
	);

	/** Breiten ausgewählter Windows-1252-Zeichen > 127: array( regular, bold ). */
	private static $extra = array(
		128 => array( 556, 556 ), 130 => array( 222, 278 ), 132 => array( 333, 500 ), 133 => array( 1000, 1000 ),
		145 => array( 222, 278 ), 146 => array( 222, 278 ), 147 => array( 333, 500 ), 148 => array( 333, 500 ),
		149 => array( 350, 350 ), 150 => array( 556, 556 ), 151 => array( 1000, 1000 ), 160 => array( 278, 278 ),
		163 => array( 556, 556 ), 167 => array( 556, 556 ), 169 => array( 737, 737 ), 176 => array( 400, 400 ),
		183 => array( 278, 278 ), 196 => array( 667, 722 ), 201 => array( 667, 667 ), 214 => array( 778, 778 ),
		215 => array( 584, 584 ), 220 => array( 722, 722 ), 223 => array( 611, 611 ), 224 => array( 556, 556 ),
		225 => array( 556, 556 ), 228 => array( 556, 556 ), 231 => array( 500, 556 ), 232 => array( 556, 556 ),
		233 => array( 556, 556 ), 246 => array( 556, 611 ), 252 => array( 556, 611 ),
	);

	public function __construct( $title = '' ) {
		$this->title = $title;
	}

	public function add_page() {
		$this->pages[] = '';
		$this->cur     = count( $this->pages ) - 1;
	}

	/* ---------------------------------------------------------------- Zeichnen */

	public function text( $x, $y, $text, $size = 10, $bold = false, $color = '#222222', $align = 'left' ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return;
		}
		if ( 'right' === $align ) {
			$x -= $this->width( $text, $size, $bold );
		} elseif ( 'center' === $align ) {
			$x -= $this->width( $text, $size, $bold ) / 2;
		}
		$this->out(
			sprintf(
				'BT /%s %.2F Tf %s rg %.2F %.2F Td (%s) Tj ET',
				$bold ? 'F2' : 'F1',
				$size,
				$this->rgb( $color ),
				$x,
				self::H - $y,
				$this->escape( $this->encode( $text ) )
			)
		);
	}

	public function rect( $x, $y, $w, $h, $color ) {
		$this->out( sprintf( '%s rg %.2F %.2F %.2F %.2F re f', $this->rgb( $color ), $x, self::H - $y - $h, $w, $h ) );
	}

	public function line( $x1, $y1, $x2, $y2, $color = '#dddddd', $width = 0.6 ) {
		$this->out( sprintf( '%s RG %.2F w %.2F %.2F m %.2F %.2F l S', $this->rgb( $color ), $width, $x1, self::H - $y1, $x2, self::H - $y2 ) );
	}

	/** Fläche mit abgerundeten Ecken (Radius in Punkt). */
	public function rrect( $x, $y, $w, $h, $r, $color, $stroke = null, $lw = 0.8 ) {
		$r  = min( $r, $w / 2, $h / 2 );
		$k  = 0.5523 * $r; // Bézier-Näherung für Viertelkreise
		$x1 = $x;
		$y1 = self::H - $y - $h; // unten links (PDF-Koordinaten)
		$x2 = $x + $w;
		$y2 = self::H - $y;
		$p  = sprintf( '%.2F %.2F m ', $x1 + $r, $y1 );
		$p .= sprintf( '%.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c ', $x2 - $r, $y1, $x2 - $r + $k, $y1, $x2, $y1 + $r - $k, $x2, $y1 + $r );
		$p .= sprintf( '%.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c ', $x2, $y2 - $r, $x2, $y2 - $r + $k, $x2 - $r + $k, $y2, $x2 - $r, $y2 );
		$p .= sprintf( '%.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c ', $x1 + $r, $y2, $x1 + $r - $k, $y2, $x1, $y2 - $r + $k, $x1, $y2 - $r );
		$p .= sprintf( '%.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c h', $x1, $y1 + $r, $x1, $y1 + $r - $k, $x1 + $r - $k, $y1, $x1 + $r, $y1 );
		if ( $color && $stroke ) {
			$this->out( sprintf( '%s rg %s RG %.2F w %s B', $this->rgb( $color ), $this->rgb( $stroke ), $lw, $p ) );
		} elseif ( $stroke ) {
			$this->out( sprintf( '%s RG %.2F w %s S', $this->rgb( $stroke ), $lw, $p ) );
		} else {
			$this->out( sprintf( '%s rg %s f', $this->rgb( $color ), $p ) );
		}
	}

	/** QR-Code als Vektorgrafik (Modulmatrix aus NW_QR::matrix). */
	public function qr( $x, $y, $size, array $matrix, $color = '#111111' ) {
		$n = $matrix['size'];
		$u = $size / $n;
		$p = '';
		foreach ( $matrix['modules'] as $row => $cols ) {
			$run = -1;
			foreach ( $cols as $col => $dark ) {
				// Dunkle Module einer Zeile zu Streifen zusammenfassen (kleinere Datei).
				if ( $dark && $run < 0 ) {
					$run = $col;
				}
				if ( ( ! $dark || $col === $n - 1 ) && $run >= 0 ) {
					$end = $dark ? $col + 1 : $col;
					$p  .= sprintf( '%.3F %.3F %.3F %.3F re ', $x + $run * $u, self::H - $y - ( $row + 1 ) * $u, ( $end - $run ) * $u, $u + 0.01 );
					$run = -1;
				}
			}
		}
		$this->out( $this->rgb( $color ) . ' rg ' . $p . 'f' );
	}

	/**
	 * Logo (Vektorpfad aus logo-path.php) – oben links bei ($x, $y), Breite $w in Punkt.
	 */
	public function logo( $x, $y, $w, $color = '#111111' ) {
		static $path = null;
		if ( null === $path ) {
			$path = require __DIR__ . '/logo-path.php';
		}
		$s = $w / 4048; // viewBox-Breite
		$this->out( sprintf( 'q %s rg %.5F 0 0 %.5F %.2F %.2F cm %s f Q', $this->rgb( $color ), $s * 0.1, $s * 0.1, $x, self::H - $y - 868 * $s, $path ) );
	}

	/** Höhe des Logos bei Breite $w. */
	public static function logo_height( $w ) {
		return $w * 868 / 4048;
	}

	/** Textbreite in Punkt. */
	public function width( $text, $size, $bold = false ) {
		$enc = $this->encode( $text );
		$sum = 0;
		$len = strlen( $enc );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = ord( $enc[ $i ] );
			if ( $c >= 32 && $c <= 126 ) {
				$sum += self::$ascii[ $bold ? 'b' : 'r' ][ $c - 32 ];
			} else {
				$sum += isset( self::$extra[ $c ] ) ? self::$extra[ $c ][ $bold ? 1 : 0 ] : 556;
			}
		}
		return $sum * $size / 1000;
	}

	/** Bricht Text wortweise auf die maximale Breite um. */
	public function wrap( $text, $max, $size, $bold = false ) {
		$out = array();
		foreach ( preg_split( "/\r\n|\n|\r/", (string) $text ) as $para ) {
			$line = '';
			foreach ( preg_split( '/\s+/', trim( $para ) ) as $word ) {
				$try = '' === $line ? $word : $line . ' ' . $word;
				if ( '' !== $line && $this->width( $try, $size, $bold ) > $max ) {
					$out[] = $line;
					$line  = $word;
				} else {
					$line = $try;
				}
			}
			$out[] = $line;
		}
		return $out;
	}

	/* ---------------------------------------------------------------- Ausgabe */

	public function output() {
		$objects    = array();
		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$objects[5] = sprintf( '<< /Title (%s) /Producer (Rechnungen norbertwinter.at) /CreationDate (D:%s) >>', $this->escape( $this->encode( $this->title ) ), gmdate( 'YmdHis' ) );

		$kids = array();
		$n    = 6;
		foreach ( $this->pages as $content ) {
			$stream = $content;
			$filter = '';
			if ( function_exists( 'gzcompress' ) ) {
				$stream = gzcompress( $content );
				$filter = ' /Filter /FlateDecode';
			}
			$objects[ $n ]     = sprintf( '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', self::W, self::H, $n + 1 );
			$objects[ $n + 1 ] = '<< /Length ' . strlen( $stream ) . $filter . " >>\nstream\n" . $stream . "\nendstream";
			$kids[]            = $n . ' 0 R';
			$n                += 2;
		}
		$objects[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . count( $kids ) . ' >>';
		ksort( $objects );

		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objects as $num => $body ) {
			$offsets[ $num ] = strlen( $pdf );
			$pdf            .= $num . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$size = max( array_keys( $objects ) ) + 1;
		$pdf .= "xref\n0 " . $size . "\n0000000000 65535 f \n";
		for ( $i = 1; $i < $size; $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", isset( $offsets[ $i ] ) ? $offsets[ $i ] : 0 );
		}
		$pdf .= "trailer\n<< /Size " . $size . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
		return $pdf;
	}

	/* ---------------------------------------------------------------- Intern */

	private function out( $s ) {
		if ( $this->cur < 0 ) {
			$this->add_page();
		}
		$this->pages[ $this->cur ] .= $s . "\n";
	}

	private function encode( $text ) {
		$text = str_replace( array( "\xE2\x80\xAF", "\xC2\xA0" ), ' ', (string) $text ); // geschützte Leerzeichen.
		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $text, 'Windows-1252', 'UTF-8' );
		}
		if ( function_exists( 'iconv' ) ) {
			$r = iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $text );
			return false === $r ? $text : $r;
		}
		return $text;
	}

	private function escape( $s ) {
		return str_replace( array( '\\', '(', ')', "\r" ), array( '\\\\', '\\(', '\\)', '' ), $s );
	}

	private function rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$n = hexdec( str_pad( substr( $hex, 0, 6 ), 6, '0' ) );
		return sprintf( '%.3F %.3F %.3F', ( ( $n >> 16 ) & 255 ) / 255, ( ( $n >> 8 ) & 255 ) / 255, ( $n & 255 ) / 255 );
	}
}
