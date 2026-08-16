# Design Patterns im Projekt

Neben dem übergeordneten **MVC-Muster** (Model-View-Controller) kommen im Projekt
folgende weitere Entwurfsmuster zum Einsatz.

---

## 1. Singleton
*Stellt sicher, dass von einer Klasse genau eine Instanz existiert, und bietet
einen globalen Zugriffspunkt auf diese Instanz.*

`DB::getInstance()` verwaltet die PDO-Datenbankverbindung als Singleton. Der
Konstruktor ist privat; wiederholte Aufrufe von `getInstance()` geben immer
dieselbe Verbindung zurück. So wird verhindert, dass pro Request mehrere
Datenbankverbindungen geöffnet werden.

```php
// app/Model/DB.php

private static ?\PDO $instance = null;

public static function getInstance(): \PDO
{
    if (self::$instance === null) { ... }
    return self::$instance;
}

```

---

## 2. Front Controller
*Alle eingehenden Anfragen laufen durch einen einzigen zentralen Einstiegspunkt,
der dann die Weiterverarbeitung steuert.*

`index.php` startet die Session und lädt die Konfiguration. `router.php` wertet
die URI aus, wählt den zuständigen Controller und instanziiert ihn. Kein
Controller ist direkt über eine URL erreichbar — der gesamte Routing-Mechanismus
ist an einer Stelle konzentriert.

```
Browser → index.php → router.php → Controller::handle()
```

---

## 3. Template Method
*Eine abstrakte Basisklasse definiert das Skelett eines Algorithmus; konkrete
Unterklassen füllen die variablen Schritte aus, ohne die Grundstruktur zu ändern.*

`AbstractController` stellt wiederverwendbare Hilfsmethoden (`render()`,
`redirect()`, `requireLogin()`) bereit und deklariert `handle()` als abstrakte
Methode. Jeder konkrete Controller (z. B. `MonitorAddController`) implementiert
ausschließlich `handle()` und erbt das Rest-Verhalten.

```php
// app/Controller/AbstractController.php
abstract public function handle(): void;

protected function render(string $view, array $data = []): void { ... }
protected function redirect(string $url): never { ... }
protected function requireLogin(): void { ... }
```

---

## 4. Value Object
*Ein Objekt ohne eigene Identität, das ausschließlich über seinen Wert definiert
ist. Typischerweise unveränderlich (immutable).*

`UrlCheckResult` ist als `final` deklariert und besitzt ausschließlich
`readonly`-Properties, die im Konstruktor gesetzt werden. Das Objekt hat keine
ID und keinen veränderbaren Zustand — zwei Instanzen mit denselben Werten sind
semantisch identisch.

```php
// app/Service/UrlCheckResult.php
final class UrlCheckResult
{
    public function __construct(
        public readonly bool   $isUsable,
        public readonly int    $statusCode,
        public readonly string $effectiveUrl,
        public readonly string $message,
        public readonly string $severity,
    ) {}
}
```

---

## 5. Chain of Responsibility
*Eine Kette von Handlern bearbeitet eine Anfrage nacheinander. Jeder Handler
entscheidet, ob er die Anfrage selbst behandelt oder an den nächsten weitergibt.*

In `MonitoringService::runCheck()` wird die Feinauswahl über eine mehrstufige
Fallback-Kette ermittelt. Jede Stufe greift nur dann ein, wenn die vorherige
gescheitert ist:

```
1. findInnerInOuterPositions   — exakte Wortfolge in den Außen-Positionen
        ↓ nicht gefunden
2. findPositions($outerText)   — direkte Suche im bereinigten Klartext
        ↓ nicht gefunden
3. extractCurrentValue()       — Mustererkennung (z. B. \d+,\d+ für Preise)
        ↓ nicht gefunden
4. extractByStoredOffsets()    — gespeicherte relative Position ± 5 Zeichen
        ↓ nicht gefunden
5. mb_substr($outerText, 0, 500) — letzter Ausweg: ganzer Umfeld-Bereich
```

---

## 6. Dependency Injection (Constructor Injection)
*Abhängigkeiten werden einer Klasse von außen übergeben, statt dass sie diese
selbst erzeugt. Das entkoppelt Klassen voneinander und erleichtert das Testen.*

`MonitoringService` erhält `SelectionSearchService` über den Konstruktor.
Dadurch kann im Unit-Test eine andere Implementierung injiziert werden, ohne
die Klasse selbst zu verändern. Dasselbe Prinzip gilt für `AbstractController`
(erhält `SessionService`) und alle konkreten Controller.

```php
// app/Service/MonitoringService.php
public function __construct(
    private readonly SelectionSearchService $searchService
) {}
```

---

## 7. Data Mapper
*Bildet Datenbankzeilen auf Objekte ab, ohne dass die Objekte selbst SQL kennen.
Trennt die Domänenlogik von der Datenbankstruktur.*

Alle Model-Klassen (`MonitoredPage`, `MonitoringDump`, `User`) besitzen eine
statische `fromRow(array $row): self`-Methode, die einen assoziativen Array
(PDO-Ergebnis) in ein typisiertes Objekt überführt. Die Klassen enthalten kein
SQL — das verbleibt in den Controllern und Services.

```php
// app/Model/MonitoredPage.php
public static function fromRow(array $row): self
{
    $page = new self();
    $page->id  = (int) $row['id'];
    $page->url = $row['url'];
    // ...
    return $page;
}
```

---

## 8. Facade
*Bietet eine vereinfachte Schnittstelle zu einem komplexen Subsystem und
verbirgt dessen interne Abläufe vor dem Aufrufer.*

`MonitoringService` kapselt den gesamten Ablauf eines Prüflaufs hinter einer
einzigen Methode `runCheck()`. Der Aufrufer (z. B. `monitor.php`) muss nicht
wissen, wie HTML abgerufen, Positionen gesucht, Inhalte verglichen oder Dumps
gespeichert werden — er ruft nur `runCheck($page)` auf und erhält ein
`MonitoringDump`-Objekt zurück.

```php
// Aufrufer in app/Cli/monitor.php
$dump = $monitoringService->runCheck($page);
```
