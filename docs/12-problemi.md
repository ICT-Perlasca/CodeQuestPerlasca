# 12. Risoluzione problemi

Con `DEBUG = true` in `inc/config.php` (solo in sviluppo) le risposte JSON mostrano l'errore reale. In produzione il dettaglio è nel log PHP del server (`[openday] ...`).

## Login e accesso

| Sintomo | Causa probabile | Cosa fare |
|---|---|---|
| "Utente o password errati" | utente/password diversi da quelli della sessione (il confronto è in minuscolo) o sessione inesistente | controlla in admin → Sessione |
| "... è terminato. Ti aspettiamo all'IIS Perlasca!" | la sessione è scaduta o chiusa | cambia l'ora di fine o crea una nuova sessione |
| "Troppi tentativi: chiedi la password allo staff" | 10 errori nella stessa sessione del browser | chiudi e riapri il browser (nuova sessione PHP) |
| Il login non funziona mai, appena installato | non esiste nessuna sessione | creala da admin → Sessione |
| Admin: "Pannello disattivato" | `ADMIN_PASS_HASH` vuoto | genera l'hash e mettilo in `inc/config.php` |
| Admin: "Troppi tentativi" | 5 errori in 5 minuti | aspetta 5 minuti |
| Admin: "Database non pronto" | manca la tabella `sessioni` | importa `db/schema.sql` |
| `/admin` rimanda alla pagina di login in continuazione | cookie bloccati | abilita i cookie; sul sito in HTTPS il cookie è `secure` |

## Gioco e punteggi

| Sintomo | Causa | Cosa fare |
|---|---|---|
| "Sessione scaduta: rifai il login" (401) | cookie PHP scaduto o sessione chiusa | rifare il login |
| "Profilo non riconosciuto: reinserisci il nickname" (403) | l'uuid del browser non è quello legato alla sessione PHP | ricarica la home e conferma il nickname |
| "Profilo non trovato" (404) | partecipante eliminato da admin | rifare l'onboarding (la home lo fa da sola) |
| "Nickname già preso" (409) | in quella sessione lo usa un altro | scegline un altro |
| "Troppo veloce! Riprova tra un attimo" (429) | più di un salvataggio ogni 2 secondi per gioco | aspetta un istante |
| "Prima risolvi il livello N" (409) | livelli sequenziali | risolvi il precedente o attiva "Sblocca tutti i livelli" |
| "Troppo veloce per essere vero!" | Duck Coding: soluzione in meno di 2 s | normale: riprova |
| Il punteggio non sale | i punti sono il **record**, rigiocare non somma; oppure il tetto del modulo | nessun problema |
| Un gioco appare "non disponibile" | stato Disabilitato/Nascosto | admin → Giochi |
| I punti di un livello non cambiano dopo averlo rigiocato | Duck Coding conta solo la prima soluzione | previsto |

## Classifica

| Sintomo | Cosa fare |
|---|---|
| La LIM mostra la sessione sbagliata | aggiungi `?s=<numero sessione>` all'indirizzo |
| Vuota | si vedono solo i partecipanti con punti > 0 e non nascosti |
| Badge "offline" | il proiettore non raggiunge il server: controlla rete e indirizzo |

## Attestato

| Sintomo | Cosa fare |
|---|---|
| "Il download degli attestati è chiuso" | la scadenza è passata: sposta "Download attestati fino al" in admin → Sessione |
| "QR code scaduto" | genera un nuovo QR dalla pagina del diploma (vale `qr_minuti` minuti) |
| "Diploma non disponibile" | il partecipante è stato nascosto: mostralo in admin → Partecipanti |
| Il PDF non si genera / errore sul registro | controlla che `registro` abbia la colonna `numero_attestato` (se il DB è vecchio serve un `ALTER` manuale) |
| Caratteri strani nel nome | il nome accetta solo lettere latine/accentate; altre lettere possono non essere stampabili nei font base del PDF |

## Database e deploy

| Sintomo | Cosa fare |
|---|---|
| "Database non raggiungibile" | controlla `DB_*` in `inc/config.php`; in locale `DB_SOCKET` e che MariaDB di XAMPP sia attivo |
| Su Altervista le modifiche allo schema non compaiono | `CREATE TABLE IF NOT EXISTS` non aggiorna: serve `ALTER` manuale |
| Dopo l'upload tutto è rotto / vedo i dati locali | è stato caricato `inc/config.local.php` oppure sovrascritto `inc/config.php`: rimuovi/ripristina |
| `inc/` raggiungibile dal browser | manca `inc/.htaccess` sul server |
| XAMPP: porta occupata | `dev/xampp.sh start` ferma `httpd`/`mariadb` di sistema; con `stop` si riavviano |

## Mobile

- Il sito usa `localStorage` per ricordare il profilo: in navigazione privata o con storage bloccato funziona comunque, ma al ricaricamento il ragazzo dovrà confermare di nuovo il nickname.
- Se cambia sessione (altro utente/password) il profilo salvato nel browser viene scartato di proposito.
