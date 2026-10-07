<?php
/**
 * Kopieren nach config.php und anpassen. config.php wird nie über die App ausgeliefert
 * (.htaccess) und gehört nicht ins Git.
 */
return array(
	// Datenbank, Originale und Belege. Am sichersten AUSSERHALB des Web-Verzeichnisses,
	// z. B. '/home/USER/rechnungen-daten'. Standard: Unterordner data/ (per .htaccess gesperrt).
	// 'data_dir' => '/home/USER/rechnungen-daten',

	// E-Mail-Versand über SMTP – am besten ein eigenes Postfach der Domain, z. B. rechnung@deine-domain.at.
	// ssl = Port 465 (TLS von Anfang an) · tls = Port 587 (STARTTLS). Unverschlüsselt ist nicht möglich.
	'smtp' => array(
		'host'      => 'mail.example.com',
		'port'      => 465,
		'secure'    => 'ssl',
		'user'      => 'rechnung@example.com',
		'pass'      => '',
		'from'      => 'rechnung@example.com',
		'from_name' => '',
		'bcc'       => '', // eigene Adresse: Kopie jeder versendeten Rechnung
	),

	// Schlüssel für cron.php?key=… (leer = wird automatisch erzeugt und in den Einstellungen angezeigt)
	'cron_key' => '',
);
