'use strict';

const P = window.PorteLogiche;
// quali livelli si giocano: dalla sessione in admin (Open Day: scatole misteriose + livelli difficili)
const NUMERI = OD.numeri;
const LIVELLI = NUMERI.map((n) => ({ ...P.LIVELLI[n - 1], orig: n - 1 })).filter((l) => l.tipo);
const N_LIVELLI = LIVELLI.length;
const PUNTI_LIVELLO = 100;
const API = '../api/save_score.php';
const MODULO = 'porte_logiche';
const SBLOCCA_TUTTO = OD.sbloccaTutto; // livelli tutti aperti (scelto per sessione in admin)

const $ = (id) => document.getElementById(id);
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
/* ---------- guida introduttiva (compare la prima volta, poi col pulsante ?) ---------- */
// non spiega le singole porte: nei primi livelli si scoprono giocando
Guida.crea({
  chiave: 'porte_logiche',
  bottone: document.getElementById('btn-guida'),
  slide: [
    { html: `<div class="grande">🔌</div><h2>Interruttori e corrente</h2>
      <p>Ogni interruttore è un <b>bit</b>: <b>1</b> = passa corrente, <b>0</b> = niente corrente. Se la corrente arriva alla lampadina, si accende.</p>
      <div class="guida-prova" data-demo="interruttore"></div>`,
      avvia: (el) => Guida.demoInterruttore(el),
    },
    { html: `<div class="grande">🧩</div><h2>Le porte decidono</h2>
      <p>Nei circuiti, tra gli interruttori e la lampadina ci sono le <b>porte</b>: piccoli pezzi che lasciano passare la corrente oppure la fermano, secondo una regola.</p>
      <p>All'inizio non ti diciamo le regole: <b>prova gli interruttori e scoprile tu</b>. Dopo averle scoperte le trovi scritte in "Le regole delle porte".</p>`,
    },
    { html: `<div class="grande">🎯</div><h2>Come si gioca</h2>
      <ol>
        <li><b>Tocca gli interruttori</b> nel circuito per accendere o spegnere i bit.</li>
        <li>Obiettivo del livello: <b>accendere la lampadina</b> (o rispondere alla domanda).</li>
        <li>Quando ci riesci, vai al <b>livello successivo</b>: ogni livello superato dà <b>100 punti</b>.</li>
      </ol>
      <p class="muto">I numeri in alto sono i livelli. Se sei bloccato puoi spegnere tutto e ricominciare.</p>`,
    },
  ],
});

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
  if (!SBLOCCA_TUTTO && i > 0 && !stato.fatti.has(i - 1)) return;
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
    b.disabled = !SBLOCCA_TUTTO && i > 0 && !stato.fatti.has(i - 1);
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
