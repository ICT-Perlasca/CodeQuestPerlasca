<?php
/**
 * Admin: intervallo di date del registro (pagina Registro ed export CSV).
 */
declare(strict_types=1);

/**
 * Intervallo del registro da ?dal=&al= (formato datetime-local, minuti inclusi).
 * Di default l'intera giornata dell'ultimo arrivo (o di oggi).
 *
 * @return array{0: DateTime, 1: DateTime}
 */
function intervallo_registro(PDO $pdo): array
{
    $leggi = static fn(string $k): ?DateTime => DateTime::createFromFormat('Y-m-d\TH:i', (string) ($_GET[$k] ?? '')) ?: null;
    $dal = $leggi('dal');
    $al = $leggi('al');
    if ($dal === null || $al === null) {
        $ultimo = $pdo->query('SELECT MAX(arrivato_il) FROM registro')->fetchColumn();
        $giorno = $ultimo ? date('Y-m-d', strtotime($ultimo)) : date('Y-m-d');
        $dal ??= new DateTime("$giorno 00:00");
        $al ??= new DateTime("$giorno 23:59");
    }
    return $al < $dal ? [$al, $dal] : [$dal, $al];
}

/** Parametri SQL dell'intervallo: dal HH:MM:00 ad al HH:MM:59. */
function parametri_intervallo(DateTime $dal, DateTime $al): array
{
    return [$dal->format('Y-m-d H:i:00'), $al->format('Y-m-d H:i:59')];
}
