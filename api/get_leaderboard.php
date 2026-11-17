<?php
/**
 * GET api/get_leaderboard.php[?s=<id sessione>]  (pubblico, sola lettura)
 * Risposta: { ok, top: [{id, nickname, avatar, punti}], ultimi: [...], partecipanti }
 *
 * Interrogato ogni pochi secondi da classifica.php: risponde con ETag, quindi
 * se nulla e' cambiato il browser riceve un 304 senza corpo.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/api.php';

richiedi_metodo('GET');

$pdo = db();
$sid = sessione_pubblica() ?? 0; // ?s=<id sessione>; senza, quella dell'ospite o la piu' recente

// a parita' di punti vince chi li ha raggiunti prima
$top = $pdo->prepare(
    'SELECT id, nickname, avatar, punti_totali AS punti
       FROM partecipanti
      WHERE sessione_id = ? AND nascosto = 0 AND punti_totali > 0
      ORDER BY punti_totali DESC, updated_at ASC, id ASC
      LIMIT 10'
);
$top->execute([$sid]);
$top = $top->fetchAll();

$ultimi = $pdo->prepare(
    'SELECT id, nickname, avatar, punti_totali AS punti
       FROM partecipanti
      WHERE sessione_id = ? AND nascosto = 0
      ORDER BY created_at DESC, id DESC
      LIMIT 6'
);
$ultimi->execute([$sid]);
$ultimi = $ultimi->fetchAll();

$totale = $pdo->prepare('SELECT COUNT(*) FROM partecipanti WHERE sessione_id = ? AND nascosto = 0');
$totale->execute([$sid]);
$totale = (int) $totale->fetchColumn();

$corpo = json_encode(
    ['ok' => true, 'top' => $top, 'ultimi' => $ultimi, 'partecipanti' => $totale],
    JSON_UNESCAPED_UNICODE
);
$etag = '"' . md5($corpo) . '"';

header('Cache-Control: no-cache');
header('ETag: ' . $etag);

// Apache con mod_deflate aggiunge "-gzip" all'ETag: lo ignoriamo nel confronto
$richiesto = str_replace(['W/', '-gzip'], '', $_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
if (trim($richiesto) === $etag) {
    http_response_code(304);
    exit;
}

echo $corpo;
