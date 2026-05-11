<?php
// der Name ist noch nicht gut
// darf nur etwas machen wenn die Url passt
// sollte die Url als Clickable zur Verfügung stellen oder einen Text generieren der sagt was zu tun ist
class ProcessSelection
{
public function __construct()
{
    $this->processSelection($_SESSION['S_URL']);
}

    public function processSelection(string $url)
    {
        // $url = $_SESSION['S_URL'];
        $tmpName = $_SESSION["S_ID"] . "tmp";
        $fp = fopen(__dir__ . "/../arrivals/" . $tmpName . ".txt", "w");

        // auslesen der url -------------------------
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, true); // Ausgabe mit Http-Header (also das was auch get_headers() ausgibt)
        // brauche ich den Header??
        //curl_setopt($curl, CURLOPT_RETURNTRANSFER, false); //der Transfer wird als Zeichenkette zurückgegeben anstatt direkt in den STDOUT zu gehen
        curl_setopt($curl, CURLOPT_HTTP_CONTENT_DECODING, false);

        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        // Anweisung zum schreiben des html der url in den stream
        curl_setopt($curl, CURLOPT_FILE, $fp);
        // Ausführung des Auslesen
        $curlout = curl_exec($curl);
        // stream schließen
        fclose($fp);
    }
}
