# 13. Sicurezza e privacy

## Cosa è protetto e come

| Area | Misura |
|---|---|
| Configurazione | `inc/` è protetta da `inc/.htaccess` (`Require all denied`, anche per Apache 2.2). `inc/config.local.php` è in `.gitignore` |
| Errori | l'eccezione DB non espone credenziali/DSN (log lato server); le risposte JSON dicono solo "Errore interno" salvo `DEBUG = true` |
| Cookie di sessione | nome `OPENDAY`, `HttpOnly`, `SameSite=Lax`, `Secure` quando si usa HTTPS |
| Login ospite | confronto di utente e password per sessione; massimo 10 tentativi per sessione PHP; `session_regenerate_id` al login e al logout |
| Login admin | `password_verify` su `ADMIN_PASS_HASH`; 5 tentativi poi attesa di 5 minuti; pannello disattivato se l'hash è vuoto |
| CSRF (admin) | token per sessione su ogni POST (`hash_equals`), poi redirect |
| CSRF (API) | i POST richiedono `Content-Type: application/json` |
| Admin | `X-Frame-Options: DENY`, `noindex`, `Cache-Control: no-store` |
| SQL | solo prepared statement PDO (niente emulazione) |
| XSS | output con `htmlspecialchars` |
| Anti-cheat | tetto di punti per modulo e per sessione, un solo record per modulo, un salvataggio ogni 2 s, tempo di Duck Coding misurato dal server, livelli sequenziali verificati dal server |
| Concorrenza | `SELECT ... FOR UPDATE` sul partecipante: due salvataggi simultanei non perdono né duplicano punti |
| Nickname | 3-16 caratteri senza spazi; blacklist per sottostringa su forma normalizzata (anche leet-speak) |
| Token QR | 16 byte casuali (`random_bytes`), un solo token attivo per persona, scadenza breve |

## Limiti noti (da conoscere)

- La password degli ospiti è condivisa e visibile sul cartellone: serve a limitare l'accesso, non a identificare le persone. Le password delle sessioni sono salvate **in chiaro** (minuscole) nel DB perché vanno mostrate in admin.
- Il login ospite non ha un rate limit per IP, solo per sessione PHP: chi cancella i cookie può riprovare. Per un evento in presenza è considerato accettabile.
- Un punteggio inviato dal client è fidato fino al tetto del modulo: per Binary Lights, Porte logiche, Modifica il gioco e Hack the Web(site) un utente esperto potrebbe inviare direttamente il massimo. È un gioco, non un esame; Duck Coding è l'unico con calcolo lato server.
- `config.php` può contenere `OSPITE_PASS` di riserva: tieni il repository privato.

## Privacy

- La **classifica pubblica mostra solo nickname e avatar**; i nomi veri esistono solo se il ragazzo li scrive per l'attestato e li vede solo lo staff in admin (Partecipanti, Registro, CSV).
- I dati personali sono: nome e cognome (facoltativi), orario d'arrivo e punti, nel `registro` permanente. Non si raccolgono email, telefoni o dati di contatto.
- Il nome per l'attestato è memorizzato in `diplomi_token` solo finché il token è valido (poi viene cancellato) e in `registro.nome`.
- Il **CSV** del registro contiene i nomi: conservalo in un luogo riservato e cancellalo quando non serve più. **Svuota registro** (admin → Sistema) elimina tutti i nomi: usalo per rispettare i tempi di conservazione decisi dalla scuola.
- Per evitare nomi nel sito, istruisci i ragazzi a scegliere nickname di fantasia: il campo non accetta spazi, quindi scoraggia "Nome Cognome".
- Le sessioni demo non scrivono nel registro, quindi sono ideali per prove con i tutor.
