<?php
class SelectionFormElement{
    public $selectionFormElement;
    public function selectionFormElement($formBox){
        //label text, name, for, type, id ... submit field ... alles geht als variable
        $s = $_SERVER["PHP_SELF"];
        $this->selectionFormElement = 
        '<form action="'. $s.'" method="post">
                <label for="wider_select">please copy-paste the data you\'d like to monitor from the website you selected, enclosed with some surrounding lines </label>
                <textarea type="text" name="wider_select" id="wider_select" cols="50" rows="5" placeholder="'.$formBox .'"  ></textarea>
                <input type="submit" value="Raus damit">
                <input type="reset" value="Ach Mist">
            </form>';
        
        return $this->selectionFormElement;
    }
}
?>