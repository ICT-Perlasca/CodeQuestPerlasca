/**
 * Ispeziona: un pannello "strumenti per sviluppatori" semplificato, con l'aspetto
 * di quello di Chrome, che lavora sul VERO codice di un riquadro della pagina.
 *
 *   Elementi : albero HTML. Tocca una riga per selezionarla; tocca di nuovo un testo
 *              o un attributo (oppure doppio clic) per modificarlo. Svuotare un
 *              attributo lo cancella. 🗑 elimina l'elemento selezionato.
 *   Stili    : le regole CSS che valgono per l'elemento selezionato, con spunte per
 *              disattivare una proprieta' e valori modificabili, piu' element.style.
 *   Console  : esegue JavaScript nella pagina e mostra gli errori.
 *
 * Il pannello osserva il riquadro con un MutationObserver: le modifiche fatte con
 * il vero F12 del browser compaiono anche qui.
 *
 * Uso: Ispeziona.crea({ radice, foglio: () => CSSStyleSheet, pannello, cornice,
 *                       mostraSelettore: (sel) => string, onModifica: () => void })
 */
(function () {
  'use strict';

  const VUOTI = new Set(['input', 'br', 'img', 'hr', 'meta', 'link', 'source']);
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  /** "a: b; c: rgba(1, 2, 3); d: e" -> parti separate dai ";" fuori dalle parentesi e dalle virgolette. */
  function dividiDichiarazioni(testo) {
    const parti = [];
    let cur = '', par = 0, q = '';
    for (const ch of testo) {
      if (q) { if (ch === q) q = ''; cur += ch; continue; }
      if (ch === '"' || ch === "'") q = ch;
      else if (ch === '(') par++;
      else if (ch === ')') par = Math.max(0, par - 1);
      else if (ch === ';' && par === 0) { parti.push(cur); cur = ''; continue; }
      cur += ch;
    }
    parti.push(cur);
    return parti.map((p) => p.trim()).filter(Boolean);
  }

  function crea({ radice, foglio, pannello, cornice, mostraSelettore = (s) => s, onModifica = () => {} }) {
    const s = {
      sel: null,              // nodo selezionato (elemento, testo o commento)
      chiusi: new WeakSet(),  // elementi chiusi nell'albero
      refs: [],               // indice -> nodo / parte modificabile dell'albero
      srefs: [],              // indice -> dichiarazione CSS
      disattivati: new WeakMap(), // CSSStyleDeclaration -> [{prop, val, imp}] tolte con la spunta
      editing: false,
      sporco: false,
      picker: false,
      mangiaClick: false,
      storia: [], posStoria: 0,
    };

    pannello.classList.add('dt');
    pannello.innerHTML = `
      <div class="dt-tabs">
        <button type="button" class="dt-picker" title="Seleziona un elemento nella pagina" aria-label="Seleziona un elemento nella pagina" aria-pressed="false">
          <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M2 2h9v3h-1.5V3.5h-6v6H5V11H2z" fill="currentColor"/><path d="M7 7l7 3-3 1-1 3z" fill="currentColor"/></svg>
        </button>
        <button type="button" class="dt-tab attivo" data-tab="elementi">Elementi</button>
        <button type="button" class="dt-tab" data-tab="console">Console</button>
      </div>
      <div class="dt-vista" data-vista="elementi">
        <div class="dt-albero" role="tree" aria-label="Codice HTML della pagina"></div>
        <div class="dt-barra">
          <span class="dt-percorso"></span>
          <button type="button" class="dt-btn" data-az="attr" title="Aggiungi un attributo all'elemento selezionato">+ attributo</button>
          <button type="button" class="dt-btn" data-az="elimina" title="Elimina l'elemento selezionato">🗑 Elimina</button>
        </div>
        <div class="dt-stili-tit">Stili <small>(CSS)</small></div>
        <div class="dt-stili"></div>
      </div>
      <div class="dt-vista" data-vista="console" hidden>
        <div class="dt-log" aria-live="polite"></div>
        <form class="dt-prompt" autocomplete="off">
          <span aria-hidden="true">›</span>
          <input class="dt-cmd" spellcheck="false" autocapitalize="off" aria-label="Comando JavaScript" placeholder="scrivi un comando e premi Invio">
        </form>
      </div>`;
    const q = (sel) => pannello.querySelector(sel);
    const albero = q('.dt-albero'), stili = q('.dt-stili'), logEl = q('.dt-log'), cmd = q('.dt-cmd');
    const box = document.createElement('div');
    box.className = 'dt-evidenzia';
    box.innerHTML = '<span></span>';
    cornice.append(box);

    const elementoDi = (n) => (n && n.nodeType === 1 ? n : n?.parentElement) || null;
    const nelSito = (n) => !!n && n !== radice && radice.contains(n);
    const ref = (x) => s.refs.push(x) - 1;
    const sref = (x) => s.srefs.push(x) - 1;

    /* =====================================================================
       ALBERO HTML
       ===================================================================== */
    function apertura(el) {
      let h = `<span class="dt-tag">&lt;${el.localName}</span>`;
      for (const a of el.attributes) {
        h += ` <span class="dt-attr dt-mod" data-e="${ref({ el, nome: a.name })}"><span class="dt-an">${esc(a.name)}</span>` +
          (a.value !== '' ? `=<span class="dt-av">"${esc(a.value)}"</span>` : '') + '</span>';
      }
      return h + '<span class="dt-tag">&gt;</span>';
    }
    const chiusura = (el) => `<span class="dt-tag">&lt;/${el.localName}&gt;</span>`;
    const testo = (n) => `<span class="dt-testo dt-mod" data-e="${ref({ nodo: n })}">${esc(n.data.trim())}</span>`;
    const figliUtili = (el) => [...el.childNodes].filter((c) => (c.nodeType === 1) || (c.nodeType === 3 && c.data.trim()) || c.nodeType === 8);

    function riga(n, d, contenuto, freccia = '') {
      return `<div class="dt-riga" data-n="${ref(n)}" style="--d:${d}"><span class="dt-freccia">${freccia}</span>${contenuto}</div>`;
    }

    function nodo(n, d, out) {
      if (n.nodeType === 3) { if (n.data.trim()) out.push(riga(n, d, testo(n))); return; }
      if (n.nodeType === 8) { out.push(riga(n, d, `<span class="dt-cm">&lt;!-- ${testo(n)} --&gt;</span>`)); return; }
      if (n.nodeType !== 1 || n.classList.contains('dt-evidenzia')) return;
      const figli = figliUtili(n);
      if (VUOTI.has(n.localName)) out.push(riga(n, d, apertura(n)));
      else if (!figli.length) out.push(riga(n, d, apertura(n) + chiusura(n)));
      else if (figli.length === 1 && figli[0].nodeType === 3 && figli[0].data.trim().length <= 70) out.push(riga(n, d, apertura(n) + testo(figli[0]) + chiusura(n)));
      else if (s.chiusi.has(n)) out.push(riga(n, d, apertura(n) + '<span class="dt-puntini">…</span>' + chiusura(n), '▸'));
      else {
        out.push(riga(n, d, apertura(n), '▾'));
        for (const c of figli) nodo(c, d + 1, out);
        out.push(riga(n, d, chiusura(n)));
      }
    }

    function renderAlbero() {
      if (s.editing) { s.sporco = true; return; }
      if (s.sel && !radice.contains(s.sel)) s.sel = null;
      s.refs = [];
      const out = [];
      for (const n of radice.childNodes) nodo(n, 0, out);
      const scroll = albero.scrollTop;
      albero.innerHTML = out.join('') || '<p class="dt-vuoto">(pagina vuota)</p>';
      albero.scrollTop = scroll;
      segnaSelezione();
    }

    function segnaSelezione() {
      for (const r of albero.querySelectorAll('.dt-riga')) r.classList.toggle('sel', s.refs[r.dataset.n] === s.sel);
      const el = elementoDi(s.sel);
      const percorso = [];
      for (let x = el; x && x !== radice; x = x.parentElement) {
        percorso.unshift(x.localName + (x.classList.length ? '.' + [...x.classList].join('.') : ''));
      }
      q('.dt-percorso').textContent = percorso.length ? percorso.join(' › ') : 'nessun elemento selezionato';
      q('[data-az=elimina]').disabled = !nelSito(s.sel);
      q('[data-az=attr]').disabled = !(s.sel && s.sel.nodeType === 1);
      evidenzia(el);
    }

    function seleziona(n, scorri = false) {
      s.sel = n;
      if (scorri) {
        for (let x = n.parentElement; x && x !== radice; x = x.parentElement) s.chiusi.delete(x);
        renderAlbero();
        const r = [...albero.querySelectorAll('.dt-riga.sel')][0];
        if (r) {
          const top = r.offsetTop - albero.offsetTop;
          if (top < albero.scrollTop || top > albero.scrollTop + albero.clientHeight - 30) albero.scrollTop = top - albero.clientHeight / 3;
        }
      } else segnaSelezione();
      renderStili();
    }

    /* ---------- riquadro azzurro sull'elemento nella pagina ---------- */
    let evidenziato = null;
    function evidenzia(el) {
      evidenziato = el && nelSito(el) ? el : null;
      ridisegna();
    }
    function ridisegna() {
      const el = evidenziato;
      if (!el || !el.isConnected) { box.style.display = 'none'; return; }
      const r = el.getBoundingClientRect(), c = cornice.getBoundingClientRect();
      if (!r.width && !r.height) { box.style.display = 'none'; return; }
      Object.assign(box.style, { display: 'block', left: `${r.left - c.left}px`, top: `${r.top - c.top}px`, width: `${r.width}px`, height: `${r.height}px` });
      box.firstChild.textContent = `${el.localName}${el.classList.length ? '.' + [...el.classList].join('.') : ''}  ${Math.round(r.width)}×${Math.round(r.height)}`;
      box.classList.toggle('sotto', r.top - c.top < 22);
    }

    /* ---------- modifica in linea ---------- */
    function editor(span, valore, salva, placeholder = '') {
      s.editing = true;
      const inp = document.createElement('input');
      inp.className = 'dt-edit';
      inp.value = valore;
      inp.placeholder = placeholder;
      inp.spellcheck = false;
      inp.autocapitalize = 'off';
      inp.size = Math.max(6, valore.length + 1, placeholder.length);
      span.replaceWith(inp);
      inp.focus();
      inp.select();
      let finito = false;
      const fine = (ok) => {
        if (finito) return;
        finito = true;
        s.editing = false;
        if (ok) {
          try { salva(inp.value); } catch (e) { log('err', `${e.name}: ${e.message}`); }
        }
        renderAlbero();
        renderStili();
        onModifica();
      };
      inp.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); fine(true); }
        else if (e.key === 'Escape') { e.preventDefault(); fine(false); }
      });
      inp.addEventListener('input', () => { inp.size = Math.max(6, inp.value.length + 1); });
      inp.addEventListener('blur', () => fine(true));
      for (const t of ['click', 'dblclick', 'pointerdown']) inp.addEventListener(t, (e) => e.stopPropagation());
    }

    /** 'type="text"' -> setAttribute('type', 'text'). Testo vuoto = attributo cancellato. */
    function scriviAttributo(el, vecchio, testo) {
      const t = testo.trim();
      if (!t) { if (vecchio) el.removeAttribute(vecchio); return; }
      const m = /^([^\s"'=<>/]+)(?:\s*=\s*(?:"([^"]*)"?|'([^']*)'?|([^\s"']*)))?/.exec(t);
      if (!m) return;
      const nome = m[1].toLowerCase(), val = m[2] ?? m[3] ?? m[4] ?? '';
      if (vecchio && vecchio !== nome) el.removeAttribute(vecchio);
      el.setAttribute(nome, val);
    }

    function modifica(span) {
      const d = s.refs[span.dataset.e];
      if (!d) return;
      if (d.nodo) {
        editor(span, d.nodo.data.trim(), (v) => { d.nodo.data = v; });
      } else if (d.el) {
        const v = d.el.getAttribute(d.nome);
        editor(span, v === '' ? d.nome : `${d.nome}="${v}"`, (t) => scriviAttributo(d.el, d.nome, t));
      }
    }

    function nuovoAttributo() {
      const el = s.sel;
      if (!el || el.nodeType !== 1) return;
      const r = [...albero.querySelectorAll('.dt-riga.sel')][0];
      if (!r) return;
      const segnaposto = document.createElement('span');
      const chiusa = r.querySelectorAll(':scope > .dt-tag')[1]; // il ">" di apertura: [0] e' "<tag"
      r.insertBefore(document.createTextNode(' '), chiusa);
      r.insertBefore(segnaposto, chiusa);
      editor(segnaposto, '', (t) => scriviAttributo(el, null, t), 'nome="valore"');
    }

    albero.addEventListener('click', (e) => {
      const r = e.target.closest('.dt-riga');
      if (!r) return;
      const n = s.refs[r.dataset.n];
      if (e.target.closest('.dt-freccia') && n.nodeType === 1 && figliUtili(n).length) {
        if (s.chiusi.has(n)) s.chiusi.delete(n); else s.chiusi.add(n);
        renderAlbero();
        return;
      }
      const mod = e.target.closest('.dt-mod');
      const gia = s.sel === n;
      if (!gia) seleziona(n);
      if (mod && gia) modifica(mod);
    });
    albero.addEventListener('dblclick', (e) => {
      const mod = e.target.closest('.dt-mod');
      if (mod && mod.isConnected) modifica(mod);
    });
    albero.addEventListener('mouseover', (e) => {
      const r = e.target.closest('.dt-riga');
      if (r && !s.editing) { evidenziato = elementoDi(s.refs[r.dataset.n]); ridisegna(); }
    });
    albero.addEventListener('mouseleave', () => evidenzia(elementoDi(s.sel)));

    q('[data-az=elimina]').addEventListener('click', () => {
      const n = s.sel;
      if (!nelSito(n)) return;
      const padre = n.parentElement;
      n.remove();
      s.sel = padre && padre !== radice ? padre : null;
      renderAlbero();
      renderStili();
      onModifica();
    });
    q('[data-az=attr]').addEventListener('click', nuovoAttributo);

    /* =====================================================================
       STILI
       ===================================================================== */
    function dichiarazioni(decl) {
      const attive = [];
      for (const parte of dividiDichiarazioni(decl.cssText)) {
        const i = parte.indexOf(':');
        if (i < 0) continue;
        let val = parte.slice(i + 1).trim();
        const imp = /!\s*important$/i.test(val);
        if (imp) val = val.replace(/\s*!\s*important$/i, '');
        attive.push({ decl, prop: parte.slice(0, i).trim(), val, imp });
      }
      // le proprieta' tolte con la spunta restano visibili (barrate) finche' non si riattivano
      const off = (s.disattivati.get(decl) || []).filter((o) => !attive.some((a) => a.prop === o.prop));
      s.disattivati.set(decl, off);
      return attive.concat(off.map((o) => ({ ...o, decl, off: true })));
    }

    function blocco(titolo, decl, origine) {
      const righe = dichiarazioni(decl).map((d) => {
        const i = sref(d);
        return `<div class="dt-decl${d.off ? ' off' : ''}">` +
          `<input type="checkbox" data-s="${i}" ${d.off ? '' : 'checked'} aria-label="Attiva ${esc(d.prop)}">` +
          `<span class="dt-prop dt-smod" data-s="${i}" data-campo="prop">${esc(d.prop)}</span>: ` +
          `<span class="dt-val dt-smod" data-s="${i}" data-campo="val">${esc(d.val)}${d.imp ? ' !important' : ''}</span>;</div>`;
      }).join('');
      return `<div class="dt-regola"><div class="dt-sel"><span>${esc(titolo)}</span> {<small>${esc(origine)}</small></div>${righe}` +
        `<div class="dt-decl"><span class="dt-add dt-smod" data-s="${sref({ decl, nuova: true })}" data-campo="nuova" title="Aggiungi una proprietà">+ aggiungi</span></div><div>}</div></div>`;
    }

    function renderStili() {
      if (s.editing) { s.sporco = true; return; }
      const el = elementoDi(s.sel);
      s.srefs = [];
      if (!el || !nelSito(el)) {
        stili.innerHTML = '<p class="dt-vuoto">Seleziona un elemento per vedere il suo CSS.</p>';
        return;
      }
      let h = blocco('element.style', el.style, 'scritto nell\'HTML');
      let regole = [];
      try { regole = [...foglio().cssRules]; } catch { /* foglio non pronto */ }
      for (const r of regole.reverse()) {
        if (!r.selectorText) continue;
        let ok = false;
        try { ok = el.matches(r.selectorText); } catch { /* selettore non valido */ }
        if (ok) h += blocco(mostraSelettore(r.selectorText), r.style, 'stile.css');
      }
      stili.innerHTML = h;
    }

    function salvaDichiarazione(d, campo, testo) {
      const t = testo.trim().replace(/;+\s*$/, '');
      const off = s.disattivati.get(d.decl) || [];
      if (campo === 'nuova') {
        for (const parte of dividiDichiarazioni(t)) {
          const i = parte.indexOf(':');
          if (i < 0) continue;
          const val = parte.slice(i + 1).trim();
          d.decl.setProperty(parte.slice(0, i).trim(), val.replace(/\s*!\s*important$/i, ''), /!\s*important$/i.test(val) ? 'important' : '');
        }
        return;
      }
      if (d.off) s.disattivati.set(d.decl, off.filter((o) => o.prop !== d.prop));
      if (campo === 'prop') {
        d.decl.removeProperty(d.prop);
        if (t) d.decl.setProperty(t, d.val, d.imp ? 'important' : '');
      } else if (!t) {
        d.decl.removeProperty(d.prop);
      } else {
        const imp = /!\s*important$/i.test(t);
        const val = t.replace(/\s*!\s*important$/i, '');
        d.decl.setProperty(d.prop, val, imp ? 'important' : '');
        if (!d.decl.getPropertyValue(d.prop)) log('warn', `Valore non valido per ${d.prop}: "${val}"`);
      }
    }

    stili.addEventListener('click', (e) => {
      const span = e.target.closest('.dt-smod');
      if (!span) return;
      const d = s.srefs[span.dataset.s];
      const campo = span.dataset.campo;
      const valore = campo === 'nuova' ? '' : campo === 'prop' ? d.prop : d.val + (d.imp ? ' !important' : '');
      editor(span, valore, (t) => salvaDichiarazione(d, campo, t), campo === 'nuova' ? 'es. color: red' : '');
    });
    stili.addEventListener('change', (e) => {
      if (e.target.type !== 'checkbox') return;
      const d = s.srefs[e.target.dataset.s];
      const off = s.disattivati.get(d.decl) || [];
      if (e.target.checked) {
        d.decl.setProperty(d.prop, d.val, d.imp ? 'important' : '');
        s.disattivati.set(d.decl, off.filter((o) => o.prop !== d.prop));
      } else {
        d.decl.removeProperty(d.prop);
        off.push({ prop: d.prop, val: d.val, imp: d.imp });
        s.disattivati.set(d.decl, off);
      }
      renderStili();
      onModifica();
    });

    /* =====================================================================
       CONSOLE
       ===================================================================== */
    function formatta(v) {
      if (v === undefined) return 'undefined';
      if (typeof v === 'string') return JSON.stringify(v);
      if (typeof v === 'function') return `ƒ ${v.name || ''}()`;
      if (v instanceof Element) return v.outerHTML.length > 120 ? v.outerHTML.slice(0, 117) + '…' : v.outerHTML;
      try { return JSON.stringify(v) ?? String(v); } catch { return String(v); }
    }

    function log(tipo, testo) {
      const r = document.createElement('div');
      r.className = `dt-msg ${tipo}`;
      r.textContent = testo;
      logEl.append(r);
      while (logEl.childElementCount > 200) logEl.firstElementChild.remove();
      logEl.scrollTop = logEl.scrollHeight;
      if ((tipo === 'err' || tipo === 'warn') && q('[data-vista=console]').hidden) q('[data-tab=console]').classList.add('errore');
    }

    q('.dt-prompt').addEventListener('submit', (e) => {
      e.preventDefault();
      const codice = cmd.value;
      if (!codice.trim()) return;
      s.storia.push(codice);
      s.posStoria = s.storia.length;
      cmd.value = '';
      log('in', codice);
      try {
        const r = (0, eval)(codice); // eval globale: come la console vera
        log('out', formatta(r));
      } catch (err) {
        log('err', `Uncaught ${err.name}: ${err.message}`);
      }
      onModifica();
    });
    cmd.addEventListener('keydown', (e) => {
      if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
      e.preventDefault();
      s.posStoria = Math.max(0, Math.min(s.storia.length, s.posStoria + (e.key === 'ArrowUp' ? -1 : 1)));
      cmd.value = s.storia[s.posStoria] ?? '';
    });

    // errori della pagina (es. onclick con una funzione che non esiste)
    window.addEventListener('error', (e) => {
      const m = String(e.message || 'Errore');
      log('err', m.startsWith('Uncaught') ? m : `Uncaught ${m}`);
    });
    for (const [metodo, tipo] of [['log', 'out'], ['warn', 'warn'], ['error', 'err']]) {
      const orig = console[metodo].bind(console);
      console[metodo] = (...a) => { orig(...a); log(tipo, a.map((x) => (typeof x === 'string' ? x : formatta(x))).join(' ')); };
    }

    /* ---------- schede ---------- */
    function apriScheda(nome) {
      for (const t of pannello.querySelectorAll('.dt-tab')) t.classList.toggle('attivo', t.dataset.tab === nome);
      for (const v of pannello.querySelectorAll('.dt-vista')) v.hidden = v.dataset.vista !== nome;
      if (nome === 'console') { q('[data-tab=console]').classList.remove('errore', 'lampeggia'); cmd.focus({ preventScroll: true }); }
    }
    for (const t of pannello.querySelectorAll('.dt-tab')) t.addEventListener('click', () => apriScheda(t.dataset.tab));

    /* =====================================================================
       SELETTORE (freccina): tocca un elemento nella pagina per trovarlo nel codice
       ===================================================================== */
    const picker = q('.dt-picker');
    function attivaPicker(on) {
      s.picker = on;
      picker.classList.toggle('attivo', on);
      picker.setAttribute('aria-pressed', String(on));
      radice.classList.toggle('dt-picking', on);
      if (!on) evidenzia(elementoDi(s.sel));
    }
    picker.addEventListener('click', () => attivaPicker(!s.picker));
    radice.addEventListener('mousemove', (e) => {
      if (s.picker && e.target !== radice) { evidenziato = e.target; ridisegna(); }
    });
    // pointerdown arriva anche sui pulsanti disattivati (il click no)
    window.addEventListener('pointerdown', (e) => {
      if (!s.picker) return;
      const t = document.elementFromPoint(e.clientX, e.clientY);
      if (!nelSito(t)) return;
      e.preventDefault();
      e.stopPropagation();
      s.mangiaClick = true;
      setTimeout(() => { s.mangiaClick = false; }, 600);
      attivaPicker(false);
      apriScheda('elementi');
      seleziona(t, true);
    }, true);
    window.addEventListener('click', (e) => {
      if (s.mangiaClick && radice.contains(e.target)) { e.preventDefault(); e.stopPropagation(); s.mangiaClick = false; }
    }, true);

    /* ---------- sincronizzazione con le modifiche (anche dal vero F12) ---------- */
    let inAttesa = false;
    new MutationObserver(() => {
      if (inAttesa) return;
      inAttesa = true;
      requestAnimationFrame(() => { inAttesa = false; renderAlbero(); renderStili(); });
    }).observe(radice, { subtree: true, childList: true, attributes: true, characterData: true });

    setInterval(() => {
      if (s.sporco && !s.editing) { s.sporco = false; renderAlbero(); renderStili(); }
      ridisegna();
    }, 250);
    window.addEventListener('resize', ridisegna);
    radice.addEventListener('scroll', ridisegna);

    return {
      /** Nuova pagina caricata nel riquadro: azzera selezione, albero e console. */
      reset() {
        s.sel = null;
        s.chiusi = new WeakSet();
        logEl.replaceChildren();
        q('[data-tab=console]').classList.remove('errore');
        attivaPicker(false);
        apriScheda('elementi');
        renderAlbero();
        renderStili();
      },
      log,
      apriScheda,
      lampeggiaConsole(on) { q('[data-tab=console]').classList.toggle('lampeggia', on); },
    };
  }

  window.Ispeziona = { crea };
})();
