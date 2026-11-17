<?php
/**
 * Base comune delle pagine admin (docenti/tutor): login, controllo CSRF, azioni POST, menu laterale.
 * Accesso con la password il cui hash e' in ADMIN_PASS_HASH (inc/config.php).
 * Tutte le azioni sono POST con token CSRF e terminano con redirect (PRG) sulla pagina da cui arrivano.
 *
 * Pagine: index (panoramica), sessione, giochi, partecipanti, registro, sistema.
 * Il codice e' diviso in admin/_inc/: layout, registro, sessione, auth, azioni.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/duck.php';
require_once __DIR__ . '/../inc/attestato.php'; // testi e nome del PDF per l'anteprima della sessione

avvia_sessione();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

$e = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES);

require_once __DIR__ . '/_inc/layout.php';
require_once __DIR__ . '/_inc/registro.php';
require_once __DIR__ . '/_inc/sessione.php';
require_once __DIR__ . '/_inc/auth.php';
require_once __DIR__ . '/_inc/azioni.php';
