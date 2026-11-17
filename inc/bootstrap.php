<?php
/**
 * Carica tutto il necessario alle pagine: configurazione, database, sessioni, giochi.
 * Le pagine fanno un solo require di questo file.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sessioni.php';
require_once __DIR__ . '/giornate.php';
require_once __DIR__ . '/giochi.php';
require_once __DIR__ . '/profilo.php';
require_once __DIR__ . '/punteggi.php';
require_once __DIR__ . '/asset.php';
