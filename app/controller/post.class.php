<?php
class Post
{
    protected string $postSet = "Post NOT set"; // nützliche Variable aber viell. auf bool wechseln
    protected string $postIn = ""; //ggf. gesetzte Url
    public string $formBox;
    protected string $defaultFormBox;
    public static FormAnswerBlockData $formAnswerBlockData;

// vielleicht noch ein getter für $formAnswerBlockData damit sie wieder protected sein kann
    protected function checkPost(string $check, string $defaultFormBox)
    {
        $this->getPost($check);
        $this->setFormBox($defaultFormBox);
    }

    protected function getPost(string $check)
    {
        if (isset($_POST[$check])) {
            $this->postIn = htmlspecialchars(stripslashes(trim($_POST[$check])));
            $this->postSet = "Post isset";
        }
    }

    protected function setFormBox(string $defaultFormBox)
    {
        $this->formBox = $this->postIn == "" | $this->postIn == $defaultFormBox ? $defaultFormBox : "successful!";
    }

    protected function setFormAnswerBlockData()
    {
        // $this->formAnswerBlockData = $formAnswerBlockData;
        self::$formAnswerBlockData = new FormAnswerBlockData();
        
    }

    protected function updateFormAnswerBlockData()
    {
        self::$formAnswerBlockData->update($this);
    }
}
