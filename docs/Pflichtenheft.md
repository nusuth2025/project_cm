# Pflichtenheft — ContentMonitor

**Auftraggeber:** berlinCreators e.V.
**Auftragnehmer:** Veit Lohse
**Bezugsdokument:** Lastenheft ContentMonitor v1.0
**Dokumentversion:** 1.1
**Datum:** Berlin, Juni 2026

---

## 1. Zielbestimmung

Das System ContentMonitor realisiert alle Muss-Anforderungen des Lastenhefts sowie die
priorisierten Soll-Anforderungen. Die technische Umsetzung erfolgt als PHP-Webanwendung
nach dem MVC-Muster ohne externes Framework, mit MariaDB als Datenspeicher und einem
CLI-Script für den automatisierten Monitoring-Lauf.

---

## 2. Produkteinsatz

### 2.1 Anwendungsbereiche

- Überwachung von Webseiten auf inhaltliche Änderungen
- Zeitgesteuerte, automatisierte Prüfung via Linux-Cron
- Benachrichtigung per E-Mail bei erkannter Änderung

### 2.2 Zielgruppe

Mitglieder von berlinCreators e.V.; technisch grundlegend versierte Nutzer (Browser-Bedienung,
Copy-Paste von Webseiteninhalten).

### 2.3 Betriebsbedingungen

Entwicklungs- und Abnahmeumgebung: openSUSE Linux, Apache 2.4 mit mod_rewrite,
PHP 8.4, MariaDB 10.x. Browser: aktueller Firefox, Chrome oder Edge.

---

## 3. Produktübersicht

```
Browser (Nutzer)
     │
     ▼
index.php  ──►  app/router.php  ──►  Controller  ──►  Service  ──►  Model/DB
                                         │
                                         ▼
                                      View (PHP-Template + Tailwind CSS)

Cron (alle 15 min)
     │
     ▼
app/Cli/monitor.php  ──►  MonitoringService  ──►  DB
                                │
                                ▼
                         NotificationService (E-Mail)
```

---

## 4. Produktfunktionen

### PF01 — Authentifizierung

| Aspekt | Umsetzung |
|--------|-----------|
| Login | POST `/login`, Verifikation via `password_verify()` |
| Session | PHP-Session, `session_regenerate_id(true)` nach Login |
| Logout | `session_destroy()`, Weiterleitung zu `/login` |
| Passwort-Speicherung | `password_hash()` mit `PASSWORD_BCRYPT`, cost 12 |
| Zugriffschutz | `requireLogin()` in `AbstractController` — alle geschützten Routen prüfen Session |

### PF02 — Monitor anlegen (FA02, FA03, FA04)

Vierstufiger Eingabeprozess:

**Schritt 1 — URL eingeben und validieren:**
- cURL-Request mit Firefox-User-Agent (verhindert 403 durch UA-Filter)
- Prüfung: HTTP-Statuscode + Content-Type `text/html`
- Fallback via `get_headers()` für Server ohne cURL-Content-Type-Antwort
- Statuscode 200 → gültig; anderer Code → nur gültig wenn Content-Type `text/html`
- Weiterleitungen werden verfolgt; finale URL wird in der Session gespeichert

**Schritt 2 — Umfeld-Text eingeben und prüfen:**
- HTML der URL wird via `MonitoringService::fetchHtml()` geladen (CURLOPT_HEADER=false)
- `<script>` und `<style>`-Blöcke werden vor der Suche entfernt
- Eingabe wird tokenisiert (Whitespace-Normalisierung)
- Suchalgorithmus (Phase-1-Greedy mit Startpunkt-Hint und Rechts-Advance):
  - Die ersten 3 Wörter werden als Phrase gesucht (verhindert falsche Verankerung in Navigation)
  - Jedes Wort wird ab der Endposition des Vorgängers gesucht
  - Kommt dasselbe Wort vor dem nächsten nochmals vor, wird die spätere Position genommen
- Bei Fehler: Nennung des ersten nicht gefundenen Worts, Text zurück ins Formular

**Schritt 3 — Feinauswahl (optional):**
- Die Wörter des Umfeld-Texts werden als klickbare Tokens angezeigt
- Der Nutzer klickt auf den eigentlich zu überwachenden Wert (z. B. Preis, Uhrzeit, Version)
- Ausgewählte Wörter werden als `inner_selection_text` gespeichert
- Schritt kann übersprungen werden; dann wird das gesamte Umfeld verglichen

**Schritt 4 — Speichern:**
- Bezeichnung (optional), Erste Prüfung um (Startstunde 0–23 Uhr) und
  Prüfintervall (Tage / Stunden / Minuten, Minimum 15 min) eingeben
- INSERT in `monitored_pages`

### PF03 — Monitor-Übersicht (FA07)

Route `GET /list` — tabellarische Ansicht aller `monitored_pages` des eingeloggten Nutzers mit:
- Label / URL; Feinauswahl-Vorschau (amber-farbener Chip) falls gesetzt
- Status-Badge (`active` / `paused` / `error`)
- Änderungs-Badge wenn letzter Dump `changed = 1`
- Anzahl bisheriger Prüfungen
- Zeitpunkt der letzten Prüfung
- Prüfintervall (ab xl-Breakpoint)
- Aktionen: Anzeigen, Bearbeiten, Löschen

### PF04 — Prüfverlauf einsehen (FA08)

Route `GET /monitor/{id}` — Einzelansicht mit:
- Metadaten: URL, Prüfintervall, Startstunde, Erstelldatum
- Feinauswahl (Wort-Badges) falls gesetzt
- Umfeld-Text mit farblicher Markierung der Feinauswahl-Wörter
- Tabelle der letzten 20 `monitoring_dumps` mit Zeitpunkt, Größe und Änderungs-Flag
- Button **„Quelltext prüfen"** → öffnet Quelltext-Debug-Ansicht in neuem Tab

### PF05 — Monitor bearbeiten und löschen (FA09)

- Bearbeiten (`GET|POST /edit/{id}`): Label, Status (`active`/`paused`), Startstunde,
  Prüfintervall (Tage/Stunden/Minuten)
- Löschen (`POST /delete/{id}`): nur POST; Ownership-Check vor dem DELETE
- Sicherheitsprinzip: alle Schreibzugriffe prüfen `user_id = ?` (verhindert fremde Datensätze)

### PF06 — Automatisiertes Monitoring (FA05)

```
php app/Cli/monitor.php --all
```

Ablauf:
1. Laden aller aktiven `monitored_pages`, gefiltert nach Fälligkeit:
   - Erstmalig: `last_dump_at IS NULL AND NOW() >= start_hour:00 Uhr heute`
   - Folgelauf: `DATE_ADD(last_dump_at, INTERVAL check_interval_minutes MINUTE) <= NOW()`
2. HTML-Abruf via cURL; `<script>`/`<style>` werden entfernt
3. Außenbereich via Suchalgorithmus (PF02 Schritt 2) lokalisieren
4. Falls `inner_selection_text` gesetzt: Feinauswahl-Position aus Außenbereich-Positionen ableiten
5. Vergleich mit letztem Dump:
   - Mit Feinauswahl: nur der Feinauswahl-Wert wird verglichen
   - Nur Außenbereich: Klartext des Bereichs wird verglichen
   - Kein Auswahltext: vollständiger HTML-Vergleich
6. INSERT in `monitoring_dumps` (`changed = 0|1`, `checked_content` = verglichener Wert)
7. Bei Exception: `status = 'error'`
8. Nach Abschluss aller Prüfungen: Benachrichtigungen versenden (PF07)

Weitere Optionen: `--page-id=X` (Einzelprüfung, ignoriert Intervall), `--dry-run` (kein DB-Schreibzugriff)

Empfohlener Cron-Eintrag (alle 15 Minuten):
```
*/15 * * * * /usr/bin/php /pfad/zum/project_contentmonitor/app/Cli/monitor.php --all >> /pfad/zum/project_contentmonitor/app/monitor.log 2>&1
```

### PF07 — Benachrichtigung (FA06)

Nach Abschluss aller Prüfungen eines CLI-Laufs:
- Alle geänderten Einträge werden pro Nutzer gebündelt
- `NotificationService::sendChangedNotifications()` versendet eine HTML-Mail pro betroffenem Nutzer
- Inhalt: Tabelle mit geändertem Monitor (Link zur Detailansicht), Zeitpunkt und Änderungsdetail
- Änderungsdetail enthält Vorher/Nachher-Wert oder Hinweis wenn Umfeld/Feinauswahl nicht mehr gefunden

### PF08 — Pausieren (FA11)

Über die Bearbeitungsseite kann `status` auf `paused` gesetzt werden.
Der CLI-Filter berücksichtigt nur Einträge mit `status = 'active'`.

### PF09 — Quelltext-Debug-Ansicht

Route `GET /monitor/{id}/quelle` — standalone HTML-Seite (neuer Tab):
- Abruf des aktuellen Seiteninhalts oder des letzten gespeicherten Dumps (umschaltbar)
- Umfeld-Wörter gelb hervorgehoben, Feinauswahl-Wörter orange hervorgehoben
- Statuszeile: Umfeld/Feinauswahl gefunden, aktuell erkannter Wert, Byte-Spanne
- Suchfunktion mit Navigation und Sprung zu erster Fundstelle
- Dient der Überprüfung ob der Algorithmus die beabsichtigte Textstelle trifft

---

## 5. Produktdaten

### 5.1 Datenbankschema

```sql
users              (id, username, email, password_hash, created_at)
monitored_pages    (id, user_id FK, url, selection_text, inner_selection_text,
                    label, status ENUM, check_interval_minutes INT, start_hour TINYINT,
                    created_at, updated_at)
monitoring_dumps   (id, monitored_page_id FK, html_content LONGTEXT,
                    checked_content LONGTEXT, found_at, changed TINYINT(1))
```

`check_interval_minutes`: Prüfintervall in Minuten (Minimum 15, Standard 1440 = 1 Tag).
`start_hour`: Stunde des ersten Prüflaufs (0–23, Standard 8).
`inner_selection_text`: Wörter der Feinauswahl; null wenn kein Feinauswahl-Schritt durchgeführt.

Fremdschlüssel mit `ON DELETE CASCADE`: Löschen eines Nutzers löscht alle seine Einträge.
Löschen eines `monitored_pages`-Eintrags löscht alle zugehörigen Dumps.

### 5.2 Datenmenge (Schätzung)

| Tabelle | Erwartete Größe |
|---------|-----------------|
| `users` | < 100 Datensätze |
| `monitored_pages` | < 1.000 Datensätze |
| `monitoring_dumps` | wächst mit Monitoring-Frequenz; ca. 1 MB pro Dump (heise.de ~900 KB) |

---

## 6. Produktleistungen

| Anforderung | Zielwert |
|-------------|----------|
| Seitenaufbau (Browser) | < 3 Sekunden bei lokalem Betrieb |
| URL-Validierung | < 10 Sekunden (cURL-Timeout eingestellt) |
| Monitoring-Lauf (pro Seite) | < 30 Sekunden (cURL-Timeout 30s) |
| Suchalgorithmus | < 5 ms pro HTML-Datei (gemessen: ~1 ms bei 200 KB) |
| Gleichzeitige Nutzer | Entwicklungsumgebung; keine Lasttests erforderlich |

---

## 7. Qualitätsanforderungen

### 7.1 Sicherheit

| Bedrohung | Maßnahme |
|-----------|----------|
| SQL-Injection | PDO Prepared Statements für alle DB-Zugriffe |
| XSS (Cross-Site-Scripting) | `htmlspecialchars()` auf alle Ausgaben in Views |
| Direktzugriff auf Quellcode | `.htaccess`: `app/` und `db/` gesperrt |
| Unautorisierter Zugriff | `requireLogin()` in `AbstractController` |
| Fremdzugriff auf Daten | Ownership-Check (`WHERE id=? AND user_id=?`) |
| Session-Fixation | `session_regenerate_id(true)` nach Login |
| Klartextpasswörter | `password_hash()` / `password_verify()` |

### 7.2 Codequalität

- `declare(strict_types=1)` in allen PHP-Dateien
- PSR-4-Namespaces (`App\Controller`, `App\Service`, `App\Model`)
- PHP-8.1-Enums statt String-Flags (`PostState`, `UrlState`)
- Keine globalen Variablen außer `$_SESSION`, `$_POST`, `$_SERVER`
- Alle Methoden mit Rückgabetyp deklariert
- Unit-Tests für Models und Services (PHPUnit)

### 7.3 Wartbarkeit

- MVC-Trennung: Geschäftslogik ausschließlich in Service-Klassen
- Views enthalten kein Business-Logic — nur `extract($data)` + HTML
- Autoloading via `spl_autoload_register()` — kein manuelles `include_once`

---

## 8. Technische Produktumgebung

| Komponente | Technologie |
|------------|-------------|
| Server-OS | openSUSE Linux |
| Webserver | Apache 2.4, mod_rewrite aktiviert |
| Skriptsprache | PHP 8.4, Extensions: curl, pdo_mysql |
| Datenbank | MariaDB 10.x |
| CSS | Tailwind CSS via CDN (kein Build-Prozess) |
| Versionskontrolle | Git |
| Test-Framework | PHPUnit (via Composer, nur Entwicklungsabhängigkeit) |
| Laufzeit-Abhängigkeiten | Keine Composer-Pakete |

---

## 9. Abnahmekriterien

Die Abnahme gilt als erfüllt wenn alle folgenden Kriterien erfüllt sind:

| Kriterium | Prüfmethode |
|-----------|-------------|
| Alle 15 Testfälle bestanden (siehe Testprotokoll) | Manuelle Durchführung |
| Monitor anlegen (4 Schritte inkl. Feinauswahl) funktioniert End-to-End | Live-Demo |
| CLI-Script führt Monitoring durch und setzt `changed`-Flag | Terminal-Demonstration |
| Benachrichtigung wird versandt bei `changed = 1` mit Vorher/Nachher-Detail | Terminal + E-Mail-Postfach |
| Löschen eines fremden Monitors ist nicht möglich | Manueller Test |
| SQL-Schema und Seed-Daten lauffähig | `mysql < db/schema.sql && mysql < db/data.sql` |
| Quelltext-Debug-Ansicht zeigt Feinauswahl an korrekter Position | Sichtprüfung im Browser |

---

## 10. Abweichungen vom Lastenheft

| Lastenheft-Anforderung | Umsetzung | Begründung |
|-----------------------|-----------|-----------|
| FA04: Prüfintervall in Tagen | Erweitert auf Minuten/Stunden/Tage + Startstunde | Feinere Steuerung ermöglicht stündliche oder minütliche Prüfungen |
| FA03: Textabschnitt überwachen | Erweitert um Feinauswahl (inner_selection_text) | Reduziert Fehlalarme; ermöglicht Überwachung einzelner Werte (Preise, Zeiten) |
| FA08: Versionsverlauf | Teilweise: Verlaufsliste ohne Diff | Implementierung eines Text-Diff würde den Zeitrahmen überschreiten; als Ausblick dokumentiert |
| NFA01: Lokale Umgebung | ✓ Vollständig erfüllt | — |

---

*Erstellt im Rahmen der IHK-Abschlussprüfung Sommer 2026, Fachinformatiker Anwendungsentwicklung*
