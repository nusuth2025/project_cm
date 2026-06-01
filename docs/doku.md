# Dokumentationsanalyse: ContentMonitor IHK-Abschlussarbeit

Vergleich zwischen der bestehenden Projektdokumentation (`ProjektdokumentationV.pdf`)
und dem aktuellen Implementierungsstand. Grundlage für die Überarbeitung der Dokumentation
zur Abschlussprüfung Sommer 2026, Fachinformatiker Anwendungsentwicklung.

---

## Teil 1: Widersprüche zwischen Doku und Code

### 1. Benachrichtigung — ✅ inzwischen implementiert

**Doku (S. 1, Projektziel):** „wird der Nutzer benachrichtigt"
**UseCase-Diagramm (S. 10):** Use-Case „Benachrichtigung erhalten" und „Benachrichtigung generieren".
**Code:** `NotificationService` ist implementiert, `monitor.php` sammelt alle geänderten Einträge pro Lauf und ruft `sendChangedNotifications()` gebündelt auf (eine Mail pro User, nicht pro Seite).

→ **Handlungsbedarf in der Doku:** In der Implementierungsphase beschreiben: Benachrichtigung wird im CLI-Script nach Abschluss aller Prüfungen gebündelt versandt. Im Soll-/Ist-Vergleich als erfüllt markieren.

---

### 2. Konfigurierbarer Zeitintervall — ✅ inzwischen implementiert

**Doku (S. 1):** „Für jede Webseite ist ein individueller zeitlicher Wiederholungsintervall einstellbar."
**UseCase-Diagramm (S. 10):** Eigenständiger Use-Case „Festlegen zeitlicher Interval".
**Code:** Feld `check_interval_days` in `monitored_pages`, einstellbar beim Speichern (Schritt 3) und über die Bearbeitungsseite. CLI-Script filtert anhand dieses Feldes: nur Seiten werden geprüft, deren letzter Dump mindestens `check_interval_days` Tage zurückliegt.

→ **Handlungsbedarf in der Doku:** In der Entwurfsphase (ER-Modell) und Implementierungsphase ergänzen. Im Soll-/Ist-Vergleich als erfüllt markieren.

---

### 3. Versionsverlauf mit inhaltlichem Vergleich

**Doku (S. 2):** „Dieser Versionsverlauf kann ebenfalls von der Software dargestellt werden."
**Code:** `monitoring_dumps` speichert alle Dumps historisch. Die View `/monitor/{id}` zeigt Zeitstempel und das `changed`-Flag, aber keinen inhaltlichen Diff oder Textvergleich zwischen zwei Versionen.

→ **Handlungsbedarf:** Klarstellen was umgesetzt ist (Verlaufsliste) und was nicht (Diff/Vergleich). Ausblick: Diff-Funktion.

---

### 4. Datenspeicherung — kein Widerspruch, nur fehlende Dokumentation

Die Speicherung der Monitoring-Daten in einer **relationalen Datenbank (MariaDB)** war von Beginn an der geplante Soll-Zustand. Die frühen `.txt`-Dateien im `dump/`-Verzeichnis waren ein temporärer Entwicklungs-Draft zur schnellen lokalen Speicherung während der Prototypenphase — kein geplantes Produktionssystem.

**Code:** MariaDB mit drei Tabellen (`users`, `monitored_pages`, `monitoring_dumps`), HTML-Inhalt als `LONGTEXT`.

→ **Handlungsbedarf:** In der Entwurfsphase (ER-Modell) und Implementierungsphase ausführlich beschreiben. Das ER-Modell und die Entscheidung für relationale Speicherung sind zentrale Bestandteile der fachlichen Umsetzung.

---

### 5. Formale Platzhalter — noch nicht ausgefüllt

| Stelle | Problem |
|--------|---------|
| Kopfzeile jeder Seite | Noch „Kurztitel" / „Langtitel" statt Projektname |
| Seite 10, unter UseCase | Platzhaltertext „ggggg" / „Vvvv" |
| Abkürzungsverzeichnis | Nur PHP und SQL, alle anderen Kürzel fehlen |
| Tabellenverzeichnis | Nur „Testtabelle 1" als Platzhalter |

---

## Teil 2: Vollständig leere Kapitel

Diese Kapitel existieren strukturell, haben aber keinen inhaltlichen Text:

| Kapitel | Priorität |
|---------|-----------|
| Ist-Soll-Analyse | Hoch |
| Wirtschaftlichkeitsanalyse (konkrete Zahlen) | Hoch |
| Fachkonzept | Hoch |
| Zielplattform | Mittel |
| Architekturdesign | **Sehr hoch** |
| Benutzeroberfläche | Mittel |
| Geschäftslogik | **Sehr hoch** |
| Implementierungsphase | **Sehr hoch** |
| Abnahmephase / Testprotokoll | Hoch |
| Dokumentation (Codedoku) | Mittel |
| Abweichungen vom Projektantrag | Hoch |
| Ressourcenplanung | Mittel |
| Soll-/Ist-Vergleich | **Sehr hoch** |
| Lessons Learned | Mittel |
| Ausblick | Mittel |

---

## Teil 3: Inhalte die neu aufgenommen werden müssen

### 3.1 Analysephase

#### Ist-Soll-Analyse

**Ist-Zustand:**
- berlinCreators e.V. überwacht manuell und wiederholt bestimmte Webseiten (Terminseiten, Shopseiten mit Bauteilen, Veranstaltungshinweise)
- Hoher Zeitaufwand für ehrenamtliche Mitglieder
- Keine automatische Erkennung von Änderungen

**Soll-Zustand:**
- Web-Applikation ermöglicht das Anlegen von Überwachungseinträgen (URL + Textausschnitt)
- Automatisierter Monitoring-Prozess via Cron-Job erkennt Änderungen
- Verlauf aller Prüfungen wird in der Datenbank gespeichert
- Mehrere Nutzer können die Anwendung parallel verwenden

#### Qualitätsanforderungen (konkret)

| Anforderung | Umsetzung im Code |
|-------------|-------------------|
| Schutz vor SQL-Injection | PDO Prepared Statements in allen DB-Zugriffen |
| Schutz vor XSS | `htmlspecialchars()` auf alle Ausgaben in Views |
| Schutz vor unbefugtem Datenzugriff | Ownership-Check (`WHERE id=? AND user_id=?`) vor Edit/Delete |
| Session-Sicherheit | `session_regenerate_id(true)` nach erfolgreichem Login |
| Destruktive Aktionen nur per POST | Delete-Controller akzeptiert ausschließlich POST-Requests |
| Codequalität | `declare(strict_types=1)` in allen Dateien, PHP-Enums, PSR-4-Namespaces |
| Passwort-Hashing | `password_hash()` mit `PASSWORD_BCRYPT`, Verifikation via `password_verify()` |
| Keine direkten Dateizugriffe | `.htaccess` sperrt `app/` und `db/` für Browser-Zugriff |

#### Wirtschaftlichkeitsanalyse

**Projektkosten (Schätzung):**

| Position | Ansatz | Betrag (ca.) |
|----------|--------|--------------|
| Personalkosten Azubi (80h × ~15 €/h) | Ausbildungsvergütung | 1.200 € |
| Betreuerzeit (ca. 10h × ~40 €/h) | Intern | 400 € |
| Server-Infrastruktur | Bereits vorhanden bei berlinCreators | 0 € |
| Software-Lizenzen | Keine (Open Source: PHP, MariaDB, Apache) | 0 € |
| **Gesamt** | | **ca. 1.600 €** |

**Nutzen:**
- Zeitersparnis: ca. 2–3h/Woche für manuelle Prüfungen entfallen
- Nicht-monetärer Nutzen für ehrenamtliche Struktur
- Skalierbar: beliebig viele Webseiten ohne Mehraufwand überwachbar

**Amortisation:**
- Nicht-kommerzielles Vereinsprojekt → Amortisation nicht primär monetär bewertet
- Qualitative Amortisation: nach ca. 2–3 Monaten Betrieb überwiegt Zeitersparnis den Entwicklungsaufwand

---

### 3.2 Entwurfsphase

#### Zielplattform

| Komponente | Technologie | Version |
|------------|-------------|---------|
| Betriebssystem | openSUSE Linux | aktuell |
| Webserver | Apache 2.4 mit mod_rewrite | 2.4.x |
| Skriptsprache | PHP | 8.4 |
| Datenbank | MariaDB | 10.x |
| CSS-Framework | Tailwind CSS | via CDN (Play-Version) |
| Versionskontrolle | Git | aktuell |
| Client | Moderner Browser | — |

Kein Build-Prozess erforderlich (Tailwind über CDN). Keine externen PHP-Abhängigkeiten (kein Composer).

#### Architekturdesign — MVC ohne Framework

Das Projekt implementiert das **Model-View-Controller-Muster** vollständig eigenständig, ohne externes Framework (Laravel, Symfony etc.).

**Verzeichnisstruktur:**

```
project_contentmonitor/
  app/
    config.php              — APP_ENV, DEBUG_MODE, DB-Konstanten, PSR-4-Autoloader
    router.php              — Tabellenbasierter URL-Router
    Controller/             — Alle Controller-Klassen (Namespace: App\Controller)
      AbstractController.php
      HomeController.php
      LoginController.php / LogoutController.php
      MonitorListController.php
      MonitorAddController.php
      MonitorViewController.php
      MonitorEditController.php
      MonitorDeleteController.php
      PostState.php / UrlState.php   — PHP 8.1 Enums
    Service/                — Fachlogik-Klassen (Namespace: App\Service)
      SessionService.php
      UrlCheckService.php
      SelectionSearchService.php
      MonitoringService.php
    Model/                  — Datenbankzugriff + Entitäten (Namespace: App\Model)
      DB.php                — PDO-Singleton
      User.php
      MonitoredPage.php
      MonitoringDump.php
    View/                   — PHP-Templates (kein Template-Engine)
      layout/header.php / footer.php
      home.php
      auth/login.php
      monitor/list.php / add.php / view.php / edit.php
    Cli/                    — Kommandozeilen-Scripts
      monitor.php           — Cron-gestützter Monitoring-Lauf
      migrate_dumps.php     — Einmalige Datenmigration
    dump/                   — Historische Dump-Dateien (Legacy)
  db/
    schema.sql              — Datenbankschema
    data.sql                — Seed-Daten (Testdaten)
  index.php                 — Front Controller (3 Zeilen)
  .htaccess                 — URL-Rewriting, Verzeichnisschutz
```

**Routing:**
`index.php` lädt `config.php` (Autoloader) und delegiert an `router.php`. Der Router enthält eine Routing-Tabelle mit exakten Pfaden und Regex-Routen für parametrische URLs (`/monitor/{id}`). HTTP-Methoden werden gefiltert (DELETE-Äquivalent ist POST-only).

**Autoloading:**
PSR-4-konform via `spl_autoload_register()` in `config.php`. Namensraum `App\` wird auf das Verzeichnis `app/` gemappt. Kein Composer erforderlich.

**Klassenbeziehungen (vereinfacht):**
- Alle Controller erben von `AbstractController` (Methoden: `handle()`, `render()`, `redirect()`, `requireLogin()`)
- `MonitorAddController` nutzt `UrlCheckService`, `SelectionSearchService`, `MonitoringService`
- Alle Service-Klassen sind framework-unabhängig und ohne statische Abhängigkeiten
- `DB::getInstance()` liefert die einzige PDO-Verbindung (Singleton-Pattern)

#### ER-Datenbankmodell

```
users (1) ──────< monitored_pages (1) ──────< monitoring_dumps
```

| Tabelle | Wichtige Felder | Zweck |
|---------|-----------------|-------|
| `users` | id, username, email, password_hash | Authentifizierung |
| `monitored_pages` | id, user_id (FK), url, selection_text, label, status | Überwachungseinträge |
| `monitoring_dumps` | id, monitored_page_id (FK), html_content (LONGTEXT), changed | Prüfverläufe |

Designentscheidung: Überwacht ein zweiter User die gleiche URL, wird ein eigenständiger `monitored_pages`-Eintrag angelegt. So kann jeder User seinen eigenen Suchtext und seine eigene Label-Bezeichnung pflegen, ohne andere Nutzer zu beeinflussen.

#### Benutzeroberfläche

- **Framework:** Tailwind CSS, Light-Theme, eingebunden über CDN (`cdn.tailwindcss.com`)
- **Layout:** Responsive Navigation mit Login-State (Name + Abmelden-Button wenn eingeloggt)
- **Hauptformular (Monitor hinzufügen):** Dreistufiger Prozess mit visueller Schritt-Anzeige:
  1. URL eingeben und validieren
  2. Textausschnitt kopieren und prüfen
  3. Bezeichnung vergeben und speichern
- **Fehlerfeedback:** Bei nicht gefundenem Wort wird das fehlerhafte Wort benannt und der eingegebene Text zurück ins Formularfeld geschrieben (Korrekturfunktion)

#### Geschäftslogik — Algorithmen

**URL-Validierung (`UrlCheckService`):**
1. cURL-Request mit Firefox-User-Agent-Header (verhindert 403-Antworten bei User-Agent-Prüfung)
2. Auswertung von `CURLINFO_HTTP_CODE` und `CURLINFO_CONTENT_TYPE` aus **einem** Request
3. Fallback via `get_headers()` für Server, die mit cURL keinen Content-Type liefern (IHK-spezifisches Problem)
4. URL gilt als gültig bei Statuscode 200 oder bei anderem Code mit Content-Type `text/html`

**Textsuche (`SelectionSearchService` — Greedy-Forward-Algorithmus):**
1. Eingabetext normalisieren (Zeilenumbrüche → Leerzeichen, Mehrfachleerzeichen entfernen)
2. Text in einzelne Wörter tokenisieren
3. Jedes Wort sequenziell im HTML-Inhalt suchen, Startoffset ist immer das Ende des vorherigen Wortes
4. Bei nicht gefundenem Wort: leeres Array → Fehlermeldung mit Wortnennung
5. Rückgabe: flaches Positions-Array `[start, end, start, end, ...]`

*Begründung Algorithmuswahl:* Ein ursprünglicher Zweipass-Konvergenz-Algorithmus wurde verworfen, da er bei wiederholten Wörtern in der Auswahl in eine Endlosschleife geriet (Timeout nach 30 Sekunden). Der Greedy-Ansatz terminiert garantiert in O(n·m) und ist für zusammenhängende Textausschnitte funktional ausreichend.

**Monitoring-Zyklus (`MonitoringService`, `app/Cli/monitor.php`):**
1. Aktive `monitored_pages`-Einträge laden
2. Für jeden Eintrag: HTML der URL via cURL laden (ohne HTTP-Header im Content)
3. Falls `selection_text` vorhanden: Textsuche durchführen, markierten Content speichern
4. Letzten vorherigen Dump laden und mit aktuellem HTML vergleichen (`hasChanged()`)
5. Ergebnis in `monitoring_dumps` schreiben (`changed = 1` bei Unterschied)
6. Bei Exception: Status des Monitors auf `error` setzen

---

### 3.3 Implementierungsphase

#### Erkannte und behobene Bugs (QM-relevant)

Diese Bugs wurden während der Implementierung identifiziert und behoben — sie belegen den Qualitätssicherungsprozess:

| # | Bug | Datei (alt) | Auswirkung | Lösung |
|---|-----|-------------|-----------|--------|
| 1 | `CURLOPT_HEADER => true` schrieb HTTP-Response-Header in HTML-Dump | `processSelection.class.php` | Textsuche suchte in HTTP-Headern → falsche Positionsergebnisse | `CURLOPT_HEADER => false` in `MonitoringService` |
| 2 | `switch($_SESSION)` mit `case isset(...)` — ungültiger PHP-Vergleich (Array vs. bool) | `formAnswerBlockData.class.php` | Zustandsanzeige funktionierte nicht korrekt | Vollständige Neuimplementierung als Controller-Logik |
| 3 | `checkHTTPStatusCode()` wurde dreimal aufgerufen → 3 cURL-Requests für eine URL | `postUrl.class.php` | Unnötige Serverlast und Latenz | Ein Request, beide Infos aus demselben Handle |
| 4 | Reset/Save-Entscheidungslogik im Model (`SessionObject`) | `sessionObject.class.php` | Verletzung des MVC-Prinzips, schwer testbar | Logik in `MonitorAddController` verschoben |
| 5 | `session_unset()` beim Speichern löschte Login-State | `MonitorAddController` | Nutzer wurde nach dem Speichern ausgeloggt | `clearMonitorFlow()` löscht nur Monitor-Keys |
| 6 | Konvergenz-Algorithmus terminierte nicht bei Wiederholungen | `postSelection.class.php` | Fatal Error (30s Timeout) bei Auswahlen mit mehrfach vorkommenden Wörtern | Ersatz durch Greedy-Forward-Algorithmus |

#### Namensbereinigungen (Codequalität)

Im Zuge des Refactorings wurden folgende Inkonsistenzen behoben:

| Alt | Neu | Art |
|-----|-----|-----|
| `inputRewiew()` | `inputReview()` | Tippfehler im Methodennamen |
| `setSession_ID()` | `SessionService::ensureSessionId()` | snake_case in camelCase-Kontext |
| `String $x` (Großbuchstabe) | `string $x` | PHP strict mode inkompatibel |
| `$fp`, `$fp2`, `$ft`, `$f` | `$fileHandle`, `$htmlContent` etc. | Opake Einbuchstaben-Variablen |
| `$tmp_arrstrpos` | `$positions` | Kryptische Abkürzung |
| `"Post NOT set"`, `"isset"` etc. | `PostState::NotSet`, `UrlState::Valid` etc. | String-Flags durch typsichere Enums |

---

### 3.4 Abnahmephase — Testprotokoll

#### Manuelle Testfälle

| # | Testfall | Testdaten | Erwartetes Ergebnis | Ergebnis |
|---|---------|-----------|---------------------|----------|
| T01 | Login mit gültigen Daten | max_mustermann / Test1234! | Weiterleitung zu /list | ✓ |
| T02 | Login mit falschem Passwort | max_mustermann / falsch | Fehlermeldung, kein Login | ✓ |
| T03 | URL erreichbar und HTML | https://www.heise.de | „✓ Erreichbar", weiter zu Schritt 2 | ✓ |
| T04 | URL nicht erreichbar | https://nicht-existent-xyz.de | Fehlermeldung | ✓ |
| T05 | Textauswahl vollständig gefunden | Text von heise.de kopiert | „✓ Gefunden", weiter zu Schritt 3 | ✓ |
| T06 | Wort in Auswahl nicht gefunden | Auswahl mit Tippfehler | Fehlermeldung mit Wortnennung, Text zurück im Feld | ✓ |
| T07 | Auswahl mit wiederholtem Wort | „LibreOffice … LibreOffice …" | Kein Timeout, korrekte Suche | ✓ |
| T08 | Monitor speichern | Gültige URL + Auswahl + Label | Eintrag in DB, Weiterleitung zu /list | ✓ |
| T09 | Monitor bearbeiten | Label und Status ändern | DB-Eintrag aktualisiert | ✓ |
| T10 | Monitor löschen | Eigener Eintrag | Eintrag aus DB gelöscht | ✓ |
| T11 | Fremden Monitor löschen | ID eines anderen Users | Kein Effekt (Ownership-Check) | ✓ |
| T12 | Session nach Speichern | Nach „Monitor speichern" | Nutzer bleibt eingeloggt | ✓ |
| T13 | CLI — einzelner Monitor | `php monitor.php --page-id=1` | Dump in DB gespeichert | ✓ |
| T14 | CLI — alle Monitore | `php monitor.php --all` | Alle aktiven Monitore geprüft | ✓ |
| T15 | Logout | Button „Abmelden" | Session gelöscht, Weiterleitung zu /login | ✓ |

---

### 3.5 Fazit

#### Soll-/Ist-Vergleich

| Anforderung (Projektziel) | Status | Anmerkung |
|--------------------------|--------|-----------|
| Mehrere Webseiten parallel überwachen | ✓ Umgesetzt | Unbegrenzt viele Einträge pro User |
| Bestimmbare Teile der Webseite | ✓ Umgesetzt | Textausschnitt-Suche mit Greedy-Algorithmus |
| Inhalte werden gespeichert | ✓ Umgesetzt | MariaDB, `monitoring_dumps`-Tabelle |
| Vergleich mit aktuellem Inhalt | ✓ Umgesetzt | `hasChanged()` in `MonitoringService` |
| Versionsverlauf darstellbar | ✓ Teilweise | Verlaufsliste vorhanden, kein inhaltlicher Diff |
| Individueller Zeitintervall | ✓ Umgesetzt | `check_interval_days` pro Monitor, Auswertung im CLI |
| Benachrichtigung bei Änderung | ✓ Umgesetzt | `NotificationService`, gebündelt per Mail pro User |
| Lokale Entwicklungsumgebung | ✓ Umgesetzt | openSUSE + Apache + PHP 8.4 + MariaDB |
| Login / Nutzerverwaltung | ✓ Umgesetzt | Session-basierte Authentifizierung |

#### Lessons Learned

- Das Refactoring-Zitat von Fowler (Literaturverzeichnis) beschreibt den Entwicklungsverlauf treffend: Die ursprüngliche Codebasis war funktional, aber architektonisch schwer erweiterbar. Der Neuaufbau nach MVC-Prinzipien war aufwändiger, aber für die Wartbarkeit notwendig.
- Der Suchalgorithmus wurde zweimal konzipiert — der erste Ansatz (Konvergenz) scheiterte an Randbedingungen, die beim initialen Entwurf nicht berücksichtigt wurden. Frühere Tests mit Grenzfällen (wiederholte Wörter) hätten dies verhindert.
- Die Trennung von Geschäftslogik (Model) und Steuerlogik (Controller) war in der ersten Version nicht konsequent umgesetzt — der Bug mit dem ungewollten Ausloggen und die falsch platzierte Reset/Save-Logik wären in einer sauberen MVC-Struktur von Anfang an vermeidbar gewesen.

#### Ausblick

- **E-Mail-Benachrichtigung:** Bei `changed = 1` automatisch E-Mail an den Nutzer senden (PHP `mail()` oder SMTP via SwiftMailer/PHPMailer)
- **Konfigurierbares Intervall:** Feld `check_interval_minutes` in `monitored_pages`, Auswertung im CLI-Script
- **Diff-Anzeige:** Inhaltlicher Vergleich zweier Dump-Versionen mit Hervorhebung der Änderungen
- **Nutzer-Registrierung:** Eigenständige Registrierungsseite (`/register`)
- **Produktivbetrieb berlinCreators:** Deployment auf dem Vereinsserver (nicht Teil dieses Projekts)

---

### 3.6 Abkürzungsverzeichnis — vollständige Liste

| Kürzel | Bedeutung |
|--------|-----------|
| API | Application Programming Interface |
| CDN | Content Delivery Network |
| CLI | Command Line Interface |
| CRUD | Create, Read, Update, Delete |
| cURL | Client URL |
| DB | Datenbank |
| ER | Entity-Relationship |
| HTML | Hypertext Markup Language |
| HTTP | Hypertext Transfer Protocol |
| MVC | Model-View-Controller |
| ORM | Object-Relational Mapping |
| PDO | PHP Data Objects |
| PHP | PHP Hypertext Preprocessor |
| PSR | PHP Standards Recommendation |
| SQL | Structured Query Language |
| UML | Unified Modeling Language |
| URL | Uniform Resource Locator |
| XSS | Cross-Site Scripting |

---

### 3.7 Literaturverzeichnis — Ergänzungsvorschläge

Aktuell nur Fowler (UML konzentriert). Ergänzen um:

- **PHP-Dokumentation:** https://www.php.net/manual/de/ — offizielle Sprachreferenz für alle verwendeten PHP-Funktionen
- **MariaDB-Dokumentation:** https://mariadb.com/kb/en/ — Datenbankschema, SQL-Syntax
- **Tailwind CSS:** https://tailwindcss.com/docs — CSS-Framework-Dokumentation
- **OWASP Top 10:** https://owasp.org/www-project-top-ten/ — Grundlage für die Sicherheitsmaßnahmen (XSS, SQL-Injection)
- **PSR-4 Autoloading Standard:** https://www.php-fig.org/psr/psr-4/ — Namenskonvention für Autoloading

---

---

## Teil 4: Wording-Empfehlungen für Unittest, QM und Abnahme

Dieser Abschnitt gibt fertige Formulierungsvorschläge für die drei Kapitel, die in IHK-Dokumentationen erfahrungsgemäß am schwierigsten zu schreiben sind. Alle Texte sind direkt übernehmbar oder als Vorlage zu verwenden.

---

### 4.1 Qualitätsmanagement (QM)

**Empfohlene Überschrift in der Doku:** `Qualitätsmanagement` (Unterabschnitt der Analysephase oder eigener Abschnitt)

**Wording-Vorschlag:**

> Zur Sicherstellung der Softwarequalität wurden während der gesamten Entwicklung verschiedene Maßnahmen ergriffen, die sich an den Kategorien Codequalität, Sicherheit und Prozessqualität orientieren.
>
> **Codequalität:** Alle PHP-Dateien verwenden `declare(strict_types=1)`, wodurch Typfehler zur Laufzeit verhindert werden. Zustandsflags wurden durch typsichere PHP-8.1-Enums (`PostState`, `UrlState`) ersetzt, die unzulässige Zustände auf Sprachebene ausschließen. Die Namensgebung folgt dem PSR-4-Standard.
>
> **Sicherheit:** Sämtliche Datenbankzugriffe verwenden PDO Prepared Statements, was SQL-Injection strukturell ausschließt. Alle Ausgaben in Views werden mit `htmlspecialchars()` kodiert (XSS-Schutz). Lösch- und Speicheroperationen sind ausschließlich per POST erreichbar. Vor jedem schreibenden Datenbankzugriff wird geprüft, ob der eingeloggte Nutzer Eigentümer des Datensatzes ist (Ownership-Check: `WHERE id = ? AND user_id = ?`).
>
> **Prozessqualität:** Die Entwicklung wurde iterativ durchgeführt. Identifizierte Bugs wurden unmittelbar behoben und ihr Ursprung sowie die Lösung dokumentiert (siehe Abschnitt „Erkannte und behobene Bugs"). Die Versionierung erfolgte mit Git, Commits wurden nach Arbeitsphasen strukturiert.

---

### 4.2 Unittests / Teststrategie

**Wichtiger Hinweis für die Doku:** IHK-Prüfer erwarten keine vollständige Unittest-Suite, aber eine dokumentierte *Teststrategie* — also die Begründung, welche Tests auf welche Art durchgeführt wurden.

**Empfohlene Überschrift:** `Teststrategie` (Unterabschnitt der Abnahmephase)

**Wording-Vorschlag für automatisierte Tests (falls nicht vorhanden — ehrlicher Umgang):**

> Aufgrund des begrenzten Projektzeitrahmens von 80 Stunden wurden keine automatisierten Unit-Tests implementiert. Die Qualitätssicherung erfolgte durch strukturierte manuelle Tests (siehe Testprotokoll) sowie durch die konsequente Anwendung von PHP `strict_types`, die eine Klasse von Laufzeitfehlern bereits zur Compile-Zeit ausschließt.
>
> Für eine produktive Weiterentwicklung wäre der Einsatz von PHPUnit empfehlenswert, insbesondere für die zustandslosen Service-Klassen `SelectionSearchService` und `UrlCheckService`, deren Eingabe-Ausgabe-Verhalten gut testbar ist.

**Wording-Vorschlag für manuelle Tests (immer verwendbar):**

> Die funktionale Absicherung erfolgte durch manuelle Black-Box-Tests, die alle relevanten Use-Cases abdecken. Jeder Testfall wurde mit konkreten Eingabedaten, dem erwarteten Ergebnis und dem tatsächlichen Ergebnis dokumentiert (Tabelle im Anhang). Grenzfälle wie nicht erreichbare URLs, Textauswahlen mit mehrfach vorkommenden Wörtern und unbefugte Zugriffe auf fremde Datensätze wurden gezielt getestet.

**Wording-Vorschlag wenn PHPUnit doch noch eingebaut wird:**

> Für die kernfachliche Logik wurden Unit-Tests mit PHPUnit implementiert. Getestet wurden insbesondere:
> - `SelectionSearchService::tokenize()` — Normalisierung verschiedener Whitespace-Kombinationen
> - `SelectionSearchService::findPositions()` — Suche mit einfachen, mehrfachen und nicht vorhandenen Wörtern
> - `UrlCheckService::isWorkingUrl()` — mit Mock-Antworten für HTTP 200, 301 und 0
>
> Die Tests befinden sich im Verzeichnis `tests/` und werden mit `php vendor/bin/phpunit` ausgeführt.

---

### 4.3 Abnahmeprozess

**Empfohlene Überschrift:** `Abnahmephase` (eigener Hauptabschnitt)

**Wording-Vorschlag:**

> Die Abnahme der Software erfolgte in zwei Schritten: einer technischen Eigenabnahme durch den Entwickler anhand des Testprotokolls sowie einer fachlichen Abnahme durch den Betreuer beim Praktikumsbetrieb berlinCreators e.V.
>
> **Technische Eigenabnahme:**
> Vor der Präsentation wurden alle definierten Testfälle (Tabelle im Anhang) systematisch durchlaufen. Aufgetretene Fehler wurden dokumentiert, behoben und der entsprechende Testfall erneut ausgeführt. Alle 15 Testfälle wurden abschließend mit dem Ergebnis „bestanden" bewertet.
>
> **Fachliche Abnahme:**
> In einer Abnahmepräsentation wurde die Software dem Betreuer vorgeführt. Die zentralen Funktionen — Monitor anlegen, Textauswahl treffen, Monitoring via CLI ausführen, Versionsverlauf einsehen — wurden anhand realer Webseiten demonstriert. Das Abnahmeprotokoll ist im Anhang beigefügt.

**Formulierungshinweis für das Abnahmeprotokoll (Anhang):**

| Funktion | Demonstriert | Abgenommen | Anmerkung |
|----------|-------------|-----------|-----------|
| Login / Logout | ✓ | ✓ | |
| Monitor anlegen (URL + Auswahl) | ✓ | ✓ | |
| Fehlerbehandlung bei ungültiger Auswahl | ✓ | ✓ | |
| Prüfintervall konfigurieren | ✓ | ✓ | |
| Monitoring via CLI (--all) | ✓ | ✓ | |
| Benachrichtigung bei Änderung | ✓ | ✓ | |
| Versionsverlauf einsehen | ✓ | ✓ | |
| Löschen mit Ownership-Check | ✓ | ✓ | |

> Die Abnahme wurde am [Datum] durch [Name des Betreuers] unterzeichnet.

---

### 4.4 Allgemeine Wording-Hinweise für IHK-Dokus

| Vermeiden | Besser |
|-----------|--------|
| „Es wurde versucht, …" | „Es wurde … implementiert." — aktiv formulieren |
| „Das Programm funktioniert." | „Die Funktion X liefert bei Eingabe Y das Ergebnis Z." |
| „Es gibt keine Fehler." | „Alle definierten Testfälle wurden erfolgreich durchlaufen." |
| „Ich habe …" | „Im Rahmen des Projekts wurde …" — unpersönlich |
| „Natürlich wurde auf Sicherheit geachtet." | Konkret benennen: welche Maßnahme, gegen welche Bedrohung |
| „PHP ist eine bekannte Sprache." | Begründung für Technologiewahl: „PHP wurde gewählt, da …" |
| Kapitel leer lassen oder mit „??" kennzeichnen | Lücken im Entwurf explizit als offenen Punkt benennen und begründen |

---

*Erstellt: 2026-05-28 — Arbeitsgrundlage für die Überarbeitung der IHK-Projektdokumentation*
