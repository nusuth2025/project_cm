-- Migration: Startstunde pro Monitor
-- Legt fest, um wie viel Uhr der erste Prüflauf eines neu angelegten Monitors
-- stattfinden soll. Folgeprüfungen laufen danach im konfigurierten Intervall.
-- Anwendung: mysql -u root -p contentmonitor < db/migrate_add_start_hour.sql

USE contentmonitor;

ALTER TABLE monitored_pages
    ADD COLUMN start_hour TINYINT UNSIGNED NOT NULL DEFAULT 8
        COMMENT 'Stunde des ersten Prüflaufs (0–23). Folgeprüfungen laufen nach check_interval_minutes.'
        AFTER check_interval_minutes;
