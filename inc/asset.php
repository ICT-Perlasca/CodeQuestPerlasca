<?php
/**
 * Percorso di un file di assets/ con la data di modifica in coda: quando il file cambia
 * i telefoni scaricano la versione nuova invece di tenere quella vecchia in cache.
 */
declare(strict_types=1);

/** @param string $base '' per le pagine alla radice, '../' per quelle in giochi/ e admin/ */
function asset(string $percorso, string $base = ''): string
{
    $file = dirname(__DIR__) . '/assets/' . $percorso;
    return $base . 'assets/' . $percorso . '?v=' . (is_file($file) ? filemtime($file) : 0);
}
