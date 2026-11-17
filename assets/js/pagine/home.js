'use strict';
const AVATAR = window.AVATAR;
const profiloServer = OD.profilo;

// Stesso telefono in una sessione diversa (prima la demo, poi l'attivita'): serve un profilo nuovo.
// Senza questo, l'identificativo della demo resterebbe e i progressi dei giochi (legati a lui) sembrerebbero gia' fatti.
const SESSIONE_ID = String(OD.sessioneId);
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
