#!/usr/bin/env bash
# Avvio/arresto dell'ambiente locale XAMPP per l'Open Day.
#   sudo bash dev/xampp.sh start   -> ferma Apache/MariaDB di sistema, avvia XAMPP,
#                                     collega il progetto in htdocs/openday e importa lo schema
#   sudo bash dev/xampp.sh reset   -> reimporta schema.sql (cancella i dati, tranne la tabella registro)
#   sudo bash dev/xampp.sh stop    -> ferma XAMPP e riavvia i servizi di sistema
set -euo pipefail

LAMPP=/opt/lampp
PROGETTO="$(cd "$(dirname "$0")/.." && pwd)"
LINK="$LAMPP/htdocs/openday"
DB=my_openday
SOCK="$LAMPP/var/mysql/mysql.sock"
MYSQL=("$LAMPP/bin/mysql" -u root --socket="$SOCK")
SERVIZI=(httpd mariadb)

[[ $EUID -eq 0 ]] || { echo "Serve root: sudo bash $0 ${1:-start}"; exit 1; }

importa_schema() {
    "${MYSQL[@]}" -e "CREATE DATABASE IF NOT EXISTS $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    "${MYSQL[@]}" "$DB" < "$PROGETTO/schema.sql"
    echo "Schema importato in $DB"
}

case "${1:-start}" in
start)
    for s in "${SERVIZI[@]}"; do
        if systemctl is-active --quiet "$s"; then
            echo "Fermo $s di sistema (occupa la porta di XAMPP)"
            systemctl stop "$s"
        fi
    done

    ln -sfn "$PROGETTO" "$LINK"
    "$LAMPP/lampp" startapache
    "$LAMPP/lampp" startmysql

    # il socket compare prima che il server accetti connessioni: aspetta il ping
    for _ in $(seq 30); do "$LAMPP/bin/mysqladmin" -u root --socket="$SOCK" ping &>/dev/null && break; sleep 1; done
    "$LAMPP/bin/mysqladmin" -u root --socket="$SOCK" ping &>/dev/null || { echo "MySQL di XAMPP non risponde"; exit 1; }

    if "${MYSQL[@]}" -e "USE $DB" 2>/dev/null; then
        echo "Database $DB gia' presente (usa 'reset' per reimportare)"
    else
        importa_schema
    fi

    IP=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}')
    echo
    echo "Pronto:  http://localhost/openday/login.php?user=openday   (password: binario101)"
    [[ -n ${IP:-} ]] && echo "Da smartphone (stessa Wi-Fi):  http://$IP/openday/login.php?user=openday"
    ;;
reset)
    # niente DROP DATABASE: schema.sql ricrea tutte le tabelle tranne `registro`, che e' permanente
    importa_schema
    ;;
stop)
    "$LAMPP/lampp" stop
    for s in "${SERVIZI[@]}"; do systemctl start "$s"; done
    echo "XAMPP fermato, servizi di sistema riavviati"
    ;;
*)
    echo "Uso: sudo bash $0 start|reset|stop"; exit 1
    ;;
esac
