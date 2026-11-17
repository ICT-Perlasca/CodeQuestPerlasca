# Documentazione · CodeQuestPerlasca (Open Day Informatica)

Web app PHP/MySQL per l'Open Day e i Laboratori Pomeridiani di Informatica dell'IIS "Giacomo Perlasca". Gli ospiti entrano con utente e password della sessione, scelgono un nickname e giocano ai minigiochi; classifica live, attestato PDF con QR code, registro permanente per lo staff.

## Da dove partire

| Sei... | Leggi |
|---|---|
| Docente / tutor che gestisce la giornata | [Guida all'uso del pannello admin](04-guida-admin.md) e [Giorno dell'evento](05-giorno-evento.md) |
| Chi deve installare o pubblicare il sito | [Ambiente locale](02-ambiente-locale.md) e [Deploy su Altervista](03-deploy-altervista.md) |
| Chi sviluppa o mantiene il codice | [Architettura](06-architettura.md), [Database](07-database.md), [API](08-api.md), [Manutenzione](11-manutenzione.md) |
| Curioso dei giochi o dell'attestato | [Giochi](09-giochi.md), [Attestato e registro](10-attestato-registro.md) |
| Qualcosa non funziona | [Risoluzione problemi](12-problemi.md) |

## Indice

1. [Panoramica](01-panoramica.md): cos'è, funzioni, concetti chiave (sessione, modulo, registro)
2. [Ambiente locale (XAMPP)](02-ambiente-locale.md)
3. [Deploy su Altervista](03-deploy-altervista.md)
4. [Guida al pannello admin](04-guida-admin.md)
5. [Giorno dell'evento: checklist](05-giorno-evento.md)
6. [Architettura e struttura del codice](06-architettura.md)
7. [Database](07-database.md)
8. [API](08-api.md)
9. [I giochi](09-giochi.md)
10. [Attestato e registro permanente](10-attestato-registro.md)
11. [Manutenzione: aggiungere giochi e livelli, modifiche allo schema](11-manutenzione.md)
12. [Risoluzione problemi](12-problemi.md)
13. [Sicurezza e privacy](13-sicurezza.md)

## Regole del progetto in breve

- Tutto in italiano (testi, commenti, nomi di tabelle e funzioni).
- **Nessuna CDN**: librerie copiate nel progetto (FPDF in `inc/lib`, Blockly e qrcode in `assets/js/vendor`), perché l'evento può girare in LAN senza internet.
- I nomi veri dei ragazzi compaiono **solo in admin**, mai nella classifica pubblica.
- Il registro è **permanente**: non si cancella con reset o reimport dello schema.
- Le modifiche alle tabelle sui DB già in uso sono `ALTER` manuali (vedi [Manutenzione](11-manutenzione.md)).
