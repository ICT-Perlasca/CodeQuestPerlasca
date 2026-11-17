<?php
/**
 * Diploma Junior Hacker: anteprima, download PDF e QR code temporaneo
 * per scaricarlo da un altro telefono (api/diploma_token.php -> diploma_pdf.php?t=...).
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/diploma.php';

avvia_sessione();
if (!ospite_collegato() || empty($_SESSION['uuid'])) {
    header('Location: index.php');
    exit;
}

$st = db()->prepare('SELECT id FROM partecipanti WHERE sessione_id = ? AND uuid = ?');
$st->execute([sessione_id_corrente(), $_SESSION['uuid']]);
$id = $st->fetchColumn();
if ($id === false) {
    header('Location: index.php'); // rimosso dall'admin: rifa' l'onboarding
    exit;
}
$p = dati_diploma((int) $id); // null = nascosto dall'admin

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);
$diplomaAperto = diploma_aperto();
$sessione = sessione_attivita(); // tipo di giornata scelto in admin.php: cambia i testi come nel PDF
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<title>Diploma · <?= $e($sessione['etichetta']) ?> Perlasca</title>
<style>
  :root { --bg:#0b1020; --panel:#141b33; --panel-2:#1c2547; --line:#2a3566; --text:#e8ecff; --muted:#8e98c7;
          --on:#ffd84d; --ok:#3ddc97; --ko:#ff5c7a; --accent:#5cc8ff; --radius:16px; }
  * { box-sizing:border-box; }
  body { margin:0; min-height:100dvh; background:radial-gradient(120% 60% at 50% 0%, #17204a 0%, var(--bg) 60%) fixed, var(--bg);
         color:var(--text); font:16px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; -webkit-tap-highlight-color:transparent; }
  [hidden] { display:none !important; }
  .app { max-width:720px; margin:0 auto; padding:max(16px, env(safe-area-inset-top)) 16px max(20px, env(safe-area-inset-bottom)); display:grid; gap:16px; }
  h1 { margin:0; font-size:1.3rem; } h2 { margin:0; font-size:1.1rem; }
  p { margin:0; color:var(--muted); }
  a { color:var(--accent); }
  .indietro { font-size:.95rem; text-decoration:none; }
  .card { background:var(--panel); border:1px solid var(--line); border-radius:var(--radius); padding:20px; display:grid; gap:14px; }
  label { display:grid; gap:6px; color:var(--muted); font-size:.9rem; }
  input { font:inherit; font-size:1.15rem; padding:12px 14px; border-radius:10px; border:1px solid var(--line); background:#0e1430; color:var(--text); }
  input:focus { outline:2px solid var(--accent); outline-offset:1px; }
  .nota { font-size:.85rem; }
  .btn { font:inherit; font-weight:800; font-size:1.1rem; padding:14px; border:0; border-radius:12px; background:var(--accent); color:#04121c; cursor:pointer; touch-action:manipulation; }
  .btn:disabled { opacity:.6; cursor:default; }
  .btn.secondario { background:var(--panel-2); color:var(--text); border:1px solid var(--line); font-weight:700; }
  .errore { color:var(--ko); font-weight:600; }

  /* Anteprima: stesse proporzioni (A4 orizzontale) e disposizione del PDF, misure in cqw */
  .foglio { container-type:inline-size; aspect-ratio:297 / 210; background:#fff; color:#0b1020; border-radius:6px;
            box-shadow:0 10px 30px rgba(0,0,0,.45); position:relative; overflow:hidden; }
  .foglio .cornice { position:absolute; inset:2.7cqw; border:.4cqw solid #0b1020; }
  .foglio .cornice::after { content:""; position:absolute; inset:.6cqw; border:.14cqw solid #5cc8ff; }
  .foglio .contenuto { position:absolute; inset:5cqw 6cqw; display:flex; flex-direction:column; align-items:center; text-align:center; }
  .foglio .bin { font:1cqw/1 ui-monospace, Menlo, Consolas, monospace; color:#a0c8e1; letter-spacing:.13cqw; }
  .foglio .scuola { margin-top:2.4cqw; font:700 1.55cqw/1 Helvetica, Arial, sans-serif; letter-spacing:.4cqw; }
  .foglio .indirizzo { margin-top:.9cqw; font:1.25cqw/1 Helvetica, Arial, sans-serif; color:#5f688c; }
  .foglio .titolo { margin-top:4cqw; font:700 3.3cqw/1 Helvetica, Arial, sans-serif; letter-spacing:.2cqw; }
  .foglio .data { margin-top:1.4cqw; padding-top:1.4cqw; border-top:.27cqw solid #5cc8ff; font:700 1.3cqw/1 Helvetica, Arial, sans-serif;
                  color:#0a6eaa; letter-spacing:.5cqw; text-transform:uppercase; }
  .foglio .attesta { margin-top:2.9cqw; font:italic 1.8cqw/1 "Times New Roman", Times, serif; color:#5f688c; }
  .foglio .nome { margin-top:1.5cqw; padding:0 3cqw .9cqw; min-width:27cqw; border-bottom:.2cqw solid #c89600;
                  font:700 4cqw/1.1 "Times New Roman", Times, serif; max-width:100%; overflow-wrap:anywhere; }
  .foglio .codice-nick { margin-top:1cqw; font:1.25cqw/1 Helvetica, Arial, sans-serif; color:#5f688c; }
  .foglio .testo { margin-top:2cqw; max-width:72cqw; font:1.6cqw/1.4 "Times New Roman", Times, serif; }
  .foglio .testo.lungo { font-size:1.3cqw; } /* come nel PDF, che riduce il corpo per stare in 3 righe */
  .foglio .box { display:flex; gap:2.7cqw; margin-top:2.6cqw; }
  .foglio .box div { width:18cqw; padding:1.1cqw 0 .9cqw; background:#eef7fd; border:.1cqw solid #5cc8ff; }
  .foglio .box b { display:block; font:700 2cqw/1 Helvetica, Arial, sans-serif; }
  .foglio .box span { display:block; margin-top:.6cqw; font:.85cqw/1 Helvetica, Arial, sans-serif; color:#5f688c; letter-spacing:.17cqw; }
  .foglio .sigillo { position:absolute; left:50%; bottom:5.3cqw; translate:-50% 0; width:8.4cqw; aspect-ratio:1; border-radius:50%;
                     background:#ffd84d; border:.17cqw solid #c89600; box-shadow:inset 0 0 0 .55cqw #ffd84d, inset 0 0 0 .72cqw #c89600;
                     display:grid; place-content:center; font:700 .9cqw/1.25 Helvetica, Arial, sans-serif; letter-spacing:.13cqw; }
  .foglio .motto { position:absolute; right:10.1cqw; bottom:7.2cqw; font:1cqw/1.6 Helvetica, Arial, sans-serif; color:#5f688c; text-align:right; }
  .foglio .motto i { font:italic 1.45cqw/1 "Times New Roman", Times, serif; color:#0b1020; }
  .foglio .paperelle { position:absolute; inset:0; width:100%; height:100%; pointer-events:none; }
  .foglio .medaglia { font-size:.8cqw; line-height:1.1; }
  .foglio .medaglia b { display:block; font-size:2.4cqw; letter-spacing:0; }
  .podio-card { border-color:#c89600; background:linear-gradient(135deg, #2a2410, var(--panel) 60%); }
  .podio-card b { color:var(--on); }
  .foglio .num { position:absolute; left:8cqw; bottom:7.2cqw; font:1cqw/1.6 Helvetica, Arial, sans-serif; color:#5f688c; text-align:left; }

  .qr-area { display:grid; justify-items:center; gap:10px; }
  .qr-box { position:relative; width:min(260px, 100%); aspect-ratio:1; background:#fff; border-radius:14px; padding:10px; }
  .qr-box svg { display:block; width:100%; height:100%; }
  .qr-box.scaduto svg { opacity:.12; }
  .qr-box .timbro { position:absolute; inset:0; display:grid; place-items:center; color:#0b1020; font-weight:800; font-size:1.2rem; }
  .countdown { font-family:ui-monospace, Menlo, Consolas, monospace; font-weight:800; color:var(--on); font-size:1.1rem; }
  .avviso { padding:12px 14px; border-radius:12px; border:1px solid #6b5a1f; background:#2a2410; color:#ffe9a3; font-size:.9rem; }
</style>
</head>
<body>
<main class="app">
  <header style="display:grid; gap:6px">
    <a class="indietro" href="index.php">← Torna al percorso</a>
    <h1>🎓 Il tuo diploma Junior Hacker</h1>
    <?php if ($p): ?>
      <p>Attestato di partecipazione <?= $e($sessione['nel']) ?> del <?= $e($p['data_evento']) ?>.</p>
    <?php endif; ?>
  </header>

<?php if (!$p): ?>
  <section class="card">
    <h2>Diploma non disponibile</h2>
    <p>Chiedi a un membro dello staff.</p>
  </section>
<?php else: ?>
  <div class="foglio" role="img" aria-label="Anteprima del diploma">
    <div class="cornice"></div>
    <div class="contenuto">
      <div class="bin"><?= testo_binario($sessione['binario']) ?></div>
      <div class="scuola">IIS "GIACOMO PERLASCA"</div>
      <div class="indirizzo">Indirizzo Informatica e Telecomunicazioni</div>
      <div class="titolo">ATTESTATO DI PARTECIPAZIONE</div>
      <div class="data"><?= $e($sessione['etichetta']) ?> · <?= $e($p['data_evento']) ?></div>
      <div class="attesta">Si attesta che</div>
      <div class="nome ant-nome"><?= $e($p['nickname']) ?></div>
      <div class="codice-nick ant-nick" hidden>nome in codice «<?= $e($p['nickname']) ?>»</div>
      <div class="testo<?= mb_strlen(testo_partecipazione()) > 260 ? ' lungo' : '' ?>"><?= $e(testo_partecipazione()) ?></div>
      <div class="box">
        <?php foreach (COMPETENZE as $c): ?>
          <div><b><?= $e($c) ?></b><span>COMPETENZA SBLOCCATA</span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="num">Attestato n. <?= $e($p['codice']) ?><br>Rilasciato il <?= date('d/m/Y') ?></div>
    <?= paperelle_svg() ?>
    <div class="sigillo">JUNIOR<br>HACKER</div>
    <div class="motto"><i>Il futuro si programma.</i><br>Ti aspettiamo all'IIS Perlasca!</div>
  </div>

  <?php if ($p['podio'] !== null): [$metallo, $colore, $bordo] = MEDAGLIE[$p['podio']]; ?>
    <div class="card podio-card">
      <h2>🏆 Sei sul podio: <?= $p['podio'] ?>° posto!</h2>
      <p>Insieme all'attestato ricevi il <b>certificato di merito</b>: è la seconda pagina del PDF.
        Vale la classifica di adesso (ore <?= $e($p['ora_classifica']) ?>), quindi scaricalo subito!</p>
    </div>
    <div class="foglio" role="img" aria-label="Anteprima del certificato di merito">
      <div class="cornice"></div>
      <div class="contenuto">
        <div class="bin"><?= testo_binario($sessione['binario']) ?></div>
        <div class="scuola">IIS "GIACOMO PERLASCA"</div>
        <div class="indirizzo">Indirizzo Informatica e Telecomunicazioni</div>
        <div class="titolo">CERTIFICATO DI MERITO</div>
        <div class="data" style="border-top-color:<?= rgb_hex($colore) ?>">Classifica Junior Hacker · <?= $e($p['data_evento']) ?></div>
        <div class="attesta">Assegnato a</div>
        <div class="nome ant-nome"><?= $e($p['nickname']) ?></div>
        <div class="codice-nick ant-nick" hidden>nome in codice «<?= $e($p['nickname']) ?>»</div>
        <div class="testo<?= mb_strlen(testo_merito($p)) > 260 ? ' lungo' : '' ?>"><?= $e(testo_merito($p)) ?></div>
      </div>
      <?= podio_svg($p['podio']) ?>
      <div class="num">Certificato n. <?= $e($p['codice']) ?>-<?= $p['podio'] ?><br>Classifica delle ore <?= $e($p['ora_classifica']) ?></div>
      <div class="sigillo medaglia" style="background:<?= rgb_hex($colore) ?>; border-color:<?= rgb_hex($bordo) ?>;
           box-shadow:inset 0 0 0 .55cqw <?= rgb_hex($colore) ?>, inset 0 0 0 .72cqw <?= rgb_hex($bordo) ?>">
        <b><?= $p['podio'] ?>°</b><?= $metallo ?></div>
      <div class="motto"><i>Il futuro si programma.</i><br>Ti aspettiamo all'IIS Perlasca!</div>
    </div>
  <?php endif; ?>

  <?php if (!$diplomaAperto): ?>
    <section class="card"><h2>Download chiuso</h2><p><?= $e(DIPLOMA_CHIUSO_MSG) ?></p></section>
  <?php else: ?>
  <form class="card" id="form-pdf" method="post" action="diploma_pdf.php" autocomplete="off">
    <h2>Scarica il PDF</h2>
    <label>Nome e cognome da stampare (facoltativo)
      <input id="nome" name="nome" maxlength="60" autocapitalize="words" spellcheck="false" pattern="\p{L}[\p{L}\p{M} '’.\-]{1,59}">
    </label>
    <p class="nota">Se lo lasci vuoto sul diploma compare il tuo nickname. Il nome viene registrato dalla scuola
      nell'elenco dei partecipanti <?= $e($sessione['nel']) ?>, ma non compare mai in classifica.</p>
    <p class="errore" id="errore" role="alert" hidden></p>
    <button class="btn" type="submit"><?= $p['podio'] !== null
      ? '⬇️ Scarica attestato + certificato di merito (PDF)'
      : '⬇️ Scarica il diploma (PDF)' ?></button>
  </form>

  <section class="card">
    <h2>📱 Scaricalo su un altro telefono</h2>
    <p>Inquadra il QR code con la fotocamera di un telefono: il PDF si scarica senza login.
      Il codice vale <?= diploma_token_minuti() ?> minuti.</p>
    <p class="avviso" id="avviso-localhost" hidden>Stai usando <b>localhost</b>: un telefono non può raggiungere questo indirizzo.
      Apri la pagina dall'indirizzo IP del PC (quello che stampa <code>dev/xampp.sh</code>) e genera di nuovo il QR code.</p>
    <div class="qr-area" id="qr-area" hidden>
      <div class="qr-box" id="qr-box"><span class="timbro" id="qr-timbro" hidden></span></div>
      <span class="countdown" id="qr-countdown" aria-live="polite"></span>
      <a id="qr-link" href="#" target="_blank" rel="noopener">Apri il link</a>
    </div>
    <p class="errore" id="qr-errore" role="alert" hidden></p>
    <button class="btn secondario" type="button" id="btn-qr">Genera QR code</button>
  </section>
  <?php endif; ?>
<?php endif; ?>
</main>

<?php if ($p && $diplomaAperto): ?>
<script src="assets/js/vendor/qrcode.min.js"></script>
<script>
'use strict';
const NICK = <?= json_encode($p['nickname'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
const NOME_RE = /^\p{L}[\p{L}\p{M} '’.\-]{1,59}$/u;
const $ = (id) => document.getElementById(id);

const nomePulito = () => $('nome').value.trim().replace(/\s+/g, ' ');

function controllaNome() {
  const nome = nomePulito();
  const ok = nome === '' || NOME_RE.test(nome);
  $('errore').textContent = ok ? '' : 'Nome: 2-60 caratteri, solo lettere, spazi, apostrofi e trattini';
  $('errore').hidden = ok;
  return ok;
}

/* ---------- anteprima ---------- */
$('nome').addEventListener('input', () => {
  const nome = nomePulito();
  document.querySelectorAll('.ant-nome').forEach((el) => { el.textContent = nome || NICK; });
  document.querySelectorAll('.ant-nick').forEach((el) => { el.hidden = nome === ''; });
  if (!$('errore').hidden) controllaNome();
  if (qr.nome !== null && nome !== qr.nome) qrNonValido('Hai cambiato il nome: genera un nuovo QR code');
});

$('form-pdf').addEventListener('submit', (ev) => {
  if (!controllaNome()) ev.preventDefault();
});

/* ---------- QR code ---------- */
const qr = { nome: null, scade: 0, timer: 0 };

if (['localhost', '127.0.0.1', '[::1]'].includes(location.hostname)) $('avviso-localhost').hidden = false;

function qrSvg(testo) {
  const q = qrcode(0, 'M');
  q.addData(testo);
  q.make();
  const n = q.getModuleCount();
  let d = '';
  for (let r = 0; r < n; r++) {
    for (let c = 0; c < n; c++) if (q.isDark(r, c)) d += `M${c} ${r}h1v1h-1z`;
  }
  return `<svg viewBox="-2 -2 ${n + 4} ${n + 4}" shape-rendering="crispEdges" aria-label="QR code per scaricare il diploma">`
       + `<path d="${d}" fill="#0b1020"/></svg>`;
}

function qrNonValido(messaggio) {
  clearInterval(qr.timer);
  qr.nome = null;
  $('qr-box').classList.add('scaduto');
  $('qr-timbro').textContent = messaggio;
  $('qr-timbro').hidden = false;
  $('qr-countdown').textContent = '';
  $('qr-link').hidden = true;
  $('btn-qr').textContent = 'Genera un nuovo QR code';
}

function aggiornaCountdown() {
  const resto = Math.max(0, Math.ceil((qr.scade - Date.now()) / 1000));
  if (resto === 0) { qrNonValido('QR code scaduto'); return; }
  $('qr-countdown').textContent = `Valido ancora ${Math.floor(resto / 60)}:${String(resto % 60).padStart(2, '0')}`;
}

$('btn-qr').addEventListener('click', async () => {
  if (!controllaNome()) return;
  const nome = nomePulito();
  const errore = $('qr-errore');
  errore.hidden = true;
  $('btn-qr').disabled = true;
  try {
    const res = await fetch('api/diploma_token.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nome }),
    });
    const data = await res.json().catch(() => ({}));
    if (res.status === 401) { location.href = 'login.php'; return; }
    if (!res.ok || !data.ok) throw new Error(data.errore || `Errore ${res.status}`);

    const url = new URL('diploma_pdf.php?t=' + data.token, location.href).href;
    clearInterval(qr.timer);
    qr.nome = nome;
    qr.scade = Date.now() + (data.secondi - 2) * 1000; // margine per la latenza di rete
    $('qr-box').querySelector('svg')?.remove();
    $('qr-box').insertAdjacentHTML('afterbegin', qrSvg(url));
    $('qr-box').classList.remove('scaduto');
    $('qr-timbro').hidden = true;
    $('qr-link').href = url;
    $('qr-link').hidden = false;
    $('qr-area').hidden = false;
    $('btn-qr').textContent = 'Rigenera QR code';
    aggiornaCountdown();
    qr.timer = setInterval(aggiornaCountdown, 1000);
  } catch (err) {
    errore.textContent = err.message;
    errore.hidden = false;
  } finally {
    $('btn-qr').disabled = false;
  }
});
</script>
<?php endif; ?>
</body>
</html>
