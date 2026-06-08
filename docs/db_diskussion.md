# Datenbankdiskussion: MariaDB (relational) vs. NoSQL

Analyse auf Basis des aktuellen Schemas mit drei Tabellen:
`users` · `monitored_pages` · `monitoring_dumps`

---

## Aktuelles Datenmodell auf einen Blick

```
users (1) ──< monitored_pages (1) ──< monitoring_dumps (n)
```

- **users**: feste Felder, klare Identität (id, email, password_hash)
- **monitored_pages**: feste Konfiguration pro Monitor (URL, Intervall, Startzeit, Auswahl-Texte, Status, Zähler)
- **monitoring_dumps**: wachsende Zeitreihe, pro Eintrag großer HTML-Blob + extrahierter Inhalt + Änderungs-Flag

---

## Pro: MariaDB behalten (relationale Datenbank)

**Datenintegrität durch Fremdschlüssel**
`ON DELETE CASCADE` stellt sicher, dass beim Löschen eines Monitors automatisch
alle zugehörigen Dumps verschwinden. In den meisten NoSQL-Datenbanken muss das
die Anwendung selbst erledigen — eine häufige Fehlerquelle.

**Schema passt zur Struktur der Daten**
Die drei Tabellen haben stabile, klar definierte Felder. Es gibt keine variablen
Attribute, keine unbekannte Tiefenverschachtelung. Relationale Tabellen sind
genau dafür gebaut.

**Einfache Abfragen, die SQL bereits kann**
`MAX(found_at)`, `COUNT(*)`, `DATE_ADD(last_checked_at, INTERVAL ... MINUTE) <= NOW()`
— das alles ist Standard-SQL, eine Zeile, kein Code. In dokumentenorientierten
Datenbanken (MongoDB, CouchDB) wären das Aggregations-Pipelines oder mehrere
Roundtrips.

**ACID-Garantien**
Beim Schreiben eines Dumps und dem gleichzeitigen Update von `last_checked_at`
und `check_count` auf `monitored_pages` stellt InnoDB sicher, dass entweder
alles oder nichts landet. NoSQL-Systeme bieten das meist nur innerhalb eines
einzelnen Dokuments.

**Kein Betriebsaufwand**
MariaDB läuft bereits, ist in PHP trivial zu nutzen (PDO) und hat keinerlei
zusätzliche Infrastruktur-Anforderungen. MongoDB oder Cassandra benötigen
eigene Server, Treiber und Monitoring.

**Bewährter Stack für kleine bis mittlere Last**
Der Monitor prüft URLs im Minuten-/Stunden-Takt. MariaDB verkraftet tausende
Monitore problemlos. Ein NoSQL-System würde hier keinen Geschwindigkeitsvorteil
bringen.

---

## Contra: MariaDB behalten / Pro NoSQL

**`html_content` (LONGTEXT) als Blob in einer Zeile ist suboptimal**
Das vollständige HTML jeder Änderung in einer relationalen Zeile zu speichern
ist kein klassischer Anwendungsfall für SQL. Dokumentdatenbanken oder
Object-Stores (z. B. S3-kompatibel) sind für große, unstrukturierte Textmengen
geeigneter — sie komprimieren und paginieren solche Inhalte besser.

**Schemamigrationen kosten Aufwand**
Jede neue Spalte erfordert ein `ALTER TABLE` und eine Migrationsdatei.
In einer dokumentenorientierten DB (MongoDB, CouchDB) kann man einfach neue
Felder in neue Dokumente schreiben, alte Dokumente bleiben wie sie sind.
Das wurde im Projektverlauf bereits mehrfach spürbar (fünf Migrationsdateien).

**Zeitreihendaten wachsen unbegrenzt**
`monitoring_dumps` ist eine append-only Zeitreihe — genau der Typ, für den
spezialisierte Zeitreihendatenbanken (InfluxDB, TimescaleDB) oder
dokumentenorientierte Stores effizienter sind, wenn die Datenmenge sehr groß wird
(Millionen Einträge, lange Historien).

**Wenig von SQL wird wirklich genutzt**
Joins werden kaum verwendet — die Abfragen lesen meist nur eine Tabelle.
Der Vorteil von SQL (komplexe Joins, Aggregationen über viele Tabellen)
kommt hier kaum zum Tragen.

---

## Welche NoSQL-Variante käme theoretisch infrage?

| Typ | Kandidat | Passt weil... | Passt nicht weil... |
|---|---|---|---|
| Dokumentenorientiert | MongoDB | Dumps als Dokument mit eingebettetem HTML natürlich | Relationen zwischen User → Monitor → Dump manuell |
| Zeitreihe | InfluxDB / TimescaleDB | Dumps sind Messwerte mit Timestamp | Konfiguration (monitored_pages) passt nicht ins Zeitreihen-Modell |
| Key-Value | Redis | Schnelle Abfrage „nächste fällige Monitore" | Kein persistenter Speicher für Dumps geeignet |
| Column-Family | Cassandra | Sehr gut für append-only Zeitreihen mit hohem Volumen | Massiver Overhead für diesen Anwendungsfall |

---

## Fazit

**MariaDB ist für dieses Projekt die richtige Wahl** — und das wird sich in
absehbarer Zeit nicht ändern.

Der einzige echte Schwachpunkt ist das Speichern großer HTML-Blobs in LONGTEXT.
Wer das optimieren möchte, sollte nicht zur NoSQL wechseln, sondern die
HTML-Inhalte aus der Datenbank auslagern (Dateisystem oder Object-Store) und
in der Datenbank nur Metadaten + Pfad/Hash behalten. Das lässt sich mit dem
bestehenden relationalen Schema problemlos kombinieren.

Eine Migration zu NoSQL würde die vorhandenen Stärken (Integrität, einfache
Abfragen, kein Betriebsaufwand) aufgeben, ohne einen messbaren Vorteil zu
gewinnen.
