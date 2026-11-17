-- Solo per i database che hanno GIA' eseguito migrazione-sessioni.sql: aggiunge l'opzione
-- "sblocca tutti i livelli" per sessione. Si puo' eseguire piu' volte senza danni.
ALTER TABLE sessioni ADD COLUMN IF NOT EXISTS sblocca_tutto TINYINT(1) NOT NULL DEFAULT 0 AFTER demo;
