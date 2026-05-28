<?php
// we're in ignorethis

class FormAnswerBlockData
{
    public string $trackedUrl = "";
    public string $trackedSelection = "";
    public string $trackedInnerSelection = "";
    public static PostUrl $postUrl;
    public static PostSelection $postSelection;
    // protected GivenUrl $givenUrl;
    // protected GivenSelection $givenSelection;

    // public function __construct()
    //     {
    //         // $this->givenUrl = $givenUrl;
    //         // $this->givenSelection = $givenSelection;    
    //         // $this->update();
    //     }

    public function update(Post $me) // so richtig gut find ich das noch nicht, hier alles mit übergeben zu müssen
    {
        // $givenUrl = $this->givenUrl;
        // $givenSelection = $this->givenSelection;
        echo "me----<br>"; 
        var_dump($me);
        // var_dump(self::$postUrl);
$me_class = get_class($me);
        switch ($me_class) {
            case "PostUrl": 
                // --- scheinbar liegt das Problem hier ... aber warum??
                // $tmp = empty(self::$postUrl) ? $me : self::$postUrl;
                // var_dump($tmp);
                // self::$postUrl =$tmp;
                self::$postUrl = !isset(self::$postUrl) ? $me : ($me === self::$postUrl ? self::$postUrl : $me);
            // self::$postUrl = $me === self::$postUrl ? self::$postUrl : $me; // oder so?
                break;
            case "PostSelection":
                self::$postSelection = !isset(self::$postSelection) ? $me : ($me === self::$postSelection ? self::$postSelection : $me);
                echo "hier war postSelction case------#####";
                break;
            default: echo "<br>update dropped... <br>"; // hier könnte ein log gesetzt werden
        }

        if (isset($_SESSION)) {
            switch ($_SESSION) {
                case isset($_SESSION["S_URL"]):
                    $this->trackedUrl = $_SESSION["S_URL"];
                    if($me_class === "PostSelection") {$this->whenUrlIsSet($me);echo "hier war postselection-------#####<br>";}
                    break;
                case !isset($_SESSION["S_URL"]) && self::$postUrl->urlState == "NOT set":
                    $this->trackedUrl = "bisher keine Website gewählt";
                    break;
                case !isset($_SESSION["S_URL"]) && self::$postUrl->urlState == "url not working":
                    $this->trackedUrl = "Diese Adresse ist nicht korrekt oder nicht erreichbar";
                    break;
            }
        }
    }
    private function whenUrlIsSet(Post $me)
    {
        echo "whenUrlisset ------########";
        var_dump(self::$postSelection);
        switch ($_SESSION) {
            case isset($_SESSION["S_SELECTION"]):
                $this->trackedSelection = $_SESSION["S_SELECTION"];
                break;
            case !isset($_SESSION["S_SELECTION"]) && self::$postSelection->selectionState == "selection not working":
                $this->trackedSelection = "Ihre Auswahl ist nicht verwendbar. Bitte versuchen Sie etwas anderes.";
                break;
            // -----
            // jetzt muss die selection wieder in in die Box geschrieben werden - aber als inhalt, damit sie korrigiert werden kann und eine Ausgabe die sagt bei welchem Wort das Problem liegt - dazu gibt es schon den singlefalseWords[]
            case isset($_SESSION["S_URL"]) && self::$postSelection->selectionState == "PROBLEM":
                $this->trackedSelection = "PROBLEM: Ihre Auswahl ist nicht verwendbar. Bitte versuchen Sie etwas anderes.";
                // var_dump($formSelection);
                break;
            // 
            default:
                $this->trackedSelection = "hier erscheint ihre Auswahl mit Umfeld";
        }
        switch ($_SESSION) {
            case isset($_SESSION["S_INNERSELECTION"]):
                $this->trackedInnerSelection = $_SESSION["S_INNERSELECTION"];
                break;
                // ------- Achtung noch nicht korrekt--------
            case !isset($_SESSION["S_INNERSELECTION"]) && self::$postSelection->selectionState == "selection not working":
                $this->trackedInnerSelection = "Ihre Auswahl ist nicht verwendbar. Bitte versuchen Sie etwas anderes.";
                break;
            default:
                $this->trackedInnerSelection = "hier erscheint ihre zu beobachtende Auswahl";
        }
    }
}
