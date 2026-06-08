# Anwenderdokumentation — ContentMonitor

**Version:** 1.1 | **Datum:** Juni 2026
**Anwendung:** ContentMonitor — Änderungsüberwachung für HTML-Webseiten

---

## Inhaltsverzeichnis

1. [Überblick](#1-überblick)
2. [Systemvoraussetzungen](#2-systemvoraussetzungen)
3. [Installation und Einrichtung](#3-installation-und-einrichtung)
4. [Erste Schritte — Anmelden](#4-erste-schritte--anmelden)
5. [Kontoeinstellungen](#5-kontoeinstellungen)
6. [Monitor anlegen](#6-monitor-anlegen)
7. [Monitor-Übersicht](#7-monitor-übersicht)
8. [Monitor anzeigen und Verlauf einsehen](#8-monitor-anzeigen-und-verlauf-einsehen)
9. [Quelltext-Ansicht](#9-quelltext-ansicht)
10. [Monitor bearbeiten](#10-monitor-bearbeiten)
11. [Monitor löschen](#11-monitor-löschen)
12. [Automatisches Monitoring einrichten (Cron)](#12-automatisches-monitoring-einrichten-cron)
13. [Monitoring manuell starten (CLI)](#13-monitoring-manuell-starten-cli)
14. [Häufige Probleme und Lösungen](#14-häufige-probleme-und-lösungen)
15. [Administrationshinweise](#15-administrationshinweise)

---

## 1. Überblick

ContentMonitor überwacht Textabschnitte auf HTML-Webseiten automatisch auf Änderungen.
Sobald eine Änderung erkannt wird, erhalten Sie eine E-Mail-Benachrichtigung.

**Typischer Arbeitsablauf:**

```
1. Anmelden
      │
      ▼
2. Monitor anlegen
   (URL → Textauswahl → Feinauswahl → Intervall → speichern)
      │
      ▼
3. Cron-Job läuft automatisch im eingestellten Intervall
   (prüft Seite, speichert Ergebnis, sendet Mail bei Änderung)
      │
      ▼
4. Verlauf in der Anwendung einsehen
```

---

## 2. Systemvoraussetzungen

### Für Endanwender (Browser-Nutzung)

- Moderner Browser: Firefox, Chrome, Edge (aktuell)
- Netzwerkzugang zum Server, auf dem ContentMonitor läuft

### Für die Installation (Administrator)

| Komponente | Mindestversion |
|------------|---------------|
| Linux | openSUSE / Debian / Ubuntu |
| Apache | 2.4 mit aktiviertem mod_rewrite |
| PHP | 8.4 mit Extensions: `curl`, `pdo_mysql` |
| MariaDB | 10.x |

---

## 3. Installation und Einrichtung

> Diese Schritte führt der Administrator einmalig durch.

### Schritt 1 — Datenbank anlegen

```bash
sudo mariadb < db/schema.sql
```

Dies erstellt die Datenbank `contentmonitor`, den DB-Nutzer `contentmonitor`
und alle drei Tabellen.

### Schritt 2 — Testdaten laden (optional)

```bash
# Lädt einen Testnutzer (max_mustermann / Test1234!) und Beispieldaten:
sudo mariadb < db/data.sql
```

### Schritt 3 — Passwort-Hash für neuen Nutzer generieren

```bash
php -r "echo password_hash('IhrPasswort', PASSWORD_BCRYPT, ['cost' => 12]);"
```

Den ausgegebenen Hash in die `users`-Tabelle eintragen:

```sql
INSERT INTO users (username, email, password_hash)
VALUES ('nutzername', 'mail@beispiel.de', '$2y$12$...');
```

### Schritt 4 — Konfiguration prüfen

In `app/config.php` folgende Werte anpassen:

```php
define('APP_ENV',  'prod');            // 'dev' für Entwicklung, 'prod' für Betrieb
define('APP_URL',  'http://localhost'); // Öffentliche URL der Anwendung
define('DB_PASS',  'changeme');        // Bitte ändern!
```

### Schritt 5 — Apache konfigurieren

In der Apache-VirtualHost-Konfiguration muss `AllowOverride All` gesetzt sein
und `mod_rewrite` muss geladen sein:

```apache
<Directory "/pfad/zum/project_contentmonitor">
    AllowOverride All
</Directory>
```

In `/etc/sysconfig/apache2` (openSUSE):
```
APACHE_MODULES="... rewrite"
```

Danach: `sudo systemctl restart apache2`

### Schritt 6 — Cron-Job einrichten (automatisches Monitoring)

```bash
crontab -e
```

Folgende Zeile einfügen (stündlich — empfohlen):

```
0 * * * * /usr/bin/php /pfad/zum/project_contentmonitor/app/Cli/monitor.php --all >> /pfad/zum/project_contentmonitor/app/monitor.log 2>&1
```

Für Prüfintervalle unter einer Stunde (z. B. alle 15 Minuten):

```
*/15 * * * * /usr/bin/php /pfad/zum/project_contentmonitor/app/Cli/monitor.php --all >> /pfad/zum/project_contentmonitor/app/monitor.log 2>&1
```

> Das Script prüft bei jedem Aufruf selbst, welche Monitore fällig sind —
> es ist unschädlich, es häufiger laufen zu lassen als nötig.

---

## 4. Erste Schritte — Anmelden

1. Browser öffnen und die Adresse des ContentMonitor aufrufen
2. Auf der Startseite auf **Anmelden** klicken oder direkt `/login` aufrufen
3. Benutzernamen und Passwort eingeben
4. **Anmelden** klicken

Nach erfolgreicher Anmeldung erscheint die Navigationsleiste. Der angezeigte
**Benutzername** ist ein Link zu den Kontoeinstellungen.

> **Hinweis:** Zugangsdaten werden vom Administrator angelegt.
> Eine Selbstregistrierung ist nicht möglich.

---

## 5. Kontoeinstellungen

Klicken Sie in der Navigationsleiste auf Ihren **Benutzernamen** oder rufen Sie
`/settings` auf.

### E-Mail-Adresse ändern

1. Neue E-Mail-Adresse eingeben
2. Aktuelles Passwort zur Bestätigung eingeben
3. **E-Mail aktualisieren** klicken

### Passwort ändern

1. Aktuelles Passwort eingeben
2. Neues Passwort eingeben (mindestens 8 Zeichen)
3. Neues Passwort wiederholen
4. **Passwort ändern** klicken

---

## 6. Monitor anlegen

Ein Monitor besteht aus einer URL und einem Textausschnitt, der auf dieser Seite
überwacht werden soll. Das Anlegen erfolgt in vier Schritten.

Klicken Sie in der Navigation auf **Hinzufügen**.

### Schritt 1 — URL eingeben

1. Die Webadresse der zu überwachenden Seite eingeben
   (vollständig mit `https://`, z. B. `https://www.heise.de`)
2. **URL prüfen** klicken
3. Bei Erfolg erscheint **✓ Erreichbar** und Schritt 2 wird eingeblendet

> **Fehlermeldung:** „Diese Adresse ist nicht erreichbar" → Prüfen Sie ob die URL
> im Browser aufrufbar ist und HTML-Inhalt liefert.

### Schritt 2 — Textauswahl treffen

Auf der linken Seite erscheint ein grüner Hinweisblock mit Tipps zur Textauswahl.
Lesen Sie diesen vor dem ersten Anlegen eines Monitors.

```
┌──────────────────────────────────────────────────┐
│ 🟢 https://www.heise.de      [Diese Seite öffnen]│
└──────────────────────────────────────────────────┘
```

1. Klicken Sie auf **Diese Seite öffnen** — die Seite öffnet sich in einem neuen Tab
2. Markieren und kopieren Sie auf der Webseite einen Textbereich, **innerhalb dessen**
   sich der zu prüfende Inhalt befindet — inklusive etwas Kontext davor und danach
3. Wichtig: Kopieren Sie nur sichtbaren Text, keine Bilder oder Icons. Befindet sich
   ein Bild im Bereich, kopieren Sie Text vor und nach dem Bild in der richtigen Reihenfolge
4. In das Textfeld **Auswahltext** einfügen (Strg+V)
5. **Auswahl prüfen** klicken

Bei Erfolg erscheint **✓ Gefunden** und Schritt 3 wird eingeblendet.

> **Fehlermeldung:** „Das Wort ‚XY' wurde nicht gefunden. Möglicherweise fehlt nur
> ein Leerzeichen zwischen den Worten." → Text korrigieren und erneut versuchen.
> Häufige Ursache: Beim Kopieren wurden zwei Wörter ohne Leerzeichen verbunden,
> oder der Text wird von JavaScript dynamisch geladen.

### Schritt 3 — Feinauswahl (optional)

Die Feinauswahl ermöglicht es, innerhalb des kopierten Umfelds **einen konkreten
Wert** zu markieren — z. B. einen Preis, ein Datum oder eine Versionsnummer.
Nur dieser Wert wird dann bei jeder Prüfung verglichen.

```
┌─────────────────────────────────────────────┐
│ Preis        ab   198,00   Euro  verfügbar   │
│              ──   ──────                     │
│ Ausgewählt: 198,00                           │
└─────────────────────────────────────────────┘
```

1. Klicken Sie auf die Wörter oder Zahlen, die Sie überwachen möchten
   (z. B. den Preis „198,00")
2. Ein zweiter Klick auf ein bereits markiertes Wort hebt die Auswahl wieder auf
3. Klicken Sie auf **Feinauswahl übernehmen**

> Wenn Sie keine Feinauswahl setzen möchten, klicken Sie auf **Überspringen** —
> dann wird das gesamte Umfeld auf Änderungen verglichen.

### Schritt 4 — Zeitintervall und Speichern

```
┌──────────────────────────────────────────────────┐
│ Bezeichnung (optional)                            │
│ ┌─────────────────────────────────────────────┐  │
│ │ z.B. heise.de Startseite                    │  │
│ └─────────────────────────────────────────────┘  │
│                                                   │
│ Erste Prüfung um:  [08:00 Uhr ▾]                 │
│                                                   │
│ Prüfintervall:  [ 1 ] Tage  [ 0 ] Std.  [ 0 ] Min│
│                                                   │
│  [Monitor speichern]  [Zurücksetzen]              │
└──────────────────────────────────────────────────┘
```

1. Optional eine **Bezeichnung** eingeben (erscheint in der Übersicht)
2. **Erste Prüfung um** — Uhrzeit des ersten automatischen Prüflaufs wählen
3. **Prüfintervall** festlegen — wie viel Zeit zwischen zwei Prüfungen liegen soll
   (Minimum: 15 Minuten; Beispiel: 1 Tag 0 Stunden 0 Minuten = täglich)
4. **Monitor speichern** klicken

Nach dem Speichern wird sofort eine erste Prüfung durchgeführt und Sie gelangen
zur Detailansicht des neuen Monitors.

---

## 7. Monitor-Übersicht

Erreichbar über **Meine Monitore** in der Navigation oder `/list`.

```
┌──────────────────┬──────────┬──────────────┬──────────┬────────────────────┐
│ Label / URL      │ Status   │ Letzte Prüf. │ Prüfungen│                    │
├──────────────────┼──────────┼──────────────┼──────────┼────────────────────┤
│ heise.de         │ 🟢 Aktiv │ 2026-06-01   │ 12       │ Anzeigen Bearb. Lösch│
│ uhrzeit.org      │ 🟠 Geänd.│ 2026-06-01   │  8       │ Anzeigen Bearb. Lösch│
└──────────────────┴──────────┴──────────────┴──────────┴────────────────────┘
```

**Status-Badges:**
- 🟢 **Aktiv** — Monitor läuft, keine Änderung beim letzten Lauf
- 🟠 **Geändert** — Die letzte Prüfung hat eine Änderung erkannt
- 🟡 **Pausiert** — Monitor ist deaktiviert, wird nicht geprüft
- 🔴 **Fehler** — Letzter Abruf ist fehlgeschlagen

---

## 8. Monitor anzeigen und Verlauf einsehen

Klicken Sie in der Übersicht auf **Anzeigen** (oder `/monitor/{id}`).

Die Detailansicht zeigt alle Konfigurationsdetails sowie die letzten 20 Prüfläufe:

```
┌─────────────────┬────────────────┬───────────┬──────────┬────┐
│ Zeitpunkt       │ Status         │ Wert      │ Größe    │    │
├─────────────────┼────────────────┼───────────┼──────────┼────┤
│ 2026-06-01 08:00│ 🟢 Unverändert │ 198,00    │ 887.3 KB │ 🔍 │
│ 2026-05-31 08:00│ 🟠 Geändert    │ 220,99    │ 881.1 KB │ 🔍 │
│ 2026-05-30 08:00│ 🟢 Unverändert │ 198,00    │ 880.9 KB │ 🔍 │
└─────────────────┴────────────────┴───────────┴──────────┴────┘
```

- **Wert** — der zum Zeitpunkt der Prüfung gefundene Wert der Feinauswahl
  (nur sichtbar wenn eine Feinauswahl gesetzt ist)
- **🔍** — öffnet die [Quelltext-Ansicht](#9-quelltext-ansicht) für genau diesen Dump
- **✕** — löscht diesen Dump (außer dem ersten, der als Basislinie erhalten bleibt)

Über den Button **🔍 Quelltext prüfen** (oben rechts) kann jederzeit die
aktuelle Live-Version der Seite mit den markierten Fundstellen geprüft werden.

---

## 9. Quelltext-Ansicht

Die Quelltext-Ansicht zeigt den abgerufenen HTML-Quelltext mit farbig markierten
Fundstellen der Auswahl. Sie hilft zu prüfen, ob der Such-Algorithmus die
beabsichtigte Stelle trifft.

**Farbkodierung:**
- 🟡 **Gelb** — Wörter des Umfelds (äußere Auswahl)
- 🟠 **Orange** — Wörter der Feinauswahl

### Quelle wählen

```
[ 🌐 Live abrufen ]  [ ◀ Neuer ]  [ Dump wählen … ▾ ]  [ Älter ▶ ]
```

- **Live abrufen** — lädt die Seite jetzt neu (aktueller Stand)
- **Dump wählen** — wählt einen gespeicherten Dump aus der Verlaufsliste
- **◀ Neuer / Älter ▶** — navigiert zwischen gespeicherten Dumps

### Toolbar

```
[ Suchen: ____________ ] [ ↑ Vorh. ] [ ↓ Nächste ]  [ ▶ Zur Feinauswahl ] [ ▶ Zum Umfeld ]
```

- **Suchen** — durchsucht den Quelltext, markiert alle Treffer
- **↑ Vorh. / ↓ Nächste** — springt zwischen Suchtreffern
- **▶ Zur Feinauswahl** — springt zur nächsten orangenen Markierung (bei mehreren Fundstellen)
- **▶ Zum Umfeld** — springt zur nächsten gelben Markierung

---

## 10. Monitor bearbeiten

Klicken Sie in der Übersicht oder Detailansicht auf **Bearbeiten**.

Änderbar sind:
- **Bezeichnung** — der angezeigte Name in der Übersicht
- **Status** — `Aktiv` oder `Pausiert`
- **Prüfintervall** — Tage, Stunden und Minuten zwischen zwei Prüfungen
- **Startzeit** — Uhrzeit des ersten Prüflaufs

URL und Textausschnitt können nach dem Speichern **nicht** geändert werden.
Legen Sie in diesem Fall einen neuen Monitor an.

---

## 11. Monitor löschen

Klicken Sie in der Übersicht auf **Löschen**. Ein Bestätigungsdialog erscheint.

> ⚠️ Das Löschen entfernt den Monitor und **alle zugehörigen Prüfergebnisse**
> unwiderruflich aus der Datenbank.

---

## 12. Automatisches Monitoring einrichten (Cron)

Das automatische Monitoring erfolgt durch einen **Cron-Job** auf dem Server.

**Empfohlene Einstellung:** Stündlicher Aufruf

```
0 * * * * /usr/bin/php /pfad/zum/project_contentmonitor/app/Cli/monitor.php --all >> /pfad/zum/project_contentmonitor/app/monitor.log 2>&1
```

Was passiert dabei:
1. Das Script lädt alle aktiven Monitore, deren Prüfintervall abgelaufen ist
2. Für jeden fälligen Monitor wird die Webseite abgerufen
3. Der Inhalt wird mit dem letzten gespeicherten Wert verglichen
4. Bei einer Änderung wird eine E-Mail an den Nutzer gesendet
5. Das Ergebnis wird in der Datenbank gespeichert

> Der Cron-Job wird vom Administrator eingerichtet. Als Nutzer müssen Sie
> nichts weiter tun, außer Ihre Monitore korrekt anzulegen.

---

## 13. Monitoring manuell starten (CLI)

Wer Zugang zum Server hat, kann das Monitoring auch manuell starten:

```bash
# Alle fälligen Monitore prüfen:
php app/Cli/monitor.php --all

# Nur einen bestimmten Monitor prüfen (ID aus der Übersicht):
php app/Cli/monitor.php --page-id=3

# Testlauf ohne Datenbankschreibzugriff:
php app/Cli/monitor.php --all --dry-run
```

Beispielausgabe:
```
Prüfe #1: https://www.heise.de
  Keine Änderung.
Prüfe #3: https://www.uhrzeit.org/weltzeit
  ÄNDERUNG festgestellt — Dump #12 gespeichert.

Sende Benachrichtigungen ...
Benachrichtigungen gesendet.
Fertig.
```

---

## 14. Häufige Probleme und Lösungen

### „Diese Adresse ist nicht erreichbar"

**Ursache:** Die URL ist nicht erreichbar, gibt keinen HTTP-200-Status zurück
oder liefert keinen HTML-Inhalt (z. B. PDF, Bild, API-Endpunkt).

**Lösung:**
- Prüfen ob die URL im Browser aufrufbar ist
- Sicherstellen dass die URL mit `https://` oder `http://` beginnt
- Einige Seiten blockieren automatisierte Anfragen — dies lässt sich nicht umgehen

---

### „Das Wort ‚XY' wurde nicht gefunden"

**Ursache:** Ein Wort im kopierten Text kommt im HTML-Quelltext nicht vor.

**Häufige Ursachen:**
- Beim Kopieren wurden zwei Wörter ohne Leerzeichen verbunden
- Der Text wird von JavaScript dynamisch geladen (ContentMonitor prüft nur den
  statischen HTML-Quelltext)
- Sonderzeichen oder geschützte Leerzeichen im kopierten Text

**Lösung:**
- Text mit einem Leerzeichen zwischen den betroffenen Wörtern korrigieren
- Seite erneut öffnen und Text frisch kopieren
- Im Browser: Rechtsklick → „Seitenquelltext anzeigen" → mit Strg+F prüfen ob
  der Text dort vorkommt

---

### Kein E-Mail erhalten obwohl Änderung erkannt

**Ursache:** E-Mail-Versand nicht konfiguriert oder E-Mail im Spam-Ordner.

**Lösung:**
- Spam-Ordner prüfen
- Administrator kontaktieren wegen E-Mail-Konfiguration

---

### Monitor hat Status „Fehler"

**Ursache:** Der letzte Monitoring-Lauf konnte die Webseite nicht abrufen.

**Lösung:**
1. Monitor bearbeiten und Status auf **Aktiv** zurücksetzen
2. Webseite im Browser prüfen ob sie erreichbar ist
3. Falls die Seite dauerhaft nicht erreichbar ist: Monitor löschen

---

### Seite erscheint weiß / 404-Fehler bei Unterseiten

**Ursache:** Apache-Konfiguration (mod_rewrite / AllowOverride) nicht korrekt.

**Lösung:** Administrator kontaktieren (siehe Installationsanleitung, Schritt 5).

---

## 15. Administrationshinweise

### Neuen Nutzer anlegen

```bash
# Hash für Passwort generieren:
php -r "echo password_hash('Passwort123!', PASSWORD_BCRYPT, ['cost' => 12]);"

# SQL ausführen:
sudo mariadb contentmonitor -e "
INSERT INTO users (username, email, password_hash)
VALUES ('nutzername', 'mail@beispiel.de', '\$2y\$12\$...');"
```

### Datenbank-Backup

```bash
mysqldump -u contentmonitor -p contentmonitor > backup_$(date +%Y%m%d).sql
```

### Log-Datei des Cron-Jobs prüfen

```bash
tail -50 /pfad/zum/project_contentmonitor/app/monitor.log
```

### APP_ENV umschalten

In `app/config.php`:
```php
define('APP_ENV', 'prod');  // Produktivbetrieb — keine Debug-Ausgaben im Browser
define('APP_ENV', 'dev');   // Entwicklung
```

---

*ContentMonitor — IHK-Abschlussprojekt Sommer 2026 | Veit Lohse | berlinCreators e.V.*
