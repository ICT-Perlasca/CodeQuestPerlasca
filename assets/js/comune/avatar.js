// Pool avatar condiviso: chiave salvata nel DB (partecipanti.avatar) -> emoji mostrata.
// Le chiavi devono rispettare /^[a-z0-9-]{1,30}$/ (validazione in api/register.php)
// e non vanno rinominate: i partecipanti gia' registrati le usano.
window.AVATAR_GRUPPI = Object.freeze([
  { nome: 'Tech', voci: {
    robot: '🤖', alieno: '👾', computer: '💻', joystick: '🕹️', gamepad: '🎮', floppy: '💾',
    cd: '💿', smartphone: '📱', antenna: '📡', lampadina: '💡', batteria: '🔋', ingranaggio: '⚙️',
    lucchetto: '🔒', chiave: '🔑', lente: '🔍', fulmine: '⚡',
  } },
  { nome: 'Spazio', voci: {
    razzo: '🚀', satellite: '🛰️', ufo: '🛸', pianeta: '🪐', terra: '🌍', luna: '🌙',
    stella: '🌟', cometa: '☄️',
  } },
  { nome: 'Animali', voci: {
    pinguino: '🐧', volpe: '🦊', polpo: '🐙', gatto: '🐱', gufo: '🦉', lupo: '🐺',
    tigre: '🐯', leone: '🦁', panda: '🐼', koala: '🐨', scimmia: '🐵', rana: '🐸',
    ape: '🐝', squalo: '🦈', tartaruga: '🐢', geco: '🦎',
  } },
  { nome: 'Fantasy & co.', voci: {
    drago: '🐲', unicorno: '🦄', fantasma: '👻', mago: '🧙', teschio: '💀', corona: '👑',
    diamante: '💎', fuoco: '🔥', cervello: '🧠', dna: '🧬', pizza: '🍕', ciambella: '🍩',
  } },
]);

window.AVATAR = Object.freeze(Object.assign({}, ...window.AVATAR_GRUPPI.map((g) => g.voci)));
