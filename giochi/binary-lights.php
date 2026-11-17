<?php
/**
 * Binary Lights: comporre numeri accendendo gli 8 bit di un byte.
 * Il numero di round dipende dalla sessione scelta in admin → Sessione (TIPI_GIORNATA in inc/giornate.php):
 * Open Day 5 round (~2 minuti), laboratorio pomeridiano 12 round con difficolta' piu' graduale.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/bootstrap.php';

richiedi_gioco_attivo('binary_lights');

$round = sessione_attivita()['giochi']['binary_round'];
// intervallo del numero bersaglio per ogni round: si parte dai 4 bit bassi
$intervalli = $round <= 5
    ? [[1, 15], [16, 63], [64, 127], [128, 255], [128, 255]]
    : [[1, 15], [8, 15], [16, 31], [16, 63], [32, 63], [64, 127], [64, 127], [96, 127], [128, 191], [128, 255], [192, 255], [128, 255]];
$intervalli = array_slice($intervalli, 0, $round);
while (count($intervalli) < $round) {
    $intervalli[] = [128, 255]; // se in TIPI_GIORNATA si mettono piu' round: i successivi sono a 8 bit pieni
}
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<title>Binary Lights · CodeQuestPerlasca</title>
<link rel="stylesheet" href="<?= asset('css/tema.css', '../') ?>">
<link rel="stylesheet" href="<?= asset('css/giochi/binary-lights.css', '../') ?>">
</head>
<body>
<main class="app">
  <header class="top">
    <a href="../index.php" aria-label="Torna alla mappa">&larr; Mappa</a>
    <h1>Binary <span>Lights</span></h1>
    <div class="hud" aria-live="polite">
      <button type="button" class="btn-guida" id="btn-guida" aria-label="Guida: come si gioca" title="Come si gioca">?</button>
      <span class="pill">R <b id="hud-round">0</b>/<?= $round ?></span>
      <span class="pill"><b id="hud-punti">0</b> pt</span>
    </div>
  </header>

  <!-- INTRO -->
  <section id="intro" class="card">
    <h2>1 byte = 8 lampadine</h2>
    <p>Il computer conosce solo acceso (1) e spento (0). Ogni lampadina vale il doppio di quella alla sua destra.</p>
    <div class="esempio" aria-label="Esempio: 00101010 vale 42">
      <span>128 64 <span class="uno">32</span> 16 <span class="uno">8</span> 4 <span class="uno">2</span> 1</span>
      <span>&nbsp;0   0  <span class="uno">1</span>  0 <span class="uno">1</span> 0 <span class="uno">1</span> 0</span>
      <span>→ 32 + 8 + 2 = <span class="uno">42</span></span>
    </div>
    <ol>
      <li>Leggi il numero da comporre.</li>
      <li>Accendi le lampadine giuste prima che scada il tempo.</li>
      <li>Più sei veloce, più punti fai. <?= $round ?> round, difficoltà crescente!</li>
    </ol>
    <button class="btn" id="btn-start" type="button">Inizia</button>
  </section>

  <!-- GIOCO -->
  <section id="gioco" hidden>
    <div style="display:grid; gap:14px">
      <div class="target">
        <span class="lbl">Componi il numero</span>
        <strong id="target" aria-live="assertive">0</strong>
        <div class="timer" id="timer"><div class="fill" id="timer-fill"></div></div>
        <span class="secondi" id="secondi">20 s</span>
      </div>

      <div class="bulbs" id="bulbs" role="group" aria-label="Gli 8 bit del byte"></div>

      <div class="readout">
        <code id="bin">0000 0000</code>
        <span class="expr" id="expr">= 0</span>
      </div>

      <div class="comandi">
        <button class="btn secondario" id="btn-azzera" type="button">Spegni tutto</button>
      </div>
    </div>
  </section>

  <!-- FINE -->
  <section id="fine" class="card" hidden>
    <h2 id="fine-titolo">Partita finita!</h2>
    <div class="punteggio-finale"><span id="fine-punti">0</span> <small style="font-size:1.2rem">pt</small></div>
    <div class="stat">
      <div><b id="fine-indovinati">0/<?= $round ?></b><span>indovinati</span></div>
      <div><b id="fine-veloce">–</b><span>più veloce</span></div>
      <div><b id="fine-record">0</b><span>tuo record</span></div>
    </div>
    <p class="salvataggio" id="salvataggio" aria-live="polite"></p>
    <p>Lo sapevi? Con 8 bit si contano 256 valori (da 0 a 255): è il motivo per cui ogni colore RGB e ogni ottetto di un indirizzo IP va da 0 a 255.</p>
    <div class="azioni">
      <button class="btn" id="btn-ancora" type="button">Gioca ancora</button>
      <a class="btn secondario" href="../index.php" style="text-align:center; text-decoration:none">Prossima tappa →</a>
    </div>
  </section>
</main>

<div class="toast" id="toast" role="status"></div>

<script src="<?= asset('js/comune/guida.js', '../') ?>"></script>
<script>const OD = { round: <?= (int) $round ?>, livelli: <?= json_encode($intervalli) ?> };</script>
<script src="<?= asset('js/comune/storage.js', '../') ?>"></script>
<script src="<?= asset('js/giochi/binary-lights/pagina.js', '../') ?>"></script>
</body>
</html>
