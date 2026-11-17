<?php
/**
 * Connessione PDO condivisa. Il resto (sessioni, giochi, ecc.) e' in inc/bootstrap.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Restituisce l'unica istanza PDO della richiesta (lazy).
 * In caso di errore registra il dettaglio nel log e lancia un'eccezione
 * generica, cosi' credenziali e DSN non finiscono mai nella risposta.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = DB_SOCKET !== ''
        ? 'mysql:unix_socket=' . DB_SOCKET . ';dbname=' . DB_NAME . ';charset=utf8mb4'
        : 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        error_log('[openday] connessione DB fallita: ' . $e->getMessage());
        throw new RuntimeException('Database non raggiungibile');
    }
    return $pdo;
}
