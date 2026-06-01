# Anwenderdokumentation — ContentMonitor

**Version:** 1.0 | **Datum:** Mai 2026
**Anwendung:** ContentMonitor — Änderungsüberwachung für HTML-Webseiten

---

## Inhaltsverzeichnis

1. [Überblick](#1-überblick)
2. [Systemvoraussetzungen](#2-systemvoraussetzungen)
3. [Installation und Einrichtung](#3-installation-und-einrichtung)
4. [Erste Schritte — Anmelden](#4-erste-schritte--anmelden)
5. [Monitor anlegen](#5-monitor-anlegen)
6. [Monitor-Übersicht](#6-monitor-übersicht)
7. [Monitor anzeigen und Verlauf einsehen](#7-monitor-anzeigen-und-verlauf-einsehen)
8. [Monitor bearbeiten](#8-monitor-bearbeiten)
9. [Monitor löschen](#9-monitor-löschen)
10. [Automatisches Monitoring einrichten (Cron)](#10-automatisches-monitoring-einrichten-cron)
11. [Monitoring manuell starten (CLI)](#11-monitoring-manuell-starten-cli)
12. [Häufige Probleme und Lösungen](#12-häufige-probleme-und-lösungen)
13. [Administrationshinweise](#13-administrationshinweise)

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
   (URL eingeben → Text von der Seite kopieren → speichern)
      │
      ▼
3. Cron-Job läuft automatisch täglich
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
# Als root oder mit ausreichenden Rechten:
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
define('APP_ENV', 'prod');   // 'dev' für Entwicklung, 'prod' für Betrieb
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'contentmonitor');
define('DB_USER', 'contentmonitor');
define('DB_PASS', 'changeme');         // Bitte ändern!
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

Für kürzere Intervalle (z. B. wenn Prüfintervalle unter einer Stunde konfiguriert sind):

```
*/15 * * * * /usr/bin/php /pfad/zum/project_contentmonitor/app/Cli/monitor.php --all >> /pfad/zum/project_contentmonitor/app/monitor.log 2>&1
```

> Das Script prüft bei jedem Aufruf selbst, welche Monitore fällig sind —
> es ist also unschädlich, es häufiger laufen zu lassen als nötig.

---

## 4. Erste Schritte — Anmelden

1. Browser öffnen und die Adresse des ContentMonitor aufrufen
   (z. B. `http://project_contentmonitor.local`)
2. Auf der Startseite auf **Anmelden** klicken oder direkt `/login` aufrufen
3. Benutzernamen und Passwort eingeben
4. **Anmelden** klicken

Nach erfolgreicher Anmeldung erscheint die Navigationsleiste mit Ihrem Benutzernamen
und dem Button **Abmelden**.

> **Hinweis:** Zugangsdaten werden vom Administrator angelegt.
> Eine Selbstregistrierung ist nicht möglich.

---

## 5. Monitor anlegen

Ein Monitor besteht aus einer URL und einem Textausschnitt, der auf dieser Seite
überwacht werden soll.

### Schritt 1 — Seite aufrufen

Klicken Sie in der Navigation auf **Hinzufügen** oder auf der Startseite auf
**Monitor hinzufügen**.

### Schritt 2 — URL eingeben

```
┌─────────────────────────────────────────┐
│ URL                                      │
│ ┌─────────────────────────────────────┐ │
│ │ https://example.com                 │ │
│ └─────────────────────────────────────┘ │
│  [URL prüfen]  [✕ Leeren]               │
└─────────────────────────────────────────┘
```

1. Die Webadresse der zu überwachenden Seite eingeben
   (vollständig mit `https://`, z. B. `https://www.heise.de`)
2. **URL prüfen** klicken
3. Bei Erfolg erscheint **✓ Erreichbar** und Schritt 2 wird eingeblendet

> **Mögliche Fehlermeldung:** „Diese Adresse ist nicht erreichbar oder liefert keinen
> HTML-Inhalt." → Prüfen Sie ob die URL erreichbar ist und mit `http://` oder `https://`
> beginnt.

### Schritt 3 — Textauswahl treffen

Nach erfolgreicher URL-Prüfung erscheint ein grüner Infostreifen mit der URL und
dem Button **Diese Seite öffnen**.

```
┌──────────────────────────────────────────────────┐
│ 🟢 https://www.heise.de      [Diese Seite öffnen]│
└──────────────────────────────────────────────────┘
```

1. Klicken Sie auf **Diese Seite öffnen** — die Seite öffnet sich in einem neuen Tab
2. Auf der Webseite den zu überwachenden Text markieren und **kopieren** (Strg+C)
3. Dabei etwas Kontext (die umgebenden Sätze oder Zeilen) mit kopieren —
   dies erhöht die Treffsicherheit der Suche

   **Beispiel:**
   ```
   Kopieren Sie nicht nur:
   "Neue Veranstaltung"

   Sondern auch den Kontext darum:
   "Veranstaltungen im März
   Neue Veranstaltung am 15. März
   Ort: Werkstatt"
   ```

4. In das Textfeld **Auswahltext** wechseln und einfügen (Strg+V)
5. **Auswahl prüfen** klicken

Bei Erfolg erscheint **✓ Gefunden** und Schritt 3 (Speichern) wird eingeblendet.

> **Mögliche Fehlermeldung:** „Das Wort ‚XY' wurde nicht gefunden." →
> Das markierte Wort konnte im Quelltext der Seite nicht gefunden werden.
> Häufige Ursachen: Sonderzeichen, versteckter JavaScript-Inhalt oder
> Unterschiede zwischen angezeigtem und Quelltext. Text korrigieren und erneut prüfen.

### Schritt 4 — Speichern

```
┌─────────────────────────────────────────────┐
│ Bezeichnung (optional)                       │
│ ┌──────────────────────────────────────┐ [✕]│
│ │ z.B. heise.de Startseite             │    │
│ └──────────────────────────────────────┘    │
│                                              │
│ Prüfintervall (Tage): [  1  ]               │
│                                              │
│  [Monitor speichern]  [Zurücksetzen]         │
└─────────────────────────────────────────────┘
```

1. Optional eine **Bezeichnung** eingeben (z. B. „heise.de Startseite")
2. **Prüfintervall** festlegen: Wie viele Tage sollen zwischen zwei automatischen
   Prüfungen liegen? (Minimum: 1 Tag)
3. **Monitor speichern** klicken

Sie werden zur Übersicht weitergeleitet und sehen den neuen Eintrag in der Liste.

---

## 6. Monitor-Übersicht

Erreichbar über **Meine Monitore** in der Navigation oder `/list`.

```
┌──────────────────┬──────────┬──────────────┬──────────┬───────────────┐
│ Label / URL      │ Status   │ Letzte Prüf. │ Prüfungen│               │
├──────────────────┼──────────┼──────────────┼──────────┼───────────────┤
│ heise.de         │ 🟢 Aktiv │ 2026-05-28   │ 3        │ Anzeigen      │
│ heise.de         │ 🟡 Geänd.│              │          │ Bearbeiten    │
│ uhrzeit.org      │ 🟢 Aktiv │ 2026-05-11   │ 1        │ Löschen       │
└──────────────────┴──────────┴──────────────┴──────────┴───────────────┘
```

**Status-Badges:**
- 🟢 **Aktiv** — Monitor ist aktiv und wird beim nächsten Cron-Lauf geprüft
- 🟡 **Geändert** — Die letzte Prüfung hat eine Änderung erkannt
- 🟠 **Pausiert** — Monitor ist deaktiviert, wird nicht geprüft
- 🔴 **Fehler** — Letzter Abruf ist fehlgeschlagen (Seite nicht erreichbar?)

---

## 7. Monitor anzeigen und Verlauf einsehen

Klicken Sie in der Übersicht auf **Anzeigen** (oder rufen Sie `/monitor/{id}` auf).

Die Detailansicht zeigt:
- URL und Textausschnitt
- Erstellungsdatum
- Tabelle der letzten 20 Prüfungen:

```
┌──────────────────────┬────────────────┬──────────┐
│ Zeitpunkt            │ Status         │ Größe    │
├──────────────────────┼────────────────┼──────────┤
│ 2026-05-28 15:00     │ 🟢 Unverändert │ 887.3 KB │
│ 2026-05-27 15:00     │ 🟡 Geändert    │ 881.1 KB │
│ 2026-05-26 15:00     │ 🟢 Unverändert │ 880.9 KB │
└──────────────────────┴────────────────┴──────────┘
```

> Wenn noch keine Prüfungen stattgefunden haben, erscheint ein Hinweis mit dem
> CLI-Befehl zum manuellen Start.

---

## 8. Monitor bearbeiten

Klicken Sie in der Übersicht oder Detailansicht auf **Bearbeiten**.

Änderbar sind:
- **Bezeichnung** — der angezeigte Name in der Übersicht
- **Status** — `Aktiv` oder `Pausiert`
- **Prüfintervall** — Anzahl Tage zwischen zwei Prüfungen

URL und Textausschnitt können nach dem Speichern **nicht** geändert werden.
Legen Sie in diesem Fall einen neuen Monitor an.

---

## 9. Monitor löschen

Klicken Sie in der Übersicht auf **Löschen**. Ein Bestätigungsdialog erscheint.

> ⚠️ Das Löschen entfernt den Monitor und **alle zugehörigen Prüfergebnisse**
> unwiderruflich aus der Datenbank.

---

## 10. Automatisches Monitoring einrichten (Cron)

Das automatische Monitoring erfolgt durch einen **Cron-Job** auf dem Server.
Ein Cron-Job ist eine geplante Aufgabe, die zu festgelegten Zeiten automatisch ausgeführt wird.

**Empfohlene Einstellung:** Täglich um 15:00 Uhr

```
0 * * * * /usr/bin/php /pfad/zum/project_contentmonitor/app/Cli/monitor.php --all >> /pfad/zum/project_contentmonitor/app/monitor.log 2>&1
```

Was passiert dabei:
1. Das Script lädt alle aktiven Monitore, die laut ihrem Intervall fällig sind
2. Für jeden Monitor wird die Webseite erneut abgerufen
3. Der Inhalt wird mit dem letzten gespeicherten Inhalt verglichen
4. Wurde eine Änderung erkannt, wird eine E-Mail an den Nutzer gesendet
5. Das Ergebnis wird in der Datenbank gespeichert

> Der Cron-Job wird vom Administrator eingerichtet. Als Nutzer müssen Sie
> nichts weiter tun, außer Ihre Monitore korrekt anzulegen.

---

## 11. Monitoring manuell starten (CLI)

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

## 12. Häufige Probleme und Lösungen

### „Diese Adresse ist nicht erreichbar"

**Ursache:** Die URL ist nicht erreichbar, gibt keinen HTTP-200-Status zurück,
oder liefert keinen HTML-Inhalt (z. B. PDF, Bild, API-Endpunkt).

**Lösung:**
- Prüfen ob die URL im Browser aufrufbar ist
- Sicherstellen dass die URL mit `https://` oder `http://` beginnt
- Einige Seiten blockieren automatisierte Anfragen — dies lässt sich nicht umgehen

---

### „Das Wort ‚XY' wurde nicht gefunden"

**Ursache:** Der kopierte Text enthält ein Wort, das im HTML-Quelltext der Seite
nicht vorkommt.

**Häufige Ursachen:**
- Der Text wird von JavaScript dynamisch geladen (ContentMonitor prüft nur den
  statischen HTML-Quelltext)
- Sonderzeichen oder geschützte Leerzeichen im kopierten Text
- Die Seite hat sich zwischen dem Öffnen und dem Einfügen des Textes verändert

**Lösung:**
- Seite erneut öffnen und Text frisch kopieren
- Kürzen: weniger Text verwenden, dafür eindeutigeren
- Im Browser: Seite → Rechtsklick → „Seitenquelltext anzeigen" und prüfen,
  ob der Text dort vorkommt (Strg+F)

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

## 13. Administrationshinweise

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
tail -50 /var/log/contentmonitor.log
```

### Dump-Migration (einmalig nach Erstinstallation)

Falls historische Dump-Dateien aus dem Verzeichnis `app/dump/` in die Datenbank
übernommen werden sollen:

```bash
php app/Cli/migrate_dumps.php
```

### APP_ENV umschalten

In `app/config.php`:
```php
define('APP_ENV', 'prod');  // Produktivbetrieb — keine Debug-Ausgaben
define('APP_ENV', 'dev');   // Entwicklung — debug()-Ausgaben aktiv
```

---

*ContentMonitor — IHK-Abschlussprojekt Sommer 2026 | Veit Lohse | berlinCreators e.V.*
