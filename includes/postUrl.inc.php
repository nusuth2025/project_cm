<?php
class PostUrl extends Post
{
    public string $defaultFormBox = "https://example.com";
    public string $checkedUrlIn = "";
    public string  $check = "url";
    public string $formAnswerUrl = "";
    public string $urlState = "";
    public int $statusCode = 0;


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
                    // $this->setFormAnswerBlockData();
                    // $this->updateFormAnswerBlockData();
                } else {
                    $this->urlState = "url not working";
                };
        }
        $this->setFormAnswerBlockData();
        var_dump(self::$formAnswerBlockData); //---------------
        $this->setUrlState();
    }

    // public static function setUrlState(string $state) //"NOT set", "isset", "url not working"
    // {
    //     self::urlState = $state;
    // }

    // die Benennung ist ggf unpassend - weil hier eigentlich die formBox gesetzt wird - aber setFormBox gibts schon in post.inc.php
    private function setUrlState()
    {
        switch ($this->urlState) {
            case "NOT set":;
            case "isset":;
                break;
            case "url not working":
                $this->formBox = $this->postIn;
        }
        $this->updateFormAnswerBlockData();
    }

    private function getCurlHandle(string $url)
    {
        $curlHandle = curl_init($url);
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
        return $curlHandle;
    }

    private function checkContentType(string $url)
    {

        $curlHandle = $this->getCurlHandle($url);

        curl_exec($curlHandle);
        $contenttype = curl_getinfo($curlHandle, CURLINFO_CONTENT_TYPE) !== false ? curl_getinfo($curlHandle, CURLINFO_CONTENT_TYPE) : @$this->checkContentTypeSimple($url); // @ unterdrückt Warnings bei wirren urls
        // echo "Content-Type: " . $contenttype . "\n";
        var_dump($contenttype); // bei der IHK kommt hier false
        return strtolower(explode(";", $contenttype)[0]);
    }
    // dieser Fallback ist bspw. für die IHK-Seite, die mit curl keinen gültigen Content-Type liefert
    private function checkContentTypeSimple(String $url)
    {
        if ($contenttype = (get_headers($url, true)["content-type"]) ?? (get_headers($url, true)["Content-Type"])) {
            // return strtolower(explode(";", $contenttype)[0]);
            return $contenttype;
        }
    }

    private function checkIfWorkingUrl(string $url): bool
    {
        // ein check auf Statuscode wäre auch gut
        $typetocheck = ['text/html'];
        $return = false;
        if ($this->checkHTTPStatusCode($url) !== 200) {

            $this->statusCode = $this->checkHTTPStatusCode($url);

            if (in_array($this->checkContentType($url), $typetocheck)) {
                $return = true;
            }
        } elseif ($this->checkHTTPStatusCode($url) === 0) {
            $this->statusCode = 0;
            $return = false;
        } else {
            $this->statusCode = $this->checkHTTPStatusCode($url);
            $return = true;
        }

        return $return;
    }

    private function checkHTTPStatusCode(string $url)
    {
        $curlHandle = $this->getCurlHandle($url);

        curl_exec($curlHandle);
        $statuscode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE) !== false ? curl_getinfo($curlHandle, CURLINFO_HTTP_CODE) : @$this->checkHTTPStatusCodeSimple($url);

        var_dump($statuscode); // ausgabe '200' // bei der IHK kommt hier 0
        return $statuscode; // ausgabe '200'
    }

    private function checkHTTPStatusCodeSimple(String $url)
    {
        $statuscode = get_headers($url, true)[0]; // ausgabe 'HTTP/1.1 200 OK'
        $statuscode = (int)(explode(" ", $statuscode)[1]); // ausgabe 200 (int) oder ein anderer Statuscode oder sonst immer 0
        return $statuscode;
    }

    protected function updateFormAnswerBlockData()
    {
        self::$formAnswerBlockData->update($this);
    }
}
