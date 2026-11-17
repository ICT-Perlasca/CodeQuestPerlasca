<?php
/** Esci dalla sessione ospite (POST): torna al login. Il profilo resta nella sessione di gioco, si rientra con utente e password. */
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

avvia_sessione();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    unset($_SESSION['ospite'], $_SESSION['sessione_id'], $_SESSION['uuid'], $_SESSION['ultimo_salvataggio']);
    session_regenerate_id(true);
}
header('Location: login.php');
exit;
