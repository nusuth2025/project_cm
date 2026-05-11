<?php
class PostSelection extends Post
{
    protected string $defaultFormBox = "surrounding text ... text id'like to monitor ... surrounding text";
    public string $checkedSelectionIn = "";
    public string  $check = "wider_select";
    public string $formAnswerSelection = "";
    public string $selectionState = "";
    public ProcessSelection $processSelection;
    public array $arrstrpos = [];

    // ab hier noch baustelle--------------------------
    public function checkSelection()
    {
        $this->checkPost($this->check, $this->defaultFormBox);

        switch ($this->postSet) {
            case "Post NOT set":
                $this->formAnswerSelection = $this->defaultFormBox;
                $this->selectionState = "NOT set";
                break;
            case "Post isset":
                $this->processSelection = new ProcessSelection();
                if ($this->checkIfWorkingSelection($this->postIn)) {
                    $this->checkedSelectionIn = $this->postIn;
                    $this->formAnswerSelection = $this->formBox; // hier sollte vielleicht lieber die erfolgreiche Auswahl wieder in die Box geschrieben werden als bzw. stehen bleiben, dafür müsste man aber die Methode aus dem Parent.post überschreiben - aber der AnswerBlock löst das viell. besser
                    $this->selectionState = "isset";
                    SessionObject::setSessionSelection($this->checkedSelectionIn);
                } else {
                    $this->selectionState = "selection not working";
                };
        }
        // $this->setFormBoxUrl();
    }
    // diese Funktion sollte vielleicht versuchen die Selection zu finden - dazu muss vorher die Seite in txt geschrieben werden
    // Url der Seite aus der Session holen, denke ich
    private function checkIfWorkingSelection(String $selection)
    {
        $tmpName = $_SESSION["S_ID"] . "tmp";
        $fp2 = file_get_contents(__dir__ . "/../arrivals/" . $tmpName . ".txt");

        // $fp2 = file_get_contents("arrivals/curlout.txt");
        //$wider_str_trim = trim($wider_string); // entfernt Zeilenumbrüche + erste und letzte Leerzeichen

        // --------------- versuch die Fehlauswahl zu korrigieren
        // while (strpos($fp2, $selection) === false) {
        //     // $selection = substr($selection, 1); // entfernt das erste Zeichen des Strings

        // }
        // -----------------
        $wider_str_replace = str_replace(["\n", " ", "\0", "\t", "\x0B", "\r"], " ", $selection);
        $wider_str_trim = trim($wider_str_replace);
        $wider_str_trim_arr = explode(" ", $wider_str_trim); // zerlegt den String in eine Array aus einzelnen Worten
        // $wider_str_trim_arr = $wider_str_trim;
        $wider_str_trim_arr_asstring = ">" . implode("|", $wider_str_trim_arr) . "<"; // nur für den Test - gibt den Array als string aus
        // $test2 = gettype($wider_str_trim_arr[1]);
        // $first_pos = strpos($wider_string, $wider_str_trim_arr[0]);
        // $last_pos = strrpos($wider_string, $wider_str_trim_arr[0]);
        //$first_pos = strpos($fp2, $wider_str_trim_arr[0]); // Postion des ersten Buchtaben des ersten Arrayelements im gegebenen Text
        //$first_pos = strpos($fp2, $selection); //----------
        //----------
        // while (strpos($fp2, $selection) === false) {
        //     $selection = substr($selection, 1); // entfernt das erste Zeichen des Strings
        // }
        $first_pos = strpos($fp2, $selection);
        //----------------

        //$last_pos = strpos($fp2, $selection); // Postion des ersten Buchtaben des letzten Arrayelements im gegebenen Text

        // $end_pos = $last_pos + strlen($wider_str_trim_arr[count($wider_str_trim_arr) - 1]); // Postion des letzten Buchtaben des letzten Arrayelements im gegebenen Text
        $end_pos = $first_pos + strlen($selection); //----------------
        $string_part = htmlspecialchars(substr($fp2, $first_pos, ($end_pos - $first_pos)));

        // foreach( $wider_str_trim_arr as $str){
        //     $start_pos = strpos($fp2, $str);
        // }

        $start_pos2 = 0;
        $end_pos2 = 0;
        // $arrstrpos = [];
        $y = 0;

        // while ($start_pos2 <= $end_pos2) {
        for ($x = 0; $x < count($wider_str_trim_arr); $x++) {
            if ($wider_str_trim_arr[$x] == "") {
                continue;
            }
            $previouspos = $x == 0 ? $first_pos : $previouspos;

            $strpos = strpos($fp2, $wider_str_trim_arr[$x], $previouspos);
            // wenn das letzte Wort noch nicht erreicht ist
            if ($x < count($wider_str_trim_arr) - 1) {
                // solange dieses Wort erneut vorkommt, bevor die nächste Position erreicht ist, wird dieses als neue Position angenommen
                while (true) {
                    // das gleiche Wort nochmal danach?
                    $nextsamepos = strpos($fp2, $wider_str_trim_arr[$x], $strpos + 1);
                    // ist es ein Leerzeichen wird es übersprungen
                    if ($wider_str_trim_arr[$x] == "") {
                        continue;
                    }
                    // die Position des nächsten Wortes
                    $next_x = strpos($fp2, $wider_str_trim_arr[$x + 1], $strpos + 1);
                    if ($nextsamepos !== false && $nextsamepos < $next_x && $next_x !== false) {
                        $strpos = $nextsamepos;
                    } else {
                        break;
                    }
                }
            }

            $strpos_end = $strpos + strlen($wider_str_trim_arr[$x]);
            $previouspos = $strpos_end;
            $this->arrstrpos[] = $strpos; //Anfangsposition wird in Array geschrieben
            $this->arrstrpos[] = $strpos_end; // Endposition wird in Array geschrieben
            // der Array besteht also aus einer Abfolge von Anfangs und Endpostionen für genau diese Wortfolge
        }
        // }
        $test = implode('|', $this->arrstrpos);
        // $test2 = $arrstrpos[count($arrstrpos) - 1];
        // $test = implode('|',array_keys($arrstrpos));
        // $string_part2 = htmlspecialchars(substr($fp2, $arrstrpos[0], $arrstrpos[count($arrstrpos) - 1] - $arrstrpos[0]));
        // --------------- nur für Test ------------
        var_dump($selection, $first_pos, $end_pos, $wider_str_replace, $wider_str_trim_arr_asstring, $string_part, $test);
        $this->showSelectionInTmp();
        return true;
    }
// ----------------- das ist eine reine Testfunktion die eine Datei erzeugt in der man die gefundene Auswahl überprüfen kann - gekennzeichnet durch |#|...|##|
    public function showSelectionInTmp()
    {
        // Erzeugen des Namens der temporären Datei, wie das auch processSelection macht
        // und Schreiben des Inhalts in $markedTemp 
        $tmpName = $_SESSION["S_ID"] . "tmp";
        $fp2 = file_get_contents(__dir__ . "/../arrivals/" . $tmpName . ".txt");
        $markedTemp = $fp2;

        // Aufbau eines neuen Strings mit Markierungen um die Worte aus dem arrstrpos[]-Positionsarray
        $markedTemp2 = substr($markedTemp, 0, $this->arrstrpos[0]);
        for ($x = 0; $x < count($this->arrstrpos); $x += 2) {
            $start = $this->arrstrpos[$x];
            $end = $this->arrstrpos[$x + 1];
            $between = $x > 0 && $start > $this->arrstrpos[$x - 1] + 1 ? substr($markedTemp, $this->arrstrpos[$x - 1] + 1, $start - ($this->arrstrpos[$x - 1] + 1)) : "";
            $markedTemp2 .= $between;
            $markedTemp2 .= "|#|" . substr($markedTemp,  $start, $end - $start) . "|##|";
            // der String geht nur bis zum letzen Wort, danach fertig
        }
        // Erzeugen einer ...tmp_Check.txt in die der markierte String geschrieben wird
        $tmpName = $_SESSION["S_ID"] . "tmp_Check";
        $ft = fopen(__dir__ . "/../arrivals/" . $tmpName . ".txt", "w");
        fwrite($ft, serialize($markedTemp2));
        fclose($ft);
    }
}
