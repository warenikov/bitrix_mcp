<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp;

enum ToolType: string
{
    case Builtin = 'builtin'; // PHP-класс, поставляется с модулем
    case Iblock  = 'iblock';  // генерируется по конфигу инфоблока
    case Custom  = 'custom';  // PHP-класс, написанный разработчиком сайта
}
