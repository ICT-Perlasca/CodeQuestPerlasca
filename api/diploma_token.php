<?php
/**
 * POST api/diploma_token.php
 * Body JSON: { "nome": "Mario Rossi" }   (facoltativo, stampato al posto del nickname)
 *
 * Crea il token del QR code per scaricare il diploma da un altro telefono:
 * valido DIPLOMA_TOKEN_MINUTI minuti, uno solo attivo per partecipante (il nuovo
 * sostituisce il vecchio). I token scaduti, con il nome, vengono cancellati qui.
 *
 * Risposta: { ok, token, secondi }
 * Il client compone l'URL (diploma_pdf.php?t=...) a partire dal proprio indirizzo.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/api.php';
require_once __DIR__ . '/../inc/diploma.php';

richiedi_metodo('POST');

// niente richiedi_ospite(): il diploma resta scaricabile anche a evento chiuso
avvia_sessione();
if (empty($_SESSION['ospite'])) {
    json_errore(401, 'Sessione scaduta: rifai il login');
}
if (empty($_SESSION['uuid'])) {
    json_errore(409, 'Prima scegli il tuo nickname');
}
$in = leggi_json();

try {
    $nome = nome_diploma((string) ($in['nome'] ?? ''));
} catch (InvalidArgumentException $ex) {
    json_errore(422, $ex->getMessage());
}

$pdo = db();
$st = $pdo->prepare('SELECT id FROM partecipanti WHERE uuid = ? AND nascosto = 0');
$st->execute([$_SESSION['uuid']]);
$id = $st->fetchColumn();
if ($id === false) {
    json_errore(403, 'Diploma non disponibile: chiedi a un membro dello staff');
}

$token = bin2hex(random_bytes(16));
$ora = time();

$pdo->beginTransaction();
$st = $pdo->prepare('DELETE FROM diplomi_token WHERE scade_il <= ? OR partecipante_id = ?');
$st->execute([date('Y-m-d H:i:s', $ora), $id]);
$st = $pdo->prepare('INSERT INTO diplomi_token (token, partecipante_id, nome, scade_il) VALUES (?, ?, ?, ?)');
$st->execute([$token, $id, $nome, date('Y-m-d H:i:s', $ora + DIPLOMA_TOKEN_MINUTI * 60)]);
$pdo->commit();

registra_diploma((int) $id, $nome);

json_out(['ok' => true, 'token' => $token, 'secondi' => DIPLOMA_TOKEN_MINUTI * 60]);
