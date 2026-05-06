<?php

$aMenu = [
    [
        'parent_menu' => 'global_menu_services',
        'section'     => 'alexw_clientsmcp',
        'sort'        => 100,
        'url'         => 'alexw_mcp_tools.php',
        'text'        => 'MCP-сервер',
        'title'       => 'Управление MCP-сервером',
        'icon'        => 'default_menu_icon',
        'page_icon'   => 'default_page_icon',
        'items_id'    => 'menu_alexw_mcp',
        'items'       => [
            [
                'url'      => 'alexw_mcp_tools.php',
                'text'     => 'MCP-инструменты',
                'title'    => 'Настройка инструментов MCP-сервера',
                'more_url' => ['alexw_mcp_tool_edit.php'],
            ],
            [
                'url'      => 'alexw_mcp_tokens.php',
                'text'     => 'MCP-токены',
                'title'    => 'Управление токенами доступа',
                'more_url' => ['alexw_mcp_token_edit.php'],
            ],
        ],
    ],
];

return $aMenu;
