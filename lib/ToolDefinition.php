<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp;

final class ToolDefinition
{
    public function __construct(
        public readonly int      $id,
        public readonly string   $name,
        public readonly string   $title,
        public readonly string   $description,
        public readonly ToolType $type,
        public readonly array    $config,
        public readonly bool     $authRequired,
        public readonly bool     $browserSafe,
        public readonly bool     $active,
        public readonly int      $sort,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:           (int)$row['ID'],
            name:         $row['NAME'],
            title:        $row['TITLE'] ?? '',
            description:  $row['DESCRIPTION'] ?? '',
            type:         ToolType::from($row['TYPE']),
            config:       is_string($row['CONFIG']) ? (json_decode($row['CONFIG'], true) ?? []) : [],
            authRequired: $row['AUTH_REQUIRED'] === 'Y',
            browserSafe:  ($row['BROWSER_SAFE'] ?? 'N') === 'Y',
            active:       $row['ACTIVE'] === 'Y',
            sort:         (int)($row['SORT'] ?? 500),
        );
    }
}
