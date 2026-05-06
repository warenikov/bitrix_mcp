<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp\Tools;

use Alexw\Clientsmcp\ToolDefinition;
use Alexw\Clientsmcp\ToolInterface;

/**
 * Универсальный инструмент для инфоблоков.
 * Конфигурируется через ToolDefinition::config:
 *
 *   iblock_id          — ID инфоблока
 *   operation          — 'list' | 'get'
 *   fields             — базовые поля (пустой массив = ID, NAME, DETAIL_TEXT, DATE_ACTIVE_FROM)
 *   properties         — коды свойств для вывода (пустой массив = не выводить)
 *   search_properties  — коды свойств, по которым ищем вместе с NAME через OR:
 *                          ["PROP_CODE1", "PROP_CODE2"]
 *   filter_properties  — свойства, доступные LLM для динамической фильтрации:
 *                          [{"code": "COLOR", "description": "Цвет: Red, Blue, Green"}]
 *   filter             — статический фильтр (например ['ACTIVE' => 'Y'])
 *   limit              — макс. кол-во элементов для list (default 20, max 100)
 */
final class IblockTool implements ToolInterface
{
    private const DEFAULT_FIELDS = ['ID', 'NAME', 'DETAIL_TEXT', 'DATE_ACTIVE_FROM'];
    private const MAX_LIMIT = 100;

    public function __construct(private readonly ToolDefinition $def) {}

    public function schema(): array
    {
        $config    = $this->def->config;
        $operation = $config['operation'] ?? 'list';

        $properties = match ($operation) {
            'list'  => $this->buildListSchema($config),
            'get'   => [
                'id' => ['type' => 'integer', 'description' => 'ID элемента инфоблока'],
            ],
            default => [],
        };

        return [
            'name'        => $this->def->name,
            'description' => $this->def->description,
            'inputSchema' => [
                'type'       => 'object',
                'properties' => $properties,
            ],
        ];
    }

    public function execute(array $args, ?int $userId): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $config   = $this->def->config;
        $iblockId = (int)($config['iblock_id'] ?? 0);

        if ($iblockId === 0) {
            throw new \RuntimeException('iblock_id не настроен для инструмента ' . $this->def->name);
        }

        $data = match ($config['operation'] ?? 'list') {
            'list'  => $this->executeList($iblockId, $config, $args),
            'get'   => $this->executeGet($iblockId, $config, $args),
            default => throw new \RuntimeException("Неизвестная операция: " . ($config['operation'] ?? '')),
        };

        return [
            'content' => [
                ['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE)],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Schema builders
    // -------------------------------------------------------------------------

    private function buildListSchema(array $config): array
    {
        $filterProps = $this->normalizeFilterProperties($config['filter_properties'] ?? []);
        $searchCodes = $config['search_properties'] ?? [];

        $searchDescription = 'Поисковый запрос по названию элемента';
        if (!empty($searchCodes)) {
            $searchDescription .= '. Поиск также ведётся по свойствам: ' . implode(', ', $searchCodes);
        }

        $params = [
            'query'  => ['type' => 'string',  'description' => $searchDescription],
            'limit'  => ['type' => 'integer', 'description' => 'Максимум элементов', 'default' => $config['limit'] ?? 20],
            'offset' => ['type' => 'integer', 'description' => 'Смещение для постраничной навигации', 'default' => 0],
        ];

        foreach ($filterProps as $prop) {
            $params[strtolower($prop['code'])] = [
                'type'        => 'string',
                'description' => $prop['description'] ?? ('Фильтр по свойству ' . $prop['code']),
            ];
        }

        return $params;
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    private function executeList(int $iblockId, array $config, array $args): array
    {
        $limit       = min((int)($args['limit'] ?? $config['limit'] ?? 20), self::MAX_LIMIT);
        $offset      = max((int)($args['offset'] ?? 0), 0);
        $fields      = $config['fields'] ?? self::DEFAULT_FIELDS;
        $propCodes   = $config['properties'] ?? [];
        $searchCodes = $config['search_properties'] ?? [];
        $filterProps = $this->normalizeFilterProperties($config['filter_properties'] ?? []);

        $filter = array_merge(['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'], $config['filter'] ?? []);

        if (!empty($args['query'])) {
            $filter[] = $this->buildSearchSubfilter($args['query'], $searchCodes);
        }

        foreach ($filterProps as $prop) {
            $paramKey = strtolower($prop['code']);
            if (!empty($args[$paramKey])) {
                $filter['PROPERTY_' . $prop['code']] = $args[$paramKey];
            }
        }

        // Только базовые поля — без PROPERTY_*, чтобы не было дублей по множественным свойствам
        $select = array_unique(array_merge(['ID'], $fields));
        $nav    = $this->buildNav($limit, $offset);

        $rs   = \CIBlockElement::GetList([], $filter, false, $nav, $select);
        $rows = [];
        $ids  = [];
        $skip = isset($nav['nTopCount']) ? $offset : 0;
        $i    = 0;

        while ($row = $rs->GetNext()) {
            if ($i++ < $skip) continue;
            if (count($rows) >= $limit) break;
            $id = (int)$row['ID'];
            if (!isset($rows[$id])) {
                $rows[$id] = $row;
                $ids[]     = $id;
            }
        }

        $propsMap = $this->fetchPropertiesMap($iblockId, $ids, $propCodes);

        return array_map(
            fn(int $id) => $this->buildRow($rows[$id], $fields, $propCodes, $propsMap[$id] ?? []),
            $ids
        );
    }

    private function executeGet(int $iblockId, array $config, array $args): array
    {
        $id        = (int)($args['id'] ?? 0);
        $fields    = $config['fields'] ?? self::DEFAULT_FIELDS;
        $propCodes = $config['properties'] ?? [];

        if ($id === 0) {
            throw new \RuntimeException('Параметр id обязателен');
        }

        $select = array_unique(array_merge(['ID'], $fields));
        $rs     = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, 'ID' => $id, 'ACTIVE' => 'Y'],
            false,
            ['nTopCount' => 1],
            $select
        );

        $row = $rs->GetNext();
        if (!$row) return [];

        $propsMap = $this->fetchPropertiesMap($iblockId, [$id], $propCodes);
        return $this->buildRow($row, $fields, $propCodes, $propsMap[$id] ?? []);
    }

    /**
     * Загружает значения свойств одним запросом для нескольких элементов.
     * Возвращает [elementId => [propCode => scalar|array|null]]
     */
    private function fetchPropertiesMap(int $iblockId, array $ids, array $propCodes): array
    {
        if (empty($ids) || empty($propCodes)) return [];

        $values = [];
        \CIBlockElement::GetPropertyValuesArray(
            $values,
            $iblockId,
            ['ID' => $ids],
            ['CODE' => $propCodes]
        );

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = [];
            $props = $values[$id] ?? [];
            foreach ($propCodes as $code) {
                if (!array_key_exists($code, $props)) {
                    continue;
                }
                $prop = $props[$code];
                // Множественное свойство: массив массивов
                if (isset($prop[0]) && is_array($prop[0])) {
                    $vals = array_values(array_filter(
                        array_column($prop, 'VALUE'),
                        fn($v) => $v !== null && $v !== '' && $v !== false
                    ));
                    $result[$id][$code] = count($vals) === 1 ? $vals[0] : $vals;
                } else {
                    $val = $prop['VALUE'] ?? null;
                    $result[$id][$code] = ($val === '' || $val === false) ? null : $val;
                }
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** OR-субфильтр: поиск по %NAME + %PROPERTY_CODE для каждого кода */
    private function buildSearchSubfilter(string $query, array $searchCodes): array
    {
        $sub = ['LOGIC' => 'OR', '%NAME' => $query];
        foreach ($searchCodes as $code) {
            $sub['%PROPERTY_' . $code] = $query;
        }

        return $sub;
    }

    /** Постраничная навигация: страницы для кратного offset, nTopCount для некратного */
    private function buildNav(int $limit, int $offset): array
    {
        if ($offset === 0) {
            return ['nPageSize' => $limit, 'iNumPage' => 1];
        }

        if ($offset % $limit === 0) {
            return ['nPageSize' => $limit, 'iNumPage' => (int)($offset / $limit) + 1];
        }

        return ['nTopCount' => $limit + $offset];
    }

    private function buildRow(array $row, array $fields, array $propCodes, array $fetchedProps = []): array
    {
        $item = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $row)) {
                $item[strtolower($field)] = $row[$field];
            }
        }

        if (!empty($propCodes) && !empty($fetchedProps)) {
            $props = [];
            foreach ($propCodes as $code) {
                if (array_key_exists($code, $fetchedProps)) {
                    $props[strtolower($code)] = $fetchedProps[$code];
                }
            }
            if (!empty($props)) {
                $item['properties'] = $props;
            }
        }

        return $item;
    }

    /**
     * Нормализует filter_properties в единый формат: [['code' => ..., 'description' => ...]]
     * Принимает как строки ["CODE"], так и объекты [{"code": "CODE", "description": "..."}]
     */
    private function normalizeFilterProperties(array $raw): array
    {
        $result = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $result[] = ['code' => $item, 'description' => null];
            } elseif (is_array($item) && !empty($item['code'])) {
                $result[] = ['code' => $item['code'], 'description' => $item['description'] ?? null];
            }
        }

        return $result;
    }
}
