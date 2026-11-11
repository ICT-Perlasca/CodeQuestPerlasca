<?php
/**
 * GET api/get_leaderboard.php  (pubblico, sola lettura)
 * Risposta: { ok, top: [{id, nickname, avatar, punti}], ultimi: [...], partecipanti }
 *
 * Interrogato ogni pochi secondi da classifica.php: risponde con ETag, quindi
 * se nulla e' cambiato il browser riceve un 304 senza corpo.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/api.php';

richiedi_metodo('GET');

$pdo = db();

// a parita' di punti vince chi li ha raggiunti prima
$top = $pdo->query(
    'SELECT id, nickname, avatar, punti_totali AS punti
       FROM partecipanti
      WHERE nascosto = 0 AND punti_totali > 0
      ORDER BY punti_totali DESC, updated_at ASC, id ASC
      LIMIT 10'
)->fetchAll();

$ultimi = $pdo->query(
    'SELECT id, nickname, avatar, punti_totali AS punti
       FROM partecipanti
      WHERE nascosto = 0
      ORDER BY created_at DESC, id DESC
      LIMIT 6'
)->fetchAll();

$totale = (int) $pdo->query('SELECT COUNT(*) FROM partecipanti WHERE nascosto = 0')->fetchColumn();

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
