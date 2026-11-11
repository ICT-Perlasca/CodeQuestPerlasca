<?php
/**
 * Hack the Web(site): il sito del Dottor Quack (che ha rapito le paperelle) va "aggirato"
 * modificandone HTML e CSS con un pannello Ispeziona simulato (assets/js/ispeziona.js).
 * Il sito e' vero HTML dentro #sito: funziona anche il vero F12 del browser.
 *
 * Livelli in assets/js/hack-livelli.js, 100 punti l'uno. Quali livelli si giocano dipende
 * dalla sessione scelta in admin.php (TIPI_GIORNATA['giochi']['hack_livelli'] in inc/db.php).
 * Livello segreto ★ senza punti: la parola e' nel commento in fondo a questa pagina.
 * Senza login si gioca in "modalita' prova": progressi solo nel browser.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/db.php';

$livelli = sessione_attivita()['giochi']['hack_livelli'];
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<title>Hack the Web(site) · Open Day Perlasca</title>
<style>
  :root {
    --bg:#0b1020; --panel:#141b33; --panel-2:#1c2547; --line:#2a3566; --text:#e8ecff; --muted:#8e98c7;
    --on:#ffd84d; --ok:#3ddc97; --ko:#ff5c7a; --accent:#5cc8ff; --radius:16px;
    --mono: ui-monospace, "SF Mono", "Cascadia Mono", "Roboto Mono", Menlo, Consolas, monospace;
  }
  * { box-sizing:border-box; }
  html, body { margin:0; }
  body { min-height:100dvh; background:radial-gradient(120% 60% at 50% 0%, #17204a 0%, var(--bg) 60%) fixed, var(--bg);
         color:var(--text); font:16px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; -webkit-tap-highlight-color:transparent; }
  /* attenzione: niente regole su button/input/p generici, altrimenti cambierebbero anche il sito del Dottor Quack */
  .ui button { font:inherit; color:inherit; touch-action:manipulation; cursor:pointer; }
  .ui [hidden] { display:none !important; }

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
  .livelli button.segreto { border-style:dashed; color:var(--on); }
  .livelli button.segreto.fatto { background:var(--on); border-color:var(--on); color:#2a2000; }
  .pill { background:var(--panel); border:1px solid var(--line); border-radius:999px; padding:4px 12px; font-family:var(--mono); font-size:.9rem; white-space:nowrap; }
  .pill b { color:var(--on); }

  .main { display:grid; gap:14px; padding:14px 16px max(16px, env(safe-area-inset-bottom)); max-width:1300px; margin:0 auto; }
  @media (min-width:960px) { .main { grid-template-columns:minmax(0, 1fr) minmax(0, 1fr); align-items:start; } }
  .col { display:grid; gap:12px; min-width:0; }
  .card { background:var(--panel); border:1px solid var(--line); border-radius:var(--radius); padding:14px 16px; }
  .missione h2 { margin:0 0 6px; font-size:1.15rem; }
  .missione h2 small { color:var(--muted); font-weight:600; font-size:.85rem; margin-right:6px; }
  .missione p { margin:0; color:#c9d0f2; }
  .missione b { color:var(--on); }
  .missione code { font-family:var(--mono); font-size:.88em; background:#0e1430; border:1px solid var(--line); border-radius:5px; padding:0 4px; color:#f29766; }
  .missione small { color:var(--muted); }
  .lista { list-style:none; margin:10px 0 0; padding:0; display:grid; gap:4px; }
  .lista li::before { content:"⬜ "; }
  .lista li.ok::before { content:"✅ "; }
  .lista li.ok { color:var(--ok); }
  .aiuti { margin:10px 0 0; padding:0 0 0 1.2em; color:#c9d0f2; font-size:.92rem; display:grid; gap:4px; }
  .aiuti li::marker { content:"💡 "; }
  .esito { margin-top:10px !important; font-weight:700; }
  .esito.ok { color:var(--ok) !important; }
  .comandi { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
  .btn { border:0; border-radius:12px; padding:10px 16px; font-weight:700; font-size:.95rem; }
  .btn:active { transform:scale(.97); }
  .btn.secondario { background:var(--panel-2); border:1px solid var(--line); }
  .btn.primario { background:var(--accent); color:#04121c; font-weight:800; }
  .avviso { font-size:.88rem; color:var(--muted); margin:0; }
  .avviso a { color:var(--accent); }
  .f12 { font-size:.85rem; color:var(--muted); margin:0; }
  .f12 kbd { font-family:var(--mono); background:var(--panel-2); border:1px solid var(--line); border-bottom-width:3px; border-radius:5px; padding:0 5px; color:var(--text); }

  /* ---------- il browser finto ---------- */
  .browser { border-radius:12px; overflow:hidden; border:1px solid var(--line); background:#dee1e6; }
  .barra-url { display:flex; align-items:center; gap:8px; padding:7px 10px; background:#dee1e6; color:#333; }
  .pallini { display:flex; gap:5px; }
  .pallini i { width:11px; height:11px; border-radius:50%; background:#ff5f57; }
  .pallini i:nth-child(2) { background:#febc2e; } .pallini i:nth-child(3) { background:#28c840; }
  .url { flex:1; min-width:0; background:#fff; border-radius:999px; padding:3px 12px; font:13px/1.4 system-ui, sans-serif; color:#333;
         white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .ricarica { border:0; background:none; font-size:1.1rem; padding:0 4px; color:#444 !important; }
  .msg-sito { padding:8px 12px; font:600 14px/1.35 system-ui, sans-serif; background:#fff3cd; color:#5c4400; border-top:1px solid #e6d28a; }
  .msg-sito.ko { background:#ffe1e6; color:#7a0019; border-color:#f3a6b5; }
  .msg-sito.ok { background:#d8f8e9; color:#064d2f; border-color:#8fdcb8; }
  .cornice { position:relative; background:#fff; }
  #sito { position:relative; height:clamp(260px, 42vh, 420px); overflow:auto; }
  #sito.dt-picking, #sito.dt-picking * { cursor:crosshair !important; }

  /* ---------- il pannello Ispeziona (stile DevTools) ---------- */
  .dt { --dtb:#202124; --dtl:#3c4043; --dtt:#e8eaed; --tag:#5db0d7; --an:#9bbbdc; --av:#f29766; --cm:#6a9955; --sel:#073655;
        background:var(--dtb); color:var(--dtt); border:1px solid var(--dtl); border-radius:12px; overflow:hidden;
        font:12.5px/1.55 var(--mono); }
  .dt button { font:inherit; color:inherit; cursor:pointer; touch-action:manipulation; }
  .dt-tabs { display:flex; align-items:stretch; border-bottom:1px solid var(--dtl); background:#292a2d; font-family:system-ui, sans-serif; font-size:13px; }
  .dt-picker { background:none; border:0; border-right:1px solid var(--dtl); padding:8px 11px; color:#9aa0a6 !important; display:grid; place-items:center; }
  .dt-picker.attivo { color:#8ab4f8 !important; background:#35363a; }
  .dt-tab { background:none; border:0; border-bottom:2px solid transparent; padding:8px 14px; color:#9aa0a6 !important; position:relative; }
  .dt-tab.attivo { color:var(--dtt) !important; border-bottom-color:#8ab4f8; }
  .dt-tab.errore::after { content:""; position:absolute; top:7px; right:5px; width:7px; height:7px; border-radius:50%; background:#f28b82; }
  .dt-tab.lampeggia { animation:lampeggia 1s infinite; }
  @keyframes lampeggia { 50% { background:#3c4a6b; } }
  .dt-albero { max-height:min(300px, 40vh); min-height:120px; overflow:auto; padding:6px 0; }
  .dt-riga { padding:1px 8px 1px calc(4px + var(--d) * 16px); white-space:pre-wrap; word-break:break-word; cursor:default; }
  .dt-riga:hover { background:#2a2b2e; }
  .dt-riga.sel { background:var(--sel); }
  .dt-freccia { display:inline-block; width:14px; color:#9aa0a6; cursor:pointer; }
  .dt-tag { color:var(--tag); } .dt-an { color:var(--an); } .dt-av { color:var(--av); } .dt-cm, .dt-cm .dt-testo { color:var(--cm); }
  .dt-puntini { color:#9aa0a6; background:#3c4043; border-radius:3px; padding:0 3px; }
  .dt-riga.sel .dt-mod { text-decoration:underline dotted #8ab4f8; text-underline-offset:3px; cursor:text; }
  .dt-edit { font:inherit; color:#fff; background:#1a1a1a; border:1px solid #8ab4f8; border-radius:3px; padding:0 3px; max-width:100%; outline:none; }
  .dt-barra { display:flex; align-items:center; gap:6px; padding:5px 8px; border-top:1px solid var(--dtl); border-bottom:1px solid var(--dtl); background:#292a2d; }
  .dt-percorso { flex:1; min-width:0; color:#9aa0a6; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .dt-btn { background:#35363a; border:1px solid var(--dtl); border-radius:6px; padding:2px 8px; font-family:system-ui, sans-serif !important; font-size:12px !important; }
  .dt-btn:disabled { opacity:.4; cursor:not-allowed; }
  .dt-stili-tit { padding:5px 10px; font:600 12px system-ui, sans-serif; color:#9aa0a6; border-bottom:1px solid var(--dtl); }
  .dt-stili { max-height:min(320px, 45vh); overflow:auto; }
  .dt-regola { padding:6px 10px; border-bottom:1px solid var(--dtl); }
  .dt-sel span { color:#e8eaed; }
  .dt-sel small { float:right; color:#9aa0a6; font-family:system-ui, sans-serif; }
  .dt-decl { padding-left:4px; }
  .dt-decl input[type=checkbox] { margin:0 6px 0 0; vertical-align:-2px; width:13px; height:13px; accent-color:#8ab4f8; }
  .dt-prop { color:#9cdcfe; cursor:text; } .dt-val { color:#e8eaed; cursor:text; }
  .dt-decl.off .dt-prop, .dt-decl.off .dt-val { text-decoration:line-through; opacity:.55; }
  .dt-add { color:#9aa0a6; cursor:text; padding:0 0 0 17px; }
  .dt-add:hover { color:#8ab4f8; }
  .dt-vuoto { color:#9aa0a6; margin:8px 10px; font-family:system-ui, sans-serif; }
  .dt-log { height:min(320px, 45vh); overflow:auto; padding:4px 0; }
  .dt-msg { padding:2px 10px; border-bottom:1px solid #2c2d30; white-space:pre-wrap; word-break:break-word; }
  .dt-msg.in::before { content:"› "; color:#8ab4f8; }
  .dt-msg.out { color:#c4c7c5; } .dt-msg.out::before { content:"← "; color:#9aa0a6; }
  .dt-msg.err { color:#f28b82; background:#290000; }
  .dt-msg.warn { color:#fdd663; background:#332b00; }
  .dt-prompt { display:flex; gap:6px; align-items:center; padding:6px 10px; border-top:1px solid var(--dtl); }
  .dt-prompt span { color:#8ab4f8; }
  .dt-cmd { flex:1; min-width:0; font:inherit; color:var(--dtt); background:none; border:0; outline:none; }
  .dt-evidenzia { position:absolute; display:none; pointer-events:none; background:rgba(111,168,220,.35); outline:1px solid #6fa8dc; z-index:3; }
  .dt-evidenzia span { position:absolute; left:0; bottom:100%; margin-bottom:2px; background:#202124; color:#e8eaed; font:11px/1.4 var(--mono);
                       padding:1px 5px; border-radius:3px; white-space:nowrap; }
  .dt-evidenzia.sotto span { bottom:auto; top:100%; margin:2px 0 0; }

  .toast { position:fixed; left:50%; bottom:24px; transform:translate(-50%, 20px); background:var(--ok); color:#04241a;
           font-weight:800; padding:12px 18px; border-radius:12px; opacity:0; pointer-events:none; transition:.25s; z-index:5; text-align:center; }
  .toast.vis { opacity:1; transform:translate(-50%, 0); }
  .toast small { display:block; font-weight:600; }
</style>
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

<script src="../assets/js/ispeziona.js"></script>
<script src="../assets/js/hack-livelli.js"></script>
<script>
'use strict';

const H = window.HackLivelli;
const NUMERI = <?= json_encode(array_values($livelli)) ?>;   // livelli di questa sessione (admin.php)
const LIVELLI = NUMERI.map((n) => ({ n, ...H.LIVELLI[n - 1] })).filter((l) => l.titolo);
const PUNTI_LIVELLO = 100;
const API = '../api/save_score.php';
const MODULO = 'hack_website';

const $ = (id) => document.getElementById(id);
const storage = {
  get(k) { try { return localStorage.getItem(k); } catch { return null; } },
  set(k, v) { try { localStorage.setItem(k, v); } catch { /* ignora */ } },
};

// progressi per profilo: sullo stesso telefono due nickname diversi non si mescolano
const CHIAVE_FATTI = `od_hack_fatti_${storage.get('od_uuid') || 'prova'}`;
const stato = {
  i: 0,              // indice in LIVELLI, oppure -1 = livello segreto
  fatti: new Set(JSON.parse(storage.get(CHIAVE_FATTI) || '[]')), // numeri dei livelli superati
  segreto: storage.get('od_hack_segreto') === '1',
  aiuti: 0,
  montato: null,     // livello attualmente nel riquadro (per smonta e per ignorare vittorie vecchie)
  salvataggio: 0,
};
const sito = $('sito');
const foglioSito = $('sito-css');
const livello = () => (stato.i < 0 ? H.SEGRETO : LIVELLI[stato.i]);
const fatto = (l) => (l === H.SEGRETO ? stato.segreto : stato.fatti.has(l.n));

/** CSS del livello: "body" = il riquadro del sito, il resto vale solo dentro il riquadro. */
function prefissa(css) {
  return css.replace(/([^{}]+)\{/g, (m, sel) => sel.split(',').map((x) => {
    x = x.trim();
    return /^body\b/.test(x) ? '#sito' + x.slice(4) : `#sito ${x}`;
  }).join(', ') + ' {');
}
const mostraSelettore = (sel) => sel.replace(/#sito\s+/g, '').replace(/#sito/g, 'body');

const dt = Ispeziona.crea({
  radice: sito,
  foglio: () => foglioSito.sheet,
  pannello: $('ispeziona'),
  cornice: $('cornice'),
  mostraSelettore,
  onModifica: () => controlla(),
});

/* =====================================================================
   LIVELLI
   ===================================================================== */
function monta() {
  const vecchio = stato.montato;
  if (vecchio && vecchio.smonta) vecchio.smonta();
  const l = livello();
  stato.montato = l;
  foglioSito.textContent = prefissa(H.BASE + l.css);
  sito.innerHTML = l.html;
  sito.scrollTop = 0;
  $('url').textContent = l.url;
  messaggio('');
  const api = {
    vinci: () => { if (stato.montato === l) vinci(l); },
    avviso: (t, tipo) => { if (stato.montato === l) messaggio(t, tipo); },
  };
  if (l === H.SEGRETO) H.lucchetto(sito, api, parolaSegreta() || '\u0000');
  else if (l.monta) l.monta(sito, api);
  dt.reset();
  dt.lampeggiaConsole(!!l.console && !fatto(l));
}

/** La parola del livello segreto sta in un commento fuori dal sito (in fondo a questa pagina). */
function parolaSegreta() {
  const it = document.createNodeIterator(document.body, NodeFilter.SHOW_COMMENT);
  for (let c = it.nextNode(); c; c = it.nextNode()) {
    const m = /parola segreta[^:]*:\s*(\S+)/i.exec(c.data);
    if (m) return m[1].toLowerCase();
  }
  return '';
}

function apriLivello(i) {
  if (i > 0 && !stato.fatti.has(LIVELLI[i - 1].n)) return;
  stato.i = i;
  if (i >= 0) storage.set('od_hack_livello', String(i));
  stato.aiuti = 0;
  const l = livello();
  $('l-num').textContent = i < 0 ? 'Livello ★' : `Livello ${i + 1}/${LIVELLI.length}`;
  $('l-titolo').textContent = l.titolo;
  $('l-testo').innerHTML = l.testo;
  monta();
  mostraAiuti();
  aggiornaEsito();
  disegnaLivelli();
}

function messaggio(testo, tipo = '') {
  const el = $('msg-sito');
  el.hidden = !testo;
  el.className = 'msg-sito' + (tipo ? ' ' + tipo : '');
  el.textContent = testo;
}

function mostraAiuti() {
  const l = livello();
  const ol = $('l-aiuti');
  ol.innerHTML = l.aiuti.slice(0, stato.aiuti).map((a) => `<li>${a}</li>`).join('');
  ol.hidden = stato.aiuti === 0;
  $('btn-aiuto').hidden = stato.aiuti >= l.aiuti.length;
}

function aggiornaEsito() {
  const l = livello();
  const ok = fatto(l);
  const out = $('l-esito');
  out.className = 'esito' + (ok ? ' ok' : '');
  out.textContent = ok ? (l === H.SEGRETO ? '★ Sei un vero hacker!' : '✓ Livello superato!') : '';
  $('btn-avanti').hidden = !ok || stato.i < 0 || stato.i >= LIVELLI.length - 1;
}

/** Chiamato di continuo e dopo ogni modifica: livelli che si vincono "guardando" la pagina. */
function controlla() {
  const l = livello();
  if (stato.montato !== l) return;
  if (l.lista) {
    const voci = l.lista(sito);
    const ul = $('l-lista');
    ul.hidden = false;
    ul.innerHTML = voci.map((v) => `<li class="${v.ok ? 'ok' : ''}">${v.testo}</li>`).join('');
    if (voci.every((v) => v.ok)) vinci(l);
  } else {
    $('l-lista').hidden = true;
    if (l.controlla && l.controlla(sito)) vinci(l);
  }
}

function vinci(l) {
  if (fatto(l)) return;
  if (l === H.SEGRETO) {
    stato.segreto = true;
    storage.set('od_hack_segreto', '1');
    toast('★ Livello segreto! Sei un vero hacker 🕶️', 'Hai usato gli strumenti veri del browser');
  } else {
    stato.fatti.add(l.n);
    storage.set(CHIAVE_FATTI, JSON.stringify([...stato.fatti]));
    const tutti = LIVELLI.every((x) => stato.fatti.has(x.n));
    toast(tutti ? 'Tutti i livelli superati! Le paperelle sono libere 🦆🏆' : 'Livello superato! 🎉', `+${PUNTI_LIVELLO} punti`);
    salva();
  }
  if (navigator.vibrate) navigator.vibrate([30, 40, 30]);
  messaggio('🎉 Ce l\'hai fatta! Ricorda: hai cambiato solo la TUA copia della pagina. Premi ↻ e torna tutto come prima.', 'ok');
  dt.lampeggiaConsole(false);
  aggiornaEsito();
  disegnaLivelli();
}

function disegnaLivelli() {
  const nav = $('livelli');
  nav.replaceChildren();
  const bottone = (testo, titolo, cls, disab, click) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = testo;
    b.title = titolo;
    b.className = cls;
    b.disabled = disab;
    b.addEventListener('click', click);
    nav.append(b);
  };
  LIVELLI.forEach((l, i) => {
    const ok = stato.fatti.has(l.n);
    bottone(ok ? '✓' : i + 1, `Livello ${i + 1}: ${l.titolo}`, (ok ? 'fatto' : '') + (i === stato.i ? ' corrente' : ''),
      i > 0 && !stato.fatti.has(LIVELLI[i - 1].n), () => apriLivello(i));
  });
  bottone('★', 'Livello segreto (serve il vero F12)', 'segreto' + (stato.segreto ? ' fatto' : '') + (stato.i < 0 ? ' corrente' : ''), false, () => apriLivello(-1));
  $('hud-punti').textContent = punti();
}

const punti = () => LIVELLI.filter((l) => stato.fatti.has(l.n)).length * PUNTI_LIVELLO;

function toast(testo, sotto) {
  const el = $('toast');
  el.innerHTML = '';
  el.append(testo);
  if (sotto) el.append(Object.assign(document.createElement('small'), { textContent: sotto }));
  el.classList.add('vis');
  clearTimeout(toast.t);
  toast.t = setTimeout(() => el.classList.remove('vis'), 2600);
}

/* ---------- salvataggio: il punteggio e' 100 x livelli superati (vale il record) ---------- */
function modalitaProva() {
  $('avviso').innerHTML = 'Modalità prova: <a href="../index.php">entra all\'Open Day</a> per salvare i punti in classifica.';
  $('avviso').hidden = false;
}
async function salva() {
  const uuid = storage.get('od_uuid');
  if (!uuid) { modalitaProva(); return; }
  const avviso = $('avviso');
  try {
    const res = await fetch(API, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ uuid, modulo: MODULO, punti: punti() }),
    });
    const d = await res.json().catch(() => ({}));
    if (res.status === 429) { clearTimeout(stato.salvataggio); stato.salvataggio = setTimeout(salva, 2500); return; } // due livelli di fila
    if (!res.ok || !d.ok) throw new Error(d.errore || `Errore ${res.status}`);
    avviso.hidden = true;
  } catch (e) {
    avviso.textContent = `Punteggio non salvato: ${e.message}`;
    avviso.hidden = false;
  }
}

$('btn-aiuto').addEventListener('click', () => { stato.aiuti++; mostraAiuti(); });
$('btn-avanti').addEventListener('click', () => apriLivello(stato.i + 1));
$('btn-ricarica').addEventListener('click', () => {
  monta();
  messaggio('↻ Pagina ricaricata: tutte le tue modifiche sono sparite. Erano solo nella tua copia della pagina, il sito vero sta sul server!');
});
setInterval(controlla, 300);

const primoLibero = LIVELLI.findIndex((l) => !stato.fatti.has(l.n));
const salvato = Number(storage.get('od_hack_livello'));
apriLivello(Number.isInteger(salvato) && salvato >= 0 && salvato < LIVELLI.length && (salvato === 0 || stato.fatti.has(LIVELLI[salvato - 1].n))
  ? salvato : Math.max(0, primoLibero));
if (!storage.get('od_uuid')) modalitaProva();
else if (stato.fatti.size) salva(); // riallinea il server se il telefono ha progressi non salvati
</script>
<!--
  🕵️ Complimenti, hai trovato il commento nascosto: stai usando gli strumenti veri del browser!
  La parola segreta del livello ★ è: PAPERHACKER
-->
</body>
</html>
