# 2. Ambiente locale (XAMPP)

## Requisiti

- Linux con XAMPP in `/opt/lampp` (PHP 8+, MariaDB)
- `sudo` (lo script ferma Apache/MariaDB di sistema, che occupano le porte di XAMPP)

## Avvio

```bash
sudo bash dev/xampp.sh start
```

Lo script:

1. ferma `httpd` e `mariadb` di sistema se attivi;
2. collega il progetto in `/opt/lampp/htdocs/openday` (symlink);
3. avvia Apache e MySQL di XAMPP e aspetta che rispondano;
4. crea il database `my_openday` e importa `db/schema.sql` se il DB non esiste;
5. stampa gli indirizzi: `http://localhost/openday/login.php` e, da smartphone sulla stessa Wi-Fi, `http://<IP-del-pc>/openday/login.php`.

Altri comandi:

| Comando | Effetto |
|---|---|
| `sudo bash dev/xampp.sh reset` | reimporta `db/schema.sql` (azzera sessioni, partecipanti, punteggi; **non** `registro` né `blacklist`) |
| `sudo bash dev/xampp.sh stop` | ferma XAMPP e riavvia i servizi di sistema |

## Configurazione locale

Crea `inc/config.local.php` (ignorato da git). Se esiste, `inc/config.php` lo carica **al posto di sé stesso**, quindi deve definire tutte le stesse costanti:

```php
<?php
declare(strict_types=1);
const DB_HOST = 'localhost';
const DB_NAME = 'my_openday';
const DB_USER = 'root';
const DB_PASS = '';
const DB_SOCKET = '/opt/lampp/var/mysql/mysql.sock';
const OSPITE_USER = 'openday';
const OSPITE_PASS = 'binario101';
const ADMIN_PASS_HASH = '...';   // vedi sotto
const DEBUG = true;               // mostra gli errori reali nelle risposte JSON
```

Hash della password admin:

```bash
php -r 'echo password_hash("la-tua-password", PASSWORD_DEFAULT), PHP_EOL;'
```

## Prima sessione

Dopo l'import dello schema non esiste nessuna sessione e il login ospite è chiuso. Apri `http://localhost/openday/admin/`, accedi con la password admin e crea la prima sessione dalla pagina **Sessione** (vedi [guida admin](04-guida-admin.md)).

## Aggiornare un DB locale esistente

Non ci sono migrazioni: dopo una modifica dello schema in locale basta `sudo bash dev/xampp.sh reset` (cancella i dati di prova, non il registro). Per un DB di produzione vedi [Manutenzione](11-manutenzione.md#modifiche-allo-schema).

## Test dalla riga di comando

Non c'è una suite di test. Per provare senza XAMPP si può usare MariaDB temporanea + `php -S`; le pagine dei giochi si verificano aprendole nel browser (i livelli di Hack the Web(site) sono stati testati con Chrome headless). Il file `assets/js/giochi/modifica-gioco/motore.js` è scritto per poter girare anche in Node e verificare che le missioni siano risolvibili.
