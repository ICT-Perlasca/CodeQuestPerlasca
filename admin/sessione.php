<?php
/** Admin · Sessione: tipo di giornata, testi dell'attestato, apertura e scadenza dell'evento. */
declare(strict_types=1);

require_once __DIR__ . '/_base.php';

$pdo = db();
$impostazioni = $pdo->query('SELECT chiave, valore FROM impostazioni')->fetchAll(PDO::FETCH_KEY_PAIR);
$tScadenza = strtotime($impostazioni['scadenza'] ?? '');
$scadenzaInput = $tScadenza ? date('Y-m-d\TH:i', $tScadenza) : '';
$sessione = sessione_attivita();
// la scadenza del login cade in un altro giorno rispetto all'attivita': quasi sempre una svista
$scadenzaAltroGiorno = $tScadenza && date('Y-m-d', $tScadenza) !== date('Y-m-d', $sessione['data']);
$aperto = evento_aperto();
$diplomaManuale = !empty($impostazioni['diploma_scadenza']);
$diplomaInput = $diplomaManuale ? date('Y-m-d\TH:i', diploma_scadenza()) : '';

$stato = sessione_stato();
admin_inizio('sessione.php', $stato === 'attiva' ? 'Sessione' : 'Nuova sessione');
if ($stato !== 'attiva'):
    $scaduta = $stato === 'scaduta';
    $nPart = (int) $pdo->query('SELECT COUNT(*) FROM partecipanti')->fetchColumn();
?>
<section class="card" id="nuova-sessione">
  <h2><?= $scaduta ? 'La sessione è terminata' : 'Benvenuto: configura la prima sessione' ?></h2>
  <p class="muted"><?= $scaduta
      ? 'Per riaprire il gioco crea una nuova sessione: imposta di nuovo giornata, ora di fine e password. Fino ad allora gli ospiti non possono entrare.'
      : "Finché non crei la sessione il login degli ospiti resta chiuso. Compila tutti i campi obbligatori e il gioco si apre." ?></p>
  <form method="post" class="campi">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="azione" value="sessione_nuova">
    <label>Tipo di giornata
      <select name="tipo" required>
        <?php foreach (TIPI_GIORNATA as $codice => $tg): ?>
          <option value="<?= $e($codice) ?>" <?= $sessione['tipo'] === $codice ? 'selected' : '' ?>><?= $e($tg['etichetta']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Giorno dell'attività
      <input type="date" name="data" value="<?= date('Y-m-d') ?>" required>
    </label>
    <label>Fine sessione (chiude il login)
      <input type="time" name="fine" required>
    </label>
    <label>Password ospiti <span class="muted">4-30 lettere/numeri, da scrivere sul cartellone</span>
      <span class="riga-form">
        <input type="text" name="ospite_pass" id="ospite-pass" maxlength="30" autocomplete="off" autocapitalize="none" required>
        <button type="button" id="genera-pass">Suggerisci</button>
      </span>
    </label>
    <label class="largo">Titolo dell'attività <span class="muted">facoltativo, es. "Robotica con Arduino"</span>
      <input type="text" name="titolo" maxlength="60">
    </label>
    <label class="largo">Descrizione sull'attestato <span class="muted">facoltativo: continua la frase dopo la virgola (max 200)</span>
      <textarea name="descrizione" maxlength="200" rows="2"></textarea>
    </label>
    <label>Download attestati fino al <span class="muted">vuoto = mezzanotte che chiude il giorno dopo</span>
      <input type="datetime-local" name="diploma_scadenza">
    </label>
    <label>QR code valido (minuti)
      <input type="number" name="qr_minuti" min="1" max="<?= DIPLOMA_TOKEN_MINUTI_MAX ?>" value="<?= diploma_token_minuti() ?>" required>
    </label>
    <?php if ($nPart > 0): ?>
      <label class="largo riga-form"><input type="checkbox" name="azzera" value="1" <?= $scaduta ? 'checked' : '' ?>>
        Azzera la classifica (<?= $nPart ?> partecipanti): il registro permanente non viene toccato</label>
    <?php endif; ?>
    <div class="largo riga-form"><button class="primario" type="submit"><?= $scaduta ? 'Crea nuova sessione e riapri il login' : 'Crea la sessione e apri il login' ?></button></div>
  </form>
</section>
<script>
document.getElementById('genera-pass').addEventListener('click', () => {
  const lettere = 'abcdefghjkmnpqrstuvwxyz23456789', b = crypto.getRandomValues(new Uint8Array(8));
  document.getElementById('ospite-pass').value = Array.from(b, (x) => lettere[x % lettere.length]).join('');
});
</script>
<?php
    admin_fine();
    exit;
endif;
?>
<section class="card" id="sessione">
  <h2>Giornata <span class="muted">· da impostare prima di aprire le porte</span></h2>
  <form method="post" class="campi">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="azione" value="sessione">
    <label>Tipo di giornata
      <select name="tipo" required>
        <?php foreach (TIPI_GIORNATA as $codice => $tg): ?>
          <option value="<?= $e($codice) ?>" <?= $sessione['tipo'] === $codice ? 'selected' : '' ?>><?= $e($tg['etichetta']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Giorno dell'attività
      <input type="date" name="data" value="<?= date('Y-m-d', $sessione['data']) ?>" required>
    </label>
    <label>Fine (chiude il login) <span class="muted">facoltativo</span>
      <input type="time" name="fine" value="">
    </label>
    <label class="largo">Titolo dell'attività <span class="muted">facoltativo, es. "Robotica con Arduino"</span>
      <input type="text" name="titolo" maxlength="60" value="<?= $e($sessione['titolo']) ?>">
    </label>
    <label class="largo">Descrizione sull'attestato <span class="muted">facoltativo: continua la frase dopo la virgola (max 200)</span>
      <textarea name="descrizione" maxlength="200" rows="2" placeholder="<?= $e($sessione['attivita']) ?>"><?= $e($sessione['descrizione']) ?></textarea>
    </label>
    <p class="largo muted" style="margin:0">Durata dei giochi:
      <?php foreach (TIPI_GIORNATA as $codice => $tg): ?>
        <b><?= $e($tg['etichetta']) ?></b> <?= $e($tg['giochi']['durata']) ?> (Duck Coding <?= (int) $tg['giochi']['duck_livelli'] ?> livelli, Binary Lights <?= (int) $tg['giochi']['binary_round'] ?> round, Modifica il gioco <?= (int) $tg['giochi']['palla_missioni'] ?> missioni, Porte logiche <?= count($tg['giochi']['porte_livelli']) ?> livelli, Hack the Web(site) <?= count($tg['giochi']['hack_livelli']) ?> livelli)<?= $codice === array_key_last(TIPI_GIORNATA) ? '.' : ' ·' ?>
      <?php endforeach; ?>
    </p>
    <div class="largo riga-form"><button class="primario" type="submit">Salva sessione</button></div>
  </form>

  <?php if ($scadenzaAltroGiorno): ?>
    <p class="flash ko" style="margin:12px 0 0">Attenzione: la scadenza del login (<?= date('d/m/Y H:i', $tScadenza) ?>) non è nel giorno dell'attività.
      Indica l'ora di fine qui sopra o correggila nel riquadro Evento.</p>
  <?php endif; ?>

  <h3>Così apparirà sull'attestato</h3>
  <div class="anteprima-testo">
    <b><?= $e(mb_strtoupper($sessione['etichetta'] . ' · ' . data_italiana($sessione['data']))) ?></b>
    <p>Si attesta che <i>Nome Cognome</i> <?= $e(testo_partecipazione()) ?></p>
    <p class="muted">PDF scaricato: <code><?= $e(nome_file_diploma(['nickname' => 'Nickname'])) ?></code></p>
  </div>
</section>

  <section class="card">
    <h2>Evento <span class="stato <?= $aperto ? '' : 'chiuso' ?>"><?= $aperto ? 'aperto' : 'chiuso' ?></span></h2>
    <form method="post" class="riga-form">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <input type="hidden" name="azione" value="evento">
      <label class="riga-form"><input type="checkbox" name="attivo" <?= ($impostazioni['evento_attivo'] ?? '0') === '1' ? 'checked' : '' ?>> Login attivo</label>
      <label class="riga-form">Scade il <input type="datetime-local" name="scadenza" value="<?= $e($scadenzaInput) ?>" required></label>
      <button class="primario" type="submit">Salva</button>
    </form>
    <p class="muted" style="margin:10px 0 0">Dopo la scadenza (o con "Login attivo" spento) nessuno può entrare né salvare punti. La classifica resta visibile.</p>
  </section>

  <section class="card pericolo-card">
    <h2>Termina la sessione</h2>
    <form method="post" class="riga-form" onsubmit="return confirm('Chiudere ora il login? Per riaprire dovrai creare una nuova sessione.')">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <input type="hidden" name="azione" value="sessione_termina">
      <button class="pericolo" type="submit">Termina ora</button>
    </form>
    <p class="muted" style="margin:10px 0 0">Chiude subito il login. Per riaprire il gioco bisogna creare una nuova sessione (giornata, ora di fine, nuova password). La sessione si chiude comunque da sola all'ora di fine.</p>
  </section>

  <section class="card">
    <h2>Password ospiti</h2>
    <form method="post" class="riga-form">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <input type="hidden" name="azione" value="password_ospite">
      <label class="riga-form">Password <input type="text" name="ospite_pass" value="<?= $e(ospite_pass()) ?>" autocomplete="off" autocapitalize="none" maxlength="30"></label>
      <button class="primario" type="submit">Salva</button>
    </form>
    <p class="muted" style="margin:10px 0 0">È la password che gli studenti scrivono al login (utente <code><?= $e(OSPITE_USER) ?></code>) e che compare in classifica. Cambiala per ogni sessione; chi è già entrato resta collegato. Vuoto = quella di <code>inc/config.php</code>.</p>
  </section>

  <section class="card">
    <h2>Download attestati <span class="stato <?= diploma_aperto() ? '' : 'chiuso' ?>"><?= diploma_aperto() ? 'aperto' : 'chiuso' ?></span></h2>
    <form method="post" class="riga-form">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <input type="hidden" name="azione" value="diploma">
      <label class="riga-form">Scaricabili fino al <input type="datetime-local" name="diploma_scadenza" value="<?= $e($diplomaInput) ?>"></label>
      <label class="riga-form">QR code valido <input type="number" name="qr_minuti" min="1" max="<?= DIPLOMA_TOKEN_MINUTI_MAX ?>" value="<?= diploma_token_minuti() ?>" required style="width:5em"> minuti</label>
      <button class="primario" type="submit">Salva</button>
    </form>
    <p class="muted" style="margin:10px 0 0">Oltre questa data/ora gli studenti non possono più scaricare il PDF né generare il QR code (l'anteprima resta visibile). Indipendente dalla scadenza del login. Lasciando il campo vuoto vale la scadenza automatica: la mezzanotte che chiude il giorno dopo l'attività. Ora scade il <b><?= date('d/m/Y H:i', diploma_scadenza()) ?></b><?= $diplomaManuale ? '' : ' (automatica)' ?>.</p>
  </section>
<?php admin_fine(); ?>
