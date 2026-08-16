# MVC-Pattern in contentMonitor

## Grundprinzip

Das Projekt verwendet eine selbst implementierte MVC-Architektur — kein Framework, keine ORM-Schicht.
Der Einstiegspunkt ist `index.php`, der die Konfiguration lädt und den Router aufruft.
Von dort übernimmt ein Controller, verarbeitet die Anfrage und liefert über `render()` eine View aus.

```
Browser-Request
      │
  index.php          ← Session starten, Autoloader, Config
      │
  router.php         ← URL auf Controller-Klasse abbilden
      │
  Controller         ← Anfrage verarbeiten, Daten holen, View aufrufen
      │
  View (PHP-Template) ← HTML ausgeben
```

---

## Die drei Schichten im Detail

### Model (`app/Model/`)

| Datei               | Aufgabe                                      |
|---------------------|----------------------------------------------|
| `DB.php`            | PDO-Singleton — liefert die Datenbankverbindung |
| `MonitoredPage.php` | Datenobjekt für eine überwachte Seite        |
| `MonitoringDump.php`| Datenobjekt für einen gespeicherten Prüflauf |
| `User.php`          | Datenobjekt für einen Benutzer               |

Die Modelle sind **reine Datencontainer** (Data Transfer Objects).
Sie besitzen keine Methoden zum Laden oder Speichern — nur eine statische Factory-Methode `fromRow()`,
die aus einem Datenbank-Array ein typisiertes Objekt baut:

```php
// MonitoredPage.php
public static function fromRow(array $row): self
{
    $page = new self();
    $page->id  = (int) $row['id'];
    $page->url = $row['url'];
    // …
    return $page;
}
```

SQL-Abfragen stehen **nicht** im Model, sondern im Controller oder Service.

### Controller (`app/Controller/`)

Alle Controller erben von `AbstractController`, der drei Hilfsmethoden bereitstellt:

```php
protected function render(string $view, array $data = []): void   // View einbinden
protected function redirect(string $url): never                    // HTTP 302
protected function requireLogin(): void                            // Auth-Guard
```

Jeder Controller implementiert genau eine Methode: `handle()`.
Sie ist der einzige Einstiegspunkt — der Router ruft `$controller->handle()` auf.

Innerhalb von `handle()` laufen in der Regel drei Schritte ab:
1. Login prüfen (`requireLogin()`)
2. Daten aus DB oder Session holen
3. View mit aufbereiteten Daten rendern (`render(...)`)

Beispiel `MonitorListController`:
```php
public function handle(): void
{
    $this->requireLogin();
    $stmt = DB::getInstance()->prepare('SELECT …');
    $stmt->execute([$this->session->getUserId()]);
    $pages = $stmt->fetchAll();
    $this->render('monitor/list', ['pages' => $pages]);
}
```

### View (`app/View/`)

Views sind einfache PHP-Dateien. `render()` macht alle übergebenen Array-Schlüssel via `extract()`
zu lokalen Variablen im Template:

```
app/View/
├── layout/
│   ├── header.php   ← <head>, Navigation
│   └── footer.php   ← </body>
├── monitor/
│   ├── list.php
│   ├── add.php
│   ├── view.php
│   └── edit.php
├── auth/
│   └── login.php
└── user/
    └── settings.php
```

Views binden Layout-Partials manuell ein:
```php
<?php require BASE_PATH . '/app/View/layout/header.php'; ?>
<!-- Seiteninhalt -->
<?php require BASE_PATH . '/app/View/layout/footer.php'; ?>
```

---

## Wo dieses Projekt vom klassischen MVC abweicht

### 1. Anämische Modelle statt Active Record

Im klassischen MVC (und vor allem in Frameworks wie Laravel oder Rails) enthält das Model
die Datenbanklogik: `MonitoredPage::find($id)`, `$page->save()` usw.

Hier sind Modelle reine Datenhüllen. SQL-Abfragen stehen direkt im Controller (kurze Zugriffe)
oder werden an einen **Service** delegiert (komplexe Logik). Das Model weiß nicht, wie es
sich selbst speichert oder lädt.

**Vorteil:** Einfacher zu testen, da kein verstecktes Datenbankverhalten im Model steckt.  
**Nachteil:** Datenbanklogik ist über Controller und Services verteilt statt an einer Stelle.

---

### 2. Service-Schicht als vierte Ebene

Zwischen Controller und Model existiert eine zusätzliche `app/Service/`-Schicht:

| Service                  | Aufgabe                                             |
|--------------------------|-----------------------------------------------------|
| `SessionService`         | Kapselt `$_SESSION` hinter typisierten Methoden     |
| `MonitoringService`      | HTML abrufen, Textauswahl vergleichen, Dump speichern |
| `UrlCheckService`        | URL per cURL prüfen, Weiterleitungen folgen         |
| `SelectionSearchService` | Wortpositionen im HTML-Text finden                  |
| `NotificationService`    | E-Mail-Benachrichtigungen bei Änderungen            |

Services tragen die eigentliche Geschäftslogik. Der Controller orchestriert nur:
er ruft den Service auf und gibt das Ergebnis an die View weiter.

---

### 3. Selbst geschriebener Router ohne Framework

Der Router in `router.php` ist eine flache Tabelle. Routen mit URL-Parametern
werden mit Regex-Mustern (erkennbar am führenden `#`) definiert,
Capturing Groups werden als Integer-Parameter an den Konstruktor übergeben:

```php
$routes = [
    ['GET',      '/list',                    MonitorListController::class],
    ['GET',      '#^/monitor/(\d+)$#',       MonitorViewController::class],
    ['GET|POST', '#^/edit/(\d+)$#',          MonitorEditController::class],
    ['POST',     '#^/delete/(\d+)$#',        MonitorDeleteController::class],
    // …
];
// Aufruf:
$controller = new $controllerClass($session, ...$params);
$controller->handle();
```

Es gibt keine Middleware, keine Named Routes, kein Dependency-Injection-Container.

---

### 4. Session als Zustandsmaschine im Mehr-Schritt-Formular

Der `/add`-Flow (Monitor anlegen) ist in drei Schritte aufgeteilt:
URL prüfen → Textauswahl wählen → Feinauswahl setzen → Speichern.

Da HTTP zustandslos ist, hält der `SessionService` den Zwischenstand zwischen
den einzelnen POST-Anfragen. Der Controller liest den aktuellen Schritt
aus der Session, entscheidet welcher Schritt als nächstes gezeigt wird,
und persistiert Zwischenergebnisse zurück in die Session.

Zwei PHP-Enums in `app/Controller/` modellieren die möglichen Zustände:

```php
enum UrlState: string  { case NotSet; case Valid; case NotWorking; }
enum PostState: string { case NotSet; case Valid; case Problem; }
```

Diese Zustände sind keine klassischen Model-Daten — sie existieren nur
für die Laufzeit eines Request/Response-Zyklus und steuern, welche
Formular-Abschnitte die View anzeigt.

---

### 5. CLI-Schicht außerhalb des MVC-Zyklus

`app/Cli/` enthält Skripte, die per Cron-Job ohne Browser-Request laufen:

```
app/Cli/
├── monitor.php        ← Führt Prüfläufe für alle aktiven Monitore durch
└── migrate_dumps.php  ← Datenmigration
```

Diese Skripte nutzen dieselben Modelle und Services wie die Web-Schicht,
aber keinen Router und keine Controller. Sie sind kein Teil von MVC,
sondern eine eigenständige Ausführungsumgebung für Hintergrundjobs.

---

## Welches Muster steckt tatsächlich dahinter?

### Einordnung

Wenn man die fünf Abweichungen oben zusammenzählt, ergibt sich: Das Projekt folgt **keinem reinen MVC**,
sondern einer Kombination aus zwei etablierten Mustern:

**1. Service-Layer-Pattern** (Fowler, *Patterns of Enterprise Application Architecture*, 2002)  
**2. Action-Domain-Responder (ADR)** (Paul M. Jones, 2014 — als Nachfolger von MVC für HTTP-Anwendungen)

Beide Muster sind keine Erfindungen dieses Projekts, sondern dokumentierte Architekturkonzepte,
die genau die hier gemachten Designentscheidungen beschreiben und begründen.

---

### Action-Domain-Responder (ADR)

ADR ist eine Weiterentwicklung von MVC, die speziell für den Request/Response-Zyklus im Web gedacht ist.
Das klassische MVC stammt aus Desktop-GUI-Anwendungen, in denen eine View ein Model *dauerhaft beobachtet*
und bei Änderungen neu zeichnet (Observer-Pattern). Im Web gibt es diesen Zustand nicht:
jeder HTTP-Request erzeugt eine frische Ausführung, ohne laufende Objekte oder persistente Verbindungen.

ADR benennt die drei Rollen so:

| ADR-Begriff | Was es ist | Im Projekt |
|-------------|-----------|------------|
| **Action**  | Einstiegspunkt für genau einen HTTP-Request | `handle()` in jedem Controller |
| **Domain**  | Gesamte Geschäftslogik, unabhängig von HTTP | `app/Service/` + `app/Model/` |
| **Responder** | Aufbereitung der HTTP-Antwort | `render()` → View-Template |

Der entscheidende Unterschied zu MVC: Der Controller (hier: die Action) ruft **die Domain auf und übergibt das Ergebnis
an den Responder** — er denkt nie über HTTP-Ausgabe nach. Umgekehrt weiß die Domain nichts vom HTTP-Kontext.
Diese Trennung ist hier sauber umgesetzt: Services und Modelle kennen weder `$_GET`, `$_POST` noch HTTP-Header.

```
HTTP-Request
      │
  handle()          ← Action: liest Request-Parameter, delegiert
      │
  Service           ← Domain: Geschäftslogik, DB-Zugriff, reine PHP-Logik
      │
  render()          ← Responder: baut HTML aus Daten, kennt keine Logik
      │
HTTP-Response
```

---

### Service-Layer-Pattern

Das Service-Layer-Pattern (Fowler) beschreibt eine zusätzliche Schicht zwischen Controller und Model,
die die eigentliche Anwendungslogik trägt. Controller werden dadurch zu **dünnen Orchestratoren**:
sie nehmen Eingaben entgegen, rufen den passenden Service auf und leiten das Ergebnis weiter.

Dieses Projekt folgt genau diesem Modell:

- `MonitorAddController.handle()` prüft den Session-Zustand, ruft `UrlCheckService` oder `MonitoringService` auf,
  entscheidet anhand des Rückgabewerts welcher Schritt als nächstes gezeigt wird — aber kein SQL.
- `MonitoringService` enthält den gesamten Ablauf eines Prüflaufs: HTML abrufen, Textausschnitt vergleichen,
  Dump speichern, Benachrichtigung auslösen. Dieser Code ist **sowohl vom Web-Controller als auch vom
  CLI-Skript aus aufrufbar** — weil er nichts über HTTP weiß.

Das ist der zentrale Vorteil des Service-Layer-Patterns in diesem Projekt:
**dieselbe Geschäftslogik funktioniert für Browser-Requests und Cron-Jobs**, ohne Duplikation.

---

### Warum anämische Modelle hier sinnvoll sind

Anämische Modelle (Modelle ohne eigene Datenbanklogik) werden oft als Anti-Pattern bezeichnet —
aber das gilt primär dann, wenn ein ORM wie Doctrine oder Eloquent vorhanden ist, das Active Record
oder Data Mapper vollständig umsetzt. In diesem Projekt gibt es kein ORM.

Die Modelle (`MonitoredPage`, `MonitoringDump`, `User`) sind **typisierte Datenhüllen** (DTOs).
Sie erfüllen genau eine Aufgabe: aus einem rohen Datenbank-Array ein Objekt mit benannten Feldern bauen.
Das verhindert den Zugriff auf `$row['url']` überall im Code und macht Tippfehler zu Compile-Fehlern statt
zu Laufzeit-Bugs.

SQL direkt im Service zu schreiben statt in Methoden wie `MonitoredPage::find()` ist bei einem Projekt
dieser Größe expliziter und einfacher nachzuvollziehen als versteckte ORM-Magic.

---

### Begründung der Gesamtentscheidung

Das Projekt ist **kein Framework-Projekt**, und das ist eine bewusste Entscheidung, die sich in der
Architektur widerspiegelt. Die Konsequenzen:

| Entscheidung | Begründung |
|---|---|
| Kein ORM | Keine externe Abhängigkeit, SQL bleibt sichtbar und prüfbar |
| Handgemachter Router | Vollständige Kontrolle und Transparenz über das URL-Mapping |
| Services statt fetter Controller | Wiederverwendung durch CLI-Schicht, Testbarkeit ohne HTTP-Kontext |
| Anämische Modelle | Einfachheit ohne ORM-Overhead, typisiertes Datenmapping per `fromRow()` |
| Eine `handle()`-Methode pro Controller | Jeder Controller hat genau eine Verantwortung (Single Responsibility) |
| CLI außerhalb des MVC-Zyklus | Hintergrundjobs benötigen keinen Router — sie rufen Services direkt auf |

Das Ergebnis ist kein Lehrbuch-MVC, aber auch kein Chaos. Es ist eine **pragmatische Schichtenarchitektur**,
die für ein PHP-Projekt ohne Framework-Unterstützung gut passt: nachvollziehbar, erweiterbar
und testbar — ohne dass ein DI-Container, ein ORM oder eine Template-Engine nötig wären.

---

## Zusammenfassung: Klassendiagramm (vereinfacht)

```
index.php
    └── router.php
            └── Controller (AbstractController)
                    ├── SessionService          (immer injiziert)
                    ├── DB::getInstance()       (direkte SQL-Abfragen)
                    ├── MonitoringService       (komplexe Logik)
                    │       ├── SelectionSearchService
                    │       └── DB::getInstance()
                    ├── UrlCheckService
                    ├── Model (MonitoredPage, MonitoringDump, …)
                    │       └── fromRow()  ← nur Datenmapping
                    └── render() → View (PHP-Template)
                                        └── layout/header.php
                                        └── layout/footer.php

app/Cli/
    └── monitor.php → MonitoringService → Model → DB
```

| Aspekt                    | Klassisches MVC              | Dieses Projekt                        |
|---------------------------|------------------------------|---------------------------------------|
| Model-Verantwortung       | Daten + Datenbankzugriff     | Nur Daten (DTO + `fromRow`)           |
| Datenbankzugriff          | Im Model (`find`, `save`)    | Im Controller oder Service            |
| Geschäftslogik            | Im Model oder Controller     | In dedizierten Service-Klassen        |
| Routing                   | Framework-Router             | Selbst geschriebene Routing-Tabelle   |
| Dependency Injection      | DI-Container                 | Manuell im Konstruktor                |
| Template-Engine           | Twig, Blade, …               | Natives PHP mit `extract()`           |
| Hintergrundjobs           | Queue/Worker-System          | CLI-Skripte per Cron-Job              |

---

## Quellen und weiterführende Literatur

### MVC — Ursprung und Grundlage

- **Trygve Reenskaug**: *Models-Views-Controllers* (1979), Xerox PARC Technical Note.  
  Das Originaldokument, in dem MVC erstmals beschrieben wurde — für Smalltalk-Desktop-Anwendungen, nicht für das Web.  
  Verfügbar unter: `https://folk.universitetet-i-oslo.no/trygver/1979/mvc-2/1979-12-MVC.pdf`

- **Wikipedia**: *Model–view–controller*  
  Guter Überblick über Varianten und historische Entwicklung des Musters.

---

### Service Layer Pattern

- **Martin Fowler**: *Patterns of Enterprise Application Architecture* (2002), Addison-Wesley.  
  Kapitel "Service Layer" (S. 133–145). Das Standardwerk für Architekturmuster in Unternehmensanwendungen —
  hier wird das Service-Layer-Pattern formal definiert.  
  Online-Katalog: `https://martinfowler.com/eaaCatalog/serviceLayer.html`

---

### Anemic Domain Model

- **Martin Fowler**: *AnemicDomainModel* (2003), Bliki-Artikel.  
  Fowler beschreibt das Anämische Domänenmodell zunächst als Anti-Pattern — erklärt aber auch,
  unter welchen Bedingungen es akzeptabel oder bewusst gewählt ist.  
  `https://martinfowler.com/bliki/AnemicDomainModel.html`

---

### Data Transfer Object (DTO)

- **Martin Fowler**: *Patterns of Enterprise Application Architecture* (2002).  
  Kapitel "Data Transfer Object" — beschreibt genau das Muster, das `fromRow()` hier umsetzt:
  ein Objekt, das nur Daten transportiert und keine Geschäftslogik enthält.  
  `https://martinfowler.com/eaaCatalog/dataTransferObject.html`

---

### Action-Domain-Responder (ADR)

- **Paul M. Jones**: *Action Domain Responder: A Refinement of MVC* (2014), Blogartikel und Proposal.  
  Der ursprüngliche Text, der ADR als Weiterentwicklung von MVC für HTTP-Anwendungen definiert.  
  `https://pmjones.io/adr`

- **Paul M. Jones**: *ADR by Example* (2019), GitHub-Repository mit kommentierten Implementierungsbeispielen.  
  `https://github.com/pmjones/adr-byexample`

---

### Single Responsibility Principle (SRP)

- **Robert C. Martin**: *Clean Code: A Handbook of Agile Software Craftsmanship* (2008), Prentice Hall.  
  Kapitel 10: "Classes" — beschreibt SRP als Grundlage für den Ein-`handle()`-pro-Controller-Ansatz.

- **Robert C. Martin**: *Agile Software Development, Principles, Patterns, and Practices* (2002).  
  Hier wird SRP als eines der SOLID-Prinzipien formal eingeführt.
