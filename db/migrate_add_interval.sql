-- Migration: Prüfintervall pro Monitor
-- Anwendung: mysql -u root -p contentmonitor < db/migrate_add_interval.sql

USE contentmonitor;

ALTER TABLE monitored_pages
    ADD COLUMN check_interval_days INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Prüfintervall in Tagen (Cron läuft täglich um 15 Uhr)'
        AFTER status;
