-- =====================================================================
-- Finkashi Analytics — Jeu d'essai
-- Donnees de test realistes pour le developpement et la demonstration.
-- A executer APRES 001_create_schema.sql.
-- =====================================================================

SET NAMES utf8mb4;

-- --- Pages du site (thematique jeu video) ---------------------------
INSERT INTO page (chemin, titre) VALUES
    ('/',                       'Accueil'),
    ('/articles/garage-bad-dream', 'Garage : Bad Dream Adventure'),
    ('/articles/jeux-niche-3ds', 'Pepites meconnues sur 3DS'),
    ('/a-propos',               'A propos'),
    ('/contact',                'Contact');

-- --- Sources de trafic ----------------------------------------------
INSERT INTO source (domaine, canal) VALUES
    ('google.com',      'recherche'),
    ('duckduckgo.com',  'recherche'),
    ('reddit.com',      'social'),
    ('bsky.app',        'social'),
    ('senscritique.com','referent');

-- --- Evenements bruts (repartis sur 3 jours) ------------------------
-- Les hash sont fictifs mais au bon format (64 caracteres hex).
INSERT INTO evenement (page_id, source_id, canal, pays, visiteur_hash, survenu_le) VALUES
    (1, 1, 'recherche', 'FR', SHA2('visiteur-a-2026-05-26', 256), '2026-05-26 09:14:00'),
    (2, 1, 'recherche', 'FR', SHA2('visiteur-b-2026-05-26', 256), '2026-05-26 10:02:00'),
    (2, 3, 'social',    'BE', SHA2('visiteur-c-2026-05-26', 256), '2026-05-26 11:30:00'),
    (3, 5, 'referent',  'JP', SHA2('visiteur-d-2026-05-26', 256), '2026-05-26 14:45:00'),
    (1, NULL, 'direct', 'FR', SHA2('visiteur-a-2026-05-26', 256), '2026-05-26 18:20:00'),
    (2, 2, 'recherche', 'CA', SHA2('visiteur-e-2026-05-27', 256), '2026-05-27 08:05:00'),
    (3, 4, 'social',    'FR', SHA2('visiteur-f-2026-05-27', 256), '2026-05-27 12:15:00'),
    (4, NULL, 'direct',  'FR', SHA2('visiteur-g-2026-05-27', 256), '2026-05-27 16:40:00'),
    (1, 1, 'recherche', 'DE', SHA2('visiteur-h-2026-05-28', 256), '2026-05-28 07:55:00'),
    (2, 3, 'social',    'FR', SHA2('visiteur-i-2026-05-28', 256), '2026-05-28 09:30:00'),
    (2, 3, 'social',    'FR', SHA2('visiteur-i-2026-05-28', 256), '2026-05-28 09:33:00'),
    (5, NULL, 'direct',  'FR', SHA2('visiteur-j-2026-05-28', 256), '2026-05-28 20:10:00');

-- --- Une regle d'alerte d'exemple -----------------------------------
INSERT INTO alerte_regle (metrique, operateur, seuil, active) VALUES
    ('visiteurs_jour', 'inferieur', 10, TRUE);
