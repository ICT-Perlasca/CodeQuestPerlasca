<?php
/**
 * Pannello admin (docenti/tutor): sessione (tipo di giornata e testi dei PDF), moderazione nickname, apertura evento, blacklist.
 * Accesso con la password il cui hash e' in ADMIN_PASS_HASH (inc/config.php).
 * Tutte le azioni sono POST con token CSRF e terminano con redirect (PRG).
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/codegame.php';
require_once __DIR__ . '/inc/diploma.php'; // testi e nome del PDF per l'anteprima della sessione

avvia_sessione();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

$e = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES);

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

function torna(): void
{
    $q = array_intersect_key($_GET, ['q' => 1, 'ord' => 1, 'dal' => 1, 'al' => 1]);
    header('Location: admin.php' . ($q ? '?' . http_build_query($q) : ''));
    exit;
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
    $paginaLogin = true;
}

/* ---------------------------------------------------------------- azioni */
if (!empty($_SESSION['admin']) && $metodo === 'POST') {
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
            header('Location: admin.php');
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

/* ---------------------------------------------------------------- export CSV del registro */
if (!empty($_SESSION['admin']) && ($_GET['export'] ?? '') === 'csv') {
    [$dal, $al] = intervallo_registro(db());
    $st = db()->prepare(
        'SELECT arrivato_il, nickname, nome, punti, diploma_il, numero_attestato FROM registro
          WHERE arrivato_il BETWEEN ? AND ? ORDER BY arrivato_il, id'
    );
    $st->execute(parametri_intervallo($dal, $al));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="registro-openday-' . $dal->format('Y-m-d_Hi') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel riconosce l'UTF-8 (accenti nei nomi)
    // il punto e virgola e' il separatore che Excel in italiano si aspetta
    $riga = static fn(array $campi) => fputcsv($out, array_map(
        static fn($v): string => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : (string) $v, // niente formule
        $campi
    ), ';', '"', '');
    $riga(['Arrivo', 'Nickname', 'Nome e cognome', 'Punti', 'Diploma scaricato', 'N. attestato']);
    while ($r = $st->fetch()) {
        $riga([
            date('d/m/Y H:i', strtotime($r['arrivato_il'])),
            $r['nickname'],
            $r['nome'] ?? '',
            $r['punti'],
            $r['diploma_il'] ? date('d/m/Y H:i', strtotime($r['diploma_il'])) : '',
            $r['numero_attestato'] ?? '',
        ]);
    }
    exit;
}

/* ---------------------------------------------------------------- dati */
if (empty($paginaLogin)) {
    $pdo = db();
    $impostazioni = $pdo->query('SELECT chiave, valore FROM impostazioni')->fetchAll(PDO::FETCH_KEY_PAIR);
    $tScadenza = strtotime($impostazioni['scadenza'] ?? '');
    $scadenzaInput = $tScadenza ? date('Y-m-d\TH:i', $tScadenza) : '';
    $sessione = sessione_attivita();
    $sessioneConfigurata = isset($impostazioni['sessione_data']);
    // la scadenza del login cade in un altro giorno rispetto all'attivita': quasi sempre una svista
    $scadenzaAltroGiorno = $tScadenza && date('Y-m-d', $tScadenza) !== date('Y-m-d', $sessione['data']);

    $stat = $pdo->query('SELECT COUNT(*) AS totale, COALESCE(SUM(nascosto), 0) AS nascosti, COALESCE(SUM(punti_totali > 0), 0) AS giocanti FROM partecipanti')->fetch();
    // solo i giochi che esistono (GIOCHI in inc/db.php): le attivita' mai fatte o tolte non si vedono
    $codiciGiochi = array_keys(GIOCHI);
    $segnaposto = implode(', ', array_fill(0, count($codiciGiochi), '?'));
    $st = $pdo->prepare(
        "SELECT m.codice, m.titolo, COUNT(p.partecipante_id) AS giocatori, COALESCE(MAX(p.punti), 0) AS record,
                COALESCE(AVG(p.punti), 0) AS media, COALESCE(AVG(p.tentativi), 0) AS tentativi, m.max_punti
           FROM moduli m LEFT JOIN punteggi p ON p.modulo = m.codice
          WHERE m.codice IN ($segnaposto)
          GROUP BY m.codice, m.titolo, m.max_punti, m.percorso_ordine
          ORDER BY m.percorso_ordine IS NULL, m.percorso_ordine, m.titolo"
    );
    $st->execute($codiciGiochi);
    $moduli = $st->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    // schermata di ogni gioco (tranne Duck Coding, che ha i tempi per livello piu' sotto):
    // fin dove arrivano i ragazzi e i migliori. Nessuna tabella nuova: basta `punteggi`.
    $schede = [];
    foreach ($moduli as $codice => $m) {
        if ($codice === 'paperelle_coding') {
            continue;
        }
        $g = GIOCHI[$codice];
        $n = livelli_sessione($codice);
        $tetto = max_punti_sessione($codice, (int) $m['max_punti']);
        $st = $pdo->prepare(
            'SELECT p.punti, p.tentativi, p.updated_at, pa.nickname, pa.avatar, pa.nascosto, r.nome
               FROM punteggi p
               JOIN partecipanti pa ON pa.id = p.partecipante_id
               LEFT JOIN registro r ON r.uuid = pa.uuid
              WHERE p.modulo = ?
              ORDER BY p.punti DESC, p.updated_at ASC'
        );
        $st->execute([$codice]);
        $righe = $st->fetchAll();

        // Binary Lights: i punti dipendono anche dalla velocita' -> fasce di punteggio.
        // Gli altri: 100 punti per livello superato, e i livelli si sbloccano in ordine -> "fin dove arrivano".
        $barre = [];
        if ($codice === 'binary_lights') {
            $fasce = 5;
            $passo = (int) max(1, ceil($tetto / $fasce));
            for ($i = 0; $i < $fasce; $i++) {
                $da = $i * $passo;
                $a = $i === $fasce - 1 ? PHP_INT_MAX : ($i + 1) * $passo - 1;
                $barre[] = [
                    'etichetta' => $i === $fasce - 1 ? "da {$da}" : "{$da}–{$a}",
                    'conta'     => count(array_filter($righe, static fn(array $r): bool => (int) $r['punti'] >= $da && (int) $r['punti'] <= $a)),
                ];
            }
        } else {
            for ($k = 1; $k <= $n; $k++) {
                $barre[] = [
                    'etichetta' => "{$k}° " . rtrim($g['unita'], 'i') . ($g['unita'] === 'missioni' ? 'e' : 'o'),
                    'conta'     => count(array_filter($righe, static fn(array $r): bool => (int) $r['punti'] >= $k * $g['per'])),
                ];
            }
        }
        $schede[$codice] = [
            'titolo'  => $m['titolo'],
            'icona'   => $g['icona'],
            'unita'   => $g['unita'],
            'n'       => $n,
            'tetto'   => $tetto,
            'righe'   => $righe,
            'barre'   => $barre,
            'max'     => max([1, ...array_column($barre, 'conta')]),
            'finito'  => count(array_filter($righe, static fn(array $r): bool => (int) $r['punti'] >= $tetto)),
        ];
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    $ord = ($_GET['ord'] ?? '') === 'punti' ? 'punti' : 'recenti';
    $sql = 'SELECT p.id, p.nickname, p.avatar, p.punti_totali, p.nascosto, p.created_at, r.nome
              FROM partecipanti p LEFT JOIN registro r ON r.uuid = p.uuid'
         . ($q !== '' ? ' WHERE p.nickname LIKE ? OR r.nome LIKE ?' : '')
         . ($ord === 'punti' ? ' ORDER BY p.punti_totali DESC, p.updated_at ASC' : ' ORDER BY p.created_at DESC, p.id DESC')
         . ' LIMIT 200';
    $st = $pdo->prepare($sql);
    $cerca = '%' . addcslashes($q, '%_\\') . '%';
    $st->execute($q !== '' ? [$cerca, $cerca] : []);
    $partecipanti = $st->fetchAll();

    // registro permanente: quanti sono arrivati nell'intervallo scelto (un turno) e per fascia oraria
    [$dal, $al] = intervallo_registro($pdo);
    $intervallo = parametri_intervallo($dal, $al);
    $st = $pdo->prepare(
        'SELECT COUNT(*) AS totale, COALESCE(SUM(nome IS NOT NULL), 0) AS con_nome, COALESCE(SUM(diploma_il IS NOT NULL), 0) AS diplomi
           FROM registro WHERE arrivato_il BETWEEN ? AND ?'
    );
    $st->execute($intervallo);
    $turno = $st->fetch();
    $st = $pdo->prepare(
        "SELECT DATE_FORMAT(arrivato_il, '%Y-%m-%d %H:00') AS fascia, COUNT(*) AS arrivi,
                SUM(nome IS NOT NULL) AS con_nome, SUM(diploma_il IS NOT NULL) AS diplomi
           FROM registro WHERE arrivato_il BETWEEN ? AND ?
          GROUP BY fascia ORDER BY fascia"
    );
    $st->execute($intervallo);
    $fasce = $st->fetchAll();
    $maxArrivi = max([1, ...array_map(static fn(array $f): int => (int) $f['arrivi'], $fasce)]);
    $st = $pdo->prepare(
        'SELECT arrivato_il, nickname, nome, punti, diploma_il, numero_attestato FROM registro
          WHERE arrivato_il BETWEEN ? AND ? ORDER BY arrivato_il, id LIMIT 1000'
    );
    $st->execute($intervallo);
    $registro = $st->fetchAll();
    $piuGiorni = $dal->format('Y-m-d') !== $al->format('Y-m-d');

    // Duck Coding: tempi per livello e i piu' veloci (la tabella puo' mancare sui DB vecchi)
    $codegame = null;
    try {
        $perLivello = $pdo->query(
            'SELECT livello, COUNT(*) AS aperti, COUNT(ms) AS risolti, AVG(ms) AS media, MIN(ms) AS migliore,
                    AVG(esecuzioni) AS esecuzioni, AVG(punti) AS punti
               FROM codegame_tempi GROUP BY livello'
        )->fetchAll(PDO::FETCH_UNIQUE);
        $codegame = ['livelli' => [], 'top' => []];
        foreach (codegame_livelli() as $n => $l) {
            [$mediaPunti] = codegame_media_ms($pdo, $n);
            $codegame['livelli'][$n] = ['titolo' => $l['titolo'], 'media_punti' => $mediaPunti] + ($perLivello[$n] ?? []);
        }
        $codegame['top'] = $pdo->query(
            'SELECT p.nickname, r.nome, COUNT(t.ms) AS risolti, SUM(t.ms) AS tempo, SUM(t.punti) AS punti
               FROM codegame_tempi t
               JOIN partecipanti p ON p.id = t.partecipante_id
               LEFT JOIN registro r ON r.uuid = p.uuid
              WHERE t.ms IS NOT NULL
              GROUP BY p.id, p.nickname, r.nome
              ORDER BY risolti DESC, punti DESC, tempo ASC
              LIMIT 15'
        )->fetchAll();
    } catch (PDOException $ex) {
        error_log('[openday] codegame_tempi: ' . $ex->getMessage());
    }

    $blacklist = $pdo->query('SELECT id, parola FROM blacklist ORDER BY parola')->fetchAll();
    $aperto = evento_aperto();
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$csrf = $_SESSION['csrf'] ?? '';
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Admin · CodeQuestPerlasca</title>
<style>
  :root { --bg:#0b1020; --panel:#141b33; --panel-2:#1c2547; --line:#2a3566; --text:#e8ecff; --muted:#8e98c7;
          --on:#ffd84d; --ok:#3ddc97; --ko:#ff5c7a; --accent:#5cc8ff; --mono: ui-monospace, Menlo, Consolas, monospace; }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--text); font:15px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  .app { max-width:980px; margin:0 auto; padding:16px; display:grid; gap:16px; }
  header { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
  header h1 { margin:0; font-size:1.3rem; margin-right:auto; }
  h2 { margin:0 0 12px; font-size:1.05rem; }
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
</style>
</head>
<body>
<?php if (!empty($paginaLogin)): ?>
  <form class="card login" method="post">
    <h1 style="margin:0; font-size:1.3rem">Admin Open Day</h1>
    <?php if ($errore !== ''): ?><p class="flash ko" role="alert"><?= $e($errore) ?></p><?php endif; ?>
    <input type="hidden" name="azione" value="login">
    <input type="password" name="password" placeholder="Password admin" autofocus required>
    <button class="primario" type="submit">Entra</button>
  </form>
<?php else: ?>
<div class="app">
  <header>
    <h1>🛠️ Admin Open Day</h1>
    <a class="btn" href="classifica.php" target="_blank">Classifica live ↗</a>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <button type="submit" name="azione" value="logout">Esci</button>
    </form>
  </header>

  <?php if ($flash): ?><p class="flash <?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>

  <section class="card" id="sessione">
    <h2>Sessione <span class="muted">· da impostare prima di aprire le porte</span></h2>
    <form method="post" class="campi">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <input type="hidden" name="azione" value="sessione">
      <label>Tipo di giornata
        <select name="tipo" required>
          <?php foreach (TIPI_GIORNATA as $codice => $tg): ?>
            <option value="<?= $e($codice) ?>" <?= $sessione['tipo'] === $codice ? 'selected' : '' ?>><?= $e($tg['etichetta']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Giorno dell'attività
        <input type="date" name="data" value="<?= date('Y-m-d', $sessione['data']) ?>" required>
      </label>
      <label>Fine (chiude il login) <span class="muted">facoltativo</span>
        <input type="time" name="fine" value="">
      </label>
      <label class="largo">Titolo dell'attività <span class="muted">facoltativo, es. "Robotica con Arduino"</span>
        <input type="text" name="titolo" maxlength="60" value="<?= $e($sessione['titolo']) ?>">
      </label>
      <label class="largo">Descrizione sull'attestato <span class="muted">facoltativo: continua la frase dopo la virgola (max 200)</span>
        <textarea name="descrizione" maxlength="200" rows="2" placeholder="<?= $e($sessione['attivita']) ?>"><?= $e($sessione['descrizione']) ?></textarea>
      </label>
      <p class="largo muted" style="margin:0">Durata dei giochi:
        <?php foreach (TIPI_GIORNATA as $codice => $tg): ?>
          <b><?= $e($tg['etichetta']) ?></b> <?= $e($tg['giochi']['durata']) ?> (Duck Coding <?= (int) $tg['giochi']['duck_livelli'] ?> livelli, Binary Lights <?= (int) $tg['giochi']['binary_round'] ?> round, Modifica il gioco <?= (int) $tg['giochi']['palla_missioni'] ?> missioni, Porte logiche <?= count($tg['giochi']['porte_livelli']) ?> livelli, Hack the Web(site) <?= count($tg['giochi']['hack_livelli']) ?> livelli)<?= $codice === array_key_last(TIPI_GIORNATA) ? '.' : ' ·' ?>
        <?php endforeach; ?>
      </p>
      <div class="largo riga-form"><button class="primario" type="submit">Salva sessione</button></div>
    </form>

    <?php if (!$sessioneConfigurata): ?>
      <p class="flash ko" style="margin:12px 0 0">Sessione non ancora salvata: i PDF usano "Open Day" e la data della scadenza del login.</p>
    <?php elseif ($scadenzaAltroGiorno): ?>
      <p class="flash ko" style="margin:12px 0 0">Attenzione: la scadenza del login (<?= date('d/m/Y H:i', $tScadenza) ?>) non è nel giorno dell'attività.
        Indica l'ora di fine qui sopra o correggila nel riquadro Evento.</p>
    <?php endif; ?>

    <h3>Così apparirà sull'attestato</h3>
    <div class="anteprima-testo">
      <b><?= $e(mb_strtoupper($sessione['etichetta'] . ' · ' . data_italiana($sessione['data']))) ?></b>
      <p>Si attesta che <i>Nome Cognome</i> <?= $e(testo_partecipazione()) ?></p>
      <p class="muted">PDF scaricato: <code><?= $e(nome_file_diploma(['nickname' => 'Nickname'])) ?></code></p>
    </div>
  </section>

  <div class="griglia">
    <section class="card">
      <h2>Evento <span class="stato <?= $aperto ? '' : 'chiuso' ?>"><?= $aperto ? 'aperto' : 'chiuso' ?></span></h2>
      <form method="post" class="riga-form">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="azione" value="evento">
        <label class="riga-form"><input type="checkbox" name="attivo" <?= ($impostazioni['evento_attivo'] ?? '0') === '1' ? 'checked' : '' ?>> Login attivo</label>
        <label class="riga-form">Scade il <input type="datetime-local" name="scadenza" value="<?= $e($scadenzaInput) ?>" required></label>
        <button class="primario" type="submit">Salva</button>
      </form>
      <p class="muted" style="margin:10px 0 0">Dopo la scadenza (o con "Login attivo" spento) nessuno può entrare né salvare punti. La classifica resta visibile.</p>
    </section>

    <section class="card">
      <h2>Statistiche</h2>
      <div class="numeri">
        <div><b><?= (int) $stat['totale'] ?></b><span>partecipanti</span></div>
        <div><b><?= (int) $stat['giocanti'] ?></b><span>con punti</span></div>
        <div><b><?= (int) $stat['nascosti'] ?></b><span>nascosti</span></div>
      </div>
      <table>
        <thead><tr><th>Attività</th><th style="text-align:right">Giocatori</th><th style="text-align:right">Record</th></tr></thead>
        <tbody>
        <?php foreach ($moduli as $codice => $m): ?>
          <tr><td><?= GIOCHI[$codice]['icona'] ?> <a href="#gioco-<?= $e($codice) ?>"><?= $e($m['titolo']) ?></a></td><td class="num"><?= (int) $m['giocatori'] ?></td><td class="num"><?= (int) $m['record'] ?><span class="muted">/<?= max_punti_sessione($codice, (int) $m['max_punti']) ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </div>

  <section class="card" id="gioco-paperelle_coding">
    <h2>🦆 Duck Coding <span class="muted">(tempi per livello)</span></h2>
    <?php if ($codegame === null): ?>
      <p class="muted">Tabella <code>codegame_tempi</code> mancante: creala con lo SQL in <code>schema.sql</code>.</p>
    <?php else: ?>
      <?php $sec = static fn($ms): string => $ms === null ? '–' : number_format($ms / 1000, 1, ',', '') . ' s'; ?>
      <div style="overflow-x:auto">
      <table>
        <thead><tr><th>Livello</th><th style="text-align:right">Aperto</th><th style="text-align:right">Risolto</th>
          <th style="text-align:right">Tempo medio</th><th style="text-align:right">Migliore</th>
          <th style="text-align:right" title="Media con 3 tempi di riferimento aggiunti: e' quella che decide i punti">Media punti</th>
          <th style="text-align:right">Tentativi</th><th style="text-align:right">Punti medi</th></tr></thead>
        <tbody>
        <?php foreach ($codegame['livelli'] as $n => $l): ?>
          <tr>
            <td><?= $n ?>. <?= $e($l['titolo']) ?></td>
            <td class="num"><?= (int) ($l['aperti'] ?? 0) ?></td>
            <td class="num"><?= (int) ($l['risolti'] ?? 0) ?></td>
            <td class="num"><?= $sec(isset($l['media']) ? (float) $l['media'] : null) ?></td>
            <td class="num"><?= $sec(isset($l['migliore']) ? (int) $l['migliore'] : null) ?></td>
            <td class="num"><?= $sec($l['media_punti']) ?></td>
            <td class="num"><?= isset($l['esecuzioni']) ? number_format((float) $l['esecuzioni'], 1, ',', '') : '–' ?></td>
            <td class="num"><?= isset($l['punti']) ? (int) round((float) $l['punti']) : '–' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <h3>I più bravi</h3>
      <?php if (!$codegame['top']): ?>
        <p class="muted">Nessun livello risolto finora.</p>
      <?php else: ?>
        <table>
          <thead><tr><th>Nickname</th><th style="text-align:right">Livelli</th><th style="text-align:right">Tempo totale</th><th style="text-align:right">Punti</th></tr></thead>
          <tbody>
          <?php foreach ($codegame['top'] as $t): ?>
            <tr>
              <td><?= $e($t['nickname']) ?><?php if ($t['nome']): ?> <span class="muted">· <?= $e($t['nome']) ?></span><?php endif; ?></td>
              <td class="num"><?= (int) $t['risolti'] ?>/<?= count($codegame['livelli']) ?></td>
              <td class="num"><?= $sec((int) $t['tempo']) ?></td>
              <td class="num"><?= (int) $t['punti'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <p class="muted" style="margin:10px 0 0">Il tempo parte alla prima apertura del livello e si ferma alla prima soluzione.
        Punti = 100 × media ÷ tempo (da 30 a 130) + 20 se usano i blocchi minimi. "Tentativi" = quante volte hanno premuto Esegui.</p>
    <?php endif; ?>
  </section>

  <div class="griglia">
  <?php foreach ($schede as $codice => $s): ?>
    <section class="card gioco" id="gioco-<?= $e($codice) ?>">
      <h2><?= $s['icona'] ?> <?= $e($s['titolo']) ?> <span class="muted">· <?= (int) $s['n'] ?> <?= $e($s['unita']) ?> in questa sessione</span></h2>
      <div class="numeri">
        <div><b><?= count($s['righe']) ?></b><span>giocatori</span></div>
        <div><b><?= $s['righe'] ? (int) round(array_sum(array_column($s['righe'], 'punti')) / count($s['righe'])) : 0 ?></b><span>punti medi</span></div>
        <div><b><?= (int) $s['finito'] ?></b><span><?= $codice === 'binary_lights' ? 'punteggio pieno' : 'tutto finito' ?></span></div>
      </div>
      <h3><?= $codice === 'binary_lights' ? 'Quanti punti fanno' : 'Fin dove arrivano' ?></h3>
      <?php if (!$s['righe']): ?>
        <p class="muted">Nessuno ha ancora giocato.</p>
      <?php else: ?>
        <table>
          <tbody>
          <?php foreach ($s['barre'] as $b): ?>
            <tr>
              <td style="white-space:nowrap"><?= $e($b['etichetta']) ?></td>
              <td class="barra-cella"><div class="barra" style="width:<?= round($b['conta'] / $s['max'] * 100) ?>%"></div></td>
              <td class="num"><?= (int) $b['conta'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <h3>I migliori</h3>
        <table>
          <thead><tr><th>Nickname</th><th style="text-align:right"><?= $codice === 'binary_lights' ? 'Partite' : 'Salvataggi' ?></th><th style="text-align:right">Punti</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($s['righe'], 0, 8) as $r): ?>
            <tr<?= $r['nascosto'] ? ' class="muted" title="Nascosto dalla classifica"' : '' ?>>
              <td><span data-avatar="<?= $e($r['avatar']) ?>"></span> <?= $e($r['nickname']) ?><?php if ($r['nome']): ?> <span class="muted">· <?= $e($r['nome']) ?></span><?php endif; ?></td>
              <td class="num"><?= (int) $r['tentativi'] ?></td>
              <td class="num"><?= (int) $r['punti'] ?><span class="muted">/<?= (int) $s['tetto'] ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <p class="muted" style="margin:10px 0 0"><a href="<?= $e(GIOCHI[$codice]['pagina']) ?>" target="_blank">Apri il gioco ↗</a></p>
    </section>
  <?php endforeach; ?>
  </div>

  <section class="card">
    <h2>Partecipanti</h2>
    <form method="get" class="riga-form" style="margin-bottom:12px">
      <input type="search" name="q" value="<?= $e($q) ?>" placeholder="Cerca nickname o nome" style="flex:1">
      <input type="hidden" name="ord" value="<?= $e($ord) ?>">
      <button type="submit">Cerca</button>
      <a class="btn" href="?<?= $e(http_build_query(['q' => $q, 'ord' => $ord === 'punti' ? 'recenti' : 'punti'])) ?>">
        Ordina per <?= $ord === 'punti' ? 'più recenti' : 'punti' ?>
      </a>
      <a class="btn" href="?<?= $e(http_build_query(['q' => $q, 'ord' => $ord])) ?>">↻ Aggiorna</a>
    </form>

    <?php if (!$partecipanti): ?>
      <p class="muted">Nessun partecipante<?= $q !== '' ? ' trovato' : ' ancora' ?>.</p>
    <?php endif; ?>
    <ul class="lista">
      <?php foreach ($partecipanti as $p): ?>
        <li class="partecipante <?= $p['nascosto'] ? 'nascosto' : '' ?>">
          <span class="av" data-avatar="<?= $e($p['avatar']) ?>"></span>
          <div class="info">
            <b><?= $e($p['nickname']) ?><?php if ($p['nome'] !== null): ?> <span class="nome">· <?= $e($p['nome']) ?></span><?php endif; ?><?= $p['nascosto'] ? '<span class="tag">nascosto</span>' : '' ?></b>
            <span>entrato alle <?= $e(date('H:i', strtotime($p['created_at']))) ?></span>
          </div>
          <span class="pt"><?= (int) $p['punti_totali'] ?></span>
          <div class="azioni">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="nascosto" value="<?= $p['nascosto'] ? '0' : '1' ?>">
              <button type="submit" name="azione" value="visibilita"><?= $p['nascosto'] ? 'Mostra' : 'Nascondi' ?></button>
            </form>
            <form method="post" data-conferma="Eliminare «<?= $e($p['nickname']) ?>»?">
              <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="pericolo" type="submit" name="azione" value="elimina" title="Elimina il partecipante e i suoi punti">Elimina</button>
              <button class="pericolo pieno" type="submit" name="azione" value="banna" title="Elimina e aggiunge il nickname alla blacklist">Banna</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>


  <section class="card" id="registro">
    <h2>Registro partecipanti <span class="muted">(permanente)</span></h2>
    <form method="get" action="#registro" class="riga-form">
      <input type="hidden" name="q" value="<?= $e($q) ?>">
      <input type="hidden" name="ord" value="<?= $e($ord) ?>">
      <label class="riga-form">Dal <input type="datetime-local" name="dal" value="<?= $dal->format('Y-m-d\TH:i') ?>" required></label>
      <label class="riga-form">al <input type="datetime-local" name="al" value="<?= $al->format('Y-m-d\TH:i') ?>" required></label>
      <button class="primario" type="submit">Mostra turno</button>
      <a class="btn" href="?<?= $e(http_build_query(['export' => 'csv', 'dal' => $dal->format('Y-m-d\TH:i'), 'al' => $al->format('Y-m-d\TH:i')])) ?>">⬇️ Esporta CSV</a>
    </form>

    <div class="numeri" style="margin:12px 0 0">
      <div><b><?= (int) $turno['totale'] ?></b><span>arrivati nell'intervallo</span></div>
      <div><b><?= (int) $turno['con_nome'] ?></b><span>con nome e cognome</span></div>
      <div><b><?= (int) $turno['diplomi'] ?></b><span>diplomi scaricati</span></div>
    </div>

    <?php if ($fasce): ?>
      <h3>Arrivi per fascia oraria <span class="muted">· tocca una fascia per vederla da sola</span></h3>
      <div class="scorri">
        <table>
          <thead><tr><th>Fascia</th><th></th><th style="text-align:right">Arrivi</th><th style="text-align:right">Con nome</th><th style="text-align:right">Diplomi</th></tr></thead>
          <tbody>
          <?php foreach ($fasce as $f):
              $inizio = new DateTime($f['fascia']); ?>
            <tr>
              <td><a href="?<?= $e(http_build_query(['q' => $q, 'ord' => $ord, 'dal' => $inizio->format('Y-m-d\TH:00'), 'al' => $inizio->format('Y-m-d\TH:59')])) ?>#registro">
                <?= $piuGiorni ? $inizio->format('d/m ') : '' ?><?= $inizio->format('H:00') ?>–<?= $inizio->format('H:59') ?></a></td>
              <td class="barra-cella"><div class="barra" style="width:<?= round(100 * $f['arrivi'] / $maxArrivi) ?>%"></div></td>
              <td class="num"><?= (int) $f['arrivi'] ?></td>
              <td class="num"><?= (int) $f['con_nome'] ?></td>
              <td class="num"><?= (int) $f['diplomi'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <details>
        <summary>Elenco nominativo (<?= count($registro) ?><?= count($registro) === 1000 ? ', primi 1000: usa il CSV' : '' ?>)</summary>
        <div class="scorri">
          <table style="margin-top:8px">
            <thead><tr><th>Arrivo</th><th>Nickname</th><th>Nome e cognome</th><th style="text-align:right">Punti</th><th>Diploma</th><th style="text-align:right">N. attestato</th></tr></thead>
            <tbody>
            <?php foreach ($registro as $r): ?>
              <tr>
                <td><?= date($piuGiorni ? 'd/m H:i' : 'H:i', strtotime($r['arrivato_il'])) ?></td>
                <td><?= $e($r['nickname']) ?></td>
                <td><?= $r['nome'] !== null ? $e($r['nome']) : '<span class="muted">—</span>' ?></td>
                <td class="num"><?= (int) $r['punti'] ?></td>
                <td><?= $r['diploma_il'] ? date('H:i', strtotime($r['diploma_il'])) : '<span class="muted">—</span>' ?></td>
                <td class="num"><?= $r['numero_attestato'] !== null ? (int) $r['numero_attestato'] : '<span class="muted">—</span>' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>
    <?php else: ?>
      <p class="muted" style="margin:12px 0 0">Nessun arrivo in questo intervallo.</p>
    <?php endif; ?>
    <p class="muted" style="margin:12px 0 0">Si conta un partecipante per telefono: chi gioca in gruppo su un solo telefono vale uno.
      Il nome compare quando il ragazzo lo scrive per il diploma. Il registro non viene toccato da "Azzera classifica" né dall'eliminazione dei nickname.</p>
  </section>

  <div class="griglia">
    <section class="card">
      <h2>Blacklist nickname <span class="muted">(<?= count($blacklist) ?>)</span></h2>
      <form method="post" class="riga-form">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="azione" value="blacklist_aggiungi">
        <input type="text" name="parola" placeholder="Nuova parola" maxlength="40" required style="flex:1">
        <button type="submit">Aggiungi</button>
      </form>
      <p class="muted" style="margin:8px 0 0">Blocca ogni nickname che <em>contiene</em> la parola (anche scritta in leet, es. "c4zz0").</p>
      <div class="chips">
        <?php foreach ($blacklist as $b): ?>
          <form method="post" class="chip">
            <?= $e($b['parola']) ?>
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="azione" value="blacklist_rimuovi">
            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
            <button type="submit" aria-label="Rimuovi <?= $e($b['parola']) ?>">×</button>
          </form>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card">
      <h2>Azzera classifica</h2>
      <p class="muted" style="margin:0 0 10px">Elimina tutti i partecipanti e i punteggi (es. dopo le prove, prima dell'apertura). Il registro partecipanti resta. Non si può annullare.</p>
      <form method="post" class="riga-form">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="azione" value="reset">
        <input type="text" name="conferma" placeholder="Scrivi RESET" autocomplete="off" required>
        <button class="pericolo pieno" type="submit">Azzera tutto</button>
      </form>
      <h2 style="margin-top:20px">Svuota registro</h2>
      <p class="muted" style="margin:0 0 10px">Cancella l'elenco permanente dei partecipanti, nomi compresi (es. prima di un nuovo anno). Esporta prima il CSV.</p>
      <form method="post" class="riga-form">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="azione" value="registro_svuota">
        <input type="text" name="conferma" placeholder="Scrivi SVUOTA" autocomplete="off" required>
        <button class="pericolo pieno" type="submit">Svuota registro</button>
      </form>
    </section>
  </div>
</div>

<script src="assets/js/avatar.js"></script>
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
<?php endif; ?>
</body>
</html>
