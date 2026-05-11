<?php
class PostUrl extends Post
{
    public string $defaultFormBox = "https://example.com";
    public string $checkedUrlIn = "";
    public string  $check = "url";
    public string $formAnswerUrl = "";
    public string $urlState = "";


    // der Check auf Validität der url reicht wahrscheinlich noch nicht
    public function checkUrlIn()
    {
        $this->checkPost($this->check, $this->defaultFormBox);

        switch ($this->postSet) {
            case "Post NOT set":
                $this->formAnswerUrl = $this->defaultFormBox;
                $this->urlState = "NOT set";
                break;
            case "Post isset":
                if ($this->checkIfWorkingUrl($this->postIn)) {
                    $this->checkedUrlIn = $this->postIn;
                    $this->formAnswerUrl = $this->formBox;
                    $this->urlState = "isset";
                    SessionObject::setSessionUrl($this->checkedUrlIn);
                } else {
                    $this->urlState = "url not working";
                };
        }
        $this->setUrlState();
    }

    // public static function setUrlState(string $state) //"NOT set", "isset", "url not working"
    // {
    //     self::urlState = $state;
    // }

    private function setUrlState()
    {
        switch ($this->urlState) {
            case "NOT set":;
            case "isset":;
                break;
            case "url not working":
                $this->formBox = $this->postIn;
        }
    }

    private function checkContentType(string $url)
    {

        $curlHandle = curl_init($url);
        // ggf. ist der curl_setopt_array auch nicht nötig und diese Zeile genügt
        // curl_setopt($curlHandle,CURLOPT_NOBODY,true);
        curl_setopt_array(
            $curlHandle,
            array(

                CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64; rv:148.0) Gecko/20100101 Firefox/148.0',
                CURLOPT_ENCODING => 'gzip, deflate, br, zstd',
                CURLOPT_HTTPHEADER => array(
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: de,en-US;q=0.9,en;q=0.8',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1',
                    'Sec-Fetch-Dest: 	document',
                    'Sec-Fetch-Mode: 	navigate',
                    'Sec-Fetch-Site: 	cross-site',
                    'Sec-Fetch-User: 	?1',
                    'Priority: 	u=0, i',
                ),
                CURLOPT_NOBODY => true,
            )
        );
        curl_exec($curlHandle);
        $contenttype = curl_getinfo($curlHandle, CURLINFO_CONTENT_TYPE);
        return strtolower(explode(";", $contenttype)[0]);
    }

    private function checkIfWorkingUrl(string $url): bool
    {
        // ein check auf Statuscode wäre auch gut
        $typetocheck = ['text/html'];
        $return = false;

        if (in_array($this->checkContentType($url), $typetocheck)) {
            $return = true;
        }

        return $return;
    }
}
