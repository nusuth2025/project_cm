<?php

class SessionObject
{

    public static function setSession_ID()
    {
        $_SESSION["S_ID"] = isset($_SESSION["S_ID"]) ? $_SESSION["S_ID"] : (string) "monitor" . time();
    }

    public static function setSessionUrl($url)
    {
        $_SESSION["S_URL"] = isset($_SESSION["S_URL"]) ? $_SESSION["S_URL"] : $url;
    }

    public static function setSessionSelection($selection)
    {
        $_SESSION["S_SELECTION"] = isset($_SESSION["S_SELECTION"]) ? $_SESSION["S_SELECTION"] : $selection;
    }

    public static function setSessionInnerSelection($innerSelection)
    {
        $_SESSION["S_INNERSELECTION"] = isset($_SESSION["S_INNERSELECTION"]) ? $_SESSION["S_INNERSELECTION"] : $innerSelection;
    }

    public static function resetOrSaveSession()
    {
        // $postresetsave = $_POST; // nur für Testzwecke

        if (isset($_POST["reset_form"]) && $_POST["reset_form"] == "reset") {
            self::resetSession();
        } elseif (isset($_POST["save_data"]) && $_POST["save_data"] == "SAVE") {
            // $_SESSION["RESETSAVE"] = $_POST; // vielleicht unnötig
            self::saveSession();
        }
        // $_SESSION["RESETSAVE"] = $postresetsave; // nur für Testzwecke

    }

    public static function resetSession()
    {
        session_unset();
        header('Location:' . $_SERVER['HTTP_REFERER']);
    }

    public static function saveSession()
    {
        if (isset($_SESSION["S_ID"])) {
            $f = fopen("../arrivals/" . $_SESSION["S_ID"] . ".txt", "wa");

            fwrite($f, serialize($_SESSION));
            fclose($f);
        }
        session_unset();
        header('Location:' . $_SERVER['HTTP_REFERER']);
    }
}
