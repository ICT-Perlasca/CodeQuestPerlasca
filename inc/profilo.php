<?php
/**
 * Nickname e identificativo (uuid) dei partecipanti.
 */
declare(strict_types=1);

/** Minuscolo, leet-speak tradotto (0->o, 1->i, ...), solo lettere: forma usata per la blacklist. */
function normalizza_nickname(string $nickname): string
{
    $norm = strtr(mb_strtolower($nickname), ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's']);
    return preg_replace('/[^\p{L}]/u', '', $norm);
}

function uuid_valido(string $uuid): bool
{
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) === 1;
}

/** Confronta il nickname normalizzato con la blacklist (per sottostringa). */
function nickname_ammesso(string $nickname): bool
{
    $norm = normalizza_nickname($nickname);

    foreach (db()->query('SELECT parola FROM blacklist')->fetchAll(PDO::FETCH_COLUMN) as $parola) {
        if ($parola !== '' && str_contains($norm, mb_strtolower($parola))) {
            return false;
        }
    }
    return true;
}
