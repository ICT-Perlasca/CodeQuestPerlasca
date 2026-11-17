-- Solo per i database che hanno GIA' eseguito migrazione-sessioni.sql: aggiunge l'opzione
-- "sblocca tutti i livelli" per sessione. Eseguirlo UNA volta sola (MySQL di Altervista non ha IF NOT EXISTS: se la colonna c'e' gia' da' l'errore #1060, innocuo).
ALTER TABLE sessioni ADD COLUMN sblocca_tutto TINYINT(1) NOT NULL DEFAULT 0 AFTER demo;
