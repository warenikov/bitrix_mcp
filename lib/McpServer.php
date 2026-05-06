<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp;

final class McpServer
{
    private const PROTOCOL_VERSION = '2025-11-25';
    private const SERVER_NAME      = 'alexw.clientsmcp';
    private const SERVER_VERSION   = '1.0.0';

    public static function dispatch(array $body, ?int $userId, ?string $token = null): array
    {
        $id     = $body['id'] ?? null;
        $method = $body['method'] ?? '';
        $params = $body['params'] ?? [];

        return match ($method) {
            'initialize'         => self::handleInitialize($id),
            'notifications/initialized' => self::notification(),
            'tools/list'         => self::handleToolsList($id, $userId, $token),
            'tools/call'         => self::handleToolsCall($id, $params, $userId, $token),
            default              => self::methodNotFound($id, $method),
        };
    }

    // --- Handlers ---

    private static function handleInitialize(mixed $id): array
    {
        $result = [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities'    => [
                'tools' => new \stdClass(),
            ],
            'serverInfo' => [
                'name'    => self::SERVER_NAME,
                'version' => self::SERVER_VERSION,
            ],
        ];

        $instructions = \COption::GetOptionString('alexw.clientsmcp', 'server_instructions', '');
        if ($instructions !== '') {
            $result['instructions'] = $instructions;
        }

        return self::result($id, $result);
    }

    private static function handleToolsList(mixed $id, ?int $userId, ?string $token): array
    {
        $tools = ToolsRegistry::getForRequest($userId, $token);

        $schemas = array_map(
            static fn(ToolDefinition $def) => ToolsRegistry::resolve($def)->schema(),
            $tools
        );

        return self::result($id, ['tools' => array_values($schemas)]);
    }

    private static function handleToolsCall(mixed $id, array $params, ?int $userId, ?string $token): array
    {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        $def = ToolsRegistry::findForRequest($name, $userId, $token);

        if ($def === null) {
            return self::result($id, [
                'content' => [['type' => 'text', 'text' => "Tool '{$name}' not found or not allowed"]],
                'isError'  => true,
            ]);
        }

        try {
            $content = ToolsRegistry::resolve($def)->execute($args, $userId);
            return self::result($id, $content);
        } catch (\Throwable $e) {
            return self::result($id, [
                'content' => [['type' => 'text', 'text' => $e->getMessage()]],
                'isError'  => true,
            ]);
        }
    }

    // --- Helpers ---

    private static function result(mixed $id, array $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private static function notification(): array
    {
        // Уведомления не требуют ответа по спецификации MCP
        return [];
    }

    private static function methodNotFound(mixed $id, string $method): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => ['code' => -32601, 'message' => "Method not found: {$method}"],
        ];
    }
}
