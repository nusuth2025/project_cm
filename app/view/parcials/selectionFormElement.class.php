<?php
class SelectionFormElement
{
    public String $selectionFormElement;
    public String $alert = "";

    public function selectionFormElement(String $formBox, String $text = "", array $falseWord = [])
    {
        $alert = "";
        if (!empty($falseWord)) {
            $text = $this->inputRewiew($falseWord);
            $alert = '<p style="color: red;">The following word is not propperly found in the selection: ' . $falseWord[1] . '<br>Please correct your input!</p>';
        }
        //label text, name, for, type, id ... submit field ... alles geht als variable
        $s = $_SERVER["PHP_SELF"];
        $this->selectionFormElement =
            '<form action="' . $s . '" method="post">
                <label for="wider_select">please copy-paste the data you\'d like to monitor from the website you selected, enclosed with some surrounding lines </label>
                <textarea type="text" name="wider_select" id="wider_select" cols="50" rows="5" placeholder="' . $formBox . '"  >' . $text . '</textarea>' . $alert . '
                <input type="submit" value="Raus damit">
                <input type="reset" value="Ach Mist">
            </form>';

        return $this->selectionFormElement;
    }
    private function inputRewiew(array $falseWord)
    {
        $text = "";
        if (isset($falseWord[0]) && isset($falseWord[1])) {
            $text = $falseWord[2];
            echo "inputReview ------------<br>";
        }
        return $text;
    }
}
