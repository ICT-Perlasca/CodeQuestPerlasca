<?php
/**
 * Dashboard Open Day: onboarding (nickname + avatar) e mappa delle attivita'.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

avvia_sessione();
if (!ospite_collegato()) {
    header('Location: login.php');
    exit;
}

$profilo = null;
if (isset($_SESSION['uuid'])) {
    $st = db()->prepare('SELECT id, uuid, nickname, avatar, punti_totali, nascosto, updated_at FROM partecipanti WHERE sessione_id = ? AND uuid = ?');
    $st->execute([sessione_id_corrente(), $_SESSION['uuid']]);
    $profilo = $st->fetch() ?: null;
    if ($profilo === null) {
        unset($_SESSION['uuid']); // rimosso dall'admin: rifa' l'onboarding
    }
}

$giochi = sessione_attivita()['giochi']; // Open Day corto, laboratorio lungo (admin)
$percorso = [
  ['codice' => 'paperelle_coding', 'icona' => '🦆', 'colore' => '#ffd84d', 'titolo' => 'Duck Coding',       'desc' => 'Programma a blocchi e porta le paperelle allo stagno', 'href' => 'giochi/paperelle.php'],
  ['codice' => 'binary_lights',    'icona' => '🔢', 'colore' => '#5cc8ff', 'titolo' => 'Binary Lights',     'desc' => 'Componi numeri con 8 bit',                           'href' => 'giochi/binary-lights.php'],
  ['codice' => 'porte_logiche',    'icona' => '💡', 'colore' => '#b48cff', 'titolo' => 'Porte logiche',     'desc' => 'AND, OR, NOT: accendi la lampadina',                 'href' => 'giochi/porte-logiche.php'],
  ['codice' => 'modifica_gioco',   'icona' => '🛠️', 'colore' => '#3ddc97', 'titolo' => 'Modifica il gioco', 'desc' => 'Cambia gravità e colori nel codice',                'href' => 'giochi/modifica-gioco.php'],
  ['codice' => 'hack_website',     'icona' => '🕵️', 'colore' => '#ff5c7a', 'titolo' => 'Hack the Web(site)', 'desc' => 'Usa "Ispeziona" come un hacker per liberare le paperelle', 'href' => 'giochi/hack-website.php'],
];

// progressi del ragazzo e posizione in classifica (solo se ha gia' un profilo)
$fatti = [];
$posizione = null;
if ($profilo) {
    $pdo = db();
    $st = $pdo->prepare('SELECT modulo, punti FROM punteggi WHERE partecipante_id = ?');
    $st->execute([$profilo['id']]);
    $puntiGioco = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    $st = $pdo->prepare('SELECT COUNT(*) FROM codegame_tempi WHERE partecipante_id = ? AND ms IS NOT NULL');
    $st->execute([$profilo['id']]);
    $duckFatti = (int) $st->fetchColumn();

    if ((int) $profilo['punti_totali'] > 0 && !(int) $profilo['nascosto']) {
        $st = $pdo->prepare(
            'SELECT COUNT(*) + 1 FROM partecipanti WHERE sessione_id = ? AND nascosto = 0 AND punti_totali > 0 AND (punti_totali > ?
               OR (punti_totali = ? AND (updated_at < ? OR (updated_at = ? AND id < ?))))'
        );
        $st->execute([sessione_id_corrente(), $profilo['punti_totali'], $profilo['punti_totali'], $profilo['updated_at'], $profilo['updated_at'], $profilo['id']]);
        $posizione = (int) $st->fetchColumn();
    }
}

// filtra i giochi nascosti, segna quelli disabilitati, calcola totale e fatti di ognuno
$percorso = array_values(array_filter($percorso, static fn(array $t): bool => stato_gioco($t['codice']) !== 'nascosto'));
$completate = 0;
foreach ($percorso as &$t) {
    $t['totale'] = livelli_sessione($t['codice']);
    $t['unita'] = ['binary_lights' => 'round', 'modifica_gioco' => 'missioni'][$t['codice']] ?? 'livelli';
    $t['chiuso'] = stato_gioco($t['codice']) === 'disabilitato';
    $t['fatti'] = match ($t['codice']) {
        'paperelle_coding' => min($t['totale'], $duckFatti ?? 0),
        'binary_lights'    => ($puntiGioco[$t['codice']] ?? 0) > 0 ? $t['totale'] : 0, // i punti dei round non sono tutti da 300: conta "giocato"
        default            => min($t['totale'], intdiv((int) ($puntiGioco[$t['codice']] ?? 0), GIOCHI[$t['codice']]['per'])),
    };
    $t['completo'] = $t['totale'] > 0 && $t['fatti'] >= $t['totale'];
    $t['iniziato'] = $t['fatti'] > 0;
    $completate += $t['completo'] ? 1 : 0;
}
unset($t);
$attive = count(array_filter($percorso, static fn(array $t): bool => !$t['chiuso']));
$percento = $attive > 0 ? (int) round(100 * $completate / $attive) : 0;
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<title>CodeQuestPerlasca · Informatica</title>
<link rel="stylesheet" href="<?= asset('css/tema.css', '') ?>">
<link rel="stylesheet" href="<?= asset('css/pagine/home.css', '') ?>">
</head>
<body>
<main class="app">
  <header id="intestazione" <?= $profilo ? 'hidden' : '' ?>>
    <h1><?= $e(sessione_attivita()['etichetta']) ?> · Informatica e Telecomunicazioni</h1>
    <p>IIS "Giacomo Perlasca"</p>
  </header>

  <!-- ONBOARDING -->
  <form id="onboarding" class="card" <?= $profilo ? 'hidden' : '' ?> autocomplete="off">
    <h2>Come ti chiami… in codice?</h2>
    <p>Scegli un nickname. <strong>Non usare il tuo nome e cognome veri</strong>: qui sei un hacker in incognito!</p>
    <div class="avatar-row">
      <button class="avatar avatar-btn" type="button" id="avatar-preview" aria-expanded="false" aria-controls="avatar-griglia" aria-label="Scegli il tuo avatar"></button>
      <div class="testo"><b>Il tuo avatar</b><p>Tocca l'icona per sceglierlo</p></div>
      <button class="btn secondario" type="button" id="btn-avatar" aria-label="Avatar a caso">🎲</button>
    </div>
    <div class="avatar-griglia" id="avatar-griglia" role="radiogroup" aria-label="Avatar" hidden></div>
    <label>Nickname (3-16 caratteri, niente spazi)
      <input id="nickname" maxlength="16" autocapitalize="none" spellcheck="false" required pattern="[\p{L}\p{N}_\-]{3,16}">
    </label>
    <p class="errore" id="errore" role="alert"></p>
    <button class="btn" type="submit" id="btn-entra">Iniziamo!</button>
  </form>

  <!-- DASHBOARD -->
  <section id="dashboard" <?= $profilo ? '' : 'hidden' ?> style="display:grid; gap:16px">
    <div class="hero">
      <div class="kicker"><?= $e(sessione_attivita()['etichetta']) ?> · IIS Perlasca</div>
      <div class="profilo">
        <button class="avatar avatar-btn" type="button" id="dash-avatar" aria-expanded="false" aria-controls="dash-griglia" aria-label="Cambia avatar"></button>
        <div><b id="dash-nick"><?= $e($profilo['nickname'] ?? '') ?></b><p>Junior Hacker in addestramento</p></div>
        <div class="pt"><strong id="dash-punti"><?= (int) ($profilo['punti_totali'] ?? 0) ?></strong><span>punti</span></div>
      </div>
      <div class="avatar-griglia" id="dash-griglia" role="radiogroup" aria-label="Avatar" hidden></div>
      <p class="errore" id="dash-errore" role="alert" hidden></p>
      <?php if ($attive > 0): ?>
        <div class="avanzamento">
          <div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percento ?>"><i style="width:<?= $percento ?>%"></i></div>
          <p><?= $completate ?> sfid<?= $completate === 1 ? 'a' : 'e' ?> su <?= $attive ?> completat<?= $completate === 1 ? 'a' : 'e' ?><?= $posizione ? ' · 🏆 sei ' . $posizione . '° in classifica' : '' ?></p>
        </div>
      <?php endif; ?>
    </div>

    <div class="titolo-sfide"><h2>Scegli la tua sfida</h2><button type="button" class="btn-aiuto" id="btn-intro">❓ Come funziona</button></div>
    <ul class="griglia-giochi">
      <?php foreach ($percorso as $t):
        $tag = $t['chiuso'] ? 'div' : 'a';
        $attr = $t['chiuso'] ? 'aria-disabled="true"' : 'href="' . $e($t['href']) . '"'; ?>
        <li>
          <<?= $tag ?> class="gioco<?= $t['chiuso'] ? ' off' : '' ?>" style="--c:<?= $e($t['colore']) ?>" <?= $attr ?>>
            <span class="ico"><?= $t['icona'] ?></span>
            <b><?= $e($t['titolo']) ?></b>
            <small><?= $e($t['desc']) ?></small>
            <span class="stato">
              <span><?= (int) $t['totale'] ?> <?= $e($t['unita']) ?></span>
              <?php if ($t['chiuso']): ?><span class="chip">chiuso</span>
              <?php elseif ($t['codice'] === 'binary_lights' && $t['iniziato']): ?><span class="chip ok">✓ giocato</span>
              <?php elseif ($t['completo']): ?><span class="chip ok">✓ <?= (int) $t['fatti'] ?>/<?= (int) $t['totale'] ?></span>
              <?php elseif ($t['iniziato'] && $t['codice'] !== 'binary_lights'): ?><span class="chip corso"><?= (int) $t['fatti'] ?>/<?= (int) $t['totale'] ?></span>
              <?php else: ?><span class="chip">da fare</span><?php endif; ?>
            </span>
          </<?= $tag ?>>
        </li>
      <?php endforeach; ?>
    </ul>

    <a class="diploma" href="diploma.php">
      <span class="i">🎓</span>
      <span>Il tuo diploma &amp; la classifica<small>Scarica l'attestato Junior Hacker</small></span>
    </a>
  </section>
  <form method="post" action="logout.php" style="text-align:center;margin:8px 0 24px" onsubmit="return confirm('Vuoi uscire? Per rientrare servono utente e password della sessione.')">
    <button class="btn secondario" type="submit">🚪 Esci</button>
  </form>
</main>

<!-- INTRODUZIONE: due slide che spiegano come funzionano le attivita' -->
<div class="intro" id="intro" role="dialog" aria-modal="true" aria-label="Come funziona" hidden>
  <div class="intro-box">
    <section class="slide on">
      <div class="grande">🕹️</div>
      <h2>Benvenuto, Junior Hacker!</h2>
      <p>Qui trovi delle sfide a tema informatica. Si gioca dal telefono, da solo o con un amico, <b>nell'ordine che preferisci</b>.</p>
      <ul class="passi">
        <li><span class="n">1</span><span><b>Scegli una sfida</b><span class="t">Tocca una card nella pagina iniziale.</span></span></li>
        <li><span class="n">2</span><span><b>Supera i livelli</b><span class="t">Ogni livello superato ti dà dei punti. Se sbagli, riprova: nessuno ti toglie niente.</span></span></li>
        <li><span class="n">3</span><span><b>Sali in classifica</b><span class="t">Compare solo il tuo nickname: il nome vero lo scrivi soltanto per il diploma.</span></span></li>
      </ul>
    </section>
    <section class="slide">
      <div class="grande">🎯</div>
      <h2>Le sfide</h2>
      <ul class="passi">
        <li><span class="n">🦆</span><span><b>Duck Coding</b><span class="t">Trascina i blocchi per far arrivare la paperella allo stagno.</span></span></li>
        <li><span class="n">🔢</span><span><b>Binary Lights</b><span class="t">Accendi gli 8 bit per comporre il numero richiesto.</span></span></li>
        <li><span class="n">💡</span><span><b>Porte logiche</b><span class="t">Collega interruttori e porte per accendere la lampadina.</span></span></li>
        <li><span class="n">🛠️</span><span><b>Modifica il gioco</b><span class="t">Cambia i numeri nel codice e guarda la palla reagire.</span></span></li>
        <li><span class="n">🕵️</span><span><b>Hack the Web(site)</b><span class="t">Usa "Ispeziona" come un hacker per liberare le paperelle.</span></span></li>
      </ul>
      <p>Alla fine scarichi il tuo <b>diploma</b> dalla pagina iniziale. 🎓</p>
    </section>
    <div class="intro-nav">
      <button type="button" class="intro-salta" id="intro-salta">Salta</button>
      <div class="puntini" aria-hidden="true"><i class="on"></i><i></i></div>
      <button type="button" class="btn" id="intro-avanti">Avanti</button>
    </div>
  </div>
</div>

<script src="<?= asset('js/comune/avatar.js') ?>"></script>
<script>const OD = { profilo: <?= json_encode($profilo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>, sessioneId: <?= (int) sessione_id_corrente() ?> };</script>
<script src="<?= asset('js/comune/storage.js', '') ?>"></script>
<script src="<?= asset('js/pagine/home.js', '') ?>"></script>
</body>
</html>
