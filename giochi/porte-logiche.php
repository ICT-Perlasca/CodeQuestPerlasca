<?php
/**
 * Porte logiche: interruttori (bit) collegati a porte AND / OR / NOT fino a una
 * lampadina da accendere. I primi livelli non spiegano niente: le porte sono "scatole
 * misteriose" che si scoprono giocando. Collega il binario al "ragionamento" del computer:
 * 1 = corrente, 0 = niente corrente, e con le porte si arriva a fare le somme.
 *
 * Livelli e logica in assets/js/giochi/porte-logiche/motore.js. Il numero di livelli dipende
 * dalla sessione scelta in admin → Sessione (TIPI_GIORNATA in inc/giornate.php). 100 punti a livello.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/bootstrap.php';

richiedi_gioco_attivo('porte_logiche');

$livelli = sessione_attivita()['giochi']['porte_livelli']; // numeri dei livelli (da 1) di questa sessione
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<title>Porte logiche · CodeQuestPerlasca</title>
<link rel="stylesheet" href="<?= asset('css/tema.css', '../') ?>">
<link rel="stylesheet" href="<?= asset('css/giochi/porte-logiche.css', '../') ?>">
</head>
<body>
<header class="top">
  <a href="../index.php">← <?= htmlspecialchars(sessione_attivita()['etichetta'], ENT_QUOTES) ?></a>
  <h1>💡 Porte <span>logiche</span></h1>
  <nav class="livelli" id="livelli" aria-label="Livelli"></nav>
  <button type="button" class="btn-guida" id="btn-guida" aria-label="Guida: come si gioca" title="Come si gioca">?</button>
  <span class="pill"><b id="hud-punti">0</b> pt</span>
</header>

<main class="main">
  <section class="col">
    <div class="card" aria-live="polite">
      <h2><small id="l-num">Livello 1</small><span id="l-titolo"></span></h2>
      <p id="l-testo"></p>
      <p class="esito" id="l-esito"></p>
    </div>
    <div class="circuito"><svg id="svg" role="group" aria-label="Il circuito: tocca gli interruttori"></svg></div>
    <div class="card">
      <h3>Così ragiona un computer</h3>
      <div class="binario">
        <span class="nomi" id="bin-nomi"></span>
        <span class="bits" id="bin-bits"></span>
        <span id="bin-dec"></span>
      </div>
      <p class="nota">Ogni interruttore è un <b>bit</b>: 1 = passa corrente, 0 = niente corrente. Il computer non "capisce" vero o falso:
        fa scorrere bit dentro miliardi di porte come queste, ed è così che confronta, decide e fa i conti.</p>
    </div>
    <div class="comandi">
      <button class="btn secondario" id="btn-spegni" type="button">Spegni tutto</button>
      <button class="btn primario" id="btn-avanti" type="button" hidden>Livello successivo ▶</button>
    </div>
    <p class="avviso" id="avviso" hidden></p>
  </section>

  <section class="col">
    <div class="card" id="sfida"></div>
    <div class="card">
      <h3>Le regole delle porte</h3>
      <div class="regole" id="regole"></div>
    </div>
  </section>
</main>

<div class="toast" id="toast" role="status"></div>

<script src="<?= asset('js/giochi/porte-logiche/motore.js', '../') ?>"></script>
<script src="<?= asset('js/comune/guida.js', '../') ?>"></script>
<script>const OD = { numeri: <?= json_encode(array_values(array_map('intval', $livelli))) ?>, sbloccaTutto: <?= sblocca_livelli() ? 'true' : 'false' ?> };</script>
<script src="<?= asset('js/comune/storage.js', '../') ?>"></script>
<script src="<?= asset('js/giochi/porte-logiche/pagina.js', '../') ?>"></script>
</body>
</html>
