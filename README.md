# CodeQuestPerlasca – Open Day Informatica, IIS "Giacomo Perlasca"

Web app PHP/MySQL per l'Open Day di Informatica: gli ospiti accedono con un account condiviso, scelgono un nickname e giocano a una serie di minigiochi. Punteggi e classifica sono in tempo reale; a fine percorso si scarica un attestato PDF con QR code.

## Funzioni

- Login ospite e onboarding (nickname + avatar)
- Classifica pubblica (solo nickname; i nomi veri si vedono solo in admin)
- Minigiochi: **Duck Coding** (blocchi Blockly, 28 livelli), **Binary Lights**, **Porte logiche**, **Modifica il gioco**, **Hack the Web(site)**
- Attestato PDF (FPDF) con QR valido 10 minuti; i primi 3 in classifica ricevono anche un certificato di merito
- Registro permanente (nomi, arrivi per turno, numero attestato, export CSV)
- Pannello `admin/` (pagine Panoramica, Sessioni, Giochi, Partecipanti, Registro, Sistema): sessione (Open Day / Laboratori), statistiche per gioco, reset

## Documentazione

Guide complete e dettagli tecnici in [`docs/`](docs/README.md) (installazione, deploy, pannello admin, giorno dell'evento, architettura, database, API, giochi, attestato, manutenzione, problemi, sicurezza).

[Guida per docenti e tutor](https://claude.ai/artifact/5jzNq4BeBaHSHD2UWiVFzA): come preparare e gestire la giornata dal pannello admin.

## Struttura

| Percorso | Contenuto |
|---|---|
| `index.php`, `login.php`, `classifica.php` | pagine pubbliche |
| `admin/` | pannello di amministrazione (`admin.php` rimanda qui) |
| `diploma.php`, `diploma_pdf.php` | attestato |
| `giochi/` | i minigiochi |
| `api/` | endpoint JSON |
| `inc/` | configurazione, DB, logica (sessioni, giochi, attestato, Duck Coding), librerie FPDF; protetta da `.htaccess` |
| `assets/` | `css/` (tema comune, pagine, giochi), `js/` (comune, pagine, un cartella per gioco), `vendor/` con le librerie copiate in locale (Blockly, qrcode) |
| `db/schema.sql` | schema del database |
| `dev/xampp.sh` | avvio dell'ambiente locale con XAMPP |

Nessuna dipendenza da CDN: l'evento può girare in LAN senza internet.

## Sviluppo locale (XAMPP)

1. Avvia XAMPP con `dev/xampp.sh`.
2. Crea `inc/config.local.php` (ignorato da git) con le costanti di `inc/config.php`: utente `root` senza password, socket `/opt/lampp/var/mysql`.
3. Importa `db/schema.sql`.

## Deploy su Altervista

1. Compila `inc/config.php` (host `localhost`, DB `my_<utente>`, utente `<utente>`, password vuota) e imposta `ADMIN_PASS_HASH`:
   ```
   php -r 'echo password_hash("la-tua-password", PASSWORD_DEFAULT), PHP_EOL;'
   ```
2. **Non** caricare `config.local.php`.
3. Importa `db/schema.sql`. Le tabelle `registro` e `blacklist` sono permanenti e non vanno mai cancellate.
4. Al primo accesso ad `admin/` crea una sessione (pagina Sessioni): nome, giornata, ora di fine, password ospiti. Finché non ne esiste una il login ospite resta chiuso. Si possono tenere **più sessioni aperte insieme** (es. una *demo* per gli studenti tutor e quelle vere): la password scritta al login decide in quale si entra; classifica, partecipanti e statistiche sono separati per sessione. Una sessione *demo* non scrive nel registro e non consuma numeri d'attestato. Per la LIM: `classifica.php?s=<numero sessione>`.

## Note

- La password dell'account ospite è in `inc/config.php`: tieni il repository privato.
- "Sblocca tutti i livelli" è un'opzione per sessione (admin → Sessione): lasciala spenta per le sessioni vere.
