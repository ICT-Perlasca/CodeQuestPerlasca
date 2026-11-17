<?php
/**
 * Modifica il gioco: una palla che rimbalza gia' programmata, con le variabili
 * (gravita', rimbalzo, velocita', grandezza, colori) modificabili direttamente
 * nel codice: l'effetto si vede subito. Missioni da completare, 100 punti l'una.
 *
 * Fisica e missioni in assets/js/palla-fisica.js. Il numero di missioni dipende
 * dalla sessione scelta in admin.php (TIPI_GIORNATA in inc/db.php).
 * Senza login si gioca in "modalita' prova": progressi solo nel browser.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/db.php';

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
<style>
  :root {
    --bg:#0b1020; --panel:#141b33; --panel-2:#1c2547; --line:#2a3566; --text:#e8ecff; --muted:#8e98c7;
    --on:#ffd84d; --ok:#3ddc97; --ko:#ff5c7a; --accent:#5cc8ff; --run:#ff9f1a; --radius:16px;
    --mono: ui-monospace, "SF Mono", "Cascadia Mono", "Roboto Mono", Menlo, Consolas, monospace;
    --kw:#ff9fd4; --num:#ffd84d; --str:#9be59b; --cm:#6f7aa8;
  }
  * { box-sizing:border-box; }
  html, body { margin:0; }
  body { min-height:100dvh; background:radial-gradient(120% 60% at 50% 0%, #17204a 0%, var(--bg) 60%) fixed, var(--bg);
         color:var(--text); font:16px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; -webkit-tap-highlight-color:transparent; }
  button, select, input { font:inherit; color:inherit; }
  button { touch-action:manipulation; cursor:pointer; }
  [hidden] { display:none !important; }

  .top { display:flex; align-items:center; gap:10px 14px; flex-wrap:wrap; padding:max(10px, env(safe-area-inset-top)) 16px 10px;
         border-bottom:1px solid var(--line); background:rgba(11,16,32,.85); }
  .top a { color:var(--muted); text-decoration:none; font-size:.95rem; }
  .top h1 { margin:0; font-size:1.1rem; white-space:nowrap; }
  .top h1 span { color:var(--on); }
  .missioni { display:flex; gap:4px; flex-wrap:wrap; margin-right:auto; }
  .missioni button { width:30px; height:30px; border-radius:50%; border:2px solid var(--line); background:var(--panel);
                     font-weight:800; font-size:.8rem; display:grid; place-items:center; padding:0; }
  .missioni button.fatta { background:var(--ok); border-color:var(--ok); color:#04241a; }
  .missioni button.corrente { border-color:var(--on); box-shadow:0 0 0 3px rgba(255,216,77,.3); }
  .missioni button:disabled { opacity:.35; cursor:not-allowed; }
  .pill { background:var(--panel); border:1px solid var(--line); border-radius:999px; padding:4px 12px; font-family:var(--mono); font-size:.9rem; white-space:nowrap; }
  .pill b { color:var(--on); }

  .main { display:grid; gap:14px; padding:14px 16px max(16px, env(safe-area-inset-bottom)); max-width:1200px; margin:0 auto; }
  @media (min-width:900px) { .main { grid-template-columns:1fr 1fr; align-items:start; } }
  .col { display:grid; gap:12px; min-width:0; }
  .card { background:var(--panel); border:1px solid var(--line); border-radius:var(--radius); padding:14px 16px; }
  .missione h2 { margin:0 0 6px; font-size:1.15rem; }
  .missione h2 small { color:var(--muted); font-weight:600; font-size:.85rem; margin-right:6px; }
  .missione p { margin:0; color:#c9d0f2; }
  .missione b { color:var(--on); }
  .esito { margin-top:10px !important; font-weight:700; }
  .esito.ok { color:var(--ok); }

  .campo { position:relative; border-radius:var(--radius); overflow:hidden; border:1px solid var(--line); aspect-ratio:4 / 3; }
  .campo canvas { display:block; width:100%; height:100%; }
  .comandi { display:flex; gap:10px; flex-wrap:wrap; }
  .btn { border:0; border-radius:12px; padding:12px 18px; font-weight:800; font-size:1.05rem; transition:transform .08s; }
  .btn:active { transform:scale(.97); }
  .btn.lancia { background:var(--run); color:#2a1500; flex:1; min-width:140px; }
  .btn.secondario { background:var(--panel-2); border:1px solid var(--line); font-weight:600; font-size:.95rem; }
  .btn.primario { background:var(--accent); color:#04121c; }

  /* ---------- il codice con i valori modificabili ---------- */
  .codice { font-family:var(--mono); font-size:.92rem; background:#0e1430; border:1px solid var(--line); border-radius:var(--radius); padding:12px 14px; overflow-x:auto; }
  .codice .titolo-file { color:var(--muted); font-size:.8rem; margin-bottom:8px; }
  .riga { display:flex; flex-wrap:wrap; align-items:center; gap:2px 6px; padding:5px 0; border-bottom:1px dashed #1f2852; }
  .riga:last-child { border-bottom:0; }
  .riga.bloccata { opacity:.55; }
  .kw { color:var(--kw); } .cm { color:var(--cm); } .fn { color:var(--on); } .str { color:var(--str); }
  .riga .cm { flex-basis:100%; font-size:.8rem; padding-left:2ch; }
  .riga.cambiata { animation:lampo .6s; }
  @keyframes lampo { from { background:rgba(255,216,77,.18); } to { background:transparent; } }
  .val { font-family:var(--mono); font-size:.95rem; color:var(--num); background:#18204a; border:1px solid #34407a; border-radius:6px; padding:3px 6px; }
  input.val[type=number] { width:6.5ch; }
  input.val:focus, select.val:focus { outline:2px solid var(--accent); outline-offset:1px; }
  input.val[type=color] { width:34px; height:28px; padding:1px; cursor:pointer; }
  select.val { color:var(--kw); }
  .val:disabled { cursor:not-allowed; }
  .hex { color:var(--str); }
  .riga input[type=range] { flex-basis:100%; accent-color:var(--accent); margin:4px 0 0; }
  details.codice summary { cursor:pointer; color:var(--accent); font-family:system-ui, sans-serif; font-weight:600; }
  pre.motore { margin:10px 0 0; white-space:pre; line-height:1.5; }
  .avviso { font-size:.88rem; color:var(--muted); margin:0; }
  .avviso a { color:var(--accent); }

  .toast { position:fixed; left:50%; bottom:24px; transform:translate(-50%, 20px); background:var(--ok); color:#04241a;
           font-weight:800; padding:12px 18px; border-radius:12px; opacity:0; pointer-events:none; transition:.25s; z-index:5; text-align:center; }
  .toast.vis { opacity:1; transform:translate(-50%, 0); }
  .toast small { display:block; font-weight:600; }
</style>
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

<script src="../assets/js/palla-fisica.js"></script>
<script>
'use strict';

const F = window.PallaFisica;
const N_MISSIONI = <?= (int) $missioni ?>;     // dalla sessione in admin.php (Open Day 4, laboratorio 10)
const MISSIONI = F.MISSIONI.slice(0, N_MISSIONI);
const PUNTI_MISSIONE = 100;
const API = '../api/save_score.php';
const MODULO = 'modifica_gioco';
const SBLOCCA_TUTTO = <?= sblocca_livelli() ? 'true' : 'false' ?>; // missioni tutte aperte (scelto per sessione in admin)

const $ = (id) => document.getElementById(id);
const storage = {
  get(k) { try { return localStorage.getItem(k); } catch { return null; } },
  set(k, v) { try { localStorage.setItem(k, v); } catch { /* ignora */ } },
};

// progressi per profilo: sullo stesso telefono due nickname diversi non si mescolano
const CHIAVE_FATTE = `od_palla_fatte_${storage.get('od_uuid') || 'prova'}`;
const valoriIniziali = () => Object.fromEntries(Object.entries(F.VARIABILI).map(([k, d]) => [k, d.val]));
const stato = {
  m: 0,                                   // indice della missione aperta
  v: valoriIniziali(),
  palla: null,
  scia: [],
  fatte: new Set(JSON.parse(storage.get(CHIAVE_FATTE) || '[]').filter((i) => i < N_MISSIONI)),
  salvataggio: 0,
};

/* =====================================================================
   IL CODICE: una riga per variabile, il valore e' un campo modificabile
   ===================================================================== */
const campi = {};
function costruisciCodice() {
  const box = $('codice');
  for (const [nome, d] of Object.entries(F.VARIABILI)) {
    const riga = document.createElement('div');
    riga.className = 'riga';
    riga.dataset.nome = nome;
    const pre = document.createElement('span');
    pre.innerHTML = `<span class="kw">let</span> ${nome} =`;
    riga.append(pre);

    let input, range = null, hex = null;
    if (d.tipo === 'num') {
      input = Object.assign(document.createElement('input'), { type: 'number', min: d.min, max: d.max, step: d.step, className: 'val', inputMode: 'decimal' });
      range = Object.assign(document.createElement('input'), { type: 'range', min: d.min, max: d.max, step: d.step });
      range.setAttribute('aria-label', `${nome} (cursore)`);
    } else if (d.tipo === 'col') {
      input = Object.assign(document.createElement('input'), { type: 'color', className: 'val' });
      hex = Object.assign(document.createElement('span'), { className: 'hex' });
    } else {
      input = document.createElement('select');
      input.className = 'val';
      input.innerHTML = '<option value="true">true</option><option value="false">false</option>';
    }
    input.setAttribute('aria-label', nome);
    riga.append(input);
    if (hex) riga.append(hex);
    riga.append(Object.assign(document.createElement('span'), { textContent: ';' }));
    const cm = Object.assign(document.createElement('span'), { className: 'cm', textContent: `// ${d.nota}` });
    riga.append(cm);
    if (range) riga.append(range);
    box.append(riga);

    const applica = (grezzo, daRange) => {
      let val = grezzo;
      if (d.tipo === 'num') {
        val = Number(String(grezzo).replace(',', '.'));
        if (!Number.isFinite(val)) return;
        val = Math.min(d.max, Math.max(d.min, val));
        val = Math.round(val / d.step) * d.step;
        val = Number(val.toFixed(2));        // 1 resta 1 (non 0.9999999)
      } else if (d.tipo === 'bool') val = grezzo === 'true';
      stato.v[nome] = val;
      mostraValore(nome, daRange);
      riga.classList.remove('cambiata'); void riga.offsetWidth; riga.classList.add('cambiata');
      if (nome === 'scia' || nome === 'coloreSfondo') stato.scia = [];
      controlla();
    };
    input.addEventListener(d.tipo === 'num' ? 'change' : 'input', () => applica(input.value, false));
    if (d.tipo === 'num') input.addEventListener('input', () => { if (input.value !== '' && !input.value.endsWith('.')) applica(input.value, true); });
    if (range) range.addEventListener('input', () => applica(range.value, false));
    campi[nome] = { input, range, hex, riga };
  }
}

/** Scrive nei campi il valore attuale (senza toccare quello che si sta digitando). */
function mostraValore(nome, tranneInput = false) {
  const c = campi[nome], v = stato.v[nome];
  if (!tranneInput) c.input.value = String(v);
  if (c.range) c.range.value = String(v);
  if (c.hex) c.hex.textContent = `"${v}"`;
}

function applicaBlocchi() {
  const blocca = MISSIONI[stato.m].blocca || {};
  for (const [nome, c] of Object.entries(campi)) {
    const bloccata = nome in blocca;
    if (bloccata) stato.v[nome] = blocca[nome];
    c.input.disabled = bloccata;
    if (c.range) c.range.disabled = bloccata;
    c.riga.classList.toggle('bloccata', bloccata);
    c.riga.title = bloccata ? '🔒 Bloccata in questa missione' : '';
    mostraValore(nome);
  }
}

/* =====================================================================
   SIMULAZIONE E DISEGNO
   ===================================================================== */
const cv = $('campo');
const ctx = cv.getContext('2d');
const S = cv.width / F.W;                 // da unita' del gioco a pixel del canvas

function lancia() {
  stato.palla = F.lancia(stato.v);
  stato.scia = [];
}

function disegna() {
  const v = stato.v, p = stato.palla, m = MISSIONI[stato.m];
  ctx.setTransform(S, 0, 0, S, 0, 0);
  ctx.fillStyle = v.coloreSfondo;
  ctx.fillRect(0, 0, F.W, F.H);

  // punto di partenza
  ctx.strokeStyle = 'rgba(255,255,255,.25)';
  ctx.setLineDash([3, 3]);
  ctx.beginPath(); ctx.arc(F.PARTENZA.x, F.PARTENZA.y, 6, 0, Math.PI * 2); ctx.stroke();
  ctx.setLineDash([]);

  if (m.muro) {
    const q = m.muro;
    ctx.fillStyle = '#8a5a3c';
    ctx.fillRect(q.x, q.y, q.w, q.h);
    ctx.fillStyle = 'rgba(0,0,0,.25)';
    for (let y = q.y + 8; y < q.y + q.h; y += 10) ctx.fillRect(q.x, y, q.w, 1.5);   // mattoni
  }
  if (m.canestro) {
    const c = m.canestro;
    ctx.lineWidth = 3;
    ctx.strokeStyle = p && p.st.centrato ? '#3ddc97' : '#ff8c1a';
    ctx.beginPath(); ctx.arc(c.x, c.y, c.r, 0, Math.PI * 2); ctx.stroke();
    ctx.strokeStyle = 'rgba(255,255,255,.35)'; ctx.lineWidth = 1;
    for (let i = -2; i <= 2; i++) { ctx.beginPath(); ctx.moveTo(c.x + i * c.r / 3, c.y - c.r * .8); ctx.lineTo(c.x + i * c.r / 4, c.y + c.r * .8); ctx.stroke(); }
  }

  if (v.scia) {
    stato.scia.forEach(([x, y], i) => {
      ctx.globalAlpha = (i + 1) / stato.scia.length * .5;
      ctx.fillStyle = v.colorePalla;
      ctx.beginPath(); ctx.arc(x, y, v.raggio * .6, 0, Math.PI * 2); ctx.fill();
    });
    ctx.globalAlpha = 1;
  }

  if (p) {
    ctx.fillStyle = v.colorePalla;
    ctx.beginPath(); ctx.arc(p.x, p.y, v.raggio, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,.35)';
    ctx.beginPath(); ctx.arc(p.x - v.raggio * .35, p.y - v.raggio * .35, v.raggio * .28, 0, Math.PI * 2); ctx.fill();
  }

  // freccia della spinta iniziale: fa capire velocitaX / velocitaY
  ctx.strokeStyle = 'rgba(92,200,255,.7)'; ctx.lineWidth = 2;
  ctx.beginPath(); ctx.moveTo(F.PARTENZA.x, F.PARTENZA.y);
  ctx.lineTo(F.PARTENZA.x + v.velocitaX * 4, F.PARTENZA.y + v.velocitaY * 4); ctx.stroke();
}

let ultimo = 0, accumulo = 0;
function ciclo(t) {
  const dt = Math.min(100, t - (ultimo || t));
  ultimo = t;
  accumulo += dt;
  // passi fissi da 1/60 s: la fisica e' uguale su ogni telefono
  while (accumulo >= 1000 / 60) {
    accumulo -= 1000 / 60;
    if (stato.palla) {
      F.passo(stato.palla, stato.v, MISSIONI[stato.m]);
      if (stato.v.scia) { stato.scia.push([stato.palla.x, stato.palla.y]); if (stato.scia.length > 60) stato.scia.shift(); }
      if (stato.palla.fermo > 90) lancia();     // ferma da 1,5 s: si rilancia da sola
    }
  }
  controlla();
  disegna();
  requestAnimationFrame(ciclo);
}

/* =====================================================================
   MISSIONI
   ===================================================================== */
function apriMissione(i) {
  if (!SBLOCCA_TUTTO && i > 0 && !stato.fatte.has(i - 1)) return;
  stato.m = i;
  storage.set('od_palla_missione', String(i));
  const m = MISSIONI[i];
  $('m-num').textContent = `Missione ${i + 1}/${MISSIONI.length}`;
  $('m-titolo').textContent = m.titolo;
  $('m-testo').innerHTML = m.testo;
  applicaBlocchi();
  aggiornaEsito();
  disegnaMissioni();
  lancia();
}

function aggiornaEsito() {
  const fatta = stato.fatte.has(stato.m);
  const out = $('m-esito');
  out.className = 'esito' + (fatta ? ' ok' : '');
  out.textContent = fatta ? '✓ Missione compiuta!' : '';
  $('btn-avanti').hidden = !fatta || stato.m >= MISSIONI.length - 1;
}

function controlla() {
  if (!stato.palla || stato.fatte.has(stato.m)) return;
  if (!MISSIONI[stato.m].ok(stato.v, stato.palla.st)) return;
  stato.fatte.add(stato.m);
  storage.set(CHIAVE_FATTE, JSON.stringify([...stato.fatte]));
  if (navigator.vibrate) navigator.vibrate([30, 40, 30]);
  const tutte = stato.fatte.size === MISSIONI.length;
  toast(tutte ? 'Tutte le missioni compiute! 🏆' : 'Missione compiuta! 🎉', `+${PUNTI_MISSIONE} punti`);
  aggiornaEsito();
  disegnaMissioni();
  salva();
}

function disegnaMissioni() {
  const nav = $('missioni');
  nav.replaceChildren();
  MISSIONI.forEach((m, i) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = stato.fatte.has(i) ? '✓' : i + 1;
    b.title = `Missione ${i + 1}: ${m.titolo}`;
    b.classList.toggle('fatta', stato.fatte.has(i));
    b.classList.toggle('corrente', i === stato.m);
    b.disabled = !SBLOCCA_TUTTO && i > 0 && !stato.fatte.has(i - 1);
    b.addEventListener('click', () => apriMissione(i));
    nav.append(b);
  });
  $('hud-punti').textContent = stato.fatte.size * PUNTI_MISSIONE;
}

function toast(testo, sotto) {
  const el = $('toast');
  el.innerHTML = '';
  el.append(testo);
  if (sotto) el.append(Object.assign(document.createElement('small'), { textContent: sotto }));
  el.classList.add('vis');
  clearTimeout(toast.t);
  toast.t = setTimeout(() => el.classList.remove('vis'), 2200);
}

/* ---------- salvataggio: il punteggio e' 100 x missioni compiute (vale il record) ---------- */
async function salva() {
  const uuid = storage.get('od_uuid');
  const avviso = $('avviso');
  if (!uuid) {
    avviso.innerHTML = 'Modalità prova: <a href="../index.php">entra all\'Open Day</a> per salvare i punti in classifica.';
    avviso.hidden = false;
    return;
  }
  const punti = stato.fatte.size * PUNTI_MISSIONE;
  try {
    const res = await fetch(API, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ uuid, modulo: MODULO, punti }),
    });
    const d = await res.json().catch(() => ({}));
    if (res.status === 429) { clearTimeout(stato.salvataggio); stato.salvataggio = setTimeout(salva, 2500); return; } // due missioni di fila
    if (!res.ok || !d.ok) throw new Error(d.errore || `Errore ${res.status}`);
    avviso.hidden = true;
  } catch (e) {
    avviso.textContent = `Punteggio non salvato: ${e.message}`;
    avviso.hidden = false;
  }
}

$('btn-lancia').addEventListener('click', lancia);
$('btn-reset').addEventListener('click', () => {
  stato.v = valoriIniziali();
  applicaBlocchi();
  lancia();
});
$('btn-avanti').addEventListener('click', () => apriMissione(stato.m + 1));

costruisciCodice();
const primaLibera = MISSIONI.findIndex((m, i) => !stato.fatte.has(i));
const salvata = Number(storage.get('od_palla_missione'));
apriMissione(Number.isInteger(salvata) && salvata < MISSIONI.length && (SBLOCCA_TUTTO || salvata === 0 || stato.fatte.has(salvata - 1))
  ? salvata : Math.max(0, primaLibera));
if (!storage.get('od_uuid')) { $('avviso').innerHTML = 'Modalità prova: <a href="../index.php">entra all\'Open Day</a> per salvare i punti in classifica.'; $('avviso').hidden = false; }
else if (stato.fatte.size) salva(); // riallinea il server se il telefono ha progressi non salvati
requestAnimationFrame(ciclo);
</script>
</body>
</html>
