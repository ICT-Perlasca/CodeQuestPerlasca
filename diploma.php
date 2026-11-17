<?php
/**
 * Diploma Junior Hacker: anteprima, download PDF e QR code temporaneo
 * per scaricarlo da un altro telefono (api/diploma_token.php -> diploma_pdf.php?t=...).
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/attestato.php';

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
$sessione = sessione_attivita(); // tipo di giornata scelto in admin: cambia i testi come nel PDF
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<title>Diploma · <?= $e($sessione['etichetta']) ?> Perlasca</title>
<link rel="stylesheet" href="<?= asset('css/tema.css', '') ?>">
<link rel="stylesheet" href="<?= asset('css/pagine/diploma.css', '') ?>">
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
<script>const OD = { nick: <?= json_encode($p['nickname'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?> };</script>
<script src="<?= asset('js/pagine/diploma.js', '') ?>"></script>
<?php endif; ?>
</body>
</html>
