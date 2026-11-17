# 5. Giorno dell'evento: checklist

## Qualche giorno prima

- [ ] Sito pubblicato e verificato (vedi [checklist di deploy](03-deploy-altervista.md#da-verificare-dopo-ogni-pubblicazione))
- [ ] Creata la **sessione vera** in admin: tipo di giornata, data, ora di fine, utente e password
- [ ] Creata (se serve) una **sessione demo** per i tutor, con password diversa
- [ ] Impostata la scadenza del download attestati (o lasciato il default: mezzanotte del giorno dopo)
- [ ] Provato un giro completo da smartphone: login, nickname, un gioco, attestato, QR
- [ ] Stato dei giochi controllato (Attivo / Disabilitato / Nascosto)
- [ ] Cartellone all'ingresso con utente e password della sessione
- [ ] LIM/proiettore: `classifica.php?s=<n>` aperta a schermo intero (il pulsante a schermo intero è nell'intestazione)

## Se manca internet (LAN)

Il sito funziona anche in rete locale senza internet, perché non usa CDN. Serve però un server raggiungibile da tutti i telefoni (con XAMPP: `sudo bash dev/xampp.sh start` stampa l'indirizzo da usare, `http://<IP>/openday/login.php`).

## Durante

- Controlla la **Panoramica** per gli arrivi e i giochi più giocati.
- Nickname inappropriato: **Partecipanti → Nascondi** (resta in lista, sparisce dalla classifica) oppure **Banna**.
- Un ragazzo ha perso il profilo (cambio telefono, cookie cancellati): rifà il login e sceglie un altro nickname; i punti vecchi restano al vecchio profilo.
- Un gioco crea problemi: **Giochi → Disabilitato** lo ferma senza toccare il resto.
- Vuoi chiudere in anticipo: **Sessione → Termina**.

## Attestati

- Il ragazzo apre la pagina Diploma, può scrivere nome e cognome (facoltativo) e scarica il PDF.
- Per passarlo a un altro telefono genera il **QR code**: vale pochi minuti (default 10) ed è uno solo attivo per volta.
- Se il download è chiuso (scadenza passata) compare "Chiedi a un membro dello staff": si può riaprire spostando "Download attestati fino al" nella pagina Sessione.

## Dopo

- [ ] **Esporta il CSV** del registro (Registro → Esporta CSV) e conservalo
- [ ] Lascia la sessione online finché serve per i download degli attestati
- [ ] Non svuotare il registro: serve come archivio. Si svuota solo prima di un nuovo anno, dopo l'export
- [ ] Se vuoi ripartire pulito per un'altra giornata: crea una **nuova sessione** (preferibile) o usa "Azzera classifica"
