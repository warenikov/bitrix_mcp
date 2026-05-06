<?php

use Alexw\Clientsmcp\ORM\TokenTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @var CUser $USER */
if (!$USER->IsAdmin()) $APPLICATION->AuthForm('Нет доступа');

Loader::includeModule('alexw.clientsmcp');

// ─── Действия ────────────────────────────────────────────────────────────────

$oSort = new CAdminSorting('alexw_mcp_tokens', 'ID', 'DESC');
$oList = new CAdminList('alexw_mcp_tokens', $oSort);

if ($oList->GroupAction() && check_bitrix_sessid()) {
    $ids    = array_map('intval', (array)($_REQUEST['ID'] ?? []));
    $action = $_REQUEST['action_target'] ?? '';
    foreach ($ids as $id) {
        if ($id <= 0) continue;
        match ($action) {
            'delete'     => TokenTable::delete($id),
            'activate'   => TokenTable::update($id, ['ACTIVE' => 'Y']),
            'deactivate' => TokenTable::update($id, ['ACTIVE' => 'N']),
            default      => null,
        };
    }
    LocalRedirect($APPLICATION->GetCurPage());
}

if ($_REQUEST['action'] === 'delete' && (int)($_REQUEST['ID'] ?? 0) > 0 && check_bitrix_sessid()) {
    TokenTable::delete((int)$_REQUEST['ID']);
    LocalRedirect($APPLICATION->GetCurPage());
}

// ─── Заголовок ───────────────────────────────────────────────────────────────

$APPLICATION->SetTitle('MCP-токены');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

if (($_GET['mess_type'] ?? '') === 'OK') {
    CAdminMessage::ShowMessage(['TYPE' => 'OK', 'MESSAGE' => 'Токен сохранён']);
}

// ─── Список ──────────────────────────────────────────────────────────────────

$oList->AddAdminContextMenu([
    [
        'TEXT'   => 'Создать токен',
        'LINK'   => 'alexw_mcp_token_edit.php?lang=' . LANGUAGE_ID,
        'TITLE'  => 'Создать новый токен',
        'ICON'   => 'btn_new',
    ],
]);

$oList->AddHeaders([
    ['id' => 'ID',         'content' => 'ID',           'sort' => 'ID',         'default' => true],
    ['id' => 'NAME',       'content' => 'Название',      'sort' => 'NAME',       'default' => true],
    ['id' => 'TOKEN',      'content' => 'Токен',         'sort' => false,        'default' => true],
    ['id' => 'USER',       'content' => 'Пользователь',  'sort' => false,        'default' => true],
    ['id' => 'TOOLS',      'content' => 'Инструменты',   'sort' => false,        'default' => true],
    ['id' => 'ACTIVE',     'content' => 'Активен',       'sort' => 'ACTIVE',     'default' => true],
    ['id' => 'CREATED_AT', 'content' => 'Создан',        'sort' => 'CREATED_AT', 'default' => true],
    ['id' => 'LAST_USED',  'content' => 'Последнее использование', 'sort' => 'LAST_USED', 'default' => true],
]);

$dbResult = TokenTable::getList([
    'order'  => [$oSort->getField() => $oSort->getOrder()],
    'select' => [
        'ID', 'NAME', 'TOKEN', 'USER_ID', 'TOOLS', 'ACTIVE', 'CREATED_AT', 'LAST_USED',
        'USER_LOGIN'     => 'USER.LOGIN',
        'USER_NAME'      => 'USER.NAME',
        'USER_LAST_NAME' => 'USER.LAST_NAME',
    ],
]);

while ($row = $dbResult->fetch()) {
    $editUrl = 'alexw_mcp_token_edit.php?ID=' . $row['ID'];
    $oRow    = $oList->AddRow($row['ID'], $row, $editUrl);

    $oRow->AddViewField('NAME', '<a href="' . $editUrl . '">' . htmlspecialcharsex($row['NAME'] ?: '—') . '</a>');

    // Маскированный токен: первые 10 символов + ****
    $token    = $row['TOKEN'];
    $masked   = strlen($token) > 10 ? htmlspecialcharsex(substr($token, 0, 10)) . '****' : str_repeat('*', strlen($token));
    $oRow->AddViewField('TOKEN', '<code style="font-size:12px;">' . $masked . '</code>');

    // Пользователь
    $login = htmlspecialcharsex($row['USER_LOGIN'] ?? '');
    $name  = trim(htmlspecialcharsex(($row['USER_NAME'] ?? '') . ' ' . ($row['USER_LAST_NAME'] ?? '')));
    $userHtml = $login ? '<a href="user_edit.php?ID=' . (int)$row['USER_ID'] . '">' . $login . '</a>' . ($name ? ' <span style="color:#888;">(' . $name . ')</span>' : '') : '—';
    $oRow->AddViewField('USER', $userHtml);

    // Инструменты
    $toolsJson = $row['TOOLS'];
    if ($toolsJson === null || $toolsJson === '') {
        $toolsHtml = '<span style="color:#888;">Все</span>';
    } else {
        $toolsList = json_decode($toolsJson, true) ?: [];
        $toolsHtml = htmlspecialcharsex(implode(', ', $toolsList)) ?: '—';
    }
    $oRow->AddViewField('TOOLS', $toolsHtml);

    $oRow->AddCheckField('ACTIVE');

    // Даты
    $oRow->AddViewField('CREATED_AT', $row['CREATED_AT'] instanceof DateTime ? $row['CREATED_AT']->format('d.m.Y H:i') : '—');
    $oRow->AddViewField('LAST_USED',  $row['LAST_USED']  instanceof DateTime ? $row['LAST_USED']->format('d.m.Y H:i')  : '<span style="color:#aaa;">никогда</span>');

    $oRow->AddActions([
        ['ICON' => 'edit',   'TEXT' => 'Редактировать', 'ACTION' => $oList->ActionRedirect($editUrl)],
        ['ICON' => 'delete', 'TEXT' => 'Удалить',
         'ACTION' => "if(confirm('Удалить токен «" . CUtil::JSEscape($row['NAME'] ?: $masked) . "»?')) " .
                     $oList->ActionDoGroup($row['ID'], 'delete')],
    ]);
}

$oList->AddGroupActionTable([
    'delete'     => 'Удалить',
    'activate'   => 'Активировать',
    'deactivate' => 'Деактивировать',
]);

$oList->DisplayList();

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
