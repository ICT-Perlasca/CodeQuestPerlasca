# 11. Manutenzione

## Modifiche allo schema

Il progetto **non ha file di migrazione**. `db/schema.sql` è lo schema completo per un database nuovo e basta importarlo.

Per un database già in uso (Altervista) una modifica di struttura non si ottiene reimportando lo schema, perché cancellerebbe i dati. Si fa a mano in phpMyAdmin con `ALTER TABLE`/`INSERT`, **ricordandosi di aggiornare anche `db/schema.sql`**. Note:

- Il MySQL di Altervista non ha `ADD COLUMN IF NOT EXISTS`: se rilanci per errore un `ALTER ... ADD COLUMN` l'errore #1060 (colonna già presente) è innocuo.
- `registro` e `blacklist` sono permanenti: `CREATE TABLE IF NOT EXISTS` non le aggiorna, quindi servono sempre `ALTER` manuali (è già successo, ha rotto l'attestato).
- Se il pannello admin mostra "Database non pronto" manca la tabella `sessioni`: importa `db/schema.sql`.

## Aggiornare i punti massimi di un modulo

`moduli.max_punti` è il tetto anti-cheat: se aggiungi livelli devi aggiornarlo sia in `db/schema.sql` sia sul DB esistente:

```sql
UPDATE moduli SET max_punti = 1900 WHERE codice = 'porte_logiche';
```

## Aggiungere livelli a un gioco esistente

- **Duck Coding**: aggiungi il livello in `codegame_livelli()` (`inc/duck/livelli.php`, mappa + `blocchi`, `ottimali`, `rif`, `dir`). Aggiorna `duck_livelli` di `laboratori` in `TIPI_GIORNATA` e `max_punti` (150 per livello; `codegame_max_punti()` calcola il valore corretto). Verifica che il livello sia risolvibile (in passato si è usato un simulatore Node identico al gioco).
- **Porte logiche**: aggiungi l'oggetto in `assets/js/giochi/porte-logiche/motore.js`; aggiungi il numero a `porte_livelli` nei tipi di giornata interessati (è una lista di numeri: puoi scegliere quali livelli); +100 a `max_punti`.
- **Hack the Web(site)**: aggiungi il livello in `assets/js/giochi/hack-website/livelli.js`; aggiungi il numero a `hack_livelli`; +100 a `max_punti`.
- **Modifica il gioco**: aggiungi la missione in `assets/js/giochi/modifica-gioco/motore.js` (ordine = ordine di gioco); aggiorna `palla_missioni` e `max_punti`.
- **Binary Lights**: i round sono parametrici (`binary_round` e l'array di intervalli in `giochi/binary-lights.php`); il tetto per sessione è `round × 300`.

## Aggiungere un nuovo gioco

1. **Database**: `INSERT INTO moduli (codice, titolo, max_punti, percorso_ordine) VALUES (...)` in `db/schema.sql` e, a mano, sul DB di produzione.
2. **`GIOCHI`** in `inc/giochi.php`: icona, pagina, chiave dei livelli, punti per livello, unità. Se il gioco ha un numero di livelli per giornata, aggiungi la chiave a `TIPI_GIORNATA[...]['giochi']` e un ramo in `max_punti_sessione()`.
3. **Pagina** in `giochi/<gioco>.php`: inizia con `richiedi_gioco_attivo('<codice>')`, controlla `ospite_collegato()`, carica `tema.css` e un CSS in `assets/css/giochi/`, passa i dati con `const OD = {...}` e carica `comune/storage.js` e `assets/js/giochi/<gioco>/pagina.js`. Il JS salva con `fetch` su `api/save_score.php` (`{uuid, modulo, punti}`), usa `storage` per `localStorage` e rispetta `OD.sbloccaTutto` (da `sblocca_livelli()`) se i livelli sono sequenziali. Usa `asset()` per i percorsi di CSS e JS.
4. **Home**: aggiungi la card all'array `$percorso` di `index.php`; se serve, un ramo nel `match` dei progressi.
5. **Admin**: la scheda statistica del gioco compare da sola per i codici in `GIOCHI` (conta `punti / per`); se vuoi dati diversi, estendi `admin/giochi.php`.
6. **Documentazione**: aggiorna [Giochi](09-giochi.md) e la tabella in [Panoramica](01-panoramica.md).

## Aggiungere un tipo di giornata

Aggiungi una voce a `TIPI_GIORNATA` in `inc/giornate.php` con tutte le chiavi (`etichetta`, `nel`, `del`, `binario`, `file`, `attivita`, `merito`, `giochi` con `duck_livelli`, `binary_round`, `palla_missioni`, `porte_livelli`, `hack_livelli`, `durata`). Compare da sola nel menu "Tipo di giornata" di admin. Il tipo **non** è salvato nel registro.

## Modificare la blacklist

Da admin → Partecipanti, oppure dal DB. Le parole sono in forma normalizzata (minuscolo, solo lettere, leet tradotto): `normalizza_nickname()`.

## Librerie incluse

| Libreria | Versione | Dove |
|---|---|---|
| FPDF | 1.86 | `inc/lib/fpdf` |
| Blockly | 13.4.0 | `assets/js/vendor/blockly` |
| qrcode (JS) | – | `assets/js/vendor/qrcode.min.js` |

Non usare CDN: copia sempre i file nel progetto.

## Convenzioni del codice

- Le pagine caricano solo `inc/bootstrap.php` (più `duck.php` / `attestato.php` se servono); niente CSS o JS lunghi dentro i `.php`: stanno in `assets/` e si includono con `asset()`.
- `declare(strict_types=1)` in ogni file PHP; query solo con prepared statement (PDO, `EMULATE_PREPARES` disattivato).
- Output HTML sempre con `htmlspecialchars` (`$e()`).
- Le azioni di admin sono POST con token CSRF e redirect (PRG).
- Testi, nomi e commenti in italiano.

## Pubblicare (Git e Altervista)

Il repository ha i remote `origin` e `ict`. Il codice si carica su Altervista via FTPS (vedi [Deploy](03-deploy-altervista.md)), **senza** `inc/config.php`; le credenziali FTP non sono salvate nel progetto.
