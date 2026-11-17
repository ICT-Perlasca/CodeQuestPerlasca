<?php
/** Admin · Sistema: azioni irreversibili (azzera classifica, svuota registro). */
declare(strict_types=1);

require_once __DIR__ . '/_base.php';

admin_inizio('sistema.php', 'Sistema');
?>
<section class="card pericolo-card">
    <h2>Azzera classifica di questa sessione</h2>
    <p class="muted" style="margin:0 0 10px">Elimina i partecipanti e i punteggi della sessione scelta nel menu (<b><?= $e(sessione_attivita()['nome']) ?></b>), senza toccare le altre sessioni. Il registro partecipanti resta. Non si può annullare.</p>
    <form method="post" class="riga-form">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <input type="hidden" name="azione" value="reset">
      <input type="text" name="conferma" placeholder="Scrivi RESET" autocomplete="off" required>
      <button class="pericolo pieno" type="submit" <?= $sidAdmin === null ? 'disabled' : '' ?>>Azzera la classifica</button>
    </form>
    <h2 style="margin-top:20px">Svuota registro</h2>
    <p class="muted" style="margin:0 0 10px">Cancella l'elenco permanente dei partecipanti, nomi compresi (es. prima di un nuovo anno). Esporta prima il CSV.</p>
    <form method="post" class="riga-form">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <input type="hidden" name="azione" value="registro_svuota">
      <input type="text" name="conferma" placeholder="Scrivi SVUOTA" autocomplete="off" required>
      <button class="pericolo pieno" type="submit">Svuota registro</button>
    </form>
</section>
<?php admin_fine(); ?>
