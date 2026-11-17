<?php
/**
 * Classifica live a tutto schermo per proiettore/LIM.
 * Pubblica e in sola lettura: interroga api/get_leaderboard.php ogni 4 secondi.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

// ?s=<id> sceglie la sessione (con piu' sessioni attive: una classifica per ogni proiettore)
$sid = sessione_pubblica();
$sessione = sessione_attivita();

$cartella = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
$urlGioco = ($_SERVER['HTTP_HOST'] ?? 'localhost') . $cartella;
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0b1020">
<title>Classifica Live · CodeQuestPerlasca</title>
<link rel="stylesheet" href="<?= asset('css/tema.css', '') ?>">
<link rel="stylesheet" href="<?= asset('css/pagine/classifica.css', '') ?>">
</head>
<body>
<div class="schermo">
  <header>
    <div>
      <h1>🏆 Classifica <span>Junior Hacker</span></h1>
      <p><?= $e($sessione['etichetta']) ?> Informatica e Telecomunicazioni · IIS "Giacomo Perlasca"<?= $sessione['demo'] ? ' · <b>DEMO</b>' : '' ?></p>
    </div>
    <div class="live">
      <span class="badge" id="stato"><span class="dot"></span><span id="stato-testo">LIVE</span></span>
      <span class="badge"><b id="totale">0</b> giocatori</span>
      <button id="btn-fs" type="button">Schermo intero (F)</button>
    </div>
  </header>

  <main>
    <section class="colonna">
      <h2>Top 10</h2>
      <ol id="top"></ol>
      <p class="vuoto" id="vuoto" hidden>Ancora nessun punteggio… chi sarà il primo hacker?</p>
    </section>

    <aside class="colonna">
      <div class="box">
        <h2>Ultimi arrivati</h2>
        <ul id="ultimi"></ul>
      </div>
      <div class="box unisciti">
        <p>Gioca anche tu! Apri</p>
        <span class="url"><?= $e($urlGioco) ?></span>
        <p>utente</p>
        <span class="pwd"><?= $e(ospite_utente()) ?></span>
        <p>password</p>
        <span class="pwd"><?= $e(ospite_pass()) ?></span>
      </div>
    </aside>
  </main>
</div>

<div class="banner" id="banner" role="status"></div>

<script src="<?= asset('js/comune/avatar.js') ?>"></script>
<script>const OD = { sessioneId: <?= (int) $sid ?> };</script>
<script src="<?= asset('js/pagine/classifica.js', '') ?>"></script>
</body>
</html>
