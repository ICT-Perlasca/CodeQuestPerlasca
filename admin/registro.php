<?php
/** Admin · Registro permanente dei partecipanti: arrivi per turno, elenco nominativo, export CSV. */
declare(strict_types=1);

require_once __DIR__ . '/_base.php';

/* ---------------------------------------------------------------- export CSV del registro */
if (($_GET['export'] ?? '') === 'csv') {
    [$dal, $al] = intervallo_registro(db());
    $st = db()->prepare(
        'SELECT arrivato_il, nickname, nome, punti, diploma_il, numero_attestato FROM registro
          WHERE arrivato_il BETWEEN ? AND ? ORDER BY arrivato_il, id'
    );
    $st->execute(parametri_intervallo($dal, $al));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="registro-openday-' . $dal->format('Y-m-d_Hi') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel riconosce l'UTF-8 (accenti nei nomi)
    // il punto e virgola e' il separatore che Excel in italiano si aspetta
    $riga = static fn(array $campi) => fputcsv($out, array_map(
        static fn($v): string => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : (string) $v, // niente formule
        $campi
    ), ';', '"', '');
    $riga(['Arrivo', 'Nickname', 'Nome e cognome', 'Punti', 'Diploma scaricato', 'N. attestato']);
    while ($r = $st->fetch()) {
        $riga([
            date('d/m/Y H:i', strtotime($r['arrivato_il'])),
            $r['nickname'],
            $r['nome'] ?? '',
            $r['punti'],
            $r['diploma_il'] ? date('d/m/Y H:i', strtotime($r['diploma_il'])) : '',
            $r['numero_attestato'] ?? '',
        ]);
    }
    exit;
}

$pdo = db();
    [$dal, $al] = intervallo_registro($pdo);
    $intervallo = parametri_intervallo($dal, $al);
    $st = $pdo->prepare(
        'SELECT COUNT(*) AS totale, COALESCE(SUM(nome IS NOT NULL), 0) AS con_nome, COALESCE(SUM(diploma_il IS NOT NULL), 0) AS diplomi
           FROM registro WHERE arrivato_il BETWEEN ? AND ?'
    );
    $st->execute($intervallo);
    $turno = $st->fetch();
    $st = $pdo->prepare(
        "SELECT DATE_FORMAT(arrivato_il, '%Y-%m-%d %H:00') AS fascia, COUNT(*) AS arrivi,
                SUM(nome IS NOT NULL) AS con_nome, SUM(diploma_il IS NOT NULL) AS diplomi
           FROM registro WHERE arrivato_il BETWEEN ? AND ?
          GROUP BY fascia ORDER BY fascia"
    );
    $st->execute($intervallo);
    $fasce = $st->fetchAll();
    $maxArrivi = max([1, ...array_map(static fn(array $f): int => (int) $f['arrivi'], $fasce)]);
    $st = $pdo->prepare(
        'SELECT arrivato_il, nickname, nome, punti, diploma_il, numero_attestato FROM registro
          WHERE arrivato_il BETWEEN ? AND ? ORDER BY arrivato_il, id LIMIT 1000'
    );
    $st->execute($intervallo);
    $registro = $st->fetchAll();
    $piuGiorni = $dal->format('Y-m-d') !== $al->format('Y-m-d');

admin_inizio('registro.php', 'Registro');
?>
<section class="card" id="registro">
  <h2>Arrivi <span class="muted">(registro permanente)</span></h2>
  <form method="get" class="riga-form">
    <label class="riga-form">Dal <input type="datetime-local" name="dal" value="<?= $dal->format('Y-m-d\TH:i') ?>" required></label>
    <label class="riga-form">al <input type="datetime-local" name="al" value="<?= $al->format('Y-m-d\TH:i') ?>" required></label>
    <button class="primario" type="submit">Mostra turno</button>
    <a class="btn" href="?<?= $e(http_build_query(['export' => 'csv', 'dal' => $dal->format('Y-m-d\TH:i'), 'al' => $al->format('Y-m-d\TH:i')])) ?>">⬇️ Esporta CSV</a>
  </form>

  <div class="numeri" style="margin:12px 0 0">
    <div><b><?= (int) $turno['totale'] ?></b><span>arrivati nell'intervallo</span></div>
    <div><b><?= (int) $turno['con_nome'] ?></b><span>con nome e cognome</span></div>
    <div><b><?= (int) $turno['diplomi'] ?></b><span>diplomi scaricati</span></div>
  </div>

  <?php if ($fasce): ?>
    <h3>Arrivi per fascia oraria <span class="muted">· tocca una fascia per vederla da sola</span></h3>
    <div class="scorri">
      <table>
        <thead><tr><th>Fascia</th><th></th><th style="text-align:right">Arrivi</th><th style="text-align:right">Con nome</th><th style="text-align:right">Diplomi</th></tr></thead>
        <tbody>
        <?php foreach ($fasce as $f):
            $inizio = new DateTime($f['fascia']); ?>
          <tr>
            <td><a href="?<?= $e(http_build_query(['dal' => $inizio->format('Y-m-d\TH:00'), 'al' => $inizio->format('Y-m-d\TH:59')])) ?>">
              <?= $piuGiorni ? $inizio->format('d/m ') : '' ?><?= $inizio->format('H:00') ?>–<?= $inizio->format('H:59') ?></a></td>
            <td class="barra-cella"><div class="barra" style="width:<?= round(100 * $f['arrivi'] / $maxArrivi) ?>%"></div></td>
            <td class="num"><?= (int) $f['arrivi'] ?></td>
            <td class="num"><?= (int) $f['con_nome'] ?></td>
            <td class="num"><?= (int) $f['diplomi'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <details>
      <summary>Elenco nominativo (<?= count($registro) ?><?= count($registro) === 1000 ? ', primi 1000: usa il CSV' : '' ?>)</summary>
      <div class="scorri">
        <table style="margin-top:8px">
          <thead><tr><th>Arrivo</th><th>Nickname</th><th>Nome e cognome</th><th style="text-align:right">Punti</th><th>Diploma</th><th style="text-align:right">N. attestato</th></tr></thead>
          <tbody>
          <?php foreach ($registro as $r): ?>
            <tr>
              <td><?= date($piuGiorni ? 'd/m H:i' : 'H:i', strtotime($r['arrivato_il'])) ?></td>
              <td><?= $e($r['nickname']) ?></td>
              <td><?= $r['nome'] !== null ? $e($r['nome']) : '<span class="muted">—</span>' ?></td>
              <td class="num"><?= (int) $r['punti'] ?></td>
              <td><?= $r['diploma_il'] ? date('H:i', strtotime($r['diploma_il'])) : '<span class="muted">—</span>' ?></td>
              <td class="num"><?= $r['numero_attestato'] !== null ? (int) $r['numero_attestato'] : '<span class="muted">—</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  <?php else: ?>
    <p class="muted" style="margin:12px 0 0">Nessun arrivo in questo intervallo.</p>
  <?php endif; ?>
  <p class="muted" style="margin:12px 0 0">Si conta un partecipante per telefono: chi gioca in gruppo su un solo telefono vale uno.
    Il nome compare quando il ragazzo lo scrive per il diploma. Il registro non viene toccato da "Azzera classifica" né dall'eliminazione dei nickname.</p>
</section>
<?php admin_fine(); ?>
