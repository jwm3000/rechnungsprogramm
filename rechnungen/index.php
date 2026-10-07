<?php
/**
 * Rechnungsprogramm
 * Einstiegsseite der App (alles Weitere lädt app.js über api.php).
 */
require __DIR__ . '/lib/bootstrap.php';
nw_session();

header( 'X-Frame-Options: SAMEORIGIN' );
header( 'X-Content-Type-Options: nosniff' );
header( 'Referrer-Policy: same-origin' );
header( "Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; frame-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'" );

$v = function ( $f ) {
	return $f . '?v=' . filemtime( __DIR__ . '/' . $f );
};
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#f4f5f7" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0f1115" media="(prefers-color-scheme: dark)">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Rechnungen">
<title>Rechnungen · <?php echo htmlspecialchars( nw_setting( 'company' ) ?: 'Rechnungsprogramm', ENT_QUOTES, 'UTF-8' ); ?></title>
<link rel="icon" href="<?php echo $v( 'assets/icon.svg' ); ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?php echo $v( 'assets/icon-180.png' ); ?>">
<link rel="manifest" href="<?php echo $v( 'assets/manifest.json' ); ?>">
<link rel="preload" href="assets/manrope.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?php echo $v( 'assets/app.css' ); ?>">
</head>
<body>
<div id="app" aria-live="polite"><div class="boot"><div class="boot-mark">[ ]</div></div></div>
<div id="toasts" role="status"></div>
<script id="logo-svg" type="text/plain"><?php echo nw_logo_svg(); // bereinigt, nur Pfade ?></script>
<script src="<?php echo $v( 'assets/app.js' ); ?>" defer></script>
</body>
</html>
