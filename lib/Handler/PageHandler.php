<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp\Handler;

use Bitrix\Main\Loader;

/**
 * Подключение webmcp.js на публичных страницах: регистрация инструментов
 * сайта для браузерных AI-агентов. Отключается опцией webmcp_enabled.
 */
final class PageHandler
{
    public static function onPageStart(): void
    {
        if (!Loader::includeModule('alexw.clientsmcp')) {
            return;
        }

        if (\COption::GetOptionString('alexw.clientsmcp', 'webmcp_enabled', 'Y') !== 'Y') {
            return;
        }

        if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
            return;
        }

        $jsPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/alexw.clientsmcp/webmcp.js';
        $version = file_exists($jsPath) ? filemtime($jsPath) : time();

        global $APPLICATION;
        $APPLICATION->AddHeadScript('/bitrix/js/alexw.clientsmcp/webmcp.js?v=' . $version);
    }
}
