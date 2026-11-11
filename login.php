<?php
/**
 * Login ospite condiviso. Link da QR code: login.php?user=openday
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';

avvia_sessione();

$errore = '';
$utente = (string) ($_POST['user'] ?? $_GET['user'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $tentativi = $_SESSION['tentativi_login'] ?? 0;

    if ($tentativi >= 10) {
        $errore = 'Troppi tentativi: chiedi la password allo staff.';
    } elseif (!evento_aperto()) {
        $errore = sessione_attivita()['etichetta'] . " e' terminato. Ti aspettiamo all'IIS Perlasca!";
    } elseif (hash_equals(OSPITE_USER, $utente) && hash_equals(OSPITE_PASS, strtolower(trim($password)))) {
        session_regenerate_id(true);
        $_SESSION['ospite'] = true;
        $_SESSION['tentativi_login'] = 0;
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['tentativi_login'] = $tentativi + 1;
        $errore = 'Password errata. Suggerimento: e\' scritta sul cartellone all\'ingresso!';
    }
}
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>CodeQuestPerlasca · Accesso</title>
<style>
  :root { --bg:#0b1020; --panel:#141b33; --line:#2a3566; --text:#e8ecff; --muted:#8e98c7; --accent:#5cc8ff; --ko:#ff5c7a; }
  * { box-sizing:border-box; }
  body { margin:0; min-height:100dvh; display:grid; place-items:center; padding:16px;
         background:var(--bg); color:var(--text); font:16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  form { width:100%; max-width:360px; background:var(--panel); border:1px solid var(--line); border-radius:16px; padding:24px; display:grid; gap:14px; }
  h1 { margin:0; font-size:1.4rem; }
  p { margin:0; color:var(--muted); font-size:.95rem; }
  label { display:grid; gap:6px; font-size:.9rem; color:var(--muted); }
  input { font:inherit; font-size:1.1rem; padding:12px 14px; border-radius:10px; border:1px solid var(--line); background:#0e1430; color:var(--text); }
  input:focus { outline:2px solid var(--accent); outline-offset:1px; }
  button { font:inherit; font-weight:700; font-size:1.1rem; padding:14px; border:0; border-radius:12px; background:var(--accent); color:#04121c; cursor:pointer; }
  .errore { color:var(--ko); font-weight:600; }
</style>
</head>
<body>
<form method="post" autocomplete="off">
  <h1><?= htmlspecialchars(sessione_attivita()['etichetta'], ENT_QUOTES) ?> · Informatica</h1>
  <p>IIS "Giacomo Perlasca" — entra e gioca!</p>
  <?php if ($errore !== ''): ?><p class="errore" role="alert"><?= htmlspecialchars($errore, ENT_QUOTES) ?></p><?php endif; ?>
  <label>Utente
    <input name="user" value="<?= htmlspecialchars($utente, ENT_QUOTES) ?>" autocapitalize="none" required>
  </label>
  <label>Password dell'evento
    <input name="password" type="password" autocapitalize="none" autofocus required>
  </label>
  <button type="submit">Entra</button>
</form>
</body>
</html>
