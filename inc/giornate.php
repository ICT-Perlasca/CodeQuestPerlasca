<?php
/**
 * Tipi di giornata (Open Day, Laboratori) e parametri della sessione corrente.
 */
declare(strict_types=1);

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
 *                  duck_livelli : livelli di Duck Coding giocabili (i primi N di inc/duck/livelli.php)
 *                  binary_round : round di Binary Lights (max 300 punti l'uno)
 *                  palla_missioni : missioni di "Modifica il gioco" (100 punti l'una)
 *                  porte_livelli  : QUALI livelli di "Porte logiche" (numeri da 1, vedi
 *                                   assets/js/giochi/porte-logiche/motore.js), 100 punti l'uno
 *                  hack_livelli   : QUALI livelli di "Hack the Web(site)" (numeri da 1, vedi
 *                                   assets/js/giochi/hack-website/livelli.js), 100 punti l'uno
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
