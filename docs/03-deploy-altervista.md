# 3. Deploy su Altervista

## Prima installazione

1. **Configurazione.** Compila `inc/config.php` con i dati del servizio:
   - `DB_HOST = 'localhost'`, `DB_NAME = 'my_<utente>'`, `DB_USER = '<utente>'`, `DB_PASS = ''` (su Altervista la password MySQL è vuota), `DB_SOCKET = ''`
   - `ADMIN_PASS_HASH`: hash generato con `php -r 'echo password_hash("...", PASSWORD_DEFAULT), PHP_EOL;'`
   - `DEBUG = false`
2. **Non caricare** `inc/config.local.php`: se esiste sostituirebbe la configurazione di produzione.
3. **Database.** Da phpMyAdmin importa `db/schema.sql` nel database del progetto.
4. **Upload** di tutto il progetto via FTP/FTPS.
5. **Prima sessione.** Apri `/admin/`, accedi e crea la sessione dalla pagina **Sessione**: finché non esiste, il login ospite resta chiuso.

## Aggiornamenti successivi

- Carica **solo i file modificati**, mai `inc/config.php` (sul server contiene i dati veri). Ricorda che anche `assets/` (CSS/JS) fa parte del sito: i file cambiati lì vanno caricati come i `.php`; le pagine aggiungono alla loro URL la data di modifica, quindi i telefoni scaricano la versione nuova.
- Con `lftp` il certificato di `ftp.<nome>.altervista.org` non corrisponde al nome host: serve `set ssl:verify-certificate no`.
- Il progetto **non ha file di migrazione**: se cambi lo schema su un DB già in uso, applica gli `ALTER`/`INSERT` a mano da phpMyAdmin (`CREATE TABLE IF NOT EXISTS` non aggiorna le tabelle esistenti, e il MySQL di Altervista non supporta `ADD COLUMN IF NOT EXISTS`). Tieni `db/schema.sql` allineato. Vedi [Manutenzione](11-manutenzione.md#modifiche-allo-schema).
- Le tabelle `registro` e `blacklist` sono permanenti: mai `DROP`.

## Da verificare dopo ogni pubblicazione

- [ ] `admin/` si apre e accetta la password
- [ ] esiste almeno una sessione attiva con utente/password noti
- [ ] il login ospite funziona, si registra un nickname, si salva un punteggio
- [ ] `classifica.php` mostra il nickname
- [ ] l'attestato si scarica e il QR funziona
- [ ] `inc/` non è raggiungibile dal browser (es. `/inc/config.php` deve dare 403: lo garantisce `inc/.htaccess`)
- [ ] la data di fine sessione e la scadenza del download attestati sono quelle giuste

## Note

- La password predefinita degli ospiti in `inc/config.php` (`OSPITE_PASS`) è solo un valore di partenza: utente e password reali si scelgono per ogni sessione in admin. Tieni comunque il repository **privato**.
- Se il nuovo DB deve riutilizzare un registro esistente, **non** reimportare `db/schema.sql` su quel DB senza controllare: le due tabelle permanenti usano `CREATE TABLE IF NOT EXISTS` proprio per sopravvivere, le altre vengono ricreate (e svuotate).
