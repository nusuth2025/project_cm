# Qualitätsanforderungen — ContentMonitor

Grundlage: ISO/IEC 25010 (Softwarequalitätsmodell), angepasst an den Projektrahmen.
Der Betrieb auf einem öffentlichen Server ist **nicht** Teil des Projekts (vgl. Lastenheft,
Grenzen); Anforderungen, die ausschließlich den Produktivbetrieb betreffen, werden
daher nur kursorisch erwähnt.

---

## 1. Funktionale Eignung

| Kriterium | Anforderung | Nachweis |
|---|---|---|
| Vollständigkeit | Alle funktionalen Anforderungen FA 01–FA 11 sind umgesetzt | Manuelle Abnahme anhand Lastenheft |
| Korrektheit der Suche | Wörter der Textauswahl werden im sichtbaren Seiteninhalt gefunden, nicht in HTML-Attributen (z. B. href-URLs) | Unit-Tests `SelectionSearchServiceTest`; manuelle Prüfung in der Quelltext-Ansicht |
| Korrektheit der Änderungserkennung | Eine Änderung der Feinauswahl wird zuverlässig erkannt; ein unveränderter Wert erzeugt keine Falschmeldung | Unit-Tests `MonitoringServiceFallbackTest`; Monitoring-Verlauf im Browser |
| Benachrichtigung | Bei erkannter Änderung wird genau eine E-Mail je Nutzer pro Cron-Lauf versendet | Manueller Test mit lokalem Mailserver |

---

## 2. Zuverlässigkeit

| Kriterium | Anforderung | Nachweis |
|---|---|---|
| Fehlertoleranz | Schlägt der HTTP-Abruf einer Seite fehl, wird der Monitor auf Status `error` gesetzt; die übrigen Monitore werden weiter geprüft | Manueller Test mit nicht erreichbarer URL |
| Suchalgorithmus terminiert | Beide Phasen der Positionssuche terminieren garantiert in O(n·k) (Phase 1) bzw. ≤ n Iterationen (Phase 2) | Code-Inspektion `SelectionSearchService` |
| Datenkonsistenz | Wird ein Monitor gelöscht, werden alle zugehörigen Dumps per `ON DELETE CASCADE` entfernt | Datenbankschema `db/schema.sql` |

---

## 3. Benutzbarkeit

| Kriterium | Anforderung | Nachweis |
|---|---|---|
| Geführter Ablauf | Das Anlegen eines Monitors erfolgt in vier klar getrennten Schritten mit Fortschrittsanzeige | Manuelle Prüfung im Browser |
| Verständliche Fehlermeldungen | Nicht gefundene Wörter werden namentlich gemeldet; ein Hinweis auf fehlendes Leerzeichen erscheint bei häufigem Kopierfehler | Manuelle Prüfung; `MonitorAddController` |
| Sofortige Rückmeldung | Nach dem Speichern wird sofort ein initialer Dump erstellt; die Quelltext-Ansicht ist unmittelbar nutzbar | Manueller Test nach Anlegen eines Monitors |
| Browserkompatibilität | Oberfläche ist funktionsfähig in Firefox, Chrome und Edge (aktuell) — NFA 02 | Manuelle Sichtprüfung |

---

## 4. Wartbarkeit

| Kriterium | Anforderung | Nachweis |
|---|---|---|
| Testabdeckung | Alle wesentlichen Service-Methoden sind durch Unit-Tests abgedeckt | PHPUnit: 51 Tests, 95 Assertions — `vendor/bin/phpunit` |
| Modulare Struktur | Strikte MVC-Schichtentrennung; Controller, Services und Models haben keine zirkulären Abhängigkeiten | Klassendiagramm `docs/class_diagram.uxf` |
| Geringe externe Abhängigkeiten | Im Produktivcode werden ausschließlich PHP-Bordmittel genutzt (keine Composer-Pakete) — NFA 06 | `composer.json`: keine `require`-Einträge |
| Schemamigrationen | Jede Datenbankänderung ist als eigenständige Migrationsdatei dokumentiert | `db/migrate_*.sql` |

---

## 5. Sicherheit (lokaler Betrieb)

| Kriterium | Anforderung | Nachweis |
|---|---|---|
| Passwortspeicherung | Passwörter werden mit `password_hash()` (bcrypt, cost 12) gespeichert — NFA 03 | `LoginController`, `UserSettingsController` |
| XSS-Schutz | Alle Ausgaben in Views werden durch `htmlspecialchars()` kodiert — NFA 04 | Code-Inspektion aller View-Dateien |
| SQL-Injection | Alle Datenbankzugriffe verwenden Prepared Statements mit PDO — NFA 04 | Code-Inspektion aller Controller und Services |
| Zugriffskontrolle | Jede geschützte Route prüft per `requireLogin()` den Session-Status | `AbstractController::requireLogin()` |
| Eigentumsvalidierung | Beim Löschen von Dumps und Monitoren wird per JOIN geprüft, ob das Objekt dem eingeloggten Nutzer gehört | `MonitorDumpDeleteController`, `MonitorDeleteController` |

> **Hinweis:** Maßnahmen für den öffentlichen Serverbetrieb (HTTPS, CSRF-Schutz,
> Rate-Limiting) sind bewusst nicht umgesetzt, da die Anwendung gemäß Projektantrag
> lokal betrieben wird. Eine Übersicht dieser Maßnahmen und ihrer Aufwände ist in
> `docs/deployment_sicherheit.md` dokumentiert.

---

## 6. Effizienz

| Kriterium | Anforderung | Nachweis |
|---|---|---|
| Antwortzeiten | Seitenaufrufe der Web-Oberfläche erfolgen ohne spürbare Verzögerung im lokalen Betrieb | Manuelle Sichtprüfung |
| Cron-Laufzeit | Unveränderte Seiten erzeugen keinen Datenbank-Schreibzugriff (kein Dump gespeichert) | `MonitoringService::runCheck()` |
| Datenmenge | Die Dump-Tabelle wächst nur bei erkannten Änderungen; unveränderte Prüfläufe werden verworfen | Code-Inspektion `MonitoringService` |

---

## 7. Nicht im Projektumfang

Die folgenden Qualitätsaspekte sind aus dem Projektrahmen explizit ausgeschlossen:

- **Skalierbarkeit** — die Anwendung ist für Einzelnutzer im lokalen Betrieb ausgelegt
- **Hochverfügbarkeit / Ausfallsicherheit** — kein Produktivbetrieb geplant
- **Lastverhalten** — kein Performance-Test unter gleichzeitiger Last
- **Barrierefreiheit (WCAG)** — nicht im Lastenheft gefordert
