-- =====================================================================
--  Migrazione per i database GIA' IN USO (Altervista, XAMPP): sessioni multiple.
--  Da eseguire UNA volta con phpMyAdmin, sul database del progetto.
--  Non toccare questo file su un database nuovo: li' basta importare schema.sql.
--
--  Cosa fa: crea la tabella `sessioni`, trasforma la sessione che c'era
--  (chiavi sessione_*, scadenza, ospite_pass... di `impostazioni`) nella sessione n. 1,
--  e lega a lei i partecipanti esistenti. Il registro non viene toccato.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS sessioni (
  id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nome              VARCHAR(60)   NOT NULL,
  tipo              VARCHAR(20)   NOT NULL DEFAULT 'openday',
  data              DATE          NOT NULL,
  titolo            VARCHAR(60)   NOT NULL DEFAULT '',
  descrizione       VARCHAR(200)  NOT NULL DEFAULT '',
  utente            VARCHAR(30)   NOT NULL DEFAULT 'openday',
  password          VARCHAR(30)   NOT NULL,
  scade_il          DATETIME      NOT NULL,
  attiva            TINYINT(1)    NOT NULL DEFAULT 1,
  demo              TINYINT(1)    NOT NULL DEFAULT 0,
  diploma_scade_il  DATETIME      NULL,
  qr_minuti         SMALLINT UNSIGNED NULL,
  creata_il         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_password (password)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- la sessione di prima diventa la n. 1 (solo se non esiste ancora nessuna sessione)
INSERT INTO sessioni (nome, tipo, data, titolo, descrizione, password, scade_il, attiva, diploma_scade_il, qr_minuti)
SELECT
  'Sessione precedente',
  COALESCE(NULLIF((SELECT valore FROM impostazioni WHERE chiave = 'sessione_tipo'), ''), 'openday'),
  COALESCE(STR_TO_DATE((SELECT valore FROM impostazioni WHERE chiave = 'sessione_data'), '%Y-%m-%d'), CURDATE()),
  COALESCE((SELECT valore FROM impostazioni WHERE chiave = 'sessione_titolo'), ''),
  COALESCE((SELECT valore FROM impostazioni WHERE chiave = 'sessione_descrizione'), ''),
  COALESCE(NULLIF((SELECT valore FROM impostazioni WHERE chiave = 'ospite_pass'), ''), 'binario101'),
  COALESCE(STR_TO_DATE((SELECT valore FROM impostazioni WHERE chiave = 'scadenza'), '%Y-%m-%d %H:%i:%s'), NOW()),
  COALESCE((SELECT valore FROM impostazioni WHERE chiave = 'evento_attivo'), '1') = '1',
  STR_TO_DATE(NULLIF((SELECT valore FROM impostazioni WHERE chiave = 'diploma_scadenza'), ''), '%Y-%m-%d %H:%i:%s'),
  (SELECT CAST(valore AS UNSIGNED) FROM impostazioni WHERE chiave = 'diploma_qr_minuti')
FROM (SELECT 1) x
WHERE NOT EXISTS (SELECT 1 FROM sessioni);

-- partecipanti: colonna sessione_id e unicita' per sessione
ALTER TABLE partecipanti ADD COLUMN sessione_id INT UNSIGNED NULL AFTER id;
UPDATE partecipanti SET sessione_id = (SELECT MIN(id) FROM sessioni) WHERE sessione_id IS NULL;
ALTER TABLE partecipanti
  MODIFY sessione_id INT UNSIGNED NOT NULL,
  DROP INDEX uq_uuid,
  DROP INDEX uq_nickname,
  DROP INDEX idx_classifica,
  ADD UNIQUE KEY uq_uuid (sessione_id, uuid),
  ADD UNIQUE KEY uq_nickname (sessione_id, nickname),
  ADD KEY idx_classifica (sessione_id, nascosto, punti_totali, updated_at),
  ADD CONSTRAINT fk_partecipanti_sessione FOREIGN KEY (sessione_id) REFERENCES sessioni (id) ON DELETE CASCADE;

-- le vecchie chiavi di sessione in `impostazioni` non servono piu'
DELETE FROM impostazioni WHERE chiave IN
  ('evento_attivo', 'scadenza', 'sessione_tipo', 'sessione_data', 'sessione_titolo', 'sessione_descrizione',
   'sessione_creata', 'ospite_pass', 'diploma_scadenza', 'diploma_qr_minuti');
