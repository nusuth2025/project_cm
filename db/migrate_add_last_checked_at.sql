-- Migration: last_checked_at in monitored_pages
-- Wird vom Cron-Script nach jeder Prüfung gesetzt (unabhängig vom Dump).
-- Ersetzt die bisherige Nutzung von MAX(found_at) aus monitoring_dumps
-- zur Intervall-Berechnung — korrekt, seitdem Dumps nur noch bei Änderungen gespeichert werden.

USE contentmonitor;

ALTER TABLE monitored_pages
    ADD COLUMN last_checked_at DATETIME NULL
        COMMENT 'Zeitpunkt der letzten tatsächlichen Prüfung (unabhängig vom Dump)'
        AFTER start_hour;
