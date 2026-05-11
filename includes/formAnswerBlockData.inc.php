<?php
// die funktioniert wahrscheinlich, wenn man sie in includes kopiert
class FormAnswerBlockData
{
    public string $trackedUrl = "";
    public string $trackedSelection = "";
    public string $trackedInnerSelection = "";

    public function update($givenUrl, $givenSelection) // so richtig gut find ich das noch nicht, hier alles mit übergeben zu müssen
    {
       
        if (isset($_SESSION)) {
            switch ($_SESSION) {
                case isset($_SESSION["S_URL"]):
                    $this->trackedUrl = $_SESSION["S_URL"];
                    $this->whenUrlIsSet($givenSelection);
                    break;
                case !isset($_SESSION["S_URL"]) && $givenUrl->postUrl->urlState == "NOT set":
                    $this->trackedUrl = "bisher keine Website gewählt";
                    break;
                case !isset($_SESSION["S_URL"]) && $givenUrl->postUrl->urlState == "url not working":
                    $this->trackedUrl = "Diese Adresse ist nicht korrekt oder nicht erreichbar";
                    break;
            }
        }
    }
    private function whenUrlIsSet($givenSelection)
    {
        switch ($_SESSION) {
            case isset($_SESSION["S_SELECTION"]):
                $this->trackedSelection = $_SESSION["S_SELECTION"];
                break;
            case !isset($_SESSION["S_SELECTION"]) && $givenSelection->postSelection->selectionState == "selection not working":
                $this->trackedSelection = "Ihre Auswahl ist nicht verwendbar. Bitte versuchen Sie etwas anderes.";
                break;
            default:
                $this->trackedSelection = "hier erscheint ihre Auswahl mit Umfeld";
        }
        switch ($_SESSION) {
            case isset($_SESSION["S_INNERSELECTION"]):
                $this->trackedInnerSelection = $_SESSION["S_INNERSELECTION"];
                break;
            case !isset($_SESSION["S_INNERSELECTION"]) && $givenSelection->postSelection->selectionState == "selection not working":
                $this->trackedInnerSelection = "Ihre Auswahl ist nicht verwendbar. Bitte versuchen Sie etwas anderes.";
                break;
            default:
                $this->trackedInnerSelection = "hier erscheint ihre zu beobachtende Auswahl";
                
        }
    }
}
