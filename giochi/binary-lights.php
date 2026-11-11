<?php
/**
 * Binary Lights: comporre numeri accendendo gli 8 bit di un byte.
 * Il numero di round dipende dalla sessione scelta in admin.php (TIPI_GIORNATA in inc/db.php):
 * Open Day 5 round (~2 minuti), laboratorio pomeridiano 12 round con difficolta' piu' graduale.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/db.php';

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
<title>Binary Lights · Open Day Perlasca</title>
<style>
  :root {
    --bg: #0b1020;
    --panel: #141b33;
    --panel-2: #1c2547;
    --line: #2a3566;
    --text: #e8ecff;
    --muted: #8e98c7;
    --on: #ffd84d;
    --on-glow: rgba(255, 216, 77, .55);
    --off: #262e52;
    --ok: #3ddc97;
    --ko: #ff5c7a;
    --accent: #5cc8ff;
    --radius: 16px;
    --mono: ui-monospace, "SF Mono", "Cascadia Mono", "Roboto Mono", Menlo, Consolas, monospace;
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; }
  body {
    min-height: 100dvh;
    background: radial-gradient(120% 60% at 50% 0%, #17204a 0%, var(--bg) 60%) fixed, var(--bg);
    color: var(--text);
    font: 16px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    -webkit-tap-highlight-color: transparent;
    -webkit-user-select: none;
    user-select: none;
  }
  button { font: inherit; color: inherit; touch-action: manipulation; cursor: pointer; }
  [hidden] { display: none !important; }

  .app {
    max-width: 760px;
    margin: 0 auto;
    padding: max(14px, env(safe-area-inset-top)) 16px max(18px, env(safe-area-inset-bottom));
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  /* ---------- barra superiore ---------- */
  .top { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
  .top a { color: var(--muted); text-decoration: none; font-size: .95rem; padding: 6px 0; }
  .top h1 { margin: 0; font-size: 1.1rem; letter-spacing: .02em; }
  .top h1 span { color: var(--on); }
  .hud { display: flex; gap: 8px; }
  .pill {
    background: var(--panel); border: 1px solid var(--line); border-radius: 999px;
    padding: 4px 12px; font-family: var(--mono); font-size: .9rem; white-space: nowrap;
  }
  .pill b { color: var(--on); }

  /* ---------- schede (intro / fine) ---------- */
  .card {
    background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius);
    padding: 20px; display: grid; gap: 14px;
  }
  .card h2 { margin: 0; font-size: 1.5rem; }
  .card p { margin: 0; color: var(--muted); }
  .card ol { margin: 0; padding-left: 1.2em; display: grid; gap: 6px; }
  .esempio {
    font-family: var(--mono); background: var(--panel-2); border-radius: 12px; padding: 12px;
    display: grid; gap: 4px; font-size: .95rem;
  }
  .esempio .uno { color: var(--on); font-weight: 700; }
  .btn {
    border: 0; border-radius: 14px; padding: 16px; font-weight: 800; font-size: 1.15rem;
    background: var(--accent); color: #04121c; transition: transform .08s;
  }
  .btn:active { transform: scale(.97); }
  .btn.secondario { background: var(--panel-2); color: var(--text); border: 1px solid var(--line); font-weight: 600; }
  .btn:focus-visible, .bulb:focus-visible { outline: 3px solid var(--accent); outline-offset: 3px; }
  .azioni { display: grid; gap: 10px; }

  /* ---------- obiettivo + timer ---------- */
  .target {
    background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius);
    padding: 14px 16px 16px; display: grid; gap: 10px; text-align: center;
  }
  .target .lbl { color: var(--muted); font-size: .9rem; text-transform: uppercase; letter-spacing: .08em; }
  .target strong {
    font-family: var(--mono); font-size: clamp(3.2rem, 18vw, 5rem); line-height: 1; color: var(--text);
  }
  .timer { position: relative; height: 10px; border-radius: 999px; background: var(--panel-2); overflow: hidden; }
  .timer .fill {
    position: absolute; inset: 0; background: var(--accent); transform-origin: left center;
    border-radius: inherit; will-change: transform;
  }
  .timer.urgente .fill { background: var(--ko); }
  .secondi { font-family: var(--mono); font-size: .9rem; color: var(--muted); }
  .timer.urgente + .secondi { color: var(--ko); font-weight: 700; }

  /* ---------- lampadine ---------- */
  .bulbs {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
  }
  .bulb {
    position: relative;
    display: grid; justify-items: center; gap: 6px;
    padding: 10px 4px 10px;
    background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius);
    transition: border-color .15s, background-color .15s, transform .08s;
    min-height: 120px;
  }
  .bulb:active { transform: scale(.95); }
  .bulb .bit { font-family: var(--mono); font-size: 1.25rem; font-weight: 800; color: var(--muted); }
  .bulb .lamp {
    width: clamp(42px, 13vw, 58px); aspect-ratio: 1; border-radius: 50%;
    background: var(--off);
    box-shadow: inset 0 -6px 0 rgba(0,0,0,.25);
    transition: background .15s, box-shadow .2s;
    position: relative;
  }
  .bulb .lamp::after { /* attacco della lampadina */
    content: ""; position: absolute; left: 30%; right: 30%; bottom: -8px; height: 9px;
    background: #4a5486; border-radius: 0 0 4px 4px;
  }
  .bulb .peso { font-family: var(--mono); font-size: .95rem; color: var(--muted); margin-top: 4px; }

  .bulb.on { border-color: var(--on); background: #221f2a; }
  .bulb.on .bit { color: var(--on); }
  .bulb.on .peso { color: var(--text); font-weight: 700; }
  .bulb.on .lamp {
    background: radial-gradient(circle at 35% 30%, #fffbe6 0%, var(--on) 45%, #e0a800 100%);
    box-shadow: 0 0 22px 4px var(--on-glow), 0 0 60px 6px rgba(255, 216, 77, .2);
  }
  /* soluzione mostrata a tempo scaduto */
  .bulb.sol { border-style: dashed; border-color: var(--accent); }
  .bulb.sol .lamp { background: rgba(92, 200, 255, .35); box-shadow: 0 0 18px rgba(92, 200, 255, .5); }
  .bulbs.vinto .bulb.on { border-color: var(--ok); }
  .bulbs.vinto .bulb.on .lamp {
    background: radial-gradient(circle at 35% 30%, #eafff5 0%, var(--ok) 50%, #1f9e68 100%);
    box-shadow: 0 0 26px 6px rgba(61, 220, 151, .55);
  }

  /* ---------- lettura binario/decimale ---------- */
  .readout {
    background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius);
    padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
  }
  .readout code { font-family: var(--mono); font-size: 1.35rem; letter-spacing: .12em; color: var(--on); }
  .readout .expr { font-family: var(--mono); font-size: 1rem; color: var(--text); }
  .readout .expr.oltre { color: var(--ko); }
  .readout .expr.oltre::after { content: "  troppo!"; font-size: .85rem; }

  .comandi { display: grid; grid-template-columns: 1fr; }

  /* ---------- toast feedback ---------- */
  .toast {
    position: fixed; left: 50%; top: 38%; transform: translate(-50%, -50%) scale(.8);
    padding: 14px 22px; border-radius: 16px; font-weight: 800; font-size: 1.4rem;
    background: var(--panel-2); border: 1px solid var(--line); opacity: 0; pointer-events: none;
    transition: opacity .2s, transform .25s cubic-bezier(.2, 1.4, .4, 1);
    text-align: center; max-width: calc(100% - 32px);
  }
  .toast.vis { opacity: 1; transform: translate(-50%, -50%) scale(1); }
  .toast.ok { color: var(--ok); border-color: var(--ok); }
  .toast.ko { color: var(--ko); border-color: var(--ko); }
  .toast small { display: block; font-size: .95rem; font-weight: 600; color: var(--muted); font-family: var(--mono); }

  /* ---------- fine partita ---------- */
  .punteggio-finale { font-family: var(--mono); font-size: 3.2rem; font-weight: 800; color: var(--on); line-height: 1; }
  .stat { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
  .stat div { background: var(--panel-2); border-radius: 12px; padding: 10px; text-align: center; }
  .stat b { display: block; font-family: var(--mono); font-size: 1.3rem; }
  .stat span { font-size: .8rem; color: var(--muted); }
  .salvataggio { font-size: .95rem; }
  .salvataggio.ok { color: var(--ok); }
  .salvataggio.ko { color: var(--ko); }

  /* ---------- desktop / tablet: 8 lampadine in riga ---------- */
  @media (min-width: 640px) {
    .bulbs { grid-template-columns: repeat(8, 1fr); gap: 8px; }
    .bulb:nth-child(4) { margin-right: 10px; } /* separa i due nibble */
    .comandi { grid-template-columns: 1fr 1fr; gap: 10px; }
  }
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { transition: none !important; }
  }
</style>
</head>
<body>
<main class="app">
  <header class="top">
    <a href="../index.php" aria-label="Torna alla mappa">&larr; Mappa</a>
    <h1>Binary <span>Lights</span></h1>
    <div class="hud" aria-live="polite">
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

<script>
'use strict';

const CONFIG = {
  round: <?= $round ?>,                       // dalla sessione in admin.php (Open Day 5, laboratorio 12)
  secondiRound: 20,
  puntiBase: 100,
  bonusSecondo: 10,               // max per round: 100 + 20*10 = 300 -> max partita round*300
  modulo: 'binary_lights',
  api: '../api/save_score.php',
};
// intervallo del numero bersaglio per ogni round (calcolato in PHP qui sopra)
const LIVELLI = <?= json_encode($intervalli) ?>;
const PESI = [128, 64, 32, 16, 8, 4, 2, 1];

const $ = (id) => document.getElementById(id);
const el = {
  intro: $('intro'), gioco: $('gioco'), fine: $('fine'),
  bulbs: $('bulbs'), target: $('target'), bin: $('bin'), expr: $('expr'),
  timer: $('timer'), timerFill: $('timer-fill'), secondi: $('secondi'),
  hudRound: $('hud-round'), hudPunti: $('hud-punti'), toast: $('toast'),
};

const stato = {
  bits: new Array(8).fill(0),
  round: 0,
  punti: 0,
  indovinati: 0,
  target: 0,
  inizio: 0,
  bloccato: true,
  raf: 0,
  tempoMigliore: Infinity,
  timeout: 0,
};

/* ---------- storage sicuro (puo' fallire in navigazione privata) ---------- */
const storage = {
  get(k) { try { return localStorage.getItem(k); } catch { return null; } },
  set(k, v) { try { localStorage.setItem(k, v); } catch { /* ignora */ } },
};

/* ---------- costruzione lampadine ---------- */
const lampadine = PESI.map((peso, i) => {
  const b = document.createElement('button');
  b.type = 'button';
  b.className = 'bulb';
  b.setAttribute('aria-pressed', 'false');
  b.setAttribute('aria-label', `Bit da ${peso}`);
  b.innerHTML = `<span class="bit">0</span><span class="lamp"></span><span class="peso">${peso}</span>`;
  b.addEventListener('click', () => toggle(i));
  el.bulbs.append(b);
  return b;
});

const valore = () => stato.bits.reduce((acc, bit, i) => acc + bit * PESI[i], 0);
const inBinario = (n) => n.toString(2).padStart(8, '0').replace(/^(.{4})/, '$1 ');
const vibra = (ms) => { if (navigator.vibrate) navigator.vibrate(ms); };

function render() {
  lampadine.forEach((b, i) => {
    const on = stato.bits[i] === 1;
    b.classList.toggle('on', on);
    b.setAttribute('aria-pressed', String(on));
    b.firstChild.textContent = on ? '1' : '0';
  });
  const v = valore();
  const accesi = PESI.filter((_, i) => stato.bits[i]);
  el.bin.textContent = inBinario(v);
  el.expr.textContent = accesi.length > 1 ? `${accesi.join(' + ')} = ${v}` : `= ${v}`;
  el.expr.classList.toggle('oltre', v > stato.target);
}

function toggle(i) {
  if (stato.bloccato) return;
  stato.bits[i] ^= 1;
  vibra(8);
  render();
  if (valore() === stato.target) successo();
}

function mostra(sezione) {
  for (const s of [el.intro, el.gioco, el.fine]) s.hidden = s !== sezione;
}

function toast(html, tipo, durata) {
  el.toast.innerHTML = html;
  el.toast.className = `toast vis ${tipo}`;
  clearTimeout(toast.t);
  toast.t = setTimeout(() => el.toast.classList.remove('vis'), durata);
}

function aggiornaHud() {
  el.hudRound.textContent = stato.round;
  el.hudPunti.textContent = stato.punti;
}

/* ---------- ciclo di gioco ---------- */
function nuovaPartita() {
  Object.assign(stato, { round: 0, punti: 0, indovinati: 0, tempoMigliore: Infinity, target: 0 });
  aggiornaHud();
  mostra(el.gioco);
  nuovoRound();
}

function nuovoRound() {
  if (stato.round >= CONFIG.round) return fine();

  stato.round++;
  const [min, max] = LIVELLI[stato.round - 1];
  let t;
  do { t = min + Math.floor(Math.random() * (max - min + 1)); } while (t === stato.target);
  stato.target = t;
  stato.bits.fill(0);

  el.bulbs.classList.remove('vinto');
  lampadine.forEach((b) => b.classList.remove('sol'));
  el.target.textContent = t;
  aggiornaHud();
  render();

  stato.bloccato = false;
  stato.inizio = performance.now();
  cancelAnimationFrame(stato.raf);
  stato.raf = requestAnimationFrame(tick);
}

function tick(now) {
  const totale = CONFIG.secondiRound * 1000;
  const restante = Math.max(0, totale - (now - stato.inizio));
  el.timerFill.style.transform = `scaleX(${restante / totale})`;
  el.secondi.textContent = `${Math.ceil(restante / 1000)} s`;
  el.timer.classList.toggle('urgente', restante < 5000);

  if (restante <= 0) return tempoScaduto();
  stato.raf = requestAnimationFrame(tick);
}

function successo() {
  stato.bloccato = true;
  cancelAnimationFrame(stato.raf);

  const trascorsi = (performance.now() - stato.inizio) / 1000;
  const secondiRimasti = Math.max(0, Math.floor(CONFIG.secondiRound - trascorsi));
  const guadagno = CONFIG.puntiBase + secondiRimasti * CONFIG.bonusSecondo;
  stato.punti += guadagno;
  stato.indovinati++;
  stato.tempoMigliore = Math.min(stato.tempoMigliore, trascorsi);
  aggiornaHud();

  el.bulbs.classList.add('vinto');
  vibra([30, 40, 30]);
  toast(`+${guadagno}<small>${inBinario(stato.target)} = ${stato.target}</small>`, 'ok', 1000);
  stato.timeout = setTimeout(nuovoRound, 1100);
}

function tempoScaduto() {
  stato.bloccato = true;
  // mostra la soluzione sulle lampadine
  PESI.forEach((peso, i) => lampadine[i].classList.toggle('sol', (stato.target & peso) !== 0));
  vibra(200);
  toast(`Tempo scaduto!<small>${stato.target} = ${inBinario(stato.target)}</small>`, 'ko', 2000);
  stato.timeout = setTimeout(nuovoRound, 2200);
}

function fine() {
  stato.bloccato = true;
  const record = Math.max(stato.punti, Number(storage.get('od_record_binary_lights')) || 0);
  storage.set('od_record_binary_lights', String(record));

  $('fine-titolo').textContent =
    stato.indovinati === CONFIG.round ? 'Byte perfetto! 🎉' :
    stato.indovinati >= 3 ? 'Ottimo lavoro!' : 'Partita finita!';
  $('fine-punti').textContent = stato.punti;
  $('fine-indovinati').textContent = `${stato.indovinati}/${CONFIG.round}`;
  $('fine-veloce').textContent = Number.isFinite(stato.tempoMigliore) ? `${stato.tempoMigliore.toFixed(1)}s` : '–';
  $('fine-record').textContent = record;
  mostra(el.fine);
  salvaPunteggio(stato.punti);
}

/* ---------- salvataggio sul server (se l'utente ha fatto login + nickname) ---------- */
async function salvaPunteggio(punti) {
  const out = $('salvataggio');
  out.className = 'salvataggio';
  const uuid = storage.get('od_uuid');
  if (!uuid) {
    out.textContent = 'Modalità prova: fai il login all\'Open Day per entrare in classifica.';
    return;
  }

  out.textContent = 'Salvataggio in corso…';
  try {
    const res = await fetch(CONFIG.api, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ uuid, modulo: CONFIG.modulo, punti }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) throw new Error(data.errore || `Errore ${res.status}`);

    out.classList.add('ok');
    out.textContent = data.nuovo_record
      ? `Nuovo record salvato! Totale in classifica: ${data.punti_totali} pt`
      : `Salvato. Il tuo record (${data.punti_modulo} pt) resta valido. Totale: ${data.punti_totali} pt`;
  } catch (err) {
    out.classList.add('ko');
    out.textContent = `Punteggio non salvato: ${err.message}`;
  }
}

/* ---------- eventi ---------- */
$('btn-start').addEventListener('click', nuovaPartita);
$('btn-ancora').addEventListener('click', nuovaPartita);
$('btn-azzera').addEventListener('click', () => {
  if (stato.bloccato) return;
  stato.bits.fill(0);
  render();
});

// Desktop: tasti 1-8 = lampadine da sinistra a destra, Backspace = spegni tutto
document.addEventListener('keydown', (e) => {
  if (el.gioco.hidden) return;
  const n = Number(e.key);
  if (n >= 1 && n <= 8) toggle(n - 1);
  else if (e.key === 'Backspace') $('btn-azzera').click();
});

render();
</script>
</body>
</html>
