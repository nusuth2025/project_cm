-- Migration: check_count in monitored_pages
-- Zählt tatsächliche Prüfläufe (unabhängig von gespeicherten Dumps).
-- Vorher wurde dump_count aus monitoring_dumps abgeleitet — seit Dumps nur noch
-- bei Änderungen gespeichert werden, stimmt das nicht mehr mit der Prüfanzahl überein.

USE contentmonitor;

ALTER TABLE monitored_pages
    ADD COLUMN check_count INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Anzahl tatsächlich durchgeführter Prüfläufe'
        AFTER last_checked_at;
