'use strict';
const NICK = OD.nick;
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
