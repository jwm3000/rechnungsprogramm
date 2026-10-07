<?php
/**
 * Logo: SVG aus data/logo.svg – wird gelesen, bereinigt (nur Formen, keine Skripte/Texte/Links)
 * und als Vektorgrafik ins PDF gezeichnet.
 *
 * Unterstützt: path (M L H V C S Q T Z, Bögen werden als Linie genähert), rect, circle, ellipse,
 * polygon, polyline, g – mit transform (matrix, translate, scale, rotate) und fill (Attribut oder style).
 * Text muss in Pfade umgewandelt sein.
 */
defined( 'NW_APP' ) || exit;

function nw_logo_file() {
	return nw_data_dir() . '/logo.svg';
}

/**
 * Geparstes Logo (zwischengespeichert) oder null.
 *
 * @return array{box:array, shapes:array}|null  box = [minx, miny, maxx, maxy]; shape = [fill|null, ops[]]
 */
function nw_logo() {
	static $cache = false;
	if ( false !== $cache ) {
		return $cache;
	}
	$file = nw_logo_file();
	if ( ! is_file( $file ) ) {
		return $cache = null;
	}
	$cfile = nw_data_dir() . '/logo.cache.json';
	$c     = is_file( $cfile ) ? json_decode( (string) file_get_contents( $cfile ), true ) : null;
	if ( $c && ( $c['mtime'] ?? 0 ) === filemtime( $file ) && ( $c['size'] ?? 0 ) === filesize( $file ) ) {
		return $cache = $c['logo'];
	}
	try {
		$logo = nw_logo_parse( (string) file_get_contents( $file ) );
	} catch ( Throwable $e ) {
		$logo = null;
	}
	@file_put_contents( $cfile, json_encode( array( 'mtime' => filemtime( $file ), 'size' => filesize( $file ), 'logo' => $logo ) ) );
	return $cache = $logo;
}

function nw_logo_parse( $svg ) {
	if ( strlen( $svg ) > 2000000 ) {
		throw new RuntimeException( 'Das Logo ist zu groß (max. 2 MB).' );
	}
	$prev = libxml_use_internal_errors( true );
	$doc  = new DOMDocument();
	$ok   = $doc->loadXML( $svg, LIBXML_NONET ); // keine Netzzugriffe, Entities werden nicht aufgelöst
	libxml_use_internal_errors( $prev );
	if ( ! $ok || ! $doc->documentElement || 'svg' !== strtolower( $doc->documentElement->localName ) ) {
		throw new RuntimeException( 'Keine gültige SVG-Datei.' );
	}
	$shapes = array();
	nw_logo_walk( $doc->documentElement, array( 1, 0, 0, 1, 0, 0 ), null, $shapes );
	if ( ! $shapes ) {
		throw new RuntimeException( 'Im SVG wurden keine Formen gefunden. Texte bitte in Pfade umwandeln.' );
	}
	$box = array( INF, INF, -INF, -INF );
	foreach ( $shapes as $sh ) {
		foreach ( $sh[1] as $op ) {
			for ( $i = 1; $i + 1 < count( $op ); $i += 2 ) {
				$box[0] = min( $box[0], $op[ $i ] );
				$box[1] = min( $box[1], $op[ $i + 1 ] );
				$box[2] = max( $box[2], $op[ $i ] );
				$box[3] = max( $box[3], $op[ $i + 1 ] );
			}
		}
	}
	if ( $box[2] - $box[0] <= 0 || $box[3] - $box[1] <= 0 ) {
		throw new RuntimeException( 'Das Logo ist leer.' );
	}
	return array( 'box' => $box, 'shapes' => $shapes );
}

function nw_logo_walk( DOMElement $el, array $m, $fill, array &$shapes ) {
	$name = strtolower( $el->localName );
	if ( in_array( $name, array( 'defs', 'clippath', 'mask', 'style', 'script', 'text', 'title', 'desc', 'metadata', 'symbol', 'pattern', 'lineargradient', 'radialgradient' ), true ) ) {
		return;
	}
	if ( $el->hasAttribute( 'transform' ) ) {
		$m = nw_mat_mul( $m, nw_logo_transform( $el->getAttribute( 'transform' ) ) );
	}
	$f = nw_logo_fill( $el );
	if ( null !== $f ) {
		$fill = $f;
	}
	if ( 'none' === strtolower( trim( (string) $el->getAttribute( 'display' ) ) ) ) {
		return;
	}
	$ops = null;
	$a   = function ( $n ) use ( $el ) {
		return (float) $el->getAttribute( $n );
	};
	switch ( $name ) {
		case 'path':
			$ops = nw_logo_path( $el->getAttribute( 'd' ) );
			break;
		case 'rect':
			$x  = $a( 'x' );
			$y  = $a( 'y' );
			$w  = $a( 'width' );
			$h  = $a( 'height' );
			$rx = $el->hasAttribute( 'rx' ) ? $a( 'rx' ) : $a( 'ry' );
			$ry = $el->hasAttribute( 'ry' ) ? $a( 'ry' ) : $rx;
			$rx = min( $rx, $w / 2 );
			$ry = min( $ry, $h / 2 );
			if ( $rx > 0 || $ry > 0 ) {
				$k   = 0.5523;
				$ops = array(
					array( 'M', $x + $rx, $y ), array( 'L', $x + $w - $rx, $y ),
					array( 'C', $x + $w - $rx + $k * $rx, $y, $x + $w, $y + $ry - $k * $ry, $x + $w, $y + $ry ),
					array( 'L', $x + $w, $y + $h - $ry ),
					array( 'C', $x + $w, $y + $h - $ry + $k * $ry, $x + $w - $rx + $k * $rx, $y + $h, $x + $w - $rx, $y + $h ),
					array( 'L', $x + $rx, $y + $h ),
					array( 'C', $x + $rx - $k * $rx, $y + $h, $x, $y + $h - $ry + $k * $ry, $x, $y + $h - $ry ),
					array( 'L', $x, $y + $ry ),
					array( 'C', $x, $y + $ry - $k * $ry, $x + $rx - $k * $rx, $y, $x + $rx, $y ),
					array( 'Z' ),
				);
			} else {
				$ops = array( array( 'M', $x, $y ), array( 'L', $x + $w, $y ), array( 'L', $x + $w, $y + $h ), array( 'L', $x, $y + $h ), array( 'Z' ) );
			}
			break;
		case 'circle':
		case 'ellipse':
			$cx  = $a( 'cx' );
			$cy  = $a( 'cy' );
			$rx  = 'circle' === $name ? $a( 'r' ) : $a( 'rx' );
			$ry  = 'circle' === $name ? $a( 'r' ) : $a( 'ry' );
			$k   = 0.5523;
			$ops = array(
				array( 'M', $cx + $rx, $cy ),
				array( 'C', $cx + $rx, $cy + $k * $ry, $cx + $k * $rx, $cy + $ry, $cx, $cy + $ry ),
				array( 'C', $cx - $k * $rx, $cy + $ry, $cx - $rx, $cy + $k * $ry, $cx - $rx, $cy ),
				array( 'C', $cx - $rx, $cy - $k * $ry, $cx - $k * $rx, $cy - $ry, $cx, $cy - $ry ),
				array( 'C', $cx + $k * $rx, $cy - $ry, $cx + $rx, $cy - $k * $ry, $cx + $rx, $cy ),
				array( 'Z' ),
			);
			break;
		case 'polygon':
		case 'polyline':
			preg_match_all( '/-?(?:\d+\.?\d*|\.\d+)(?:e[-+]?\d+)?/i', $el->getAttribute( 'points' ), $mm );
			$p = array_map( 'floatval', $mm[0] );
			$ops = array();
			for ( $i = 0; $i + 1 < count( $p ); $i += 2 ) {
				$ops[] = array( 0 === $i ? 'M' : 'L', $p[ $i ], $p[ $i + 1 ] );
			}
			$ops[] = array( 'Z' );
			break;
	}
	if ( $ops && 'none' !== $fill ) {
		$t = array();
		foreach ( $ops as $op ) {
			$o = array( $op[0] );
			for ( $i = 1; $i + 1 < count( $op ); $i += 2 ) {
				$o[] = round( $m[0] * $op[ $i ] + $m[2] * $op[ $i + 1 ] + $m[4], 3 );
				$o[] = round( $m[1] * $op[ $i ] + $m[3] * $op[ $i + 1 ] + $m[5], 3 );
			}
			$t[] = $o;
		}
		$shapes[] = array( $fill, $t );
	}
	foreach ( $el->childNodes as $child ) {
		if ( $child instanceof DOMElement ) {
			nw_logo_walk( $child, $m, $fill, $shapes );
		}
	}
}

/** fill aus Attribut oder style; null = erben, 'none', sonst #rrggbb (currentColor → '') */
function nw_logo_fill( DOMElement $el ) {
	$v = null;
	if ( preg_match( '/(?:^|;)\s*fill\s*:\s*([^;]+)/i', (string) $el->getAttribute( 'style' ), $m ) ) {
		$v = trim( $m[1] );
	} elseif ( $el->hasAttribute( 'fill' ) ) {
		$v = trim( $el->getAttribute( 'fill' ) );
	}
	if ( null === $v ) {
		return null;
	}
	$v = strtolower( $v );
	if ( 'none' === $v || 'transparent' === $v ) {
		return 'none';
	}
	if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $v ) ) {
		return 4 === strlen( $v ) ? '#' . $v[1] . $v[1] . $v[2] . $v[2] . $v[3] . $v[3] : $v;
	}
	if ( preg_match( '/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/', $v, $m ) ) {
		return sprintf( '#%02x%02x%02x', min( 255, $m[1] ), min( 255, $m[2] ), min( 255, $m[3] ) );
	}
	$named = array( 'black' => '#000000', 'white' => '#ffffff', 'red' => '#ff0000', 'gray' => '#808080', 'grey' => '#808080' );
	return $named[ $v ] ?? ''; // currentColor & Unbekanntes → Schriftfarbe
}

function nw_mat_mul( array $a, array $b ) {
	return array(
		$a[0] * $b[0] + $a[2] * $b[1], $a[1] * $b[0] + $a[3] * $b[1],
		$a[0] * $b[2] + $a[2] * $b[3], $a[1] * $b[2] + $a[3] * $b[3],
		$a[0] * $b[4] + $a[2] * $b[5] + $a[4], $a[1] * $b[4] + $a[3] * $b[5] + $a[5],
	);
}

function nw_logo_transform( $t ) {
	$m = array( 1, 0, 0, 1, 0, 0 );
	preg_match_all( '/(matrix|translate|scale|rotate)\s*\(([^)]*)\)/i', $t, $all, PREG_SET_ORDER );
	foreach ( $all as $x ) {
		preg_match_all( '/-?(?:\d+\.?\d*|\.\d+)(?:e[-+]?\d+)?/i', $x[2], $n );
		$v = array_map( 'floatval', $n[0] );
		switch ( strtolower( $x[1] ) ) {
			case 'matrix':
				if ( 6 === count( $v ) ) {
					$m = nw_mat_mul( $m, $v );
				}
				break;
			case 'translate':
				$m = nw_mat_mul( $m, array( 1, 0, 0, 1, $v[0] ?? 0, $v[1] ?? 0 ) );
				break;
			case 'scale':
				$m = nw_mat_mul( $m, array( $v[0] ?? 1, 0, 0, $v[1] ?? ( $v[0] ?? 1 ), 0, 0 ) );
				break;
			case 'rotate':
				$r  = deg2rad( $v[0] ?? 0 );
				$cx = $v[1] ?? 0;
				$cy = $v[2] ?? 0;
				$m  = nw_mat_mul( $m, array( 1, 0, 0, 1, $cx, $cy ) );
				$m  = nw_mat_mul( $m, array( cos( $r ), sin( $r ), -sin( $r ), cos( $r ), 0, 0 ) );
				$m  = nw_mat_mul( $m, array( 1, 0, 0, 1, -$cx, -$cy ) );
				break;
		}
	}
	return $m;
}

/** SVG-Pfad → absolute Befehle M/L/C/Z. */
function nw_logo_path( $d ) {
	preg_match_all( '/[MmLlHhVvCcSsQqTtAaZz]|-?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?/', (string) $d, $mm );
	$tok = $mm[0];
	$out = array();
	$i   = 0;
	$n   = count( $tok );
	$cmd = '';
	$x   = $y = $sx = $sy = 0.0;
	$lc  = null; // letzter Kontrollpunkt (für S/T)
	$lq  = null;
	$num = function () use ( &$i, $tok, $n ) {
		if ( $i >= $n || ! is_numeric( $tok[ $i ] ) ) {
			throw new RuntimeException( 'Pfad unvollständig.' );
		}
		return (float) $tok[ $i++ ];
	};
	while ( $i < $n ) {
		if ( ! is_numeric( $tok[ $i ] ) ) {
			$cmd = $tok[ $i++ ];
		} elseif ( '' === $cmd ) {
			throw new RuntimeException( 'Pfad ohne Befehl.' );
		}
		$rel = ctype_lower( $cmd );
		switch ( strtoupper( $cmd ) ) {
			case 'M':
				$x  = $num() + ( $rel ? $x : 0 );
				$y  = $num() + ( $rel ? $y : 0 );
				$sx = $x;
				$sy = $y;
				$out[] = array( 'M', $x, $y );
				$cmd   = $rel ? 'l' : 'L';
				$lc    = $lq = null;
				break;
			case 'L':
				$x     = $num() + ( $rel ? $x : 0 );
				$y     = $num() + ( $rel ? $y : 0 );
				$out[] = array( 'L', $x, $y );
				$lc    = $lq = null;
				break;
			case 'H':
				$x     = $num() + ( $rel ? $x : 0 );
				$out[] = array( 'L', $x, $y );
				$lc    = $lq = null;
				break;
			case 'V':
				$y     = $num() + ( $rel ? $y : 0 );
				$out[] = array( 'L', $x, $y );
				$lc    = $lq = null;
				break;
			case 'C':
			case 'S':
				if ( 'C' === strtoupper( $cmd ) ) {
					$x1 = $num() + ( $rel ? $x : 0 );
					$y1 = $num() + ( $rel ? $y : 0 );
				} else {
					$x1 = $lc ? 2 * $x - $lc[0] : $x;
					$y1 = $lc ? 2 * $y - $lc[1] : $y;
				}
				$x2    = $num() + ( $rel ? $x : 0 );
				$y2    = $num() + ( $rel ? $y : 0 );
				$ex    = $num() + ( $rel ? $x : 0 );
				$ey    = $num() + ( $rel ? $y : 0 );
				$out[] = array( 'C', $x1, $y1, $x2, $y2, $ex, $ey );
				$lc    = array( $x2, $y2 );
				$lq    = null;
				$x     = $ex;
				$y     = $ey;
				break;
			case 'Q':
			case 'T':
				if ( 'Q' === strtoupper( $cmd ) ) {
					$qx = $num() + ( $rel ? $x : 0 );
					$qy = $num() + ( $rel ? $y : 0 );
				} else {
					$qx = $lq ? 2 * $x - $lq[0] : $x;
					$qy = $lq ? 2 * $y - $lq[1] : $y;
				}
				$ex    = $num() + ( $rel ? $x : 0 );
				$ey    = $num() + ( $rel ? $y : 0 );
				$out[] = array( 'C', $x + 2 / 3 * ( $qx - $x ), $y + 2 / 3 * ( $qy - $y ), $ex + 2 / 3 * ( $qx - $ex ), $ey + 2 / 3 * ( $qy - $ey ), $ex, $ey );
				$lq    = array( $qx, $qy );
				$lc    = null;
				$x     = $ex;
				$y     = $ey;
				break;
			case 'A':
				for ( $k = 0; $k < 5; $k++ ) {
					$num();
				}
				$x     = $num() + ( $rel ? $x : 0 );
				$y     = $num() + ( $rel ? $y : 0 );
				$out[] = array( 'L', $x, $y ); // Bögen vereinfacht
				$lc    = $lq = null;
				break;
			case 'Z':
				$out[] = array( 'Z' );
				$x     = $sx;
				$y     = $sy;
				$lc    = $lq = null;
				$cmd   = '';
				break;
		}
	}
	return $out;
}

/** Bereinigtes SVG zum Einbetten in die Seite (nur Pfade, Farbe „currentColor“ für Schriftfarbe). */
function nw_logo_svg() {
	$logo = nw_logo();
	if ( ! $logo ) {
		return '';
	}
	$b   = $logo['box'];
	$out = sprintf( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="%s %s %s %s" role="img" aria-label="Logo">', round( $b[0], 2 ), round( $b[1], 2 ), round( $b[2] - $b[0], 2 ), round( $b[3] - $b[1], 2 ) );
	foreach ( $logo['shapes'] as $sh ) {
		$d = '';
		foreach ( $sh[1] as $op ) {
			$d .= $op[0] . ( count( $op ) > 1 ? implode( ' ', array_slice( $op, 1 ) ) : '' );
		}
		$out .= '<path fill="' . ( $sh[0] ? $sh[0] : 'currentColor' ) . '" d="' . $d . '"/>';
	}
	return $out . '</svg>';
}
