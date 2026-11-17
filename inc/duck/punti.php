<?php
/**
 * Duck Coding: costanti, calcolo del punteggio e tempi medi.
 */
declare(strict_types=1);

const CODEGAME_MODULO = 'paperelle_coding';
const CODEGAME_PUNTI_MIN = 30;     // chi risolve, anche lentissimo, prende almeno questi
const CODEGAME_PUNTI_MAX = 130;    // tetto della parte "tempo" (= 1.3 volte piu' veloce della media)
const CODEGAME_BONUS_BLOCCHI = 20; // soluzione con il numero minimo di blocchi
const CODEGAME_PESO_RIF = 3;       // la media parte come se 3 persone avessero impiegato il tempo di riferimento
const CODEGAME_MIN_MS = 2000;      // sotto i 2 secondi non e' umano

// Livelli aperti senza risolvere i precedenti: si decide per ogni sessione da admin (sblocca_livelli() in inc/sessioni.php).

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
 * scelto in admin (Open Day: pochi, laboratorio pomeridiano: tutti). Vedi TIPI_GIORNATA.
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
