<?php
/**
 * Attestato in PDF (FPDF 1.86, font base del PDF: serve solo mbstring).
 */
declare(strict_types=1);

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

/* --------------------------------------------------------------- disegno */

define('FPDF_FONTPATH', dirname(__DIR__) . '/lib/fpdf/font/');
require_once dirname(__DIR__) . '/lib/fpdf/fpdf.php';

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
