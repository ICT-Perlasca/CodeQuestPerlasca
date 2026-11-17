<?php
/**
 * Hack the Web(site): il sito del Dottor Quack (che ha rapito le paperelle) va "aggirato"
 * modificandone HTML e CSS con un pannello Ispeziona simulato (assets/js/giochi/hack-website/ispeziona.js).
 * Il sito e' vero HTML dentro #sito: funziona anche il vero F12 del browser.
 *
 * Livelli in assets/js/giochi/hack-website/livelli.js, 100 punti l'uno. Quali livelli si giocano dipende
 * dalla sessione scelta in admin → Sessione (TIPI_GIORNATA in inc/giornate.php).
 * Livello segreto ★ senza punti: la parola e' nel commento in fondo a questa pagina.
 * Senza login si gioca in "modalita' prova": progressi solo nel browser.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/bootstrap.php';

richiedi_gioco_attivo('hack_website');

$livelli = sessione_attivita()['giochi']['hack_livelli'];
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<title>Hack the Web(site) · CodeQuestPerlasca</title>
<link rel="stylesheet" href="<?= asset('css/tema.css', '../') ?>">
<link rel="stylesheet" href="<?= asset('css/giochi/hack-website.css', '../') ?>">
<style id="sito-css"></style>
</head>
<body>
<header class="top ui">
  <a href="../index.php">← <?= htmlspecialchars(sessione_attivita()['etichetta'], ENT_QUOTES) ?></a>
  <h1>🕵️ Hack the <span>Web(site)</span></h1>
  <nav class="livelli" id="livelli" aria-label="Livelli"></nav>
  <span class="pill"><b id="hud-punti">0</b> pt</span>
</header>

<main class="main">
  <section class="col">
    <div class="card missione ui" aria-live="polite">
      <h2><small id="l-num">Livello 1</small><span id="l-titolo"></span></h2>
      <p id="l-testo"></p>
      <ul class="lista" id="l-lista" hidden></ul>
      <ol class="aiuti" id="l-aiuti" hidden></ol>
      <p class="esito" id="l-esito"></p>
      <div class="comandi">
        <button class="btn secondario" id="btn-aiuto" type="button">💡 Aiuto</button>
        <button class="btn primario" id="btn-avanti" type="button" hidden>Prossimo livello ▶</button>
      </div>
    </div>

    <div class="browser">
      <div class="barra-url ui">
        <span class="pallini" aria-hidden="true"><i></i><i></i><i></i></span>
        <button class="ricarica" id="btn-ricarica" type="button" title="Ricarica la pagina: torna tutto come prima" aria-label="Ricarica la pagina">↻</button>
        <span class="url">🔒 <span id="url"></span></span>
      </div>
      <div class="msg-sito" id="msg-sito" hidden></div>
      <div class="cornice" id="cornice">
        <div id="sito"></div>
      </div>
    </div>
    <p class="f12">💻 Al computer funziona anche il vero Ispeziona del browser: <kbd>F12</kbd> oppure tasto destro → <i>Ispeziona</i>.</p>
    <p class="avviso" id="avviso" hidden></p>
  </section>

  <section class="col">
    <div id="ispeziona" aria-label="Pannello Ispeziona"></div>
  </section>
</main>

<div class="toast ui" id="toast" role="status"></div>

<script src="<?= asset('js/giochi/hack-website/ispeziona.js', '../') ?>"></script>
<script src="<?= asset('js/giochi/hack-website/livelli.js', '../') ?>"></script>
<script>const OD = { numeri: <?= json_encode(array_values($livelli)) ?>, sbloccaTutto: <?= sblocca_livelli() ? 'true' : 'false' ?> };</script>
<script src="<?= asset('js/comune/storage.js', '../') ?>"></script>
<script src="<?= asset('js/giochi/hack-website/pagina.js', '../') ?>"></script>
<!--
  🕵️ Complimenti, hai trovato il commento nascosto: stai usando gli strumenti veri del browser!
  La parola segreta del livello ★ è: PAPERHACKER
-->
</body>
</html>
