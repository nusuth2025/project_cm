<?php
declare(strict_types=1);

// Migriert bestehende dump/*.txt Dateien in die monitoring_dumps-Tabelle
// Anwendung: php app/Cli/migrate_dumps.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only' . PHP_EOL);
}

require_once dirname(__DIR__, 2) . '/app/config.php';

use App\Model\DB;

$dumpDir = BASE_PATH . '/app/dump/';

// Mapping: monitoring_dumps.id → Dateinamen
$fileMap = [
    1 => ['tmp' => 'monitor1779960746tmp.txt', 'check' => 'monitor1779960746tmp_Check.txt'],
    2 => ['tmp' => 'monitor1779963854tmp.txt', 'check' => 'monitor1779963854tmp_Check.txt'],
];

$db = DB::getInstance();

foreach ($fileMap as $dumpId => $files) {
    $tmpPath = $dumpDir . $files['tmp'];
    if (!file_exists($tmpPath)) {
        echo "Datei nicht gefunden: {$tmpPath} — übersprungen." . PHP_EOL;
        continue;
    }

    $raw = file_get_contents($tmpPath);

    // HTTP-Header herausfiltern: alles vor dem ersten doppelten CRLF
    // Bug-Fix: die alten Dumps wurden mit CURLOPT_HEADER=true geschrieben
    $headerEnd = strpos($raw, "\r\n\r\n");
    $html      = $headerEnd !== false ? substr($raw, $headerEnd + 4) : $raw;

    $checkedContent = null;
    $checkPath      = $dumpDir . $files['check'];
    if (file_exists($checkPath)) {
        $rawCheck = file_get_contents($checkPath);
        $checkedContent = unserialize($rawCheck);
        if ($checkedContent === false) {
            $checkedContent = $rawCheck; // Fallback falls nicht serialisiert
        }
    }

    $stmt = $db->prepare(
        'UPDATE monitoring_dumps SET html_content = ?, checked_content = ? WHERE id = ?'
    );
    $stmt->execute([$html, $checkedContent, $dumpId]);

    echo "Dump #{$dumpId} aktualisiert ("
        . round(strlen($html) / 1024, 1) . " KB HTML"
        . ($checkedContent !== null ? ', mit marked content' : '')
        . ').' . PHP_EOL;
}

echo 'Migration abgeschlossen.' . PHP_EOL;
