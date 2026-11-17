# 10. Attestato e registro permanente

## L'attestato

Generato con **FPDF 1.86** (`inc/lib/fpdf`, font base del PDF: serve solo `mbstring`), A4 orizzontale. Codice in `inc/attestato/` (`dati.php`: dati, testi, numerazione; `disegni.php`: SVG condivisi; `pdf.php`: FPDF); l'anteprima HTML in `diploma.php` usa le stesse proporzioni e gli stessi disegni (le paperelle sono SVG condivisi tra PDF e anteprima).

### Contenuto

- **Attestato di partecipazione**: intestazione con la parola del tipo di giornata scritta in codice binario, "Si attesta che *Nome Cognome* ..." con il testo del tipo di giornata (o la descrizione scritta in admin), tre "competenze sbloccate" uguali per tutti (**Logica, Creatività, Curiosità**), sigillo "Junior Hacker", paperelle in basso, numero attestato e data. È **inclusivo**: niente punti né posizione.
- **Certificato di merito**: ai **primi 3 della classifica al momento del download** il PDF ha una seconda pagina con podio, medaglia (oro/argento/bronzo) e l'**ora della classifica** stampata. Il podio non viene salvato nel registro: chi scarica di nuovo più tardi può avere un risultato diverso.
- Senza nome scritto dal ragazzo, compare il nickname. Non c'è riga per la firma: si stampa a casa.
- Numero: `<anno>/<progressivo>`, es. `2026/1287`. Nelle sessioni demo vale `DEMO`.
- Nome del file: `attestato-<openday|laboratori>-<nickname>.pdf` (solo caratteri ASCII).

### Numerazione

`numero_attestato()` assegna il numero la **prima volta** che si apre l'attestato e poi non cambia. Parte da `ATTESTATO_PRIMO_NUMERO = 1287` e prosegue con `MAX(numero_attestato) + 1` nel registro. Due download simultanei sono messi in fila da un lock transazionale, con la chiave `UNIQUE` come rete di sicurezza (fino a 3 tentativi).

### Download e QR code

1. Il ragazzo apre `diploma.php`, scrive (facoltativamente) nome e cognome e scarica il PDF (`POST diploma_pdf.php`).
2. Per scaricarlo da un altro telefono genera il QR: `api/diploma_token.php` crea un token casuale di 16 byte valido `qr_minuti` minuti (predefinito `DIPLOMA_TOKEN_MINUTI = 10`, massimo 1440). Il QR punta a `diploma_pdf.php?t=<token>`, che non richiede login. Il disegno del QR usa `assets/js/vendor/qrcode.min.js`, senza servizi esterni.
3. Un solo token attivo per partecipante: generarne uno nuovo invalida il precedente.

### Chiusura del download

`diploma_aperto()` è vero finché `time() < diploma_scadenza()`. La scadenza è `sessioni.diploma_scade_il` se impostata, altrimenti **00:00 di due giorni dopo la data della sessione** (cioè la mezzanotte che chiude il giorno dopo). È indipendente dall'ora di fine del login: l'attestato si scarica anche a sessione terminata.

Un partecipante **nascosto** dall'admin non ha attestato (`dati_diploma()` restituisce `null`).

### Modificare il testo o il disegno

- Testi per tipo di giornata: `TIPI_GIORNATA` in `inc/giornate.php` (`attivita`, `merito`, `binario`, `file`).
- Competenze: costante `COMPETENZE` in `inc/attestato/pdf.php`.
- Layout, colori (`COL_*`), podio (`PODIO`), medaglie (`MEDAGLIE`): in `inc/attestato/pdf.php` e `inc/attestato/disegni.php`. Se cambi il layout PDF, aggiorna anche l'anteprima in `diploma.php`.

## Il registro permanente

Tabella `registro`: una riga per **uuid** (cioè per profilo/telefono) con:

| Colonna | Quando si scrive |
|---|---|
| `nickname`, `arrivato_il` | alla prima registrazione (`api/register.php`); l'ora d'arrivo non cambia mai |
| `punti` | a ogni salvataggio punteggio (`aggiorna_totale()`) |
| `nome` | quando il ragazzo scrive il nome per l'attestato; un nome nuovo sostituisce il vecchio, senza nome resta il precedente |
| `diploma_il` | a ogni download |
| `numero_attestato` | alla prima apertura dell'attestato |

Caratteristiche:

- **Le sessioni demo non scrivono nel registro**.
- Non viene toccato da "Azzera classifica", dall'eliminazione di partecipanti o di sessioni, né da `db/schema.sql`/`dev/xampp.sh reset`. Si svuota solo da **Sistema → Svuota registro** (scrivendo `SVUOTA`).
- Se manca la riga di un partecipante (registrato prima che esistesse il registro) viene creata al primo download o numero attestato.
- Consultazione e CSV in **admin → Registro** (vedi [guida admin](04-guida-admin.md#registro)).
- Contiene dati personali (nomi): vedi [Sicurezza e privacy](13-sicurezza.md).
