# 9. I giochi

Cinque minigiochi, definiti in `GIOCHI` (`inc/giochi.php`). Ogni pagina in `giochi/` chiama `richiedi_gioco_attivo('<codice>')` e mostra "Questo gioco non è disponibile" se i docenti l'hanno disabilitato o nascosto. In quasi tutti i giochi livelli e missioni si sbloccano in sequenza, a meno che la sessione abbia "Sblocca tutti i livelli".

I livelli giocabili dipendono dal tipo di giornata (tabella in [Panoramica](01-panoramica.md#concetti-chiave)).

## Duck Coding
`giochi/paperelle.php` · modulo `paperelle_coding` · livelli in `inc/codegame.php` · API `api/codegame.php`

Programmazione a blocchi con **Blockly 13.4.0** (copiato in `assets/js/vendor/blockly`, traduzione italiana in `msg/it.js`): si guida una papera su una mappa 8×8 fino allo stagno raccogliendo le paperelle. 28 livelli: i primi sono sequenze semplici, poi cicli `ripeti` annidati, `finché`, `se`, labirinti dove serve la regola della mano sinistra/destra, stagno trappola.

Mappa: `#` sasso, `.` prato, `P` partenza, `S` stagno, `o` paperella. Ogni livello ha direzione iniziale (`dir`: 0 su, 1 destra, 2 giù, 3 sinistra), blocchi disponibili, `ottimali` (blocchi della soluzione migliore) e `rif` (tempo di riferimento in secondi).

**Punti per livello**: `100 × tempo medio / tuo tempo`, tra 30 e 130, più 20 se i blocchi usati sono ≤ `ottimali`. Massimo 150; modulo max 4200.
La media è calcolata **solo sulla sessione corrente** ed è "prudente": ai tempi reali si aggiungono 3 tempi di riferimento (`CODEGAME_PESO_RIF`), così i primi giocatori non sono premiati o puniti da una media su 1-2 persone. Il tempo è misurato dal server (tabella `codegame_tempi`), minimo 2 secondi.

## Binary Lights
`giochi/binary-lights.php` · modulo `binary_lights` · API `save_score.php`

Si compongono numeri decimali accendendo gli 8 bit di un byte. Round: 5 (Open Day) o 12 (laboratori), fino a **300 punti** l'uno. Il numero bersaglio parte dai 4 bit bassi (1-15) e cresce fino a 8 bit pieni (128-255): all'Open Day gli intervalli sono 1-15, 16-63, 64-127, 128-255, 128-255; nei laboratori 12 round più graduali. Il numero di round dipende da `TIPI_GIORNATA`; il tetto è `min(max_punti, round × 300)`. Include una guida introduttiva a slide (`assets/js/comune/guida.js`).

## Porte logiche
`giochi/porte-logiche.php` · `assets/js/giochi/porte-logiche/` (`motore.js` = livelli e circuiti, `pagina.js` = pagina) · modulo `porte_logiche`

Circuiti disegnati in SVG con porte AND, OR, NOT, XOR. Tipi di sfida: accendi la lampadina, tutte accese, scegli la porta, numeri, `indovina`. 19 livelli da 100 punti:

- 1-3: **scatole misteriose**: le porte sono nascoste, si deduce la regola dalle prove; la spiegazione compare solo dopo la scoperta
- 4-14: circuiti con pezzi mancanti, interruttori, cassaforte, votazione, "Fatto in casa"...
- 15-18: i quattro livelli **difficili** (Specchi, Due lampadine, Tre pezzi mancanti, Il guardiano), inclusi nell'Open Day
- 19: "Il computer fa le somme": sommatore binario

Open Day: livelli `[1, 2, 3, 4, 15, 16, 17, 18]`; laboratori: tutti. La lista dei livelli è in `TIPI_GIORNATA['giochi']['porte_livelli']`.

## Modifica il gioco
`giochi/modifica-gioco.php` · `assets/js/giochi/modifica-gioco/` (`motore.js` = fisica e missioni, `pagina.js`) · modulo `modifica_gioco`

Una palla che rimbalza in un campo 400×300. Il codice mostrato ha variabili modificabili (`gravita`, `rimbalzo`, `velocitaX`, `velocitaY`, `raggio`, `colorePalla`, `coloreSfondo`, `scia`) e ogni missione chiede un obiettivo. Le missioni sono, nell'ordine: Cambia colore, Sulla Luna, Canestro!, Tocca il cielo, Palla gigante, Rimbalzo perfetto, Palla di piombo, Nello spazio, Dietro il muro, Tiro da campione. Alcune variabili sono bloccate dalla missione. 10 missioni da 100 punti; la fisica gira a 60 passi/secondo ed è verificabile anche in Node.

## Hack the Web(site)
`giochi/hack-website.php` · `assets/js/giochi/hack-website/` (`ispeziona.js`, `livelli.js`, `pagina.js`) · modulo `hack_website`

Un pannello **Ispeziona** simulato in stile Chrome che lavora sul DOM vero della pagina `#sito`: si modifica HTML e CSS per "liberare le paperelle". Funziona anche con l'F12 vero. 15 livelli da 100 punti (i primi tre facilissimi: titolo, colore, dimensione del testo). Open Day: i primi 4 (titolo, colore, scritta minuscola, porta chiusa).

Esiste un **livello segreto ★** senza punti: la parola è nascosta in un commento HTML in fondo alla pagina e si trova solo con l'F12 vero o Ctrl+U.

## Riepilogo punti

| Gioco | Per livello | Open Day | Laboratori |
|---|---|---|---|
| Duck Coding | fino a 150 | 6 livelli | 28 livelli |
| Binary Lights | 300 | 5 round (1500) | 12 round (3600) |
| Porte logiche | 100 | 8 (800) | 19 (1900) |
| Modifica il gioco | 100 | 4 (400) | 10 (1000) |
| Hack the Web(site) | 100 | 4 (400) | 15 (1500) |

## Progresso in home

La home calcola i livelli fatti così: Duck Coding dai livelli risolti in `codegame_tempi`; Binary Lights è "fatto" se ha almeno un punto (i round non valgono tutti 300); gli altri come `punti / per` livello, con `per` da `GIOCHI`. I giochi sono presentati senza ordine ("Scegli la tua sfida"), si gioca come si preferisce.
