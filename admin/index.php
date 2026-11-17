<?php
/** Admin · Panoramica: numeri chiave, stato di evento e giochi, arrivi per fascia oraria, primi in classifica. */
declare(strict_types=1);

require_once __DIR__ . '/_base.php';

$pdo = db();
// numeri della sessione scelta nel menu (admin/_base.php); registro e fasce orarie sono invece permanenti, di tutte le giornate
$stat = $pdo->prepare(
    'SELECT COUNT(*) AS totale, COALESCE(SUM(nascosto), 0) AS nascosti, COALESCE(SUM(punti_totali > 0), 0) AS giocanti,
            COALESCE(AVG(CASE WHEN punti_totali > 0 THEN punti_totali END), 0) AS media
       FROM partecipanti WHERE sessione_id = ?'
);
$stat->execute([$sidAdmin]);
$stat = $stat->fetch();
$diplomi = 0;
$fasce = [];
$maxArrivi = 1;
try { // la tabella registro puo' mancare sui DB vecchi
    $diplomi = (int) $pdo->query('SELECT COUNT(*) FROM registro WHERE diploma_il IS NOT NULL')->fetchColumn();
    [$dal, $al] = intervallo_registro($pdo); // di default l'ultima giornata con arrivi
    $st = $pdo->prepare(
        "SELECT DATE_FORMAT(arrivato_il, '%H:00') AS fascia, COUNT(*) AS arrivi
           FROM registro WHERE arrivato_il BETWEEN ? AND ? GROUP BY fascia ORDER BY fascia"
    );
    $st->execute(parametri_intervallo($dal, $al));
    $fasce = $st->fetchAll();
    $maxArrivi = max([1, ...array_map(static fn(array $f): int => (int) $f['arrivi'], $fasce)]);
} catch (PDOException $ex) {
    error_log('[openday] registro: ' . $ex->getMessage());
}
$top = $pdo->prepare(
    'SELECT nickname, avatar, punti_totali FROM partecipanti WHERE sessione_id = ? AND nascosto = 0 AND punti_totali > 0
      ORDER BY punti_totali DESC, updated_at ASC, id ASC LIMIT 5'
);
$top->execute([$sidAdmin]);
$top = $top->fetchAll();

$sessione = sessione_attivita();
$conteggio = ['attivo' => 0, 'disabilitato' => 0, 'nascosto' => 0];
foreach (array_keys(GIOCHI) as $codice) {
    $conteggio[stato_gioco($codice)]++;
}
$aperto = evento_aperto();
$scadenza = strtotime((string) (sessione_corrente()['scade_il'] ?? ''));

admin_inizio('index.php', 'Panoramica');
?>
<div class="kpi">
  <div><strong><?= (int) $stat['totale'] ?></strong><span>partecipanti</span></div>
  <div><strong><?= (int) $stat['giocanti'] ?></strong><span>con punti</span></div>
  <div><strong><?= (int) round((float) $stat['media']) ?></strong><span>punti medi</span></div>
  <div><strong><?= $diplomi ?></strong><span>diplomi scaricati</span></div>
</div>

<div class="griglia">
  <section class="card">
    <h2><?= $e($sessione['nome']) ?> <span class="stato <?= $aperto ? '' : 'chiuso' ?>"><?= $aperto ? 'aperta' : 'chiusa' ?></span></h2>
    <p class="muted" style="margin:0 0 10px"><?= $e($sessione['etichetta']) ?><?= $sessione['demo'] ? ' (demo)' : '' ?> del <?= date('d/m/Y', $sessione['data']) ?>
      <?= $scadenza ? '· login fino al ' . date('d/m H:i', $scadenza) : '' ?> · utente <code><?= $e(ospite_utente()) ?></code> · password <code><?= $e(ospite_pass()) ?></code></p>
    <a class="btn" href="sessione.php">Sessioni →</a>
  </section>
  <section class="card">
    <h2>Giochi</h2>
    <p class="muted" style="margin:0 0 10px"><?= $conteggio['attivo'] ?> attivi · <?= $conteggio['disabilitato'] ?> disabilitati · <?= $conteggio['nascosto'] ?> nascosti</p>
    <a class="btn" href="giochi.php">Gestisci →</a>
  </section>
</div>

<div class="griglia">
  <section class="card">
    <h2>Arrivi per fascia oraria</h2>
    <?php if (!$fasce): ?>
      <p class="muted">Nessun arrivo registrato.</p>
    <?php else: ?>
      <table><tbody>
      <?php foreach ($fasce as $f): ?>
        <tr><td style="white-space:nowrap"><?= $e($f['fascia']) ?></td>
          <td class="barra-cella"><div class="barra" style="width:<?= round(100 * $f['arrivi'] / $maxArrivi) ?>%"></div></td>
          <td class="num"><?= (int) $f['arrivi'] ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
      <p class="muted" style="margin:8px 0 0"><a href="registro.php">Dettaglio nel registro →</a></p>
    <?php endif; ?>
  </section>
  <section class="card">
    <h2>Primi in classifica</h2>
    <?php if (!$top): ?>
      <p class="muted">Ancora nessun punteggio.</p>
    <?php else: ?>
      <table><tbody>
      <?php foreach ($top as $i => $t): ?>
        <tr><td><?= ['🥇', '🥈', '🥉', '4°', '5°'][$i] ?> <span data-avatar="<?= $e($t['avatar']) ?>"></span> <?= $e($t['nickname']) ?></td>
          <td class="num"><?= (int) $t['punti_totali'] ?> pt</td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </section>
</div>
<?php admin_fine(); ?>
