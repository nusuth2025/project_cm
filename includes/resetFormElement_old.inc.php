<?php
class ResetFormElement_old
{
    public $resetFormElement_old;
    public function resetFormElement_old()
    {
        // der SAVE Button hat noch keine Funktion und sollte ggf. eine eigene Klasse bekommen
       
        $this->resetFormElement_old =
            '<label for="reset_form_old">if you\'d like to reset the form click the button </label>
                <button name="reset_form_old"><a href="includes/sessionUnset.php">reset</a></button>
                
                <input type="button" value="SAVE">';

        return $this->resetFormElement_old;
    }
}
