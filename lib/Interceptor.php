<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp;

use Bitrix\Main\Loader;

final class Interceptor
{
    public static function handle(): void
    {
        if (!self::isMcpRequest()) return;

        // Загружаем модуль явно — OnProlog стреляет до Loader::includeModule в коде страницы
        Loader::includeModule('alexw.clientsmcp');

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');

        // Авторизация: нет заголовка — анонимный запрос (только публичные tools)
        //              есть заголовок — обязан быть валидным токеном
        $userId = null;
        $token  = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if ($authHeader !== '') {
            $token  = Auth::extractToken($authHeader);
            $userId = Auth::getUserIdByToken($authHeader);

            if ($userId === null) {
                http_response_code(401);
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error'   => ['code' => -32001, 'message' => 'Unauthorized'],
                    'id'      => null,
                ], JSON_UNESCAPED_UNICODE);
                die();
            }
        }

        $input = file_get_contents('php://input');
        $body  = json_decode($input, true);

        if (!is_array($body)) {
            http_response_code(400);
            echo json_encode([
                'jsonrpc' => '2.0',
                'error'   => ['code' => -32700, 'message' => 'Parse error'],
                'id'      => null,
            ], JSON_UNESCAPED_UNICODE);
            die();
        }

        $response = McpServer::dispatch($body, $userId, $token);

        // notifications не требуют ответа — отвечаем 204
        if ($response === []) {
            http_response_code(204);
            die();
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        die();
    }

    private static function isMcpRequest(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;

        // Явный заголовок протокола (curl-тесты, некоторые клиенты)
        if (isset($_SERVER['HTTP_MCP_PROTOCOL_VERSION'])) return true;

        // MCP Streamable HTTP spec: клиент обязан слать Accept с text/event-stream
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'text/event-stream') && str_contains($accept, 'application/json')) {
            return true;
        }

        return false;
    }
}
