<?php
/**
 * Duck Coding: livelli e calcolo del punteggio.
 * Unica fonte dei livelli: la pagina giochi/paperelle.php li passa al browser
 * in JSON, api/codegame.php li usa per tempi di riferimento e blocchi ottimali.
 *
 * Mappa 8x8, una stringa per riga:
 *   #  sasso/cespuglio (non si passa)   .  prato
 *   P  partenza della papera            S  stagno (arrivo)
 *   o  paperella da raccogliere (prato)
 * dir: direzione iniziale 0=su 1=destra 2=giu' 3=sinistra
 * blocchi: blocchi disponibili nella cassetta degli attrezzi
 * ottimali: blocchi della soluzione migliore (bonus se non li superi)
 * rif: tempo di riferimento in secondi, usato finche' ci sono pochi dati
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const CODEGAME_MODULO = 'paperelle_coding';
const CODEGAME_PUNTI_MIN = 30;     // chi risolve, anche lentissimo, prende almeno questi
const CODEGAME_PUNTI_MAX = 130;    // tetto della parte "tempo" (= 1.3 volte piu' veloce della media)
const CODEGAME_BONUS_BLOCCHI = 20; // soluzione con il numero minimo di blocchi
const CODEGAME_PESO_RIF = 3;       // la media parte come se 3 persone avessero impiegato il tempo di riferimento
const CODEGAME_MIN_MS = 2000;      // sotto i 2 secondi non e' umano

// Livelli aperti senza risolvere i precedenti: si decide per ogni sessione da admin (sblocca_livelli() in inc/db.php).

function codegame_livelli(): array
{
    return [
        1 => [
            'titolo'   => 'Verso lo stagno',
            'testo'    => 'La paperella vuole fare il bagno! Trascina i blocchi <b>avanza</b> sotto a <b>quando premi Esegui</b> e portala fino allo stagno.',
            'mappa'    => ['########', '########', '########', '##P..S##', '########', '########', '########', '########'],
            'dir'      => 1,
            'blocchi'  => ['avanza'],
            'ottimali' => 3,
            'rif'      => 30,
        ],
        2 => [
            'titolo'   => 'Svolta!',
            'testo'    => 'Il sentiero gira. Usa <b>gira a destra</b> o <b>gira a sinistra</b>: la papera ruota sul posto, senza spostarsi.',
            'mappa'    => ['########', '########', '#P..####', '###.####', '###.####', '###S####', '########', '########'],
            'dir'      => 1,
            'blocchi'  => ['avanza', 'gira'],
            'ottimali' => 6,
            'rif'      => 45,
        ],
        3 => [
            'titolo'   => 'Raccogli i pulcini',
            'testo'    => 'Tre pulcini si sono persi! Passa sopra a tutti prima di arrivare allo stagno: ti seguiranno in fila.',
            'mappa'    => ['########', '#Po.####', '###o.###', '####o.##', '#####S##', '########', '########', '########'],
            'dir'      => 1,
            'blocchi'  => ['avanza', 'gira'],
            'ottimali' => 12,
            'rif'      => 75,
        ],
        4 => [
            'titolo'   => 'Ripeti!',
            'testo'    => 'Tanti <b>avanza</b> uno dopo l\'altro? Con <b>ripeti</b> scrivi un comando una volta sola e il computer lo esegue quante volte vuoi.',
            'mappa'    => ['########', 'P..o...#', '######.#', '######.#', '######o#', '######.#', '######S#', '########'],
            'dir'      => 1,
            'blocchi'  => ['avanza', 'gira', 'ripeti'],
            'ottimali' => 5,
            'rif'      => 70,
        ],
        5 => [
            'titolo'   => 'La scala',
            'testo'    => 'Guarda bene la forma del sentiero: c\'e\' un pezzo che si ripete. Bastano 5 blocchi!',
            'mappa'    => ['########', 'P.######', '#o.#####', '##o.####', '###o.###', '####o.##', '#####S##', '########'],
            'dir'      => 1,
            'blocchi'  => ['avanza', 'gira', 'ripeti'],
            'ottimali' => 5,
            'rif'      => 100,
        ],
        6 => [
            'titolo'   => 'Fino allo stagno',
            'testo'    => 'Non vuoi contare i gradini? <b>ripeti finche\' non arrivi</b> continua da solo e si ferma quando la papera tocca l\'acqua.',
            'mappa'    => ['######S#', '#####..#', '####o.##', '###..###', '##o.####', '#..#####', '..######', 'P#######'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'finche'],
            'ottimali' => 5,
            'rif'      => 90,
        ],
        7 => [
            'titolo'   => 'La spirale',
            'testo'    => 'Un sentiero lunghissimo! Usa <b>se c\'e\' strada</b>: la papera guarda intorno e decide da sola quando girare.',
            'mappa'    => ['########', '...o...#', '.#####.#', '.#S..#.#', 'o###.#o#', '.....#.#', '######.#', 'P......#'],
            'dir'      => 1,
            'blocchi'  => ['avanza', 'gira', 'finche', 'se'],
            'ottimali' => 4,
            'rif'      => 150,
        ],
        8 => [
            'titolo'   => 'Il labirinto',
            'testo'    => 'Sfida del labirinto: le curve vanno a destra e a sinistra. Con <b>se... altrimenti</b> scrivi un programma che trova la strada in qualsiasi labirinto!',
            'mappa'    => ['########', '##....##', '##o##.##', '##.##o##', '...##...', '.######.', '.######o', 'P####S..'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'finche', 'se', 'se_altrimenti'],
            'ottimali' => 6,
            'rif'      => 180,
        ],

        /* ---- dal 9: livelli bonus. Verificati con un simulatore identico a quello
           del gioco: tutti risolvibili, `ottimali` = blocchi della soluzione migliore. ---- */

        // ripeti avanzato
        9 => [
            'titolo'   => 'Ripeti dentro ripeti',
            'testo'    => 'Un <b>ripeti</b> si puo\' mettere dentro un altro <b>ripeti</b>! Fai il giro del laghetto, raccogli i pulcini negli angoli e torna allo stagno.',
            'mappa'    => ['########', '#o....o#', '#.####.#', '#.####.#', '#.####.#', '#.####.#', '#PS...o#', '########'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'ripeti'],
            'ottimali' => 4,
            'rif'      => 90,
        ],
        10 => [
            'titolo'   => 'Gradini lunghi',
            'testo'    => 'Gradini da tre passi: cerca il pezzo che si ripete e usa due <b>ripeti</b>, uno dentro l\'altro.',
            'mappa'    => ['P.o.####', '###.####', '###o####', '###..o.#', '######.#', '######.#', '######S#', '########'],
            'dir'      => 1,
            'blocchi'  => ['avanza', 'gira', 'ripeti'],
            'ottimali' => 7,
            'rif'      => 100,
        ],
        11 => [
            'titolo'   => 'Dietrofront',
            'testo'    => 'Il pulcino e\' in fondo a un vicolo cieco. Per tornare indietro la papera deve girarsi due volte: <b>dietrofront</b>!',
            'mappa'    => ['########', '########', '########', '########', '#o....S#', '###.####', '###.####', '###P####'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'ripeti'],
            'ottimali' => 9,
            'rif'      => 100,
        ],
        12 => [
            'titolo'   => 'La croce',
            'testo'    => 'Un pulcino in fondo a ogni braccio della croce, lo stagno nell\'ultimo. Andata, dietrofront, ritorno... e si ricomincia!',
            'mappa'    => ['###o####', '###.####', '###.####', 'o..P..S#', '###.####', '###.####', '###o####', '########'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'ripeti'],
            'ottimali' => 8,
            'rif'      => 150,
        ],
        13 => [
            'titolo'   => 'Il serpente',
            'testo'    => 'Su e giu\', su e giu\'... Il sentiero fa le curve come un serpente. Quale pezzo si ripete?',
            'mappa'    => ['########', '########', '.o.#.o.#', '.#.#.#.#', '.#.#.#.#', '.#.#.#.#', '.#.#.#.#', 'P#.o.#.S'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'ripeti'],
            'ottimali' => 13,
            'rif'      => 180,
        ],
        14 => [
            'titolo'   => 'Il prato aperto',
            'testo'    => 'Niente sentiero: puoi passare dove vuoi! Raccogli i tre pulcini e arriva allo stagno. La sfida e\' trovare il programma piu\' corto.',
            'mappa'    => ['########', '#o....o#', '#......#', '#..##..#', '#..#S..#', '#......#', '#P....o#', '########'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'ripeti'],
            'ottimali' => 8,
            'rif'      => 150,
        ],
        15 => [
            'titolo'   => 'Il pettine',
            'testo'    => 'Tre pulcini in fondo ai denti del pettine. Suggerimento: l\'ultimo giro del <b>ripeti</b> puo\' fermarsi a meta\', appena la papera tocca l\'acqua.',
            'mappa'    => ['########', '########', '########', '########', '#o#o#o##', '#.#.#.##', 'P......S', '########'],
            'dir'      => 1,
            'blocchi'  => ['avanza', 'gira', 'ripeti'],
            'ottimali' => 11,
            'rif'      => 160,
        ],

        // ripeti finche' non arrivi
        16 => [
            'titolo'   => 'Gradoni',
            'testo'    => '<b>ripeti finche\' non arrivi</b> e <b>ripeti</b> insieme: un ciclo dentro l\'altro.',
            'mappa'    => ['########', '######S#', '######.#', '####.o.#', '####.###', '##.o.###', '##o#####', 'P..#####'],
            'dir'      => 1,
            'blocchi'  => ['avanza', 'gira', 'ripeti', 'finche'],
            'ottimali' => 7,
            'rif'      => 120,
        ],
        17 => [
            'titolo'   => 'Zig-zag',
            'testo'    => 'Un passo su, due a destra, un passo su, due a destra... finche\' non arrivi!',
            'mappa'    => ['########', '########', '########', '######.S', '####.o.#', '##.o.###', '.o.#####', 'P#######'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'ripeti', 'finche'],
            'ottimali' => 6,
            'rif'      => 110,
        ],

        // se c'e' strada
        18 => [
            'titolo'   => 'Sentiero tortuoso',
            'testo'    => 'Curve a destra e a sinistra, tutte diverse. Insegna alla papera a guardarsi intorno con <b>se c\'e\' strada</b>: un programma che va bene per qualsiasi sentiero!',
            'mappa'    => ['########', '########', '##.o..##', '##.##.##', '##o##..#', '.o.###.#', '.#####S#', 'P#######'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'finche', 'se'],
            'ottimali' => 6,
            'rif'      => 150,
        ],
        19 => [
            'titolo'   => 'Spirale oraria',
            'testo'    => 'La spirale del livello 7, ma al contrario: stavolta tutte le curve sono a destra.',
            'mappa'    => ['P......o', '#######.', '.....o#.', '.####.#.', '.##S#.#.', '.##...#.', '.######.', 'o.......'],
            'dir'      => 1,
            'blocchi'  => ['avanza', 'gira', 'finche', 'se'],
            'ottimali' => 4,
            'rif'      => 90,
        ],
        20 => [
            'titolo'   => 'Le nicchie',
            'testo'    => 'I pulcini giocano a nascondino nelle nicchie a sinistra del corridoio. Quando c\'e\' una nicchia: entra, prendi il pulcino, torna indietro e riprendi la strada.',
            'mappa'    => ['########', '########', '########', '########', '########', '##o#o#o#', 'P......S', '########'],
            'dir'      => 1,
            'blocchi'  => ['avanza', 'gira', 'finche', 'se'],
            'ottimali' => 9,
            'rif'      => 180,
        ],
        21 => [
            'titolo'   => 'Il sentiero lungo',
            'testo'    => 'Il sentiero piu\' lungo di tutti, con sei pulcini. Contare i passi? Meglio un programma che guarda la strada da solo.',
            'mappa'    => ['..o#....', '.#.#o##.', 'o#...##o', '.######.', '.#.o....', '.#.#####', '.#o..S##', 'P#######'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'finche', 'se'],
            'ottimali' => 6,
            'rif'      => 150,
        ],

        // labirinti: se... altrimenti
        22 => [
            'titolo'   => 'Vicoli ciechi',
            'testo'    => 'Un labirinto con i pulcini in fondo ai vicoli ciechi. Trucco dei labirinti: tieni sempre una mano appoggiata al muro... ma quale mano?',
            'mappa'    => ['o....#.o', '.###.#.#', '.#...#.o', '.#.###.#', '...#...#', '##.#.#.#', '.....#..', 'P###...S'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'finche', 'se', 'se_altrimenti'],
            'ottimali' => 5,
            'rif'      => 150,
        ],
        23 => [
            'titolo'   => 'Mano destra',
            'testo'    => 'Attenzione: in questo labirinto c\'e\' un\'isola e chi segue il muro sbagliato gira in tondo per sempre!',
            'mappa'    => ['......#S', '.####.#.', '.#..#...', '.#.##.#.', 'P..#..#.', '##.#.##.', '...#....', 'o#...#o#'],
            'dir'      => 1,
            'blocchi'  => ['avanza', 'gira', 'finche', 'se', 'se_altrimenti'],
            'ottimali' => 5,
            'rif'      => 150,
        ],
        24 => [
            'titolo'   => 'Lo stagno sbagliato',
            'testo'    => 'Due stagni! Ma la mamma si tuffa solo quando ha tutti i pulcini: se entra nel primo stagno troppo presto, ha perso.',
            'mappa'    => ['S..#....', '##.#o##.', '...#..#.', 'o####.#.', '......#.', '.##.###.', '.#..#...', '.#.##S#P'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'finche', 'se', 'se_altrimenti'],
            'ottimali' => 5,
            'rif'      => 150,
        ],
        25 => [
            'titolo'   => 'Sempre dritto',
            'testo'    => 'Qui la mano sinistra e la mano destra girano in tondo! Prova un\'altra regola: <b>se puoi vai dritto</b>, altrimenti scegli da che parte girare.',
            'mappa'    => ['S....#..', '####o#.#', '...#...#', '.#o#.#.#', '.#...#..', '.####.#.', '......#.', '#.#.P...'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'finche', 'se', 'se_altrimenti'],
            'ottimali' => 6,
            'rif'      => 180,
        ],
        26 => [
            'titolo'   => 'Il grande labirinto',
            'testo'    => 'Il labirinto piu\' intricato, con due pulcini nascosti negli angoli. Mano destra, mano sinistra o sempre dritto? Una sola regola li trova tutti e due.',
            'mappa'    => ['o#...#.S', '.#.#.#.#', '...#.o.#', '##.####.', '.......#', '.#.##.##', '.#..#...', 'P##...#.'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'finche', 'se', 'se_altrimenti'],
            'ottimali' => 5,
            'rif'      => 180,
        ],
        27 => [
            'titolo'   => 'L\'isola dei pulcini',
            'testo'    => 'Un\'isola in mezzo al labirinto e tre pulcini sparsi. Prova le regole che conosci: una sola li porta tutti a casa.',
            'mappa'    => ['S..o...#', '.#####.#', '.#...#.#', '.#.#.#..', '...#...o', '##.####.', '.#......', '.##P##o#'],
            'dir'      => 0,
            'blocchi'  => ['avanza', 'gira', 'finche', 'se', 'se_altrimenti'],
            'ottimali' => 5,
            'rif'      => 180,
        ],
        28 => [
            'titolo'   => 'Gran finale',
            'testo'    => 'L\'ultima prova: un\'isola, uno stagno trappola e tre pulcini. Se ce la fai sei un vero Junior Hacker!',
            'mappa'    => ['...#...S', 'o#.#o#.#', '.#...#..', '.####.#.', 'P....#..', '####.#.#', '...#...#', 'o#...#.S'],
            'dir'      => 1,
            'blocchi'  => ['avanza', 'gira', 'finche', 'se', 'se_altrimenti'],
            'ottimali' => 6,
            'rif'      => 210,
        ],
    ];
}

/**
 * Punti di un livello: 100 se impieghi esattamente il tempo medio, di piu' se
 * sei piu' veloce (fino a 130), di meno se piu' lento (fino a 30). +20 se usi
 * il numero minimo di blocchi.
 */
function codegame_punti(int $ms, float $mediaMs, bool $ottimale): array
{
    $tempo = (int) round(100 * $mediaMs / max($ms, 1));
    $tempo = max(CODEGAME_PUNTI_MIN, min(CODEGAME_PUNTI_MAX, $tempo));
    $bonus = $ottimale ? CODEGAME_BONUS_BLOCCHI : 0;
    return [$tempo + $bonus, $bonus];
}

/**
 * I livelli giocabili nella sessione in corso: i primi N secondo il tipo di giornata
 * scelto in admin.php (Open Day: pochi, laboratorio pomeridiano: tutti). Vedi TIPI_GIORNATA.
 */
function codegame_livelli_attivi(): array
{
    $n = max(1, sessione_attivita()['giochi']['duck_livelli']);
    return array_slice(codegame_livelli(), 0, $n, true);
}

/** Punteggio massimo del modulo con tutti i livelli (va scritto in moduli.max_punti). */
function codegame_max_punti(): int
{
    return count(codegame_livelli()) * (CODEGAME_PUNTI_MAX + CODEGAME_BONUS_BLOCCHI);
}

/**
 * Tempo medio di un livello in ms. Media "prudente": ai tempi reali si
 * aggiungono CODEGAME_PESO_RIF tempi di riferimento, cosi' i primi che giocano
 * non vengono premiati o puniti da una media fatta su 1-2 persone.
 * La media e' della sola sessione corrente: i tempi di una demo non influenzano le altre.
 */
function codegame_media_ms(PDO $pdo, int $livello, ?int $escludiId = null): array
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) AS n, COALESCE(SUM(t.ms), 0) AS somma
           FROM codegame_tempi t JOIN partecipanti p ON p.id = t.partecipante_id
          WHERE p.sessione_id = ? AND t.livello = ? AND t.ms IS NOT NULL AND t.partecipante_id <> ?'
    );
    $st->execute([sessione_id_corrente() ?? 0, $livello, $escludiId ?? 0]);
    $r = $st->fetch();
    $rifMs = codegame_livelli()[$livello]['rif'] * 1000;
    $n = (int) $r['n'];
    return [($r['somma'] + CODEGAME_PESO_RIF * $rifMs) / ($n + CODEGAME_PESO_RIF), $n];
}
