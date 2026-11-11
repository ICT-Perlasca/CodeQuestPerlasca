<?php
/**
 * POST api/register.php
 * Body JSON: { "uuid": "<uuidv4>", "nickname": "Neo_42", "avatar": "robot-3" }
 *
 * Crea il partecipante (o aggiorna nickname/avatar se il UUID esiste gia',
 * ad es. sessione PHP scaduta ma localStorage ancora presente) e lega il
 * UUID alla sessione: da qui in poi save_score.php accetta solo questo UUID.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/api.php';

richiedi_metodo('POST');
richiedi_ospite();
$in = leggi_json();

$uuid     = strtolower(trim((string) ($in['uuid'] ?? '')));
$nickname = trim((string) ($in['nickname'] ?? ''));
$avatar   = (string) ($in['avatar'] ?? '');

if (!uuid_valido($uuid)) {
    json_errore(422, 'Identificativo non valido');
}
// 3-16 caratteri, niente spazi (scoraggia "Nome Cognome")
if (preg_match('/^[\p{L}\p{N}_\-]{3,16}$/u', $nickname) !== 1) {
    json_errore(422, 'Nickname: 3-16 caratteri, solo lettere, numeri, _ e -');
}
if (preg_match('/^[a-z0-9\-]{1,30}$/', $avatar) !== 1) {
    json_errore(422, 'Avatar non valido');
}
if (!nickname_ammesso($nickname)) {
    json_errore(422, 'Questo nickname non e\' ammesso, scegline un altro');
}

$pdo = db();
// Niente ON DUPLICATE KEY UPDATE: scatterebbe anche su uq_nickname e
// sovrascriverebbe il profilo di chi ha gia' quel nickname.
// Qui un nickname occupato da un altro UUID fa sempre fallire la query.
$esiste = $pdo->prepare('SELECT 1 FROM partecipanti WHERE uuid = ?');
$esiste->execute([$uuid]);
try {
    if ($esiste->fetchColumn()) {
        $pdo->prepare('UPDATE partecipanti SET nickname = :nick, avatar = :avatar WHERE uuid = :uuid')
            ->execute(['uuid' => $uuid, 'nick' => $nickname, 'avatar' => $avatar]);
    } else {
        $pdo->prepare('INSERT INTO partecipanti (uuid, nickname, avatar) VALUES (:uuid, :nick, :avatar)')
            ->execute(['uuid' => $uuid, 'nick' => $nickname, 'avatar' => $avatar]);
    }
} catch (PDOException $e) {
    if ($e->getCode() === '23000') { // violazione uq_nickname
        json_errore(409, 'Nickname gia\' preso, prova ad aggiungere un numero');
    }
    throw $e;
}

// registro permanente: l'ora d'arrivo resta quella della prima registrazione
$pdo->prepare(
    'INSERT INTO registro (uuid, nickname, arrivato_il)
     VALUES (:uuid, :nick, :ora)
     ON DUPLICATE KEY UPDATE nickname = :nick_upd'
)->execute(['uuid' => $uuid, 'nick' => $nickname, 'ora' => date('Y-m-d H:i:s'), 'nick_upd' => $nickname]);

$_SESSION['uuid'] = $uuid;

$st = $pdo->prepare('SELECT uuid, nickname, avatar, punti_totali FROM partecipanti WHERE uuid = ?');
$st->execute([$uuid]);
json_out(['ok' => true, 'partecipante' => $st->fetch()]);

/** Confronta il nickname normalizzato con la blacklist (per sottostringa). */
function nickname_ammesso(string $nickname): bool
{
    $norm = normalizza_nickname($nickname);

    foreach (db()->query('SELECT parola FROM blacklist')->fetchAll(PDO::FETCH_COLUMN) as $parola) {
        if ($parola !== '' && str_contains($norm, mb_strtolower($parola))) {
            return false;
        }
    }
    return true;
}
