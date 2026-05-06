<?php

use Alexw\Clientsmcp\SchemaInstaller;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class alexw_clientsmcp extends CModule
{
    public $MODULE_ID   = 'alexw.clientsmcp';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME        = Loc::getMessage('ALEXW_CLIENTSMCP_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ALEXW_CLIENTSMCP_MODULE_DESCRIPTION');
        $this->PARTNER_NAME       = Loc::getMessage('ALEXW_CLIENTSMCP_PARTNER_NAME');
        $this->PARTNER_URI        = Loc::getMessage('ALEXW_CLIENTSMCP_PARTNER_URI');
    }

    public function DoInstall(): bool
    {
        ModuleManager::registerModule($this->MODULE_ID);
        Loader::includeModule($this->MODULE_ID);

        $this->InstallDB();
        $this->InstallFiles();
        $this->InstallEvents();

        return true;
    }

    public function DoUninstall(): bool
    {
        Loader::includeModule($this->MODULE_ID);

        $this->UnInstallEvents();
        $this->UnInstallFiles();
        // таблицы не дропаем — токены и настройки инструментов могут быть нужны

        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }

    public function InstallDB(): void
    {
        (new SchemaInstaller())->install();
    }

    public function UnInstallDB(): void
    {
        (new SchemaInstaller())->uninstall();
    }

    public function InstallFiles(): void
    {
        $adminDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin';
        $srcDir   = __DIR__ . '/../admin';
        foreach (glob($srcDir . '/*.php') as $src) {
            $filename = basename($src);
            if ($filename === 'menu.php') continue;
            copy($src, $adminDir . '/' . $filename);
        }
    }

    public function UnInstallFiles(): void
    {
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/alexw_mcp_tokens.php');
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/alexw_mcp_token_edit.php');
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/alexw_mcp_tools.php');
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/alexw_mcp_tool_edit.php');
    }

    public function InstallEvents(): void
    {
        EventManager::getInstance()->registerEventHandlerCompatible(
            'main', 'OnProlog',
            $this->MODULE_ID,
            '\Alexw\Clientsmcp\Interceptor', 'handle'
        );
    }

    public function UnInstallEvents(): void
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'main', 'OnProlog',
            $this->MODULE_ID,
            '\Alexw\Clientsmcp\Interceptor', 'handle'
        );
    }
}
