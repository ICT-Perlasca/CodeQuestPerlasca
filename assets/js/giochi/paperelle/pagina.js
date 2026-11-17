'use strict';

const LIVELLI = OD.livelli;
const API = '../api/codegame.php';
const SBLOCCA_TUTTO = OD.sbloccaTutto; // scelto per sessione in admin
const T = 50;                          // lato di una casella in unita' SVG
const DX = [0, 1, 0, -1], DY = [-1, 0, 1, 0];
const MAX_AZIONI = 400, MAX_CICLI = 2000;

const $ = (id) => document.getElementById(id);
const attendi = (ms) => new Promise((r) => setTimeout(r, ms));
const secondi = (ms) => (ms / 1000).toLocaleString('it-IT', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' s';
const mmss = (ms) => { const s = Math.floor(ms / 1000); return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`; };

const stato = {
  n: 1,                  // livello aperto
  online: false,         // true = tempi e punti salvati sul server
  uuid: storage.get('od_uuid'),
  progresso: {},         // n -> { risolto, ms, punti, media_ms }
  timerBase: 0,          // ms gia' trascorsi sul livello secondo il server
  timerDa: 0,            // performance.now() a cui corrisponde timerBase
  timerAttivo: false,
  esecuzione: 0,         // token: cambia per interrompere l'animazione in corso
  inCorso: false,
};

/* =====================================================================
   BLOCCHI
   ===================================================================== */
const COL = { evento: '#e8890c', moto: '#3d7be0', ciclo: '#25a05a', scelta: '#9a5cd6' };
Blockly.defineBlocksWithJsonArray([
  { type: 'quando_esegui', message0: 'quando premi ▶ Esegui', nextStatement: null, colour: COL.evento,
    tooltip: 'Il programma parte da qui' },
  { type: 'avanza', message0: 'avanza ⬆', previousStatement: null, nextStatement: null, colour: COL.moto,
    tooltip: 'La papera fa un passo avanti' },
  { type: 'gira', message0: 'gira %1',
    args0: [{ type: 'field_dropdown', name: 'DIR', options: [['a sinistra ↺', 'SX'], ['a destra ↻', 'DX']] }],
    previousStatement: null, nextStatement: null, colour: COL.moto, tooltip: 'La papera ruota sul posto' },
  { type: 'ripeti', message0: 'ripeti %1 volte', args0: [{ type: 'field_number', name: 'VOLTE', value: 3, min: 1, max: 20, precision: 1 }],
    message1: '%1', args1: [{ type: 'input_statement', name: 'FAI' }],
    previousStatement: null, nextStatement: null, colour: COL.ciclo, tooltip: 'Esegue i blocchi interni più volte' },
  { type: 'finche', message0: 'ripeti finché non arrivi allo stagno', message1: '%1', args1: [{ type: 'input_statement', name: 'FAI' }],
    previousStatement: null, nextStatement: null, colour: COL.ciclo, tooltip: 'Ripete finché la papera non è nello stagno' },
  { type: 'se', message0: 'se c\'è strada %1', args0: [{ type: 'field_dropdown', name: 'DIR', options: [['avanti', 'AV'], ['a sinistra', 'SX'], ['a destra', 'DX']] }],
    message1: '%1', args1: [{ type: 'input_statement', name: 'FAI' }],
    previousStatement: null, nextStatement: null, colour: COL.scelta, tooltip: 'Esegue i blocchi interni solo se c\'è strada' },
  { type: 'se_altrimenti', message0: 'se c\'è strada %1', args0: [{ type: 'field_dropdown', name: 'DIR', options: [['avanti', 'AV'], ['a sinistra', 'SX'], ['a destra', 'DX']] }],
    message1: '%1', args1: [{ type: 'input_statement', name: 'FAI' }],
    message2: 'altrimenti %1', args2: [{ type: 'input_statement', name: 'ALTRIMENTI' }],
    previousStatement: null, nextStatement: null, colour: COL.scelta, tooltip: 'Sceglie tra due strade' },
]);

const CASSETTA = {
  avanza: [{ kind: 'block', type: 'avanza' }],
  gira: [{ kind: 'block', type: 'gira', fields: { DIR: 'SX' } }, { kind: 'block', type: 'gira', fields: { DIR: 'DX' } }],
  ripeti: [{ kind: 'block', type: 'ripeti' }],
  finche: [{ kind: 'block', type: 'finche' }],
  se: [{ kind: 'block', type: 'se', fields: { DIR: 'SX' } }],
  se_altrimenti: [{ kind: 'block', type: 'se_altrimenti', fields: { DIR: 'AV' } }],
};
const cassetta = (nomi) => ({ kind: 'flyoutToolbox', contents: nomi.flatMap((n) => CASSETTA[n]) });

const tema = Blockly.Theme.defineTheme('paperelle', {
  base: Blockly.Themes.Classic,
  componentStyles: {
    workspaceBackgroundColour: '#0e1430', toolboxBackgroundColour: '#141b33', flyoutBackgroundColour: '#182046',
    flyoutOpacity: 1, scrollbarColour: '#3a4680', scrollbarOpacity: .8, insertionMarkerColour: '#ffffff',
    insertionMarkerOpacity: .35, cursorColour: '#ffd84d',
  },
  fontStyle: { family: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', weight: 'bold', size: 13 },
});

const ws = Blockly.inject('blockly', {
  toolbox: cassetta(['avanza']),
  renderer: 'zelos',
  theme: tema,
  media: '../assets/js/vendor/blockly/media/',
  trashcan: true,
  sounds: false,
  move: { scrollbars: true, drag: true, wheel: true },
  zoom: { controls: true, wheel: false, startScale: .85, maxScale: 1.6, minScale: .5 },
  grid: { spacing: 26, length: 2, colour: '#1f2852', snap: false },
});
ws.addChangeListener(Blockly.Events.disableOrphans); // i blocchi staccati restano grigi e non partono
new ResizeObserver(() => Blockly.svgResize(ws)).observe($('blockly'));

const testa = () => ws.getTopBlocks(false).find((b) => b.type === 'quando_esegui');
function contaBlocchi() {
  const t = testa();
  return t ? t.getDescendants(false).filter((b) => b.isEnabled()).length - 1 : 0;
}

let salvaTimer = 0;
function salvaOra() {
  if (!salvaTimer) return;
  clearTimeout(salvaTimer);
  salvaTimer = 0;
  storage.set(`od_papere_ws_${stato.n}`, JSON.stringify(Blockly.serialization.workspaces.save(ws)));
}
ws.addChangeListener((e) => {
  if (e.isUiEvent) return;
  aggiornaContatore();
  $('codice').innerHTML = codiceJs();
  clearTimeout(salvaTimer);
  salvaTimer = setTimeout(() => { salvaTimer = 1; salvaOra(); }, 300);
});

function aggiornaContatore() {
  const n = contaBlocchi(), ott = livello().ottimali;
  $('n-blocchi').textContent = n;
  $('n-blocchi').classList.toggle('troppi', n > ott);
  $('n-ottimali').textContent = `/ ${ott} ideali`;
}

function caricaWorkspace(n) {
  ws.clear();
  let salvato = null;
  try { salvato = JSON.parse(storage.get(`od_papere_ws_${n}`) || 'null'); } catch { salvato = null; }
  if (salvato) {
    try { Blockly.serialization.workspaces.load(salvato, ws); } catch { ws.clear(); }
  }
  if (!testa()) {
    Blockly.serialization.blocks.append({ type: 'quando_esegui', x: 40, y: 40, deletable: false }, ws);
  }
  testa().setDeletable(false);
  aggiornaContatore();
  $('codice').innerHTML = codiceJs();
}

/* =====================================================================
   DAI BLOCCHI A UN PROGRAMMA (albero di istruzioni)
   ===================================================================== */
function compila(blocco) {
  const out = [];
  for (let b = blocco; b; b = b.getNextBlock()) {
    if (!b.isEnabled()) continue;
    const id = b.id;
    switch (b.type) {
      case 'avanza': out.push({ t: 'avanza', id }); break;
      case 'gira': out.push({ t: 'gira', verso: b.getFieldValue('DIR') === 'SX' ? -1 : 1, id }); break;
      case 'ripeti': out.push({ t: 'ripeti', volte: Number(b.getFieldValue('VOLTE')) || 0, corpo: compila(b.getInputTargetBlock('FAI')), id }); break;
      case 'finche': out.push({ t: 'finche', corpo: compila(b.getInputTargetBlock('FAI')), id }); break;
      case 'se':
      case 'se_altrimenti':
        out.push({
          t: 'se', dir: b.getFieldValue('DIR'), id,
          corpo: compila(b.getInputTargetBlock('FAI')),
          altrimenti: b.type === 'se_altrimenti' ? compila(b.getInputTargetBlock('ALTRIMENTI')) : [],
        });
        break;
    }
  }
  return out;
}
const programma = () => { const t = testa(); return t ? compila(t.getNextBlock()) : []; };

/** Lo stesso programma scritto in JavaScript (con un po' di colore). */
function codiceJs() {
  const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;');
  const k = (s) => `<span class="k">${s}</span>`, f = (s) => `<span class="f">${s}</span>`;
  const COND = { AV: 'stradaAvanti()', SX: 'stradaASinistra()', DX: 'stradaADestra()' };
  const righe = (lista, ind) => lista.map((i) => {
    const p = '  '.repeat(ind);
    switch (i.t) {
      case 'avanza': return `${p}${f('avanza')}();`;
      case 'gira': return `${p}${f(i.verso < 0 ? 'giraASinistra' : 'giraADestra')}();`;
      case 'ripeti': return `${p}${k('for')} (${k('let')} i = 0; i ${esc('<')} ${i.volte}; i++) {\n${righe(i.corpo, ind + 1)}${p}}`;
      case 'finche': return `${p}${k('while')} (!${f('sulloStagno')}()) {\n${righe(i.corpo, ind + 1)}${p}}`;
      case 'se': return `${p}${k('if')} (${f(COND[i.dir])}) {\n${righe(i.corpo, ind + 1)}${p}}`
        + (i.altrimenti.length ? ` ${k('else')} {\n${righe(i.altrimenti, ind + 1)}${p}}` : '');
    }
    return '';
  }).map((r) => r + '\n').join('');
  const corpo = righe(programma(), 0);
  return corpo || '<span class="c">// aggancia i blocchi sotto "quando premi Esegui"</span>';
}

/* =====================================================================
   SIMULAZIONE: esegue il programma e produce gli eventi da animare
   ===================================================================== */
function nuovoMondo(liv) {
  const m = { x: 0, y: 0, dir: liv.dir, pulcini: new Set(), presi: 0, totPulcini: 0, arrivato: false };
  liv.mappa.forEach((riga, y) => [...riga].forEach((c, x) => {
    if (c === 'P') { m.x = x; m.y = y; }
    if (c === 'o') { m.pulcini.add(`${x},${y}`); m.totPulcini++; }
  }));
  m.libero = (x, y) => y >= 0 && y < 8 && x >= 0 && x < 8 && liv.mappa[y][x] !== '#';
  m.strada = (rel) => { const d = (m.dir + rel + 4) % 4; return m.libero(m.x + DX[d], m.y + DY[d]); };
  m.stagno = () => liv.mappa[m.y][m.x] === 'S';
  return m;
}

/** Generatore: esegue un'istruzione alla volta e restituisce cosa e' successo. */
function* esegui(lista, m, cont) {
  for (const i of lista) {
    if (++cont.cicli > MAX_CICLI) throw { fine: 'infinito' };
    switch (i.t) {
      case 'avanza': {
        if (++cont.azioni > MAX_AZIONI) throw { fine: 'infinito' };
        const nx = m.x + DX[m.dir], ny = m.y + DY[m.dir];
        if (!m.libero(nx, ny)) { yield { tipo: 'sbatti', id: i.id }; throw { fine: 'sbatti' }; }
        m.x = nx; m.y = ny;
        const chiave = `${nx},${ny}`;
        const preso = m.pulcini.delete(chiave);
        if (preso) m.presi++;
        yield { tipo: 'muovi', id: i.id, preso: preso ? chiave : null };
        if (m.stagno()) throw { fine: m.presi === m.totPulcini ? 'vinto' : 'pulcini' };
        break;
      }
      case 'gira':
        if (++cont.azioni > MAX_AZIONI) throw { fine: 'infinito' };
        m.dir = (m.dir + i.verso + 4) % 4;
        yield { tipo: 'gira', id: i.id, verso: i.verso };
        break;
      case 'ripeti':
        yield { tipo: 'controlla', id: i.id };
        for (let k = 0; k < i.volte; k++) yield* esegui(i.corpo, m, cont);
        break;
      case 'finche':
        while (!m.stagno()) {
          yield { tipo: 'controlla', id: i.id };
          yield* esegui(i.corpo, m, cont);
          if (++cont.cicli > MAX_CICLI) throw { fine: 'infinito' };
        }
        break;
      case 'se': {
        yield { tipo: 'controlla', id: i.id };
        const rel = { AV: 0, SX: -1, DX: 1 }[i.dir];
        yield* esegui(m.strada(rel) ? i.corpo : i.altrimenti, m, cont);
        break;
      }
    }
  }
}

/* =====================================================================
   DISEGNO DELLA SCENA (SVG)
   ===================================================================== */
const svg = $('scena');
const NS = 'http://www.w3.org/2000/svg';
let grafica = {};   // riferimenti agli elementi da animare

function el(tag, attr, padre) {
  const e = document.createElementNS(NS, tag);
  for (const [k, v] of Object.entries(attr)) e.setAttribute(k, v);
  if (padre) padre.append(e);
  return e;
}

/** Papera vista dall'alto, rivolta verso l'alto, centrata in (0,0). */
function disegnaPapera(padre, scala, colore) {
  const g = el('g', { transform: `scale(${scala})` }, padre);
  el('ellipse', { cx: 2, cy: 6, rx: 15, ry: 18, fill: 'rgba(0,0,0,.18)' }, g);
  el('path', { d: 'M-6 16 L0 25 L6 16 Z', fill: colore.scuro }, g);
  el('ellipse', { class: 'corpo', cx: 0, cy: 4, rx: 13, ry: 16, fill: colore.corpo, stroke: colore.scuro, 'stroke-width': 1.5 }, g);
  el('ellipse', { cx: -9, cy: 6, rx: 5, ry: 10, fill: colore.scuro, opacity: .55 }, g);
  el('ellipse', { cx: 9, cy: 6, rx: 5, ry: 10, fill: colore.scuro, opacity: .55 }, g);
  el('circle', { cx: 0, cy: -12, r: 9, fill: colore.testa, stroke: colore.scuro, 'stroke-width': 1.2 }, g);
  el('ellipse', { cx: 0, cy: -22, rx: 5, ry: 4, fill: '#ff8c1a', stroke: '#c95f00', 'stroke-width': 1 }, g);
  el('circle', { cx: -4, cy: -14, r: 1.8, fill: '#1a1a1a' }, g);
  el('circle', { cx: 4, cy: -14, r: 1.8, fill: '#1a1a1a' }, g);
  return g;
}
const MAMMA = { corpo: '#ffd84d', testa: '#ffe36e', scuro: '#d9a600' };
const PULCINO = { corpo: '#fff27a', testa: '#fff6a8', scuro: '#e0c200' };

function disegnaScena() {
  const liv = livello();
  svg.replaceChildren();
  const fondo = el('g', {}, svg);
  liv.mappa.forEach((riga, y) => [...riga].forEach((c, x) => {
    const px = x * T, py = y * T, cx = px + T / 2, cy = py + T / 2;
    if (c === '#') {
      el('rect', { x: px, y: py, width: T, height: T, fill: (x + y) % 2 ? '#4fa457' : '#58ad5f' }, fondo);
      const tipo = (x * 7 + y * 13) % 5;
      if (tipo === 0) { // cespuglio
        el('circle', { cx: cx - 7, cy: cy + 3, r: 11, fill: '#2f7d3a' }, fondo);
        el('circle', { cx: cx + 7, cy: cy + 2, r: 12, fill: '#348a40' }, fondo);
        el('circle', { cx, cy: cy - 7, r: 11, fill: '#3b9847' }, fondo);
      } else if (tipo === 1) { // sasso
        el('ellipse', { cx, cy: cy + 4, rx: 16, ry: 12, fill: '#8a8f9c', stroke: '#6b707c', 'stroke-width': 2 }, fondo);
        el('ellipse', { cx: cx - 4, cy: cy, rx: 6, ry: 3, fill: '#a9aebb' }, fondo);
      } else if (tipo === 2) { // fiori
        for (const [ox, oy, col] of [[-10, -8, '#ff8fb1'], [8, -4, '#fff'], [-2, 10, '#ffd84d']]) {
          el('circle', { cx: cx + ox, cy: cy + oy, r: 4, fill: col }, fondo);
        }
      } else if (tipo === 3) { // albero
        el('circle', { cx, cy, r: 20, fill: '#246b30' }, fondo);
        el('circle', { cx: cx - 5, cy: cy - 5, r: 10, fill: '#2f8a3e' }, fondo);
      }
    } else {
      el('rect', { x: px, y: py, width: T, height: T, fill: '#d9c58f' }, fondo);
      el('rect', { x: px + 2, y: py + 2, width: T - 4, height: T - 4, rx: 8, fill: '#e6d5a3' }, fondo);
    }
    if (c === 'S') {
      el('rect', { x: px + 2, y: py + 2, width: T - 4, height: T - 4, rx: 16, fill: '#3aa6e0', stroke: '#2b7fb0', 'stroke-width': 3 }, fondo);
      el('ellipse', { class: 'stagno-onda', cx, cy, rx: 12, ry: 7, fill: 'none', stroke: '#bfe9ff', 'stroke-width': 2, opacity: .7 }, fondo);
      el('circle', { cx: cx + 11, cy: cy + 10, r: 5, fill: '#3f9a4a' }, fondo);
    }
  }));

  // griglia leggera: aiuta a contare i passi
  const griglia = el('g', { stroke: 'rgba(0,0,0,.08)', 'stroke-width': 1 }, svg);
  for (let i = 1; i < 8; i++) {
    el('line', { x1: i * T, y1: 0, x2: i * T, y2: 400 }, griglia);
    el('line', { x1: 0, y1: i * T, x2: 400, y2: i * T }, griglia);
  }

  grafica = { pulcini: new Map(), seguaci: [], storia: [] };
  liv.mappa.forEach((riga, y) => [...riga].forEach((c, x) => {
    if (c !== 'o') return;
    const g = el('g', { class: 'pulcino-seguace' }, svg);
    disegnaPapera(g, .5, PULCINO);
    g.style.transform = `translate(${x * T + T / 2}px, ${y * T + T / 2}px) rotate(180deg)`;
    grafica.pulcini.set(`${x},${y}`, { g, x, y, ang: 180, nato: -1 });
  }));

  const m = nuovoMondo(liv);
  grafica.papera = el('g', { class: 'papera' }, svg);
  disegnaPapera(grafica.papera, .95, MAMMA);
  grafica.x = m.x; grafica.y = m.y; grafica.ang = m.dir * 90;
  grafica.storia.push([m.x, m.y]);
  posizionaPapera(false);
}

function posizionaPapera(anima, spinta = 0) {
  const p = grafica.papera;
  p.style.transition = anima ? '' : 'none';
  const rad = grafica.ang * Math.PI / 180;
  const ox = Math.sin(rad) * spinta, oy = -Math.cos(rad) * spinta;
  p.style.transform = `translate(${grafica.x * T + T / 2 + ox}px, ${grafica.y * T + T / 2 + oy}px) rotate(${grafica.ang}deg)`;
  if (!anima) { p.getBoundingClientRect(); p.style.transition = ''; }
}

/** I pulcini raccolti seguono la mamma in fila, ripercorrendo i suoi passi. */
function muoviSeguaci() {
  const s = grafica.storia;
  grafica.seguaci.forEach((p, i) => {
    const idx = Math.max(s.length - 2 - i, p.nato);
    const [nx, ny] = s[idx];
    if (nx === p.x && ny === p.y) return;
    p.ang = ruotaVerso(p.ang, Math.round(Math.atan2(nx - p.x, -(ny - p.y)) * 180 / Math.PI));
    p.x = nx; p.y = ny;
    p.g.style.transform = `translate(${nx * T + T / 2}px, ${ny * T + T / 2}px) rotate(${p.ang}deg)`;
  });
}
/** Angolo piu' vicino al precedente (evita giri completi nell'animazione). */
function ruotaVerso(da, a) { let d = ((a - da) % 360 + 540) % 360 - 180; return da + d; }

/* =====================================================================
   ESECUZIONE ANIMATA
   ===================================================================== */
async function avvia() {
  if (stato.inCorso) return;
  const prog = programma();
  if (!prog.length) {
    return mostraEsito('ko', 'Il programma è vuoto!', 'Trascina un blocco e aggancialo sotto "quando premi ▶ Esegui": i blocchi staccati restano grigi e non partono.');
  }
  ripristina();
  const token = ++stato.esecuzione;
  stato.inCorso = true;
  document.body.classList.add('in-esecuzione');
  $('btn-esegui').disabled = true;
  $('esito').hidden = true;
  const chiaveRun = `od_papere_run_${stato.n}`;
  storage.set(chiaveRun, String((Number(storage.get(chiaveRun)) || 0) + 1));

  const passo = Number($('velocita').value);
  svg.style.setProperty('--passo', `${Math.round(passo * .85)}ms`);
  const m = nuovoMondo(livello());
  const gen = esegui(prog, m, { azioni: 0, cicli: 0 });
  let fine = 'incompleto';

  try {
    for (const ev of gen) {
      if (token !== stato.esecuzione) return;
      ws.highlightBlock(ev.id);
      if (ev.tipo === 'controlla') { await attendi(passo * .35); continue; }
      if (ev.tipo === 'muovi') {
        grafica.x = m.x; grafica.y = m.y;
        grafica.storia.push([m.x, m.y]);
        posizionaPapera(true);
        muoviSeguaci();
        if (ev.preso) {
          const p = grafica.pulcini.get(ev.preso);
          p.nato = grafica.storia.length - 1;
          grafica.seguaci.push(p);
          if (navigator.vibrate) navigator.vibrate(15);
        }
      } else if (ev.tipo === 'gira') {
        grafica.ang += ev.verso * 90;
        posizionaPapera(true);
      } else if (ev.tipo === 'sbatti') {
        grafica.papera.classList.add('sbatte');
        posizionaPapera(true, 14);
        await attendi(passo * .5);
        posizionaPapera(true);
        if (navigator.vibrate) navigator.vibrate(120);
      }
      await attendi(passo);
    }
  } catch (e) {
    if (e && e.fine) fine = e.fine; else throw e;
  } finally {
    if (token === stato.esecuzione) {
      ws.highlightBlock(null);
      stato.inCorso = false;
      document.body.classList.remove('in-esecuzione');
      $('btn-esegui').disabled = false;
    }
  }
  if (token !== stato.esecuzione) return;

  const mancano = m.totPulcini - m.presi;
  switch (fine) {
    case 'vinto': return vittoria();
    case 'sbatti': return mostraEsito('ko', 'Ahia! La papera ha sbattuto contro un ostacolo.', 'Controlla in che direzione guarda prima di farla avanzare.');
    case 'pulcini': return mostraEsito('ko', `Hai lasciato indietro ${mancano === 1 ? 'un pulcino' : mancano + ' pulcini'}!`, 'Passa sopra a tutti i pulcini prima di entrare nello stagno.');
    case 'infinito': return mostraEsito('ko', 'Il programma non finisce più!', 'Forse c\'è un ciclo che non arriva mai allo stagno.');
    default: return mostraEsito('ko', 'La papera non è arrivata allo stagno.', 'Il programma è finito troppo presto: aggiungi altri blocchi.');
  }
}

function ripristina() {
  stato.esecuzione++;
  stato.inCorso = false;
  ws.highlightBlock(null);
  document.body.classList.remove('in-esecuzione');
  $('btn-esegui').disabled = false;
  disegnaScena();
}

function mostraEsito(tipo, testo, dettaglio = '') {
  const box = $('esito');
  box.className = `esito ${tipo}`;
  box.innerHTML = '';
  box.append(testo);
  if (dettaglio) { const s = document.createElement('small'); s.textContent = dettaglio; box.append(s); }
  box.hidden = false;
}

/* =====================================================================
   VITTORIA, TEMPI E PUNTI
   ===================================================================== */
async function vittoria() {
  const n = stato.n, liv = livello();
  const blocchi = contaBlocchi();
  const ottimale = blocchi <= liv.ottimali;
  const giaRisolto = !!stato.progresso[n]?.risolto;
  fermaTimer();
  mostraEsito('ok', 'Ce l\'hai fatta! 🎉', ottimale ? 'Soluzione perfetta: numero minimo di blocchi!' : `Funziona! Si poteva fare con soli ${liv.ottimali} blocchi.`);

  let r = null, errore = '';
  if (stato.online && !giaRisolto) {
    try {
      r = await api('completa', { livello: n, blocchi, esecuzioni: Number(storage.get(`od_papere_run_${n}`)) || 1 });
      stato.progresso[n] = { risolto: true, ms: r.ms, punti: r.punti, media_ms: r.media_ms };
    } catch (e) { errore = e.message; }
  } else if (!stato.online && !giaRisolto) {
    stato.progresso[n] = { risolto: true, ms: Math.round(tempoTrascorso()), punti: null };
    storage.set('od_papere_prova', JSON.stringify(stato.progresso));
  }
  disegnaLivelli();
  aggiornaPunti();

  const p = stato.progresso[n] || {};
  $('vinto-titolo').textContent = `Livello ${n} superato! 🎉`;
  $('vinto-testo').textContent = giaRisolto
    ? 'Livello già risolto: per la classifica conta il primo tempo.'
    : ottimale ? 'E con il numero minimo di blocchi: programma perfetto!' : `Funziona! Prova a rifarlo con soli ${liv.ottimali} blocchi.`;

  const conf = $('vinto-confronto');
  conf.replaceChildren();
  const media = r?.media_ms ?? p.media_ms;
  if (p.ms) {
    const max = Math.max(p.ms, media || 0);
    const riga = (cls, nome, ms) => {
      const d = document.createElement('div');
      d.className = `riga ${cls}`;
      d.innerHTML = `<span>${nome}</span><div class="barra" style="width:${Math.max(3, ms / max * 100)}%"></div><span>${secondi(ms)}</span>`;
      conf.append(d);
    };
    riga('tu', 'Il tuo tempo', p.ms);
    if (media) riga('', 'Media', media);
  }

  const out = $('vinto-punti');
  if (errore) out.textContent = `Punteggio non salvato: ${errore}`;
  else if (!stato.online) out.textContent = 'Modalità prova: fai il login all\'Open Day per salvare tempi e punti.';
  else if (r && !r.gia_risolto) {
    out.innerHTML = `<span class="punti-grandi">+${r.punti}</span> punti${r.bonus ? ` <small>(compresi ${r.bonus} di bonus blocchi)</small>` : ''}<br><small>Totale in classifica: ${r.punti_totali} pt</small>`;
  } else out.textContent = p.punti != null ? `Punti di questo livello: ${p.punti}` : '';

  const ultimo = n === LIVELLI.length;
  $('btn-avanti').textContent = ultimo ? 'Vedi il riepilogo 🏁' : 'Livello successivo ▶';
  $('dlg-vinto').showModal();
}

$('btn-avanti').addEventListener('click', () => {
  $('dlg-vinto').close();
  if (stato.n < LIVELLI.length) apriLivello(stato.n + 1);
  else mostraRiepilogo();
});
$('btn-resta').addEventListener('click', () => $('dlg-vinto').close());
$('btn-chiudi-fine').addEventListener('click', () => $('dlg-fine').close());

function mostraRiepilogo() {
  const tb = $('fine-righe');
  tb.replaceChildren();
  for (const l of LIVELLI) {
    const p = stato.progresso[l.n] || {};
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${l.n}. ${l.titolo}</td><td class="num">${p.ms ? secondi(p.ms) : '–'}</td>`
      + `<td class="num">${p.media_ms ? secondi(p.media_ms) : '–'}</td><td class="num">${p.punti ?? '–'}</td>`;
    tb.append(tr);
  }
  $('dlg-fine').showModal();
}

/* ---------- cronometro (il tempo "vero" e' quello del server) ---------- */
function tempoTrascorso() { return stato.timerBase + (stato.timerAttivo ? performance.now() - stato.timerDa : 0); }
function avviaTimer(base) { stato.timerBase = base; stato.timerDa = performance.now(); stato.timerAttivo = true; }
function fermaTimer() { stato.timerBase = tempoTrascorso(); stato.timerAttivo = false; }
setInterval(() => {
  const p = stato.progresso[stato.n];
  $('hud-tempo').textContent = p?.risolto && p.ms ? secondi(p.ms) : mmss(tempoTrascorso());
}, 250);

function aggiornaPunti() {
  const tot = Object.values(stato.progresso).reduce((s, p) => s + (p.punti || 0), 0);
  $('hud-punti').textContent = tot;
  $('hud-punti-box').hidden = !stato.online;
}

/* =====================================================================
   LIVELLI
   ===================================================================== */
const livello = () => LIVELLI[stato.n - 1];
const sbloccato = (n) => SBLOCCA_TUTTO || n === 1 || !!stato.progresso[n - 1]?.risolto;

function disegnaLivelli() {
  const nav = $('livelli');
  nav.replaceChildren();
  for (const l of LIVELLI) {
    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = stato.progresso[l.n]?.risolto ? '✓' : l.n;
    b.title = `Livello ${l.n}: ${l.titolo}`;
    b.classList.toggle('fatto', !!stato.progresso[l.n]?.risolto);
    b.classList.toggle('corrente', l.n === stato.n);
    b.disabled = !sbloccato(l.n);
    b.addEventListener('click', () => apriLivello(l.n));
    nav.append(b);
  }
}

async function apriLivello(n) {
  if (!sbloccato(n)) return;
  salvaOra(); // i blocchi del livello che stai lasciando
  stato.n = n;
  const liv = livello();
  storage.set('od_papere_livello', String(n));
  $('liv-num').textContent = `Livello ${n}/${LIVELLI.length}`;
  $('liv-titolo').textContent = liv.titolo;
  $('liv-testo').innerHTML = liv.testo;
  $('liv-obiettivo').textContent = `Obiettivo: arrivare allo stagno usando al massimo ${liv.ottimali} blocchi (+20 punti bonus).`;
  $('esito').hidden = true;
  ws.updateToolbox(cassetta(liv.blocchi));
  caricaWorkspace(n);
  ripristina();
  disegnaLivelli();

  if (stato.progresso[n]?.risolto) { fermaTimer(); return; }
  avviaTimer(0);
  if (!stato.online) return;
  try {
    const r = await api('inizia', { livello: n });
    if (stato.n !== n) return;
    stato.progresso[n] = { ...stato.progresso[n], media_ms: r.media_ms };
    if (r.risolto) { stato.progresso[n] = { risolto: true, ms: r.ms, punti: r.punti, media_ms: r.media_ms }; fermaTimer(); disegnaLivelli(); }
    else avviaTimer(r.trascorsi_ms);
  } catch (e) {
    mostraEsito('ko', 'Cronometro non avviato', e.message);
  }
}

/* =====================================================================
   SERVER
   ===================================================================== */
async function api(azione, dati = {}) {
  const res = await fetch(API, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ uuid: stato.uuid, azione, ...dati }),
  });
  const d = await res.json().catch(() => ({}));
  if (!res.ok || !d.ok) { const err = new Error(d.errore || `Errore ${res.status}`); err.status = res.status; throw err; }
  return d;
}

async function avvio() {
  const avviso = $('avviso-prova');
  if (stato.uuid) {
    try {
      const r = await api('stato');
      stato.online = true;
      for (const l of r.livelli) stato.progresso[l.livello] = { risolto: l.risolto, ms: l.ms, punti: l.punti, media_ms: l.media_ms };
    } catch (e) {
      avviso.innerHTML = `Modalità prova (${e.message.replace(/</g, '&lt;')}). <a href="../index.php">Torna all'Open Day</a> per salvare i punti.`;
    }
  } else {
    avviso.innerHTML = 'Modalità prova: i tempi non vengono salvati. <a href="../index.php">Entra all\'Open Day</a> per finire in classifica.';
  }
  if (!stato.online) {
    avviso.hidden = false;
    try { stato.progresso = JSON.parse(storage.get('od_papere_prova') || '{}') || {}; } catch { stato.progresso = {}; }
  }
  aggiornaPunti();
  // riparte dall'ultimo livello aperto, altrimenti dal primo non risolto
  const primoLibero = LIVELLI.find((l) => !stato.progresso[l.n]?.risolto)?.n ?? LIVELLI.length;
  const ultimo = Number(storage.get('od_papere_livello')) || 0;
  apriLivello(ultimo && sbloccato(ultimo) ? ultimo : primoLibero);
}

$('btn-esegui').addEventListener('click', avvia);
$('btn-reset').addEventListener('click', () => { ripristina(); $('esito').hidden = true; });
const velSalvata = storage.get('od_papere_vel');
if (velSalvata && [...$('velocita').options].some((o) => o.value === velSalvata)) $('velocita').value = velSalvata;
$('velocita').addEventListener('change', () => storage.set('od_papere_vel', $('velocita').value));

avvio();
