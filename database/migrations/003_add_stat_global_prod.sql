-- =====================================================================
-- Finkashi Analytics — Migration 003 : table d'agregats globaux
--
-- Cette table stocke par jour le nombre de visiteurs uniques et de
-- pages vues, calcules directement a partir des evenements bruts
-- (COUNT DISTINCT sur visiteur_hash et COUNT pages).
--
-- Pourquoi cette table ? Parce qu'on ne peut PAS obtenir le bon total
-- de visiteurs uniques en sommant stat_jour_canal ou stat_jour_page :
-- un visiteur peut apparaitre dans plusieurs canaux (recherche puis
-- direct) ou plusieurs pages au cours de sa session, ce qui le ferait
-- compter plusieurs fois.
--
-- A importer une seule fois dans phpMyAdmin OVH apres mise a jour
-- du code de l'API.
-- =====================================================================

CREATE TABLE IF NOT EXISTS finkashi_stat_jour_global (
    jour       DATE NOT NULL PRIMARY KEY,
    visiteurs  INT UNSIGNED NOT NULL,
    pages_vues INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
