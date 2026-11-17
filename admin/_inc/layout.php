<?php
/**
 * Admin: aspetto delle pagine (testa, menu laterale, chiusura) e messaggi flash.
 * Il CSS e' in assets/css/admin.css.
 */
declare(strict_types=1);

/** Voci del menu: file => [icona, nome]. */
const ADMIN_VOCI = [
    'index.php'        => ['📊', 'Panoramica'],
    'sessione.php'     => ['📅', 'Sessione'],
    'giochi.php'       => ['🎮', 'Giochi'],
    'partecipanti.php' => ['👥', 'Partecipanti'],
    'registro.php'     => ['📒', 'Registro'],
    'sistema.php'      => ['⚙️', 'Sistema'],
];

function flash(string $messaggio, string $tipo = 'ok'): void
{
    $_SESSION['flash'] = [$tipo, $messaggio];
}

/** Torna alla pagina corrente (mantiene filtri e gioco scelto). */
function torna(): void
{
    $q = array_intersect_key($_GET, ['q' => 1, 'ord' => 1, 'dal' => 1, 'al' => 1, 'g' => 1]);
    header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . ($q ? '?' . http_build_query($q) : ''));
    exit;
}

function admin_head(string $titolo): void
{
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>' . htmlspecialchars($titolo, ENT_QUOTES) . ' · Admin · CodeQuestPerlasca</title>'
       . '<link rel="stylesheet" href="' . asset('css/admin.css', '../') . '"></head>';
}

/** Apre la pagina: testa, menu laterale, titolo e messaggio flash. */
function admin_inizio(string $file, string $titolo): void
{
    global $e, $csrf;
    global $sidAdmin;
    // senza nessuna sessione il pannello serve a poco: si parte dalla creazione.
    // Restano liberi registro (export dei dati) e sistema (reset).
    if ($sidAdmin === null && !in_array($file, ['sessione.php', 'registro.php', 'sistema.php'], true)) {
        flash('Prima di tutto crea una sessione di gioco.', 'ko');
        header('Location: sessione.php');
        exit;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    $riga = $sidAdmin !== null ? sessione_riga($sidAdmin) : null;
    $aperto = evento_aperto();

    admin_head($titolo);
    echo '<body><div class="layout"><nav class="menu"><h1>🛡️ Admin<small>CodeQuestPerlasca</small></h1>';
    $tutte = elenco_sessioni();
    if ($tutte) {
        echo '<form method="post" class="scelta-sessione"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="azione" value="admin_sessione">'
           . '<label>Sessione<select name="sessione" onchange="this.form.submit()">';
        foreach ($tutte as $r) {
            $statoR = ['attiva' => '●', 'chiusa' => '○', 'scaduta' => '○'][stato_sessione($r)];
            echo '<option value="' . (int) $r['id'] . '"' . ((int) $r['id'] === $sidAdmin ? ' selected' : '') . '>'
               . $statoR . ' ' . $e($r['nome']) . ((int) $r['demo'] === 1 ? ' (demo)' : '') . '</option>';
        }
        echo '</select></label><noscript><button type="submit">Vai</button></noscript></form>';
    }
    foreach (ADMIN_VOCI as $f => [$icona, $nome]) {
        echo '<a href="' . $f . '"' . ($f === $file ? ' class="on"' : '') . '><span>' . $icona . '</span> ' . $nome . '</a>';
    }
    echo '<a href="../classifica.php' . ($sidAdmin !== null ? '?s=' . $sidAdmin : '') . '" target="_blank"><span>🏆</span> Classifica live ↗</a><div class="sp"></div>';
    echo '<div class="live">';
    if ($riga !== null) {
        echo '<span class="stato' . ($aperto ? '' : ' chiuso') . '">' . ($aperto ? 'Sessione aperta' : 'Sessione chiusa') . '</span>'
           . '<br>fino al ' . date('d/m H:i', strtotime((string) $riga['scade_il']));
    } else {
        echo '<span class="stato chiuso">Nessuna sessione</span>';
    }
    echo '</div>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><button type="submit" name="azione" value="logout">🚪 Esci</button></form>';
    echo '</nav><main class="contenuto"><h1>' . $e($titolo) . '</h1>';
    if ($flash) {
        echo '<p class="flash ' . $e($flash[0]) . '" role="status">' . $e($flash[1]) . '</p>';
    }
}

function admin_fine(): void
{
    ?>
</main></div>
<script src="<?= asset('js/comune/avatar.js', '../') ?>"></script>
<script>
'use strict';
document.querySelectorAll('[data-avatar]').forEach((el) => {
  el.textContent = window.AVATAR[el.dataset.avatar] || '🤖';
});
document.querySelectorAll('form[data-conferma]').forEach((f) => {
  f.addEventListener('submit', (ev) => {
    if (!confirm(f.dataset.conferma)) ev.preventDefault();
  });
});
</script>
</body></html>
<?php
}
