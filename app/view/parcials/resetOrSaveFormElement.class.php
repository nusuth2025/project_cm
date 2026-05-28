<?php
class ResetOrSaveFormElement
{
    public String $resetOrSaveFormElement;
    public function resetOrSaveFormElement()
    {
        $s = "/../../app/model/sessionResetOrSave.php";
        var_dump($s);
        $this->resetOrSaveFormElement =
            '<form action="' . $s . '" method="post">
                <label for="reset_form">if you\'d like to reset the form without saving click the button </label>
                <button type="submit" name="reset_form" value="reset">reset</button>
                <br>                
                <label for="save_data">to save your selections click this button</label>
                <button type="submit" name="save_data" value="SAVE">SAVE</button>
                </form>';

        return $this->resetOrSaveFormElement;
    }
}
