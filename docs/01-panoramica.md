# 1. Panoramica

## Cos'è

CodeQuestPerlasca è un sito pensato per essere usato dagli smartphone dei ragazzi durante l'Open Day (visita di circa 10 minuti) e nei Laboratori Pomeridiani (circa 1 ora). Ogni ospite:

1. entra con **utente e password** della sessione (scritti sul cartellone all'ingresso);
2. sceglie **nickname e avatar**;
3. gioca ai minigiochi nell'ordine che preferisce, accumulando punti;
4. a fine percorso scarica l'**attestato PDF** (con QR code per passarlo a un altro telefono).

Sul proiettore/LIM gira la **classifica live**, che mostra solo i nickname.

## Funzioni

- Login ospite per sessione, onboarding (nickname con filtro blacklist + avatar)
- Cinque minigiochi: Duck Coding, Binary Lights, Porte logiche, Modifica il gioco, Hack the Web(site) ([dettagli](09-giochi.md))
- Classifica pubblica in tempo reale, una per sessione
- Attestato PDF; i primi 3 in classifica al momento del download ricevono anche un certificato di merito
- Registro permanente (nomi, arrivi per fascia oraria, numero attestato, export CSV)
- Pannello admin: sessioni, stato dei giochi, partecipanti, blacklist, registro, reset

## Concetti chiave

**Sessione** (tabella `sessioni`). Un'occasione di gioco con le sue regole: tipo di giornata (Open Day o Laboratori), data, utente e password degli ospiti, ora di fine, scadenza del download degli attestati, validità del QR. Possono essere **aperte più sessioni insieme** (es. una demo per i tutor e quelle vere): utente + password scelgono dove si entra. Classifica, partecipanti e statistiche sono separati per sessione.

**Sessione demo.** Sessione di prova: i partecipanti non entrano nel registro permanente e l'attestato riporta "DEMO" invece di consumare un numero progressivo.

**Tipo di giornata** (costante `TIPI_GIORNATA` in `inc/giornate.php`). Decide la durata dei giochi:

| | Open Day | Laboratori |
|---|---|---|
| Durata | ~10 minuti | ~1 ora |
| Duck Coding | primi 6 livelli | 28 livelli |
| Binary Lights | 5 round | 12 round |
| Modifica il gioco | 4 missioni | 10 missioni |
| Porte logiche | 8 livelli (1-4 e 15-18) | 19 livelli |
| Hack the Web(site) | 4 livelli | 15 livelli |

**Modulo** (tabella `moduli`). Un gioco, con `max_punti` come tetto anti-cheat. Le righe di giochi mai realizzati restano nel DB ma admin non le mostra: l'elenco dei giochi reali è la costante `GIOCHI` in `inc/giochi.php`.

**Registro** (tabella `registro`). Elenco permanente di tutti gli arrivati con nome (se lo scrivono per l'attestato), punti, orario di arrivo, ultimo download e numero di attestato. Sopravvive a reset e reimport dello schema.

**Stato di un gioco.** `attivo`, `disabilitato` (card grigia, non si gioca né si salvano punti) o `nascosto` (sparisce dalla home). Salvato in `impostazioni` come chiave `gioco_<codice>`, valido per tutte le sessioni.

## Tecnologie

- PHP 8 (`declare(strict_types=1)`, `match`, `never`, ecc.), MySQL/MariaDB via PDO, nessun framework
- JavaScript vanilla; Blockly 13.4.0 (Duck Coding) e qrcode copiati in `assets/js/vendor`
- FPDF 1.86 per i PDF (font base del PDF, serve solo `mbstring`)
- Hosting di produzione: Altervista; sviluppo: XAMPP su Linux
