<?php
/**
 * Classifica live a tutto schermo per proiettore/LIM.
 * Pubblica e in sola lettura: interroga api/get_leaderboard.php ogni 4 secondi.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';

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
<style>
  :root {
    --bg: #0b1020; --panel: #141b33; --panel-2: #1c2547; --line: #2a3566;
    --text: #e8ecff; --muted: #8e98c7; --on: #ffd84d; --ok: #3ddc97; --ko: #ff5c7a; --accent: #5cc8ff;
    --oro: #ffd84d; --argento: #c9d3f0; --bronzo: #e09a5b;
    --mono: ui-monospace, "SF Mono", "Cascadia Mono", "Roboto Mono", Menlo, Consolas, monospace;
    --riga: clamp(44px, 7.2vh, 84px);
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; height: 100%; }
  body {
    background: radial-gradient(90% 70% at 30% 0%, #1a2456 0%, var(--bg) 65%) fixed, var(--bg);
    color: var(--text); font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    overflow: hidden;
  }
  .schermo {
    height: 100dvh; display: grid; grid-template-rows: auto 1fr; gap: 2vh;
    padding: 2.5vh 2.5vw;
  }

  /* ---------- intestazione ---------- */
  header { display: flex; align-items: center; gap: 2vw; }
  header h1 { margin: 0; font-size: clamp(1.4rem, 3.4vw, 3.4rem); line-height: 1.05; letter-spacing: -.01em; }
  header h1 span { color: var(--on); }
  header p { margin: .3em 0 0; color: var(--muted); font-size: clamp(.8rem, 1.3vw, 1.3rem); }
  .live { margin-left: auto; display: flex; align-items: center; gap: 1.5vw; font-size: clamp(.9rem, 1.4vw, 1.4rem); }
  .badge {
    display: inline-flex; align-items: center; gap: .5em; padding: .35em .9em; border-radius: 999px;
    background: var(--panel); border: 1px solid var(--line); font-weight: 700; white-space: nowrap;
  }
  .badge .dot { width: .7em; height: .7em; border-radius: 50%; background: var(--ok); animation: pulse 1.6s infinite; }
  .badge.offline .dot { background: var(--ko); animation: none; }
  .badge b { font-family: var(--mono); color: var(--on); }
  #btn-fs {
    font: inherit; font-size: .85em; color: var(--muted); background: transparent;
    border: 1px solid var(--line); border-radius: 999px; padding: .35em .9em; cursor: pointer;
  }
  :fullscreen #btn-fs { display: none; }

  /* ---------- griglia principale ---------- */
  main { display: grid; grid-template-columns: 2.1fr 1fr; gap: 2.5vw; min-height: 0; }
  .colonna { display: grid; align-content: start; gap: 1.6vh; min-height: 0; }
  h2 { margin: 0; font-size: clamp(.9rem, 1.5vw, 1.5rem); color: var(--muted); text-transform: uppercase; letter-spacing: .1em; }

  /* ---------- top 10 ---------- */
  #top { list-style: none; margin: 0; padding: 0; display: grid; gap: 1vh; position: relative; }
  .riga {
    height: var(--riga); display: grid; grid-template-columns: 2.2em 1.6em 1fr auto; align-items: center; gap: 1vw;
    padding: 0 1.4vw; border-radius: 14px; background: var(--panel); border: 1px solid var(--line);
    font-size: calc(var(--riga) * .42);
  }
  .riga .pos { font-family: var(--mono); font-weight: 800; color: var(--muted); text-align: center; }
  .riga .av { font-size: 1.15em; text-align: center; }
  .riga .nick { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .riga .pt { font-family: var(--mono); font-weight: 800; color: var(--on); }
  .riga .pt small { font-size: .55em; color: var(--muted); margin-left: .3em; }
  .riga.p1 { border-color: var(--oro); background: linear-gradient(90deg, rgba(255, 216, 77, .2), var(--panel) 60%); }
  .riga.p2 { border-color: var(--argento); background: linear-gradient(90deg, rgba(201, 211, 240, .14), var(--panel) 60%); }
  .riga.p3 { border-color: var(--bronzo); background: linear-gradient(90deg, rgba(224, 154, 91, .16), var(--panel) 60%); }
  .riga.p1 .pos { color: var(--oro); } .riga.p2 .pos { color: var(--argento); } .riga.p3 .pos { color: var(--bronzo); }
  .riga.nuovo { animation: entra .7s cubic-bezier(.2, 1.2, .4, 1), bagliore 2.6s ease-out; }
  .riga .pt.cambiato { animation: pop .6s ease-out; }
  .vuoto {
    padding: 4vh 2vw; text-align: center; color: var(--muted); font-size: clamp(1rem, 2vw, 2rem);
    border: 2px dashed var(--line); border-radius: 16px;
  }

  /* ---------- colonna destra ---------- */
  .box { background: var(--panel); border: 1px solid var(--line); border-radius: 16px; padding: 2vh 1.4vw; display: grid; gap: 1.2vh; }
  #ultimi { list-style: none; margin: 0; padding: 0; display: grid; gap: .9vh; }
  #ultimi li {
    display: flex; align-items: center; gap: .7em; font-size: clamp(.9rem, 1.6vw, 1.6rem);
    padding: .35em .5em; border-radius: 10px; background: var(--panel-2);
  }
  #ultimi li span:nth-child(2) { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  #ultimi li.nuovo { animation: entra .6s ease-out; }
  .unisciti { text-align: center; }
  .unisciti p { margin: 0; color: var(--muted); font-size: clamp(.85rem, 1.4vw, 1.4rem); }
  .unisciti .url { font-family: var(--mono); font-weight: 800; font-size: clamp(1rem, 2vw, 2.1rem); color: var(--accent); word-break: break-all; }
  .unisciti .pwd { font-family: var(--mono); font-weight: 800; font-size: clamp(1rem, 2vw, 2.1rem); color: var(--on); }

  /* ---------- banner podio ---------- */
  .banner {
    position: fixed; left: 50%; top: 50%; transform: translate(-50%, -50%) scale(.6); opacity: 0; pointer-events: none;
    padding: 3vh 4vw; border-radius: 24px; text-align: center; background: var(--panel-2); border: 3px solid var(--on);
    box-shadow: 0 0 80px rgba(255, 216, 77, .45); transition: opacity .3s, transform .45s cubic-bezier(.2, 1.4, .4, 1);
    font-size: clamp(1.4rem, 4vw, 4.2rem); font-weight: 800; max-width: 90vw;
  }
  .banner.vis { opacity: 1; transform: translate(-50%, -50%) scale(1); }
  .banner small { display: block; font-size: .45em; color: var(--muted); font-weight: 600; margin-top: .4em; }

  @keyframes pulse { 50% { opacity: .35; } }
  @keyframes entra { from { opacity: 0; transform: translateX(8vw); } to { opacity: 1; transform: none; } }
  @keyframes bagliore { 0%, 40% { box-shadow: 0 0 0 3px var(--accent), 0 0 40px rgba(92, 200, 255, .6); } 100% { box-shadow: none; } }
  @keyframes pop { 40% { transform: scale(1.25); color: var(--ok); } }

  /* schermi stretti (anteprima da telefono): una colonna e scroll */
  @media (max-width: 760px), (orientation: portrait) {
    body { overflow: auto; }
    .schermo { height: auto; min-height: 100dvh; padding: 16px; }
    header { flex-wrap: wrap; }
    .live { margin-left: 0; }
    main { grid-template-columns: 1fr; }
    :root { --riga: 52px; }
  }
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation: none !important; transition: none !important; }
  }
</style>
</head>
<body>
<div class="schermo">
  <header>
    <div>
      <h1>🏆 Classifica <span>Junior Hacker</span></h1>
      <p>Open Day Informatica e Telecomunicazioni · IIS "Giacomo Perlasca"</p>
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
        <p>password</p>
        <span class="pwd"><?= $e(OSPITE_PASS) ?></span>
      </div>
    </aside>
  </main>
</div>

<div class="banner" id="banner" role="status"></div>

<script src="assets/js/avatar.js"></script>
<script>
'use strict';
const POLL_MS = 4000;
const AVATAR = window.AVATAR;
const MOTO_RIDOTTO = matchMedia('(prefers-reduced-motion: reduce)').matches;

const $ = (id) => document.getElementById(id);
const righe = new Map();       // id partecipante -> <li> della top 10
let posizioniPrima = new Map(); // id -> posizione al giro precedente
let ultimiVisti = new Set();
let ultimoJson = '';
let primoGiro = true;

const emoji = (k) => AVATAR[k] || '🤖';

function creaRiga() {
  const li = document.createElement('li');
  li.className = 'riga';
  li.innerHTML = '<span class="pos"></span><span class="av"></span><span class="nick"></span><span class="pt"></span>';
  return li;
}

/* Top 10 con animazione FLIP: le righe esistenti scivolano nella nuova posizione */
function renderTop(top) {
  const lista = $('top');
  const yPrima = new Map();
  righe.forEach((li, id) => yPrima.set(id, li.getBoundingClientRect().top));

  const ids = new Set(top.map((p) => p.id));
  righe.forEach((li, id) => { if (!ids.has(id)) { li.remove(); righe.delete(id); } });

  const posizioniOra = new Map();
  top.forEach((p, i) => {
    let li = righe.get(p.id);
    const nuovo = !li;
    if (nuovo) { li = creaRiga(); righe.set(p.id, li); }

    const pt = li.querySelector('.pt');
    const puntiPrima = li.dataset.punti;
    li.className = `riga${i < 3 ? ` p${i + 1}` : ''}`;
    li.querySelector('.pos').textContent = i + 1;
    li.querySelector('.av').textContent = emoji(p.avatar);
    li.querySelector('.nick').textContent = p.nickname;
    pt.innerHTML = '';
    pt.append(String(p.punti), Object.assign(document.createElement('small'), { textContent: 'pt' }));
    li.dataset.punti = p.punti;
    lista.append(li); // riordina il DOM

    if (!primoGiro) {
      if (nuovo) li.classList.add('nuovo');
      else if (puntiPrima !== String(p.punti)) {
        pt.classList.remove('cambiato'); void pt.offsetWidth; pt.classList.add('cambiato');
      }
      const prima = posizioniPrima.get(p.id);
      if (i < 3 && (prima === undefined || prima > i)) annunciaPodio(p, i);
    }
    posizioniOra.set(p.id, i);
  });

  if (!MOTO_RIDOTTO) {
    righe.forEach((li, id) => {
      if (!yPrima.has(id)) return;
      const dy = yPrima.get(id) - li.getBoundingClientRect().top;
      if (dy) li.animate([{ transform: `translateY(${dy}px)` }, { transform: 'none' }], { duration: 650, easing: 'cubic-bezier(.2,.9,.3,1)' });
    });
  }

  posizioniPrima = posizioniOra;
  $('vuoto').hidden = top.length > 0;
}

function renderUltimi(ultimi) {
  const ul = $('ultimi');
  ul.innerHTML = '';
  for (const p of ultimi) {
    const li = document.createElement('li');
    if (!primoGiro && !ultimiVisti.has(p.id)) li.className = 'nuovo';
    const av = document.createElement('span'); av.textContent = emoji(p.avatar);
    const nick = document.createElement('span'); nick.textContent = p.nickname;
    li.append(av, nick);
    ul.append(li);
  }
  ultimiVisti = new Set(ultimi.map((p) => p.id));
}

/* Banner centrale quando qualcuno entra o sale sul podio (uno alla volta) */
const codaBanner = [];
let bannerAttivo = false;
const MEDAGLIE = ['🥇', '🥈', '🥉'];

function annunciaPodio(p, pos) {
  codaBanner.push({ testo: `${MEDAGLIE[pos]} ${emoji(p.avatar)} ${p.nickname}`, sotto: pos === 0 ? 'è il nuovo primo in classifica!' : `sale al ${pos + 1}° posto!` });
  if (!bannerAttivo) prossimoBanner();
}

function prossimoBanner() {
  const b = codaBanner.shift();
  const el = $('banner');
  if (!b) { bannerAttivo = false; return; }
  bannerAttivo = true;
  el.textContent = b.testo;
  el.append(Object.assign(document.createElement('small'), { textContent: b.sotto }));
  el.classList.add('vis');
  setTimeout(() => { el.classList.remove('vis'); setTimeout(prossimoBanner, 500); }, 3200);
}

function statoConnessione(online) {
  $('stato').classList.toggle('offline', !online);
  $('stato-testo').textContent = online ? 'LIVE' : 'riconnessione…';
}

async function aggiorna() {
  try {
    const res = await fetch('api/get_leaderboard.php', { cache: 'no-cache' });
    if (!res.ok) throw new Error(res.status);
    const testo = await res.text();
    if (testo !== ultimoJson) {
      const data = JSON.parse(testo);
      renderTop(data.top);
      renderUltimi(data.ultimi);
      $('totale').textContent = data.partecipanti;
      ultimoJson = testo;
      primoGiro = false;
    }
    statoConnessione(true);
  } catch {
    statoConnessione(false);
  } finally {
    setTimeout(aggiorna, POLL_MS);
  }
}

function schermoIntero() {
  if (!document.fullscreenElement) document.documentElement.requestFullscreen?.().catch(() => {});
}
$('btn-fs').addEventListener('click', schermoIntero);
document.addEventListener('keydown', (e) => { if (e.key === 'f' || e.key === 'F') schermoIntero(); });

aggiorna();
</script>
</body>
</html>
