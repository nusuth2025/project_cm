<?php

class GivenSelection
{
    public PostSelection $postSelection;
    // public ProcessSelection $processSelection;
    public String $formAnswerSelection;
    public String $formBox;

    public function __construct()
    {
        $this->givenSelection();
    }

    private function givenSelection()
    {
        $this->postSelection = new PostSelection();
        // $this->processSelection = new ProcessSelection();
        $this->postSelection->checkSelection();
        $this->formBox = $this->postSelection->formBox;

        switch ($this->postSelection->selectionState) {
            case  "isset":
                $this->formAnswerSelection = $this->postSelection->checkedSelectionIn;
                break;
            case "NOT set":
                $this->formAnswerSelection = "bisher keine Auswahl getroffen";
                break;
            default:
                $this->formAnswerSelection = "Diese Auswahl ist nicht im Quelltext zu finden";
        }
    }
}
