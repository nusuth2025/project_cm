-- contentMonitor Seed-Daten
-- Anwendung: mysql -u root -p < db/data.sql
--
-- Passwort-Hash generieren:
--   php -r "echo password_hash('Test1234!', PASSWORD_BCRYPT, ['cost' => 12]);"

USE contentmonitor;

-- -------------------------------------------------------
-- User: Max Mustermann (pseudonymisiert)
-- Passwort: Test1234!
-- -------------------------------------------------------
INSERT INTO users (id, username, email, password_hash, created_at) VALUES (
    1,
    'max_mustermann',
    'max.mustermann@beispiel.de',
    '$2y$12$Z4jA4onZAQqBU/mZxxvM9.0u.bkEcvadA5CgaNZUfdZpiQUHAspuG',
    '2026-03-01 10:00:00'
);

-- -------------------------------------------------------
-- Überwachte Seiten — abgeleitet aus den Dump-Dateien
-- Timestamps aus Dateinamen (Unix-Timestamp → Datum):
--   monitor1774366350 = 2026-03-24 15:32:30
--   monitor1774440977 = 2026-03-25 12:16:17
--   monitor1774441009 = 2026-03-25 12:16:49
--   monitor1778525922 = 2026-05-11 18:58:42 (mit Selection)
--   monitor1779960746 = 2026-05-28 09:32:26 (heise.de HTML-Dump)
--   monitor1779963854 = 2026-05-28 10:24:14 (heise.de HTML-Dump)
-- -------------------------------------------------------
INSERT INTO monitored_pages
    (id, user_id, url, selection_text, inner_selection_text, label, status, created_at, updated_at)
VALUES
(1, 1,
    'https://www.uhrzeit.org/weltzeit',
    NULL, NULL,
    'Uhrzeit.org Weltzeit',
    'active',
    '2026-03-24 15:32:30', '2026-03-24 15:32:30'),

(2, 1,
    'https://www.uhrzeit.org/weltzeit',
    NULL, NULL,
    'Uhrzeit.org Weltzeit (2)',
    'active',
    '2026-03-25 12:16:17', '2026-03-25 12:16:17'),

(3, 1,
    'https://google.com',
    NULL, NULL,
    'Google Startseite',
    'active',
    '2026-03-25 12:16:49', '2026-03-25 12:16:49'),

(4, 1,
    'https://www.uhrzeit.org/weltzeit',
    'Uhrzeit.org Weltzeit \n\nDie Weltzeit auf einen Blick\n\nHier finden Sie eine Übersicht alle Zeitzonen der Erde und der wichtigsten Städte der Welt',
    NULL,
    'Uhrzeit.org Weltzeit (mit Auswahl)',
    'active',
    '2026-05-11 18:58:42', '2026-05-11 18:58:42'),

(5, 1,
    'https://www.heise.de',
    NULL, NULL,
    'heise online Startseite',
    'active',
    '2026-05-28 09:32:26', '2026-05-28 09:32:26'),

(6, 1,
    'https://www.heise.de',
    NULL, NULL,
    'heise online Startseite (2)',
    'active',
    '2026-05-28 10:24:14', '2026-05-28 10:24:14');

-- -------------------------------------------------------
-- Monitoring-Dumps — Platzhalter für die zwei heise.de-Dumps
-- HTML-Inhalt wird per migrate_dumps.php nachgeladen
-- (900 KB+ Dateien sind nicht inline in SQL einbettbar)
-- -------------------------------------------------------
INSERT INTO monitoring_dumps
    (monitored_page_id, html_content, checked_content, found_at, changed)
VALUES
(5, '', NULL, '2026-05-28 09:32:26', 0),
(6, '', NULL, '2026-05-28 10:24:14', 0);
