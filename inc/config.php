<?php
/**
 * Configurazione. Su Altervista:
 *   host = localhost, database = my_<nomeutente>, utente = <nomeutente>, password vuota.
 * La cartella inc/ e' protetta da .htaccess: non e' raggiungibile dal browser.
 */
declare(strict_types=1);

date_default_timezone_set('Europe/Rome');

// Sviluppo locale (XAMPP): config.local.php definisce le stesse costanti e
// sostituisce questo file. Non caricarlo su Altervista.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
    return;
}

const DB_HOST = 'localhost';
const DB_NAME = 'my_nomeutente';
const DB_USER = 'nomeutente';
const DB_PASS = '';
const DB_SOCKET = ''; // vuoto = connessione via host

// Account ospite condiviso, comunicato all'ingresso dell'Open Day
const OSPITE_USER = 'openday';
const OSPITE_PASS = 'binario101';

// Hash della password dell'admin. Generalo con:
//   php -r 'echo password_hash("la-tua-password", PASSWORD_DEFAULT), PHP_EOL;'
const ADMIN_PASS_HASH = '';

// true solo in sviluppo: mostra i messaggi di errore reali nelle risposte JSON
const DEBUG = false;
