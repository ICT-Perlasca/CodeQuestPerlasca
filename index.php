<?php
/**
 * Dashboard Open Day: onboarding (nickname + avatar) e mappa delle attivita'.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';

avvia_sessione();
if (!ospite_collegato()) {
    header('Location: login.php');
    exit;
}

$profilo = null;
if (isset($_SESSION['uuid'])) {
    $st = db()->prepare('SELECT id, uuid, nickname, avatar, punti_totali, nascosto, updated_at FROM partecipanti WHERE sessione_id = ? AND uuid = ?');
    $st->execute([sessione_id_corrente(), $_SESSION['uuid']]);
    $profilo = $st->fetch() ?: null;
    if ($profilo === null) {
        unset($_SESSION['uuid']); // rimosso dall'admin: rifa' l'onboarding
    }
}

$giochi = sessione_attivita()['giochi']; // Open Day corto, laboratorio lungo (admin.php)
$percorso = [
  ['codice' => 'paperelle_coding', 'icona' => '🦆', 'colore' => '#ffd84d', 'titolo' => 'Duck Coding',       'desc' => 'Programma a blocchi e porta le paperelle allo stagno', 'href' => 'giochi/paperelle.php'],
  ['codice' => 'binary_lights',    'icona' => '🔢', 'colore' => '#5cc8ff', 'titolo' => 'Binary Lights',     'desc' => 'Componi numeri con 8 bit',                           'href' => 'giochi/binary-lights.php'],
  ['codice' => 'porte_logiche',    'icona' => '💡', 'colore' => '#b48cff', 'titolo' => 'Porte logiche',     'desc' => 'AND, OR, NOT: accendi la lampadina',                 'href' => 'giochi/porte-logiche.php'],
  ['codice' => 'modifica_gioco',   'icona' => '🛠️', 'colore' => '#3ddc97', 'titolo' => 'Modifica il gioco', 'desc' => 'Cambia gravità e colori nel codice',                'href' => 'giochi/modifica-gioco.php'],
  ['codice' => 'hack_website',     'icona' => '🕵️', 'colore' => '#ff5c7a', 'titolo' => 'Hack the Web(site)', 'desc' => 'Usa "Ispeziona" come un hacker per liberare le paperelle', 'href' => 'giochi/hack-website.php'],
];

// progressi del ragazzo e posizione in classifica (solo se ha gia' un profilo)
$fatti = [];
$posizione = null;
if ($profilo) {
    $pdo = db();
    $st = $pdo->prepare('SELECT modulo, punti FROM punteggi WHERE partecipante_id = ?');
    $st->execute([$profilo['id']]);
    $puntiGioco = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    $st = $pdo->prepare('SELECT COUNT(*) FROM codegame_tempi WHERE partecipante_id = ? AND ms IS NOT NULL');
    $st->execute([$profilo['id']]);
    $duckFatti = (int) $st->fetchColumn();

    if ((int) $profilo['punti_totali'] > 0 && !(int) $profilo['nascosto']) {
        $st = $pdo->prepare(
            'SELECT COUNT(*) + 1 FROM partecipanti WHERE sessione_id = ? AND nascosto = 0 AND punti_totali > 0 AND (punti_totali > ?
               OR (punti_totali = ? AND (updated_at < ? OR (updated_at = ? AND id < ?))))'
        );
        $st->execute([sessione_id_corrente(), $profilo['punti_totali'], $profilo['punti_totali'], $profilo['updated_at'], $profilo['updated_at'], $profilo['id']]);
        $posizione = (int) $st->fetchColumn();
    }
}

// filtra i giochi nascosti, segna quelli disabilitati, calcola totale e fatti di ognuno
$percorso = array_values(array_filter($percorso, static fn(array $t): bool => stato_gioco($t['codice']) !== 'nascosto'));
$completate = 0;
foreach ($percorso as &$t) {
    $t['totale'] = livelli_sessione($t['codice']);
    $t['unita'] = ['binary_lights' => 'round', 'modifica_gioco' => 'missioni'][$t['codice']] ?? 'livelli';
    $t['chiuso'] = stato_gioco($t['codice']) === 'disabilitato';
    $t['fatti'] = match ($t['codice']) {
        'paperelle_coding' => min($t['totale'], $duckFatti ?? 0),
        'binary_lights'    => ($puntiGioco[$t['codice']] ?? 0) > 0 ? $t['totale'] : 0, // i punti dei round non sono tutti da 300: conta "giocato"
        default            => min($t['totale'], intdiv((int) ($puntiGioco[$t['codice']] ?? 0), GIOCHI[$t['codice']]['per'])),
    };
    $t['completo'] = $t['totale'] > 0 && $t['fatti'] >= $t['totale'];
    $t['iniziato'] = $t['fatti'] > 0;
    $completate += $t['completo'] ? 1 : 0;
}
unset($t);
$attive = count(array_filter($percorso, static fn(array $t): bool => !$t['chiuso']));
$percento = $attive > 0 ? (int) round(100 * $completate / $attive) : 0;
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<title>CodeQuestPerlasca · Informatica</title>
<style>
  :root { --bg:#0b1020; --panel:#141b33; --panel-2:#1c2547; --line:#2a3566; --text:#e8ecff; --muted:#8e98c7;
          --on:#ffd84d; --ok:#3ddc97; --ko:#ff5c7a; --accent:#5cc8ff; --radius:16px; }
  * { box-sizing:border-box; }
  body { margin:0; min-height:100dvh; background:radial-gradient(120% 60% at 50% 0%, #17204a 0%, var(--bg) 60%) fixed, var(--bg);
         color:var(--text); font:16px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; -webkit-tap-highlight-color:transparent; }
  [hidden] { display:none !important; }
  .app { max-width:640px; margin:0 auto; padding:max(16px, env(safe-area-inset-top)) 16px max(20px, env(safe-area-inset-bottom)); display:grid; gap:16px; }
  h1 { margin:0; font-size:1.3rem; } h2 { margin:0; font-size:1.1rem; }
  p { margin:0; color:var(--muted); }
  .card { background:var(--panel); border:1px solid var(--line); border-radius:var(--radius); padding:20px; display:grid; gap:14px; }
  label { display:grid; gap:6px; color:var(--muted); font-size:.9rem; }
  input { font:inherit; font-size:1.15rem; padding:12px 14px; border-radius:10px; border:1px solid var(--line); background:#0e1430; color:var(--text); }
  input:focus { outline:2px solid var(--accent); outline-offset:1px; }
  .btn { font:inherit; font-weight:800; font-size:1.1rem; padding:14px; border:0; border-radius:12px; background:var(--accent); color:#04121c; cursor:pointer; touch-action:manipulation; }
  .btn.secondario { background:var(--panel-2); color:var(--text); border:1px solid var(--line); font-weight:600; font-size:.95rem; padding:10px 14px; }
  .avatar-row { display:flex; align-items:center; gap:14px; }
  .avatar-row .testo { flex:1; min-width:0; }
  .avatar-row .testo b { display:block; }
  .avatar-row .testo p { font-size:.9rem; }
  .avatar { font-size:2.6rem; width:64px; height:64px; display:grid; place-items:center; background:var(--panel-2); border-radius:16px; border:1px solid var(--line); }
  .avatar-btn { flex:none; position:relative; font-family:inherit; color:inherit; padding:0; cursor:pointer; touch-action:manipulation; transition:transform .1s, border-color .15s; }
  .avatar-btn:active { transform:scale(.94); }
  .avatar-btn:focus-visible, .avatar-griglia button:focus-visible { outline:3px solid var(--accent); outline-offset:2px; }
  .avatar-btn::after { /* badge "modifica" */
    content:"✎"; position:absolute; right:-6px; bottom:-6px; width:24px; height:24px; border-radius:50%;
    display:grid; place-items:center; font-size:.8rem; font-weight:800; background:var(--accent); color:#04121c; }
  .avatar-btn[aria-expanded="true"] { border-color:var(--accent); }
  .avatar-griglia { display:grid; gap:12px; padding:12px; border-radius:14px; background:#0e1430; border:1px solid var(--line);
                    max-height:min(55vh, 440px); overflow-y:auto; overscroll-behavior:contain; }
  .avatar-griglia h3 { margin:0 0 6px; font-size:.75rem; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; }
  .avatar-griglia .gruppo { display:grid; grid-template-columns:repeat(auto-fill, minmax(46px, 1fr)); gap:6px; }
  .avatar-griglia button { aspect-ratio:1; padding:0; font-size:1.65rem; line-height:1; border-radius:12px; border:2px solid transparent;
                           background:var(--panel-2); cursor:pointer; touch-action:manipulation; }
  .avatar-griglia button[aria-checked="true"] { border-color:var(--accent); background:#173049; }
  .avatar-griglia button:active { transform:scale(.92); }
  .errore { color:var(--ko); font-weight:600; min-height:1.4em; }
  .profilo { display:flex; align-items:center; gap:12px; }
  .profilo .avatar { width:52px; height:52px; font-size:2rem; }
  .profilo b { display:block; font-size:1.15rem; }
  .punti { margin-left:auto; font-family:ui-monospace, Menlo, Consolas, monospace; color:var(--on); font-weight:800; font-size:1.2rem; white-space:nowrap; }
  .hero { position:relative; overflow:hidden; border-radius:22px; padding:20px; border:1px solid var(--line); background:linear-gradient(135deg, #1b2a6b, #141b33 60%); display:grid; gap:14px; }
  .hero::before { content:"01001000 01000001 01000011 01001011"; position:absolute; right:-10px; top:6px; width:120px; word-break:break-all; line-height:1.6;
                  font:700 .7rem ui-monospace, monospace; color:#5cc8ff22; pointer-events:none; }
  .kicker { font-size:.72rem; letter-spacing:.12em; text-transform:uppercase; color:var(--accent); font-weight:700; }
  .hero .profilo .avatar { width:68px; height:68px; font-size:2.4rem; border:2px solid var(--accent); border-radius:20px; }
  .hero .profilo b { font-size:1.3rem; }
  .pt { margin-left:auto; text-align:right; position:relative; }
  .pt strong { display:block; font:800 1.9rem ui-monospace, Menlo, Consolas, monospace; color:var(--on); line-height:1; }
  .pt span { font-size:.75rem; color:var(--muted); }
  .bar { height:10px; border-radius:99px; background:var(--bg); overflow:hidden; border:1px solid var(--line); }
  .bar i { display:block; height:100%; background:linear-gradient(90deg, var(--accent), var(--ok)); border-radius:99px; transition:width .4s; }
  .avanzamento p { font-size:.82rem; margin-top:6px; }
  .griglia-giochi { list-style:none; margin:0; padding:0; display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .griglia-giochi li:last-child:nth-child(odd) { grid-column:1 / -1; }
  .gioco { position:relative; display:grid; gap:8px; align-content:start; height:100%; padding:16px; border-radius:var(--radius); background:var(--panel);
           border:1px solid var(--line); color:inherit; text-decoration:none; overflow:hidden; transition:transform .12s, border-color .15s; }
  a.gioco:hover { border-color:var(--c); transform:translateY(-2px); }
  a.gioco:active { transform:scale(.97); }
  .gioco::after { content:""; position:absolute; inset:auto -30% -60% -30%; height:100%; background:radial-gradient(closest-side, var(--c), transparent); opacity:.18; pointer-events:none; }
  .gioco .ico { width:52px; height:52px; border-radius:16px; display:grid; place-items:center; font-size:1.7rem;
                background:color-mix(in srgb, var(--c) 22%, transparent); border:1px solid color-mix(in srgb, var(--c) 55%, transparent); }
  .gioco b { font-size:1rem; }
  .gioco small { color:var(--muted); font-size:.8rem; line-height:1.3; }
  .gioco .stato { display:flex; justify-content:space-between; align-items:center; gap:6px; font-size:.75rem; color:var(--muted); margin-top:4px; position:relative; }
  .chip { border-radius:99px; padding:2px 9px; border:1px solid var(--line); font-weight:700; white-space:nowrap; }
  .chip.ok { color:var(--ok); border-color:#3ddc9755; }
  .chip.corso { color:var(--accent); border-color:#5cc8ff55; }
  .gioco.off { opacity:.45; filter:grayscale(.8); }
  .diploma { display:flex; align-items:center; gap:14px; padding:18px; border-radius:var(--radius); text-decoration:none; color:#1a1300; font-weight:700;
             background:linear-gradient(135deg, #ffd84d, #ffb347); }
  .diploma .i { font-size:2rem; }
  .diploma small { display:block; font-weight:500; opacity:.75; }
  .titolo-sfide { display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .btn-aiuto { font:inherit; font-size:.85rem; font-weight:700; color:var(--accent); background:transparent; border:1px solid var(--line); border-radius:99px; padding:5px 12px; cursor:pointer; touch-action:manipulation; }
  .intro { position:fixed; inset:0; z-index:50; display:grid; place-items:center; padding:16px; background:rgba(5,8,20,.82); }
  .intro-box { width:100%; max-width:460px; max-height:calc(100dvh - 32px); overflow-y:auto; border-radius:22px; border:1px solid var(--line); padding:22px 20px 18px;
               background:linear-gradient(160deg, #1b2a6b, #141b33 55%); display:grid; gap:16px; }
  .slide { display:none; gap:12px; }
  .slide.on { display:grid; }
  .slide .grande { font-size:2.6rem; line-height:1; }
  .slide h2 { font-size:1.35rem; }
  .slide p { color:var(--text); }
  .passi { list-style:none; margin:0; padding:0; display:grid; gap:10px; }
  .passi li { display:flex; gap:12px; align-items:flex-start; }
  .passi .n { flex:none; width:34px; height:34px; border-radius:50%; display:grid; place-items:center; background:var(--panel-2); border:1px solid var(--line); font-size:1.1rem; }
  .passi b { display:block; }
  .passi span.t { color:var(--muted); font-size:.88rem; }
  .intro-nav { display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .puntini { display:flex; gap:7px; }
  .puntini i { width:9px; height:9px; border-radius:50%; background:var(--line); }
  .puntini i.on { background:var(--accent); }
  .intro-nav .btn { padding:11px 18px; font-size:1rem; }
  .intro-salta { background:transparent; border:0; color:var(--muted); font:inherit; font-size:.85rem; cursor:pointer; padding:6px; }
</style>
</head>
<body>
<main class="app">
  <header id="intestazione" <?= $profilo ? 'hidden' : '' ?>>
    <h1><?= $e(sessione_attivita()['etichetta']) ?> · Informatica e Telecomunicazioni</h1>
    <p>IIS "Giacomo Perlasca"</p>
  </header>

  <!-- ONBOARDING -->
  <form id="onboarding" class="card" <?= $profilo ? 'hidden' : '' ?> autocomplete="off">
    <h2>Come ti chiami… in codice?</h2>
    <p>Scegli un nickname. <strong>Non usare il tuo nome e cognome veri</strong>: qui sei un hacker in incognito!</p>
    <div class="avatar-row">
      <button class="avatar avatar-btn" type="button" id="avatar-preview" aria-expanded="false" aria-controls="avatar-griglia" aria-label="Scegli il tuo avatar"></button>
      <div class="testo"><b>Il tuo avatar</b><p>Tocca l'icona per sceglierlo</p></div>
      <button class="btn secondario" type="button" id="btn-avatar" aria-label="Avatar a caso">🎲</button>
    </div>
    <div class="avatar-griglia" id="avatar-griglia" role="radiogroup" aria-label="Avatar" hidden></div>
    <label>Nickname (3-16 caratteri, niente spazi)
      <input id="nickname" maxlength="16" autocapitalize="none" spellcheck="false" required pattern="[\p{L}\p{N}_\-]{3,16}">
    </label>
    <p class="errore" id="errore" role="alert"></p>
    <button class="btn" type="submit" id="btn-entra">Iniziamo!</button>
  </form>

  <!-- DASHBOARD -->
  <section id="dashboard" <?= $profilo ? '' : 'hidden' ?> style="display:grid; gap:16px">
    <div class="hero">
      <div class="kicker"><?= $e(sessione_attivita()['etichetta']) ?> · IIS Perlasca</div>
      <div class="profilo">
        <button class="avatar avatar-btn" type="button" id="dash-avatar" aria-expanded="false" aria-controls="dash-griglia" aria-label="Cambia avatar"></button>
        <div><b id="dash-nick"><?= $e($profilo['nickname'] ?? '') ?></b><p>Junior Hacker in addestramento</p></div>
        <div class="pt"><strong id="dash-punti"><?= (int) ($profilo['punti_totali'] ?? 0) ?></strong><span>punti</span></div>
      </div>
      <div class="avatar-griglia" id="dash-griglia" role="radiogroup" aria-label="Avatar" hidden></div>
      <p class="errore" id="dash-errore" role="alert" hidden></p>
      <?php if ($attive > 0): ?>
        <div class="avanzamento">
          <div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percento ?>"><i style="width:<?= $percento ?>%"></i></div>
          <p><?= $completate ?> sfid<?= $completate === 1 ? 'a' : 'e' ?> su <?= $attive ?> completat<?= $completate === 1 ? 'a' : 'e' ?><?= $posizione ? ' · 🏆 sei ' . $posizione . '° in classifica' : '' ?></p>
        </div>
      <?php endif; ?>
    </div>

    <div class="titolo-sfide"><h2>Scegli la tua sfida</h2><button type="button" class="btn-aiuto" id="btn-intro">❓ Come funziona</button></div>
    <ul class="griglia-giochi">
      <?php foreach ($percorso as $t):
        $tag = $t['chiuso'] ? 'div' : 'a';
        $attr = $t['chiuso'] ? 'aria-disabled="true"' : 'href="' . $e($t['href']) . '"'; ?>
        <li>
          <<?= $tag ?> class="gioco<?= $t['chiuso'] ? ' off' : '' ?>" style="--c:<?= $e($t['colore']) ?>" <?= $attr ?>>
            <span class="ico"><?= $t['icona'] ?></span>
            <b><?= $e($t['titolo']) ?></b>
            <small><?= $e($t['desc']) ?></small>
            <span class="stato">
              <span><?= (int) $t['totale'] ?> <?= $e($t['unita']) ?></span>
              <?php if ($t['chiuso']): ?><span class="chip">chiuso</span>
              <?php elseif ($t['codice'] === 'binary_lights' && $t['iniziato']): ?><span class="chip ok">✓ giocato</span>
              <?php elseif ($t['completo']): ?><span class="chip ok">✓ <?= (int) $t['fatti'] ?>/<?= (int) $t['totale'] ?></span>
              <?php elseif ($t['iniziato'] && $t['codice'] !== 'binary_lights'): ?><span class="chip corso"><?= (int) $t['fatti'] ?>/<?= (int) $t['totale'] ?></span>
              <?php else: ?><span class="chip">da fare</span><?php endif; ?>
            </span>
          </<?= $tag ?>>
        </li>
      <?php endforeach; ?>
    </ul>

    <a class="diploma" href="diploma.php">
      <span class="i">🎓</span>
      <span>Il tuo diploma &amp; la classifica<small>Scarica l'attestato Junior Hacker</small></span>
    </a>
  </section>
  <form method="post" action="logout.php" style="text-align:center;margin:8px 0 24px" onsubmit="return confirm('Vuoi uscire? Per rientrare servono utente e password della sessione.')">
    <button class="btn secondario" type="submit">🚪 Esci</button>
  </form>
</main>

<!-- INTRODUZIONE: due slide che spiegano come funzionano le attivita' -->
<div class="intro" id="intro" role="dialog" aria-modal="true" aria-label="Come funziona" hidden>
  <div class="intro-box">
    <section class="slide on">
      <div class="grande">🕹️</div>
      <h2>Benvenuto, Junior Hacker!</h2>
      <p>Qui trovi delle sfide a tema informatica. Si gioca dal telefono, da solo o con un amico, <b>nell'ordine che preferisci</b>.</p>
      <ul class="passi">
        <li><span class="n">1</span><span><b>Scegli una sfida</b><span class="t">Tocca una card nella pagina iniziale.</span></span></li>
        <li><span class="n">2</span><span><b>Supera i livelli</b><span class="t">Ogni livello superato ti dà dei punti. Se sbagli, riprova: nessuno ti toglie niente.</span></span></li>
        <li><span class="n">3</span><span><b>Sali in classifica</b><span class="t">Compare solo il tuo nickname: il nome vero lo scrivi soltanto per il diploma.</span></span></li>
      </ul>
    </section>
    <section class="slide">
      <div class="grande">🎯</div>
      <h2>Le sfide</h2>
      <ul class="passi">
        <li><span class="n">🦆</span><span><b>Duck Coding</b><span class="t">Trascina i blocchi per far arrivare la paperella allo stagno.</span></span></li>
        <li><span class="n">🔢</span><span><b>Binary Lights</b><span class="t">Accendi gli 8 bit per comporre il numero richiesto.</span></span></li>
        <li><span class="n">💡</span><span><b>Porte logiche</b><span class="t">Collega interruttori e porte per accendere la lampadina.</span></span></li>
        <li><span class="n">🛠️</span><span><b>Modifica il gioco</b><span class="t">Cambia i numeri nel codice e guarda la palla reagire.</span></span></li>
        <li><span class="n">🕵️</span><span><b>Hack the Web(site)</b><span class="t">Usa "Ispeziona" come un hacker per liberare le paperelle.</span></span></li>
      </ul>
      <p>Alla fine scarichi il tuo <b>diploma</b> dalla pagina iniziale. 🎓</p>
    </section>
    <div class="intro-nav">
      <button type="button" class="intro-salta" id="intro-salta">Salta</button>
      <div class="puntini" aria-hidden="true"><i class="on"></i><i></i></div>
      <button type="button" class="btn" id="intro-avanti">Avanti</button>
    </div>
  </div>
</div>

<script src="assets/js/avatar.js"></script>
<script>
'use strict';
const AVATAR = window.AVATAR;
const profiloServer = <?= json_encode($profilo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

const storage = {
  get(k) { try { return localStorage.getItem(k); } catch { return null; } },
  set(k, v) { try { localStorage.setItem(k, v); } catch { /* ignora */ } },
};

// Stesso telefono in una sessione diversa (prima la demo, poi l'attivita'): serve un profilo nuovo.
// Senza questo, l'identificativo della demo resterebbe e i progressi dei giochi (legati a lui) sembrerebbero gia' fatti.
const SESSIONE_ID = '<?= (int) sessione_id_corrente() ?>';
if (storage.get('od_sessione') !== SESSIONE_ID) {
  try { localStorage.removeItem('od_uuid'); } catch { /* ignora */ }
  storage.set('od_sessione', SESSIONE_ID);
}

// UUIDv4 anche su http:// in LAN, dove crypto.randomUUID non esiste
function uuidv4() {
  if (crypto.randomUUID) return crypto.randomUUID();
  const b = crypto.getRandomValues(new Uint8Array(16));
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

const chiavi = Object.keys(AVATAR);
const $ = (id) => document.getElementById(id);
const avatarCasuale = () => chiavi[Math.floor(Math.random() * chiavi.length)];

let avatar = AVATAR[storage.get('od_avatar')] ? storage.get('od_avatar') : avatarCasuale();
let profilo = profiloServer;

/**
 * Collega un riquadro avatar (bottone) a una griglia di scelta raggruppata per categoria.
 * Toccare il riquadro apre/chiude la griglia; scegliere un'icona chiama onScelta e la chiude.
 */
function creaSceltaAvatar(bottone, griglia, attuale, onScelta) {
  for (const gruppo of window.AVATAR_GRUPPI) {
    const sezione = document.createElement('section');
    const titolo = document.createElement('h3');
    titolo.textContent = gruppo.nome;
    const box = document.createElement('div');
    box.className = 'gruppo';
    for (const [chiave, emoji] of Object.entries(gruppo.voci)) {
      const b = document.createElement('button');
      b.type = 'button';
      b.dataset.avatar = chiave;
      b.textContent = emoji;
      b.setAttribute('role', 'radio');
      b.setAttribute('aria-label', chiave);
      box.append(b);
    }
    sezione.append(titolo, box);
    griglia.append(sezione);
  }

  const apri = (aperta) => {
    griglia.hidden = !aperta;
    bottone.setAttribute('aria-expanded', String(aperta));
    if (!aperta) return;
    griglia.querySelectorAll('[data-avatar]').forEach((b) => b.setAttribute('aria-checked', String(b.dataset.avatar === attuale())));
    const scelto = griglia.querySelector('[aria-checked="true"]');
    if (scelto) griglia.scrollTop = scelto.offsetTop - griglia.offsetTop - 40;
  };

  bottone.addEventListener('click', () => apri(griglia.hidden));
  griglia.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-avatar]');
    if (!b) return;
    apri(false);
    onScelta(b.dataset.avatar);
    bottone.focus();
  });
  griglia.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') { apri(false); bottone.focus(); }
  });
}

async function registra(uuid, nickname, avatarScelto) {
  const res = await fetch('api/register.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ uuid, nickname, avatar: avatarScelto }),
  });
  const data = await res.json().catch(() => ({}));
  if (res.status === 401) { location.href = 'login.php'; return null; }
  if (!res.ok || !data.ok) throw new Error(data.errore || `Errore ${res.status}`);
  storage.set('od_uuid', uuid);
  storage.set('od_nick', nickname);
  storage.set('od_avatar', avatarScelto);
  return data.partecipante;
}

const mostraAvatar = () => { $('avatar-preview').textContent = AVATAR[avatar]; };

function mostraDashboard(p) {
  profilo = p;
  $('dash-avatar').textContent = AVATAR[p.avatar] || '🤖';
  $('dash-nick').textContent = p.nickname;
  $('dash-punti').textContent = p.punti_totali;
  $('onboarding').hidden = true;
  $('intestazione').hidden = true;
  $('dashboard').hidden = false;
}

/* ---------- onboarding ---------- */
creaSceltaAvatar($('avatar-preview'), $('avatar-griglia'), () => avatar, (scelto) => {
  avatar = scelto;
  mostraAvatar();
});

$('btn-avatar').addEventListener('click', () => {
  let nuovo;
  do { nuovo = avatarCasuale(); } while (nuovo === avatar);
  avatar = nuovo;
  mostraAvatar();
  $('avatar-griglia').hidden = true;
  $('avatar-preview').setAttribute('aria-expanded', 'false');
});

$('onboarding').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const uuid = storage.get('od_uuid') || uuidv4();
  $('errore').textContent = '';
  $('btn-entra').disabled = true;
  try {
    const p = await registra(uuid, $('nickname').value.trim(), avatar);
    if (p) { mostraDashboard(p); introSeNuovo(); }
  } catch (err) {
    $('errore').textContent = err.message;
  } finally {
    $('btn-entra').disabled = false;
  }
});

/* ---------- cambio avatar dalla dashboard ---------- */
creaSceltaAvatar($('dash-avatar'), $('dash-griglia'), () => profilo && profilo.avatar, async (scelto) => {
  if (!profilo || scelto === profilo.avatar) return;
  const errore = $('dash-errore');
  const precedente = profilo.avatar;
  errore.hidden = true;
  $('dash-avatar').textContent = AVATAR[scelto]; // aggiornamento immediato, annullato in caso di errore
  try {
    const p = await registra(profilo.uuid || storage.get('od_uuid'), profilo.nickname, scelto);
    if (p) mostraDashboard({ ...profilo, ...p });
  } catch (err) {
    $('dash-avatar').textContent = AVATAR[precedente] || '🤖';
    errore.textContent = err.message;
    errore.hidden = false;
  }
});


/* ---------- introduzione a due slide ---------- */
const intro = $('intro');
const slide = intro.querySelectorAll('.slide');
const puntini = intro.querySelectorAll('.puntini i');
let slideAttuale = 0;

function mostraSlide(n) {
  slideAttuale = n;
  slide.forEach((el, i) => el.classList.toggle('on', i === n));
  puntini.forEach((el, i) => el.classList.toggle('on', i === n));
  $('intro-avanti').textContent = n === slide.length - 1 ? 'Inizia!' : 'Avanti';
  $('intro-salta').hidden = n === slide.length - 1;
}
function apriIntro() {
  mostraSlide(0);
  intro.hidden = false;
  $('intro-avanti').focus();
}
function chiudiIntro() {
  intro.hidden = true;
  storage.set('od_intro', '1');
}
$('intro-avanti').addEventListener('click', () => {
  if (slideAttuale < slide.length - 1) mostraSlide(slideAttuale + 1); else chiudiIntro();
});
$('intro-salta').addEventListener('click', chiudiIntro);
$('btn-intro').addEventListener('click', apriIntro);
document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape' && !intro.hidden) chiudiIntro(); });
let tocco = null; // scorrimento col dito tra le due slide
intro.addEventListener('touchstart', (ev) => { tocco = ev.touches[0].clientX; }, { passive: true });
intro.addEventListener('touchend', (ev) => {
  if (tocco === null) return;
  const dx = ev.changedTouches[0].clientX - tocco;
  tocco = null;
  if (dx < -50 && slideAttuale < slide.length - 1) mostraSlide(slideAttuale + 1);
  if (dx > 50 && slideAttuale > 0) mostraSlide(slideAttuale - 1);
});
// la prima volta compare da sola, appena c'e' un profilo
const introSeNuovo = () => { if (!storage.get('od_intro')) apriIntro(); };

if (profiloServer) {
  if (!storage.get('od_uuid')) storage.set('od_uuid', profiloServer.uuid);
  mostraDashboard(profiloServer);
  introSeNuovo();
} else {
  $('nickname').value = storage.get('od_nick') || '';
  mostraAvatar();
}
</script>
</body>
</html>
