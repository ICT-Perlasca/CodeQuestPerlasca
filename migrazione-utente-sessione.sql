-- Solo per i database che hanno GIA' eseguito migrazione-sessioni.sql prima dell'aggiunta del
-- nome utente per sessione. Eseguirlo UNA volta sola (MySQL di Altervista non ha IF NOT EXISTS: se la colonna c'e' gia' da' l'errore #1060, innocuo). Le sessioni esistenti
-- continuano a usare l'utente "openday".
ALTER TABLE sessioni ADD COLUMN utente VARCHAR(30) NOT NULL DEFAULT 'openday' AFTER descrizione;
