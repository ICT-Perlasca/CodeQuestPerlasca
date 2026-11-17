<?php
/**
 * Modifica il gioco: una palla che rimbalza gia' programmata, con le variabili
 * (gravita', rimbalzo, velocita', grandezza, colori) modificabili direttamente
 * nel codice: l'effetto si vede subito. Missioni da completare, 100 punti l'una.
 *
 * Fisica e missioni in assets/js/giochi/modifica-gioco/motore.js. Il numero di missioni dipende
 * dalla sessione scelta in admin → Sessione (TIPI_GIORNATA in inc/giornate.php).
 * Senza login si gioca in "modalita' prova": progressi solo nel browser.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/bootstrap.php';

richiedi_gioco_attivo('modifica_gioco');

$missioni = sessione_attivita()['giochi']['palla_missioni'];
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<title>Modifica il gioco · CodeQuestPerlasca</title>
<link rel="stylesheet" href="<?= asset('css/tema.css', '../') ?>">
<link rel="stylesheet" href="<?= asset('css/giochi/modifica-gioco.css', '../') ?>">
</head>
<body>
<header class="top">
  <a href="../index.php">← <?= htmlspecialchars(sessione_attivita()['etichetta'], ENT_QUOTES) ?></a>
  <h1>🛠️ Modifica <span>il gioco</span></h1>
  <nav class="missioni" id="missioni" aria-label="Missioni"></nav>
  <span class="pill" id="pill-punti"><b id="hud-punti">0</b> pt</span>
</header>

<main class="main">
  <section class="col">
    <div class="card missione" aria-live="polite">
      <h2><small id="m-num">Missione 1</small><span id="m-titolo"></span></h2>
      <p id="m-testo"></p>
      <p class="esito" id="m-esito"></p>
    </div>
    <div class="campo"><canvas id="campo" width="800" height="600" aria-label="La palla che rimbalza"></canvas></div>
    <div class="comandi">
      <button class="btn lancia" id="btn-lancia" type="button">▶ Lancia</button>
      <button class="btn secondario" id="btn-reset" type="button" title="Rimette i valori iniziali">↺ Valori iniziali</button>
      <button class="btn primario" id="btn-avanti" type="button" hidden>Prossima missione ▶</button>
    </div>
    <p class="avviso" id="avviso" hidden></p>
  </section>

  <section class="col">
    <div class="codice" id="codice">
      <div class="titolo-file">📄 gioco.js — cambia i valori gialli: la palla cambia subito!</div>
    </div>
    <details class="codice">
      <summary>&lt;/&gt; Come si muove la palla (il motore del gioco)</summary>
      <pre class="motore"><span class="cm">// questa funzione gira 60 volte al secondo</span>
<span class="kw">function</span> <span class="fn">aggiorna</span>() {
  velocitaY = velocitaY + gravita;   <span class="cm">// la gravità tira giù</span>
  x = x + velocitaX;                 <span class="cm">// la palla si sposta</span>
  y = y + velocitaY;

  <span class="kw">if</span> (y + raggio &gt; pavimento) {      <span class="cm">// ha toccato terra?</span>
    y = pavimento - raggio;
    velocitaY = -velocitaY * rimbalzo; <span class="cm">// torna su (più o meno forte)</span>
  }
  <span class="cm">// ...e lo stesso per gli altri tre bordi</span>

  <span class="fn">disegnaCerchio</span>(x, y, raggio, colorePalla);
}</pre>
    </details>
  </section>
</main>

<div class="toast" id="toast" role="status"></div>

<script src="<?= asset('js/giochi/modifica-gioco/motore.js', '../') ?>"></script>
<script>const OD = { nMissioni: <?= (int) $missioni ?>, sbloccaTutto: <?= sblocca_livelli() ? 'true' : 'false' ?> };</script>
<script src="<?= asset('js/comune/storage.js', '../') ?>"></script>
<script src="<?= asset('js/giochi/modifica-gioco/pagina.js', '../') ?>"></script>
</body>
</html>
