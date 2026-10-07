<?php
/**
 * Software-Update über GitHub-Releases.
 *
 * Ablauf: neuestes Release abfragen → Paket laden (Release-Anhang „rechnungen.zip“, sonst Quellcode-Zip)
 * → prüfen → Sicherung der aktuellen Programmdateien und der Datenbank → Dateien ersetzen.
 * Nie angefasst werden data/ (Datenbank, Belege) und config.php.
 */
defined( 'NW_APP' ) || exit;

function nw_update_repo() {
	$u = (array) nw_config( 'update' );
	return preg_match( '#^[\w.-]+/[\w.-]+$#', (string) ( $u['repo'] ?? '' ) ) ? $u['repo'] : '';
}

/**
 * HTTPS-GET mit Zertifikatsprüfung. Weiterleitungen werden selbst verfolgt; der Token geht nur an api.github.com.
 *
 * @return array{code:int, body:string}
 */
function nw_http_get( $url, $accept = 'application/vnd.github+json', $max = 52428800 ) {
	$token = (string) ( ( (array) nw_config( 'update' ) )['token'] ?? '' );
	for ( $hop = 0; $hop < 5; $hop++ ) {
		$host = (string) parse_url( $url, PHP_URL_HOST );
		if ( 0 !== strpos( $url, 'https://' ) || ! preg_match( '/(^|\.)(github\.com|githubusercontent\.com)$/', $host ) ) {
			throw new RuntimeException( 'Download nur von GitHub über HTTPS erlaubt (' . $host . ').' );
		}
		$headers = array( 'User-Agent: Rechnungsprogramm-Updater', 'Accept: ' . $accept );
		if ( '' !== $token && 'api.github.com' === parse_url( $url, PHP_URL_HOST ) ) {
			$headers[] = 'Authorization: Bearer ' . $token;
		}
		$location = '';
		if ( function_exists( 'curl_init' ) ) {
			$ch = curl_init( $url );
			curl_setopt_array(
				$ch,
				array(
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_FOLLOWLOCATION => false,
					CURLOPT_HTTPHEADER     => $headers,
					CURLOPT_SSL_VERIFYPEER => true,
					CURLOPT_SSL_VERIFYHOST => 2,
					CURLOPT_TIMEOUT        => 120,
					CURLOPT_HEADER         => true,
				)
			);
			$raw = curl_exec( $ch );
			if ( false === $raw ) {
				throw new RuntimeException( 'Download fehlgeschlagen: ' . curl_error( $ch ) );
			}
			$code  = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
			$hsize = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
			$head  = substr( $raw, 0, $hsize );
			$body  = substr( $raw, $hsize );
			curl_close( $ch );
			if ( preg_match( '/^location:\s*(\S+)/mi', $head, $m ) ) {
				$location = trim( $m[1] );
			}
		} else {
			$ctx  = stream_context_create(
				array(
					'http' => array( 'header' => implode( "\r\n", $headers ), 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 120 ),
					'ssl'  => array( 'verify_peer' => true, 'verify_peer_name' => true ),
				)
			);
			$body = @file_get_contents( $url, false, $ctx, 0, $max + 1 );
			if ( false === $body ) {
				throw new RuntimeException( 'Download fehlgeschlagen.' );
			}
			$code = 0;
			foreach ( $http_response_header ?? array() as $h ) {
				if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
					$code = (int) $m[1];
				} elseif ( preg_match( '/^location:\s*(\S+)/i', $h, $m ) ) {
					$location = trim( $m[1] );
				}
			}
		}
		if ( $code >= 300 && $code < 400 && '' !== $location ) {
			$url = $location;
			continue;
		}
		if ( strlen( $body ) > $max ) {
			throw new RuntimeException( 'Download ist zu groß.' );
		}
		return array( 'code' => $code, 'body' => $body );
	}
	throw new RuntimeException( 'Zu viele Weiterleitungen.' );
}

/**
 * Neuestes Release abfragen (zwischengespeichert, höchstens alle 12 Stunden – außer $force).
 */
function nw_update_check( $force = false ) {
	$repo  = nw_update_repo();
	$cache = json_decode( (string) nw_setting( 'update_cache' ), true );
	if ( ! $force && $cache && ( $cache['repo'] ?? '' ) === $repo && time() - (int) ( $cache['checked'] ?? 0 ) < 43200 ) {
		return nw_update_result( $cache );
	}
	if ( '' === $repo ) {
		return array( 'repo' => '', 'current' => NW_APP, 'error' => 'Kein GitHub-Repository eingestellt (config.php → update → repo).' );
	}
	$r = nw_http_get( 'https://api.github.com/repos/' . $repo . '/releases/latest' );
	if ( 404 === $r['code'] ) {
		$data = array( 'repo' => $repo, 'checked' => time(), 'tag' => '', 'error' => 'Noch kein Release veröffentlicht.' );
	} elseif ( 200 !== $r['code'] ) {
		$msg = json_decode( $r['body'], true )['message'] ?? ( 'HTTP ' . $r['code'] );
		throw new RuntimeException( 'GitHub: ' . $msg );
	} else {
		$rel   = json_decode( $r['body'], true );
		$asset = '';
		foreach ( (array) ( $rel['assets'] ?? array() ) as $a ) {
			if ( 'rechnungen.zip' === ( $a['name'] ?? '' ) ) {
				$asset  = $a['url']; // API-URL, funktioniert auch bei privaten Repos
				$digest = (string) ( $a['digest'] ?? '' ); // „sha256:…“, von GitHub berechnet
			}
		}
		$data = array(
			'repo'      => $repo,
			'checked'   => time(),
			'tag'       => (string) $rel['tag_name'],
			'name'      => (string) ( $rel['name'] ?? '' ),
			'notes'     => (string) ( $rel['body'] ?? '' ),
			'published' => (string) ( $rel['published_at'] ?? '' ),
			'url'       => (string) ( $rel['html_url'] ?? '' ),
			'asset'     => $asset,
			'digest'    => $digest ?? '',
			'zipball'   => (string) ( $rel['zipball_url'] ?? '' ),
		);
	}
	nw_set_setting( 'update_cache', json_encode( $data, JSON_UNESCAPED_UNICODE ) );
	return nw_update_result( $data );
}

function nw_update_result( array $d ) {
	$latest = ltrim( (string) ( $d['tag'] ?? '' ), 'vV' );
	return $d + array(
		'current' => NW_APP,
		'latest'  => $latest,
		'newer'   => '' !== $latest && version_compare( $latest, NW_APP, '>' ),
	);
}

/**
 * Einfacher ZIP-Leser (Stored/Deflate) – funktioniert ohne ZipArchive.
 *
 * @return array Pfad => Inhalt (nur Dateien)
 */
function nw_unzip( $zip ) {
	$eocd = strrpos( $zip, "PK\x05\x06" );
	if ( false === $eocd ) {
		throw new RuntimeException( 'Ungültiges ZIP-Archiv.' );
	}
	$e     = unpack( 'vdisk/vstart/ventries/vtotal/Vsize/Voffset', substr( $zip, $eocd + 4, 16 ) );
	$pos   = $e['offset'];
	$files = array();
	for ( $i = 0; $i < $e['total']; $i++ ) {
		if ( "PK\x01\x02" !== substr( $zip, $pos, 4 ) ) {
			throw new RuntimeException( 'Beschädigtes ZIP-Verzeichnis.' );
		}
		$c    = unpack( 'vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen/vclen/vdisk/vint/Vext/Voffset', substr( $zip, $pos + 4, 42 ) );
		$name = substr( $zip, $pos + 46, $c['nlen'] );
		$pos += 46 + $c['nlen'] + $c['elen'] + $c['clen'];
		if ( '/' === substr( $name, -1 ) ) {
			continue;
		}
		$l    = unpack( 'vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen', substr( $zip, $c['offset'] + 4, 26 ) );
		$raw  = substr( $zip, $c['offset'] + 30 + $l['nlen'] + $l['elen'], $c['csize'] );
		$data = 8 === $c['method'] ? @gzinflate( $raw ) : ( 0 === $c['method'] ? $raw : false );
		if ( false === $data || strlen( $data ) !== $c['usize'] || ( crc32( $data ) & 0xffffffff ) !== ( $c['crc'] & 0xffffffff ) ) {
			throw new RuntimeException( 'ZIP-Eintrag defekt: ' . $name );
		}
		$files[ $name ] = $data;
	}
	return $files;
}

/** Programmdateien (relativ zu NW_ROOT), ohne data/ und config.php. */
function nw_program_files() {
	$out = array();
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( NW_ROOT, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		$rel = ltrim( str_replace( '\\', '/', substr( $f->getPathname(), strlen( NW_ROOT ) ) ), '/' );
		if ( $f->isFile() && ! nw_update_protected( $rel ) ) {
			$out[] = $rel;
		}
	}
	return $out;
}

function nw_update_protected( $rel ) {
	return 0 === strpos( $rel, 'data/' ) || 'data' === $rel || 'config.php' === $rel;
}

/**
 * Update installieren.
 *
 * @return array{from:string, to:string, files:int, backup:string}
 */
function nw_update_install() {
	@set_time_limit( 300 );
	$info = nw_update_check( true );
	if ( empty( $info['newer'] ) ) {
		throw new RuntimeException( 'Kein neueres Release vorhanden.' );
	}
	$r = $info['asset']
		? nw_http_get( $info['asset'], 'application/octet-stream' )
		: nw_http_get( $info['zipball'], 'application/vnd.github+json' );
	if ( 200 !== $r['code'] ) {
		throw new RuntimeException( 'Paket konnte nicht geladen werden (HTTP ' . $r['code'] . ').' );
	}
	// Prüfsumme gegen die von GitHub gemeldete vergleichen (Schutz vor beschädigten oder veränderten Downloads)
	if ( $info['asset'] && preg_match( '/^sha256:([0-9a-f]{64})$/', (string) ( $info['digest'] ?? '' ), $dm ) && ! hash_equals( $dm[1], hash( 'sha256', $r['body'] ) ) ) {
		throw new RuntimeException( 'Prüfsumme des Update-Pakets stimmt nicht – Update abgebrochen, nichts geändert.' );
	}
	$entries = nw_unzip( $r['body'] );

	// Programmordner im Archiv finden: der Ordner, der VERSION, index.php und lib/bootstrap.php enthält.
	$prefix = null;
	foreach ( array_keys( $entries ) as $name ) {
		if ( preg_match( '#^(.*?)lib/bootstrap\.php$#', $name, $m ) && isset( $entries[ $m[1] . 'index.php' ], $entries[ $m[1] . 'VERSION' ] ) ) {
			$prefix = $m[1];
			break;
		}
	}
	if ( null === $prefix ) {
		throw new RuntimeException( 'Das Paket enthält kein Rechnungsprogramm.' );
	}
	$version = trim( $entries[ $prefix . 'VERSION' ] );
	if ( ! version_compare( $version, NW_APP, '>' ) ) {
		throw new RuntimeException( 'Das Paket (' . $version . ') ist nicht neuer als die installierte Version.' );
	}
	$files = array();
	foreach ( $entries as $name => $data ) {
		if ( 0 !== strpos( $name, $prefix ) ) {
			continue;
		}
		$rel = substr( $name, strlen( $prefix ) );
		if ( '' === $rel || false !== strpos( $rel, '..' ) || '/' === $rel[0] || false !== strpos( $rel, "\0" ) ) {
			throw new RuntimeException( 'Ungültiger Pfad im Paket: ' . $rel );
		}
		if ( ! nw_update_protected( $rel ) ) {
			$files[ $rel ] = $data;
		}
	}

	// Schreibrechte vorab prüfen – lieber gar nicht als halb aktualisieren.
	foreach ( array_keys( $files ) as $rel ) {
		$target = NW_ROOT . '/' . $rel;
		$dir    = dirname( $target );
		if ( ( is_file( $target ) && ! is_writable( $target ) ) || ( is_dir( $dir ) && ! is_writable( $dir ) ) ) {
			throw new RuntimeException( 'Keine Schreibrechte für ' . $rel . ' – Update abgebrochen, nichts geändert.' );
		}
	}

	// Sicherung: aktuelle Programmdateien + Datenbank
	$backup = array();
	foreach ( nw_program_files() as $rel ) {
		$backup[ 'programm/' . $rel ] = file_get_contents( NW_ROOT . '/' . $rel );
	}
	$tmp = nw_data_dir( 'updates' ) . '/db-' . bin2hex( random_bytes( 4 ) ) . '.sqlite';
	db()->exec( 'VACUUM INTO ' . db()->quote( $tmp ) );
	$backup['rechnungen.sqlite'] = file_get_contents( $tmp );
	@unlink( $tmp );
	$bname = 'sicherung-' . NW_APP . '-' . date( 'Ymd-His' ) . '.zip';
	file_put_contents( nw_data_dir( 'updates' ) . '/' . $bname, nw_zip( $backup ) );

	// Dateien schreiben (je Datei atomar über temporäre Datei + rename)
	foreach ( $files as $rel => $data ) {
		$target = NW_ROOT . '/' . $rel;
		if ( ! is_dir( dirname( $target ) ) ) {
			mkdir( dirname( $target ), 0755, true );
		}
		$t = $target . '.upd-' . bin2hex( random_bytes( 3 ) );
		file_put_contents( $t, $data );
		@chmod( $t, 0644 );
		rename( $t, $target );
	}
	if ( function_exists( 'opcache_reset' ) ) {
		@opcache_reset();
	}
	nw_log( 'Software-Update von ' . NW_APP . ' auf ' . $version . ' installiert (Sicherung: ' . $bname . ')' );
	nw_set_setting( 'update_cache', '' );
	return array( 'from' => NW_APP, 'to' => $version, 'files' => count( $files ), 'backup' => $bname );
}

/** Vorhandene Sicherungen (neueste zuerst). */
function nw_update_backups() {
	$out = array();
	foreach ( glob( nw_data_dir( 'updates' ) . '/sicherung-*.zip' ) ?: array() as $f ) {
		$out[] = array( 'name' => basename( $f ), 'size' => filesize( $f ), 'time' => date( 'Y-m-d H:i:s', filemtime( $f ) ) );
	}
	usort( $out, function ( $a, $b ) { return strcmp( $b['time'], $a['time'] ); } );
	return $out;
}

/** Programmdateien aus einer Sicherung zurückholen (die Datenbank bleibt, wie sie ist). */
function nw_update_rollback( $name ) {
	if ( ! preg_match( '/^sicherung-[\w.-]+\.zip$/', (string) $name ) ) {
		throw new RuntimeException( 'Ungültige Sicherung.' );
	}
	$file = nw_data_dir( 'updates' ) . '/' . $name;
	if ( ! is_file( $file ) ) {
		throw new RuntimeException( 'Sicherung nicht gefunden.' );
	}
	$n = 0;
	foreach ( nw_unzip( file_get_contents( $file ) ) as $path => $data ) {
		if ( 0 !== strpos( $path, 'programm/' ) ) {
			continue;
		}
		$rel = substr( $path, 9 );
		if ( false !== strpos( $rel, '..' ) || nw_update_protected( $rel ) ) {
			continue;
		}
		$target = NW_ROOT . '/' . $rel;
		if ( ! is_dir( dirname( $target ) ) ) {
			mkdir( dirname( $target ), 0755, true );
		}
		file_put_contents( $target, $data );
		$n++;
	}
	if ( function_exists( 'opcache_reset' ) ) {
		@opcache_reset();
	}
	nw_set_setting( 'update_cache', '' );
	nw_log( 'Programmstand aus ' . $name . ' wiederhergestellt' );
	return $n;
}
