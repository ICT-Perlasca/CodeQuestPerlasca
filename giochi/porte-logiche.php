<?php
/**
 * Porte logiche: interruttori (bit) collegati a porte AND / OR / NOT fino a una
 * lampadina da accendere. I primi livelli non spiegano niente: le porte sono "scatole
 * misteriose" che si scoprono giocando. Collega il binario al "ragionamento" del computer:
 * 1 = corrente, 0 = niente corrente, e con le porte si arriva a fare le somme.
 *
 * Livelli e logica in assets/js/porte-logiche.js. Il numero di livelli dipende
 * dalla sessione scelta in admin.php (TIPI_GIORNATA in inc/db.php). 100 punti a livello.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/db.php';

$livelli = sessione_attivita()['giochi']['porte_livelli']; // numeri dei livelli (da 1) di questa sessione
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<title>Porte logiche · CodeQuestPerlasca</title>
<style>
  :root {
    --bg:#0b1020; --panel:#141b33; --panel-2:#1c2547; --line:#2a3566; --text:#e8ecff; --muted:#8e98c7;
    --on:#ffd84d; --off:#3a4680; --ok:#3ddc97; --ko:#ff5c7a; --accent:#5cc8ff; --radius:16px;
    --mono: ui-monospace, "SF Mono", "Cascadia Mono", "Roboto Mono", Menlo, Consolas, monospace;
  }
  * { box-sizing:border-box; }
  html, body { margin:0; }
  body { min-height:100dvh; background:radial-gradient(120% 60% at 50% 0%, #17204a 0%, var(--bg) 60%) fixed, var(--bg);
         color:var(--text); font:16px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; -webkit-tap-highlight-color:transparent; }
  button { font:inherit; color:inherit; touch-action:manipulation; cursor:pointer; }
  [hidden] { display:none !important; }

  .top { display:flex; align-items:center; gap:10px 14px; flex-wrap:wrap; padding:max(10px, env(safe-area-inset-top)) 16px 10px;
         border-bottom:1px solid var(--line); background:rgba(11,16,32,.85); }
  .top a { color:var(--muted); text-decoration:none; font-size:.95rem; }
  .top h1 { margin:0; font-size:1.1rem; white-space:nowrap; }
  .top h1 span { color:var(--on); }
  .livelli { display:flex; gap:4px; flex-wrap:wrap; margin-right:auto; }
  .livelli button { width:30px; height:30px; border-radius:50%; border:2px solid var(--line); background:var(--panel);
                    font-weight:800; font-size:.8rem; display:grid; place-items:center; padding:0; }
  .livelli button.fatto { background:var(--ok); border-color:var(--ok); color:#04241a; }
  .livelli button.corrente { border-color:var(--on); box-shadow:0 0 0 3px rgba(255,216,77,.3); }
  .livelli button:disabled { opacity:.35; cursor:not-allowed; }
  .pill { background:var(--panel); border:1px solid var(--line); border-radius:999px; padding:4px 12px; font-family:var(--mono); font-size:.9rem; white-space:nowrap; }
  .pill b { color:var(--on); }

  .main { display:grid; gap:14px; padding:14px 16px max(16px, env(safe-area-inset-bottom)); max-width:1200px; margin:0 auto; }
  @media (min-width:900px) { .main { grid-template-columns:1.4fr 1fr; align-items:start; } }
  .col { display:grid; gap:12px; min-width:0; }
  .card { background:var(--panel); border:1px solid var(--line); border-radius:var(--radius); padding:14px 16px; }
  .card h2 { margin:0 0 6px; font-size:1.15rem; }
  .card h2 small { color:var(--muted); font-weight:600; font-size:.85rem; margin-right:6px; }
  .card h3 { margin:0 0 8px; font-size:1rem; }
  .card p { margin:0; color:#c9d0f2; }
  .card b { color:var(--on); }
  .esito { margin-top:10px !important; font-weight:700; }
  .esito.ok { color:var(--ok); }

  /* ---------- circuito ---------- */
  .circuito { background:#0e1430; border:1px solid var(--line); border-radius:var(--radius); padding:8px; overflow-x:auto; }
  .circuito svg { display:block; width:100%; height:auto; }
  .filo { fill:none; stroke-width:4; stroke-linecap:round; transition:stroke .2s; }
  .filo.on { stroke:var(--on); filter:drop-shadow(0 0 3px rgba(255,216,77,.7)); }
  .filo.off { stroke:var(--off); }
  .porta path, .porta rect { stroke-width:2.5; transition:fill .2s; }
  .porta text { font:700 12px system-ui, sans-serif; fill:var(--text); text-anchor:middle; }
  .porta .nome { font-size:11px; fill:var(--muted); }
  .cliccabile { cursor:pointer; }
  .cliccabile:focus { outline:none; }
  .cliccabile:focus-visible > :first-child { stroke:var(--accent); stroke-width:3; }
  .bit { font:800 13px var(--mono); text-anchor:middle; }
  .lampadina circle.vetro { transition:fill .25s; }

  /* ---------- binario e sfida ---------- */
  .binario { font-family:var(--mono); display:flex; flex-wrap:wrap; gap:6px 14px; align-items:baseline; }
  .binario .bits { font-size:1.5rem; font-weight:800; letter-spacing:.15em; color:var(--on); }
  .binario .nomi { color:var(--muted); letter-spacing:.15em; }
  .nota { font-size:.88rem; color:var(--muted) !important; margin-top:8px !important; }
  .chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
  .chip { font-family:var(--mono); font-weight:700; border:1px solid var(--line); background:var(--panel-2); color:var(--muted);
          border-radius:999px; padding:3px 10px; }
  .chip.trovata { background:var(--ok); border-color:var(--ok); color:#04241a; }
  .chip.ora { border-color:var(--on); }
  table { border-collapse:collapse; width:100%; font-family:var(--mono); margin-top:6px; }
  th, td { padding:5px 6px; border-bottom:1px solid var(--line); text-align:center; }
  th { color:var(--muted); font-weight:600; font-family:system-ui, sans-serif; font-size:.85rem; }
  tr.ora td { background:rgba(255,216,77,.1); }
  td.si { color:var(--ok); } td.no { color:var(--ko); }
  .regole { display:grid; gap:10px; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); }
  .regola { background:var(--panel-2); border-radius:12px; padding:8px 10px; font-size:.85rem; color:#c9d0f2; }
  .regola.usata { outline:2px solid var(--accent); }
  .regola b { display:block; font-family:var(--mono); }
  .regola table { font-size:.8rem; margin-top:4px; }
  .regola td, .regola th { padding:2px; }
  .risposte { display:grid; gap:6px; margin-top:8px; }
  .risposta { text-align:left; padding:10px 12px; border-radius:10px; border:1px solid var(--line); background:var(--panel-2); font-weight:600; }
  .risposta:disabled { opacity:.5; cursor:not-allowed; }
  .risposta.giusta { background:var(--ok); border-color:var(--ok); color:#04241a; opacity:1; }
  .risposta.sbagliata { border-color:var(--ko); color:var(--ko); }
  .comandi { display:flex; gap:10px; flex-wrap:wrap; }
  .btn { border:0; border-radius:12px; padding:12px 18px; font-weight:800; font-size:1.05rem; }
  .btn.primario { background:var(--accent); color:#04121c; flex:1; }
  .btn.secondario { background:var(--panel-2); border:1px solid var(--line); font-weight:600; font-size:.95rem; }
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
  <h1>💡 Porte <span>logiche</span></h1>
  <nav class="livelli" id="livelli" aria-label="Livelli"></nav>
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

<script src="../assets/js/porte-logiche.js"></script>
<script>
'use strict';

const P = window.PorteLogiche;
// quali livelli si giocano: dalla sessione in admin.php (Open Day: scatole misteriose + livelli difficili)
const NUMERI = <?= json_encode(array_values(array_map('intval', $livelli))) ?>;
const LIVELLI = NUMERI.map((n) => ({ ...P.LIVELLI[n - 1], orig: n - 1 })).filter((l) => l.tipo);
const N_LIVELLI = LIVELLI.length;
const PUNTI_LIVELLO = 100;
const API = '../api/save_score.php';
const MODULO = 'porte_logiche';

const $ = (id) => document.getElementById(id);
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
const storage = {
  get(k) { try { return localStorage.getItem(k); } catch { return null; } },
  set(k, v) { try { localStorage.setItem(k, v); } catch { /* ignora */ } },
};
const CHIAVE_FATTI = `od_porte_fatti_${storage.get('od_uuid') || 'prova'}`;

const stato = {
  n: 0,
  bit: {},
  scelte: {},
  trovate: new Set(),   // combinazioni / numeri gia' trovati nel livello aperto
  provate: new Set(),   // combinazioni provate (sfida "indovina")
  indovinato: false,
  sbagliata: -1,        // ultima risposta sbagliata (sfida "indovina")
  fatti: new Set((JSON.parse(storage.get(CHIAVE_FATTI) || '[]')).filter((i) => i < N_LIVELLI)),
  salvataggio: 0,
};
const liv = () => LIVELLI[stato.n];

/* =====================================================================
   DISPOSIZIONE: colonne per "profondita'", righe ordinate per non incrociare i fili
   ===================================================================== */
// su telefono colonne piu' strette: il circuito intero sta nello schermo e resta grande da toccare
const stretto = () => window.innerWidth < 600;
const RIGA_H = 84, MARGINE = 46;
let COL_W = stretto() ? 100 : 130;
window.addEventListener('resize', () => { const c = stretto() ? 100 : 130; if (c !== COL_W) { COL_W = c; disegna(); } });

function disponi(l) {
  const col = {}, pos = {};
  l.ingressi.forEach((n) => { col[n] = 0; });
  l.porte.forEach((p) => { col[p.id] = 1 + Math.max(...p.da.map((d) => col[d])); });
  const ultima = Math.max(...l.porte.map((p) => col[p.id])) + 1;
  l.uscite.forEach((u) => { col[u.id] = ultima; });

  const colonne = [];
  const nodi = [...l.ingressi, ...l.porte.map((p) => p.id), ...l.uscite.map((u) => u.id)];
  nodi.forEach((id) => { (colonne[col[id]] ||= []).push(id); });
  const da = Object.fromEntries([...l.porte.map((p) => [p.id, p.da]), ...l.uscite.map((u) => [u.id, [u.da]])]);
  const righe = Math.max(...colonne.map((c) => c.length));
  const H = righe * RIGA_H + 20;

  colonne.forEach((ids, c) => {
    // baricentro: un nodo sta all'altezza media dei nodi da cui riceve i fili
    // (le lampadine restano nell'ordine scritto: la prima e' il bit piu' alto del numero)
    if (c > 0 && c < ultima) ids.sort((a, b) => media(a) - media(b));
    ids.forEach((id, i) => { pos[id] = { x: MARGINE + c * COL_W, y: H * (i + 1) / (ids.length + 1) }; });
  });
  function media(id) { const d = da[id]; return d.reduce((s, x) => s + (pos[x] ? pos[x].y : 0), 0) / d.length; }
  return { pos, W: MARGINE * 2 + ultima * COL_W, H };
}

/* =====================================================================
   DISEGNO SVG
   ===================================================================== */
const svg = $('svg');
const NS = 'http://www.w3.org/2000/svg';
function el(tag, attr, padre, testo) {
  const e = document.createElementNS(NS, tag);
  for (const [k, v] of Object.entries(attr)) e.setAttribute(k, v);
  if (testo !== undefined) e.textContent = testo;
  if (padre) padre.append(e);
  return e;
}

/** Forma della porta centrata in (x, y), larga ~56: restituisce [path, x dell'uscita]. */
function formaPorta(tipo, x, y) {
  const l = x - 26, t = y - 22, b = y + 22;
  switch (tipo) {
    case 'AND': return [`M${l} ${t}H${x}A22 22 0 0 1 ${x} ${b}H${l}Z`, x + 22];
    case 'OR': return [`M${l} ${t}Q${l + 14} ${y} ${l} ${b}Q${x + 8} ${b} ${x + 26} ${y}Q${x + 8} ${t} ${l} ${t}Z`, x + 26];
    case 'XOR': return [`M${l + 4} ${t}Q${l + 18} ${y} ${l + 4} ${b}Q${x + 10} ${b} ${x + 28} ${y}Q${x + 10} ${t} ${l + 4} ${t}ZM${l - 4} ${t}Q${l + 10} ${y} ${l - 4} ${b}`, x + 28];
    case 'NOT': return [`M${l} ${t + 4}L${x + 16} ${y}L${l} ${b - 4}Z`, x + 26];
    case 'FILO': return [`M${l} ${y}H${x + 26}`, x + 26];
    default: return [`M${l} ${t}h52v44h-52Z`, x + 26];   // '?' ancora da scegliere
  }
}

function disegna() {
  const l = liv();
  const { pos, W, H } = disponi(l);
  const v = P.valuta(l, stato.bit, stato.scelte);
  svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
  svg.replaceChildren();
  const fili = el('g', {}, svg), nodi = el('g', {}, svg);

  const uscitaX = {};      // x da cui parte il filo di ogni nodo
  l.ingressi.forEach((n) => { uscitaX[n] = pos[n].x + 24; });
  l.porte.forEach((p) => { uscitaX[p.id] = formaPorta(tipoVisto(p), pos[p.id].x, pos[p.id].y)[1]; });

  const filo = (daId, x2, y2) => {
    const x1 = uscitaX[daId], y1 = pos[daId].y, mx = (x1 + x2) / 2;
    el('path', { d: `M${x1} ${y1}C${mx} ${y1} ${mx} ${y2} ${x2} ${y2}`, class: `filo ${v[daId] ? 'on' : 'off'}` }, fili);
  };

  // interruttori
  l.ingressi.forEach((n) => {
    const { x, y } = pos[n], on = v[n] === 1;
    const g = el('g', { class: 'cliccabile', tabindex: 0, role: 'switch', 'aria-checked': on, 'aria-label': `Interruttore ${n}` }, nodi);
    el('rect', { x: x - 24, y: y - 14, width: 48, height: 28, rx: 14, fill: on ? '#ffd84d' : '#262e52', stroke: on ? '#e0a800' : '#3a4680', 'stroke-width': 2 }, g);
    el('circle', { cx: on ? x + 10 : x - 10, cy: y, r: 10, fill: on ? '#fff8d6' : '#8e98c7' }, g);
    el('text', { x: x - 34, y: y + 5, class: 'bit', fill: '#e8ecff', 'text-anchor': 'end' }, g, n);
    el('text', { x, y: y + 30, class: 'bit', fill: on ? '#ffd84d' : '#8e98c7' }, g, on ? '1' : '0');
    const cambia = () => { stato.bit[n] = on ? 0 : 1; aggiorna(); };
    g.addEventListener('click', cambia);
    g.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cambia(); } });
  });

  // porte
  l.porte.forEach((p) => {
    const { x, y } = pos[p.id];
    const tipo = tipoVisto(p);
    const [d] = formaPorta(tipo, x, y);
    const on = v[p.id] === 1;
    const n = p.da.length;
    p.da.forEach((daId, j) => filo(daId, x - 26 + (tipo === 'XOR' ? 4 : 0), y + (j - (n - 1) / 2) * (n > 1 ? 20 : 0)));

    const attr = p.tipo === '?' ? { class: 'porta cliccabile', tabindex: 0, role: 'button', 'aria-label': `Porta da scegliere: ${tipo || 'nessuna'}. Tocca per cambiarla` } : { class: 'porta' };
    const g = el('g', attr, nodi);
    if (tipo === 'SCATOLA') { // porta nascosta: si scopre solo risolvendo il livello
      el('path', { d, fill: '#2a1f4d', stroke: '#b48cff', 'stroke-width': 2.5 }, g);
      el('text', { x, y: y + 8, style: 'font-size:24px', fill: '#e3d4ff' }, g, '?');
      el('text', { x, y: y + 38, class: 'nome' }, g, '???');
      return;
    }
    if (tipo === 'FILO') el('path', { d, class: `filo ${on ? 'on' : 'off'}` }, g);
    else el('path', { d, fill: tipo ? (on ? '#4a4420' : '#1c2547') : '#141b33', stroke: on ? '#ffd84d' : '#5cc8ff', 'stroke-dasharray': p.tipo === '?' ? '5 4' : '' }, g);
    if (tipo === 'NOT') el('circle', { cx: x + 21, cy: y, r: 5, fill: '#1c2547', stroke: on ? '#ffd84d' : '#5cc8ff', 'stroke-width': 2.5 }, g);
    if (!tipo) el('text', { x, y: y + 6, style: 'font-size:22px' }, g, '?');
    el('text', { x, y: y + 38, class: 'nome' }, g, p.tipo === '?' ? `${tipo ? P.REGOLE[tipo].nome : '?'} ↻` : tipo);
    if (p.tipo === '?') {
      const cambia = () => {
        const i = p.opzioni.indexOf(stato.scelte[p.id]);
        stato.scelte[p.id] = p.opzioni[(i + 1) % p.opzioni.length];
        aggiorna();
      };
      g.addEventListener('click', cambia);
      g.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cambia(); } });
    }
  });

  // lampadine
  l.uscite.forEach((u) => {
    const { x, y } = pos[u.id], on = v[u.id] === 1;
    filo(u.da, x - 18, y);
    const g = el('g', { class: 'lampadina', role: 'img', 'aria-label': `Lampadina ${on ? 'accesa' : 'spenta'}` }, nodi);
    if (on) el('circle', { cx: x, cy: y, r: 30, fill: 'rgba(255,216,77,.25)' }, g);
    el('circle', { cx: x, cy: y, r: 18, class: 'vetro', fill: on ? '#ffd84d' : '#262e52', stroke: on ? '#fff3b0' : '#3a4680', 'stroke-width': 3 }, g);
    el('rect', { x: x - 8, y: y + 16, width: 16, height: 8, rx: 2, fill: '#8e98c7' }, g);
    el('text', { x, y: y + 5, class: 'bit', fill: on ? '#3a2a00' : '#8e98c7' }, g, on ? '1' : '0');
    if (u.etichetta) el('text', { x, y: y + 40, class: 'bit', fill: '#8e98c7', style: 'font-size:11px' }, g, u.etichetta);
  });
}

/** Cosa disegnare per una porta: la scelta del giocatore, la scatola misteriosa o il tipo vero. */
function tipoVisto(p) {
  if (p.tipo === '?') return stato.scelte[p.id];
  if (p.nascosta && !stato.fatti.has(stato.n)) return 'SCATOLA';
  return p.tipo;
}

/** Una porta compare tra le regole solo dopo il livello che la fa scoprire. */
function scoperta(t) {
  if (P.SCOPERTA[t] === undefined) return true;
  const i = LIVELLI.findIndex((l) => l.orig === P.SCOPERTA[t]);   // posizione in QUESTA sessione
  return i >= 0 && (i < stato.n || (i === stato.n && stato.fatti.has(i)));
}

/* =====================================================================
   PANNELLI: binario, sfida, regole
   ===================================================================== */
function pannelli() {
  const l = liv();
  const v = P.valuta(l, stato.bit, stato.scelte);
  const k = P.chiave(l, stato.bit);
  $('bin-nomi').textContent = l.ingressi.join('');
  $('bin-bits').textContent = k;
  $('bin-dec').textContent = `= ${parseInt(k, 2)} in decimale`;

  const box = $('sfida');
  if (l.tipo === 'accendi') {
    box.innerHTML = `<h3>Obiettivo</h3><p>Accendi ${l.uscite.length > 1 ? 'tutte le lampadine' : 'la lampadina'}. Lampadina adesso: <b>${P.accese(l, v) ? 'ACCESA (1)' : 'spenta (0)'}</b></p>`;
  } else if (l.tipo === 'tutte') {
    const tot = P.soluzioni(l).length;
    box.innerHTML = `<h3>Combinazioni trovate: ${stato.trovate.size}/${tot}</h3>`
      + '<p>Tutte le combinazioni possibili degli interruttori (in verde quelle che accendono):</p><div class="chips">'
      + P.combinazioni(l).map((b) => { const c = P.chiave(l, b); return `<span class="chip ${stato.trovate.has(c) ? 'trovata' : ''} ${c === k ? 'ora' : ''}">${c}</span>`; }).join('')
      + '</div>';
  } else if (l.tipo === 'scegli') {
    const u = l.uscite[0].id;
    box.innerHTML = '<h3>La tabella da rispettare</h3><table><thead><tr>'
      + l.ingressi.map((n) => `<th>${n}</th>`).join('') + '<th>deve</th><th>ora</th></tr></thead><tbody>'
      + P.combinazioni(l).map((b) => {
        const deve = l.vuole(b) ? 1 : 0, ora = P.valuta(l, b, stato.scelte)[u];
        return `<tr class="${P.chiave(l, b) === k ? 'ora' : ''}">` + l.ingressi.map((n) => `<td>${b[n]}</td>`).join('')
          + `<td>${deve}</td><td class="${ora === deve ? 'si' : 'no'}">${ora} ${ora === deve ? '✓' : '✗'}</td></tr>`;
      }).join('') + '</tbody></table><p class="nota">Tocca la porta col punto interrogativo per cambiarla.</p>';
  } else if (l.tipo === 'indovina') {
    const u = l.uscite[0].id, tutte = P.combinazioni(l);
    const pronte = stato.provate.size === tutte.length;
    box.innerHTML = `<h3>Prova tutte le combinazioni: ${stato.provate.size}/${tutte.length}</h3><table><thead><tr>`
      + l.ingressi.map((n) => `<th>${n}</th>`).join('') + '<th>lampadina</th></tr></thead><tbody>'
      + tutte.map((b) => {
        const c = P.chiave(l, b);
        return `<tr class="${c === k ? 'ora' : ''}">` + l.ingressi.map((n) => `<td>${b[n]}</td>`).join('')
          + `<td>${stato.provate.has(c) ? P.valuta(l, b)[u] : '?'}</td></tr>`;
      }).join('') + '</tbody></table>'
      + `<p class="nota">${pronte ? 'Ora rispondi: come si comporta la scatola?' : 'Quando hai provato tutte le righe puoi rispondere.'}</p>`
      + '<div class="risposte">' + l.risposte.map((r, i) => {
        const cls = stato.indovinato && r.giusta ? 'giusta' : i === stato.sbagliata ? 'sbagliata' : '';
        return `<button type="button" class="risposta ${cls}" data-i="${i}" ${pronte && !stato.indovinato ? '' : 'disabled'}>${esc(r.testo)}</button>`;
      }).join('') + '</div>'
      + (stato.sbagliata >= 0 && !stato.indovinato ? '<p class="nota" style="color:var(--ko) !important">No! Rileggi la tabella riga per riga.</p>' : '');
    box.querySelectorAll('.risposta').forEach((b) => b.addEventListener('click', () => {
      const i = Number(b.dataset.i);
      if (l.risposte[i].giusta) stato.indovinato = true; else stato.sbagliata = i;
      aggiorna();
    }));
  } else {
    const ora = P.numero(l, v);
    box.innerHTML = `<h3>Numeri mostrati: ${stato.trovate.size}/${l.numeri.length}</h3>`
      + `<p>Le lampadine dicono <b>${l.uscite.map((x) => v[x.id]).join('')}</b> in binario = <b>${ora}</b>. `
      + `Interruttori accesi: ${l.ingressi.filter((n) => stato.bit[n]).length}. Coincide? 😉</p><div class="chips">`
      + l.numeri.map((n) => `<span class="chip ${stato.trovate.has(String(n)) ? 'trovata' : ''} ${n === ora ? 'ora' : ''}">${n} = ${n.toString(2).padStart(l.uscite.length, '0')}</span>`).join('')
      + '</div>';
  }

  // regole delle porte gia' scoperte (con la loro tabella); nei primi livelli non ce n'e' nessuna
  const usate = new Set(l.porte.flatMap((p) => (p.tipo === '?' ? p.opzioni : [p.tipo])));
  const note = ['AND', 'OR', 'NOT', 'XOR'].filter(scoperta);
  if (!note.length) {
    $('regole').innerHTML = '<p class="nota" style="margin:0 !important">Nessuna regola, per ora: scoprile giocando! 🔍</p>';
    return;
  }
  $('regole').innerHTML = note.map((t) => {
    const r = P.REGOLE[t];
    const righe = t === 'NOT' ? [[0], [1]] : [[0, 0], [0, 1], [1, 0], [1, 1]];
    return `<div class="regola ${usate.has(t) ? 'usata' : ''}"><b>${r.nome} <span style="color:var(--muted)">(${esc(r.it)})</span></b>${esc(r.spiega)}`
      + `<table><tbody>${righe.map((x) => `<tr><td>${x.join(' ')}</td><td>→</td><td>${r.f(x) ? 1 : 0}</td></tr>`).join('')}</tbody></table></div>`;
  }).join('');
}

/* =====================================================================
   PARTITA
   ===================================================================== */
function aggiorna() {
  const l = liv();
  const v = P.valuta(l, stato.bit, stato.scelte);
  if (l.tipo === 'tutte' && P.accese(l, v)) stato.trovate.add(P.chiave(l, stato.bit));
  if (l.tipo === 'numeri') stato.trovate.add(String(P.numero(l, v)));
  if (l.tipo === 'indovina') stato.provate.add(P.chiave(l, stato.bit));
  disegna();
  pannelli();

  const vinto = l.tipo === 'accendi' ? P.accese(l, v)
    : l.tipo === 'tutte' ? stato.trovate.size === P.soluzioni(l).length
    : l.tipo === 'scegli' ? P.sceltaGiusta(l, stato.scelte)
    : l.tipo === 'indovina' ? stato.indovinato
    : l.numeri.every((n) => stato.trovate.has(String(n)));
  if (vinto && !stato.fatti.has(stato.n)) completa();
}

function completa() {
  stato.fatti.add(stato.n);
  storage.set(CHIAVE_FATTI, JSON.stringify([...stato.fatti]));
  disegna();   // la scatola misteriosa si apre
  pannelli();  // e la porta scoperta entra tra le regole
  if (navigator.vibrate) navigator.vibrate([30, 40, 30]);
  toast(stato.fatti.size === LIVELLI.length ? 'Tutti i circuiti risolti! 🏆' : 'Circuito risolto! 💡', `+${PUNTI_LIVELLO} punti`);
  esito();
  disegnaLivelli();
  salva();
}

function apriLivello(i) {
  if (i > 0 && !stato.fatti.has(i - 1)) return;
  stato.n = i;
  storage.set('od_porte_livello', String(i));
  const l = liv();
  stato.bit = Object.fromEntries(l.ingressi.map((n) => [n, (l.iniziale || {})[n] || 0]));
  stato.scelte = {};
  stato.trovate = new Set();
  stato.provate = new Set();
  stato.indovinato = stato.fatti.has(i);
  stato.sbagliata = -1;
  $('l-num').textContent = `Livello ${i + 1}/${LIVELLI.length}`;
  $('l-titolo').textContent = l.titolo;
  $('l-testo').innerHTML = l.testo;
  esito();
  disegnaLivelli();
  aggiorna();
}

function esito() {
  const fatto = stato.fatti.has(stato.n);
  $('l-esito').className = 'esito' + (fatto ? ' ok' : '');
  // la spiegazione arriva solo DOPO: prima si gioca e si ragiona
  $('l-esito').innerHTML = fatto ? '✓ Circuito risolto!' + (liv().svela ? `<br><span style="color:#c9d0f2;font-weight:400">${liv().svela}</span>` : '') : '';
  $('btn-avanti').hidden = !fatto || stato.n >= LIVELLI.length - 1;
}

function disegnaLivelli() {
  const nav = $('livelli');
  nav.replaceChildren();
  LIVELLI.forEach((l, i) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = stato.fatti.has(i) ? '✓' : i + 1;
    b.title = `Livello ${i + 1}: ${l.titolo}`;
    b.classList.toggle('fatto', stato.fatti.has(i));
    b.classList.toggle('corrente', i === stato.n);
    b.disabled = i > 0 && !stato.fatti.has(i - 1);
    b.addEventListener('click', () => apriLivello(i));
    nav.append(b);
  });
  $('hud-punti').textContent = stato.fatti.size * PUNTI_LIVELLO;
}

function toast(testo, sotto) {
  const t = $('toast');
  t.innerHTML = '';
  t.append(testo);
  if (sotto) t.append(Object.assign(document.createElement('small'), { textContent: sotto }));
  t.classList.add('vis');
  clearTimeout(toast.t);
  toast.t = setTimeout(() => t.classList.remove('vis'), 2200);
}

/* ---------- salvataggio: 100 x livelli risolti (vale il record) ---------- */
function avvisoProva() {
  $('avviso').innerHTML = 'Modalità prova: <a href="../index.php">entra all\'Open Day</a> per salvare i punti in classifica.';
  $('avviso').hidden = false;
}
async function salva() {
  const uuid = storage.get('od_uuid');
  if (!uuid) return avvisoProva();
  try {
    const res = await fetch(API, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ uuid, modulo: MODULO, punti: stato.fatti.size * PUNTI_LIVELLO }),
    });
    const d = await res.json().catch(() => ({}));
    if (res.status === 429) { clearTimeout(stato.salvataggio); stato.salvataggio = setTimeout(salva, 2500); return; }
    if (!res.ok || !d.ok) throw new Error(d.errore || `Errore ${res.status}`);
    $('avviso').hidden = true;
  } catch (e) {
    $('avviso').textContent = `Punteggio non salvato: ${e.message}`;
    $('avviso').hidden = false;
  }
}

$('btn-spegni').addEventListener('click', () => { liv().ingressi.forEach((n) => { stato.bit[n] = 0; }); aggiorna(); });
$('btn-avanti').addEventListener('click', () => apriLivello(stato.n + 1));

const primoLibero = LIVELLI.findIndex((l, i) => !stato.fatti.has(i));
const salvato = Number(storage.get('od_porte_livello'));
apriLivello(Number.isInteger(salvato) && salvato < LIVELLI.length && (salvato === 0 || stato.fatti.has(salvato - 1))
  ? salvato : Math.max(0, primoLibero));
if (!storage.get('od_uuid')) avvisoProva();
else if (stato.fatti.size) salva(); // riallinea il server se il telefono ha progressi non salvati
</script>
</body>
</html>
