<?php
// Optimierungstest---------------------------------------
class PostSelection extends Post
{
    protected string $defaultFormBox = "surrounding text ... text id'like to monitor ... surrounding text";
    public string $checkedSelectionIn = "";
    public string  $check = "wider_select";
    public string $formAnswerSelection = "";
    public string $selectionState = "";
    public ProcessSelection $processSelection;
    public array $arrstrpos = [];
    public array $singleFalseWords = [];


    public function checkSelection()
    {
        $this->checkPost($this->check, $this->defaultFormBox);

        switch ($this->postSet) {
            case "Post NOT set":
                $this->formAnswerSelection = $this->defaultFormBox;
                $this->selectionState = "NOT set";
                break;
            case "Post isset":
                // hier noch prüfen ob Session url gesetzt ist
                $this->processSelection = new ProcessSelection();
                if ($this->checkIfWorkingSelection($this->postIn)) {
                    $this->checkedSelectionIn = $this->postIn;
                    if (!empty($this->singleFalseWords)) {
                        $this->formAnswerSelection = "";
                        $this->selectionState = "PROBLEM";
                        // $this->updateFormAnswerBlockData();
                    } else {
                        $this->formAnswerSelection = $this->formBox;
                        $this->selectionState = "isset";
                        SessionObject::setSessionSelection($this->checkedSelectionIn);
                    }
                    
                } else {
                    $this->selectionState = "selection not working";
                };
                $this->updateFormAnswerBlockData();
        }
        // $this->setFormBoxUrl();
    }

    private function checkIfWorkingSelection(String $selection)
    {
        $tmpName = $_SESSION["S_ID"] . "tmp";
        $fp2 = file_get_contents(__DIR__ . "/../arrivals/" . $tmpName . ".txt");
        $tmp_arrstrpos = [];
        $tmp_truth_arr = [];


        $wider_str_replace = str_replace(["\n", " ", "\0", "\t", "\x0B", "\r"], " ", $selection);
        $wider_str_trim = trim($wider_str_replace);
        $wider_str_trim_arr = explode(" ", $wider_str_trim); // zerlegt den String in eine Array aus einzelnen Worten
        // leere Elemente aus dem Array entfernen
        $tmp = [];
        foreach ($wider_str_trim_arr as $key => $value) {
            if ($value !== "") {
                $tmp[] = $value;
            }
        }
        $wider_str_trim_arr = $tmp;

        var_dump($selection, $wider_str_trim_arr);

        $wider_str_trim_arr_asstring = ">" . implode("|", $wider_str_trim_arr) . "<"; // nur für den Test - gibt den Array als string aus

        $first_pos = strpos($fp2, $selection) != false ? strpos($fp2, $selection) : 0; // falls erste Positon false, dann 0

        $all_nearest = false;
        $break = false;
        while ($all_nearest === false) {

            $single_nearest = false;
            $tmp_truth_arr = array_fill(0, count($wider_str_trim_arr), false);


            for ($x = 0; $x < count($wider_str_trim_arr); $x++) {
                // die Endposition des voherigen Wortes (falls vohanden) wird für die aktuelle Startposition genutzt
                $previouspos = $x == 0 ? $first_pos : $previouspos;

                $strpos = strpos($fp2, $wider_str_trim_arr[$x], $previouspos);
                // wenn das letzte Wort noch nicht erreicht ist
                if ($x < count($wider_str_trim_arr) - 1) {
                    // solange dieses Wort erneut vorkommt, bevor die nächste Position erreicht ist, wird dieses als neue Position angenommen
                    while (true) {
                        // das gleiche Wort nochmal danach?
                        $nextsamepos = strpos($fp2, $wider_str_trim_arr[$x], $strpos + 1);

                        // die Position des nächsten Wortes
                        $next_x = strpos($fp2, $wider_str_trim_arr[$x + 1], $strpos + 1);
                        // wenn das nächste Vorkommen des Wortes noch vor dem nächsten Wort liegt, wird diese Position übernommen
                        if ($nextsamepos !== false && $nextsamepos < $next_x && $next_x !== false) {
                            $strpos = $nextsamepos;
                        } else {
                            break;
                        }
                    }
                }

                $strpos_end = $strpos + strlen($wider_str_trim_arr[$x]);
                $previouspos = $strpos_end;
                $tmp_arrstrpos[] = $strpos; //Anfangsposition wird in Array geschrieben
                $tmp_arrstrpos[] = $strpos_end; // Endposition wird in Array geschrieben
                // der Array besteht also aus einer Abfolge von Anfangs und Endpostionen für genau diese Wortfolge
                // aber die Positionen sind nicht zwingend diejenigen die am dichtesten zusammen liegen, daher ein weiterer Durchlauf, auf Basis dieses Arrays

                if (in_array(false, $tmp_arrstrpos)) {
                    // wenn eines der Worte false ergibt .. wird das Problem an den User gereicht
                    $this->notFound($tmp_arrstrpos, $wider_str_trim_arr, $wider_str_trim);
                    var_dump($this->singleFalseWords);
                    // $break = true;
                    break 2; // Abbruch der gesamten while-Schleife
                }
            }
            // für den Test ---
            $tmp_arrstrpos_1 = $tmp_arrstrpos;
            // -----------------
            // ------- zweite Runde -------------------------------------------------------------------------------------------------
            var_dump($first_pos, $tmp_arrstrpos, $tmp_truth_arr, count($wider_str_trim_arr));

            while (in_array(false, $tmp_truth_arr)) {
                for ($x = 0; $x < count($wider_str_trim_arr); $x++) {

                    $previouspos = $x == 0 ? $tmp_arrstrpos[0] : $previouspos;
                    // die Position für das folgende Wort wie sie im Array steht
                    $nextpos = $x < count($wider_str_trim_arr) - 1 ? $tmp_arrstrpos[(($x + 1) * 2)] : $tmp_arrstrpos[count($tmp_arrstrpos) - 2];

                    $strpos = strpos($fp2, $wider_str_trim_arr[$x], $previouspos);

                    // wenn das letzte Wort noch nicht erreicht ist
                    if ($x < count($wider_str_trim_arr) - 1) {
                        // die Position des nächsten Wortes, wie sie ermittelt wird
                        $next_x = strpos($fp2, $wider_str_trim_arr[$x + 1], $strpos + 1);
                        // solange dieses Wort erneut vorkommt, bevor die nächste Position erreicht ist, wird dieses als neue Position angenommen
                        while (true) {
                            // das gleiche Wort nochmal danach?
                            $nextsamepos = strpos($fp2, $wider_str_trim_arr[$x], $strpos + 1);

                            // für den Test ----------
                            echo "<br> next_x vs nextpos: " . $next_x . " vs " . $nextpos . " on x: " . $x . " Wort: " . $wider_str_trim_arr[$x] . "\n";
                            // --------------------------
                            // ist die ermittelte Position des nächsten Wortes kleiner als diejenige aus dem Array, wird die Position des aktuellen Wortes auf Basis der ermittelten Position gesucht
                            if ($next_x < $nextpos) {

                                $next_x++;
                                $nextsamepos = strpos($fp2, $wider_str_trim_arr[$x], $next_x);
                                // Anpassung von next_x für die if-Abfrage unten
                                $next_x = $nextpos;
                                $single_nearest = false;
                                $tmp_truth_arr[$x] = $single_nearest;
                                if ($x > 0 && $x < count($wider_str_trim_arr) - 1 && $nextsamepos !== false) {
                                    $tmp_truth_arr[$x - 1] = $single_nearest;
                                }
                            }

                            if ($nextsamepos !== false && $nextsamepos < $next_x && $next_x !== false) {
                                $single_nearest = false;
                                $tmp_truth_arr[$x] = $single_nearest;
                                $strpos = $nextsamepos;
                                // für den Test ----------
                                echo "<br> ----------- next_x: " . $next_x . " nextsamepos: " . $nextsamepos . " on x: " . $x . " Wort: " . $wider_str_trim_arr[$x] . "\n";
                                // --------------------------
                            } else {
                                $single_nearest = true;
                                // ist der Array voller true's ist die Schleife fertig
                                $tmp_truth_arr[$x] = $single_nearest;
                                // für den Test ----------
                                echo implode("|", $tmp_truth_arr) . " on x: " . $x . " Wort: " . $wider_str_trim_arr[$x] . "\n";

                                break;
                            }
                        }
                    } elseif ($x == count($wider_str_trim_arr) - 1) {
                        $tmp_truth_arr[$x] = true;
                    }

                    $strpos_end = $strpos + strlen($wider_str_trim_arr[$x]);
                    $previouspos = $strpos_end;
                    $tmp_arrstrpos[($x * 2)] = $strpos; //Anfangsposition wird in Array geschrieben
                    $tmp_arrstrpos[($x * 2) + 1] = $strpos_end; // Endposition wird in Array geschrieben
                    // der Array besteht also aus einer Abfolge von Anfangs und Endpostionen für genau diese Wortfolge
                }
            }

            // ----zweiter Durchlauf fertig------------------------------------------------------

            if (!(in_array(false, $tmp_truth_arr))) {
                $all_nearest = true;
            }
        }
        $this->arrstrpos = $tmp_arrstrpos;
        
        // für den Test ---
        $test = implode('|', $this->arrstrpos);
        $test2_2 = isset($tmp_arrstrpos) ? implode('|', $tmp_arrstrpos) : 'no $test2_2';
        $test2_1 =  isset($tmp_arrstrpos_1) ? implode('|', $tmp_arrstrpos_1) : 'no $test2_1';

        var_dump($selection, $first_pos, $wider_str_replace, $wider_str_trim_arr_asstring, $test, $test2_1, $test2_2, $tmp_truth_arr);
        // Aufruf der Testfunktion siehe unten
        $this->showSelectionInTmp();
        // das ist noch kein sinvolles Return muss ich noch anpassen
        return true;
    }
    // ------------ muss das viell über update aus formAnswerBlockData laufen --------------------------------
    private function notFound(array $tmp_arrstrpos, array $wider_str_trim_arr, String $wider_str_trim)
    {

        // ein Wort ergibt false, dann soll User prüfen und korrigieren - dazu der singleFalseWords[] der in selctionFormElement.inc.php ausgewertet wird
        foreach ($tmp_arrstrpos as $key => $value) {
            if ($value === false) {
                $keyinwordarr = ($key - ($key % 2)) / 2;
                $this->singleFalseWords[] = [$keyinwordarr, $wider_str_trim_arr[$keyinwordarr], $wider_str_trim];
            }
        }
        // $formSelection->selectionFormElement(?,?, $this->singleFalseWords);
    }

    // -------------- das ist eine reine Testfunktion die eine Datei erzeugt in der man die gefundene Auswahl überprüfen kann - gekennzeichnet durch |#|...|##|
    public function showSelectionInTmp()
    {
        // Erzeugen des Namens der temporären Datei, wie das auch processSelection macht
        // und Schreiben des Inhalts in $markedTemp 
        $tmpName = $_SESSION["S_ID"] . "tmp";
        $fp2 = file_get_contents(__DIR__ . "/../arrivals/" . $tmpName . ".txt");
        $markedTemp = $fp2;

        // Aufbau eines neuen Strings mit Markierungen, um die Worte aus dem arrstrpos[]-Positionsarray
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
        $ft = fopen(__DIR__ . "/../arrivals/" . $tmpName . ".txt", "w");
        fwrite($ft, serialize($markedTemp2));
        fclose($ft);
    }
}
