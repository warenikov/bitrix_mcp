<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp\ORM;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\EnumField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

class ToolTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'alexw_mcp_tools';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new StringField('NAME', [
                'required'   => true,
                'validation' => [__CLASS__, 'validateName'],
            ]),
            new StringField('TITLE'),
            new TextField('DESCRIPTION'),
            new EnumField('TYPE', [
                'required'      => true,
                'values'        => ['builtin', 'iblock', 'custom'],
                'default_value' => 'builtin',
            ]),
            new TextField('CONFIG'),  // JSON
            new BooleanField('AUTH_REQUIRED', [
                'values'        => ['N', 'Y'],
                'default_value' => 'Y',
            ]),
            new BooleanField('ACTIVE', [
                'values'        => ['N', 'Y'],
                'default_value' => 'Y',
            ]),
            new IntegerField('SORT', ['default_value' => 500]),
        ];
    }

    public static function validateName(): array
    {
        return [
            new \Bitrix\Main\ORM\Fields\Validators\LengthValidator(1, 64),
            new \Bitrix\Main\ORM\Fields\Validators\RegExpValidator('/^[a-z0-9_]+$/'),
        ];
    }
}
