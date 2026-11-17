<?php
/**
 * POST api/save_score.php
 * Body JSON: { "uuid": "<uuidv4>", "modulo": "binary_lights", "punti": 1230 }
 *
 * - Il punteggio e' limitato a moduli.max_punti (anti-cheat lato server), ridotto
 *   secondo la sessione (Open Day / laboratori) da max_punti_sessione().
 * - Per ogni modulo si tiene il record personale (GREATEST), quindi rigiocare
 *   o reinviare la stessa richiesta non gonfia la classifica.
 * - La riga del partecipante viene bloccata (SELECT ... FOR UPDATE) e il totale
 *   ricalcolato come SUM nella stessa transazione: due salvataggi simultanei
 *   dallo stesso telefono non possono perdere punti.
 *
 * Risposta: { ok, modulo, punti_modulo, punti_totali, nuovo_record }
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/api.php';

const INTERVALLO_MIN_SECONDI = 2;

richiedi_metodo('POST');
richiedi_ospite();
$in = leggi_json();

$uuid   = strtolower(trim((string) ($in['uuid'] ?? '')));
$modulo = (string) ($in['modulo'] ?? '');
$punti  = filter_var($in['punti'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

if (!uuid_valido($uuid) || preg_match('/^[a-z0-9_]{2,40}$/', $modulo) !== 1 || $punti === false) {
    json_errore(422, 'Dati non validi');
}

// Il UUID deve essere quello registrato in questa sessione (api/register.php)
if (($_SESSION['uuid'] ?? null) !== $uuid) {
    json_errore(403, 'Profilo non riconosciuto: reinserisci il nickname');
}

// Anti-spam: al massimo un salvataggio ogni 2 secondi per modulo
$ora = time();
if ($ora - ($_SESSION['ultimo_salvataggio'][$modulo] ?? 0) < INTERVALLO_MIN_SECONDI) {
    json_errore(429, 'Troppo veloce! Riprova tra un attimo');
}
$_SESSION['ultimo_salvataggio'][$modulo] = $ora;
session_write_close(); // libera il lock della sessione prima di toccare il DB

if (isset(GIOCHI[$modulo]) && stato_gioco($modulo) !== 'attivo') {
    json_errore(403, 'Questo gioco è stato chiuso dai docenti');
}

$pdo = db();
$pdo->beginTransaction();

$st = $pdo->prepare('SELECT max_punti FROM moduli WHERE codice = ? AND attivo = 1');
$st->execute([$modulo]);
$maxPunti = $st->fetchColumn();
if ($maxPunti === false) {
    $pdo->rollBack();
    json_errore(404, 'Modulo sconosciuto');
}
$punti = min($punti, max_punti_sessione($modulo, (int) $maxPunti)); // Binary Lights: 5 o 12 round secondo la sessione

$st = $pdo->prepare('SELECT id FROM partecipanti WHERE sessione_id = ? AND uuid = ? FOR UPDATE');
$st->execute([sessione_id_corrente(), $uuid]);
$partecipanteId = $st->fetchColumn();
if ($partecipanteId === false) {
    $pdo->rollBack();
    json_errore(404, 'Profilo non trovato: reinserisci il nickname');
}

$st = $pdo->prepare('SELECT punti FROM punteggi WHERE partecipante_id = ? AND modulo = ?');
$st->execute([$partecipanteId, $modulo]);
$precedente = $st->fetchColumn();

$pdo->prepare(
    'INSERT INTO punteggi (partecipante_id, modulo, punti)
     VALUES (:pid, :modulo, :punti)
     ON DUPLICATE KEY UPDATE tentativi = tentativi + 1, punti = GREATEST(punti, :punti_upd)'
)->execute([
    'pid'       => $partecipanteId,
    'modulo'    => $modulo,
    'punti'     => $punti,
    'punti_upd' => $punti,
]);

$totale = aggiorna_totale($pdo, (int) $partecipanteId, $uuid);

$pdo->commit();

json_out([
    'ok'           => true,
    'modulo'       => $modulo,
    'punti_modulo' => max($punti, (int) ($precedente ?: 0)),
    'punti_totali' => $totale,
    'nuovo_record' => $precedente === false || $punti > (int) $precedente,
]);
