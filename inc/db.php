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

/** true se l'evento e' attivo e non ancora scaduto (flag + scadenza in `impostazioni`). */
function evento_aperto(): bool
{
    static $aperto = null;
    if ($aperto !== null) {
        return $aperto;
    }

    $righe = db()
        ->query("SELECT chiave, valore FROM impostazioni WHERE chiave IN ('evento_attivo', 'scadenza')")
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    $attivo   = ($righe['evento_attivo'] ?? '0') === '1';
    $scadenza = isset($righe['scadenza']) ? strtotime($righe['scadenza']) : false;

    return $aperto = $attivo && ($scadenza === false || time() < $scadenza);
}

/**
 * Tipi di giornata configurabili da admin.php ("Sessione"). Ogni tipo cambia i
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

/** Quanti livelli/round/missioni di un gioco si giocano nella sessione in corso. */
function livelli_sessione(string $modulo): int
{
    $v = sessione_attivita()['giochi'][GIOCHI[$modulo]['livelli']] ?? 0;
    return is_array($v) ? count($v) : (int) $v;
}

/**
 * La sessione configurata in admin.php (chiavi `sessione_*` in `impostazioni`,
 * nessuna colonna nuova). Valori di default = Open Day classico.
 *
 * @return array{tipo: string, data: int, titolo: string, descrizione: string,
 *               etichetta: string, nel: string, del: string, binario: string, file: string, attivita: string, merito: string,
 *               giochi: array{duck_livelli: int, binary_round: int, palla_missioni: int, porte_livelli: list<int>, hack_livelli: list<int>, durata: string}}
 */
function sessione_attivita(): array
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }

    $righe = db()
        ->query("SELECT chiave, valore FROM impostazioni WHERE chiave IN ('sessione_tipo', 'sessione_data', 'sessione_titolo', 'sessione_descrizione', 'scadenza')")
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    $tipo = $righe['sessione_tipo'] ?? 'openday';
    if (!isset(TIPI_GIORNATA[$tipo])) {
        $tipo = 'openday';
    }
    // data dell'attivita'; se non configurata, il giorno della scadenza del login (comportamento precedente)
    $data = strtotime((string) ($righe['sessione_data'] ?? '')) ?: strtotime((string) ($righe['scadenza'] ?? '')) ?: time();

    return $s = TIPI_GIORNATA[$tipo] + [
        'tipo'        => $tipo,
        'data'        => $data,
        'titolo'      => trim((string) ($righe['sessione_titolo'] ?? '')),
        'descrizione' => trim((string) ($righe['sessione_descrizione'] ?? '')),
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
