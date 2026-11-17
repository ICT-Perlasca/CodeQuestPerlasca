# 4. Guida al pannello admin

Indirizzo: `/admin/` (il vecchio `/admin.php` rimanda lì). Accesso con la password il cui hash è in `ADMIN_PASS_HASH`. Dopo 5 tentativi errati si aspetta 5 minuti.

## Come funziona il pannello

- Il **menu a sinistra** ha un selettore: scegli la **sessione su cui lavori**. Panoramica, Partecipanti, Giochi (statistiche) e Sistema → "Azzera classifica" riguardano quella sessione. Senza scelta viene proposta la più recente in corso (le vere prima delle demo).
- Ogni azione è un POST protetto da token CSRF e termina con un messaggio di conferma in alto.
- Pagine: Panoramica, Sessione, Giochi, Partecipanti, Registro, Sistema.

## Sessione

Qui si crea e si modifica ciò che definisce una giornata. Al primo avvio compare "Crea la prima sessione": **finché non ne esiste una, il login ospite è chiuso**.

| Campo | Significato |
|---|---|
| Nome | etichetta interna, es. "Demo studenti", "Open Day mattina" |
| Tipo di giornata | *Open Day* (~10 min) o *Laboratori Pomeridiani* (~1 h): cambia i livelli giocabili e i testi dell'attestato |
| Giorno | data stampata sull'attestato |
| Fine sessione | ora in cui il login si chiude (stesso giorno) |
| Nome utente ospiti | 3-30 lettere minuscole/numeri |
| Password ospiti | 4-30 lettere/numeri; il login non distingue maiuscole/minuscole |
| Titolo attività | facoltativo, es. "Robotica con Arduino" |
| Descrizione sull'attestato | facoltativo: continua la frase dopo la virgola (max 200) |
| Download attestati fino al | vuoto = mezzanotte che chiude il giorno dopo l'evento |
| QR code valido (minuti) | durata del QR per scaricare da un altro telefono (predefinito 10, max 1440) |
| Sessione demo | i partecipanti non entrano nel registro, l'attestato non consuma numeri ("DEMO") |
| Sblocca tutti i livelli | si può giocare qualsiasi livello subito, senza risolvere i precedenti (utile per provare i giochi) |
| Login aperto | togli la spunta per chiudere a mano |

La pagina mostra anche l'**anteprima del testo** che comparirà sull'attestato.

**Regole utili**
- Più sessioni possono essere aperte insieme. Utente + password scelgono la sessione: usa combinazioni diverse per le sessioni contemporanee. Se più sessioni hanno la stessa coppia, entra nella prima in corso, la più recente.
- **Termina**: imposta l'ora di fine a "adesso" (login chiuso). Per riaprirla cambia l'ora di fine.
- **Elimina**: scrivi `ELIMINA`; cancella la sessione e i suoi partecipanti (a cascata). **Il registro non viene toccato.**
- Per la LIM: `classifica.php?s=<numero della sessione>` (il numero è nell'elenco delle sessioni).

## Giochi

- **Stato di ogni gioco**: *Attivo*, *Disabilitato* (visibile ma bloccato, non si salvano punti), *Nascosto* (sparisce dalla home). Vale per tutte le sessioni.
- **Statistiche per gioco** (sessione scelta): giocatori, punti medi, "fin dove arrivano" (livelli = punti/100) o fasce di punti per Binary Lights, i migliori con nome. Per Duck Coding anche i **tempi per livello** e i più bravi.

## Partecipanti

- Elenco dei partecipanti della sessione, con ricerca per nickname o nome e ordinamento (più recenti / punti).
- **Nascondi/Mostra**: toglie un nickname dalla classifica senza eliminarlo (e blocca il suo attestato).
- **Elimina**: cancella il partecipante e i suoi punti; al prossimo accesso dovrà scegliere un nuovo nickname.
- **Banna**: come elimina e aggiunge il nickname (normalizzato) alla blacklist.
- **Blacklist nickname**: aggiungi o rimuovi parole; il controllo è per sottostringa sulla forma normalizzata (minuscolo, leet-speak tradotto, solo lettere: `c4zz0` → `cazzo`).

## Registro

Elenco permanente degli arrivi di tutte le sessioni reali (le demo non ci entrano).

- Filtro per intervallo data/ora (di default l'intera giornata dell'ultimo arrivo).
- Grafico degli **arrivi per fascia oraria**; toccando una fascia si filtra l'elenco.
- **Elenco nominativo** (primi 1000: oltre usa il CSV) con nome, nickname, punti, arrivo, download, numero attestato.
- **Esporta CSV**: file `registro-openday-<data>.csv` dell'intervallo scelto.
- Il nome compare solo se il ragazzo lo scrive per l'attestato.

## Sistema

Azioni distruttive, con conferma scritta:

| Azione | Conferma | Effetto |
|---|---|---|
| Azzera classifica | `RESET` | elimina partecipanti e punteggi della sessione scelta; il registro resta |
| Svuota registro | `SVUOTA` | cancella tutto il registro (nomi compresi). **Esporta prima il CSV.** Tipico a inizio anno scolastico |

## Panoramica

Stato della sessione (aperta/chiusa), situazione dei giochi, arrivi per fascia oraria e i primi in classifica.
