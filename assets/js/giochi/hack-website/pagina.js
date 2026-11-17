'use strict';

const H = window.HackLivelli;
const NUMERI = OD.numeri;   // livelli di questa sessione (admin)
const LIVELLI = NUMERI.map((n) => ({ n, ...H.LIVELLI[n - 1] })).filter((l) => l.titolo);
const PUNTI_LIVELLO = 100;
const API = '../api/save_score.php';
const MODULO = 'hack_website';
const SBLOCCA_TUTTO = OD.sbloccaTutto; // livelli tutti aperti (scelto per sessione in admin)

const $ = (id) => document.getElementById(id);
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
  if (!SBLOCCA_TUTTO && i > 0 && !stato.fatti.has(LIVELLI[i - 1].n)) return;
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
      !SBLOCCA_TUTTO && i > 0 && !stato.fatti.has(LIVELLI[i - 1].n), () => apriLivello(i));
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
apriLivello(Number.isInteger(salvato) && salvato >= 0 && salvato < LIVELLI.length && (SBLOCCA_TUTTO || salvato === 0 || stato.fatti.has(LIVELLI[salvato - 1].n))
  ? salvato : Math.max(0, primoLibero));
if (!storage.get('od_uuid')) modalitaProva();
else if (stato.fatti.size) salva(); // riallinea il server se il telefono ha progressi non salvati
