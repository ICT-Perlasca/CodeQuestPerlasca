<?php
/**
 * Admin: tutte le azioni POST (token CSRF, poi redirect alla pagina da cui arrivano).
 */
declare(strict_types=1);

/* ---------------------------------------------------------------- azioni */
if ($metodo === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        flash('Sessione scaduta, riprova.', 'ko');
        torna();
    }

    $pdo = db();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    switch ($azione) {
        case 'logout':
            session_regenerate_id(true);
            unset($_SESSION['admin'], $_SESSION['csrf']);
            header('Location: index.php');
            exit;

        case 'visibilita':
            $nascosto = ($_POST['nascosto'] ?? '') === '1' ? 1 : 0;
            $pdo->prepare('UPDATE partecipanti SET nascosto = ? WHERE id = ?')->execute([$nascosto, $id]);
            flash($nascosto ? 'Nickname nascosto dalla classifica.' : 'Nickname di nuovo visibile.');
            break;

        case 'elimina':
        case 'banna':
            $st = $pdo->prepare('SELECT nickname FROM partecipanti WHERE id = ?');
            $st->execute([$id]);
            $nick = $st->fetchColumn();
            if ($nick === false) {
                flash('Partecipante non trovato.', 'ko');
                break;
            }
            $pdo->prepare('DELETE FROM partecipanti WHERE id = ?')->execute([$id]);
            $msg = "«{$nick}» eliminato: al prossimo accesso dovra' scegliere un nuovo nickname.";
            if ($azione === 'banna') {
                $parola = normalizza_nickname($nick);
                if (mb_strlen($parola) >= 3) {
                    $pdo->prepare('INSERT IGNORE INTO blacklist (parola) VALUES (?)')->execute([$parola]);
                    $msg .= " «{$parola}» aggiunto alla blacklist.";
                }
            }
            flash($msg);
            break;

        case 'admin_sessione': // sceglie la sessione su cui lavora il pannello
            $scelta = filter_var($_POST['sessione'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($scelta !== false && sessione_riga($scelta) !== null) {
                $_SESSION['admin_sessione'] = $scelta;
            }
            break;

        case 'sessione_nuova':
            $nuova = salva_sessione($pdo, $_POST);
            if ($nuova[0]) {
                $_SESSION['admin_sessione'] = $nuova[2]; // si passa subito a lavorare sulla sessione creata
            }
            flash($nuova[1], $nuova[0] ? 'ok' : 'ko');
            break;

        case 'sessione':
            if ($sidAdmin === null) {
                flash('Nessuna sessione da salvare.', 'ko');
                break;
            }
            $salvata = salva_sessione($pdo, $_POST, $sidAdmin);
            flash($salvata[1], $salvata[0] ? 'ok' : 'ko');
            break;

        case 'sessione_termina':
            if ($sidAdmin !== null) {
                $pdo->prepare('UPDATE sessioni SET scade_il = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $sidAdmin]);
                flash('Sessione terminata: il login è chiuso. Per riaprirla cambia l\'ora di fine, o creane una nuova.');
            }
            break;

        case 'sessione_elimina':
            $riga = $sidAdmin !== null ? sessione_riga($sidAdmin) : null;
            if ($riga === null || ($_POST['conferma'] ?? '') !== 'ELIMINA') {
                flash('Scrivi ELIMINA per confermare.', 'ko');
                break;
            }
            $n = (int) $pdo->query('SELECT COUNT(*) FROM partecipanti WHERE sessione_id = ' . (int) $sidAdmin)->fetchColumn();
            $pdo->prepare('DELETE FROM sessioni WHERE id = ?')->execute([$sidAdmin]); // i partecipanti seguono (ON DELETE CASCADE)
            unset($_SESSION['admin_sessione']);
            flash("Sessione «{$riga['nome']}» eliminata con i suoi {$n} partecipanti. Il registro non è stato toccato.");
            break;

        case 'giochi':
            $st = $pdo->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?, ?) ON DUPLICATE KEY UPDATE valore = VALUES(valore)');
            foreach (array_keys(GIOCHI) as $codice) {
                $v = (string) ($_POST['stato'][$codice] ?? 'attivo');
                $st->execute(["gioco_$codice", isset(STATI_GIOCO[$v]) ? $v : 'attivo']);
            }
            flash('Stato dei giochi salvato.');
            break;

        case 'blacklist_aggiungi':
            $parola = normalizza_nickname(trim((string) ($_POST['parola'] ?? '')));
            if (mb_strlen($parola) < 2 || mb_strlen($parola) > 40) {
                flash('Parola non valida (2-40 lettere).', 'ko');
                break;
            }
            $pdo->prepare('INSERT IGNORE INTO blacklist (parola) VALUES (?)')->execute([$parola]);
            flash("«{$parola}» aggiunto alla blacklist.");
            break;

        case 'blacklist_rimuovi':
            $pdo->prepare('DELETE FROM blacklist WHERE id = ?')->execute([$id]);
            flash('Parola rimossa dalla blacklist.');
            break;

        case 'registro_svuota':
            if (($_POST['conferma'] ?? '') !== 'SVUOTA') {
                flash('Scrivi SVUOTA per confermare.', 'ko');
                break;
            }
            $n = $pdo->exec('DELETE FROM registro');
            flash("Registro svuotato: {$n} righe eliminate.");
            break;

        case 'reset':
            if (($_POST['conferma'] ?? '') !== 'RESET') {
                flash('Scrivi RESET per confermare.', 'ko');
                break;
            }
            $st = $pdo->prepare('DELETE FROM partecipanti WHERE sessione_id = ?');
            $st->execute([$sidAdmin]);
            flash('Classifica azzerata: ' . $st->rowCount() . ' partecipanti eliminati dalla sessione.');
            break;

        default:
            flash('Azione sconosciuta.', 'ko');
    }
    torna();
}

$csrf = $_SESSION['csrf'] ?? '';
