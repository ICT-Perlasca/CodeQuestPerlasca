<?php
/**
 * Admin: login con ADMIN_PASS_HASH (max 5 tentativi ogni 5 minuti) e scelta della sessione su cui lavora il pannello.
 */
declare(strict_types=1);

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$azione = (string) ($_POST['azione'] ?? '');

/* ---------------------------------------------------------------- login */
if (empty($_SESSION['admin'])) {
    $errore = '';
    if ($metodo === 'POST' && $azione === 'login') {
        $tentativi = $_SESSION['admin_tentativi'] ?? ['n' => 0, 't' => 0];
        if (ADMIN_PASS_HASH === '') {
            $errore = 'Pannello disattivato: imposta ADMIN_PASS_HASH in inc/config.php.';
        } elseif ($tentativi['n'] >= 5 && time() - $tentativi['t'] < 300) {
            $errore = 'Troppi tentativi: riprova tra 5 minuti.';
        } elseif (password_verify((string) ($_POST['password'] ?? ''), ADMIN_PASS_HASH)) {
            session_regenerate_id(true);
            unset($_SESSION['admin_tentativi']);
            $_SESSION['admin'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            torna();
        } else {
            $_SESSION['admin_tentativi'] = ['n' => $tentativi['n'] + 1, 't' => time()];
            $errore = 'Password errata.';
        }
    }
    admin_head('Accesso');
    ?>
<body>
  <form class="card login" method="post">
    <h1>Admin Open Day</h1>
    <?php if ($errore !== ''): ?><p class="flash ko" role="alert"><?= $e($errore) ?></p><?php endif; ?>
    <input type="hidden" name="azione" value="login">
    <input type="password" name="password" placeholder="Password admin" autofocus required>
    <button class="primario" type="submit">Entra</button>
  </form>
</body></html>
<?php
    exit;
}

/* ---------------------------------------------------------------- sessione selezionata */
// L'admin lavora su UNA sessione alla volta (menu in alto a sinistra): panoramica, partecipanti e statistiche
// dei giochi sono di quella sessione. Senza scelta: la piu' recente in corso (le vere prima delle demo).
try {
    $sidAdmin = $_SESSION['admin_sessione'] ?? null;
    if (!is_int($sidAdmin) || sessione_riga($sidAdmin) === null) {
        $sidAdmin = sessione_predefinita();
        $_SESSION['admin_sessione'] = $sidAdmin;
    }
} catch (PDOException $ex) {
    // schema non importato (manca la tabella `sessioni`): spiega cosa fare invece di un errore 500 muto
    error_log('[openday] ' . $ex->getMessage());
    admin_head('Database non pronto');
    echo '<body style="padding:24px;max-width:640px;margin:auto"><div class="card"><h1>Database non pronto</h1>'
       . '<p>Nel database manca la tabella delle sessioni.</p>'
       . '<p>Apri phpMyAdmin, seleziona il database del progetto e importa <code>db/schema.sql</code>, poi ricarica.</p>'
       . (DEBUG ? '<p class="muted">' . htmlspecialchars($ex->getMessage(), ENT_QUOTES) . '</p>' : '') . '</div></body></html>';
    exit;
}
imposta_sessione($sidAdmin);
