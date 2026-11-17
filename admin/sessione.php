<?php
/**
 * Admin · Sessioni: elenco, creazione e modifica. Possono essere attive piu' sessioni insieme
 * (ad es. una demo per gli studenti tutor e quelle vere dell'attivita'): ognuna ha la sua password,
 * la sua classifica e i suoi partecipanti. Si entra in quella la cui password si scrive al login.
 */
declare(strict_types=1);

require_once __DIR__ . '/_base.php';

$pdo = db();
$tutte = elenco_sessioni();
$conteggi = $pdo->query('SELECT sessione_id, COUNT(*) FROM partecipanti GROUP BY sessione_id')->fetchAll(PDO::FETCH_KEY_PAIR);
$riga = $sidAdmin !== null ? sessione_riga($sidAdmin) : null;
$sessione = sessione_attivita();

/** Campi del modulo di una sessione (uguali per creazione e modifica). $v = valori di partenza. */
function campi_sessione(array $v, bool $modifica): void
{
    global $e;
    ?>
    <label class="largo">Nome della sessione <span class="muted">per riconoscerla qui, es. "Demo studenti" o "Open Day mattina"</span>
      <input type="text" name="nome" maxlength="60" value="<?= $e($v['nome']) ?>" required>
    </label>
    <label>Tipo di giornata
      <select name="tipo" required>
        <?php foreach (TIPI_GIORNATA as $codice => $tg): ?>
          <option value="<?= $e($codice) ?>" <?= $v['tipo'] === $codice ? 'selected' : '' ?>><?= $e($tg['etichetta']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Giorno dell'attività
      <input type="date" name="data" value="<?= $e($v['data']) ?>" required>
    </label>
    <label>Fine sessione (chiude il login)
      <input type="time" name="fine" value="<?= $e($v['fine']) ?>" required>
    </label>
    <label>Nome utente ospiti <span class="muted">3-30 lettere/numeri</span>
      <input type="text" name="utente" maxlength="30" autocomplete="off" autocapitalize="none" value="<?= $e($v['utente']) ?>" required>
    </label>
    <label>Password ospiti <span class="muted">4-30 lettere/numeri</span>
      <span class="riga-form">
        <input type="text" name="ospite_pass" maxlength="30" autocomplete="off" autocapitalize="none" value="<?= $e($v['password']) ?>" required>
        <button type="button" data-genera-pass>Suggerisci</button>
      </span>
    </label>
    <label class="largo">Titolo dell'attività <span class="muted">facoltativo, es. "Robotica con Arduino"</span>
      <input type="text" name="titolo" maxlength="60" value="<?= $e($v['titolo']) ?>">
    </label>
    <label class="largo">Descrizione sull'attestato <span class="muted">facoltativo: continua la frase dopo la virgola (max 200)</span>
      <textarea name="descrizione" maxlength="200" rows="2"><?= $e($v['descrizione']) ?></textarea>
    </label>
    <label>Download attestati fino al <span class="muted">vuoto = mezzanotte che chiude il giorno dopo</span>
      <input type="datetime-local" name="diploma_scadenza" value="<?= $e($v['diploma_scadenza']) ?>">
    </label>
    <label>QR code valido (minuti)
      <input type="number" name="qr_minuti" min="1" max="<?= DIPLOMA_TOKEN_MINUTI_MAX ?>" value="<?= (int) $v['qr_minuti'] ?>" required>
    </label>
    <label class="largo riga-form"><input type="checkbox" name="demo" value="1" <?= $v['demo'] ? 'checked' : '' ?>>
      Sessione demo (prova): i partecipanti non entrano nel registro permanente e l'attestato non consuma numeri</label>
    <?php if ($modifica): ?>
      <label class="largo riga-form"><input type="checkbox" name="attiva" value="1" <?= $v['attiva'] ? 'checked' : '' ?>>
        Login aperto (togli la spunta per chiudere la sessione a mano)</label>
    <?php endif; ?>
    <?php
}

admin_inizio('sessione.php', 'Sessioni');
?>
<?php if ($tutte): ?>
<section class="card">
  <h2>Tutte le sessioni <span class="muted">· possono essere aperte insieme</span></h2>
  <table>
    <thead><tr><th>Nome</th><th>Giornata</th><th>Utente · password</th><th>Stato</th><th class="num">Giocatori</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tutte as $r):
        $st = stato_sessione($r);
        $inCorso = $st === 'attiva'; ?>
      <tr<?= (int) $r['id'] === $sidAdmin ? ' style="background:var(--panel-2)"' : '' ?>>
        <td><b><?= $e($r['nome']) ?></b><?= (int) $r['demo'] === 1 ? ' <span class="muted">(demo)</span>' : '' ?></td>
        <td><?= $e(TIPI_GIORNATA[$r['tipo']]['etichetta'] ?? $r['tipo']) ?> · <?= date('d/m', strtotime($r['data'])) ?></td>
        <td><code><?= $e($r['utente']) ?></code> · <code><?= $e($r['password']) ?></code></td>
        <td><span class="stato <?= $inCorso ? '' : 'chiuso' ?>"><?= $inCorso ? 'aperta' : ($st === 'scaduta' ? 'scaduta' : 'chiusa') ?></span>
          <span class="muted">fino alle <?= date('H:i', strtotime($r['scade_il'])) ?></span></td>
        <td class="num"><?= (int) ($conteggi[$r['id']] ?? 0) ?></td>
        <td style="text-align:right"><?php if ((int) $r['id'] === $sidAdmin): ?><span class="muted">in uso</span><?php else: ?>
          <form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="azione" value="admin_sessione">
            <button type="submit" name="sessione" value="<?= (int) $r['id'] ?>">Gestisci</button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted" style="margin:10px 0 0">Gli ospiti scrivono utente e password al login: la coppia decide in quale sessione entrano. Panoramica, partecipanti e giochi mostrano la sessione scelta nel menu a sinistra.
    Sulla LIM: <code>classifica.php?s=&lt;numero&gt;</code> mostra la classifica di una sessione precisa (il link «Classifica live» del menu lo fa da solo).</p>
</section>
<?php endif; ?>

<?php
$oggi = [
    'nome' => '', 'tipo' => 'openday', 'data' => date('Y-m-d'), 'fine' => '', 'utente' => '', 'password' => '', 'titolo' => '', 'descrizione' => '',
    'diploma_scadenza' => '', 'qr_minuti' => DIPLOMA_TOKEN_MINUTI, 'demo' => false, 'attiva' => true,
];
?>
<section class="card" id="nuova-sessione">
  <?php if ($tutte): ?><details>
    <summary style="margin:0">➕ Nuova sessione</summary>
  <?php else: ?>
    <h2>Benvenuto: crea la prima sessione</h2>
    <p class="muted">Finché non crei una sessione il login degli ospiti resta chiuso. Per far provare gli studenti tutor crea una sessione <b>demo</b> (con un suo utente e una sua password), poi quelle vere.</p>
  <?php endif; ?>
  <form method="post" class="campi" style="margin-top:12px">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="azione" value="sessione_nuova">
    <?php campi_sessione($oggi, false); ?>
    <div class="largo riga-form"><button class="primario" type="submit">Crea la sessione e apri il login</button></div>
  </form>
  <?php if ($tutte): ?></details><?php endif; ?>
</section>

<?php if ($riga !== null):
    $tScadenza = strtotime((string) $riga['scade_il']);
    $diplomaManuale = $riga['diploma_scade_il'] !== null;
    $modifica = [
        'nome' => $riga['nome'], 'tipo' => $riga['tipo'], 'data' => $riga['data'], 'fine' => date('H:i', $tScadenza),
        'utente' => $riga['utente'], 'password' => $riga['password'], 'titolo' => $riga['titolo'], 'descrizione' => $riga['descrizione'],
        'diploma_scadenza' => $diplomaManuale ? date('Y-m-d\TH:i', strtotime((string) $riga['diploma_scade_il'])) : '',
        'qr_minuti' => diploma_token_minuti(), 'demo' => (int) $riga['demo'] === 1, 'attiva' => (int) $riga['attiva'] === 1,
    ];
    $aperto = evento_aperto();
    $nPart = (int) ($conteggi[$sidAdmin] ?? 0);
?>
<section class="card" id="sessione">
  <h2>«<?= $e($riga['nome']) ?>» <span class="stato <?= $aperto ? '' : 'chiuso' ?>"><?= $aperto ? 'aperta' : 'chiusa' ?></span></h2>
  <form method="post" class="campi">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="azione" value="sessione">
    <?php campi_sessione($modifica, true); ?>
    <p class="largo muted" style="margin:0">Durata dei giochi:
      <?php foreach (TIPI_GIORNATA as $codice => $tg): ?>
        <b><?= $e($tg['etichetta']) ?></b> <?= $e($tg['giochi']['durata']) ?> (Duck Coding <?= (int) $tg['giochi']['duck_livelli'] ?> livelli, Binary Lights <?= (int) $tg['giochi']['binary_round'] ?> round, Modifica il gioco <?= (int) $tg['giochi']['palla_missioni'] ?> missioni, Porte logiche <?= count($tg['giochi']['porte_livelli']) ?> livelli, Hack the Web(site) <?= count($tg['giochi']['hack_livelli']) ?> livelli)<?= $codice === array_key_last(TIPI_GIORNATA) ? '.' : ' ·' ?>
      <?php endforeach; ?>
    </p>
    <div class="largo riga-form"><button class="primario" type="submit">Salva sessione</button></div>
  </form>

  <h3>Così apparirà sull'attestato</h3>
  <div class="anteprima-testo">
    <b><?= $e(mb_strtoupper($sessione['etichetta'] . ' · ' . data_italiana($sessione['data']))) ?></b>
    <p>Si attesta che <i>Nome Cognome</i> <?= $e(testo_partecipazione()) ?></p>
    <p class="muted">PDF scaricato: <code><?= $e(nome_file_diploma(['nickname' => 'Nickname'])) ?></code>
      · download fino al <b><?= date('d/m/Y H:i', diploma_scadenza()) ?></b><?= $diplomaManuale ? '' : ' (automatica)' ?></p>
  </div>
</section>

<section class="card pericolo-card">
  <h2>Termina o elimina «<?= $e($riga['nome']) ?>»</h2>
  <form method="post" class="riga-form" data-conferma="Chiudere ora il login di questa sessione? Per riaprirla dovrai cambiare l'ora di fine.">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="azione" value="sessione_termina">
    <button class="pericolo" type="submit" <?= $aperto ? '' : 'disabled' ?>>Termina ora</button>
    <span class="muted">Chiude subito il login. Anche all'ora di fine si chiude da sola; la classifica resta visibile.</span>
  </form>
  <form method="post" class="riga-form" style="margin-top:14px">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="azione" value="sessione_elimina">
    <input type="text" name="conferma" placeholder="Scrivi ELIMINA" autocomplete="off" required>
    <button class="pericolo pieno" type="submit">Elimina la sessione</button>
    <span class="muted">Cancella la sessione e i suoi <?= $nPart ?> partecipanti con i punteggi. Il registro permanente resta. Non si può annullare.</span>
  </form>
</section>
<?php endif; ?>

<script>
document.querySelectorAll('[data-genera-pass]').forEach((b) => b.addEventListener('click', () => {
  const lettere = 'abcdefghjkmnpqrstuvwxyz23456789', r = crypto.getRandomValues(new Uint8Array(8));
  b.closest('label').querySelector('input').value = Array.from(r, (x) => lettere[x % lettere.length]).join('');
}));
</script>
<?php admin_fine(); ?>
