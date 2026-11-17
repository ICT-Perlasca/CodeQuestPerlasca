# 7. Database

MySQL/MariaDB, `utf8mb4` / `utf8mb4_unicode_ci`, InnoDB. Schema completo in `db/schema.sql`.

## Cosa fa `db/schema.sql`

- **Ricrea** (`DROP` + `CREATE`) `codegame_tempi`, `diplomi_token`, `punteggi`, `partecipanti`, `sessioni`, `moduli`, `impostazioni`: i dati di queste tabelle si perdono.
- **Non tocca** `registro` e `blacklist` (`CREATE TABLE IF NOT EXISTS`): sono permanenti. Di conseguenza se cambi la struttura di una delle due su un DB esistente serve un `ALTER` manuale ([Manutenzione](11-manutenzione.md)).

## Tabelle

```
sessioni 1 ──< partecipanti 1 ──< punteggi >── 1 moduli
                    │ 1
                    ├──< codegame_tempi
                    └──< diplomi_token

registro  (permanente, legata ai partecipanti solo tramite uuid)
blacklist, impostazioni  (indipendenti)
```

### `sessioni`
Una riga per evento. Colonne principali: `nome`, `tipo` (`openday`/`laboratori`), `data`, `titolo`, `descrizione`, `utente`, `password` (minuscole), `scade_il`, `attiva`, `demo`, `sblocca_tutto`, `diploma_scade_il` (NULL = automatica), `qr_minuti` (NULL = 10), `creata_il`. Indice su `password`.

### `partecipanti`
`sessione_id`, `uuid` (UUID v4 generato dal browser), `nickname` (max 16), `avatar`, `punti_totali`, `nascosto`. Unici **per sessione**: `(sessione_id, uuid)` e `(sessione_id, nickname)` (la collation `_ci` rende "Neo" = "neo"). `ON DELETE CASCADE` dalla sessione.

### `moduli`
Catalogo dei giochi: `codice`, `titolo`, `max_punti`, `percorso_ordine` (solo per ordinare in admin e attestato, NULL = fuori percorso), `attivo`. Contiene anche righe di giochi mai realizzati (viaggio_pacchetto, escape_room, pixel_art, ...) che restano per non rompere vecchi punteggi.

| codice | max_punti | ordine |
|---|---|---|
| `paperelle_coding` (Duck Coding) | 4200 (28 × 150) | 1 |
| `binary_lights` | 3600 (12 × 300) | 2 |
| `modifica_gioco` | 1000 (10 × 100) | 3 |
| `porte_logiche` | 1900 (19 × 100) | 4 |
| `hack_website` | 1500 (15 × 100) | 5 |

### `punteggi`
Chiave `(partecipante_id, modulo)`; `punti` (record), `tentativi`. FK a `partecipanti` (cascade) e a `moduli`.

### `codegame_tempi`
Solo Duck Coding. Chiave `(partecipante_id, livello)`: `iniziato_ms` (cronometro del server), `ms` (NULL finché non risolto), `punti`, `blocchi`, `esecuzioni`, `risolto_il`.

### `diplomi_token`
Token del QR (32 caratteri esadecimali), `partecipante_id`, `nome` (nome da stampare), `scade_il`. I token scaduti vengono cancellati alla generazione di uno nuovo (e quindi anche i nomi che contenevano).

### `registro` (permanente)
`uuid` (unico), `nickname`, `nome`, `punti`, `arrivato_il`, `diploma_il`, `numero_attestato` (unico). Alimentato da `api/register.php` (arrivo) e `registra_diploma()` / `numero_attestato()` (nome, download, numero). Le sessioni demo non scrivono qui.

### `blacklist` (permanente)
Parole vietate nei nickname (`parola` unica), precaricate con un elenco iniziale. Controllo per sottostringa sulla forma normalizzata.

### `impostazioni`
Chiave/valore globale. Oggi contiene solo `gioco_<codice>` = `attivo` / `disabilitato` / `nascosto`. Le vecchie chiavi `sessione_*`, `scadenza`, `ospite_pass`, `diploma_*` non esistono più: le impostazioni di ogni giornata stanno in `sessioni`.

## Note operative

- `diploma_scade_il = NULL` → scadenza automatica: 00:00 di due giorni dopo la data della sessione.
- Eliminare una sessione elimina a cascata partecipanti, punteggi, tempi e token, **non** il registro.
- La numerazione degli attestati parte da **1287** (`ATTESTATO_PRIMO_NUMERO` in `inc/attestato/dati.php`) e prosegue con `MAX + 1`.
- Gli indici di `partecipanti` sono pensati per la classifica: `(sessione_id, nascosto, punti_totali, updated_at)`.
