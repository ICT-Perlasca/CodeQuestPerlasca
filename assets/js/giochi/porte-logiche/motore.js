/**
 * Porte logiche: livelli e logica dei circuiti.
 * Usato da giochi/porte-logiche.php; si carica anche in node per verificare i livelli.
 *
 * Un livello e' un circuito:
 *   ingressi : nomi degli interruttori (bit), es. ['A', 'B']
 *   iniziale : interruttori accesi all'inizio, es. { A: 1 }
 *   porte    : in ordine (ogni porta usa solo ingressi o porte precedenti)
 *              { id, tipo: 'AND'|'OR'|'NOT'|'XOR'|'?', da: [...], opzioni: [...] se tipo '?' }
 *   uscite   : lampadine { id, da, etichetta }
 * tipo di sfida:
 *   accendi : accendere tutte le lampadine
 *   tutte   : trovare TUTTE le combinazioni di interruttori che accendono la lampadina
 *   scegli  : scegliere le porte mancanti (?) perche' la lampadina segua la tabella `vuole`
 *   numeri  : le lampadine formano un numero binario: mostrare tutti i numeri di `numeri`
 *   indovina: porta nascosta: provare tutte le combinazioni, poi scegliere la risposta giusta
 */
(function (globale) {
  'use strict';

  const REGOLE = {
    AND:  { nome: 'AND',  it: 'E',      f: (x) => x.every(Boolean), spiega: 'si accende solo se TUTTI gli ingressi sono 1' },
    OR:   { nome: 'OR',   it: 'O',      f: (x) => x.some(Boolean),  spiega: 'si accende se ALMENO UN ingresso e\' 1' },
    NOT:  { nome: 'NOT',  it: 'NON',    f: (x) => !x[0],            spiega: 'fa il contrario: 1 diventa 0, 0 diventa 1' },
    XOR:  { nome: 'XOR',  it: 'O... O', f: (x) => x.filter(Boolean).length % 2 === 1, spiega: 'si accende se gli ingressi sono DIVERSI' },
    NAND: { nome: 'NAND', it: 'NON E',  f: (x) => !x.every(Boolean), spiega: 'il contrario di AND: si spegne solo se tutti sono 1' },
    FILO: { nome: 'filo', it: 'filo',   f: (x) => !!x[0],           spiega: 'lascia passare il bit com\'e\'' },
  };

  /*
   * Campi in piu' dei livelli:
   *   nascosta (sulla porta) : "scatola misteriosa", la forma si vede solo a livello risolto
   *   scopre                 : porta che il livello fa scoprire: da li' in poi compare tra le regole
   *   svela                  : spiegazione mostrata DOPO aver risolto (i primi livelli non spiegano niente prima)
   *   indovina               : provare tutte le combinazioni e poi scegliere il comportamento giusto
   *                            risposte: [{ testo, giusta }]
   */
  const LIVELLI = [
    // ---- 1-3: nessuna spiegazione, si scopre giocando ----
    { titolo: 'Scatola misteriosa',
      testo: 'Accendi la lampadina.',
      tipo: 'accendi', ingressi: ['A', 'B'],
      porte: [{ id: 'g1', tipo: 'AND', da: ['A', 'B'], nascosta: true }],
      uscite: [{ id: 'L', da: 'g1' }],
      scopre: 'AND',
      svela: 'Dentro la scatola c\'era una porta <b>AND</b> ("E"): si accende solo se A <b>e</b> B sono entrambi a 1.' },
    { titolo: 'Al contrario',
      testo: 'Accendi la lampadina.',
      tipo: 'accendi', ingressi: ['A'], iniziale: { A: 1 },
      porte: [{ id: 'g1', tipo: 'NOT', da: ['A'], nascosta: true }],
      uscite: [{ id: 'L', da: 'g1' }],
      scopre: 'NOT',
      svela: 'Era una porta <b>NOT</b> ("NON"): fa sempre il contrario. Entra 1, esce 0. Entra 0, esce 1.' },
    { titolo: 'Quanti modi?',
      testo: 'In quanti modi diversi si accende la lampadina? Trovali tutti.',
      tipo: 'tutte', ingressi: ['A', 'B'],
      porte: [{ id: 'g1', tipo: 'OR', da: ['A', 'B'], nascosta: true }],
      uscite: [{ id: 'L', da: 'g1' }],
      scopre: 'OR',
      svela: 'Era una porta <b>OR</b> ("O"): basta che <b>almeno uno</b> sia a 1. Tre modi su quattro!' },

    // ---- da qui le porte sono visibili e le regole stanno nel pannello ----
    { titolo: 'Doppio controllo',
      testo: 'Adesso le porte si vedono: guarda le regole che hai scoperto e accendi la lampadina.',
      tipo: 'accendi', ingressi: ['A', 'B', 'C'], iniziale: { C: 1 },
      porte: [
        { id: 'g1', tipo: 'OR', da: ['A', 'B'] },
        { id: 'g2', tipo: 'NOT', da: ['C'] },
        { id: 'g3', tipo: 'AND', da: ['g1', 'g2'] },
      ],
      uscite: [{ id: 'L', da: 'g3' }],
      svela: 'Serviva A <b>o</b> B acceso, <b>e</b> C spento. I computer fanno controlli cosi\' milioni di volte al secondo.' },
    { titolo: 'Il codice segreto',
      testo: 'Un solo codice accende la lampadina. Ragiona porta per porta, partendo dalla lampadina e tornando indietro.',
      tipo: 'accendi', ingressi: ['A', 'B', 'C'],
      porte: [
        { id: 'n1', tipo: 'NOT', da: ['B'] },
        { id: 'g1', tipo: 'AND', da: ['A', 'n1'] },
        { id: 'g2', tipo: 'AND', da: ['g1', 'C'] },
      ],
      uscite: [{ id: 'L', da: 'g2' }],
      svela: 'Il codice era <b>101</b>: in binario vale 5. Ogni codice di bit e\' anche un numero!' },
    { titolo: 'Che scatola e\'?',
      testo: 'Una porta che non conosci. Prova tutte le combinazioni, guarda la tabella e scegli come si comporta.',
      tipo: 'indovina', ingressi: ['A', 'B'],
      porte: [{ id: 'g1', tipo: 'XOR', da: ['A', 'B'], nascosta: true }],
      uscite: [{ id: 'L', da: 'g1' }],
      risposte: [
        { testo: 'si accende se sono accesi tutti e due' },
        { testo: 'si accende se almeno uno e\' acceso' },
        { testo: 'si accende se sono DIVERSI', giusta: true },
        { testo: 'si accende se sono UGUALI' },
      ],
      scopre: 'XOR',
      svela: 'E\' la porta <b>XOR</b> ("o l\'uno o l\'altro, ma non tutti e due"). Ti servira\' piu\' avanti!' },
    { titolo: 'Un\'altra scatola',
      testo: 'Altra porta sconosciuta: provala tutta prima di rispondere.',
      tipo: 'indovina', ingressi: ['A', 'B'],
      porte: [{ id: 'g1', tipo: 'NAND', da: ['A', 'B'], nascosta: true }],
      uscite: [{ id: 'L', da: 'g1' }],
      risposte: [
        { testo: 'si accende se sono accesi tutti e due' },
        { testo: 'si spegne solo se sono accesi tutti e due', giusta: true },
        { testo: 'si accende se sono spenti tutti e due' },
        { testo: 'fa il contrario di A' },
      ],
      svela: 'Si chiama <b>NAND</b>: e\' un AND seguito da un NOT. Con sole porte NAND si puo\' costruire un computer intero!' },
    { titolo: 'Scegli la porta',
      testo: 'Manca una porta: toccala per cambiarla. La lampadina deve seguire la tabella in tutte le righe.',
      tipo: 'scegli', ingressi: ['A', 'B'],
      porte: [
        { id: 'g1', tipo: '?', opzioni: ['AND', 'OR', 'XOR'], da: ['A', 'B'] },
        { id: 'g2', tipo: 'NOT', da: ['g1'] },
      ],
      uscite: [{ id: 'L', da: 'g2' }],
      vuole: (x) => !x.A && !x.B,
      svela: 'Con OR seguito da NOT la lampadina si accende solo quando e\' tutto spento.' },
    { titolo: 'Il pezzo mancante',
      testo: 'La lampadina deve accendersi solo con A spento e B acceso. Che pezzo serve?',
      tipo: 'scegli', ingressi: ['A', 'B'],
      porte: [
        { id: 'g1', tipo: '?', opzioni: ['FILO', 'NOT'], da: ['A'] },
        { id: 'g2', tipo: 'AND', da: ['g1', 'B'] },
      ],
      uscite: [{ id: 'L', da: 'g2' }],
      vuole: (x) => !x.A && x.B },
    { titolo: 'Tre interruttori',
      testo: 'Con 3 interruttori le combinazioni sono 8, da 000 a 111. Quali accendono la lampadina?',
      tipo: 'tutte', ingressi: ['A', 'B', 'C'],
      porte: [
        { id: 'g1', tipo: 'OR', da: ['A', 'B'] },
        { id: 'g2', tipo: 'AND', da: ['g1', 'C'] },
      ],
      uscite: [{ id: 'L', da: 'g2' }] },
    { titolo: 'La cassaforte',
      testo: 'Quattro bit, un solo codice giusto. Riesci a trovarlo senza provarli tutti?',
      tipo: 'accendi', ingressi: ['A', 'B', 'C', 'D'],
      porte: [
        { id: 'n1', tipo: 'NOT', da: ['B'] },
        { id: 'n2', tipo: 'NOT', da: ['D'] },
        { id: 'g1', tipo: 'AND', da: ['A', 'n1'] },
        { id: 'g2', tipo: 'AND', da: ['C', 'n2'] },
        { id: 'g3', tipo: 'AND', da: ['g1', 'g2'] },
      ],
      uscite: [{ id: 'L', da: 'g3' }],
      svela: 'Codice <b>1010</b> = 10 in decimale.' },
    { titolo: 'La votazione',
      testo: 'Tre amici votano con un interruttore. Trova tutte le combinazioni che accendono la lampadina: capisci la regola?',
      tipo: 'tutte', ingressi: ['A', 'B', 'C'],
      porte: [
        { id: 'g1', tipo: 'AND', da: ['A', 'B'] },
        { id: 'g2', tipo: 'AND', da: ['A', 'C'] },
        { id: 'g3', tipo: 'AND', da: ['B', 'C'] },
        { id: 'g4', tipo: 'OR', da: ['g1', 'g2'] },
        { id: 'g5', tipo: 'OR', da: ['g4', 'g3'] },
      ],
      uscite: [{ id: 'L', da: 'g5' }],
      svela: 'Vince la <b>maggioranza</b>: si accende quando almeno 2 su 3 votano 1.' },
    { titolo: 'Fatto in casa',
      testo: 'Questo circuito usa solo OR, AND e NOT. Trova quando si accende: a quale porta che conosci assomiglia?',
      tipo: 'tutte', ingressi: ['A', 'B'],
      porte: [
        { id: 'g1', tipo: 'OR', da: ['A', 'B'] },
        { id: 'g2', tipo: 'AND', da: ['A', 'B'] },
        { id: 'g3', tipo: 'NOT', da: ['g2'] },
        { id: 'g4', tipo: 'AND', da: ['g1', 'g3'] },
      ],
      uscite: [{ id: 'L', da: 'g4' }],
      svela: 'Si comporta come uno <b>XOR</b>! Le porte complicate si costruiscono con quelle semplici.' },
    { titolo: 'Due pezzi mancanti',
      testo: 'Stavolta mancano due porte. La lampadina deve accendersi solo se A e B sono diversi.',
      tipo: 'scegli', ingressi: ['A', 'B'],
      porte: [
        { id: 'g1', tipo: '?', opzioni: ['AND', 'OR'], da: ['A', 'B'] },
        { id: 'g2', tipo: '?', opzioni: ['AND', 'OR'], da: ['A', 'B'] },
        { id: 'g3', tipo: 'NOT', da: ['g2'] },
        { id: 'g4', tipo: 'AND', da: ['g1', 'g3'] },
      ],
      uscite: [{ id: 'L', da: 'g4' }],
      vuole: (x) => x.A !== x.B },

    // ---- livelli difficili: all'Open Day si giocano dopo le scatole misteriose (inc/giornate.php) ----
    { titolo: 'Specchi',
      testo: 'Ogni NOT ribalta il bit, come uno specchio. Un solo codice accende la lampadina: trovalo contando gli specchi, non provando a caso!',
      tipo: 'accendi', ingressi: ['A', 'B', 'C'],
      porte: [
        { id: 'a1', tipo: 'NOT', da: ['A'] }, { id: 'a2', tipo: 'NOT', da: ['a1'] },
        { id: 'b1', tipo: 'NOT', da: ['B'] }, { id: 'b2', tipo: 'NOT', da: ['b1'] }, { id: 'b3', tipo: 'NOT', da: ['b2'] },
        { id: 'c1', tipo: 'NOT', da: ['C'] },
        { id: 'g1', tipo: 'AND', da: ['a2', 'b3'] },
        { id: 'g2', tipo: 'AND', da: ['g1', 'c1'] },
      ],
      uscite: [{ id: 'L', da: 'g2' }],
      svela: 'Due NOT si annullano, tre NOT valgono uno. Il codice era <b>100</b> = 4.' },
    { titolo: 'Due lampadine',
      testo: 'Devi accendere <b>tutte e due</b> le lampadine insieme. Attento: quello che aiuta una puo\' spegnere l\'altra!',
      tipo: 'accendi', ingressi: ['A', 'B', 'C'],
      porte: [
        { id: 'o1', tipo: 'OR', da: ['A', 'B'] }, { id: 'nc', tipo: 'NOT', da: ['C'] }, { id: 'l1', tipo: 'AND', da: ['o1', 'nc'] },
        { id: 'na', tipo: 'NOT', da: ['A'] }, { id: 'o2', tipo: 'OR', da: ['B', 'C'] }, { id: 'l2', tipo: 'AND', da: ['na', 'o2'] },
      ],
      uscite: [{ id: 'L1', da: 'l1' }, { id: 'L2', da: 'l2' }],
      svela: 'Una sola combinazione su 8: <b>010</b>. A doveva essere spento per la seconda lampadina, C spento per la prima: restava solo B.' },
    { titolo: 'Tre pezzi mancanti',
      testo: 'Mancano tre pezzi. La lampadina deve accendersi quando A e B sono accesi tutti e due, oppure quando C e\' spento. Controlla tutte le righe!',
      tipo: 'scegli', ingressi: ['A', 'B', 'C'],
      porte: [
        { id: 'g0', tipo: '?', opzioni: ['FILO', 'NOT'], da: ['C'] },
        { id: 'g1', tipo: '?', opzioni: ['AND', 'OR'], da: ['A', 'B'] },
        { id: 'g2', tipo: '?', opzioni: ['AND', 'OR'], da: ['g1', 'g0'] },
      ],
      uscite: [{ id: 'L', da: 'g2' }],
      vuole: (x) => (x.A && x.B) || !x.C,
      svela: '(A <b>AND</b> B) <b>OR</b> (<b>NOT</b> C): hai tradotto una frase in un circuito, proprio come fa un programmatore con un <i>if</i>.' },
    { titolo: 'Il guardiano',
      testo: 'Un cancello con 4 interruttori e <b>due</b> codici che lo aprono. Trovali tutti e due: ci sono 16 combinazioni, ma ragionando bastano pochi tentativi.',
      tipo: 'tutte', ingressi: ['A', 'B', 'C', 'D'],
      porte: [
        { id: 'g1', tipo: 'OR', da: ['A', 'B'] }, { id: 'g2', tipo: 'AND', da: ['A', 'B'] }, { id: 'g3', tipo: 'NOT', da: ['g2'] },
        { id: 'g4', tipo: 'AND', da: ['g1', 'g3'] },
        { id: 'n', tipo: 'NOT', da: ['D'] }, { id: 'g5', tipo: 'AND', da: ['C', 'n'] },
        { id: 'g6', tipo: 'AND', da: ['g4', 'g5'] },
      ],
      uscite: [{ id: 'L', da: 'g6' }],
      svela: 'I codici erano <b>0110</b> e <b>1010</b>: A e B diversi, C acceso, D spento.' },

    { titolo: 'Il computer fa le somme',
      testo: 'Questo e\' un vero <b>sommatore</b>, il pezzo che dentro ogni processore fa i conti: somma A + B + C e scrive il risultato in binario sulle due lampadine. Fagli mostrare tutti i numeri da 0 a 3!',
      tipo: 'numeri', ingressi: ['A', 'B', 'C'], numeri: [0, 1, 2, 3],
      porte: [
        { id: 'x1', tipo: 'XOR', da: ['A', 'B'] },
        { id: 'a1', tipo: 'AND', da: ['A', 'B'] },
        { id: 's', tipo: 'XOR', da: ['x1', 'C'] },
        { id: 'a2', tipo: 'AND', da: ['x1', 'C'] },
        { id: 'r', tipo: 'OR', da: ['a1', 'a2'] },
      ],
      uscite: [{ id: 'L2', da: 'r', etichetta: 'vale 2' }, { id: 'L1', da: 's', etichetta: 'vale 1' }],
      svela: 'Hai usato un sommatore a 1 bit. Mettendone 64 in fila, il processore somma numeri giganti in un miliardesimo di secondo.' },
  ];

  /** Indice del livello che fa scoprire ogni porta (per mostrare le regole solo dopo). */
  const SCOPERTA = Object.fromEntries(LIVELLI.map((l, i) => [l.scopre, i]).filter(([k]) => k));

  /** Valore (0/1) di ogni ingresso, porta e uscita. scelte: { idPorta: 'AND' } per le porte '?'. */
  function valuta(liv, bit, scelte = {}) {
    const v = {};
    for (const n of liv.ingressi) v[n] = bit[n] ? 1 : 0;
    for (const p of liv.porte) {
      const tipo = p.tipo === '?' ? scelte[p.id] : p.tipo;
      v[p.id] = tipo ? (REGOLE[tipo].f(p.da.map((d) => v[d])) ? 1 : 0) : 0;
    }
    for (const u of liv.uscite) v[u.id] = v[u.da];
    return v;
  }

  /** Tutte le combinazioni degli ingressi, da 00...0 a 11...1. */
  function combinazioni(liv) {
    const n = liv.ingressi.length, out = [];
    for (let k = 0; k < 2 ** n; k++) {
      const bit = {};
      liv.ingressi.forEach((nome, i) => { bit[nome] = (k >> (n - 1 - i)) & 1; });
      out.push(bit);
    }
    return out;
  }

  const chiave = (liv, bit) => liv.ingressi.map((n) => (bit[n] ? 1 : 0)).join('');
  const accese = (liv, v) => liv.uscite.every((u) => v[u.id] === 1);
  /** Numero scritto dalle lampadine (la prima e' il bit piu' alto). */
  const numero = (liv, v) => liv.uscite.reduce((s, u) => s * 2 + v[u.id], 0);

  /** Le combinazioni che accendono la lampadina (per la sfida "tutte"). */
  const soluzioni = (liv) => combinazioni(liv).filter((b) => accese(liv, valuta(liv, b))).map((b) => chiave(liv, b));

  /** true se con queste scelte il circuito rispetta la tabella `vuole` su tutte le righe. */
  const sceltaGiusta = (liv, scelte) => liv.porte.every((p) => p.tipo !== '?' || scelte[p.id])
    && combinazioni(liv).every((b) => valuta(liv, b, scelte)[liv.uscite[0].id] === (liv.vuole(b) ? 1 : 0));

  const API = { REGOLE, LIVELLI, SCOPERTA, valuta, combinazioni, chiave, accese, numero, soluzioni, sceltaGiusta };
  if (typeof module !== 'undefined' && module.exports) module.exports = API;
  else globale.PorteLogiche = API;
})(this);
