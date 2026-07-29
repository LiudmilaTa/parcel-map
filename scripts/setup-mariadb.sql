-- Vytvoří databázi, uživatele a práva pro Parcel Map
CREATE DATABASE IF NOT EXISTS mapa_parcel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'mapa_parcel'@'localhost' IDENTIFIED BY '1111';
GRANT ALL PRIVILEGES ON mapa_parcel.* TO 'mapa_parcel'@'localhost';
FLUSH PRIVILEGES;
