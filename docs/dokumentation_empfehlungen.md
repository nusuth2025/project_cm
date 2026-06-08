# Empfehlungen zur Projektdokumentation (IHK Abschlussarbeit)

## 1. Architektur & Entwurf

### Screenshot / Diagramm
- **Klassendiagramm** (`docs/class_diagram.uxf`) — zeigt die MVC-Struktur und Abhängigkeiten zwischen
  Controllern, Services und Models. Erklärt die bewusste Entscheidung, kein Framework zu verwenden.

### Code-Auszug
- **`app/router.php`** — eigenes Routing ohne Framework: Regex-Muster, HTTP-Methoden-Prüfung,
  Controller-Instanziierung. Kurz und gut erklärbar.
- **`app/Controller/AbstractController.php`** — Basisklasse mit `render()`, `redirect()`,
  `requireLogin()`: zeigt Vererbung und Wiederverwendung.

---

## 2. Kernalgorithmus: Zweiphasige Positionssuche

Dies ist der fachlich interessanteste Teil des Projekts und eignet sich besonders gut als
Eigenleistungsnachweis.

### Code-Auszug
- **`app/Service/SelectionSearchService.php`**, Methode `findPositions()`:
  - **Phase 1** (Greedy-Forward mit Rechts-Advance): zeigt das algorithmische Denken
  - **Phase 2** (`refinePositions()`): iterative Engstellen-Verfeinerung, Konvergenznachweis
- Den **Vergleich Phase 1 vs. Phase 2** am Samsung/Galaxy-Beispiel aus dem Klassendiagramm-Kommentar
  als Erklärung hinzufügen.

### Warum das dokumentieren?
Die IHK bewertet eigenständige algorithmische Leistungen besonders. Dieser Algorithmus ist keine
Bibliothek, sondern selbst entwickelt und iterativ verbessert worden.

---

## 3. Datenbankschema & Migrationen

### Screenshot
- **ER-Diagramm** der drei Tabellen (`users`, `monitored_pages`, `monitoring_dumps`) —
  kann aus `db/schema.sql` abgeleitet oder mit einem DB-Tool (z. B. DBeaver) exportiert werden.

### Code-Auszug
- **`db/schema.sql`** (relevanter Ausschnitt): Fremdschlüssel mit `ON DELETE CASCADE`,
  Kommentare an Spalten, Indexe — zeigt durchdachtes DB-Design.
- Eine **Migrationsdatei** (z. B. `db/migrate_add_last_checked_at.sql`) als Beispiel für
  nachträgliche Schemaerweiterungen ohne Datenverlust.

---

## 4. Mehrstufiger Monitor-Wizard (UI/UX)

### Screenshots
- **Schritt 1**: URL-Eingabe mit Validierungsfeedback (Fehler- und Warnmeldung)
- **Schritt 2**: Textauswahl mit dem Hinweisblock und der grünen Sidebar
- **Schritt 3**: Feinauswahl — klickbare Wörter, Vorschau der Auswahl
- **Schritt 4**: Zeitintervall-Eingabe und Speichern

### Code-Auszug
- **`app/Controller/MonitorAddController.php`** — State-Machine über `UrlState` / `PostState`-Enums:
  zeigt wie ein mehrstufiger Formularfluss ohne JavaScript-Framework umgesetzt wird.

---

## 5. Quelltext-Prüfansicht (Debug-View)

### Screenshots
- Die **dunkle Quelltext-Ansicht** mit gelb markiertem Umfeld und orange markierter Feinauswahl
- Dump-Auswahl-Dropdown mit Navigationspfeilen
- Toolbar mit Such- und Sprungfunktionen

### Code-Auszug
- **`app/Service/SelectionSearchService.php`**, Methode `buildHighlightedHtml()`:
  Span-Aufbau, Überlappungsbehandlung (inner vs. outer), Context-Window.

---

## 6. Cron-basiertes Monitoring (CLI)

### Code-Auszug
- **`app/Cli/monitor.php`** — zeigt:
  - CLI-Absicherung (`PHP_SAPI !== 'cli'`)
  - Fälligkeitslogik via `DATE_ADD(last_checked_at, INTERVAL ...)` direkt in SQL
  - Trockenlauf-Modus (`--dry-run`)
  - Fehlerbehandlung mit Status-Update in der DB

---

## 7. Sicherheit

### Code-Auszug (je ein kurzer Ausschnitt)
- **`app/Controller/LoginController.php`** — `password_verify()`, Session-Handhabung
- **`app/Controller/AbstractController.php`** — `requireLogin()` mit Redirect
- **Irgendeine View** — konsequentes `htmlspecialchars()` gegen XSS
- **`app/Controller/MonitorDumpDeleteController.php`** — Ownership-Prüfung via JOIN
  (`WHERE mp.user_id = ?`) bevor gelöscht wird

---

## 8. Testing

### Screenshot
- PHPUnit-Ausgabe: `OK (39 tests, 76 assertions)`

### Code-Auszug
- **`tests/Unit/Service/SelectionSearchServiceTest.php`** — Unit-Tests für `findPositions()`:
  Randfall-Abdeckung (leere Eingabe, fehlendes Wort, falsche Reihenfolge)
- Einen Test als Beispiel für testgetriebenes Denken erklären

---

## 9. Technische Entscheidungen (Pflichtenheft / Begründungen)

Die bereits vorhandenen Diskussionsdokumente können direkt als Anhang oder Zusammenfassung
in die Dokumentation einfließen:

- **`docs/db_diskussion.md`** — Warum MariaDB statt NoSQL
- **`docs/php_diskussion.md`** — Warum PHP statt Python / Node.js / Go
- **`docs/deployment_sicherheit.md`** — Produktivbetrieb und Sicherheitsmaßnahmen

---

## 10. Begründung nicht implementierter Sicherheitsfeatures

### Formulierungsvorschlag für die Dokumentation (Abschnitt „Abgrenzung" oder „Nicht-Ziele")

> Die Anwendung ist gemäß Projektantrag als lokal betriebene Software konzipiert.
> Maßnahmen für den Betrieb auf einem öffentlich erreichbaren Server (HTTPS/TLS,
> CSRF-Schutz, Rate-Limiting) sind daher bewusst nicht Bestandteil dieser Arbeit
> und würden den definierten Projektumfang überschreiten.

### Formulierungsvorschlag für den Abschnitt „Qualitätssicherung" oder „Sicherheit"

> Für den lokalen Betrieb im Intranet oder auf einem Entwicklungsrechner sind die
> implementierten Sicherheitsmaßnahmen — darunter Passwort-Hashing mit `password_hash()`,
> Session-Regenerierung nach Login, konsequentes Output-Escaping gegen XSS sowie
> eigentumsbasierte Datenbankabfragen — ausreichend. Eine Erweiterung um HTTPS,
> CSRF-Schutz und Login-Rate-Limiting wäre für einen Produktivbetrieb im Internet
> notwendig und ist als nächster Ausbauschritt dokumentiert (siehe `docs/deployment_sicherheit.md`).

### Hinweis für die mündliche Prüfung

Das Dokument `docs/deployment_sicherheit.md` zeigt, dass die Sicherheitslücken für den
Produktivbetrieb **bekannt, bewertet und priorisiert** sind. Wenn der Prüfer fragt
*„Was würden Sie für den Produktivbetrieb noch ändern?"* steht damit eine vollständige,
strukturierte Antwort zur Verfügung.

---

## Hinweise zur Struktur der IHK-Dokumentation

| Abschnitt | Empfohlene Inhalte aus diesem Projekt |
|---|---|
| Ist-Analyse / Anforderungen | Lastenheft aus `docs/`, Monitoring-Problemstellung |
| Entwurf | Klassendiagramm, ER-Diagramm, Ablaufdiagramm Wizard |
| Implementierung | Algorithmus, Routing, Controller-Flow, CLI |
| Qualitätssicherung | PHPUnit-Tests, Quelltext-Prüfansicht als manuelles Testtool |
| Abgrenzung / Nicht-Ziele | Lokalbetrieb als bewusste Einschränkung (Punkt 10) |
| Fazit / Ausblick | Deployment-Sicherheit, Python-Microservice (aus `php_diskussion.md`) |

> **Tipp**: Die IHK bewertet besonders, ob du Entscheidungen begründen kannst —
> nicht nur was du gebaut hast, sondern **warum** so und nicht anders.
> Die Diskussionsdokumente sind dafür ideal.
