<?php
session_start();

SessionObject::setSession_ID();

// session_regenerate_id();
$formResetOrSave = new ResetOrSaveFormElement()->resetOrSaveFormElement();
// $formReset_old = new ResetFormElement_old()->resetFormElement_old();
$givenUrl = new GivenUrl();
$formUrl = new UrlFormElement()->urlFormElement($givenUrl->formBox);
$givenSelection = new GivenSelection();
$formSelection = new SelectionFormElement()->selectionFormElement($givenSelection->formBox);
$formAnswerBlockData = new FormAnswerBlockData(); //------
$formAnswerBlockData->update($givenUrl,$givenSelection);
// $test->update();