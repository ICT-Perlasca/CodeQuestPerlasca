'use strict';

const CONFIG = {
  round: OD.round,                       // dalla sessione in admin (Open Day 5, laboratorio 12)
  secondiRound: 20,
  puntiBase: 100,
  bonusSecondo: 10,               // max per round: 100 + 20*10 = 300 -> max partita round*300
  modulo: 'binary_lights',
  api: '../api/save_score.php',
};
// intervallo del numero bersaglio per ogni round (calcolato in PHP qui sopra)
const LIVELLI = OD.livelli;
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


/* ---------- guida introduttiva (compare la prima volta, poi col pulsante ?) ---------- */
Guida.crea({
  chiave: 'binary_lights',
  bottone: document.getElementById('btn-guida'),
  slide: [
    { html: `<div class="grande">💡</div><h2>Il computer conosce solo due cose</h2>
      <p>Acceso (<b>1</b>) e spento (<b>0</b>). Con tante lampadine accese e spente riesce a scrivere qualsiasi numero.</p>
      <p>Un <b>byte</b> sono 8 lampadine in fila. Ognuna vale <b>il doppio</b> di quella alla sua destra: 1, 2, 4, 8, 16, 32, 64, 128.</p>`,
    },
    { html: `<h2>Prova tu!</h2>
      <p>Tocca le lampadine: il numero è la <b>somma dei valori di quelle accese</b>.</p>
      <div class="guida-prova" data-demo="byte"></div>
      <p class="muto">Suggerimento: 5 = 4 + 1.</p>`,
      avvia: (el) => Guida.demoByte(el, 5, '✔ Bravo! 4 + 1 = 5'),
    },
    { html: `<div class="grande">⏱️</div><h2>Come si gioca</h2>
      <ol>
        <li>Leggi il <b>numero</b> da comporre.</li>
        <li>Accendi le lampadine giuste <b>prima che scada il tempo</b>.</li>
        <li>Più sei veloce, più punti fai.</li>
      </ol>
      <p class="muto">${CONFIG.round} round, ogni volta con numeri più difficili. Puoi rigiocare per battere il tuo record.</p>`,
    },
  ],
});

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
