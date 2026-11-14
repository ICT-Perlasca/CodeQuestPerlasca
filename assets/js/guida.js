/**
 * Guida introduttiva a slide per i giochi (Binary Lights, Porte logiche).
 *
 *   Guida.crea({
 *     chiave:   'binary_lights',          // la guida compare da sola la prima volta (localStorage od_guida_<chiave>)
 *     bottone:  document.getElementById('btn-guida'),   // riapre la guida quando si vuole
 *     slide: [ { html: '<h2>..</h2>..', avvia(el) { ...piccola demo interattiva... } }, ... ],
 *   });
 *
 * Nessuna dipendenza: lo stile e' qui dentro e usa le variabili colore della pagina
 * (--panel, --panel-2, --line, --text, --muted, --accent), che i giochi gia' definiscono.
 */
'use strict';
(() => {
  const storage = {
    get(k) { try { return localStorage.getItem(k); } catch { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch { /* ignora */ } },
  };

  const css = `
  .guida { position:fixed; inset:0; z-index:100; display:grid; place-items:center; padding:16px; background:rgba(5,8,20,.85); }
  .guida[hidden] { display:none !important; }
  .guida-box { width:100%; max-width:460px; max-height:calc(100dvh - 32px); overflow-y:auto; border-radius:22px; border:1px solid var(--line);
               padding:22px 20px 16px; background:linear-gradient(160deg, #1b2a6b, #141b33 55%); display:grid; gap:16px; color:var(--text);
               font:16px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  .guida-slide { display:none; gap:12px; }
  .guida-slide.on { display:grid; }
  .guida-slide h2 { margin:0; font-size:1.3rem; }
  .guida-slide p { margin:0; color:var(--text); }
  .guida-slide .muto { color:var(--muted); font-size:.9rem; }
  .guida-slide .grande { font-size:2.4rem; line-height:1; }
  .guida-slide ol { margin:0; padding-left:1.2em; display:grid; gap:6px; }
  .guida-prova { background:rgba(11,16,32,.55); border:1px solid var(--line); border-radius:14px; padding:12px; display:grid; gap:10px; justify-items:center; text-align:center; }
  .guida-prova .esito { min-height:1.4em; font-weight:700; color:var(--accent); }
  .guida-prova .esito.ok { color:#3ddc97; }
  .guida-lampadine { display:flex; gap:6px; }
  .guida-lampadine button { width:34px; display:grid; gap:4px; justify-items:center; background:transparent; border:0; color:var(--muted); font:inherit; font-size:.7rem; cursor:pointer; padding:0; touch-action:manipulation; }
  .guida-lampadine button i { width:30px; height:30px; border-radius:50%; background:#2a3566; border:2px solid #3a4680; display:block; transition:.12s; }
  .guida-lampadine button.on i { background:#ffd84d; border-color:#ffd84d; box-shadow:0 0 14px #ffd84d99; }
  .guida-lampadine button.on { color:#ffd84d; font-weight:700; }
  .guida-nav { display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .guida-nav button { font:inherit; cursor:pointer; touch-action:manipulation; }
  .guida-avanti { font-weight:800; font-size:1rem; padding:11px 18px; border:0; border-radius:12px; background:var(--accent); color:#04121c; }
  .guida-indietro, .guida-salta { background:transparent; border:0; color:var(--muted); font-size:.9rem; padding:8px; }
  .guida-puntini { display:flex; gap:7px; }
  .guida-puntini i { width:9px; height:9px; border-radius:50%; background:var(--line); }
  .guida-puntini i.on { background:var(--accent); }
  .btn-guida { font:inherit; font-weight:800; width:34px; height:34px; border-radius:50%; border:1px solid var(--line); background:var(--panel); color:var(--accent); cursor:pointer; touch-action:manipulation; }
  `;

  function crea({ chiave, bottone, slide }) {
    const stile = document.createElement('style');
    stile.textContent = css;
    document.head.append(stile);

    const radice = document.createElement('div');
    radice.className = 'guida';
    radice.hidden = true;
    radice.setAttribute('role', 'dialog');
    radice.setAttribute('aria-modal', 'true');
    radice.setAttribute('aria-label', 'Guida');
    radice.innerHTML = `<div class="guida-box">${slide.map((s) => `<section class="guida-slide">${s.html}</section>`).join('')}
      <div class="guida-nav">
        <button type="button" class="guida-salta">Salta</button>
        <button type="button" class="guida-indietro">‹ Indietro</button>
        <div class="guida-puntini" aria-hidden="true">${slide.map(() => '<i></i>').join('')}</div>
        <button type="button" class="guida-avanti">Avanti</button>
      </div></div>`;
    document.body.append(radice);

    const sezioni = [...radice.querySelectorAll('.guida-slide')];
    const puntini = [...radice.querySelectorAll('.guida-puntini i')];
    const avanti = radice.querySelector('.guida-avanti');
    const indietro = radice.querySelector('.guida-indietro');
    const salta = radice.querySelector('.guida-salta');
    const chiaveStorage = `od_guida_${chiave}`;
    let n = 0;
    const avviate = new Set();

    function mostra(i) {
      n = i;
      sezioni.forEach((el, k) => el.classList.toggle('on', k === i));
      puntini.forEach((el, k) => el.classList.toggle('on', k === i));
      const ultima = i === sezioni.length - 1;
      avanti.textContent = ultima ? 'Ho capito!' : 'Avanti';
      indietro.hidden = i === 0;
      salta.hidden = i !== 0; // la 1a slide ha "Salta", le altre "Indietro"
      if (!avviate.has(i) && slide[i].avvia) {
        slide[i].avvia(sezioni[i]);
        avviate.add(i);
      }
    }
    function apri() {
      mostra(0);
      radice.hidden = false;
      avanti.focus();
    }
    function chiudi() {
      radice.hidden = true;
      storage.set(chiaveStorage, '1');
      if (bottone) bottone.focus();
    }

    avanti.addEventListener('click', () => (n < sezioni.length - 1 ? mostra(n + 1) : chiudi()));
    indietro.addEventListener('click', () => mostra(Math.max(0, n - 1)));
    salta.addEventListener('click', chiudi);
    document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape' && !radice.hidden) chiudi(); });
    let tocco = null;
    radice.addEventListener('touchstart', (ev) => { tocco = ev.touches[0].clientX; }, { passive: true });
    radice.addEventListener('touchend', (ev) => {
      if (tocco === null) return;
      const dx = ev.changedTouches[0].clientX - tocco;
      tocco = null;
      if (dx < -60 && n < sezioni.length - 1) mostra(n + 1);
      if (dx > 60 && n > 0) mostra(n - 1);
    });
    if (bottone) bottone.addEventListener('click', apri);

    if (!storage.get(chiaveStorage)) apri(); // la prima volta compare da sola
    return { apri, chiudi };
  }

  /** Demo: lampadine con il loro valore (128 ... 1); `obiettivo` e' il numero da comporre. */
  function demoByte(el, obiettivo, testoOk) {
    const box = el.querySelector('[data-demo="byte"]');
    const valori = [128, 64, 32, 16, 8, 4, 2, 1];
    const acceso = new Set();
    box.innerHTML = `<div class="guida-lampadine">${valori.map((v) => `<button type="button" data-v="${v}" aria-pressed="false"><i></i>${v}</button>`).join('')}</div>
      <div class="somma"></div><div class="esito" aria-live="polite"></div>`;
    const somma = box.querySelector('.somma');
    const esito = box.querySelector('.esito');
    const aggiorna = () => {
      const tot = [...acceso].reduce((a, b) => a + b, 0);
      somma.textContent = acceso.size ? `${[...acceso].sort((a, b) => b - a).join(' + ')} = ${tot}` : 'Tutto spento = 0';
      esito.className = tot === obiettivo ? 'esito ok' : 'esito';
      esito.textContent = tot === obiettivo ? testoOk : `Obiettivo: fai ${obiettivo}`;
    };
    box.addEventListener('click', (ev) => {
      const b = ev.target.closest('button[data-v]');
      if (!b) return;
      const v = Number(b.dataset.v);
      if (acceso.has(v)) acceso.delete(v); else acceso.add(v);
      b.classList.toggle('on', acceso.has(v));
      b.setAttribute('aria-pressed', String(acceso.has(v)));
      aggiorna();
    });
    aggiorna();
  }

  /** Demo: un interruttore collegato a una lampadina. */
  function demoInterruttore(el) {
    const box = el.querySelector('[data-demo="interruttore"]');
    box.innerHTML = `<div style="display:flex;align-items:center;gap:10px;font-size:2rem">
      <button type="button" class="guida-avanti" style="min-width:110px" aria-pressed="false">0 · spento</button>
      <span aria-hidden="true">━━</span><span class="lamp" aria-hidden="true" style="filter:grayscale(1) brightness(.6)">💡</span></div>
      <div class="esito" aria-live="polite">Tocca l'interruttore</div>`;
    const btn = box.querySelector('button');
    const lamp = box.querySelector('.lamp');
    const esito = box.querySelector('.esito');
    let on = false;
    btn.addEventListener('click', () => {
      on = !on;
      btn.textContent = on ? '1 · acceso' : '0 · spento';
      btn.setAttribute('aria-pressed', String(on));
      lamp.style.filter = on ? 'none' : 'grayscale(1) brightness(.6)';
      esito.className = on ? 'esito ok' : 'esito';
      esito.textContent = on ? 'Passa corrente: la lampadina si accende!' : 'Niente corrente: la lampadina resta spenta';
    });
  }

  window.Guida = { crea, demoByte, demoInterruttore };
})();
