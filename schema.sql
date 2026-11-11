-- =====================================================================
--  Open Day Informatica & Telecomunicazioni - IIS "Giacomo Perlasca"
--  Schema MySQL / MariaDB  -  da importare con phpMyAdmin (Altervista)
--  Database Altervista: my_<nomeutente>   (selezionalo PRIMA di importare)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS codegame_tempi;
DROP TABLE IF EXISTS diplomi_token;
DROP TABLE IF EXISTS punteggi;
DROP TABLE IF EXISTS partecipanti;
DROP TABLE IF EXISTS moduli;
DROP TABLE IF EXISTS blacklist;
DROP TABLE IF EXISTS impostazioni;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Impostazioni evento (modificabili da phpMyAdmin o da admin.php)
--   evento_attivo : '1' = login aperto, '0' = chiuso manualmente
--   scadenza      : data/ora (Europe/Rome) oltre la quale il login ospite
--                   e il salvataggio punteggi vengono rifiutati
--   sessione_*    : configurazione della giornata da admin.php ("Sessione"):
--                   tipo ('openday' | 'laboratori'), data (AAAA-MM-GG), titolo
--                   e descrizione dell'attivita'. Cambiano i testi dei PDF.
--                   Se mancano valgono Open Day + giorno della scadenza.
-- ---------------------------------------------------------------------
CREATE TABLE impostazioni (
  chiave  VARCHAR(50)  NOT NULL,
  valore  VARCHAR(255) NOT NULL,
  PRIMARY KEY (chiave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO impostazioni (chiave, valore) VALUES
  ('evento_attivo', '1'),
  ('scadenza',      '2026-12-13 19:00:00'),   -- <-- aggiornare con la data dell'Open Day
  ('sessione_tipo', 'openday'),
  ('sessione_data', '2026-12-13'),
  ('sessione_titolo', ''),
  ('sessione_descrizione', '');

-- ---------------------------------------------------------------------
-- Catalogo moduli: max_punti e' il tetto lato server (anti-cheat)
-- ---------------------------------------------------------------------
CREATE TABLE moduli (
  codice          VARCHAR(40)       NOT NULL,
  titolo          VARCHAR(80)       NOT NULL,
  max_punti       INT UNSIGNED      NOT NULL,
  percorso_ordine TINYINT UNSIGNED  NULL,      -- NULL = attivita' opzionale
  attivo          TINYINT(1)        NOT NULL DEFAULT 1,
  PRIMARY KEY (codice)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO moduli (codice, titolo, max_punti, percorso_ordine) VALUES
  ('viaggio_pacchetto', 'Viaggio del Pacchetto',  500, NULL),   -- tolto dal percorso: al suo posto Duck Coding
  ('binary_lights',     'Binary Lights',         3600, 2),      -- 12 round x 300 (laboratorio); all'Open Day 5 round, tetto 1500 da max_punti_sessione()
  ('escape_room',       'Escape Room Digitale',  2000, NULL),   -- tolta dal sito
  ('pixel_art',         'Pixel Art = Numeri',     300, NULL),
  ('sort_battle',       'Sort Battle',            500, NULL),
  ('indovina_numero',   'Indovina il Numero',     500, NULL),
  ('porte_logiche',     'Porte Logiche',         1900, 4),      -- 19 livelli x 100 (assets/js/porte-logiche.js); all'Open Day 8
  ('costruisci_rete',   'Costruisci la Rete',     500, NULL),
  ('password_meter',    'Password Strength Meter',300, NULL),
  ('human_vs_ai',       'Human vs AI Quiz',       500, NULL),
  ('paperelle_coding',  'Duck Coding',           4200, 1),      -- tappa 1 del percorso, 28 livelli x 150 (inc/codegame.php)
  ('modifica_gioco',    'Modifica il gioco',     1000, 3),      -- 10 missioni x 100 (assets/js/palla-fisica.js); all'Open Day 4
  ('hack_website',      'Hack the Web(site)',    1500, 5);      -- 15 livelli x 100 (assets/js/hack-livelli.js); all'Open Day 4

-- ---------------------------------------------------------------------
-- Partecipanti: nessun dato personale, solo nickname + avatar.
--   uuid      : UUIDv4 generato dal browser e salvato in localStorage
--   nascosto  : 1 = rimosso dalla classifica dal pannello admin
-- ---------------------------------------------------------------------
CREATE TABLE partecipanti (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  uuid          CHAR(36)      NOT NULL,
  nickname      VARCHAR(16)   NOT NULL,
  avatar        VARCHAR(30)   NOT NULL,
  punti_totali  INT UNSIGNED  NOT NULL DEFAULT 0,
  nascosto      TINYINT(1)    NOT NULL DEFAULT 0,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uuid (uuid),
  UNIQUE KEY uq_nickname (nickname),               -- collation _ci: "Neo" = "neo"
  KEY idx_classifica (nascosto, punti_totali, updated_at),
  KEY idx_ultimi (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Punteggi: UNA riga per (partecipante, modulo). Si conserva il record
-- personale; punti_totali in partecipanti e' la SOMMA di queste righe,
-- ricalcolata in transazione da api/save_score.php (niente race condition).
-- ---------------------------------------------------------------------
CREATE TABLE punteggi (
  partecipante_id INT UNSIGNED      NOT NULL,
  modulo          VARCHAR(40)       NOT NULL,
  punti           INT UNSIGNED      NOT NULL DEFAULT 0,
  tentativi       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (partecipante_id, modulo),
  KEY idx_modulo (modulo, punti),
  CONSTRAINT fk_punteggi_partecipante FOREIGN KEY (partecipante_id)
    REFERENCES partecipanti (id) ON DELETE CASCADE,
  CONSTRAINT fk_punteggi_modulo FOREIGN KEY (modulo)
    REFERENCES moduli (codice) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Duck Coding: tempo di ogni partecipante su ogni livello.
--   iniziato_ms : ms Unix della PRIMA apertura del livello (il cronometro
--                 non riparte ricaricando la pagina)
--   ms          : tempo di soluzione (NULL = non ancora risolto); la media
--                 di questa colonna decide i punti di chi risolve dopo
--   punti       : punti del livello; la somma va in punteggi.paperelle_coding
-- ---------------------------------------------------------------------
CREATE TABLE codegame_tempi (
  partecipante_id INT UNSIGNED      NOT NULL,
  livello         TINYINT UNSIGNED  NOT NULL,
  iniziato_ms     BIGINT UNSIGNED   NOT NULL,
  ms              INT UNSIGNED      NULL,
  punti           SMALLINT UNSIGNED NULL,
  blocchi         SMALLINT UNSIGNED NULL,
  esecuzioni      SMALLINT UNSIGNED NULL,
  risolto_il      DATETIME          NULL,
  PRIMARY KEY (partecipante_id, livello),
  KEY idx_livello (livello, ms),
  CONSTRAINT fk_codegame_partecipante FOREIGN KEY (partecipante_id)
    REFERENCES partecipanti (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Token dei QR code del diploma: link usa-e-getta validi 10 minuti per
-- scaricare il PDF da un altro telefono senza login.
--   nome : nome e cognome da stampare (facoltativo); quello permanente
--          sta in `registro`, questa riga viene cancellata alla scadenza
-- ---------------------------------------------------------------------
CREATE TABLE diplomi_token (
  token            CHAR(32)      NOT NULL,      -- 16 byte casuali in esadecimale
  partecipante_id  INT UNSIGNED  NOT NULL,
  nome             VARCHAR(60)   NULL,
  scade_il         DATETIME      NOT NULL,
  PRIMARY KEY (token),
  KEY idx_partecipante (partecipante_id),
  KEY idx_scadenza (scade_il),
  CONSTRAINT fk_diplomi_partecipante FOREIGN KEY (partecipante_id)
    REFERENCES partecipanti (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Registro permanente dei partecipanti: una riga per dispositivo (uuid),
-- scritta all'arrivo (api/register.php) e completata con il nome quando
-- scaricano il diploma. Serve allo staff per sapere chi e' venuto e quanti
-- per turno (admin.php, export CSV).
--   NON ha chiavi esterne e NON e' nella lista DROP qui sopra: sopravvive
--   ad "Azzera classifica", all'eliminazione dei partecipanti e alla
--   reimportazione di questo file. Si svuota solo da admin.php.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS registro (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  uuid         CHAR(36)      NOT NULL,
  nickname     VARCHAR(16)   NOT NULL,
  nome         VARCHAR(60)   NULL,           -- nome e cognome dal diploma
  punti        INT UNSIGNED  NOT NULL DEFAULT 0,
  arrivato_il  DATETIME      NOT NULL,
  diploma_il   DATETIME      NULL,           -- ultimo download del diploma
  numero_attestato INT UNSIGNED NULL,          -- progressivo stampato sul diploma
  PRIMARY KEY (id),
  UNIQUE KEY uq_uuid (uuid),
  UNIQUE KEY uq_numero_attestato (numero_attestato),
  KEY idx_arrivo (arrivato_il)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Blacklist nickname: confronto per sottostringa dopo normalizzazione
-- (minuscolo, leet-speak 0->o 1->i 3->e 4->a 5->s 7->t, solo lettere).
-- Aggiungere/rimuovere voci liberamente da phpMyAdmin o admin.php.
-- ---------------------------------------------------------------------
CREATE TABLE blacklist (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parola  VARCHAR(40)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_parola (parola)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO blacklist (parola) VALUES
  ('cazz'), ('merd'), ('stronz'), ('vaffanc'), ('fanculo'), ('puttan'),
  ('troia'), ('coglion'), ('minchi'), ('bastard'), ('frocio'), ('ricchion'),
  ('negro'), ('nazi'), ('hitler'), ('mussolini'), ('porcodio'), ('diocane'),
  ('madonnaputt'), ('suca'), ('pompin'), ('figa'), ('sega'), ('culo'),
  ('fuck'), ('shit'), ('bitch'), ('dick'), ('porn'), ('sex'),
  ('admin'), ('docente'), ('prof');
