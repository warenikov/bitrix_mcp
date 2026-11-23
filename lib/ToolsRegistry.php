<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp;

final class ToolsRegistry
{
    /**
     * Возвращает инструменты, доступные для данного запроса.
     *
     * @param int|null    $userId       null = анонимный запрос
     * @param string|null $token        токен для per-token фильтрации
     * @return ToolDefinition[]
     */
    public static function getForRequest(?int $userId, ?string $token = null): array
    {
        $all = ToolRepository::getActive();

        // Фильтруем по требованию авторизации
        $filtered = array_filter($all, static function (ToolDefinition $def) use ($userId): bool {
            return !$def->authRequired || $userId !== null;
        });

        // Применяем per-token ограничения
        if ($token !== null && $userId !== null) {
            $tokenRow = TokenRepository::findByToken($token);
            if ($tokenRow !== null) {
                $allowed = TokenRepository::getAllowedTools($tokenRow);
                if ($allowed !== null) {
                    $filtered = array_filter(
                        $filtered,
                        static fn(ToolDefinition $d) => in_array($d->name, $allowed, true)
                            || !$d->authRequired // публичные всегда доступны
                    );
                }
            }
        }

        return array_values($filtered);
    }

    public static function findForRequest(string $name, ?int $userId, ?string $token = null): ?ToolDefinition
    {
        $def = ToolRepository::findByName($name);
        if ($def === null) return null;

        if ($def->authRequired && $userId === null) return null;

        // Per-token проверка только для auth-инструментов
        if ($token !== null && $userId !== null && $def->authRequired) {
            $tokenRow = TokenRepository::findByToken($token);
            if ($tokenRow !== null) {
                $allowed = TokenRepository::getAllowedTools($tokenRow);
                if ($allowed !== null && !in_array($name, $allowed, true)) return null;
            }
        }

        return $def;
    }

    public static function resolve(ToolDefinition $def): ToolInterface
    {
        return match ($def->type) {
            ToolType::Builtin, ToolType::Custom => self::instantiateClass($def),
            ToolType::Iblock                    => new Tools\IblockTool($def),
        };
    }

    /**
     * Инструменты для браузерного WebMCP-канала: только помеченные browser_safe,
     * а из них требующие авторизации — лишь для залогиненного посетителя.
     *
     * @return ToolDefinition[]
     */
    public static function getForBrowser(?int $userId): array
    {
        return array_values(array_filter(
            ToolRepository::getActive(),
            static fn(ToolDefinition $def): bool =>
                $def->browserSafe && (!$def->authRequired || ($userId !== null && $userId > 0))
        ));
    }

    public static function findForBrowser(string $name, ?int $userId): ?ToolDefinition
    {
        $def = ToolRepository::findByName($name);
        if ($def === null || !$def->active || !$def->browserSafe) return null;
        if ($def->authRequired && ($userId === null || $userId <= 0)) return null;
        return $def;
    }

    private static function instantiateClass(ToolDefinition $def): ToolInterface
    {
        $class = $def->config['class'] ?? '';
        if (!class_exists($class)) {
            throw new \RuntimeException("Tool class not found: {$class}");
        }

        $instance = new $class();
        if (!$instance instanceof ToolInterface) {
            throw new \RuntimeException("{$class} must implement ToolInterface");
        }

        return $instance;
    }
}
