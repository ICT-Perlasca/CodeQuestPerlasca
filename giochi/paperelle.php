<?php
/**
 * Duck Coding: programmazione a blocchi (Blockly, copiato in locale)
 * per portare la paperella allo stagno. I livelli stanno in inc/duck.php,
 * i tempi li misura e li salva api/codegame.php.
 * Senza login si gioca in "modalita' prova": progressi solo nel browser.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/duck.php';

richiedi_gioco_attivo('paperelle_coding');

$livelli = [];
foreach (codegame_livelli_attivi() as $n => $l) { // quanti dipende dalla sessione (admin)
    unset($l['rif']); // il tempo di riferimento resta sul server
    $livelli[] = ['n' => $n] + $l;
}
$json = json_encode($livelli, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<title>Duck Coding · CodeQuestPerlasca</title>
<link rel="stylesheet" href="<?= asset('css/tema.css', '../') ?>">
<link rel="stylesheet" href="<?= asset('css/giochi/paperelle.css', '../') ?>">
</head>
<body>
<header class="top">
  <a href="../index.php">← Open Day</a>
  <h1>🦆 Duck <span>Coding</span></h1>
  <nav class="livelli" id="livelli" aria-label="Livelli"></nav>
  <div class="hud">
    <span class="pill" title="Tempo sul livello">⏱ <b id="hud-tempo">0:00</b></span>
    <span class="pill" id="hud-punti-box"><b id="hud-punti">0</b> pt</span>
  </div>
</header>

<main class="main" id="main">
  <section class="sx">
    <div class="card istruzioni">
      <h2><small id="liv-num">Livello 1</small><span id="liv-titolo"></span></h2>
      <p id="liv-testo"></p>
      <p class="obiettivo" id="liv-obiettivo"></p>
    </div>

    <div class="scena"><svg id="scena" viewBox="0 0 400 400" role="img" aria-label="Il prato con la paperella e lo stagno"></svg></div>

    <div class="comandi">
      <button class="btn esegui" id="btn-esegui" type="button">▶ Esegui</button>
      <button class="btn secondario" id="btn-reset" type="button">↺ Ricomincia</button>
      <label class="velocita">Velocità
        <select id="velocita">
          <option value="600">lenta</option>
          <option value="320" selected>normale</option>
          <option value="120">veloce</option>
        </select>
      </label>
    </div>

    <div class="esito" id="esito" role="status" hidden></div>

    <details class="card">
      <summary>&lt;/&gt; Guarda il codice JavaScript</summary>
      <p class="avviso" style="margin:8px 0">Ogni blocco è un'istruzione di un vero linguaggio di programmazione. Ecco il tuo programma scritto come lo scriverebbe un programmatore:</p>
      <pre class="codice" id="codice"></pre>
    </details>

    <p class="avviso" id="avviso-prova" hidden></p>
  </section>

  <section class="dx">
    <div class="barra-blocchi">
      <span>Trascina i blocchi dalla colonna a sinistra e agganciali sotto <b>quando premi ▶ Esegui</b></span>
      <span>Blocchi: <b id="n-blocchi">0</b> <span id="n-ottimali"></span></span>
    </div>
    <div id="blockly"></div>
  </section>
</main>

<dialog id="dlg-vinto">
  <h2 id="vinto-titolo">Livello superato! 🎉</h2>
  <p id="vinto-testo"></p>
  <div class="confronto" id="vinto-confronto"></div>
  <p id="vinto-punti"></p>
  <div class="dialog-azioni">
    <button class="btn secondario" type="button" id="btn-resta">Resta qui</button>
    <button class="btn primario" type="button" id="btn-avanti">Livello successivo ▶</button>
  </div>
</dialog>

<dialog id="dlg-fine">
  <h2>Missione compiuta! 🦆🦆🦆</h2>
  <p>Hai portato tutte le paperelle allo stagno. Ecco i tuoi tempi confrontati con la media di chi ha giocato:</p>
  <table class="riepilogo">
    <thead><tr><th>Livello</th><th style="text-align:right">Tu</th><th style="text-align:right">Media</th><th style="text-align:right">Punti</th></tr></thead>
    <tbody id="fine-righe"></tbody>
  </table>
  <div class="dialog-azioni">
    <button class="btn secondario" type="button" id="btn-chiudi-fine">Chiudi</button>
    <a class="btn primario" href="../index.php" style="text-align:center; text-decoration:none">Torna all'Open Day</a>
  </div>
</dialog>

<script src="../assets/js/vendor/blockly/blockly_compressed.js"></script>
<script src="../assets/js/vendor/blockly/msg/it.js"></script>
<script>const OD = { livelli: <?= $json ?>, sbloccaTutto: <?= sblocca_livelli() ? 'true' : 'false' ?> };</script>
<script src="<?= asset('js/comune/storage.js', '../') ?>"></script>
<script src="<?= asset('js/giochi/paperelle/pagina.js', '../') ?>"></script>
</body>
</html>
