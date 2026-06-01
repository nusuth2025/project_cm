-- Migration: Prüfintervall von Tagen auf Minuten umstellen
-- Anwendung: mysql -u root -p contentmonitor < db/migrate_interval_to_minutes.sql
--
-- Bestehende Werte werden automatisch umgerechnet (1 Tag = 1440 Minuten).
-- Neues Standard-Intervall: 1440 Minuten (= 1 Tag)

USE contentmonitor;

ALTER TABLE monitored_pages
    CHANGE check_interval_days
           check_interval_minutes INT UNSIGNED NOT NULL DEFAULT 1440
           COMMENT 'Prüfintervall in Minuten (z. B. 1440 = 1 Tag, 90 = 1,5 Stunden)';

-- Vorhandene Tages-Werte in Minuten umrechnen
UPDATE monitored_pages
SET check_interval_minutes = check_interval_minutes * 1440;
