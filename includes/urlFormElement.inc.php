<?php
class UrlFormElement{
    public $urlFormElement;
    public function urlFormElement($formBox){
        $s = $_SERVER["PHP_SELF"];
        $this->urlFormElement = 
        '<form action="'. $s.'" method="post">
                <label for="url">website-adress you\'d like to monitor </label>
                <input type="url" name="url" id="url" placeholder="'.$formBox .'" required />
                <input type="submit" value="Raus damit">
                <input type="reset" value="Ach Mist">
            </form>';
        
        return $this->urlFormElement;
    }
}
