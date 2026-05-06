<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp\ORM;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\UserTable;

class TokenTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'alexw_mcp_tokens';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new StringField('TOKEN', [
                'required'   => true,
                'validation' => [__CLASS__, 'validateToken'],
            ]),
            new IntegerField('USER_ID', ['required' => true]),
            new StringField('NAME'),
            new TextField('TOOLS'),   // JSON-массив разрешённых tool names, null = все
            new BooleanField('ACTIVE', [
                'values'        => ['N', 'Y'],
                'default_value' => 'Y',
            ]),
            new DatetimeField('CREATED_AT'),
            new DatetimeField('LAST_USED'),

            // Связь с пользователем Битрикс
            new Reference(
                'USER',
                UserTable::class,
                Join::on('this.USER_ID', 'ref.ID'),
                ['join_type' => 'LEFT'],
            ),
        ];
    }

    public static function validateToken(): array
    {
        return [
            new \Bitrix\Main\ORM\Fields\Validators\LengthValidator(1, 64),
        ];
    }
}
