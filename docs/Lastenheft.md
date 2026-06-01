# Lastenheft — ContentMonitor

**Auftraggeber:** berlinCreators e.V., Lehrter Str. 53, 10557 Berlin-Moabit
**Auftragnehmer:** Veit Lohse (Auszubildender, BBQ Berlin Charlottenburg)
**Dokumentversion:** 1.0
**Datum:** Berlin, Mai 2026
**Projekt:** ContentMonitor — Änderungsüberwachung für HTML-Websiten

---

## 1. Ausgangssituation und Problemstellung

berlinCreators e.V. ist ein Verein im Bereich MINT, Maker und Coding. Die Vereinsmitglieder
beobachten regelmäßig externe Webseiten auf Änderungen — zum Beispiel Terminseiten von
Veranstaltungsorten, Shopseiten mit Bauteilen oder Webseiten mit Fördermittelinformationen.

Diese Beobachtung erfolgt derzeit **manuell und wiederholt**: Ein Mitglied ruft die Seite auf,
prüft visuell ob sich etwas verändert hat, und informiert bei Bedarf andere. Dieser Prozess
ist zeitaufwändig, fehleranfällig und nicht skalierbar.

**Problem:** Es gibt kein Werkzeug, das diese Überwachung automatisiert und die Vereinsmitglieder
proaktiv bei Änderungen informiert.

---

## 2. Projektziel

Ziel des Projekts ist die Entwicklung einer Webanwendung, die ausgewählte Textbereiche auf
HTML-Webseiten automatisch auf Änderungen prüft und betroffene Nutzer benachrichtigt.

Die Anwendung soll die manuelle Kontrolle ersetzen und dabei folgende Kernfunktionen erfüllen:

- Überwachung beliebig vieler Webseiten pro Nutzer
- Eingrenzen der Überwachung auf einen bestimmten Textabschnitt der Seite
- Automatisierte, zeitgesteuerte Prüfung ohne manuellen Eingriff
- Benachrichtigung des Nutzers wenn eine Änderung erkannt wird
- Speicherung des Verlaufs aller Prüfungen

---

## 3. Anforderungen

### 3.1 Funktionale Anforderungen

| ID | Anforderung | Priorität |
|----|-------------|-----------|
| FA01 | Der Nutzer kann sich in der Anwendung anmelden und abmelden | Muss |
| FA02 | Der Nutzer kann eine Webseite (URL) zur Überwachung hinzufügen | Muss |
| FA03 | Der Nutzer kann einen bestimmten Textabschnitt der Webseite zur Überwachung festlegen | Muss |
| FA04 | Der Nutzer kann einen individuellen Prüfintervall (in Tagen) festlegen | Muss |
| FA05 | Die Anwendung prüft automatisch und zeitgesteuert ob der Textabschnitt unverändert ist | Muss |
| FA06 | Der Nutzer wird per E-Mail benachrichtigt wenn eine Änderung erkannt wird | Muss |
| FA07 | Der Nutzer kann alle seine Überwachungseinträge in einer Übersicht einsehen | Muss |
| FA08 | Der Nutzer kann den Verlauf vergangener Prüfungen einsehen | Soll |
| FA09 | Der Nutzer kann Überwachungseinträge bearbeiten und löschen | Muss |
| FA10 | Mehrere Nutzer können die Anwendung unabhängig voneinander verwenden | Soll |
| FA11 | Der Nutzer kann einen Überwachungseintrag pausieren | Kann |

### 3.2 Nicht-funktionale Anforderungen

| ID | Anforderung |
|----|-------------|
| NFA01 | Die Anwendung läuft in einer lokalen Entwicklungsumgebung (Linux, Apache, PHP, MariaDB) |
| NFA02 | Die Oberfläche ist mit einem modernen Browser bedienbar |
| NFA03 | Passworter werden nicht im Klartext gespeichert |
| NFA04 | Die Anwendung ist gegen gängige Webangriffe (XSS, SQL-Injection) abgesichert |
| NFA05 | Quellcode und Datenbank sind versioniert (Git) |
| NFA06 | Die Anwendung ist ohne externe Frameworks oder kostenpflichtige Lizenzen realisiert |

### 3.3 Abgrenzung — was nicht Bestandteil des Projekts ist

- Installation und Betrieb auf dem Produktivserver von berlinCreators e.V.
- Nutzerregistrierung (Accounts werden initial vom Administrator angelegt)
- Mobile App oder native Desktop-Anwendung
- Überwachung von Webseiten, die JavaScript-Rendering erfordern (nur statisches HTML)
- Diff-Anzeige (inhaltlicher Vergleich zwischen zwei Versionen)

---

## 4. Liefergegenstände

| Gegenstand | Beschreibung |
|------------|--------------|
| Webanwendung | Lauffähige PHP-Anwendung mit Weboberfläche |
| Datenbank-Schema | SQL-Skript zur Einrichtung der Datenbank |
| Seed-Daten | SQL-Skript mit Testdaten (pseudonymisiert) |
| CLI-Script | Kommandozeilenprogramm für den Cron-gesteuerten Monitoring-Lauf |
| Projektdokumentation | IHK-Projektdokumentation gemäß Prüfungsordnung |

---

## 5. Rahmenbedingungen

- **Zeitrahmen:** 80 Stunden gesamt (Planung 20h, Umsetzung 48h, Dokumentation 12h)
- **Entwicklungsumgebung:** Rechner des Praktikanten (openSUSE Linux)
- **Zielumgebung:** Server von berlinCreators e.V. (nicht Teil des Projekts)
- **Abnahme:** Präsentation der fertigen Anwendung beim Betreuer von berlinCreators e.V.

---

*Erstellt im Rahmen der IHK-Abschlussprüfung Sommer 2026, Fachinformatiker Anwendungsentwicklung*
