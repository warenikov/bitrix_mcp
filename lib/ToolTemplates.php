<?php

declare(strict_types=1);

namespace Alexw\Clientsmcp;

final class ToolTemplates
{
    private static function definitions(): array
    {
        return [
            'blank' => [
                'label'       => 'Пустой шаблон',
                'description' => 'Чистая форма без предустановок.',
                'item' => [
                    'TYPE'          => 'iblock',
                    'NAME'          => '',
                    'TITLE'         => '',
                    'DESCRIPTION'   => '',
                    'AUTH_REQUIRED' => 'Y',
                    'ACTIVE'        => 'Y',
                    'SORT'          => 500,
                ],
                'config' => [
                    'operation'  => 'list',
                    'fields'     => ['ID', 'NAME', 'DETAIL_TEXT', 'DATE_CREATE'],
                    'properties' => [],
                    'limit'      => 20,
                    'filter'     => [],
                ],
            ],

            'search_iblock' => [
                'label'       => 'Поиск по инфоблоку',
                'description' => 'Базовый поиск элементов по названию. Поддерживает текстовый поиск и постраничную навигацию.',
                'item' => [
                    'TYPE'          => 'iblock',
                    'NAME'          => '',
                    'TITLE'         => '',
                    'DESCRIPTION'   => 'Поиск элементов по названию. Поддерживает текстовый поиск и постраничную навигацию.',
                    'AUTH_REQUIRED' => 'Y',
                    'ACTIVE'        => 'Y',
                    'SORT'          => 500,
                ],
                'config' => [
                    'operation'         => 'list',
                    'fields'            => ['ID', 'NAME', 'DETAIL_TEXT', 'DATE_CREATE'],
                    'properties'        => [],
                    'search_properties' => [],
                    'filter_properties' => [],
                    'limit'             => 20,
                    'filter'            => ['ACTIVE' => 'Y'],
                ],
            ],

            'get_by_id' => [
                'label'       => 'Карточка по ID',
                'description' => 'Возвращает полную информацию об элементе по его ID, включая все свойства.',
                'item' => [
                    'TYPE'          => 'iblock',
                    'NAME'          => '',
                    'TITLE'         => '',
                    'DESCRIPTION'   => 'Возвращает полную информацию об элементе по его ID, включая все свойства.',
                    'AUTH_REQUIRED' => 'Y',
                    'ACTIVE'        => 'Y',
                    'SORT'          => 500,
                ],
                'config' => [
                    'operation'  => 'get',
                    'fields'     => ['ID', 'NAME', 'DETAIL_TEXT', 'DATE_CREATE'],
                    'properties' => [],
                    'filter'     => ['ACTIVE' => 'Y'],
                ],
            ],

            'filtered_list' => [
                'label'       => 'Фильтруемый список',
                'description' => 'Список с возможностью фильтрации по свойствам инфоблока. После выбора инфоблока укажите свойства и допустимые значения в колонке «Фильтр LLM».',
                'item' => [
                    'TYPE'          => 'iblock',
                    'NAME'          => '',
                    'TITLE'         => '',
                    'DESCRIPTION'   => 'Поиск и фильтрация элементов. Доступные фильтры: укажите значения параметров для уточнения результатов.',
                    'AUTH_REQUIRED' => 'Y',
                    'ACTIVE'        => 'Y',
                    'SORT'          => 500,
                ],
                'config' => [
                    'operation'         => 'list',
                    'fields'            => ['ID', 'NAME', 'DETAIL_TEXT', 'DATE_CREATE'],
                    'properties'        => [],
                    'search_properties' => [],
                    'filter_properties' => [],
                    'limit'             => 20,
                    'filter'            => ['ACTIVE' => 'Y'],
                ],
            ],

            'section_list' => [
                'label'       => 'Список по разделу',
                'description' => 'Элементы из конкретного раздела инфоблока. Выберите инфоблок и укажите нужный раздел в статическом фильтре.',
                'item' => [
                    'TYPE'          => 'iblock',
                    'NAME'          => '',
                    'TITLE'         => '',
                    'DESCRIPTION'   => 'Возвращает список элементов из раздела. Поддерживает поиск по названию и постраничную навигацию.',
                    'AUTH_REQUIRED' => 'Y',
                    'ACTIVE'        => 'Y',
                    'SORT'          => 500,
                ],
                'config' => [
                    'operation'         => 'list',
                    'fields'            => ['ID', 'NAME', 'DETAIL_TEXT', 'DATE_CREATE'],
                    'properties'        => [],
                    'search_properties' => [],
                    'filter_properties' => [],
                    'limit'             => 20,
                    'filter'            => ['ACTIVE' => 'Y', 'SECTION_ID' => ''],
                ],
            ],
        ];
    }

    /** Все шаблоны */
    public static function all(): array
    {
        return self::definitions();
    }

    /** Ключ → метка для select */
    public static function labels(): array
    {
        return array_map(fn(array $t) => $t['label'], self::definitions());
    }

    /** Шаблон по ключу или null */
    public static function get(string $key): ?array
    {
        return self::definitions()[$key] ?? null;
    }
}
