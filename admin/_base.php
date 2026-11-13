<?php
/**
 * Base comune delle pagine admin (docenti/tutor): login, controllo CSRF, azioni POST, menu laterale.
 * Accesso con la password il cui hash e' in ADMIN_PASS_HASH (inc/config.php).
 * Tutte le azioni sono POST con token CSRF e terminano con redirect (PRG) sulla pagina da cui arrivano.
 *
 * Pagine: index (panoramica), sessione, giochi, partecipanti, registro, sistema.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/codegame.php';
require_once __DIR__ . '/../inc/diploma.php'; // testi e nome del PDF per l'anteprima della sessione

avvia_sessione();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

$e = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES);

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

/** Torna alla pagina corrente (mantiene filtri e gioco scelto). */
function torna(): void
{
    $q = array_intersect_key($_GET, ['q' => 1, 'ord' => 1, 'dal' => 1, 'al' => 1, 'g' => 1]);
    header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . ($q ? '?' . http_build_query($q) : ''));
    exit;
}

function admin_css(): void
{
    echo <<<'CSS'
  :root { --bg:#0b1020; --panel:#141b33; --panel-2:#1c2547; --line:#2a3566; --text:#e8ecff; --muted:#8e98c7;
          --on:#ffd84d; --ok:#3ddc97; --ko:#ff5c7a; --accent:#5cc8ff; --mono: ui-monospace, Menlo, Consolas, monospace; }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--text); font:15px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  a { color:var(--accent); }
  .card { background:var(--panel); border:1px solid var(--line); border-radius:14px; padding:16px; }
  .griglia { display:grid; gap:16px; grid-template-columns:1fr; }
  @media (min-width:760px) { .griglia { grid-template-columns:1fr 1fr; } }
  input, button { font:inherit; color:inherit; }
  input[type=text], input[type=password], input[type=search], input[type=datetime-local] {
    padding:9px 11px; border-radius:9px; border:1px solid var(--line); background:#0e1430; color:var(--text); min-width:0; }
  input[type=datetime-local] { color-scheme:dark; }
  button, .btn { padding:9px 14px; border-radius:9px; border:1px solid var(--line); background:var(--panel-2); cursor:pointer; font-weight:600; text-decoration:none; color:var(--text); display:inline-block; }
  .primario { background:var(--accent); color:#04121c; border-color:var(--accent); }
  .pericolo { background:transparent; color:var(--ko); border-color:var(--ko); }
  .pericolo.pieno { background:var(--ko); color:#1a0006; }
  .riga-form { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  .flash { padding:12px 14px; border-radius:10px; font-weight:600; }
  .flash.ok { background:rgba(61,220,151,.12); color:var(--ok); border:1px solid var(--ok); }
  .flash.ko { background:rgba(255,92,122,.12); color:var(--ko); border:1px solid var(--ko); }
  .stato { display:inline-flex; align-items:center; gap:6px; font-weight:700; }
  .stato::before { content:""; width:10px; height:10px; border-radius:50%; background:var(--ok); }
  .stato.chiuso { color:var(--ko); } .stato.chiuso::before { background:var(--ko); }
  .numeri { display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; margin-bottom:12px; }
  .numeri div { background:var(--panel-2); border-radius:10px; padding:10px; text-align:center; }
  .numeri b { display:block; font-family:var(--mono); font-size:1.4rem; color:var(--on); }
  .numeri span { color:var(--muted); font-size:.8rem; }
  table { width:100%; border-collapse:collapse; }
  th, td { text-align:left; padding:7px 6px; border-bottom:1px solid var(--line); }
  th { color:var(--muted); font-weight:600; font-size:.85rem; }
  td.num { font-family:var(--mono); text-align:right; }
  .muted { color:var(--muted); }
  .lista { list-style:none; margin:0; padding:0; display:grid; gap:8px; }
  .partecipante { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:10px; border-radius:10px; background:var(--panel-2); }
  .partecipante.nascosto { opacity:.55; }
  .partecipante .av { font-size:1.6rem; }
  .partecipante .info { flex:1 1 140px; min-width:0; }
  .partecipante .info b { display:block; overflow-wrap:anywhere; }
  .partecipante .info span { color:var(--muted); font-size:.8rem; }
  .partecipante .pt { font-family:var(--mono); font-weight:800; color:var(--on); min-width:4.5em; text-align:right; }
  .partecipante form { display:inline; }
  .azioni { display:flex; gap:6px; }
  .tag { font-size:.75rem; border:1px solid var(--line); border-radius:999px; padding:1px 8px; color:var(--muted); margin-left:6px; }
  .chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; }
  .chip { display:inline-flex; align-items:center; gap:4px; background:var(--panel-2); border-radius:999px; padding:3px 4px 3px 10px; font-family:var(--mono); font-size:.85rem; }
  .chip button { padding:0 8px; border:0; background:transparent; color:var(--ko); font-size:1rem; }
  .login { max-width:340px; margin:12vh auto; display:grid; gap:12px; }
  .partecipante .info .nome { display:inline; color:var(--text); font-weight:400; font-size:inherit; }
  h3 { margin:16px 0 8px; font-size:.9rem; color:var(--muted); font-weight:600; }
  .barra { height:10px; border-radius:5px; background:var(--accent); min-width:2px; }
  td.barra-cella { width:40%; }
  .scorri { overflow-x:auto; }
  .campi { display:grid; gap:12px; grid-template-columns:1fr; }
  @media (min-width:760px) { .campi { grid-template-columns:repeat(3, 1fr); } .campi .largo { grid-column:1 / -1; } }
  .campi label { display:grid; gap:6px; color:var(--muted); font-size:.9rem; }
  .campi label > .muted { font-size:.8rem; }
  select, textarea, input[type=date], input[type=time] { font:inherit; color:var(--text); padding:9px 11px; border-radius:9px;
    border:1px solid var(--line); background:#0e1430; color-scheme:dark; min-width:0; }
  textarea { resize:vertical; }
  .campi input[type=text] { width:100%; }
  .anteprima-testo { background:#fff; color:#0b1020; border-radius:10px; padding:14px 16px; text-align:center; display:grid; gap:8px; }
  .anteprima-testo b { color:#0a6eaa; letter-spacing:.12em; font-size:.85rem; }
  .anteprima-testo p { margin:0; font-family:"Times New Roman", Times, serif; font-size:1.05rem; }
  .anteprima-testo .muted { font-family:inherit; font-size:.8rem; color:#5f688c; }
  details summary { cursor:pointer; color:var(--accent); font-weight:600; margin-top:16px; }
  h1 { font-size:1.3rem; margin:0; }
  h2 { margin:0 0 12px; font-size:1.1rem; }
  .layout { display:grid; grid-template-columns:220px 1fr; min-height:100dvh; }
  nav.menu { background:var(--panel); border-right:1px solid var(--line); padding:16px 12px; display:flex; flex-direction:column; gap:4px; position:sticky; top:0; height:100dvh; }
  nav.menu h1 { font-size:1rem; margin:0 8px 12px; }
  nav.menu h1 small { display:block; color:var(--muted); font-weight:400; }
  nav.menu a { display:flex; gap:10px; align-items:center; padding:10px 12px; border-radius:10px; color:var(--muted); text-decoration:none; }
  nav.menu a:hover { background:var(--panel-2); color:var(--text); }
  nav.menu a.on { background:var(--panel-2); color:var(--text); box-shadow:inset 3px 0 var(--accent); }
  nav.menu .sp { flex:1; }
  nav.menu .live { margin:8px; padding:10px; border-radius:10px; background:#0e1430; font-size:.8rem; color:var(--muted); }
  nav.menu form { margin:0; }
  nav.menu button { width:100%; text-align:left; background:transparent; border:0; color:var(--muted); padding:10px 12px; }
  main.contenuto { padding:20px; display:grid; gap:16px; align-content:start; max-width:960px; width:100%; }
  main.contenuto > h1 { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .kpi { display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:12px; }
  .kpi div { background:var(--panel); border:1px solid var(--line); border-radius:14px; padding:14px; }
  .kpi strong { display:block; font:800 1.7rem var(--mono); color:var(--on); }
  .kpi span { color:var(--muted); font-size:.8rem; }
  .pericolo-card { border-color:rgba(255,92,122,.45); }
  .login-pagina { margin:0; }
  @media (max-width:760px) {
    .layout { grid-template-columns:1fr; }
    nav.menu { position:static; height:auto; flex-direction:row; overflow-x:auto; padding:8px; }
    nav.menu h1, nav.menu .live, nav.menu .sp { display:none; }
    nav.menu a, nav.menu button { white-space:nowrap; }
    main.contenuto { padding:12px; }
  }
CSS;
}

function admin_head(string $titolo): void
{
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>' . htmlspecialchars($titolo, ENT_QUOTES) . ' · Admin · CodeQuestPerlasca</title><style>';
    admin_css();
    echo '</style></head>';
}

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

/* ---------------------------------------------------------------- azioni */
if ($metodo === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        flash('Sessione scaduta, riprova.', 'ko');
        torna();
    }

    $pdo = db();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    switch ($azione) {
        case 'logout':
            session_regenerate_id(true);
            unset($_SESSION['admin'], $_SESSION['csrf']);
            header('Location: index.php');
            exit;

        case 'visibilita':
            $nascosto = ($_POST['nascosto'] ?? '') === '1' ? 1 : 0;
            $pdo->prepare('UPDATE partecipanti SET nascosto = ? WHERE id = ?')->execute([$nascosto, $id]);
            flash($nascosto ? 'Nickname nascosto dalla classifica.' : 'Nickname di nuovo visibile.');
            break;

        case 'elimina':
        case 'banna':
            $st = $pdo->prepare('SELECT nickname FROM partecipanti WHERE id = ?');
            $st->execute([$id]);
            $nick = $st->fetchColumn();
            if ($nick === false) {
                flash('Partecipante non trovato.', 'ko');
                break;
            }
            $pdo->prepare('DELETE FROM partecipanti WHERE id = ?')->execute([$id]);
            $msg = "«{$nick}» eliminato: al prossimo accesso dovra' scegliere un nuovo nickname.";
            if ($azione === 'banna') {
                $parola = normalizza_nickname($nick);
                if (mb_strlen($parola) >= 3) {
                    $pdo->prepare('INSERT IGNORE INTO blacklist (parola) VALUES (?)')->execute([$parola]);
                    $msg .= " «{$parola}» aggiunto alla blacklist.";
                }
            }
            flash($msg);
            break;

        case 'evento':
            $scadenza = DateTime::createFromFormat('Y-m-d\TH:i', (string) ($_POST['scadenza'] ?? ''));
            if ($scadenza === false) {
                flash('Data di scadenza non valida.', 'ko');
                break;
            }
            $st = $pdo->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?, ?) ON DUPLICATE KEY UPDATE valore = VALUES(valore)');
            $st->execute(['evento_attivo', isset($_POST['attivo']) ? '1' : '0']);
            $st->execute(['scadenza', $scadenza->format('Y-m-d H:i:00')]);
            flash('Impostazioni evento salvate.');
            break;

        case 'sessione':
            $tipo = (string) ($_POST['tipo'] ?? '');
            $data = DateTime::createFromFormat('!Y-m-d', (string) ($_POST['data'] ?? ''));
            // testo su una riga, spazi compattati; i font del PDF conoscono solo Windows-1252 (niente emoji)
            $pulisci = static fn(string $k): string => trim((string) preg_replace('/\s+/u', ' ', (string) ($_POST[$k] ?? '')));
            $titolo = $pulisci('titolo');
            $descrizione = $pulisci('descrizione');
            $stampabile = static fn(string $t): bool => mb_convert_encoding(mb_convert_encoding($t, 'Windows-1252', 'UTF-8'), 'UTF-8', 'Windows-1252') === $t;

            if (!isset(TIPI_GIORNATA[$tipo])) {
                flash('Tipo di giornata non valido.', 'ko');
            } elseif ($data === false) {
                flash("Data dell'attività non valida.", 'ko');
            } elseif (mb_strlen($titolo) > 60 || mb_strlen($descrizione) > 200) {
                flash('Titolo max 60 caratteri, descrizione max 200.', 'ko');
            } elseif (!$stampabile($titolo) || !$stampabile($descrizione)) {
                flash("Titolo o descrizione contengono caratteri che il PDF non sa stampare (emoji, simboli): toglili.", 'ko');
            } else {
                $st = $pdo->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?, ?) ON DUPLICATE KEY UPDATE valore = VALUES(valore)');
                $st->execute(['sessione_tipo', $tipo]);
                $st->execute(['sessione_data', $data->format('Y-m-d')]);
                $st->execute(['sessione_titolo', $titolo]);
                $st->execute(['sessione_descrizione', $descrizione]);
                $msg = 'Sessione salvata: ' . TIPI_GIORNATA[$tipo]['etichetta'] . ' del ' . $data->format('d/m/Y') . '.';
                // facoltativo: chiude il login all'ora di fine dello stesso giorno
                $fine = DateTime::createFromFormat('Y-m-d H:i', $data->format('Y-m-d') . ' ' . (string) ($_POST['fine'] ?? ''));
                if ($fine !== false) {
                    $st->execute(['scadenza', $fine->format('Y-m-d H:i:00')]);
                    $msg .= ' Login aperto fino alle ' . $fine->format('H:i') . '.';
                }
                flash($msg);
            }
            break;

        case 'giochi':
            $st = $pdo->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?, ?) ON DUPLICATE KEY UPDATE valore = VALUES(valore)');
            foreach (array_keys(GIOCHI) as $codice) {
                $v = (string) ($_POST['stato'][$codice] ?? 'attivo');
                $st->execute(["gioco_$codice", isset(STATI_GIOCO[$v]) ? $v : 'attivo']);
            }
            flash('Stato dei giochi salvato.');
            break;

        case 'blacklist_aggiungi':
            $parola = normalizza_nickname(trim((string) ($_POST['parola'] ?? '')));
            if (mb_strlen($parola) < 2 || mb_strlen($parola) > 40) {
                flash('Parola non valida (2-40 lettere).', 'ko');
                break;
            }
            $pdo->prepare('INSERT IGNORE INTO blacklist (parola) VALUES (?)')->execute([$parola]);
            flash("«{$parola}» aggiunto alla blacklist.");
            break;

        case 'blacklist_rimuovi':
            $pdo->prepare('DELETE FROM blacklist WHERE id = ?')->execute([$id]);
            flash('Parola rimossa dalla blacklist.');
            break;

        case 'registro_svuota':
            if (($_POST['conferma'] ?? '') !== 'SVUOTA') {
                flash('Scrivi SVUOTA per confermare.', 'ko');
                break;
            }
            $n = $pdo->exec('DELETE FROM registro');
            flash("Registro svuotato: {$n} righe eliminate.");
            break;

        case 'reset':
            if (($_POST['conferma'] ?? '') !== 'RESET') {
                flash('Scrivi RESET per confermare.', 'ko');
                break;
            }
            $n = $pdo->exec('DELETE FROM partecipanti');
            flash("Classifica azzerata: {$n} partecipanti eliminati.");
            break;

        default:
            flash('Azione sconosciuta.', 'ko');
    }
    torna();
}

$csrf = $_SESSION['csrf'] ?? '';

/** Apre la pagina: testa, menu laterale, titolo e messaggio flash. */
function admin_inizio(string $file, string $titolo): void
{
    global $e, $csrf;
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    $scadenza = strtotime((string) (db()->query("SELECT valore FROM impostazioni WHERE chiave = 'scadenza'")->fetchColumn() ?: ''));
    $aperto = evento_aperto();

    admin_head($titolo);
    echo '<body><div class="layout"><nav class="menu"><h1>🛡️ Admin<small>CodeQuestPerlasca</small></h1>';
    foreach (ADMIN_VOCI as $f => [$icona, $nome]) {
        echo '<a href="' . $f . '"' . ($f === $file ? ' class="on"' : '') . '><span>' . $icona . '</span> ' . $nome . '</a>';
    }
    echo '<a href="../classifica.php" target="_blank"><span>🏆</span> Classifica live ↗</a><div class="sp"></div>';
    echo '<div class="live"><span class="stato' . ($aperto ? '' : ' chiuso') . '">' . ($aperto ? 'Evento aperto' : 'Evento chiuso') . '</span>'
       . ($scadenza ? '<br>fino al ' . date('d/m H:i', $scadenza) : '') . '</div>';
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
<script src="../assets/js/avatar.js"></script>
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
