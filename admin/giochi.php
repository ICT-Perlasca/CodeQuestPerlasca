<?php
/**
 * Admin · Giochi: stato di ogni gioco (attivo / disabilitato / nascosto) e, con ?g=codice,
 * la scheda statistiche del singolo gioco (fin dove arrivano, i migliori; Duck Coding: tempi per livello).
 */
declare(strict_types=1);

require_once __DIR__ . '/_base.php';

$pdo = db();
    // solo i giochi che esistono (GIOCHI in inc/giochi.php): le attivita' mai fatte o tolte non si vedono
    $codiciGiochi = array_keys(GIOCHI);
    $segnaposto = implode(', ', array_fill(0, count($codiciGiochi), '?'));
    $st = $pdo->prepare(
        "SELECT m.codice, m.titolo, COUNT(p.partecipante_id) AS giocatori, COALESCE(MAX(p.punti), 0) AS record,
                COALESCE(AVG(p.punti), 0) AS media, COALESCE(AVG(p.tentativi), 0) AS tentativi, m.max_punti
           FROM moduli m
           LEFT JOIN (SELECT pu.* FROM punteggi pu JOIN partecipanti pa ON pa.id = pu.partecipante_id WHERE pa.sessione_id = " . (int) $sidAdmin . ") p
                  ON p.modulo = m.codice
          WHERE m.codice IN ($segnaposto)
          GROUP BY m.codice, m.titolo, m.max_punti, m.percorso_ordine
          ORDER BY m.percorso_ordine IS NULL, m.percorso_ordine, m.titolo"
    );
    $st->execute($codiciGiochi);
    $moduli = $st->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

$scelto = (string) ($_GET['g'] ?? '');
if (!isset(GIOCHI[$scelto])) {
    $scelto = '';
}

$schede = [];
if ($scelto !== '' && $scelto !== 'paperelle_coding') {
    // schermata di ogni gioco (tranne Duck Coding, che ha i tempi per livello piu' sotto):
    // fin dove arrivano i ragazzi e i migliori. Nessuna tabella nuova: basta `punteggi`.
    $schede = [];
    foreach ($moduli as $codice => $m) {
        if ($codice === 'paperelle_coding' || $codice !== $scelto) {
            continue;
        }
        $g = GIOCHI[$codice];
        $n = livelli_sessione($codice);
        $tetto = max_punti_sessione($codice, (int) $m['max_punti']);
        $st = $pdo->prepare(
            'SELECT p.punti, p.tentativi, p.updated_at, pa.nickname, pa.avatar, pa.nascosto, r.nome
               FROM punteggi p
               JOIN partecipanti pa ON pa.id = p.partecipante_id
               LEFT JOIN registro r ON r.uuid = pa.uuid
              WHERE p.modulo = ? AND pa.sessione_id = ?
              ORDER BY p.punti DESC, p.updated_at ASC'
        );
        $st->execute([$codice, $sidAdmin]);
        $righe = $st->fetchAll();

        // Binary Lights: i punti dipendono anche dalla velocita' -> fasce di punteggio.
        // Gli altri: 100 punti per livello superato, e i livelli si sbloccano in ordine -> "fin dove arrivano".
        $barre = [];
        if ($codice === 'binary_lights') {
            $fasce = 5;
            $passo = (int) max(1, ceil($tetto / $fasce));
            for ($i = 0; $i < $fasce; $i++) {
                $da = $i * $passo;
                $a = $i === $fasce - 1 ? PHP_INT_MAX : ($i + 1) * $passo - 1;
                $barre[] = [
                    'etichetta' => $i === $fasce - 1 ? "da {$da}" : "{$da}–{$a}",
                    'conta'     => count(array_filter($righe, static fn(array $r): bool => (int) $r['punti'] >= $da && (int) $r['punti'] <= $a)),
                ];
            }
        } else {
            for ($k = 1; $k <= $n; $k++) {
                $barre[] = [
                    'etichetta' => "{$k}° " . rtrim($g['unita'], 'i') . ($g['unita'] === 'missioni' ? 'e' : 'o'),
                    'conta'     => count(array_filter($righe, static fn(array $r): bool => (int) $r['punti'] >= $k * $g['per'])),
                ];
            }
        }
        $schede[$codice] = [
            'titolo'  => $m['titolo'],
            'icona'   => $g['icona'],
            'unita'   => $g['unita'],
            'n'       => $n,
            'tetto'   => $tetto,
            'righe'   => $righe,
            'barre'   => $barre,
            'max'     => max([1, ...array_column($barre, 'conta')]),
            'finito'  => count(array_filter($righe, static fn(array $r): bool => (int) $r['punti'] >= $tetto)),
        ];
    }
}

$codegame = null;
if ($scelto === 'paperelle_coding') {
    // Duck Coding: tempi per livello e i piu' veloci (la tabella puo' mancare sui DB vecchi)
    $codegame = null;
    try {
        $perLivello = $pdo->prepare(
            'SELECT t.livello, COUNT(*) AS aperti, COUNT(t.ms) AS risolti, AVG(t.ms) AS media, MIN(t.ms) AS migliore,
                    AVG(t.esecuzioni) AS esecuzioni, AVG(t.punti) AS punti
               FROM codegame_tempi t JOIN partecipanti p ON p.id = t.partecipante_id
              WHERE p.sessione_id = ? GROUP BY t.livello'
        );
        $perLivello->execute([$sidAdmin]);
        $perLivello = $perLivello->fetchAll(PDO::FETCH_UNIQUE);
        $codegame = ['livelli' => [], 'top' => []];
        foreach (codegame_livelli() as $n => $l) {
            [$mediaPunti] = codegame_media_ms($pdo, $n);
            $codegame['livelli'][$n] = ['titolo' => $l['titolo'], 'media_punti' => $mediaPunti] + ($perLivello[$n] ?? []);
        }
        $top = $pdo->prepare(
            'SELECT p.nickname, r.nome, COUNT(t.ms) AS risolti, SUM(t.ms) AS tempo, SUM(t.punti) AS punti
               FROM codegame_tempi t
               JOIN partecipanti p ON p.id = t.partecipante_id
               LEFT JOIN registro r ON r.uuid = p.uuid
              WHERE t.ms IS NOT NULL AND p.sessione_id = ?
              GROUP BY p.id, p.nickname, r.nome
              ORDER BY risolti DESC, punti DESC, tempo ASC
              LIMIT 15'
        );
        $top->execute([$sidAdmin]);
        $codegame['top'] = $top->fetchAll();
    } catch (PDOException $ex) {
        error_log('[openday] codegame_tempi: ' . $ex->getMessage());
    }
}

admin_inizio('giochi.php', $scelto === '' ? 'Giochi' : ($moduli[$scelto]['titolo'] ?? 'Gioco'));
?>
<?php if ($scelto === ''): ?>
<section class="card" id="giochi-stato">
  <h2>Stato di ogni gioco</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="azione" value="giochi">
    <div class="scorri">
    <table>
      <thead><tr><th>Gioco</th><th>Stato</th><th style="text-align:right">Giocatori</th><th style="text-align:right">Record</th><th></th></tr></thead>
      <tbody>
      <?php foreach (GIOCHI as $codice => $g): $m = $moduli[$codice] ?? ['titolo' => $codice, 'giocatori' => 0, 'record' => 0, 'max_punti' => 0]; ?>
        <tr>
          <td><?= $g['icona'] ?> <?= $e($m['titolo']) ?></td>
          <td>
            <select name="stato[<?= $e($codice) ?>]">
              <?php foreach (STATI_GIOCO as $k => $et): ?>
                <option value="<?= $e($k) ?>" <?= stato_gioco($codice) === $k ? 'selected' : '' ?>><?= $e($et) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td class="num"><?= (int) $m['giocatori'] ?></td>
          <td class="num"><?= (int) $m['record'] ?><span class="muted">/<?= max_punti_sessione($codice, (int) $m['max_punti']) ?></span></td>
          <td><a class="btn" href="?g=<?= $e($codice) ?>">Statistiche →</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p class="muted" style="margin:10px 0">Disabilitato: resta nella home ma grigio, non si può giocare né salvare punti. Nascosto: sparisce dalla home. I punti già fatti restano in classifica.</p>
    <button class="primario" type="submit">Salva giochi</button>
  </form>
</section>
<?php else: ?>
<p><a href="giochi.php">← Tutti i giochi</a></p>
  <?php if ($scelto === 'paperelle_coding'): ?>
<section class="card" id="gioco-paperelle_coding">
    <h2>Tempi per livello</h2>
    <?php if ($codegame === null): ?>
      <p class="muted">Tabella <code>codegame_tempi</code> mancante: creala con lo SQL in <code>db/schema.sql</code>.</p>
    <?php else: ?>
      <?php $sec = static fn($ms): string => $ms === null ? '–' : number_format($ms / 1000, 1, ',', '') . ' s'; ?>
      <div style="overflow-x:auto">
      <table>
        <thead><tr><th>Livello</th><th style="text-align:right">Aperto</th><th style="text-align:right">Risolto</th>
          <th style="text-align:right">Tempo medio</th><th style="text-align:right">Migliore</th>
          <th style="text-align:right" title="Media con 3 tempi di riferimento aggiunti: e' quella che decide i punti">Media punti</th>
          <th style="text-align:right">Tentativi</th><th style="text-align:right">Punti medi</th></tr></thead>
        <tbody>
        <?php foreach ($codegame['livelli'] as $n => $l): ?>
          <tr>
            <td><?= $n ?>. <?= $e($l['titolo']) ?></td>
            <td class="num"><?= (int) ($l['aperti'] ?? 0) ?></td>
            <td class="num"><?= (int) ($l['risolti'] ?? 0) ?></td>
            <td class="num"><?= $sec(isset($l['media']) ? (float) $l['media'] : null) ?></td>
            <td class="num"><?= $sec(isset($l['migliore']) ? (int) $l['migliore'] : null) ?></td>
            <td class="num"><?= $sec($l['media_punti']) ?></td>
            <td class="num"><?= isset($l['esecuzioni']) ? number_format((float) $l['esecuzioni'], 1, ',', '') : '–' ?></td>
            <td class="num"><?= isset($l['punti']) ? (int) round((float) $l['punti']) : '–' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <h3>I più bravi</h3>
      <?php if (!$codegame['top']): ?>
        <p class="muted">Nessun livello risolto finora.</p>
      <?php else: ?>
        <table>
          <thead><tr><th>Nickname</th><th style="text-align:right">Livelli</th><th style="text-align:right">Tempo totale</th><th style="text-align:right">Punti</th></tr></thead>
          <tbody>
          <?php foreach ($codegame['top'] as $t): ?>
            <tr>
              <td><?= $e($t['nickname']) ?><?php if ($t['nome']): ?> <span class="muted">· <?= $e($t['nome']) ?></span><?php endif; ?></td>
              <td class="num"><?= (int) $t['risolti'] ?>/<?= count($codegame['livelli']) ?></td>
              <td class="num"><?= $sec((int) $t['tempo']) ?></td>
              <td class="num"><?= (int) $t['punti'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <p class="muted" style="margin:10px 0 0">Il tempo parte alla prima apertura del livello e si ferma alla prima soluzione.
        Punti = 100 × media ÷ tempo (da 30 a 130) + 20 se usano i blocchi minimi. "Tentativi" = quante volte hanno premuto Esegui.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>
<?php foreach ($schede as $codice => $s): ?>
  <section class="card gioco" id="gioco-<?= $e($codice) ?>">
    <h2><?= $s['icona'] ?> <?= $e($s['titolo']) ?> <span class="muted">· <?= (int) $s['n'] ?> <?= $e($s['unita']) ?> in questa sessione</span></h2>
    <div class="numeri">
      <div><b><?= count($s['righe']) ?></b><span>giocatori</span></div>
      <div><b><?= $s['righe'] ? (int) round(array_sum(array_column($s['righe'], 'punti')) / count($s['righe'])) : 0 ?></b><span>punti medi</span></div>
      <div><b><?= (int) $s['finito'] ?></b><span><?= $codice === 'binary_lights' ? 'punteggio pieno' : 'tutto finito' ?></span></div>
    </div>
    <h3><?= $codice === 'binary_lights' ? 'Quanti punti fanno' : 'Fin dove arrivano' ?></h3>
    <?php if (!$s['righe']): ?>
      <p class="muted">Nessuno ha ancora giocato.</p>
    <?php else: ?>
      <table>
        <tbody>
        <?php foreach ($s['barre'] as $b): ?>
          <tr>
            <td style="white-space:nowrap"><?= $e($b['etichetta']) ?></td>
            <td class="barra-cella"><div class="barra" style="width:<?= round($b['conta'] / $s['max'] * 100) ?>%"></div></td>
            <td class="num"><?= (int) $b['conta'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <h3>I migliori</h3>
      <table>
        <thead><tr><th>Nickname</th><th style="text-align:right"><?= $codice === 'binary_lights' ? 'Partite' : 'Salvataggi' ?></th><th style="text-align:right">Punti</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($s['righe'], 0, 8) as $r): ?>
          <tr<?= $r['nascosto'] ? ' class="muted" title="Nascosto dalla classifica"' : '' ?>>
            <td><span data-avatar="<?= $e($r['avatar']) ?>"></span> <?= $e($r['nickname']) ?><?php if ($r['nome']): ?> <span class="muted">· <?= $e($r['nome']) ?></span><?php endif; ?></td>
            <td class="num"><?= (int) $r['tentativi'] ?></td>
            <td class="num"><?= (int) $r['punti'] ?><span class="muted">/<?= (int) $s['tetto'] ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <p class="muted" style="margin:10px 0 0"><a href="<?= $e(GIOCHI[$codice]['pagina']) ?>" target="_blank">Apri il gioco ↗</a></p>
  </section>
<?php endforeach; ?>
<?php endif; ?>
<?php admin_fine(); ?>
