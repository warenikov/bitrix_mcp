<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp;

use Alexw\Clientsmcp\ORM\ToolTable;

final class ToolRepository
{
    /** @return ToolDefinition[] */
    public static function getActive(): array
    {
        $result = ToolTable::getList([
            'filter' => ['=ACTIVE' => 'Y'],
            'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);

        $tools = [];
        while ($row = $result->fetch()) {
            $tools[] = ToolDefinition::fromRow($row);
        }

        return $tools;
    }

    public static function findByName(string $name): ?ToolDefinition
    {
        $result = ToolTable::getList([
            'filter' => ['=NAME' => $name, '=ACTIVE' => 'Y'],
            'limit'  => 1,
        ]);

        $row = $result->fetch();

        return $row ? ToolDefinition::fromRow($row) : null;
    }

    public static function save(ToolDefinition $def): \Bitrix\Main\ORM\Data\AddResult|\Bitrix\Main\ORM\Data\UpdateResult
    {
        $fields = [
            'NAME'          => $def->name,
            'TITLE'         => $def->title,
            'DESCRIPTION'   => $def->description,
            'TYPE'          => $def->type->value,
            'CONFIG'        => json_encode($def->config, JSON_UNESCAPED_UNICODE),
            'AUTH_REQUIRED' => $def->authRequired ? 'Y' : 'N',
            'ACTIVE'        => $def->active ? 'Y' : 'N',
            'SORT'          => $def->sort,
        ];

        return $def->id > 0
            ? ToolTable::update($def->id, $fields)
            : ToolTable::add($fields);
    }
}
