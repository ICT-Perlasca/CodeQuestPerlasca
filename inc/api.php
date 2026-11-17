<?php
/**
 * Bootstrap comune agli endpoint in api/: header JSON, gestione errori,
 * lettura del body e controllo della sessione ospite.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

set_exception_handler(static function (Throwable $e): void {
    error_log('[openday] ' . $e);
    if (db_in_transazione()) {
        db()->rollBack();
    }
    json_out(['ok' => false, 'errore' => DEBUG ? $e->getMessage() : 'Errore interno, riprova'], 500);
});

function db_in_transazione(): bool
{
    try {
        return db()->inTransaction();
    } catch (Throwable) {
        return false;
    }
}

function json_out(array $dati, int $codice = 200): void
{
    http_response_code($codice);
    echo json_encode($dati, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_errore(int $codice, string $messaggio): void
{
    json_out(['ok' => false, 'errore' => $messaggio], $codice);
}

function richiedi_metodo(string $metodo): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $metodo) {
        header('Allow: ' . $metodo);
        json_errore(405, 'Metodo non consentito');
    }
}

/**
 * Legge un body JSON (max 4 KB). Richiedere Content-Type: application/json
 * blocca i POST cross-site da semplici form HTML (difesa CSRF di base).
 */
function leggi_json(): array
{
    if (!str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        json_errore(415, 'Content-Type deve essere application/json');
    }
    $raw = file_get_contents('php://input', false, null, 0, 4096);
    $dati = json_decode($raw ?: '', true);
    if (!is_array($dati)) {
        json_errore(400, 'JSON non valido');
    }
    return $dati;
}

/** Blocca la richiesta se manca il login ospite o se l'evento e' chiuso. */
function richiedi_ospite(): void
{
    avvia_sessione();
    if (!ospite_collegato()) {
        json_errore(401, 'Sessione scaduta: rifai il login');
    }
    if (!evento_aperto()) {
        json_errore(403, 'La sessione e\' terminata: grazie per aver giocato!');
    }
}
