# 8. API

Tutti gli endpoint sono in `api/`, rispondono JSON UTF-8 con `Cache-Control: no-store` e, in caso di errore, `{"ok": false, "errore": "..."}`. I messaggi sono in italiano e pensati per essere mostrati all'utente; con `DEBUG = true` gli errori 500 mostrano il messaggio reale.

**Regole comuni**
- I POST richiedono `Content-Type: application/json` (415 altrimenti): è la difesa CSRF di base contro i form cross-site. Corpo massimo 4 KB.
- `richiedi_ospite()` richiede il login ospite (401 altrimenti) e una sessione in corso (403 se terminata).
- Il campo `uuid` nel corpo deve coincidere con quello legato alla sessione PHP dopo `register.php` (403 altrimenti).

## `POST api/register.php`

Crea o aggiorna il partecipante nella sessione dell'ospite.

```json
{ "uuid": "<uuid v4>", "nickname": "Neo_42", "avatar": "robot-3" }
```

- Nickname: 3-16 caratteri tra lettere, numeri, `_`, `-` (niente spazi, per scoraggiare "Nome Cognome").
- Avatar: `[a-z0-9-]{1,30}`.
- Controllo blacklist (422); nickname già usato nella sessione da un altro uuid → 409.
- Se l'uuid esiste già, aggiorna nickname/avatar (non scatta mai `ON DUPLICATE KEY`, per non sovrascrivere il profilo di un altro).
- Fuori dalle sessioni demo registra l'arrivo in `registro`.
- Risposta: `{ ok, partecipante: { uuid, nickname, avatar, punti_totali } }`.

## `POST api/save_score.php`

Salva il punteggio di un modulo (usata da Binary Lights, Porte logiche, Modifica il gioco, Hack the Web(site)).

```json
{ "uuid": "<uuid v4>", "modulo": "binary_lights", "punti": 1230 }
```

- Punti limitati a `moduli.max_punti`, ridotti da `max_punti_sessione()` (es. Binary Lights: 5 × 300 all'Open Day).
- Si tiene il record personale (`GREATEST`).
- Anti-spam: un salvataggio ogni 2 secondi per modulo (429).
- Gioco non attivo → 403; modulo sconosciuto → 404.
- Risposta: `{ ok, modulo, punti_modulo, punti_totali, nuovo_record }`.

## `POST api/codegame.php`

Duck Coding. Il **tempo è misurato dal server**: parte alla prima apertura del livello e si ferma alla prima soluzione. Ricaricare non azzera il cronometro, rigiocare un livello risolto non cambia tempo né punti.

| `azione` | Parametri | Effetto |
|---|---|---|
| `stato` | – | livelli risolti, tempi, punti, media di ogni livello |
| `inizia` | `livello` | fa partire il cronometro (solo la prima volta); 409 se il livello precedente non è risolto (salvo sessione con "sblocca tutto") |
| `completa` | `livello`, `blocchi`, `esecuzioni` | ferma il cronometro e calcola i punti; sotto 2 secondi → 422 "Troppo veloce per essere vero!" |

Punteggio: `100 × media / tuo tempo`, limitato a 30..130, +20 se usi il numero minimo di blocchi (`ottimali` del livello). Max 150 a livello. Dettagli in [Giochi](09-giochi.md#duck-coding).

## `POST api/diploma_token.php`

Genera il token del QR code.

```json
{ "nome": "Mario Rossi" }
```

- `nome` facoltativo (2-60 caratteri, lettere, spazi, apostrofi, trattini, punti).
- Non richiede che la sessione sia in corso (l'attestato resta scaricabile a evento chiuso), ma controlla `diploma_aperto()`.
- Un solo token attivo per partecipante: il nuovo sostituisce il vecchio; i token scaduti vengono cancellati.
- Risposta: `{ ok, token, secondi }`. Il client compone l'URL `diploma_pdf.php?t=<token>` con il proprio indirizzo e ne fa il QR.

## `GET api/get_leaderboard.php[?s=<id sessione>]`

Pubblico, sola lettura. Risposta: `{ ok, top: [{id, nickname, avatar, punti}], ultimi: [...], partecipanti }`. ETag e 304 se invariato. Senza `s` usa la sessione dell'ospite collegato, altrimenti `sessione_predefinita()`.

## Pagine che servono file

- `GET diploma_pdf.php?t=<token>`: scarica il PDF senza login; 404 token malformato, 410 scaduto, 403 download chiuso.
- `POST diploma_pdf.php` (`nome`): download dalla pagina del diploma, con login.
- `GET admin/registro.php?export=csv&dal=...&al=...`: export CSV del registro (solo admin).

## Codici di errore ricorrenti

| Codice | Significato |
|---|---|
| 401 | login ospite mancante o scaduto |
| 403 | profilo non riconosciuto, gioco chiuso, sessione terminata |
| 404 | profilo o modulo non trovato |
| 405 / 415 | metodo o Content-Type errato |
| 409 | nickname occupato, livello precedente da risolvere |
| 422 | dati non validi |
| 429 | troppo veloce |
| 500 | errore interno (dettaglio solo nel log del server) |
