<?php

class GivenUrl
{
    public PostUrl $postUrl; // ob das funktioniert??
    // public $givenUrl;
    // public $formAnswerUrl;
    // public $testarray = [];
    //-------------------------------------
    public $formBox; // sollte auf PostUrl->$defaultFormBox mappen
    // public $checkedUrl; // oder so ähnlich mapped auf die Url oder ist das eh nur für $formAnswerUrl?
    public function __construct()
    {
        // throw new \Exception('Not implemented');
        $this->givenUrl();
    }
    private function givenUrl()
    {
        $this->postUrl = new PostUrl();
        $this->postUrl->checkUrlIn();
        $this->formBox = $this->postUrl->formBox;
       
        // switch ($this->postUrl->urlState) {
        //     case  "isset":
        //         $this->formAnswerUrl = $_SESSION["S_URL"]; //$this->postUrl->checkedUrlIn
        //         break;
        //     case "NOT set":
        //         $this->formAnswerUrl = "bisher keine Website gewählt";
        //         break;
        //     default:
        //         $this->formAnswerUrl = "Diese Adresse ist nicht korrekt oder nicht erreichbar"; // "url not working"
        // }
        // $this->testarray[] = $this->postUrl->checkedUrlIn;
    }
}
