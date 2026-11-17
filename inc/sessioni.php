<?php
/**
 * Sessione PHP dell'ospite e sessioni di gioco (tabella `sessioni`).
 */
declare(strict_types=1);

/** Avvia la sessione PHP con cookie sicuri (una sola volta per richiesta). */
function avvia_sessione(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
    session_name('OPENDAY');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/*
 * SESSIONI. Possono essere attive piu' sessioni insieme (tabella `sessioni`): ogni ospite entra in
 * quella la cui password scrive al login (login.php) e il suo id resta in $_SESSION['sessione_id'].
 * Tutte le funzioni qui sotto lavorano sulla "sessione corrente":
 *   1. quella impostata con imposta_sessione() (admin, classifica pubblica, diploma da QR),
 *   2. altrimenti quella dell'ospite collegato ($_SESSION['sessione_id']).
 * Senza nessuna delle due valgono i valori dell'Open Day classico.
 */

/** Imposta (o con null toglie) la sessione corrente a mano, prima del cookie dell'ospite. */
function imposta_sessione(?int $id): void
{
    $GLOBALS['openday_sessione_id'] = $id;
}

function sessione_id_corrente(): ?int
{
    $id = $GLOBALS['openday_sessione_id'] ?? ($_SESSION['sessione_id'] ?? null);
    return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
}

/** Riga di `sessioni` (null se non esiste). */
function sessione_riga(int $id): ?array
{
    static $cache = [];
    if (!array_key_exists($id, $cache)) {
        $st = db()->prepare('SELECT * FROM sessioni WHERE id = ?');
        $st->execute([$id]);
        $cache[$id] = $st->fetch() ?: null;
    }
    return $cache[$id];
}

/** La riga della sessione corrente, null se non c'e'. */
function sessione_corrente(): ?array
{
    $id = sessione_id_corrente();
    return $id === null ? null : sessione_riga($id);
}

/** Stato di una sessione: 'attiva' (login aperto), 'scaduta' (passata l'ora di fine) o 'chiusa' (chiusa a mano). */
function stato_sessione(array $riga): string
{
    if (strtotime((string) $riga['scade_il']) <= time()) {
        return 'scaduta';
    }
    return (int) $riga['attiva'] === 1 ? 'attiva' : 'chiusa';
}

/** Tutte le sessioni, le piu' recenti prima. */
function elenco_sessioni(): array
{
    return db()->query('SELECT * FROM sessioni ORDER BY id DESC')->fetchAll();
}

/**
 * Sessione da mostrare quando nessuno ha scelto: la piu' recente tra quelle attive (le vere prima
 * delle demo), altrimenti la piu' recente in assoluto. null se non esiste nessuna sessione.
 */
function sessione_predefinita(): ?int
{
    $migliore = null;
    foreach (elenco_sessioni() as $r) {
        $punti = (stato_sessione($r) === 'attiva' ? 2 : 0) + ((int) $r['demo'] === 0 ? 1 : 0);
        if ($migliore === null || $punti > $migliore[0]) {
            $migliore = [$punti, (int) $r['id']];
        }
    }
    return $migliore[1] ?? null;
}

/**
 * Sessione per le pagine pubbliche (classifica): ?s=<id> se indicato e valido, altrimenti quella
 * dell'ospite collegato (se ha gia' il cookie, senza crearne uno), altrimenti sessione_predefinita().
 * La imposta come corrente e ne restituisce l'id (null se non esiste nessuna sessione).
 */
function sessione_pubblica(): ?int
{
    $id = filter_var($_GET['s'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false && isset($_COOKIE['OPENDAY'])) {
        avvia_sessione();
        $id = $_SESSION['sessione_id'] ?? false;
        session_write_close();
    }
    if ($id === false || sessione_riga((int) $id) === null) {
        $id = sessione_predefinita();
    }
    imposta_sessione($id === null ? null : (int) $id);
    return $id === null ? null : (int) $id;
}

/** Nome utente del login ospite della sessione corrente (predefinito OSPITE_USER). */
function ospite_utente(): string
{
    $u = (string) (sessione_corrente()['utente'] ?? '');
    return $u !== '' ? $u : OSPITE_USER;
}

/** Password degli ospiti della sessione corrente. */
function ospite_pass(): string
{
    return (string) (sessione_corrente()['password'] ?? '');
}

/** true se la sessione corrente e' in corso (non chiusa e non scaduta). */
function evento_aperto(): bool
{
    $r = sessione_corrente();
    return $r !== null && stato_sessione($r) === 'attiva';
}

/** true se nella sessione corrente i livelli (Duck Coding, Porte logiche, Hack, Modifica il gioco) sono tutti aperti senza risolvere i precedenti. */
function sblocca_livelli(): bool
{
    return (int) (sessione_corrente()['sblocca_tutto'] ?? 0) === 1;
}

/** true se la sessione corrente e' una demo (prova): non scrive nel registro permanente. */
function sessione_demo(): bool
{
    return (int) (sessione_corrente()['demo'] ?? 0) === 1;
}

/** true se il visitatore ha fatto il login ospite in una sessione che esiste ancora. */
function ospite_collegato(): bool
{
    return !empty($_SESSION['ospite']) && sessione_corrente() !== null;
}
