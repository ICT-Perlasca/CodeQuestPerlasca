-- Solo per i database che hanno GIA' eseguito migrazione-sessioni.sql prima dell'aggiunta del
-- nome utente per sessione. Si puo' eseguire piu' volte senza danni. Le sessioni esistenti
-- continuano a usare l'utente "openday".
ALTER TABLE sessioni ADD COLUMN IF NOT EXISTS utente VARCHAR(30) NOT NULL DEFAULT 'openday' AFTER descrizione;
