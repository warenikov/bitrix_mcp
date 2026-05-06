<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp;

use Alexw\Clientsmcp\ORM\TokenTable;
use Alexw\Clientsmcp\ORM\ToolTable;
use Bitrix\Main\Application;

final class SchemaInstaller
{
    public function install(): void
    {
        TokenTable::getEntity()->createDbTable();
        ToolTable::getEntity()->createDbTable();
    }

    public function uninstall(): void
    {
        $db = Application::getConnection();
        $db->dropTable(TokenTable::getTableName());
        $db->dropTable(ToolTable::getTableName());
    }
}
