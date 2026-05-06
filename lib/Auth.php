<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp;

final class Auth
{
    /**
     * Разбирает "Bearer mcp_xxx", ищет токен в БД, обновляет LAST_USED.
     * Возвращает USER_ID или null если токен недействителен.
     */
    public static function getUserIdByToken(string $authHeader): ?int
    {
        if (!str_starts_with($authHeader, 'Bearer ')) return null;

        $token = trim(substr($authHeader, 7));
        if ($token === '') return null;

        $row = TokenRepository::findByToken($token);
        if ($row === null) return null;

        TokenRepository::touchLastUsed((int)$row['ID']);

        return (int)$row['USER_ID'];
    }

    /**
     * Разбирает "Bearer mcp_xxx" и возвращает всю строку токена.
     * Нужна для проверки per-token разрешений в ToolsRegistry.
     */
    public static function extractToken(string $authHeader): ?string
    {
        if (!str_starts_with($authHeader, 'Bearer ')) return null;

        $token = trim(substr($authHeader, 7));

        return $token !== '' ? $token : null;
    }
}
