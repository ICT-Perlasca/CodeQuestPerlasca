<?php
/**
 * Diploma (attestato di partecipazione): dati del partecipante e disegno del PDF.
 * Usato da diploma.php (anteprima), diploma_pdf.php (download) e api/diploma_token.php (QR).
 *
 * Il PDF e' generato con FPDF 1.86 (inc/lib/fpdf, licenza libera) e i font base
 * del PDF: nessuna estensione PHP richiesta oltre a mbstring, funziona su Altervista.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const DIPLOMA_TOKEN_MINUTI = 10;

// Il primo attestato rilasciato porta questo numero, i successivi proseguono di uno.
// Partire alto da' piu' peso al diploma; la numerazione vive nel registro permanente.
const ATTESTATO_PRIMO_NUMERO = 1287;

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

    $st = $pdo->prepare('SELECT id, nickname, avatar, punti_totali, updated_at FROM partecipanti WHERE id = ? AND nascosto = 0');
    $st->execute([$id]);
    $p = $st->fetch();
    if ($p === false) {
        return null;
    }

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
              WHERE nascosto = 0
                AND (punti_totali > :punti
                     OR (punti_totali = :punti2 AND (updated_at < :agg OR (updated_at = :agg2 AND id < :id))))'
        );
        $st->execute([
            'punti' => $p['punti_totali'], 'punti2' => $p['punti_totali'],
            'agg' => $p['updated_at'], 'agg2' => $p['updated_at'], 'id' => $id,
        ]);
        $p['posizione'] = 1 + (int) $st->fetchColumn();
    }

    // certificato di merito per i primi 3 della classifica al momento del download
    $p['podio'] = $p['posizione'] !== null && $p['posizione'] <= 3 ? (int) $p['posizione'] : null;
    $p['ora_classifica'] = date('H:i');

    $p['data_evento'] = data_evento();
    $p['codice'] = date('Y') . '/' . numero_attestato($id);
    return $p;
}

/**
 * Segna nel registro permanente il download del diploma e, se indicato, il nome.
 * Un nome nuovo sostituisce il precedente; senza nome resta quello gia' salvato.
 * Crea la riga se manca (partecipanti registrati prima che esistesse il registro).
 */
function registra_diploma(int $partecipanteId, ?string $nome): void
{
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

/** Invia il PDF del diploma come download e termina. */
function invia_diploma_pdf(array $p, ?string $nome): never
{
    $pdf = disegna_diploma($p, $nome)->Output('S');
    $file = nome_file_diploma($p);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
    exit;
}

/* --------------------------------------------------------------- paperelle */

/**
 * Posizioni (mm, A4 orizzontale) delle paperelle in basso a sinistra: le usano
 * sia il PDF sia l'anteprima HTML (paperelle_svg), cosi' restano allineati.
 */
const PAPERELLE_DIPLOMA = [
    'impronte' => [[64, 198.1], [67.2, 198.1], [70.4, 198.1], [73.6, 198.1], [76.8, 198.1], [80, 198.1]],
    'papere'   => [[88, 198.7, 5.2], [96, 198.7, 5.2], [104, 198.7, 5.2], [116, 198.7, 9]], // piedi x, y, altezza
    'fumetto'  => [97, 178.5, 27, 6.2],                                                       // x, y, larghezza, altezza
    'punta'    => [[114, 184.7], [117.5, 184.7], [118.5, 188.2]],
    'testo'    => 'Quack, World!',
];

/** Numero per gli attributi SVG: 2 decimali al massimo, niente zeri inutili. */
function svg_num(float $n): string
{
    return rtrim(rtrim(sprintf('%.2F', $n), '0'), '.');
}

function svg_punti(array $punti): string
{
    return implode(' ', array_map(static fn(array $p): string => svg_num($p[0]) . ',' . svg_num($p[1]), $punti));
}

/** Paperella in SVG: stessa forma di DiplomaPdf::Paperella (piedi in x, y; alta h mm). */
function papera_svg(float $x, float $y, float $h, bool $corona = false): string
{
    $f = 'svg_num';
    $out = '<g stroke-width="' . $f(max(0.15, $h * 0.025)) . '">'
        . '<path d="M' . $f($x - .1 * $h) . ' ' . $f($y - .12 * $h) . 'V' . $f($y) . 'h' . $f(.12 * $h)
        . 'M' . $f($x + .1 * $h) . ' ' . $f($y - .12 * $h) . 'V' . $f($y) . 'h' . $f(.12 * $h) . '" stroke="#ff8c1a" fill="none"/>'
        . '<g fill="#ffd84d" stroke="#c89600">'
        . '<polygon points="' . svg_punti([[$x - .38 * $h, $y - .42 * $h], [$x - .62 * $h, $y - .62 * $h], [$x - .5 * $h, $y - .3 * $h]]) . '"/>'
        . '<ellipse cx="' . $f($x) . '" cy="' . $f($y - .36 * $h) . '" rx="' . $f(.48 * $h) . '" ry="' . $f(.25 * $h) . '"/>'
        . '<circle cx="' . $f($x + .33 * $h) . '" cy="' . $f($y - .73 * $h) . '" r="' . $f(.22 * $h) . '"/></g>'
        . '<ellipse cx="' . $f($x - .06 * $h) . '" cy="' . $f($y - .38 * $h) . '" rx="' . $f(.24 * $h) . '" ry="' . $f(.12 * $h) . '" fill="#f2c200"/>'
        . '<polygon points="' . svg_punti([[$x + .5 * $h, $y - .78 * $h], [$x + .75 * $h, $y - .71 * $h], [$x + .5 * $h, $y - .64 * $h]]) . '" fill="#ff8c1a" stroke="#c95f00"/>'
        . '<circle cx="' . $f($x + .4 * $h) . '" cy="' . $f($y - .78 * $h) . '" r="' . $f(.035 * $h) . '" fill="#1a1a1a"/>';
    if ($corona) {
        $out .= '<polygon points="' . svg_punti(punti_corona($x, $y, $h)) . '" fill="#ffd84d" stroke="#c89600"/>';
    }
    return $out . '</g>';
}

/** Coroncina sulla testa della paperella prima classificata. */
function punti_corona(float $x, float $y, float $h): array
{
    $cx = $x + 0.33 * $h;
    $b = $y - 0.9 * $h;   // base, appoggiata sulla testa
    $t = $y - 1.12 * $h;  // punte
    $w = 0.17 * $h;
    return [[$cx - $w, $b], [$cx - $w, $t], [$cx - $w / 2, $b - 0.1 * $h], [$cx, $t - 0.02 * $h],
            [$cx + $w / 2, $b - 0.1 * $h], [$cx + $w, $t], [$cx + $w, $b]];
}

/** Le paperelle del diploma in SVG (viewBox in mm del foglio) per l'anteprima in diploma.php. */
function paperelle_svg(): string
{
    $f = 'svg_num';
    $out = '';
    foreach (PAPERELLE_DIPLOMA['impronte'] as [$x, $y]) {
        $out .= '<circle cx="' . $f($x) . '" cy="' . $f($y) . '" r=".45" fill="#cde1f0"/>';
    }
    foreach (PAPERELLE_DIPLOMA['papere'] as [$x, $y, $h]) {
        $out .= papera_svg($x, $y, $h);
    }
    [$fx, $fy, $fw, $fh] = PAPERELLE_DIPLOMA['fumetto'];
    $out .= '<g fill="#fff" stroke="#5cc8ff" stroke-width=".3">'
        . '<polygon points="' . svg_punti(PAPERELLE_DIPLOMA['punta']) . '"/>'
        . '<rect x="' . $f($fx) . '" y="' . $f($fy) . '" width="' . $f($fw) . '" height="' . $f($fh) . '"/>'
        . '<rect x="' . $f($fx + 17.2) . '" y="' . $f($fy + $fh - .5) . '" width="3.1" height=".7" stroke="none"/></g>'
        . '<text x="' . $f($fx + $fw / 2) . '" y="' . $f($fy + 4.2) . '" text-anchor="middle" font-family="Courier New, Courier, monospace" font-size="2.65" fill="#0b1020">'
        . htmlspecialchars(PAPERELLE_DIPLOMA['testo'], ENT_QUOTES) . '</text>';
    return '<svg class="paperelle" viewBox="0 0 297 210" aria-hidden="true">' . $out . '</svg>';
}

/* --------------------------------------------------------------- podio */

/** Colori della medaglia: [nome, riempimento, bordo] */
const MEDAGLIE = [
    1 => ['ORO',     [255, 216, 77],  [200, 150, 0]],
    2 => ['ARGENTO', [214, 219, 228], [128, 136, 152]],
    3 => ['BRONZO',  [233, 170, 115], [160, 95, 45]],
];

/** Gradini del podio (mm): posizione => [x, y del piano, larghezza]; la base e' a PODIO_BASE. */
const PODIO = [2 => [112.5, 149, 24], 1 => [136.5, 144, 24], 3 => [160.5, 152, 24]];
const PODIO_BASE = 160.0;

function rgb_hex(array $c): string
{
    return sprintf('#%02x%02x%02x', ...$c);
}

/** Il podio con le paperelle in SVG, per l'anteprima del certificato di merito. */
function podio_svg(int $posto): string
{
    $f = 'svg_num';
    $out = '';
    foreach (PODIO as $n => [$x, $y, $w]) {
        $mio = $n === $posto;
        $out .= '<rect x="' . $f($x) . '" y="' . $f($y) . '" width="' . $f($w) . '" height="' . $f(PODIO_BASE - $y) . '" fill="'
            . ($mio ? rgb_hex(MEDAGLIE[$n][1]) : '#eef7fd') . '" stroke="' . ($mio ? rgb_hex(MEDAGLIE[$n][2]) : '#5cc8ff') . '" stroke-width=".35"/>'
            . '<text x="' . $f($x + $w / 2) . '" y="' . $f($y + 6.2) . '" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-weight="700" font-size="5" fill="#0b1020">' . $n . '</text>'
            . papera_svg($x + $w / 2 - 0.5, $y - 0.2, $n === 1 ? 10 : 8.5, $n === 1);
    }
    return '<svg class="paperelle" viewBox="0 0 297 210" aria-hidden="true">' . $out . '</svg>';
}

/* --------------------------------------------------------------- disegno */

define('FPDF_FONTPATH', __DIR__ . '/lib/fpdf/font/');
require_once __DIR__ . '/lib/fpdf/fpdf.php';

/** FPDF + cerchi e testo spaziato, che la libreria base non ha. */
final class DiplomaPdf extends FPDF
{
    /** Ellisse di centro (x, y) e semiassi rx, ry. Stile come Rect. */
    public function Ellisse(float $x, float $y, float $rx, float $ry, string $stile = 'D'): void
    {
        $cx = 0.5523 * $rx;
        $cy = 0.5523 * $ry;
        $pt = fn(float $px, float $py): string => sprintf('%.2F %.2F', $px * $this->k, ($this->h - $py) * $this->k);
        $op = match ($stile) { 'F' => 'f', 'DF', 'FD' => 'B', default => 'S' };

        $this->_out($pt($x + $rx, $y) . ' m');
        $this->_out($pt($x + $rx, $y + $cy) . ' ' . $pt($x + $cx, $y + $ry) . ' ' . $pt($x, $y + $ry) . ' c');
        $this->_out($pt($x - $cx, $y + $ry) . ' ' . $pt($x - $rx, $y + $cy) . ' ' . $pt($x - $rx, $y) . ' c');
        $this->_out($pt($x - $rx, $y - $cy) . ' ' . $pt($x - $cx, $y - $ry) . ' ' . $pt($x, $y - $ry) . ' c');
        $this->_out($pt($x + $cx, $y - $ry) . ' ' . $pt($x + $rx, $y - $cy) . ' ' . $pt($x + $rx, $y) . ' c');
        $this->_out($op);
    }

    public function Cerchio(float $x, float $y, float $r, string $stile = 'D'): void
    {
        $this->Ellisse($x, $y, $r, $r, $stile);
    }

    /** Poligono chiuso dai punti [[x, y], ...]. Stile come Rect. */
    public function Poligono(array $punti, string $stile = 'D'): void
    {
        $op = match ($stile) { 'F' => 'f', 'DF', 'FD' => 'b', default => 's' };
        foreach ($punti as $i => [$x, $y]) {
            $this->_out(sprintf('%.2F %.2F %s', $x * $this->k, ($this->h - $y) * $this->k, $i === 0 ? 'm' : 'l'));
        }
        $this->_out($op);
    }

    /**
     * Paperella vista di lato, rivolta a destra, con i piedi in (x, y) e alta circa h mm.
     * Stessi colori delle paperelle del gioco Duck Coding.
     */
    public function Paperella(float $x, float $y, float $h): void
    {
        $this->SetLineWidth(max(0.15, $h * 0.025));
        $this->SetDrawColor(255, 140, 26);
        $this->Line($x - 0.1 * $h, $y - 0.12 * $h, $x - 0.1 * $h, $y);           // zampe
        $this->Line($x + 0.1 * $h, $y - 0.12 * $h, $x + 0.1 * $h, $y);
        $this->Line($x - 0.1 * $h, $y, $x + 0.02 * $h, $y);
        $this->Line($x + 0.1 * $h, $y, $x + 0.22 * $h, $y);

        $this->SetDrawColor(200, 150, 0);
        $this->SetFillColor(255, 216, 77);
        $this->Poligono([[$x - 0.38 * $h, $y - 0.42 * $h], [$x - 0.62 * $h, $y - 0.62 * $h], [$x - 0.5 * $h, $y - 0.3 * $h]], 'DF'); // coda
        $this->Ellisse($x, $y - 0.36 * $h, 0.48 * $h, 0.25 * $h, 'DF');           // corpo
        $this->Cerchio($x + 0.33 * $h, $y - 0.73 * $h, 0.22 * $h, 'DF');         // testa
        $this->SetFillColor(242, 194, 0);
        $this->Ellisse($x - 0.06 * $h, $y - 0.38 * $h, 0.24 * $h, 0.12 * $h, 'F'); // ala

        $this->SetFillColor(255, 140, 26);
        $this->SetDrawColor(201, 95, 0);
        $this->Poligono([[$x + 0.5 * $h, $y - 0.78 * $h], [$x + 0.75 * $h, $y - 0.71 * $h], [$x + 0.5 * $h, $y - 0.64 * $h]], 'DF'); // becco
        $this->SetFillColor(26, 26, 26);
        $this->Cerchio($x + 0.4 * $h, $y - 0.78 * $h, 0.035 * $h, 'F');           // occhio
    }

    public function Corona(float $x, float $y, float $h): void
    {
        $this->SetFillColor(255, 216, 77);
        $this->SetDrawColor(200, 150, 0);
        $this->SetLineWidth(max(0.15, $h * 0.025));
        $this->Poligono(punti_corona($x, $y, $h), 'DF');
    }

    /** Testo centrato su cx alla linea di base y, con spaziatura extra tra le lettere (mm). */
    public function TestoCentrato(float $cx, float $y, string $testo, float $spazio = 0.0): void
    {
        $testo = self::cp1252($testo);
        $n = strlen($testo);
        $larghezza = $this->GetStringWidth($testo) + $spazio * max(0, $n - 1);
        if ($spazio > 0) {
            $this->_out(sprintf('%.3F Tc', $spazio * $this->k));
        }
        $this->Text($cx - $larghezza / 2, $y, $testo);
        if ($spazio > 0) {
            $this->_out('0 Tc');
        }
    }

    /** I font base del PDF usano Windows-1252: i caratteri fuori set diventano "?". */
    public static function cp1252(string $s): string
    {
        return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }
}

/** Colori dell'app (blu notte, azzurro, oro) su fondo bianco per la stampa. */
const COL_BLU = [11, 16, 32];
const COL_AZZURRO = [92, 200, 255];
const COL_AZZURRO_SCURO = [10, 110, 170];
const COL_ORO = [255, 216, 77];
const COL_ORO_SCURO = [200, 150, 0];
const COL_GRIGIO = [95, 104, 140];

/**
 * PDF A4 orizzontale: attestato di partecipazione (uguale per tutti, senza
 * punti ne' classifica) e, per i primi 3, una seconda pagina con il
 * certificato di merito. Un solo file: si scaricano insieme.
 */
function disegna_diploma(array $p, ?string $nome): DiplomaPdf
{
    $pdf = new DiplomaPdf('L', 'mm', 'A4');
    $pdf->SetTitle(DiplomaPdf::cp1252('Attestato di partecipazione - ' . sessione_attivita()['etichetta'] . ' IIS Giacomo Perlasca'));
    $pdf->SetAuthor(DiplomaPdf::cp1252('IIS "Giacomo Perlasca" - Informatica e Telecomunicazioni'));
    $pdf->SetCreator(DiplomaPdf::cp1252(sessione_attivita()['etichetta'] . ' Perlasca'));
    $pdf->SetAutoPageBreak(false);
    $pdf->SetMargins(0, 0, 0);

    pagina_partecipazione($pdf, $p, $nome);
    if ($p['podio'] !== null) {
        pagina_merito($pdf, $p, $nome, $p['podio']);
    }
    return $pdf;
}

/** Cornice, piste da circuito, binario e intestazione della scuola: comuni alle due pagine. */
function pagina_base(DiplomaPdf $pdf, string $titolo, string $sottotitolo, array $coloreRiga): float
{
    $pdf->AddPage();
    $W = $pdf->GetPageWidth();   // 297
    $H = $pdf->GetPageHeight();  // 210
    $cx = $W / 2;

    // cornice doppia + "piste" da circuito stampato negli angoli
    $pdf->SetDrawColor(...COL_BLU);
    $pdf->SetLineWidth(1.2);
    $pdf->Rect(8, 8, $W - 16, $H - 16);
    $pdf->SetDrawColor(...COL_AZZURRO);
    $pdf->SetLineWidth(0.4);
    $pdf->Rect(11, 11, $W - 22, $H - 22);
    $pdf->SetFillColor(...COL_AZZURRO);
    foreach ([[11, 11, 1, 1], [$W - 11, 11, -1, 1], [11, $H - 11, 1, -1], [$W - 11, $H - 11, -1, -1]] as [$x, $y, $dx, $dy]) {
        $pdf->Line($x, $y + 9 * $dy, $x + 6 * $dx, $y + 9 * $dy);
        $pdf->Line($x + 6 * $dx, $y + 9 * $dy, $x + 11 * $dx, $y + 14 * $dy);
        $pdf->Cerchio($x + 11 * $dx, $y + 14 * $dy, 1.1, 'F');
        $pdf->Line($x + 9 * $dx, $y, $x + 9 * $dx, $y + 5 * $dy);
        $pdf->Cerchio($x + 9 * $dx, $y + 5 * $dy, 1.1, 'F');
    }

    // "Open Day" (o "Laboratori") in ASCII binario: un easter egg per chi ha giocato a Binary Lights
    $pdf->SetFont('Courier', '', 7.5);
    $pdf->SetTextColor(160, 200, 225);
    $pdf->TestoCentrato($cx, 21, testo_binario(sessione_attivita()['binario']), 0.4);

    $pdf->SetTextColor(...COL_BLU);
    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->TestoCentrato($cx, 31, 'IIS "GIACOMO PERLASCA"', 1.2);
    $pdf->SetTextColor(...COL_GRIGIO);
    $pdf->SetFont('Helvetica', '', 10.5);
    $pdf->TestoCentrato($cx, 37.5, 'Indirizzo Informatica e Telecomunicazioni');

    $pdf->SetTextColor(...COL_BLU);
    $pdf->SetFont('Helvetica', 'B', 28);
    $pdf->TestoCentrato($cx, 56, $titolo, 0.6);

    $pdf->SetDrawColor(...$coloreRiga);
    $pdf->SetLineWidth(0.8);
    $pdf->Line($cx - 30, 61.5, $cx + 30, 61.5);
    $pdf->SetTextColor(...COL_AZZURRO_SCURO);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->TestoCentrato($cx, 69, mb_strtoupper($sottotitolo), 1.5);
    return $cx;
}

/**
 * Paragrafo centrato da y, al massimo 3 righe: se la descrizione scelta in admin
 * e' lunga il corpo scende (fino a 10 pt) per non finire sopra i riquadri sotto.
 */
function paragrafo(DiplomaPdf $pdf, float $y, string $testo): void
{
    $larghezza = $pdf->GetPageWidth() - 86;
    $testo = DiplomaPdf::cp1252($testo);
    $corpo = 13.5;
    $pdf->SetFont('Times', '', $corpo);
    while ($corpo > 10 && $pdf->GetStringWidth($testo) > 2.8 * ($larghezza - 4)) {
        $corpo -= 0.5;
        $pdf->SetFont('Times', '', $corpo);
    }
    $pdf->SetTextColor(...COL_BLU);
    $pdf->SetXY(43, $y);
    $pdf->MultiCell($larghezza, $corpo * 0.47, $testo, 0, 'C');
}

/** Nome (o nickname) grande con la riga dorata sotto; restituisce la y da cui prosegue il testo. */
function blocco_nome(DiplomaPdf $pdf, array $p, ?string $nome, string $introduzione): float
{
    $cx = $pdf->GetPageWidth() / 2;
    $pdf->SetTextColor(...COL_GRIGIO);
    $pdf->SetFont('Times', 'I', 15);
    $pdf->TestoCentrato($cx, 83, $introduzione);

    $intestatario = $nome ?? $p['nickname'];
    $pdf->SetTextColor(...COL_BLU);
    $corpo = 34;
    do {
        $pdf->SetFont('Times', 'B', $corpo);
        $larghezza = $pdf->GetStringWidth(DiplomaPdf::cp1252($intestatario));
        $corpo -= 1;
    } while ($larghezza > 220 && $corpo > 14);
    $pdf->TestoCentrato($cx, 98, $intestatario);

    $pdf->SetDrawColor(...COL_ORO_SCURO);
    $pdf->SetLineWidth(0.6);
    $mezzo = max(40, $larghezza / 2 + 8);
    $pdf->Line($cx - $mezzo, 102, $cx + $mezzo, 102);

    if ($nome !== null) {
        $pdf->SetTextColor(...COL_GRIGIO);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->TestoCentrato($cx, 108.5, 'nome in codice «' . $p['nickname'] . '»');
        return 114;
    }
    return 112;
}

/** Piede: numero a sinistra, motto a destra. */
function piede(DiplomaPdf $pdf, string $riga1, string $riga2): void
{
    $pdf->SetTextColor(...COL_GRIGIO);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->Text(30, 183, DiplomaPdf::cp1252($riga1));
    $pdf->Text(30, 188, DiplomaPdf::cp1252($riga2));

    // niente riga per la firma: il diploma si stampa a casa, al suo posto un motto (allineato a destra)
    $destra = $pdf->GetPageWidth() - 30;
    $motto = DiplomaPdf::cp1252('Il futuro si programma.');
    $pdf->SetTextColor(...COL_BLU);
    $pdf->SetFont('Times', 'I', 12);
    $pdf->Text($destra - $pdf->GetStringWidth($motto), 183, $motto);
    $invito = DiplomaPdf::cp1252("Ti aspettiamo all'IIS Perlasca!");
    $pdf->SetTextColor(...COL_GRIGIO);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->Text($destra - $pdf->GetStringWidth($invito), 188, $invito);
}

/** Le tre "competenze sbloccate": uguali per tutti, nessun confronto tra partecipanti. */
const COMPETENZE = ['Logica', 'Creatività', 'Curiosità'];

function pagina_partecipazione(DiplomaPdf $pdf, array $p, ?string $nome): void
{
    $cx = pagina_base($pdf, 'ATTESTATO DI PARTECIPAZIONE', sessione_attivita()['etichetta'] . ' · ' . $p['data_evento'], COL_AZZURRO);
    $y = blocco_nome($pdf, $p, $nome, 'Si attesta che');
    paragrafo($pdf, $y, testo_partecipazione());

    $lb = 54;
    $gap = 8;
    $x = $cx - (3 * $lb + 2 * $gap) / 2;
    $y = 136;
    $pdf->SetLineWidth(0.3);
    foreach (COMPETENZE as $competenza) {
        $pdf->SetFillColor(238, 247, 253);
        $pdf->SetDrawColor(...COL_AZZURRO);
        $pdf->Rect($x, $y, $lb, 17, 'DF');
        $pdf->SetTextColor(...COL_BLU);
        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->TestoCentrato($x + $lb / 2, $y + 8.3, $competenza);
        $pdf->SetTextColor(...COL_GRIGIO);
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->TestoCentrato($x + $lb / 2, $y + 13.4, 'COMPETENZA SBLOCCATA', 0.5);
        $x += $lb + $gap;
    }

    // sigillo
    $sy = 182;
    $pdf->SetFillColor(...COL_ORO);
    $pdf->SetDrawColor(...COL_ORO_SCURO);
    $pdf->SetLineWidth(0.5);
    $pdf->Cerchio($cx, $sy, 12.5, 'DF');
    $pdf->Cerchio($cx, $sy, 10.5, 'D');
    $pdf->SetTextColor(...COL_BLU);
    $pdf->SetFont('Helvetica', 'B', 7.5);
    $pdf->TestoCentrato($cx, $sy - 1.2, 'JUNIOR', 0.4);
    $pdf->TestoCentrato($cx, $sy + 2.6, 'HACKER', 0.4);
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->TestoCentrato($cx, $sy + 6.3, date('Y'));

    // famiglia di paperelle in fila sulla cornice (come nel gioco Duck Coding),
    // con le impronte del percorso e la mamma che saluta il mondo come ogni primo programma
    $pdf->SetFillColor(205, 225, 240);
    foreach (PAPERELLE_DIPLOMA['impronte'] as [$ix, $iy]) {
        $pdf->Cerchio($ix, $iy, 0.45, 'F');
    }
    foreach (PAPERELLE_DIPLOMA['papere'] as [$px, $py, $alt]) {
        $pdf->Paperella($px, $py, $alt);
    }
    [$fx, $fy, $fw, $fh] = PAPERELLE_DIPLOMA['fumetto'];
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(...COL_AZZURRO);
    $pdf->SetLineWidth(0.3);
    $pdf->Rect($fx, $fy, $fw, $fh, 'DF');
    $pdf->Poligono(PAPERELLE_DIPLOMA['punta'], 'DF');
    $pdf->Rect($fx + 17.2, $fy + $fh - 0.5, 3.1, 0.7, 'F'); // copre il bordo tra fumetto e punta
    $pdf->SetTextColor(...COL_BLU);
    $pdf->SetFont('Courier', '', 7.5);
    $pdf->TestoCentrato($fx + $fw / 2, $fy + 4.2, PAPERELLE_DIPLOMA['testo']);

    piede($pdf, 'Attestato n. ' . $p['codice'], 'Rilasciato il ' . date('d/m/Y'));
}

/** Certificato di merito per il podio: medaglia, podio con le paperelle, punti. */
function pagina_merito(DiplomaPdf $pdf, array $p, ?string $nome, int $posto): void
{
    [$metallo, $colore, $bordo] = MEDAGLIE[$posto];
    $cx = pagina_base($pdf, 'CERTIFICATO DI MERITO', 'Classifica Junior Hacker · ' . $p['data_evento'], $colore);
    $y = blocco_nome($pdf, $p, $nome, 'Assegnato a');
    paragrafo($pdf, $y, testo_merito($p));

    // podio: il gradino del partecipante ha il colore della sua medaglia
    $pdf->SetLineWidth(0.35);
    foreach (PODIO as $n => [$x, $yp, $w]) {
        $mio = $n === $posto;
        $pdf->SetFillColor(...($mio ? $colore : [238, 247, 253]));
        $pdf->SetDrawColor(...($mio ? $bordo : COL_AZZURRO));
        $pdf->Rect($x, $yp, $w, PODIO_BASE - $yp, 'DF');
        $pdf->SetTextColor(...COL_BLU);
        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->TestoCentrato($x + $w / 2, $yp + 6.2, (string) $n);
        $alt = $n === 1 ? 10 : 8.5;
        $pdf->Paperella($x + $w / 2 - 0.5, $yp - 0.2, $alt);
        if ($n === 1) {
            $pdf->Corona($x + $w / 2 - 0.5, $yp - 0.2, $alt);
        }
    }

    // medaglia con nastro al posto del sigillo
    $sy = 182;
    $pdf->SetFillColor(...COL_AZZURRO);
    $pdf->Poligono([[$cx - 9, $sy + 4], [$cx - 13, $sy + 15], [$cx - 9, $sy + 13.5], [$cx - 7, $sy + 16.5], [$cx - 3, $sy + 6]], 'F');
    $pdf->SetFillColor(...COL_AZZURRO_SCURO);
    $pdf->Poligono([[$cx + 9, $sy + 4], [$cx + 13, $sy + 15], [$cx + 9, $sy + 13.5], [$cx + 7, $sy + 16.5], [$cx + 3, $sy + 6]], 'F');
    $pdf->SetFillColor(...$colore);
    $pdf->SetDrawColor(...$bordo);
    $pdf->SetLineWidth(0.5);
    $pdf->Cerchio($cx, $sy, 12.5, 'DF');
    $pdf->Cerchio($cx, $sy, 10.5, 'D');
    $pdf->SetTextColor(...COL_BLU);
    $pdf->SetFont('Helvetica', 'B', 17);
    $pdf->TestoCentrato($cx, $sy + 1.8, $posto . '°');
    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->TestoCentrato($cx, $sy + 6.6, $metallo, 0.4);

    piede($pdf, 'Certificato n. ' . $p['codice'] . '-' . $posto, 'Classifica delle ore ' . $p['ora_classifica'] . ' del ' . data_italiana(time()));
}
