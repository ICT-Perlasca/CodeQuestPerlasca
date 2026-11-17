<?php
/**
 * Calcolo del punteggio totale di un partecipante.
 */
declare(strict_types=1);

/**
 * Ricalcola punti_totali del partecipante come somma dei suoi moduli e lo
 * copia nel registro. Va chiamata dentro la transazione che ha bloccato la
 * riga del partecipante (SELECT ... FOR UPDATE).
 */
function aggiorna_totale(PDO $pdo, int $partecipanteId, string $uuid): int
{
    $pdo->prepare(
        'UPDATE partecipanti
            SET punti_totali = (SELECT COALESCE(SUM(punti), 0) FROM punteggi WHERE partecipante_id = :pid)
          WHERE id = :id'
    )->execute(['pid' => $partecipanteId, 'id' => $partecipanteId]);

    $st = $pdo->prepare('SELECT punti_totali FROM partecipanti WHERE id = ?');
    $st->execute([$partecipanteId]);
    $totale = (int) $st->fetchColumn();

    if (!sessione_demo()) {
        $pdo->prepare('UPDATE registro SET punti = ? WHERE uuid = ?')->execute([$totale, $uuid]);
    }
    return $totale;
}
