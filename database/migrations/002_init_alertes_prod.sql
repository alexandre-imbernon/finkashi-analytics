-- =====================================================================
-- Finkashi Analytics — Initialisation des regles d'alerte par defaut
--
-- Optionnel : a importer apres 001_create_schema_prod.sql si vous
-- voulez demarrer avec des regles preconfigurees. Vous pouvez aussi
-- les creer une par une depuis l'interface du plugin.
-- =====================================================================

INSERT INTO finkashi_alerte_regle (metrique, operateur, seuil, active) VALUES
    ('visiteurs_jour',  'inferieur', 10,   TRUE),
    ('pages_vues_jour', 'superieur', 1000, TRUE),
    ('visiteurs_jour',  'superieur', 500,  FALSE);
