-- contentMonitor Datenbankschema
-- MariaDB / MySQL kompatibel
-- Anwendung: mysql -u root -p < db/schema.sql

CREATE DATABASE IF NOT EXISTS contentmonitor
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE contentmonitor;

CREATE USER IF NOT EXISTS 'contentmonitor'@'localhost' IDENTIFIED BY 'changeme';
GRANT ALL PRIVILEGES ON contentmonitor.* TO 'contentmonitor'@'localhost';
FLUSH PRIVILEGES;

-- -------------------------------------------------------
-- Tabelle: users
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(80)  NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Tabelle: monitored_pages
-- Verschiedene User können die gleiche URL individuell überwachen
-- (eigener Suchterm, eigene Benachrichtigung)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS monitored_pages (
    id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id              INT UNSIGNED  NOT NULL,
    url                  VARCHAR(2048) NOT NULL,
    selection_text       TEXT          NULL COMMENT 'Der zu überwachende Textausschnitt',
    inner_selection_text TEXT          NULL COMMENT 'Innerer Ausschnitt (Kerntext)',
    label                VARCHAR(255)  NULL     COMMENT 'Optionaler Anzeigename',
    status               ENUM('active','paused','error') NOT NULL DEFAULT 'active',
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_mp_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_mp_user   (user_id),
    INDEX idx_mp_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Tabelle: monitoring_dumps
-- Speichert jeden Monitoring-Lauf mit dem abgerufenen HTML-Inhalt
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS monitoring_dumps (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    monitored_page_id INT UNSIGNED NOT NULL,
    html_content      LONGTEXT     NOT NULL COMMENT 'Abgerufener HTML-Body (ohne HTTP-Header)',
    checked_content   LONGTEXT     NULL     COMMENT 'Markierter Inhalt mit |#|Wort|##|-Delimitern',
    found_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed           TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = Änderung gegenüber Vorgänger',
    PRIMARY KEY (id),
    CONSTRAINT fk_dump_page
        FOREIGN KEY (monitored_page_id) REFERENCES monitored_pages(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_dump_page  (monitored_page_id),
    INDEX idx_dump_found (found_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
