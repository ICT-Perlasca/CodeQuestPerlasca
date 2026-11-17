/**
 * "Modifica il gioco": fisica della palla che rimbalza e missioni.
 * File separato (niente CDN) usato dalla pagina giochi/modifica-gioco.php;
 * si puo' caricare anche in node per verificare che le missioni siano risolvibili.
 *
 * Il campo e' 400 x 300 unita'; un "passo" = un fotogramma (60 al secondo).
 */
(function (globale) {
  'use strict';

  const W = 400, H = 300;
  const PARTENZA = { x: 50, y: 250 };
  const VEL_MAX = 25;        // oltre, la palla "esplode" con rimbalzo > 1
  const ATTRITO_PAVIMENTO = 0.97;

  /** Valori iniziali delle variabili mostrate nel codice. */
  const VARIABILI = {
    gravita:     { tipo: 'num', val: 0.5,  min: 0,   max: 2,   step: 0.05, nota: 'quanto tira verso il basso' },
    rimbalzo:    { tipo: 'num', val: 0.7,  min: 0,   max: 1.2, step: 0.05, nota: '1 = rimbalzo perfetto, 0 = si spiaccica' },
    velocitaX:   { tipo: 'num', val: 4,    min: -15, max: 15,  step: 0.5,  nota: 'spinta orizzontale (negativo = a sinistra)' },
    velocitaY:   { tipo: 'num', val: -8,   min: -20, max: 20,  step: 0.5,  nota: 'spinta verticale (negativo = verso l\'alto)' },
    raggio:      { tipo: 'num', val: 15,   min: 5,   max: 80,  step: 1,    nota: 'grandezza della palla' },
    colorePalla: { tipo: 'col', val: '#ffd84d', nota: 'colore della palla' },
    coloreSfondo:{ tipo: 'col', val: '#0e1430', nota: 'colore dello sfondo' },
    scia:        { tipo: 'bool', val: false, nota: 'true = la palla lascia la scia' },
  };

  const rosso = (hex) => {
    const n = parseInt(String(hex).slice(1), 16);
    const r = n >> 16, g = (n >> 8) & 255, b = n & 255;
    return r >= 170 && g <= 110 && b <= 110;
  };

  /**
   * Missioni in ordine: le prime N sono quelle dell'Open Day (TIPI_GIORNATA in inc/giornate.php).
   *   blocca    : variabili fissate dalla missione (non modificabili)
   *   canestro  : {x, y, r} cerchio da centrare con il centro della palla
   *   muro      : {x, y, w, h} ostacolo
   *   ok(v, st) : condizione di vittoria (v = variabili, st = statistiche dall'ultimo lancio)
   */
  const MISSIONI = [
    { titolo: 'Cambia colore',
      testo: 'Ogni cosa che vedi sullo schermo e\' scritta nel codice. Trova la variabile <b>colorePalla</b> e rendi la palla <b>rossa</b>.',
      ok: (v) => rosso(v.colorePalla) },
    { titolo: 'Sulla Luna',
      testo: 'Sulla Luna la gravita\' e\' molto piu\' debole. Abbassa <b>gravita</b> (ma non a zero!) finche\' la palla scende lenta, poi premi <b>Lancia</b>.',
      ok: (v, st) => v.gravita > 0 && v.gravita <= 0.2 && st.pavimento >= 1 },
    { titolo: 'Canestro!',
      testo: 'Cambia <b>velocitaX</b> e <b>velocitaY</b> per centrare il canestro. La gravita\' qui e\' bloccata: prova, correggi, riprova... come un vero programmatore!',
      blocca: { gravita: 0.5 },
      canestro: { x: 300, y: 150, r: 22 },
      ok: (v, st) => st.centrato },
    { titolo: 'Tocca il cielo',
      testo: 'Fai toccare alla palla il bordo in alto. Basta una spinta verso l\'alto abbastanza forte (ricorda: verso l\'alto e\' <b>negativo</b>).',
      blocca: { gravita: 0.5 },
      ok: (v, st) => st.soffitto },
    { titolo: 'Palla gigante',
      testo: 'Trasforma la pallina in un pallone da spiaggia: <b>raggio</b> almeno 60.',
      ok: (v, st) => v.raggio >= 60 && st.pavimento >= 1 },
    { titolo: 'Rimbalzo perfetto',
      testo: 'Una palla che non perde mai energia: con quale valore di <b>rimbalzo</b> torna sempre alla stessa altezza? Falla rimbalzare 3 volte.',
      ok: (v, st) => v.gravita > 0 && v.rimbalzo === 1 && st.pavimento >= 3 },
    { titolo: 'Palla di piombo',
      testo: 'Ora il contrario: una palla che cade e resta attaccata a terra senza rimbalzare nemmeno un po\'.',
      ok: (v, st) => v.rimbalzo === 0 && st.pavimento >= 1 },
    { titolo: 'Nello spazio',
      testo: 'Senza gravita\' la palla va dritta finche\' non sbatte. Toglila del tutto e fai toccare alla palla <b>tutti e 4 i bordi</b>.',
      ok: (v, st) => v.gravita === 0 && st.bordi.size === 4 },
    { titolo: 'Dietro il muro',
      testo: 'Il canestro e\' nascosto dietro un muro: serve un tiro a parabola che lo scavalchi.',
      blocca: { gravita: 0.5, raggio: 12 },
      muro: { x: 190, y: 110, w: 20, h: 190 },
      canestro: { x: 320, y: 240, r: 22 },
      ok: (v, st) => st.centrato },
    { titolo: 'Tiro da campione',
      testo: 'Canestro piccolo, in alto, con gravita\' forte. Suggerimento: puoi anche usare il rimbalzo sul muro di destra!',
      blocca: { gravita: 0.8, raggio: 10 },
      canestro: { x: 340, y: 60, r: 14 },
      ok: (v, st) => st.centrato },
  ];

  /** Stato della palla appena lanciata + statistiche per le missioni. */
  function lancia(v) {
    return {
      x: PARTENZA.x, y: PARTENZA.y, vx: v.velocitaX, vy: v.velocitaY,
      fermo: 0,                       // fotogrammi passati quasi immobile a terra
      st: { pavimento: 0, soffitto: false, bordi: new Set(), centrato: false },
    };
  }

  const limita = (n) => Math.max(-VEL_MAX, Math.min(VEL_MAX, n));

  /** Un fotogramma: e' esattamente il codice mostrato ai ragazzi, piu' i bordi. */
  function passo(p, v, m) {
    const r = v.raggio, e = v.rimbalzo;
    p.vy = limita(p.vy + v.gravita);
    p.vx = limita(p.vx);
    p.x += p.vx;
    p.y += p.vy;

    if (p.y + r > H) {                         // pavimento
      p.y = H - r;
      if (p.vy > 0) {
        p.vy = -p.vy * e;
        if (Math.abs(p.vy) < 1) p.vy = 0;      // troppo piano: si ferma
        p.st.pavimento++;
        p.st.bordi.add('giu');
      }
      p.vx *= ATTRITO_PAVIMENTO;
    }
    if (p.y - r < 0) { p.y = r; if (p.vy < 0) p.vy = -p.vy * e; p.st.soffitto = true; p.st.bordi.add('su'); }
    if (p.x + r > W) { p.x = W - r; if (p.vx > 0) p.vx = -p.vx * e; p.st.bordi.add('dx'); }
    if (p.x - r < 0) { p.x = r; if (p.vx < 0) p.vx = -p.vx * e; p.st.bordi.add('sx'); }

    if (m && m.muro) urtaMuro(p, r, e, m.muro);
    if (m && m.canestro) {
      const c = m.canestro;
      if (Math.hypot(p.x - c.x, p.y - c.y) < c.r) p.st.centrato = true;
    }

    const aTerra = p.y + r >= H - 0.01;
    p.fermo = aTerra && p.vy === 0 && Math.abs(p.vx) < 0.05 ? p.fermo + 1 : 0;
  }

  /** Palla contro rettangolo: la spinge fuori e la fa rimbalzare. */
  function urtaMuro(p, r, e, q) {
    const cx = Math.max(q.x, Math.min(p.x, q.x + q.w));
    const cy = Math.max(q.y, Math.min(p.y, q.y + q.h));
    let dx = p.x - cx, dy = p.y - cy;
    const d = Math.hypot(dx, dy);
    if (d >= r) return;
    if (d === 0) { // centro dentro il muro: esce dal lato piu' vicino
      const lati = [[p.x - q.x, -1, 0], [q.x + q.w - p.x, 1, 0], [p.y - q.y, 0, -1]];
      lati.sort((a, b) => a[0] - b[0]);
      [, dx, dy] = lati[0];
    } else { dx /= d; dy /= d; }
    p.x = cx + dx * r;
    p.y = cy + dy * r;
    const vn = p.vx * dx + p.vy * dy;
    if (vn < 0) { p.vx -= (1 + e) * vn * dx; p.vy -= (1 + e) * vn * dy; }
  }

  const API = { W, H, PARTENZA, VARIABILI, MISSIONI, lancia, passo };
  if (typeof module !== 'undefined' && module.exports) module.exports = API;
  else globale.PallaFisica = API;
})(this);
