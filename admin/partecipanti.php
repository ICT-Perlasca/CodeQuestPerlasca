<?php
/** Admin · Partecipanti: ricerca, nascondi/elimina/banna e blacklist dei nickname. */
declare(strict_types=1);

require_once __DIR__ . '/_base.php';

$pdo = db();
    $q = trim((string) ($_GET['q'] ?? ''));
    $ord = ($_GET['ord'] ?? '') === 'punti' ? 'punti' : 'recenti';
    $sql = 'SELECT p.id, p.nickname, p.avatar, p.punti_totali, p.nascosto, p.created_at, r.nome
              FROM partecipanti p LEFT JOIN registro r ON r.uuid = p.uuid'
         . ' WHERE p.sessione_id = ?' . ($q !== '' ? ' AND (p.nickname LIKE ? OR r.nome LIKE ?)' : '')
         . ($ord === 'punti' ? ' ORDER BY p.punti_totali DESC, p.updated_at ASC' : ' ORDER BY p.created_at DESC, p.id DESC')
         . ' LIMIT 200';
    $st = $pdo->prepare($sql);
    $cerca = '%' . addcslashes($q, '%_\\') . '%';
    $st->execute($q !== '' ? [$sidAdmin, $cerca, $cerca] : [$sidAdmin]);
    $partecipanti = $st->fetchAll();
$blacklist = $pdo->query('SELECT id, parola FROM blacklist ORDER BY parola')->fetchAll();

admin_inizio('partecipanti.php', 'Partecipanti');
?>
<section class="card">
  <h2>Elenco</h2>
  <form method="get" class="riga-form" style="margin-bottom:12px">
    <input type="search" name="q" value="<?= $e($q) ?>" placeholder="Cerca nickname o nome" style="flex:1">
    <input type="hidden" name="ord" value="<?= $e($ord) ?>">
    <button type="submit">Cerca</button>
    <a class="btn" href="?<?= $e(http_build_query(['q' => $q, 'ord' => $ord === 'punti' ? 'recenti' : 'punti'])) ?>">
      Ordina per <?= $ord === 'punti' ? 'più recenti' : 'punti' ?>
    </a>
    <a class="btn" href="?<?= $e(http_build_query(['q' => $q, 'ord' => $ord])) ?>">↻ Aggiorna</a>
  </form>

  <?php if (!$partecipanti): ?>
    <p class="muted">Nessun partecipante<?= $q !== '' ? ' trovato' : ' ancora' ?>.</p>
  <?php endif; ?>
  <ul class="lista">
    <?php foreach ($partecipanti as $p): ?>
      <li class="partecipante <?= $p['nascosto'] ? 'nascosto' : '' ?>">
        <span class="av" data-avatar="<?= $e($p['avatar']) ?>"></span>
        <div class="info">
          <b><?= $e($p['nickname']) ?><?php if ($p['nome'] !== null): ?> <span class="nome">· <?= $e($p['nome']) ?></span><?php endif; ?><?= $p['nascosto'] ? '<span class="tag">nascosto</span>' : '' ?></b>
          <span>entrato alle <?= $e(date('H:i', strtotime($p['created_at']))) ?></span>
        </div>
        <span class="pt"><?= (int) $p['punti_totali'] ?></span>
        <div class="azioni">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="nascosto" value="<?= $p['nascosto'] ? '0' : '1' ?>">
            <button type="submit" name="azione" value="visibilita"><?= $p['nascosto'] ? 'Mostra' : 'Nascondi' ?></button>
          </form>
          <form method="post" data-conferma="Eliminare «<?= $e($p['nickname']) ?>»?">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="pericolo" type="submit" name="azione" value="elimina" title="Elimina il partecipante e i suoi punti">Elimina</button>
            <button class="pericolo pieno" type="submit" name="azione" value="banna" title="Elimina e aggiunge il nickname alla blacklist">Banna</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="card">
  <h2>Blacklist nickname <span class="muted">(<?= count($blacklist) ?>)</span></h2>
  <form method="post" class="riga-form">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="azione" value="blacklist_aggiungi">
    <input type="text" name="parola" placeholder="Nuova parola" maxlength="40" required style="flex:1">
    <button type="submit">Aggiungi</button>
  </form>
  <p class="muted" style="margin:8px 0 0">Blocca ogni nickname che <em>contiene</em> la parola (anche scritta in leet, es. "c4zz0").</p>
  <div class="chips">
    <?php foreach ($blacklist as $b): ?>
      <form method="post" class="chip">
        <?= $e($b['parola']) ?>
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="azione" value="blacklist_rimuovi">
        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
        <button type="submit" aria-label="Rimuovi <?= $e($b['parola']) ?>">×</button>
      </form>
    <?php endforeach; ?>
  </div>
</section>
<?php admin_fine(); ?>
