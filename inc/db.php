<?php
/**
 * Connessione PDO condivisa + helper di sessione ed evento.
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

/**
 * Tipi di giornata configurabili da admin/sessione.php. Ogni tipo cambia i
 * testi di attestato e certificato di merito (PDF e anteprima) e i titoli dell'app.
 *   etichetta  : nome della giornata nei titoli ("Open Day · 13 dicembre 2026")
 *   nel        : "all'Open Day" / "ai Laboratori Pomeridiani" nelle frasi
 *   binario    : parola scritta in codice binario in cima all'attestato
 *   file       : prefisso del nome del PDF scaricato
 *   attivita   : fine della frase sull'attestato se in admin non c'e' una descrizione
 *   merito     : fine della frase del certificato di merito
 *   giochi     : quanto durano i minigiochi. L'Open Day e' una visita di ~10 minuti in tutto,
 *                il laboratorio pomeridiano dura 1 ora: stessi giochi, piu' livelli/round.
 *                  duck_livelli : livelli di Duck Coding giocabili (i primi N di inc/codegame.php)
 *                  binary_round : round di Binary Lights (max 300 punti l'uno)
 *                  palla_missioni : missioni di "Modifica il gioco" (100 punti l'una)
 *                  porte_livelli  : QUALI livelli di "Porte logiche" (numeri da 1, vedi
 *                                   assets/js/porte-logiche.js), 100 punti l'uno
 *                  hack_livelli   : QUALI livelli di "Hack the Web(site)" (numeri da 1, vedi
 *                                   assets/js/hack-livelli.js), 100 punti l'uno
 */
const TIPI_GIORNATA = [
    'openday' => [
        'etichetta' => 'Open Day',
        'nel'       => "all'Open Day",
        'del'       => "dell'Open Day",
        'binario'   => 'Open Day',
        'file'      => 'openday',
        'attivita'  => 'affrontando le sfide del percorso Junior Hacker tra bit, reti ed enigmi digitali.',
        'merito'    => ', tra codici binari, reti e programmi a blocchi.',
        'giochi'    => ['duck_livelli' => 6, 'binary_round' => 5, 'palla_missioni' => 4, 'porte_livelli' => [1, 2, 3, 4, 15, 16, 17, 18], // scatole misteriose + i livelli difficili
                         'hack_livelli' => [1, 2, 3, 4], // titolo, colore, scritta minuscola, porta chiusa
                         'durata' => 'circa 10 minuti'],
    ],
    'laboratori' => [
        'etichetta' => 'Laboratori Pomeridiani',
        'nel'       => 'ai Laboratori Pomeridiani',
        'del'       => 'dei Laboratori Pomeridiani',
        'binario'   => 'Laboratori',
        'file'      => 'laboratori',
        'attivita'  => 'mettendo alla prova logica e creatività nelle attività pratiche proposte dai docenti e dagli studenti tutor.',
        'merito'    => ', tra esperimenti, codice e lavoro di squadra.',
        'giochi'    => ['duck_livelli' => 28, 'binary_round' => 12, 'palla_missioni' => 10, 'porte_livelli' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19],
                         'hack_livelli' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
                         'durata' => 'circa 1 ora'],
    ],
];

/**
 * I giochi che esistono davvero (le altre righe di `moduli` sono idee mai realizzate o
 * attivita' tolte: restano nel DB per non rompere vecchi punteggi, ma admin non le mostra).
 *   livelli : chiave di TIPI_GIORNATA['giochi'] con quanti livelli si giocano nella sessione
 *             (int = numero, lista = quali); null per Duck Coding, che ha la sua tabella dei tempi
 *   per     : punti di un livello/missione/round superato al massimo
 *   unita   : come si chiamano i livelli di quel gioco
 */
const GIOCHI = [
    'paperelle_coding' => ['icona' => '🦆', 'pagina' => 'giochi/paperelle.php',     'livelli' => 'duck_livelli',   'per' => 150, 'unita' => 'livelli'],
    'binary_lights'    => ['icona' => '🔢', 'pagina' => 'giochi/binary-lights.php', 'livelli' => 'binary_round',   'per' => 300, 'unita' => 'round'],
    'porte_logiche'    => ['icona' => '💡', 'pagina' => 'giochi/porte-logiche.php', 'livelli' => 'porte_livelli',  'per' => 100, 'unita' => 'livelli'],
    'modifica_gioco'   => ['icona' => '🛠️', 'pagina' => 'giochi/modifica-gioco.php', 'livelli' => 'palla_missioni', 'per' => 100, 'unita' => 'missioni'],
    'hack_website'     => ['icona' => '🕵️', 'pagina' => 'giochi/hack-website.php',  'livelli' => 'hack_livelli',   'per' => 100, 'unita' => 'livelli'],
];

/**
 * Stato di un gioco, impostato da admin.php (chiave `gioco_<codice>` in `impostazioni`,
 * nessuna colonna nuova): 'attivo' (default), 'disabilitato' (card grigia, non si gioca
 * e non si salvano punti) o 'nascosto' (sparisce dalla home e non si gioca).
 */
const STATI_GIOCO = ['attivo' => 'Attivo', 'disabilitato' => 'Disabilitato (visibile ma bloccato)', 'nascosto' => 'Nascosto (non si vede)'];

function stato_gioco(string $modulo): string
{
    static $stati = null;
    $stati ??= db()->query("SELECT chiave, valore FROM impostazioni WHERE chiave LIKE 'gioco\\_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    $v = $stati["gioco_$modulo"] ?? 'attivo';
    return isset(STATI_GIOCO[$v]) ? $v : 'attivo';
}

/** Da chiamare in cima alle pagine dei giochi: se non e' attivo mostra un avviso e si ferma. */
function richiedi_gioco_attivo(string $modulo): void
{
    // legge la sessione dell'ospite (quanti livelli, che giornata) e subito rilascia il lock del file di sessione
    if (isset($_COOKIE['OPENDAY'])) {
        avvia_sessione();
        session_write_close();
    }
    if (stato_gioco($modulo) === 'attivo') {
        return;
    }
    http_response_code(403);
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Gioco non disponibile</title></head>'
       . '<body style="margin:0;min-height:100dvh;display:grid;place-items:center;background:#0b1020;color:#e8ecff;font:16px system-ui,sans-serif;text-align:center;padding:20px">'
       . '<div><h1>Questo gioco non è disponibile</h1><p style="color:#8e98c7">Per ora è stato chiuso dai docenti.</p>'
       . '<p><a href="../index.php" style="color:#5cc8ff">← Torna alle sfide</a></p></div></body></html>';
    exit;
}

/** Quanti livelli/round/missioni di un gioco si giocano nella sessione in corso. */
function livelli_sessione(string $modulo): int
{
    $v = sessione_attivita()['giochi'][GIOCHI[$modulo]['livelli']] ?? 0;
    return is_array($v) ? count($v) : (int) $v;
}

/**
 * La sessione corrente: tipo di giornata, data, titolo e descrizione (riga di `sessioni`)
 * insieme ai parametri del tipo (TIPI_GIORNATA). Senza sessione valgono i valori dell'Open Day.
 *
 * @return array{tipo: string, data: int, titolo: string, descrizione: string, nome: string, demo: bool,
 *               etichetta: string, nel: string, del: string, binario: string, file: string, attivita: string, merito: string,
 *               giochi: array{duck_livelli: int, binary_round: int, palla_missioni: int, porte_livelli: list<int>, hack_livelli: list<int>, durata: string}}
 */
function sessione_attivita(): array
{
    $r = sessione_corrente();
    $tipo = (string) ($r['tipo'] ?? 'openday');
    if (!isset(TIPI_GIORNATA[$tipo])) {
        $tipo = 'openday';
    }
    return TIPI_GIORNATA[$tipo] + [
        'tipo'        => $tipo,
        'data'        => $r ? (strtotime((string) $r['data']) ?: time()) : time(),
        'titolo'      => trim((string) ($r['titolo'] ?? '')),
        'descrizione' => trim((string) ($r['descrizione'] ?? '')),
        'nome'        => (string) ($r['nome'] ?? ''),
        'demo'        => (int) ($r['demo'] ?? 0) === 1,
    ];
}

/**
 * Tetto dei punti di un modulo per la sessione in corso: quello di `moduli.max_punti`,
 * ridotto per i giochi che all'Open Day sono piu' corti (meno round = meno punti possibili).
 */
function max_punti_sessione(string $modulo, int $maxDb): int
{
    $g = sessione_attivita()['giochi'];
    return match ($modulo) {
        'binary_lights'  => min($maxDb, $g['binary_round'] * 300),
        'modifica_gioco' => min($maxDb, $g['palla_missioni'] * 100),
        'porte_logiche'  => min($maxDb, count($g['porte_livelli']) * 100),
        'hack_website'   => min($maxDb, count($g['hack_livelli']) * 100),
        default         => $maxDb,
    };
}

/** Minuscolo, leet-speak tradotto (0->o, 1->i, ...), solo lettere: forma usata per la blacklist. */
function normalizza_nickname(string $nickname): string
{
    $norm = strtr(mb_strtolower($nickname), ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's']);
    return preg_replace('/[^\p{L}]/u', '', $norm);
}

function uuid_valido(string $uuid): bool
{
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) === 1;
}
