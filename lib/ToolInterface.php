<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp;

interface ToolInterface
{
    /**
     * MCP tool descriptor — возвращается в tools/list.
     * Структура: {name, description, inputSchema: {type, properties, required}}
     */
    public function schema(): array;

    /**
     * Выполняет инструмент и возвращает MCP content response.
     * Структура: {content: [{type: 'text', text: '...'}]}
     *
     * @param array    $args   аргументы из tools/call → arguments
     * @param int|null $userId null для публичных инструментов
     */
    public function execute(array $args, ?int $userId): array;
}
