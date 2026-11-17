<?php
/**
 * Admin: creazione e modifica di una sessione di gioco (validazione dei campi).
 */
declare(strict_types=1);

/**
 * Crea una sessione di gioco o ne modifica una ($id): giornata, ora di fine, password ospiti,
 * scadenza del download e validita' del QR. Una nuova sessione apre subito il login.
 * Piu' sessioni possono essere attive insieme (es. una demo e quelle vere): la password
 * scritta al login decide in quale si entra, quindi deve essere diversa da quella delle altre in corso.
 *
 * @return array{0: bool, 1: string, 2: ?int} [riuscita, messaggio, id della sessione]
 */
function salva_sessione(PDO $pdo, array $in, ?int $id = null): array
{
    $pulisci = static fn(string $k): string => trim((string) preg_replace('/\s+/u', ' ', (string) ($in[$k] ?? '')));
    $stampabile = static fn(string $t): bool => mb_convert_encoding(mb_convert_encoding($t, 'Windows-1252', 'UTF-8'), 'UTF-8', 'Windows-1252') === $t;

    $nome = $pulisci('nome');
    $tipo = (string) ($in['tipo'] ?? '');
    $data = DateTime::createFromFormat('!Y-m-d', (string) ($in['data'] ?? ''));
    $fine = $data ? DateTime::createFromFormat('Y-m-d H:i', $data->format('Y-m-d') . ' ' . (string) ($in['fine'] ?? '')) : false;
    $titolo = $pulisci('titolo');
    $descrizione = $pulisci('descrizione');
    $utente = strtolower(trim((string) ($in['utente'] ?? '')));
    $pass = strtolower(trim((string) ($in['ospite_pass'] ?? '')));
    $qr = (string) ($in['qr_minuti'] ?? '');
    $grezzo = trim((string) ($in['diploma_scadenza'] ?? ''));
    $diploma = $grezzo === '' ? null : DateTime::createFromFormat('Y-m-d\TH:i', $grezzo);
    $demo = !empty($in['demo']) ? 1 : 0;
    $sblocca = !empty($in['sblocca_tutto']) ? 1 : 0;
    $attiva = $id === null || !empty($in['attiva']) ? 1 : 0;

    if ($nome === '' || mb_strlen($nome) > 60) {
        return [false, 'Dai un nome alla sessione (max 60 caratteri), per riconoscerla: es. «Demo studenti».', null];
    }
    if (!isset(TIPI_GIORNATA[$tipo])) {
        return [false, 'Tipo di giornata non valido.', null];
    }
    if ($data === false || $fine === false) {
        return [false, "Giorno e ora di fine dell'attività sono obbligatori.", null];
    }
    if ($id === null && $fine->getTimestamp() <= time()) {
        return [false, "L'ora di fine è già passata: la sessione risulterebbe subito scaduta.", null];
    }
    if (mb_strlen($titolo) > 60 || mb_strlen($descrizione) > 200) {
        return [false, 'Titolo max 60 caratteri, descrizione max 200.', null];
    }
    if (!$stampabile($titolo) || !$stampabile($descrizione)) {
        return [false, 'Titolo o descrizione contengono caratteri che il PDF non sa stampare (emoji, simboli): toglili.', null];
    }
    if (preg_match('/^[a-z0-9]{3,30}$/', $utente) !== 1) {
        return [false, 'Nome utente obbligatorio: 3-30 caratteri, solo lettere (senza accenti) e numeri.', null];
    }
    if (preg_match('/^[a-z0-9]{4,30}$/', $pass) !== 1) {
        return [false, 'Password ospiti obbligatoria: 4-30 caratteri, solo lettere (senza accenti) e numeri.', null];
    }
    if (!ctype_digit($qr) || (int) $qr < 1 || (int) $qr > DIPLOMA_TOKEN_MINUTI_MAX) {
        return [false, 'Validità del QR code: da 1 a ' . DIPLOMA_TOKEN_MINUTI_MAX . ' minuti.', null];
    }
    if ($diploma === false || ($diploma !== null && $diploma <= $fine)) {
        return [false, "La scadenza del download dev'essere una data valida dopo la fine della sessione (o vuota = automatica).", null];
    }

    // la password sceglie la sessione: due sessioni in corso non possono averla uguale
    $st = $pdo->prepare('SELECT nome FROM sessioni WHERE utente = ? AND password = ? AND id <> ? AND attiva = 1 AND scade_il > ?');
    $st->execute([$utente, $pass, $id ?? 0, date('Y-m-d H:i:s')]);
    $occupata = $st->fetchColumn();
    if ($occupata !== false && $attiva === 1 && $fine->getTimestamp() > time()) {
        return [false, "La coppia utente «{$utente}» + password «{$pass}» è già usata dalla sessione «{$occupata}», che è in corso: cambia l'utente o la password.", null];
    }

    $campi = [
        'nome' => $nome, 'tipo' => $tipo, 'data' => $data->format('Y-m-d'), 'titolo' => $titolo, 'descrizione' => $descrizione,
        'utente' => $utente, 'password' => $pass, 'scade_il' => $fine->format('Y-m-d H:i:00'), 'attiva' => $attiva, 'demo' => $demo, 'sblocca_tutto' => $sblocca,
        'diploma_scade_il' => $diploma ? $diploma->format('Y-m-d H:i:00') : null, 'qr_minuti' => (int) $qr,
    ];
    if ($id === null) {
        $pdo->prepare(
            'INSERT INTO sessioni (nome, tipo, data, titolo, descrizione, utente, password, scade_il, attiva, demo, sblocca_tutto, diploma_scade_il, qr_minuti)
             VALUES (:nome, :tipo, :data, :titolo, :descrizione, :utente, :password, :scade_il, :attiva, :demo, :sblocca_tutto, :diploma_scade_il, :qr_minuti)'
        )->execute($campi);
        $id = (int) $pdo->lastInsertId();
        $msg = "Sessione «{$nome}» creata";
    } else {
        $pdo->prepare(
            'UPDATE sessioni SET nome = :nome, tipo = :tipo, data = :data, titolo = :titolo, descrizione = :descrizione,
                    utente = :utente, password = :password, scade_il = :scade_il, attiva = :attiva, demo = :demo, sblocca_tutto = :sblocca_tutto,
                    diploma_scade_il = :diploma_scade_il, qr_minuti = :qr_minuti
              WHERE id = :id'
        )->execute($campi + ['id' => $id]);
        $msg = "Sessione «{$nome}» salvata";
    }
    return [true, $msg . ': ' . TIPI_GIORNATA[$tipo]['etichetta'] . ($demo ? ' (demo)' : '') . ' del ' . $data->format('d/m/Y')
        . ', login aperto fino alle ' . $fine->format('H:i') . ", utente «{$utente}», password «{$pass}».", $id];
}
