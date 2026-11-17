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
    const res = await fetch('api/get_leaderboard.php?s=' + OD.sessioneId, { cache: 'no-cache' });
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
