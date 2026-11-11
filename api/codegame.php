<?php
/**
 * POST api/codegame.php  -  Duck Coding
 * Body JSON: { "uuid": "<uuidv4>", "azione": "...", ... }
 *
 *   stato                         -> livelli risolti, tempi, punti, media di ogni livello
 *   inizia   { livello }          -> fa partire il cronometro (solo la prima volta)
 *   completa { livello, blocchi, esecuzioni }
 *                                 -> ferma il cronometro, calcola i punti
 *
 * Il tempo lo misura il SERVER: parte alla prima apertura del livello e si
 * ferma alla prima soluzione. Ricaricare la pagina non lo azzera e rigiocare
 * un livello gia' risolto non cambia tempo ne' punti.
 * Punti del livello = 100 * media / tuo tempo (30..130) + 20 se usi i blocchi
 * minimi. Il punteggio del modulo e' la somma dei livelli.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/api.php';
require_once __DIR__ . '/../inc/codegame.php';

richiedi_metodo('POST');
richiedi_ospite();
$in = leggi_json();

$uuid   = strtolower(trim((string) ($in['uuid'] ?? '')));
$azione = (string) ($in['azione'] ?? '');
if (!uuid_valido($uuid) || ($_SESSION['uuid'] ?? null) !== $uuid) {
    json_errore(403, 'Profilo non riconosciuto: reinserisci il nickname');
}
session_write_close();

$livelli = codegame_livelli_attivi(); // Open Day: solo i primi livelli, laboratorio: tutti
$pdo = db();
$st = $pdo->prepare('SELECT id FROM partecipanti WHERE uuid = ?');
$st->execute([$uuid]);
$pid = $st->fetchColumn();
if ($pid === false) {
    json_errore(404, 'Profilo non trovato: reinserisci il nickname');
}
$pid = (int) $pid;
$oraMs = (int) floor(microtime(true) * 1000);

if ($azione === 'stato') {
    $st = $pdo->prepare('SELECT livello, ms, punti, iniziato_ms FROM codegame_tempi WHERE partecipante_id = ?');
    $st->execute([$pid]);
    $miei = [];
    foreach ($st->fetchAll() as $r) {
        $miei[(int) $r['livello']] = $r;
    }
    $out = [];
    foreach (array_keys($livelli) as $n) {
        [$media, $quanti] = codegame_media_ms($pdo, $n, $pid);
        $r = $miei[$n] ?? null;
        $out[] = [
            'livello'  => $n,
            'risolto'  => $r !== null && $r['ms'] !== null,
            'ms'       => $r['ms'] ?? null,
            'punti'    => $r['punti'] ?? null,
            'media_ms' => (int) round($media),
            'risolti'  => $quanti,
        ];
    }
    json_out(['ok' => true, 'livelli' => $out]);
}

$livello = filter_var($in['livello'] ?? null, FILTER_VALIDATE_INT);
if ($livello === false || !isset($livelli[$livello])) {
    json_errore(422, 'Livello non valido');
}

if ($azione === 'inizia') {
    if ($livello > 1 && !CODEGAME_SBLOCCA_TUTTO) {
        $st = $pdo->prepare('SELECT 1 FROM codegame_tempi WHERE partecipante_id = ? AND livello = ? AND ms IS NOT NULL');
        $st->execute([$pid, $livello - 1]);
        if ($st->fetchColumn() === false) {
            json_errore(409, 'Prima risolvi il livello ' . ($livello - 1));
        }
    }
    // INSERT IGNORE: se il livello era gia' stato aperto il cronometro non riparte
    $pdo->prepare('INSERT IGNORE INTO codegame_tempi (partecipante_id, livello, iniziato_ms) VALUES (?, ?, ?)')
        ->execute([$pid, $livello, $oraMs]);
    $st = $pdo->prepare('SELECT iniziato_ms, ms, punti FROM codegame_tempi WHERE partecipante_id = ? AND livello = ?');
    $st->execute([$pid, $livello]);
    $r = $st->fetch();
    [$media] = codegame_media_ms($pdo, $livello, $pid);
    json_out([
        'ok'          => true,
        'risolto'     => $r['ms'] !== null,
        'ms'          => $r['ms'],
        'punti'       => $r['punti'],
        'trascorsi_ms'=> $r['ms'] ?? max(0, $oraMs - (int) $r['iniziato_ms']),
        'media_ms'    => (int) round($media),
    ]);
}

if ($azione === 'completa') {
    $blocchi    = filter_var($in['blocchi'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
    $esecuzioni = filter_var($in['esecuzioni'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
    if ($blocchi === false || $esecuzioni === false) {
        json_errore(422, 'Dati non validi');
    }

    $pdo->beginTransaction();
    // blocca il partecipante: due "completa" simultanei non possono sommare due volte
    $pdo->prepare('SELECT id FROM partecipanti WHERE id = ? FOR UPDATE')->execute([$pid]);
    $st = $pdo->prepare('SELECT iniziato_ms, ms, punti FROM codegame_tempi WHERE partecipante_id = ? AND livello = ? FOR UPDATE');
    $st->execute([$pid, $livello]);
    $r = $st->fetch();
    if ($r === false) {
        $pdo->rollBack();
        json_errore(409, 'Livello mai iniziato: ricarica la pagina');
    }
    [$media, $quanti] = codegame_media_ms($pdo, $livello, $pid);

    if ($r['ms'] !== null) { // gia' risolto: conta solo la prima volta
        $pdo->rollBack();
        json_out([
            'ok' => true, 'gia_risolto' => true, 'ms' => (int) $r['ms'], 'punti' => (int) $r['punti'],
            'media_ms' => (int) round($media),
        ]);
    }

    $ms = $oraMs - (int) $r['iniziato_ms'];
    if ($ms < CODEGAME_MIN_MS) {
        $pdo->rollBack();
        json_errore(422, 'Troppo veloce per essere vero!');
    }
    $ottimale = $blocchi <= $livelli[$livello]['ottimali'];
    [$punti, $bonus] = codegame_punti($ms, $media, $ottimale);

    $pdo->prepare(
        'UPDATE codegame_tempi SET ms = ?, punti = ?, blocchi = ?, esecuzioni = ?, risolto_il = NOW()
          WHERE partecipante_id = ? AND livello = ?'
    )->execute([$ms, $punti, $blocchi, $esecuzioni, $pid, $livello]);

    $st = $pdo->prepare('SELECT COALESCE(SUM(punti), 0) FROM codegame_tempi WHERE partecipante_id = ?');
    $st->execute([$pid]);
    $puntiModulo = min((int) $st->fetchColumn(), codegame_max_punti());
    $pdo->prepare(
        'INSERT INTO punteggi (partecipante_id, modulo, punti) VALUES (:pid, :modulo, :punti)
         ON DUPLICATE KEY UPDATE tentativi = tentativi + 1, punti = :punti_upd'
    )->execute(['pid' => $pid, 'modulo' => CODEGAME_MODULO, 'punti' => $puntiModulo, 'punti_upd' => $puntiModulo]);

    $totale = aggiorna_totale($pdo, $pid, $uuid);
    $pdo->commit();

    json_out([
        'ok'           => true,
        'gia_risolto'  => false,
        'ms'           => $ms,
        'media_ms'     => (int) round($media),
        'risolti'      => $quanti,
        'punti'        => $punti,
        'bonus'        => $bonus,
        'punti_modulo' => $puntiModulo,
        'punti_totali' => $totale,
    ]);
}

json_errore(422, 'Azione sconosciuta');
