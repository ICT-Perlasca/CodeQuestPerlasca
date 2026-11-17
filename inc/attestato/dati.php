<?php
/**
 * Attestato: dati del partecipante, testi, scadenza del download, numerazione e registro.
 * Il disegno e' in disegni.php (SVG per l'anteprima) e pdf.php (FPDF).
 */
declare(strict_types=1);

const DIPLOMA_TOKEN_MINUTI = 10;      // predefinito, modificabile per ogni sessione da admin (`sessioni.qr_minuti`)
const DIPLOMA_TOKEN_MINUTI_MAX = 1440;

/** Validita' del QR code in minuti: quella della sessione corrente, altrimenti DIPLOMA_TOKEN_MINUTI. */
function diploma_token_minuti(): int
{
    $v = sessione_corrente()['qr_minuti'] ?? null;
    $m = $v !== null ? (int) $v : DIPLOMA_TOKEN_MINUTI;
    return $m >= 1 && $m <= DIPLOMA_TOKEN_MINUTI_MAX ? $m : DIPLOMA_TOKEN_MINUTI;
}

// Il primo attestato rilasciato porta questo numero, i successivi proseguono di uno.
// Partire alto da' piu' peso al diploma; la numerazione vive nel registro permanente.
const ATTESTATO_PRIMO_NUMERO = 1287;

/**
 * Scadenza del download del diploma: quella impostata in admin/sessione.php
 * (`sessioni.diploma_scade_il`), altrimenti la mezzanotte che chiude il giorno dopo
 * l'evento (00:00 di due giorni dopo la data della sessione).
 * Indipendente dalla scadenza del login.
 */
function diploma_scadenza(): int
{
    $v = sessione_corrente()['diploma_scade_il'] ?? null;
    $t = is_string($v) && $v !== '' ? strtotime($v) : false;
    return $t !== false ? $t : diploma_scadenza_automatica();
}

function diploma_scadenza_automatica(): int
{
    return (int) strtotime('+2 days', strtotime(date('Y-m-d', sessione_attivita()['data'])));
}

/** true se gli studenti possono ancora scaricare il diploma. */
function diploma_aperto(): bool
{
    return time() < diploma_scadenza();
}

const DIPLOMA_CHIUSO_MSG = 'Il download degli attestati è chiuso. Chiedi a un membro dello staff.';

/** Data dell'attivita' ("13 dicembre 2026"): quella della sessione in admin, altrimenti il giorno della `scadenza`. */
function data_evento(): string
{
    return data_italiana(sessione_attivita()['data']);
}

/** Una parola in codice binario ASCII ("Open Day" -> "01001111 01110000 ..."), l'easter egg in cima ai fogli. */
function testo_binario(string $parola): string
{
    return implode(' ', array_map(static fn(string $c): string => sprintf('%08b', ord($c)), str_split($parola)));
}

/** Frase dell'attestato di partecipazione, secondo tipo di giornata, titolo e descrizione scelti in admin. */
function testo_partecipazione(): string
{
    $s = sessione_attivita();
    $testo = 'ha partecipato ' . $s['nel'] . " dell'indirizzo Informatica e Telecomunicazioni dell'IIS \"Giacomo Perlasca\"";
    if ($s['titolo'] !== '') {
        $testo .= ' con l\'attività «' . $s['titolo'] . '»';
    }
    $fine = $s['descrizione'] !== '' ? $s['descrizione'] : $s['attivita'];
    if (!preg_match('/[.!?…]$/u', $fine)) {
        $fine .= '.';
    }
    return $testo . ', ' . $fine;
}

/** Frase del certificato di merito per il podio. */
function testo_merito(array $p): string
{
    $s = sessione_attivita();
    return "per aver conquistato il {$p['podio']}° posto nella classifica Junior Hacker {$s['del']} con "
        . formatta_punti((int) $p['punti_totali']) . ' punti' . $s['merito'];
}

function data_italiana(int $t): string
{
    static $mesi = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
                    'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
    return date('j', $t) . ' ' . $mesi[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

/**
 * Nome e cognome facoltativi da stampare sul diploma: null se vuoto.
 * Ammessi lettere, spazi, apostrofi, punti e trattini (2-60 caratteri).
 *
 * @throws InvalidArgumentException se il nome non e' valido
 */
function nome_diploma(string $nome): ?string
{
    $nome = preg_replace('/\s+/u', ' ', trim($nome));
    if ($nome === null) {
        throw new InvalidArgumentException('Nome non valido');
    }
    if ($nome === '') {
        return null;
    }
    if (preg_match("/^\\p{L}[\\p{L}\\p{M} '’.\\-]{1,59}$/u", $nome) !== 1) {
        throw new InvalidArgumentException('Nome: 2-60 caratteri, solo lettere, spazi, apostrofi e trattini');
    }
    return $nome;
}

/**
 * Tutto cio' che va sul diploma. null se il partecipante non esiste
 * o e' stato nascosto dall'admin (nickname moderato: niente attestato).
 */
function dati_diploma(int $id): ?array
{
    $pdo = db();

    $st = $pdo->prepare('SELECT id, sessione_id, nickname, avatar, punti_totali, updated_at FROM partecipanti WHERE id = ? AND nascosto = 0');
    $st->execute([$id]);
    $p = $st->fetch();
    if ($p === false) {
        return null;
    }
    imposta_sessione((int) $p['sessione_id']); // testi, data e demo sono quelli della sessione del partecipante

    $st = $pdo->prepare(
        'SELECT m.titolo, p.punti
           FROM punteggi p JOIN moduli m ON m.codice = p.modulo
          WHERE p.partecipante_id = ? AND p.punti > 0
          ORDER BY m.percorso_ordine IS NULL, m.percorso_ordine, m.titolo'
    );
    $st->execute([$id]);
    $p['attivita'] = $st->fetchAll();

    // stessa regola di spareggio della classifica: a parita' di punti vince chi e' arrivato prima
    $p['posizione'] = null;
    if ($p['punti_totali'] > 0) {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM partecipanti
              WHERE sessione_id = :sid AND nascosto = 0
                AND (punti_totali > :punti
                     OR (punti_totali = :punti2 AND (updated_at < :agg OR (updated_at = :agg2 AND id < :id))))'
        );
        $st->execute([
            'sid' => $p['sessione_id'], 'punti' => $p['punti_totali'], 'punti2' => $p['punti_totali'],
            'agg' => $p['updated_at'], 'agg2' => $p['updated_at'], 'id' => $id,
        ]);
        $p['posizione'] = 1 + (int) $st->fetchColumn();
    }

    // certificato di merito per i primi 3 della classifica al momento del download
    $p['podio'] = $p['posizione'] !== null && $p['posizione'] <= 3 ? (int) $p['posizione'] : null;
    $p['ora_classifica'] = date('H:i');

    $p['data_evento'] = data_evento();
    // la demo non consuma numeri d'attestato: il registro numera solo quelli veri
    $p['codice'] = sessione_demo() ? 'DEMO' : date('Y') . '/' . numero_attestato($id);
    return $p;
}

/**
 * Segna nel registro permanente il download del diploma e, se indicato, il nome.
 * Un nome nuovo sostituisce il precedente; senza nome resta quello gia' salvato.
 * Crea la riga se manca (partecipanti registrati prima che esistesse il registro).
 */
function registra_diploma(int $partecipanteId, ?string $nome): void
{
    if (sessione_demo()) {
        return; // le sessioni demo non entrano nel registro permanente
    }
    db()->prepare(
        'INSERT INTO registro (uuid, nickname, nome, punti, arrivato_il, diploma_il)
         SELECT uuid, nickname, :nome, punti_totali, created_at, :ora FROM partecipanti WHERE id = :id
         ON DUPLICATE KEY UPDATE nickname = VALUES(nickname), nome = COALESCE(VALUES(nome), registro.nome),
                                 punti = VALUES(punti), diploma_il = VALUES(diploma_il)'
    )->execute(['nome' => $nome, 'ora' => date('Y-m-d H:i:s'), 'id' => $partecipanteId]);
}

/**
 * Numero progressivo dell'attestato, salvato in `registro`: assegnato la prima
 * volta che il partecipante apre il diploma, poi resta sempre lo stesso.
 * Crea la riga del registro se manca (partecipanti registrati prima che esistesse).
 */
function numero_attestato(int $partecipanteId): int
{
    $pdo = db();
    $pdo->prepare(
        'INSERT IGNORE INTO registro (uuid, nickname, punti, arrivato_il)
         SELECT uuid, nickname, punti_totali, created_at FROM partecipanti WHERE id = ?'
    )->execute([$partecipanteId]);

    $leggi = $pdo->prepare('SELECT r.numero_attestato FROM registro r JOIN partecipanti p ON p.uuid = r.uuid WHERE p.id = ?');
    $leggi->execute([$partecipanteId]);
    $numero = $leggi->fetchColumn();

    // due download nello stesso istante: il lock su MAX li mette in fila, la chiave UNIQUE fa da rete di sicurezza
    for ($tentativo = 0; $numero === null && $tentativo < 3; $tentativo++) {
        $pdo->beginTransaction();
        try {
            $max = $pdo->query('SELECT MAX(numero_attestato) FROM registro FOR UPDATE')->fetchColumn();
            $pdo->prepare(
                'UPDATE registro r JOIN partecipanti p ON p.uuid = r.uuid
                    SET r.numero_attestato = ?
                  WHERE p.id = ? AND r.numero_attestato IS NULL'
            )->execute([max((int) $max + 1, ATTESTATO_PRIMO_NUMERO), $partecipanteId]);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }
        $leggi->execute([$partecipanteId]);
        $numero = $leggi->fetchColumn();
    }
    if ($numero === null || $numero === false) {
        throw new RuntimeException('Numero attestato non assegnato');
    }
    return (int) $numero;
}

function formatta_punti(int $n): string
{
    return number_format($n, 0, ',', '.');
}

/** Nome del file scaricato: attestato-<openday|laboratori>-<nickname>.pdf (solo ASCII). */
function nome_file_diploma(array $p): string
{
    $nick = preg_replace('/[^A-Za-z0-9_\-]/', '', $p['nickname']);
    return 'attestato-' . sessione_attivita()['file'] . '-' . ($nick !== '' ? $nick : 'partecipante') . '.pdf';
}
