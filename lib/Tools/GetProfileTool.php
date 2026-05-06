<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp\Tools;

use Alexw\Clientsmcp\ToolInterface;

final class GetProfileTool implements ToolInterface
{
    public function schema(): array
    {
        return [
            'name'        => 'get_profile',
            'description' => 'Возвращает профиль авторизованного пользователя: имя, email, телефон.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => new \stdClass(),
            ],
        ];
    }

    public function execute(array $args, ?int $userId): array
    {
        if ($userId === null) {
            throw new \RuntimeException('Требуется авторизация');
        }

        $rs  = \CUser::GetByID($userId);
        $row = $rs->Fetch();

        if (!$row) {
            throw new \RuntimeException('Пользователь не найден');
        }

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'id'         => (int)$row['ID'],
                    'name'       => trim($row['NAME'] . ' ' . $row['LAST_NAME']),
                    'email'      => $row['EMAIL'],
                    'phone'      => $row['PERSONAL_PHONE'] ?? '',
                    'login'      => $row['LOGIN'],
                ], JSON_UNESCAPED_UNICODE),
            ]],
        ];
    }
}
