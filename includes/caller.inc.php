<?php
session_start();

SessionObject::setSession_ID();

// session_regenerate_id();
$formResetOrSave = new ResetOrSaveFormElement()->resetOrSaveFormElement();
// $formReset_old = new ResetFormElement_old()->resetFormElement_old();
$givenUrl = new GivenUrl();
$formUrl = new UrlFormElement()->urlFormElement($givenUrl->formBox);
$formSelection = new SelectionFormElement()->selectionFormElement("--Platzhalter--");