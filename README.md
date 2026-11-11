# Open Day Informatica – IIS "Giacomo Perlasca"

Web app PHP/MySQL per l'Open Day di Informatica: gli ospiti accedono con un account condiviso, scelgono un nickname e giocano a una serie di minigiochi. Punteggi e classifica sono in tempo reale; a fine percorso si scarica un attestato PDF con QR code.

## Funzioni

- Login ospite e onboarding (nickname + avatar)
- Classifica pubblica (solo nickname; i nomi veri si vedono solo in admin)
- Minigiochi: **Duck Coding** (blocchi Blockly, 28 livelli), **Binary Lights**, **Porte logiche**, **Modifica il gioco**, **Hack the Web(site)**
- Attestato PDF (FPDF) con QR valido 10 minuti; i primi 3 in classifica ricevono anche un certificato di merito
- Registro permanente (nomi, arrivi per turno, numero attestato, export CSV)
- Pannello `admin.php`: sessione (Open Day / Laboratori), statistiche per gioco, reset

## Struttura

| Percorso | Contenuto |
|---|---|
| `index.php`, `login.php`, `classifica.php` | pagine pubbliche |
| `admin.php` | pannello di amministrazione |
| `diploma.php`, `diploma_pdf.php` | attestato |
| `giochi/` | i minigiochi |
| `api/` | endpoint JSON |
| `inc/` | configurazione, DB, librerie (protetta da `.htaccess`) |
| `assets/` | JS/CSS, librerie copiate in locale (Blockly, qrcode) |
| `schema.sql` | schema del database |
| `dev/xampp.sh` | avvio dell'ambiente locale con XAMPP |

Nessuna dipendenza da CDN: l'evento può girare in LAN senza internet.

## Sviluppo locale (XAMPP)

1. Avvia XAMPP con `dev/xampp.sh`.
2. Crea `inc/config.local.php` (ignorato da git) con le costanti di `inc/config.php`: utente `root` senza password, socket `/opt/lampp/var/mysql`.
3. Importa `schema.sql`.

## Deploy su Altervista

1. Compila `inc/config.php` (host `localhost`, DB `my_<utente>`, utente `<utente>`, password vuota) e imposta `ADMIN_PASS_HASH`:
   ```
   php -r 'echo password_hash("la-tua-password", PASSWORD_DEFAULT), PHP_EOL;'
   ```
2. **Non** caricare `config.local.php`.
3. Importa `schema.sql`. Su DB già esistenti le modifiche vanno applicate a mano (`ALTER`/`INSERT`): `CREATE TABLE IF NOT EXISTS` non aggiorna le tabelle. La tabella `registro` è permanente e non va mai cancellata.
4. Imposta la scadenza della sessione alla data dell'Open Day.
5. In `inc/codegame.php` verifica che `CODEGAME_SBLOCCA_TUTTO` sia `false`.

## Note

- La password dell'account ospite è in `inc/config.php`: tieni il repository privato.
