<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$MODULE_ID = 'alexw.clientsmcp';
CModule::IncludeModuleEx($MODULE_ID);

/** @var alexw_clientsmcp $MODULE */
if (class_exists('alexw_clientsmcp')) {
    $MODULE = new alexw_clientsmcp();
    $MODULE->DoUninstall();
}
