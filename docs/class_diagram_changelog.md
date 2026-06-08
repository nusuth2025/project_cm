# Änderungen am Klassendiagramm

| Was | Vorher | Jetzt |
|---|---|---|
| `MonitoredPage` | `intervalLabel/Days/Hours()` | entfernt |
| `UrlCheckService` | 4 Methoden | nur noch `check()` |
| `SelectionSearchService` | `buildMarkedContent()` | entfernt |
| `UrlState` Enum-Werte | `Empty / Error` | `NotSet / NotWorking` |
| `PostState` Enum-Werte | `None / Error` | `NotSet / Problem` |
| Neue Controller | fehlten | `UserSettingsController` + `MonitorDumpDeleteController` mit Vererbungspfeilen |
| `SessionService` | `ensureSessionId()` + `getSessionId()` | entfernt (S_ID war Überbleibsel aus altem Datei-System) |
| `MonitoringService` | `getPreviousDump()` + `hasChanged()` public | auf `private` geändert (nur intern genutzt) |
