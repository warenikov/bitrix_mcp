<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp;

use Alexw\Clientsmcp\ORM\TokenTable;
use Bitrix\Main\Type\DateTime;

final class TokenRepository
{
    public static function findByToken(string $token): ?array
    {
        $result = TokenTable::getList([
            'select' => [
                '*',
                'USER_ID'        => 'USER_ID',
                'USER_NAME'      => 'USER.NAME',
                'USER_LAST_NAME' => 'USER.LAST_NAME',
                'USER_EMAIL'     => 'USER.EMAIL',
            ],
            'filter' => ['=TOKEN' => $token, '=ACTIVE' => 'Y'],
            'limit'  => 1,
        ]);

        return $result->fetch() ?: null;
    }

    public static function touchLastUsed(int $id): void
    {
        TokenTable::update($id, ['LAST_USED' => new DateTime()]);
    }

    public static function create(int $userId, string $name, array $tools = []): string
    {
        $token = 'mcp_' . bin2hex(random_bytes(24));

        TokenTable::add([
            'TOKEN'      => $token,
            'USER_ID'    => $userId,
            'NAME'       => $name,
            'TOOLS'      => empty($tools) ? null : json_encode($tools),
            'ACTIVE'     => 'Y',
            'CREATED_AT' => new DateTime(),
        ]);

        return $token;
    }

    public static function revoke(int $id): void
    {
        TokenTable::update($id, ['ACTIVE' => 'N']);
    }

    /**
     * Возвращает список разрешённых имён инструментов для токена.
     * null = все разрешены.
     *
     * @return string[]|null
     */
    public static function getAllowedTools(array $tokenRow): ?array
    {
        if (empty($tokenRow['TOOLS'])) return null;

        $tools = json_decode($tokenRow['TOOLS'], true);

        return is_array($tools) ? $tools : null;
    }
}
