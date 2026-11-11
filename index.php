<?php
/**
 * Dashboard Open Day: onboarding (nickname + avatar) e mappa delle attivita'.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';

avvia_sessione();
if (empty($_SESSION['ospite'])) {
    header('Location: login.php?user=' . urlencode(OSPITE_USER));
    exit;
}

$profilo = null;
if (isset($_SESSION['uuid'])) {
    $st = db()->prepare('SELECT uuid, nickname, avatar, punti_totali FROM partecipanti WHERE uuid = ?');
    $st->execute([$_SESSION['uuid']]);
    $profilo = $st->fetch() ?: null;
    if ($profilo === null) {
        unset($_SESSION['uuid']); // rimosso dall'admin: rifa' l'onboarding
    }
}

$giochi = sessione_attivita()['giochi']; // Open Day corto, laboratorio lungo (admin.php)
$percorso = [
  ['icona' => '🔢', 'titolo' => 'Binary Lights',         'desc' => "Componi numeri con 8 bit in {$giochi['binary_round']} round", 'href' => 'giochi/binary-lights.php'],
  ['icona' => '💡', 'titolo' => 'Porte logiche',         'desc' => 'AND, OR, NOT: accendi la lampadina come ragiona un computer · ' . count($giochi['porte_livelli']) . ' livelli', 'href' => 'giochi/porte-logiche.php'],
  ['icona' => '🛠️', 'titolo' => 'Modifica il gioco',     'desc' => "Cambia gravità, velocità e colori nel codice: {$giochi['palla_missioni']} missioni", 'href' => 'giochi/modifica-gioco.php'],
    ['icona' => '🦆', 'titolo' => 'Duck Coding',           'desc' => "Programma a blocchi: {$giochi['duck_livelli']} livelli per portare le paperelle allo stagno", 'href' => 'giochi/paperelle.php'],
  ['icona' => '🕵️', 'titolo' => 'Hack the Web(site)',    'desc' => 'Usa "Ispeziona" come un hacker per liberare le paperelle · ' . count($giochi['hack_livelli']) . ' livelli', 'href' => 'giochi/hack-website.php'],
];
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<title>Open Day Perlasca · Informatica</title>
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
  .tappe { list-style:none; margin:0; padding:0; display:grid; gap:10px; }
  .tappa { display:flex; align-items:center; gap:14px; padding:16px; border-radius:var(--radius); background:var(--panel);
           border:1px solid var(--line); color:inherit; text-decoration:none; }
  a.tappa:active { transform:scale(.98); }
  .tappa .n { flex:none; width:40px; height:40px; border-radius:50%; display:grid; place-items:center; font-weight:800; background:var(--accent); color:#04121c; }
  .tappa b { display:block; }
  .tappa span.desc { color:var(--muted); font-size:.9rem; }
  .tappa.off { opacity:.5; }
  .tappa.off .n { background:var(--panel-2); color:var(--muted); }
  .tappa .tag { margin-left:auto; font-size:.75rem; color:var(--muted); border:1px solid var(--line); border-radius:999px; padding:2px 8px; white-space:nowrap; }
</style>
</head>
<body>
<main class="app">
  <header>
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
    <div class="card" style="gap:12px">
      <div class="profilo">
        <button class="avatar avatar-btn" type="button" id="dash-avatar" aria-expanded="false" aria-controls="dash-griglia" aria-label="Cambia avatar"></button>
        <div><b id="dash-nick"><?= $e($profilo['nickname'] ?? '') ?></b><p>Junior Hacker in addestramento</p></div>
        <span class="punti"><span id="dash-punti"><?= (int) ($profilo['punti_totali'] ?? 0) ?></span> pt</span>
      </div>
      <div class="avatar-griglia" id="dash-griglia" role="radiogroup" aria-label="Avatar" hidden></div>
      <p class="errore" id="dash-errore" role="alert" hidden></p>
    </div>

    <h2>Scegli la tua sfida</h2>
    <p>Gioca nell'ordine che preferisci: ogni sfida superata ti fa salire in classifica.</p>
    <ul class="tappe">
      <?php foreach ($percorso as $t): ?>
        <li>
          <?php if ($t['href']): ?>
            <a class="tappa" href="<?= $e($t['href']) ?>">
          <?php else: ?>
            <div class="tappa off" aria-disabled="true">
          <?php endif; ?>
              <span class="n"><?= $t['icona'] ?></span>
              <span><b><?= $e($t['titolo']) ?></b><span class="desc"><?= $e($t['desc']) ?></span></span>
              <?php if (!$t['href']): ?><span class="tag">in arrivo</span><?php endif; ?>
          <?= $t['href'] ? '</a>' : '</div>' ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <h2>Il tuo diploma</h2>
    <ol class="tappe">
      <li>
        <a class="tappa" href="diploma.php">
          <span class="n">🎓</span>
          <span><b>Diploma &amp; Classifica</b><span class="desc">Il tuo attestato Junior Hacker</span></span>
        </a>
      </li>
    </ol>
  </section>
</main>

<script src="assets/js/avatar.js"></script>
<script>
'use strict';
const AVATAR = window.AVATAR;
const profiloServer = <?= json_encode($profilo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

const storage = {
  get(k) { try { return localStorage.getItem(k); } catch { return null; } },
  set(k, v) { try { localStorage.setItem(k, v); } catch { /* ignora */ } },
};

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
  if (res.status === 401) { location.href = 'login.php?user=openday'; return null; }
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
    if (p) mostraDashboard(p);
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

if (profiloServer) {
  if (!storage.get('od_uuid')) storage.set('od_uuid', profiloServer.uuid);
  mostraDashboard(profiloServer);
} else {
  $('nickname').value = storage.get('od_nick') || '';
  mostraAvatar();
}
</script>
</body>
</html>
