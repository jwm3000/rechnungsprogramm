<?php
// Router für den PHP-Entwicklungsserver: sperrt lib/, data/ und config.php wie die .htaccess.
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( preg_match( '#/(lib|data)(/|$)|/config(\.sample)?\.php$|\.sqlite#', $path ) ) {
	http_response_code( 403 );
	exit( 'Forbidden' );
}
return false;
