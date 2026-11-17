/**
 * Hack the Web(site): i livelli. Ogni livello e' una pagina del sito del Dottor Quack
 * (che ha rapito le paperelle) da "aggirare" modificando HTML e CSS con Ispeziona.
 *
 *   titolo, testo : la missione (HTML)
 *   url           : indirizzo mostrato nella barra del browser finto
 *   html, css     : il sito. Nel CSS "body" indica la pagina (diventa #sito), gli altri
 *                   selettori vengono prefissati con "#sito " (vedi giochi/hack-website.php)
 *   monta(sito, api) : collega pulsanti e lucchetti. api.vinci(), api.avviso(testo, tipo)
 *   controlla(sito)  : facoltativo, chiamato di continuo: true = livello superato
 *   lista(sito)      : facoltativo, per il livello finale: [{testo, ok}] mostrata nella missione
 *   aiuti         : suggerimenti mostrati uno alla volta col pulsante "Aiuto"
 *   console       : true = serve la Console (la scheda lampeggia)
 *
 * I numeri dei livelli (da 1) usati in TIPI_GIORNATA (inc/giornate.php, hack_livelli) sono
 * le posizioni in questo array: aggiungere livelli SOLO in fondo.
 */
(function () {
  'use strict';

  /** Rosso "abbastanza": accetta red, #f00, crimson, tomato... */
  function eRosso(colore) {
    const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(colore);
    if (!m) return false;
    const [r, g, b] = m.slice(1).map(Number);
    return r >= 170 && g <= 110 && b <= 110;
  }

  /** Un lucchetto del sito: campo + pulsante, la risposta non tiene conto di maiuscole e spazi. */
  function lucchetto(sito, api, risposte, sbagliata = '❌ Parola sbagliata. Il Dottor Quack ride: "QUACK QUACK!"') {
    const form = sito.querySelector('form.lucchetto');
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const campo = form.querySelector('input:not([type=hidden])');
      const val = campo.value.trim().toLowerCase().replace(/\s+/g, '');
      if ([].concat(risposte).some((r) => r.toLowerCase() === val)) api.vinci();
      else api.avviso(sbagliata, 'ko');
    });
  }

  const LUCCHETTO = (etichetta, extra = '') => `
  <form class="lucchetto" autocomplete="off">
    <label>🔒 ${etichetta}</label>
    <div class="riga"><input name="parola" ${extra}><button>Apri</button></div>
  </form>`;

  /** CSS di base del sito del Dottor Quack, comune a tutti i livelli. */
  const BASE = `
body { background: #f4f1ea; color: #222; font-family: Georgia, "Times New Roman", serif; padding: 18px; min-height: 100%; }
header { border-bottom: 3px solid #5b2a86; padding-bottom: 8px; margin-bottom: 14px; }
h1 { margin: 0; font-size: 1.6rem; }
p { margin: 8px 0; }
button { font: inherit; font-weight: bold; padding: 8px 16px; border-radius: 8px; border: 0; background: #5b2a86; color: #fff; cursor: pointer; }
button:disabled { background: #bbb; color: #777; cursor: not-allowed; }
.lucchetto { margin-top: 14px; padding: 12px; border: 2px dashed #5b2a86; border-radius: 10px; background: #fff; }
.lucchetto label { display: block; font-weight: bold; margin-bottom: 6px; }
.lucchetto .riga { display: flex; gap: 8px; }
.lucchetto input { font: inherit; flex: 1; min-width: 0; padding: 6px 8px; border: 1px solid #999; border-radius: 6px; }
`;

  const LIVELLI = [
    /* ---------------- 1 ---------------- */
    {
      titolo: 'Cambia il titolo',
      url: 'www.dottorquack.it',
      testo: 'Questo è il sito del malvagio <b>Dottor Quack</b>, che ha rapito le paperelle. Per prima cosa facciamogli uno scherzo: ' +
        'cambia il titolo grande della pagina in <b>HACKERATO!</b><br><small>Seleziona <code>&lt;h1&gt;</code> nel pannello Ispeziona e tocca il testo per modificarlo.</small>',
      html: `
<header>
  <h1>Il sito del Dottor Quack</h1>
</header>
<p>Benvenuti nel mio sito. Le paperelle sono MIE e nessuno le libererà. Muahahah!</p>
<p>🦆🦆🦆 <i>(chiuse in gabbia)</i></p>`,
      css: '',
      controlla: (s) => /hacker/i.test(s.querySelector('h1')?.textContent || ''),
      aiuti: [
        'Nel pannello <b>Elementi</b> cerca la riga <code>&lt;h1&gt;Il sito del Dottor Quack&lt;/h1&gt;</code>.',
        'Tocca la riga per selezionarla, poi tocca il testo <i>Il sito del Dottor Quack</i>: diventa modificabile. Scrivi HACKERATO! e premi Invio.',
      ],
    },
    /* ---------------- 2 ---------------- */
    {
      titolo: 'Colora il titolo',
      url: 'www.dottorquack.it',
      testo: 'Il Dottor Quack odia i colori: il suo titolo è <b>grigio</b>. Fallo diventare <b style="color:#ff5c7a">rosso</b>!<br>' +
        '<small>Il colore non sta nell\'HTML ma nel <b>CSS</b>: seleziona il titolo e guarda la parte <b>Stili</b> del pannello.</small>',
      html: `
<header>
  <h1>Il sito del Dottor Quack</h1>
</header>
<p>Il grigio è il colore più bello del mondo. Gli altri colori sono VIETATI.</p>`,
      css: 'h1 { color: gray; }',
      controlla: (s) => { const h = s.querySelector('h1'); return !!h && eRosso(getComputedStyle(h).color); },
      aiuti: [
        'Seleziona <code>&lt;h1&gt;</code> e guarda la parte <b>Stili</b>: c\'è la regola <code>h1 { color: gray; }</code>.',
        'Tocca <code>gray</code> e scrivi <code>red</code>, poi premi Invio.',
      ],
    },
    /* ---------------- 3 ---------------- */
    {
      titolo: 'La scritta minuscola',
      url: 'www.dottorquack.it/regole',
      testo: 'In fondo alla pagina c\'è una scritta così piccola che non si legge (sembra una riga di puntini). ' +
        'Ingrandiscila fino a leggerla: almeno <b>16px</b>.',
      html: `
<header>
  <h1>Regolamento del covo</h1>
</header>
<p>1. Le paperelle restano in gabbia.</p>
<p>2. Vietato liberare le paperelle.</p>
<p class="piccolissimo">3. Se qualcuno legge questa riga, le paperelle possono fare il bagno nello stagno. Firmato: il Dottor Quack (che sperava nessuno la leggesse)</p>`,
      css: '.piccolissimo { font-size: 2px; color: #555; }',
      controlla: (s) => { const p = s.querySelector('.piccolissimo'); return !!p && parseFloat(getComputedStyle(p).fontSize) >= 16; },
      aiuti: [
        'Seleziona l\'ultimo <code>&lt;p class="piccolissimo"&gt;</code> e guarda gli <b>Stili</b>.',
        'Cambia <code>font-size: 2px</code> in <code>font-size: 20px</code>.',
      ],
    },
    /* ---------------- 4 ---------------- */
    {
      titolo: 'La porta chiusa',
      url: 'www.dottorquack.it/covo',
      testo: 'Il pulsante <b>Entra nel covo</b> è grigio e non si può premere. Ma un pulsante è solo un pezzo di HTML... ' +
        'Riattivalo e premilo!',
      html: `
<header>
  <h1>Il covo segreto</h1>
</header>
<p>Solo il Dottor Quack può entrare. Il pulsante qui sotto è disattivato per tutti gli altri.</p>
<button class="entra" disabled>Entra nel covo</button>`,
      css: '',
      monta(s, api) {
        s.querySelector('.entra').addEventListener('click', () => api.vinci());
      },
      aiuti: [
        'Guarda bene la riga <code>&lt;button class="entra" disabled&gt;</code>: è quella parola che lo blocca.',
        'Tocca l\'attributo <code>disabled</code>, cancellalo tutto e premi Invio. Poi premi il pulsante nel sito.',
      ],
    },
    /* ---------------- 5 ---------------- */
    {
      titolo: 'Inchiostro invisibile',
      url: 'www.dottorquack.it/accesso',
      testo: 'Il Dottor Quack si è scritto la password sulla pagina, ma con l\'<b>inchiostro bianco su sfondo bianco</b>. ' +
        'Rendila visibile e usala per aprire il lucchetto.',
      html: `
<header>
  <h1>Area riservata</h1>
</header>
<div class="foglio">
  <p>Promemoria per me stesso:</p>
  <p class="segreto">la password è STAGNO</p>
</div>
${LUCCHETTO('Password')}`,
      css: '.foglio { background: white; padding: 10px; border-radius: 8px; }\n.segreto { color: white; }',
      monta(s, api) { lucchetto(s, api, 'stagno'); },
      aiuti: [
        'Nella parte <b>Stili</b> del paragrafo <code>.segreto</code> c\'è <code>color: white</code>.',
        'Cambia <code>white</code> in <code>black</code>... oppure leggi il testo direttamente nel codice HTML: nel codice si vede tutto!',
      ],
    },
    /* ---------------- 6 ---------------- */
    {
      titolo: 'Il popup infinito',
      url: 'www.dottorquack.it/gabbie',
      testo: 'Il pulsante per aprire le gabbie c\'è, ma un fastidioso <b>popup</b> lo copre e non si chiude mai. ' +
        'Fallo sparire e premi <b>Apri le gabbie</b>.',
      html: `
<header>
  <h1>Le gabbie</h1>
</header>
<p>Qui sotto c'è il pulsante che apre tutte le gabbie. Peccato che...</p>
<button class="apri">Apri le gabbie</button>
<div class="popup">
  <div class="finestra">
    <h2>📰 Iscriviti alla newsletter del Dottor Quack!</h2>
    <p>Ricevi ogni giorno 100 email di risate malvagie.</p>
    <button class="chiudi">✖ Chiudi</button>
  </div>
</div>`,
      css: `.popup { position: absolute; inset: 0; background: rgba(40, 0, 60, 0.85); display: flex; align-items: center; justify-content: center; }
.finestra { background: #fff; padding: 16px; border-radius: 12px; max-width: 80%; text-align: center; }
.finestra h2 { font-size: 1.1rem; margin: 0; }`,
      monta(s, api) {
        s.querySelector('.apri').addEventListener('click', () => api.vinci());
        s.querySelector('.chiudi').addEventListener('click', () => api.avviso('😈 Il pulsante "Chiudi" non chiude niente. Bel tentativo!', 'ko'));
      },
      aiuti: [
        'Seleziona il <code>&lt;div class="popup"&gt;</code>: è lui che copre tutto.',
        'Puoi eliminarlo con il pulsante 🗑 del pannello, oppure aggiungere negli Stili <code>display: none</code>.',
      ],
    },
    /* ---------------- 7 ---------------- */
    {
      titolo: 'Il campo troppo corto',
      url: 'www.dottorquack.it/cancello',
      testo: 'La parola per aprire il cancello è <b>PAPERELLA</b>, ma il campo accetta solo <b>3 lettere</b>! ' +
        'Allunga il campo e scrivi la parola intera.',
      html: `
<header>
  <h1>Il cancello</h1>
</header>
<p>Scrivi la parola d'ordine. Tanto non ci starà mai, eheh.</p>
${LUCCHETTO('Parola d\'ordine', 'maxlength="3"')}`,
      css: '',
      monta(s, api) { lucchetto(s, api, 'paperella'); },
      aiuti: [
        'Nell\'<code>&lt;input&gt;</code> c\'è l\'attributo <code>maxlength="3"</code>: vuol dire "massimo 3 caratteri".',
        'Cambialo in <code>maxlength="20"</code> (oppure cancellalo) e poi scrivi PAPERELLA nel campo.',
      ],
    },
    /* ---------------- 8 ---------------- */
    {
      titolo: 'I pallini misteriosi',
      url: 'www.dottorquack.it/login',
      testo: 'Il browser del Dottor Quack ha <b>ricordato la sua password</b>, ma si vedono solo dei pallini ●●●●. ' +
        'Scopri cosa c\'è sotto e scrivila nel lucchetto.',
      html: `
<header>
  <h1>Login del Dottor Quack</h1>
</header>
<div class="login">
  <label>Utente <input value="dottor.quack" readonly></label>
  <label>Password <input type="password" value="Quackissimo" readonly></label>
</div>
${LUCCHETTO('Scrivi qui la password che hai scoperto')}`,
      css: '.login { display: grid; gap: 8px; background: #fff; padding: 12px; border-radius: 8px; }\n.login input { font: inherit; padding: 4px 6px; }',
      monta(s, api) { lucchetto(s, api, 'quackissimo'); },
      aiuti: [
        'Un campo con <code>type="password"</code> mostra i pallini. Che succede se il tipo diventa <code>text</code>?',
        'Cambia <code>type="password"</code> in <code>type="text"</code>. (Hai notato che la password si leggeva già nel codice? Per questo non si salvano le password nelle pagine!)',
      ],
    },
    /* ---------------- 9 ---------------- */
    {
      titolo: 'Il bigliettino dello sviluppatore',
      url: 'www.dottorquack.it/cassaforte',
      testo: 'Sulla pagina non c\'è nessun indizio. Ma chi ha programmato il sito ha lasciato un <b>commento</b> nel codice, ' +
        'che il browser non mostra... Trovalo!',
      html: `
<header>
  <h1>La cassaforte</h1>
</header>
<!-- TODO: togliere prima di pubblicare! Combinazione della cassaforte: 1234quack -->
<p>Questa cassaforte è impenetrabile. Nessun indizio da nessuna parte. Proprio nessuno.</p>
${LUCCHETTO('Combinazione')}`,
      css: '',
      monta(s, api) { lucchetto(s, api, '1234quack'); },
      aiuti: [
        'I commenti HTML si scrivono così: <code>&lt;!-- ... --&gt;</code> e nel pannello sono verdi.',
        'Il commento è subito sotto <code>&lt;header&gt;</code>.',
      ],
    },
    /* ---------------- 10 ---------------- */
    {
      titolo: 'Promosso admin',
      url: 'www.dottorquack.it/pannello',
      testo: 'Il pannello di controllo dice che sei un semplice <b>ospite</b>. Gli admin vedono un pulsante in più... ' +
        'Diventa admin e premilo.',
      html: `
<div class="pagina" data-ruolo="ospite">
  <header>
    <h1>Pannello di controllo</h1>
  </header>
  <p>Ciao, <b>ospite</b>. Non hai i permessi per fare niente.</p>
  <div class="solo-admin">
    <p>Benvenuto, capo!</p>
    <button class="libera">🦆 Libera tutte le paperelle</button>
  </div>
</div>`,
      css: '.solo-admin { display: none; }\n[data-ruolo="admin"] .solo-admin { display: block; }',
      monta(s, api) { s.querySelector('.libera').addEventListener('click', () => api.vinci()); },
      aiuti: [
        'Guarda l\'attributo <code>data-ruolo</code> del <code>&lt;div class="pagina"&gt;</code>.',
        'Cambia <code>data-ruolo="ospite"</code> in <code>data-ruolo="admin"</code>.',
      ],
    },
    /* ---------------- 11 ---------------- */
    {
      titolo: 'Il pulsante fuggito',
      url: 'www.dottorquack.it/uscita',
      testo: 'Il pulsante <b>Uscita di sicurezza</b> esiste, ma il Dottor Quack l\'ha spostato <b>fuori dallo schermo</b>. Riportalo indietro e premilo.',
      html: `
<header>
  <h1>Uscita</h1>
</header>
<p>Da qui non si esce. Non c'è nessun pulsante. (Davvero.)</p>
<div class="zona">
  <button class="uscita" style="position: absolute; left: -9999px; top: 10px;">🚪 Uscita di sicurezza</button>
</div>`,
      css: '.zona { position: relative; height: 70px; overflow: hidden; border: 1px dashed #bbb; border-radius: 8px; }',
      monta(s, api) { s.querySelector('.uscita').addEventListener('click', () => api.vinci()); },
      aiuti: [
        'Il pulsante ha uno stile scritto direttamente nell\'HTML: <code>left: -9999px</code>, cioè 9999 pixel a sinistra!',
        'Negli <b>Stili</b> del pulsante cambia <code>-9999px</code> in <code>10px</code>.',
      ],
    },
    /* ---------------- 12 ---------------- */
    {
      titolo: 'Il negozio truffa',
      url: 'www.dottorquack.it/negozio',
      testo: 'La chiave delle gabbie costa <b>999 €</b>! Prova pure a cambiare il prezzo nella pagina e a comprarla... ' +
        'poi trova il <b>vero</b> trucco: un codice sconto nascosto.',
      html: `
<header>
  <h1>Il negozio del Dottor Quack</h1>
</header>
<div class="prodotto">
  <span class="foto">🗝️</span>
  <div>
    <b>Chiave delle gabbie</b>
    <p>Prezzo: <span class="prezzo">999</span> €</p>
    <button class="compra">Compra</button>
  </div>
</div>
<p class="coupon" hidden>Codice sconto per gli amici del Dottore: QUACK100</p>
<form class="sconto">
  <input placeholder="Codice sconto"><button>Applica</button>
</form>`,
      css: `.prodotto { display: flex; gap: 14px; align-items: center; background: #fff; padding: 12px; border-radius: 10px; }
.foto { font-size: 3rem; }
.sconto { display: flex; gap: 8px; margin-top: 12px; }
.sconto input { font: inherit; flex: 1; min-width: 0; padding: 6px 8px; }`,
      monta(s, api) {
        // il "server" ricorda il prezzo vero: cambiarlo nella pagina non serve
        let prezzoServer = 999;
        s.querySelector('.compra').addEventListener('click', () => {
          const pagina = s.querySelector('.prezzo')?.textContent.trim();
          if (prezzoServer === 0) api.vinci();
          else if (pagina !== '999') api.avviso(`🛑 Il server ha controllato: la chiave costa ${prezzoServer} €, non ${pagina} €! Hai cambiato solo la TUA copia della pagina: il prezzo vero sta sul server.`, 'ko');
          else api.avviso('💸 Non hai 999 €. Trova un altro modo!', 'ko');
        });
        s.querySelector('.sconto').addEventListener('submit', (e) => {
          e.preventDefault();
          if (e.target.querySelector('input').value.trim().toUpperCase() === 'QUACK100') {
            prezzoServer = 0;
            const p = s.querySelector('.prezzo');
            if (p) p.textContent = '0';
            api.avviso('✅ Codice valido: sconto del 100%! Ora la chiave costa 0 €. Premi Compra.', 'ok');
          } else api.avviso('❌ Codice sconto non valido', 'ko');
        });
      },
      aiuti: [
        'Cambiare il prezzo non basta: il server sa quello vero. Cerca un elemento nascosto con l\'attributo <code>hidden</code>.',
        'Il paragrafo <code>&lt;p class="coupon" hidden&gt;</code> contiene il codice: scrivilo nel campo "Codice sconto".',
      ],
    },
    /* ---------------- 13 ---------------- */
    {
      titolo: 'La mappa sfocata',
      url: 'www.dottorquack.it/mappa',
      testo: 'La mappa del covo dice dove sono le gabbie, ma è tutta <b>sfocata</b>. Toglile l\'effetto e leggi il codice.',
      html: `
<header>
  <h1>Mappa del covo</h1>
</header>
<div class="mappa">
  <p>🗺️ Gabbie: piano -1, corridoio blu</p>
  <p class="codice">Codice delle gabbie: 4711</p>
</div>
${LUCCHETTO('Codice delle gabbie')}`,
      css: '.mappa { filter: blur(7px); background: #e8dcc0; padding: 12px; border-radius: 10px; }\n.codice { font-size: 1.4rem; font-weight: bold; }',
      monta(s, api) { lucchetto(s, api, '4711'); },
      aiuti: [
        'Il CSS della <code>.mappa</code> ha un <code>filter: blur(7px)</code>: "blur" in inglese vuol dire sfocato.',
        'Togli la spunta accanto a <code>filter</code> negli Stili, oppure cambia <code>7px</code> in <code>0px</code>.',
      ],
    },
    /* ---------------- 14 ---------------- */
    {
      titolo: 'La console',
      url: 'www.dottorquack.it/sala-macchine',
      console: true,
      testo: 'Il pulsante <b>Apri gabbia</b> non funziona: premilo e guarda nella scheda <b>Console</b> che errore compare. ' +
        'Il programmatore ha sbagliato a scrivere il nome della funzione! Chiama tu quella giusta: <code>apriGabbia()</code>',
      html: `
<header>
  <h1>Sala macchine</h1>
</header>
<p>Qui c'è il computer che controlla le gabbie.</p>
<button onclick="apriGabia()">Apri gabbia</button>`,
      css: '',
      monta(s, api) { window.apriGabbia = () => { api.vinci(); return '🦆 Gabbia aperta!'; }; },
      smonta() { delete window.apriGabbia; },
      aiuti: [
        'Nella Console compare <code>apriGabia is not defined</code>: manca una <b>b</b>!',
        'Apri la scheda <b>Console</b>, scrivi <code>apriGabbia()</code> e premi Invio. (Oppure correggi l\'attributo <code>onclick</code> e premi il pulsante.)',
      ],
    },
    /* ---------------- 15 ---------------- */
    {
      titolo: 'Il gran finale',
      url: 'www.dottorquack.it',
      testo: 'Ultima mossa: trasforma la homepage del Dottor Quack nella pagina della <b>vittoria delle paperelle</b>!',
      html: `
<div class="home">
  <header>
    <span class="logo">😈</span>
    <h1>Dottor Quack: le paperelle sono mie!</h1>
  </header>
  <p>Il genio del male più temuto dello stagno.</p>
</div>`,
      css: `.home { background: #1b1b1b; color: #ddd; padding: 16px; border-radius: 10px; }
.home header { display: flex; align-items: center; gap: 10px; border-color: #900; }
.logo { font-size: 2.4rem; }`,
      lista: (s) => {
        const h = s.querySelector('h1'), logo = s.querySelector('.logo'), home = s.querySelector('.home');
        const sfondo = home ? getComputedStyle(home).backgroundColor : 'rgb(27, 27, 27)';
        return [
          { testo: 'Il titolo contiene la parola <b>LIBERE</b>', ok: /liber/i.test(h?.textContent || '') },
          { testo: 'Lo sfondo della <code>.home</code> non è più nero', ok: !!home && sfondo !== 'rgb(27, 27, 27)' && sfondo !== 'rgba(0, 0, 0, 0)' },
          { testo: 'Il logo 😈 è diventato una paperella 🦆', ok: !!logo && logo.textContent.includes('🦆') && !logo.textContent.includes('😈') },
        ];
      },
      aiuti: [
        'Ci sono 3 cose da fare: il testo dell\'<code>&lt;h1&gt;</code>, il <code>background</code> della <code>.home</code> negli Stili, il testo dello <code>&lt;span class="logo"&gt;</code>.',
        'Per la paperella: copia questa 🦆 e incollala al posto di 😈. Per lo sfondo prova <code>gold</code> o <code>lightblue</code>.',
      ],
    },
  ];

  /** Livello segreto: si risolve solo leggendo il codice vero della pagina (F12 o Ctrl+U). Niente punti. */
  const SEGRETO = {
    titolo: 'Il vero hacker',
    url: 'www.dottorquack.it/???',
    testo: 'Questo livello <b>non si risolve</b> col pannello Ispeziona del gioco. Serve quello <b>vero</b> del browser: ' +
      'su un computer premi <b>F12</b> (oppure tasto destro → <i>Ispeziona</i>). Da qualche parte nel codice di <b>tutta</b> la pagina ' +
      '(non solo del sito del Dottor Quack) c\'è un commento con la parola segreta.',
    html: `
<header>
  <h1>???</h1>
</header>
<p>Qui non c'è niente. Il segreto è fuori da questo riquadro...</p>
${LUCCHETTO('Parola segreta')}`,
    css: '',
    aiuti: [
      'Nel vero Ispeziona apri <code>&lt;body&gt;</code> e scorri fino in fondo: cerca una riga verde.',
      'Sul telefono non c\'è F12: questo livello si fa al computer. Niente punti, solo la gloria!',
    ],
  };

  window.HackLivelli = { LIVELLI, SEGRETO, BASE, lucchetto };
})();
