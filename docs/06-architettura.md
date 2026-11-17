# 6. Architettura e struttura del codice

PHP "classico" senza framework né build step: ogni pagina è un file `.php` che include gli helper di `inc/`. Il JavaScript è vanilla, in `assets/js/`.

## Struttura

| Percorso | Contenuto |
|---|---|
| `login.php`, `logout.php` | accesso/uscita dell'ospite |
| `index.php` | onboarding (nickname + avatar) e scelta dei giochi |
| `classifica.php` | classifica live per LIM/proiettore |
| `diploma.php`, `diploma_pdf.php` | anteprima e download dell'attestato |
| `giochi/` | una pagina PHP per gioco (solo markup e configurazione) |
| `api/` | endpoint JSON (`register`, `save_score`, `codegame`, `diploma_token`, `get_leaderboard`) |
| `admin/` | una pagina per voce del menu; `_base.php` carica `admin/_inc/` |
| `admin.php` | redirect 301 a `admin/index.php` |
| `inc/` | logica PHP, protetta da `inc/.htaccess` (vedi sotto) |
| `assets/css/` | `tema.css` (colori e base comuni), `admin.css`, `pagine/`, `giochi/` |
| `assets/js/` | `comune/` (storage, avatar, guida), `pagine/` (home, classifica, diploma), `giochi/<gioco>/`, `vendor/` (Blockly, qrcode) |
| `db/schema.sql` | schema del database (non ci sono migrazioni) |
| `dev/xampp.sh` | ambiente locale |
| `docs/` | questa documentazione |

I giochi hanno una cartella JS ciascuno: `pagina.js` (logica della pagina) più, dove serve, il motore dei livelli (`motore.js` per Porte logiche e Modifica il gioco; `livelli.js` e `ispeziona.js` per Hack the Web(site)).

## File di `inc/`

Le pagine fanno un solo `require` di `inc/bootstrap.php`, che carica nell'ordine:

- **`config.php`**: costanti di connessione, utente/password ospite di riserva, `ADMIN_PASS_HASH`, `DEBUG`. Se esiste `config.local.php` lo carica al posto di sé stesso.
- **`db.php`**: solo `db()` (PDO lazy, errori generici verso l'esterno e dettaglio nel log).
- **`sessioni.php`**: sessione PHP (`avvia_sessione()`) e sessioni di gioco (`sessione_corrente()`, `evento_aperto()`, `ospite_collegato()`, `sessione_demo()`, ...).
- **`giornate.php`**: `TIPI_GIORNATA`, `sessione_attivita()`, `max_punti_sessione()`.
- **`giochi.php`**: catalogo `GIOCHI`, stato dei giochi, `richiedi_gioco_attivo()`, `livelli_sessione()`.
- **`profilo.php`**: nickname (normalizzazione e blacklist) e `uuid_valido()`.
- **`punteggi.php`**: `aggiorna_totale()`.
- **`asset.php`**: `asset('css/tema.css', '../')` restituisce il percorso con la data di modifica del file (`?v=...`), così i telefoni non usano un CSS/JS vecchio dalla cache.

Moduli caricati solo dove servono:

- **`api.php`**: bootstrap degli endpoint: header JSON, handler delle eccezioni (rollback + `{ok:false, errore}`), `richiedi_metodo()`, `leggi_json()` (max 4 KB, richiede `Content-Type: application/json`), `richiedi_ospite()`.
- **`duck.php`** → `duck/livelli.php` (mappe 8x8) e `duck/punti.php` (costanti, punteggio, tempi medi).
- **`attestato.php`** → `attestato/dati.php` (dati, testi, scadenza, numerazione, registro), `attestato/disegni.php` (SVG condivisi tra anteprima e PDF), `attestato/pdf.php` (FPDF).
- **`lib/fpdf/`**: libreria FPDF.

## Pagine e configurazione JavaScript

Le pagine PHP non contengono più JS né CSS lunghi: passano al browser i dati della sessione con un piccolo blocco `<script>const OD = { ... };</script>` (livelli giocabili, `sbloccaTutto`, profilo...) e poi caricano `comune/storage.js` e il `pagina.js` del gioco. Il codice JS legge `OD`.

## Due concetti di "sessione"

Non vanno confusi:

1. **Sessione PHP** (`$_SESSION`, cookie `OPENDAY`): chi è collegato. Contiene `ospite`, `sessione_id`, `uuid`, `ultimo_salvataggio`, e per l'admin `admin`, `csrf`, `admin_sessione`.
2. **Sessione di gioco** (tabella `sessioni`): l'evento. Ogni ospite appartiene a una.

`sessione_corrente()` risolve la sessione di gioco in quest'ordine:
1. quella impostata con `imposta_sessione($id)` (admin, classifica pubblica con `?s=`, diploma da QR);
2. `$_SESSION['sessione_id']` dell'ospite collegato;
3. altrimenti nessuna (valgono i parametri dell'Open Day classico).

Quasi tutte le funzioni di `sessioni.php` (`evento_aperto(), `ospite_pass()`, `sessione_demo()`, `sessione_attivita()`, ecc.) lavorano sulla sessione corrente. Gli stati di una sessione sono `attiva`, `scaduta` (passata l'ora di fine) e `chiusa` (disattivata a mano).

## Flusso di un ospite

```
login.php (utente+password) ──► $_SESSION[ospite, sessione_id]
        │
index.php ── api/register.php (uuid, nickname, avatar) ──► partecipanti (+ registro se non demo)
        │                                                   $_SESSION[uuid]
giochi/*.php ── api/save_score.php | api/codegame.php ──► punteggi ──► partecipanti.punti_totali
        │
diploma.php ── api/diploma_token.php ──► diplomi_token ──► diploma_pdf.php?t=<token>
```

Il browser conserva in `localStorage` uuid (`od_uuid`), nickname, avatar e la sessione a cui appartiene (`od_sessione`): se la sessione cambia, l'uuid vecchio viene scartato. Il server accetta un punteggio solo se l'uuid corrisponde a quello legato alla sessione PHP.

## Punteggi

- `punteggi` ha una riga per (partecipante, modulo) con il **record personale** (`GREATEST`): rigiocare non gonfia la classifica.
- `partecipanti.punti_totali` è sempre ricalcolato come somma dei moduli (`aggiorna_totale()`), nella stessa transazione che ha bloccato la riga (`SELECT ... FOR UPDATE`); se la sessione non è demo, il totale è copiato anche nel registro.
- Il tetto per modulo è `moduli.max_punti`, ridotto da `max_punti_sessione()` per i giochi più corti (Open Day).
- Spareggio in classifica: a parità di punti vince chi li ha raggiunti prima (`updated_at`, poi `id`).

## Classifica live

`classifica.php` interroga `api/get_leaderboard.php` ogni 4 secondi. L'endpoint risponde con **ETag**: se nulla è cambiato il browser riceve 304 senza corpo. Restituisce i primi 10 (con punti > 0), gli ultimi 6 arrivati e il numero di partecipanti visibili; mai nomi veri.

## Interfaccia

Tema scuro con variabili CSS in `assets/css/tema.css` (`--bg`, `--panel`, `--accent`, ...); ogni pagina o gioco aggiunge un proprio foglio con i colori specifici. Pensato per telefono (`100dvh`, `safe-area-inset`). I giochi usano `localStorage` solo tramite `storage` (try/catch: modalità privata, storage bloccato).
