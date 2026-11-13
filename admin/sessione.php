<?php
/** Admin · Sessione: tipo di giornata, testi dell'attestato, apertura e scadenza dell'evento. */
declare(strict_types=1);

require_once __DIR__ . '/_base.php';

$pdo = db();
$impostazioni = $pdo->query('SELECT chiave, valore FROM impostazioni')->fetchAll(PDO::FETCH_KEY_PAIR);
$tScadenza = strtotime($impostazioni['scadenza'] ?? '');
$scadenzaInput = $tScadenza ? date('Y-m-d\TH:i', $tScadenza) : '';
$sessione = sessione_attivita();
$sessioneConfigurata = isset($impostazioni['sessione_data']);
// la scadenza del login cade in un altro giorno rispetto all'attivita': quasi sempre una svista
$scadenzaAltroGiorno = $tScadenza && date('Y-m-d', $tScadenza) !== date('Y-m-d', $sessione['data']);
$aperto = evento_aperto();

admin_inizio('sessione.php', 'Sessione');
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

  <?php if (!$sessioneConfigurata): ?>
    <p class="flash ko" style="margin:12px 0 0">Sessione non ancora salvata: i PDF usano "Open Day" e la data della scadenza del login.</p>
  <?php elseif ($scadenzaAltroGiorno): ?>
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
<?php admin_fine(); ?>
