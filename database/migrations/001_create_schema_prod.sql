-- =====================================================================
-- Finkashi Analytics — Schema de PRODUCTION (OVH mutualise)
--
-- Cette version est destinee a etre importee dans phpMyAdmin OVH.
-- Toutes les tables sont prefixees `finkashi_` pour cohabiter avec
-- les tables WordPress dans la meme base de donnees.
--
-- Generee automatiquement a partir de 001_create_schema.sql.
-- NE PAS EDITER DIRECTEMENT.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Table : page
-- Reference unique de chaque page du site. Evite de repeter le chemin
-- et le titre dans chaque evenement (normalisation).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS finkashi_page (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    chemin          VARCHAR(255)    NOT NULL,
    titre           VARCHAR(255)    DEFAULT NULL,
    decouverte_le   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_page_chemin (chemin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table : source
-- Domaine d'origine du trafic (referent), rattache a un canal.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS finkashi_source (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    domaine         VARCHAR(255)    NOT NULL,
    canal           ENUM('recherche','social','referent','direct') NOT NULL DEFAULT 'referent',
    decouverte_le   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_source_domaine (domaine)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table : evenement
-- Donnee brute : une ligne par consultation. Forte volumetrie, donc
-- cle en BIGINT. Retention courte (purgee apres archivage).
-- visiteur_hash : empreinte anonyme quotidienne (SHA-256 = 64 hex),
-- ne permet aucun suivi inter-journalier.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS finkashi_evenement (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    page_id         INT UNSIGNED    NOT NULL,
    source_id       INT UNSIGNED    DEFAULT NULL,
    canal           ENUM('recherche','social','referent','direct') NOT NULL DEFAULT 'direct',
    pays            CHAR(2)         DEFAULT NULL,
    visiteur_hash   CHAR(64)        NOT NULL,
    survenu_le      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_evt_survenu (survenu_le),
    KEY idx_evt_page (page_id),
    KEY idx_evt_source (source_id),
    CONSTRAINT fk_evt_page   FOREIGN KEY (page_id)   REFERENCES finkashi_page (id)   ON DELETE CASCADE,
    CONSTRAINT fk_evt_source FOREIGN KEY (source_id) REFERENCES finkashi_source (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tables d'agregats journaliers (retention longue, faible volume).
-- Une contrainte d'unicite garantit un seul enregistrement par jour
-- et par valeur d'axe (idempotence du calcul d'agregation).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS finkashi_stat_jour_page (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    page_id         INT UNSIGNED    NOT NULL,
    jour            DATE            NOT NULL,
    pages_vues      INT UNSIGNED    NOT NULL DEFAULT 0,
    visiteurs       INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sjp (jour, page_id),
    CONSTRAINT fk_sjp_page FOREIGN KEY (page_id) REFERENCES finkashi_page (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finkashi_stat_jour_source (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    source_id       INT UNSIGNED    NOT NULL,
    jour            DATE            NOT NULL,
    visiteurs       INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sjs (jour, source_id),
    CONSTRAINT fk_sjs_source FOREIGN KEY (source_id) REFERENCES finkashi_source (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finkashi_stat_jour_canal (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    jour            DATE            NOT NULL,
    canal           ENUM('recherche','social','referent','direct') NOT NULL,
    visiteurs       INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sjc (jour, canal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finkashi_stat_jour_pays (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    jour            DATE            NOT NULL,
    pays            CHAR(2)         NOT NULL,
    visiteurs       INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sjpays (jour, pays)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tables des alertes : regles configurees et historique des
-- declenchements.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS finkashi_alerte_regle (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    metrique        ENUM('visiteurs_jour','pages_vues_jour') NOT NULL,
    operateur       ENUM('inferieur','superieur') NOT NULL,
    seuil           INT UNSIGNED    NOT NULL,
    active          BOOLEAN         NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finkashi_alerte_declenchee (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    regle_id            INT UNSIGNED    NOT NULL,
    valeur_constatee    INT UNSIGNED    NOT NULL,
    declenchee_le       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notifiee            BOOLEAN         NOT NULL DEFAULT FALSE,
    PRIMARY KEY (id),
    KEY idx_ad_regle (regle_id),
    CONSTRAINT fk_ad_regle FOREIGN KEY (regle_id) REFERENCES finkashi_alerte_regle (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table : archive
-- Tracabilite des exports realises avant purge des evenements bruts.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS finkashi_archive (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    periode_debut   DATE            NOT NULL,
    periode_fin     DATE            NOT NULL,
    fichier         VARCHAR(255)    NOT NULL,
    nb_evenements   INT UNSIGNED    NOT NULL DEFAULT 0,
    creee_le        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
