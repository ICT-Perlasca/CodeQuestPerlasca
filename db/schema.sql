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
DROP TABLE IF EXISTS sessioni;
DROP TABLE IF EXISTS moduli;
DROP TABLE IF EXISTS impostazioni;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Impostazioni globali (chiave/valore): stato dei giochi `gioco_<codice>`
-- ('attivo' | 'disabilitato' | 'nascosto'), impostato da admin/giochi.php.
-- Le impostazioni di ogni giornata stanno in `sessioni`.
-- ---------------------------------------------------------------------
CREATE TABLE impostazioni (
  chiave  VARCHAR(50)  NOT NULL,
  valore  VARCHAR(255) NOT NULL,
  PRIMARY KEY (chiave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Sessioni di gioco: possono essere attive piu' sessioni insieme (ad es. una
-- "demo" per far provare gli studenti tutor e quelle vere dell'attivita').
-- Ogni sessione ha la sua password: e' la password che gli ospiti scrivono al
-- login a decidere in quale sessione entrano. Classifica e partecipanti sono
-- separati per sessione. Si creano da admin/sessione.php.
--   nome       : etichetta per lo staff ("Demo studenti", "Open Day mattina")
--   utente     : nome utente del login ospite (con la password sceglie la sessione)
--   tipo       : 'openday' | 'laboratori' (TIPI_GIORNATA in inc/giornate.php)
--   scade_il   : oltre questa data/ora il login e il salvataggio punti sono rifiutati
--   attiva     : 0 = chiusa a mano
--   sblocca_tutto : 1 = tutti i livelli aperti subito, senza risolvere i precedenti (utile per demo e prove)
--   demo       : 1 = prova: niente registro permanente ne' numero d'attestato
--   diploma_scade_il : fine del download attestati (NULL = automatica, 2 giorni dopo la data)
--   qr_minuti  : validita' del QR code del diploma (NULL = predefinita)
-- ---------------------------------------------------------------------
CREATE TABLE sessioni (
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
  sblocca_tutto     TINYINT(1)    NOT NULL DEFAULT 0,
  diploma_scade_il  DATETIME      NULL,
  qr_minuti         SMALLINT UNSIGNED NULL,
  creata_il         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_password (password)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  ('paperelle_coding',  'Duck Coding',           4200, 1),      -- tappa 1 del percorso, 28 livelli x 150 (inc/duck/livelli.php)
  ('modifica_gioco',    'Modifica il gioco',     1000, 3),      -- 10 missioni x 100 (assets/js/palla-fisica.js); all'Open Day 4
  ('hack_website',      'Hack the Web(site)',    1500, 5);      -- 15 livelli x 100 (assets/js/hack-livelli.js); all'Open Day 4

-- ---------------------------------------------------------------------
-- Partecipanti: nessun dato personale, solo nickname + avatar.
--   uuid      : UUIDv4 generato dal browser e salvato in localStorage
--   nascosto  : 1 = rimosso dalla classifica dal pannello admin
-- ---------------------------------------------------------------------
CREATE TABLE partecipanti (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  sessione_id   INT UNSIGNED  NOT NULL,
  uuid          CHAR(36)      NOT NULL,
  nickname      VARCHAR(16)   NOT NULL,
  avatar        VARCHAR(30)   NOT NULL,
  punti_totali  INT UNSIGNED  NOT NULL DEFAULT 0,
  nascosto      TINYINT(1)    NOT NULL DEFAULT 0,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uuid (sessione_id, uuid),          -- lo stesso telefono puo' stare in sessioni diverse
  UNIQUE KEY uq_nickname (sessione_id, nickname),  -- collation _ci: "Neo" = "neo"; unico solo dentro la sessione
  KEY idx_classifica (sessione_id, nascosto, punti_totali, updated_at),
  KEY idx_ultimi (created_at),
  CONSTRAINT fk_partecipanti_sessione FOREIGN KEY (sessione_id)
    REFERENCES sessioni (id) ON DELETE CASCADE
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
-- Tabella permanente come `registro`: il reimport dello schema non la
-- azzera (niente DROP) e le parole di default vengono inserite solo se la
-- tabella e' vuota. Non e' toccata nemmeno da "Azzera classifica".
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blacklist (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parola  VARCHAR(40)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_parola (parola)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO blacklist (parola)
SELECT t.parola FROM (
  SELECT 'cazz' AS parola
  UNION ALL SELECT 'merd'
  UNION ALL SELECT 'stronz'
  UNION ALL SELECT 'vaffanc'
  UNION ALL SELECT 'fanculo'
  UNION ALL SELECT 'puttan'
  UNION ALL SELECT 'troia'
  UNION ALL SELECT 'coglion'
  UNION ALL SELECT 'minchi'
  UNION ALL SELECT 'bastard'
  UNION ALL SELECT 'frocio'
  UNION ALL SELECT 'ricchion'
  UNION ALL SELECT 'negro'
  UNION ALL SELECT 'nazi'
  UNION ALL SELECT 'hitler'
  UNION ALL SELECT 'mussolini'
  UNION ALL SELECT 'porcodio'
  UNION ALL SELECT 'diocane'
  UNION ALL SELECT 'madonnaputt'
  UNION ALL SELECT 'suca'
  UNION ALL SELECT 'pompin'
  UNION ALL SELECT 'figa'
  UNION ALL SELECT 'sega'
  UNION ALL SELECT 'culo'
  UNION ALL SELECT 'fuck'
  UNION ALL SELECT 'shit'
  UNION ALL SELECT 'bitch'
  UNION ALL SELECT 'dick'
  UNION ALL SELECT 'porn'
  UNION ALL SELECT 'sex'
  UNION ALL SELECT 'admin'
  UNION ALL SELECT 'docente'
  UNION ALL SELECT 'prof'
) t
WHERE NOT EXISTS (SELECT 1 FROM blacklist);
