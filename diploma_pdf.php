<?php
/**
 * Download del diploma in PDF.
 *   GET  diploma_pdf.php?t=<token>  link del QR code (api/diploma_token.php): niente login,
 *                                   valido diploma_token_minuti() minuti
 *   POST diploma_pdf.php  nome=...  dalla pagina diploma.php, per il partecipante della sessione
 *   GET  diploma_pdf.php            come sopra, senza nome
 *
 * A differenza delle API di gioco funziona anche a evento chiuso: e' sola lettura.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/diploma.php';

/** Pagina d'errore leggibile da telefono (chi arriva dal QR non ha l'app aperta). */
function errore_pagina(int $codice, string $titolo, string $messaggio): never
{
    http_response_code($codice);
    header('Cache-Control: no-store');
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Diploma · CodeQuestPerlasca</title>'
       . '<style>body{margin:0;min-height:100dvh;display:grid;place-items:center;padding:16px;background:#0b1020;color:#e8ecff;'
       . 'font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}main{max-width:380px;background:#141b33;'
       . 'border:1px solid #2a3566;border-radius:16px;padding:24px;display:grid;gap:10px}h1{margin:0;font-size:1.3rem}'
       . 'p{margin:0;color:#8e98c7}</style></head><body><main>'
       . '<h1>' . $e($titolo) . '</h1><p>' . $e($messaggio) . '</p></main></body></html>';
    exit;
}

$token = (string) ($_GET['t'] ?? '');

if ($token !== '') {
    if (preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
        errore_pagina(404, 'Link non valido', 'Inquadra di nuovo il QR code dalla pagina del diploma.');
    }
    $st = db()->prepare('SELECT partecipante_id, nome FROM diplomi_token WHERE token = ? AND scade_il > ?');
    $st->execute([$token, date('Y-m-d H:i:s')]);
    $riga = $st->fetch();
    $p = $riga ? dati_diploma((int) $riga['partecipante_id']) : null; // imposta anche la sessione del partecipante
    if ($p !== null && !diploma_aperto()) {
        errore_pagina(403, 'Download chiuso', DIPLOMA_CHIUSO_MSG);
    }
    if ($p === null) {
        errore_pagina(410, 'QR code scaduto',
            'Il link vale ' . diploma_token_minuti() . ' minuti. Genera un nuovo QR code dalla pagina del diploma e inquadralo di nuovo.');
    }
    invia_diploma_pdf($p, $riga['nome']);
}

avvia_sessione();
if (!ospite_collegato() || empty($_SESSION['uuid'])) {
    header('Location: index.php');
    exit;
}
if (!diploma_aperto()) {
    errore_pagina(403, 'Download chiuso', DIPLOMA_CHIUSO_MSG);
}

$st = db()->prepare('SELECT id FROM partecipanti WHERE sessione_id = ? AND uuid = ?');
$st->execute([sessione_id_corrente(), $_SESSION['uuid']]);
$id = $st->fetchColumn();
$p = $id !== false ? dati_diploma((int) $id) : null;
if ($p === null) {
    errore_pagina(403, 'Diploma non disponibile', 'Chiedi a un membro dello staff.');
}

try {
    $nome = nome_diploma((string) ($_POST['nome'] ?? ''));
} catch (InvalidArgumentException $ex) {
    errore_pagina(422, 'Nome non valido', $ex->getMessage() . '. Torna indietro e correggilo.');
}

registra_diploma((int) $id, $nome);
invia_diploma_pdf($p, $nome);
