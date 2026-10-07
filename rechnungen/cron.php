<?php
/**
 * Fällige Dauerrechnungen erstellen und versenden.
 *
 * Beim Webhoster als Cronjob einrichten (täglich, z. B. 7:00 Uhr):
 *   php /pfad/zu/rechnungen/cron.php
 * oder per URL (Schlüssel siehe Einstellungen → E-Mail & Automatik):
 *   https://deine-domain.at/rechnungen/cron.php?key=…
 */
require __DIR__ . '/lib/bootstrap.php';

$cli = 'cli' === PHP_SAPI;
if ( ! $cli && ! hash_equals( nw_cron_key(), (string) ( $_GET['key'] ?? '' ) ) ) {
	http_response_code( 403 );
	exit( 'Forbidden' );
}
header( 'Content-Type: text/plain; charset=utf-8' );
$done = nw_recurring_run_due();
if ( ! $done ) {
	echo "Nichts fällig.\n";
}
foreach ( $done as $d ) {
	echo isset( $d['error'] )
		? 'Fehler bei Dauerrechnung ' . $d['recurring_id'] . ': ' . $d['error'] . "\n"
		: $d['invoice'] . ' · ' . $d['customer'] . ( $d['mailed'] ? ' · versendet' : '' ) . ( $d['note'] ? ' · ' . $d['note'] : '' ) . "\n";
}
