# Pflichtenheft — ContentMonitor

**Auftraggeber:** berlinCreators e.V.
**Auftragnehmer:** Veit Lohse
**Bezugsdokument:** Lastenheft ContentMonitor v1.0
**Dokumentversion:** 1.0
**Datum:** Berlin, Mai 2026

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

Cron (täglich)
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

Dreistufiger Eingabeprozess:

**Schritt 1 — URL eingeben und validieren:**
- cURL-Request mit Firefox-User-Agent (verhindert 403 durch UA-Filter)
- Prüfung: HTTP-Statuscode + Content-Type `text/html`
- Fallback via `get_headers()` für Server ohne cURL-Content-Type-Antwort
- Statuscode 200 → gültig; anderer Code → nur gültig wenn Content-Type `text/html`

**Schritt 2 — Textauswahl eingeben und prüfen:**
- HTML der URL wird via `MonitoringService::fetchHtml()` geladen (CURLOPT_HEADER=false)
- Eingabe wird tokenisiert (Whitespace-Normalisierung)
- Greedy-Forward-Algorithmus sucht Wörter sequenziell im HTML
- Bei Fehler: Nennung des ersten nicht gefundenen Worts, Text zurück ins Formular

**Schritt 3 — Speichern:**
- Bezeichnung (optional) und Prüfintervall (in Tagen, Minimum 1) eingeben
- INSERT in `monitored_pages`

### PF03 — Monitor-Übersicht (FA07)

Route `GET /list` — tabellarische Ansicht aller `monitored_pages` des eingeloggten Nutzers mit:
- Label / URL
- Status-Badge (`active` / `paused` / `error`)
- Änderungs-Badge wenn letzter Dump `changed = 1`
- Anzahl bisheriger Prüfungen
- Zeitpunkt der letzten Prüfung
- Aktionen: Anzeigen, Bearbeiten, Löschen

### PF04 — Prüfverlauf einsehen (FA08)

Route `GET /monitor/{id}` — Einzelansicht mit:
- Metadaten des Eintrags (URL, Textauswahl, Erstelldatum)
- Tabelle der letzten 20 `monitoring_dumps` mit Zeitpunkt, Größe und Änderungs-Flag

### PF05 — Monitor bearbeiten und löschen (FA09)

- Bearbeiten (`GET|POST /edit/{id}`): Label, Status (`active`/`paused`), Prüfintervall
- Löschen (`POST /delete/{id}`): nur POST; Ownership-Check vor dem DELETE
- Sicherheitsprinzip: alle Schreibzugriffe prüfen `user_id = ?` (verhindert fremde Datensätze)

### PF06 — Automatisiertes Monitoring (FA05)

```
php app/Cli/monitor.php --all
```

Ablauf:
1. Laden aller aktiven `monitored_pages`, gefiltert nach Intervall:
   `HAVING last_dump_at IS NULL OR DATE(last_dump_at) <= DATE_SUB(CURDATE(), INTERVAL check_interval_days DAY)`
2. HTML-Abruf via cURL
3. Textsuche (falls `selection_text` gesetzt)
4. Vergleich mit letztem Dump (`hasChanged()`)
5. INSERT in `monitoring_dumps` (`changed = 0|1`)
6. Bei Exception: `status = 'error'`

Weitere Optionen: `--page-id=X` (Einzelprüfung), `--dry-run` (kein DB-Schreibzugriff)

### PF07 — Benachrichtigung (FA06)

Nach Abschluss aller Prüfungen eines CLI-Laufs:
- Alle geänderten Einträge werden pro Nutzer gebündelt
- `NotificationService::sendChangedNotifications()` versendet eine Mail pro betroffenem Nutzer
- Inhalt: Liste der geänderten Seiten mit URL und Zeitpunkt

### PF08 — Pausieren (FA11)

Über die Bearbeitungsseite kann `status` auf `paused` gesetzt werden.
Der CLI-Filter berücksichtigt nur Einträge mit `status = 'active'`.

---

## 5. Produktdaten

### 5.1 Datenbankschema

```sql
users              (id, username, email, password_hash, created_at)
monitored_pages    (id, user_id FK, url, selection_text, inner_selection_text,
                    label, status ENUM, check_interval_days, created_at, updated_at)
monitoring_dumps   (id, monitored_page_id FK, html_content LONGTEXT,
                    checked_content LONGTEXT, found_at, changed TINYINT(1))
```

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
| Abhängigkeiten | Keine Composer-Pakete |

---

## 9. Abnahmekriterien

Die Abnahme gilt als erfüllt wenn alle folgenden Kriterien erfüllt sind:

| Kriterium | Prüfmethode |
|-----------|-------------|
| Alle 15 Testfälle bestanden (siehe Testprotokoll) | Manuelle Durchführung |
| Monitor anlegen, prüfen, speichern funktioniert End-to-End | Live-Demo |
| CLI-Script führt Monitoring durch und setzt `changed`-Flag | Terminal-Demonstration |
| Benachrichtigung wird versandt bei `changed = 1` | Terminal + E-Mail-Postfach |
| Löschen eines fremden Monitors ist nicht möglich | Manueller Test |
| SQL-Schema und Seed-Daten lauffähig | `mysql < db/schema.sql && mysql < db/data.sql` |

---

## 10. Abweichungen vom Lastenheft

| Lastenheft-Anforderung | Umsetzung | Begründung |
|-----------------------|-----------|-----------|
| FA08: Versionsverlauf | Teilweise: Verlaufsliste ohne Diff | Implementierung eines Text-Diff würde den Zeitrahmen überschreiten; als Ausblick dokumentiert |
| NFA01: Lokale Umgebung | ✓ Vollständig erfüllt | — |

---

*Erstellt im Rahmen der IHK-Abschlussprüfung Sommer 2026, Fachinformatiker Anwendungsentwicklung*
