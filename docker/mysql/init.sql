-- Cree la base de donnees WordPress au premier demarrage de MySQL.
-- Le service MySQL execute automatiquement les .sql presents dans
-- /docker-entrypoint-initdb.d/ a la premiere initialisation.

CREATE DATABASE IF NOT EXISTS wordpress CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON wordpress.* TO 'finkashi'@'%';
FLUSH PRIVILEGES;
