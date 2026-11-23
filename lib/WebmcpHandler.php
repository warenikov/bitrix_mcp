<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp;

use Bitrix\Main\Loader;

/**
 * Браузерный канал WebMCP: каталог инструментов и их исполнение
 * в контексте сессии текущего посетителя сайта.
 *
 * Отличия от основного MCP-транспорта (Interceptor):
 * - авторизация по сессии сайта, а не по Bearer-токену;
 * - доступны только инструменты с флагом browser_safe;
 * - POST защищён bitrix_sessid и ограничен rate-limit.
 */
final class WebmcpHandler
{
    public const PATH = '/alexw-clientsmcp-webmcp.json';

    private const RATE_LIMIT_WINDOW = 60;
    private const RATE_LIMIT_MAX    = 60;

    public static function handle(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($uri !== self::PATH) {
            return;
        }

        if (!Loader::includeModule('alexw.clientsmcp')) {
            return;
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        global $USER;
        $userId = (is_object($USER) && $USER->IsAuthorized()) ? (int)$USER->GetID() : null;

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            self::respondCatalog($userId);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::respondExecute($userId);
        } else {
            http_response_code(405);
        }
        die();
    }

    private static function respondCatalog(?int $userId): void
    {
        $tools = array_map(
            static fn(ToolDefinition $def) => ToolsRegistry::resolve($def)->schema(),
            ToolsRegistry::getForBrowser($userId)
        );

        echo json_encode([
            'tools'  => array_values($tools),
            'sessid' => bitrix_sessid(),
        ], JSON_UNESCAPED_UNICODE);
    }

    private static function respondExecute(?int $userId): void
    {
        $body = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($body)) {
            self::error(400, 'invalid_json');
            return;
        }

        $sentSessid = (string)($body['sessid'] ?? '');
        if ($sentSessid === '' || !hash_equals(bitrix_sessid(), $sentSessid)) {
            self::error(403, 'invalid_sessid');
            return;
        }

        if (!self::rateLimitPass()) {
            self::error(429, 'rate_limit');
            return;
        }

        $name = (string)($body['name'] ?? '');
        $args = is_array($body['arguments'] ?? null) ? $body['arguments'] : [];

        $def = ToolsRegistry::findForBrowser($name, $userId);
        if ($def === null) {
            self::error(404, 'tool_not_found');
            return;
        }

        try {
            $result = ToolsRegistry::resolve($def)->execute($args, $userId);
            echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            self::error(500, 'tool_error', $e->getMessage());
        }
    }

    private static function rateLimitPass(): bool
    {
        $now = time();
        $bucket = &$_SESSION['ALEXW_CLIENTSMCP_WEBMCP_RL'];

        if (!is_array($bucket) || ($bucket['window'] ?? 0) < $now - self::RATE_LIMIT_WINDOW) {
            $bucket = ['window' => $now, 'count' => 0];
        }

        $bucket['count']++;
        return $bucket['count'] <= self::RATE_LIMIT_MAX;
    }

    private static function error(int $code, string $error, string $detail = ''): void
    {
        http_response_code($code);
        echo json_encode([
            'ok'    => false,
            'error' => $error,
            'detail' => $detail,
        ], JSON_UNESCAPED_UNICODE);
    }
}
