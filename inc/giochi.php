<?php
/**
 * Catalogo dei giochi e loro stato (attivo / disabilitato / nascosto).
 */
declare(strict_types=1);

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
 * Stato di un gioco, impostato dall'admin (chiave `gioco_<codice>` in `impostazioni`,
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

