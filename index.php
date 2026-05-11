<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>contentMonitor</title>
    <link
        rel="icon"
        type="image/svg+xml"
        href="img/light-bulb-idea-svgrepo-com-green.svg" />
    <?php
    include_once 'includes/init.inc.php';

    // define

    ?>
</head>

<body>
    <h1>Hi ich bin aus der index.html im contentMonitor...</h1>

    <div>
        <div>Eingabe Webadresse
            <?php echo $formUrl ?>
        </div>
        <div>Ausgabe Webadresse
            <p style="background-color:antiquewhite;">This is the website you'd like to monitor: <?= $formAnswerBlockData->trackedUrl ?></p>
            <div>
                <?php echo $formResetOrSave ?>
            </div>
            <p>var_dump($_POST); :: <?php var_dump($_POST); ?></p>
            <p>var_dump($_SESSION); :: <?php var_dump($_SESSION); ?></p>
            <p>var_dump($givenUrl)<?php var_dump($givenUrl) ?></p>
            

        </div>
        <div><?php echo $formSelection ?>
            <p>var_dump($givenUrl->postUrl->urlState):: <?= var_dump($givenUrl->postUrl->urlState) ?></p>
        </div>
    </div>
    <div>
        <div>Eingabe Copy Paste Text</div>
        <div>Ausgabe Html Copy Paste Text</div>
        <div>Ausgabe Text to monitor</div>
        <div><?php 
        var_dump($formAnswerBlockData);
                    ?></div>
    </div>
</body>

</html>