<?php

use Alexw\Clientsmcp\ORM\TokenTable;
use Alexw\Clientsmcp\ORM\ToolTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @var CUser $USER */
if (!$USER->IsAdmin()) $APPLICATION->AuthForm('Нет доступа');

Loader::includeModule('alexw.clientsmcp');

$id      = (int)($_REQUEST['ID'] ?? 0);
$isNew   = $id === 0;
$item    = [];
$message = null;

// ─── Загрузка существующего токена ───────────────────────────────────────────

if (!$isNew) {
    $rs = TokenTable::getList([
        'filter' => ['=ID' => $id],
        'select' => ['ID', 'NAME', 'TOKEN', 'USER_ID', 'TOOLS', 'ACTIVE'],
    ]);
    $item = $rs->fetch() ?: [];
    if (!$item) {
        $message = ['TYPE' => 'ERROR', 'MESSAGE' => 'Токен не найден'];
        $isNew   = true;
        $id      = 0;
    }
}

// ─── Сохранение ──────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $name   = trim($_POST['NAME'] ?? '');
    $token  = trim($_POST['TOKEN'] ?? '');
    $userId = (int)($_POST['USER_ID'] ?? 0);
    $active = ($_POST['ACTIVE'] ?? 'N') === 'Y' ? 'Y' : 'N';

    // Инструменты
    $toolsMode = $_POST['tools_mode'] ?? 'all';
    $toolsVal  = null;
    if ($toolsMode === 'selected') {
        $selected = array_filter(array_map('trim', (array)($_POST['TOOLS'] ?? [])));
        $toolsVal = !empty($selected) ? json_encode(array_values($selected), JSON_UNESCAPED_UNICODE) : null;
    }

    $errors = [];
    if ($name === '') $errors[] = 'Введите название токена';
    if ($token === '') $errors[] = 'Введите или сгенерируйте токен';
    if ($userId <= 0) $errors[] = 'Укажите пользователя';

    if (!$errors) {
        $fields = [
            'NAME'    => $name,
            'TOKEN'   => $token,
            'USER_ID' => $userId,
            'ACTIVE'  => $active,
            'TOOLS'   => $toolsVal,
        ];

        if ($isNew) {
            $fields['CREATED_AT'] = new DateTime();
            $result = TokenTable::add($fields);
            if ($result->isSuccess()) {
                LocalRedirect('alexw_mcp_tokens.php?mess_type=OK&lang=' . LANGUAGE_ID);
            } else {
                $message = ['TYPE' => 'ERROR', 'MESSAGE' => implode('<br>', $result->getErrorMessages())];
            }
        } else {
            $result = TokenTable::update($id, $fields);
            if ($result->isSuccess()) {
                LocalRedirect('alexw_mcp_tokens.php?mess_type=OK&lang=' . LANGUAGE_ID);
            } else {
                $message = ['TYPE' => 'ERROR', 'MESSAGE' => implode('<br>', $result->getErrorMessages())];
            }
        }

        // Восстановить значения формы при ошибке
        $item = array_merge($item, $fields, ['ID' => $id]);
    } else {
        $message = ['TYPE' => 'ERROR', 'MESSAGE' => implode('<br>', $errors)];
        $item = array_merge($item, [
            'NAME' => $name, 'TOKEN' => $token, 'USER_ID' => $userId,
            'ACTIVE' => $active, 'TOOLS' => $toolsVal,
        ]);
    }
}

// ─── Все инструменты для вкладки «Доступ» ────────────────────────────────────

$allTools = [];
$rsTools  = ToolTable::getList([
    'select' => ['NAME', 'TITLE'],
    'filter' => ['=ACTIVE' => 'Y'],
    'order'  => ['SORT' => 'ASC', 'NAME' => 'ASC'],
]);
while ($t = $rsTools->fetch()) {
    $allTools[] = $t;
}

$selectedTools = [];
$toolsMode     = 'all';
if (!empty($item['TOOLS'])) {
    $selectedTools = json_decode($item['TOOLS'], true) ?: [];
    $toolsMode     = 'selected';
}

// ─── Пользователь ────────────────────────────────────────────────────────────

$userId   = (int)($item['USER_ID'] ?? 0);
$userInfo = '';
if ($userId > 0) {
    $rsUser = \CUser::GetByID($userId);
    if ($u = $rsUser->Fetch()) {
        $userInfo = htmlspecialcharsex($u['LOGIN']) . ' (' . htmlspecialcharsex(trim($u['NAME'] . ' ' . $u['LAST_NAME'])) . ')';
    }
}

// ─── Заголовок и пролог ──────────────────────────────────────────────────────

$APPLICATION->SetTitle($isNew ? 'Новый токен' : 'Редактирование токена');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

if ($message) {
    CAdminMessage::ShowMessage($message);
}

// ─── Форма ───────────────────────────────────────────────────────────────────

$tabControl = new CAdminTabControl('tabControl', [
    ['DIV' => 'tab_main',   'TAB' => 'Основное',            'TITLE' => 'Основные параметры токена'],
    ['DIV' => 'tab_access', 'TAB' => 'Доступ к инструментам', 'TITLE' => 'Ограничение по инструментам'],
]);
$tabControl->Begin();
?>
<form method="post" action="alexw_mcp_token_edit.php" name="edit1" enctype="multipart/form-data">
    <?= bitrix_sessid_post() ?>
    <?php if ($id): ?><input type="hidden" name="ID" value="<?= $id ?>"><?php endif; ?>

    <?php $tabControl->BeginNextTab(); ?>

    <tr>
        <td width="40%"><b>Название:</b></td>
        <td><input type="text" name="NAME" size="50" value="<?= htmlspecialcharsex($item['NAME'] ?? '') ?>"></td>
    </tr>
    <tr>
        <td><b>Токен:</b></td>
        <td>
            <input type="text" name="TOKEN" id="mcp_token_val" size="50"
                   value="<?= htmlspecialcharsex($item['TOKEN'] ?? '') ?>"
                   style="font-family:monospace;">
            <input type="button" value="Сгенерировать"
                   onclick="(function(){var a='mcp_';var b='0123456789abcdef';var r='';for(var i=0;i<32;i++)r+=b[Math.floor(Math.random()*16)];document.getElementById('mcp_token_val').value=a+r;})()"
                   class="adm-btn" style="margin-left:6px;">
        </td>
    </tr>
    <tr>
        <td><b>Пользователь:</b></td>
        <td>
            <input type="text" name="USER_ID" id="mcp_user_id" size="10"
                   value="<?= $userId > 0 ? $userId : '' ?>"
                   onchange="document.getElementById('mcp_user_hint').textContent=''"
                   style="width:80px;">
            <input type="button" value="Выбрать"
                   onclick="window.open('/bitrix/admin/user_search.php?lang=<?= LANGUAGE_ID ?>&FID=mcp_user_id&formName=edit1&mode=popup','user_search','width=780,height=560,resizable=yes,scrollbars=yes')"
                   class="adm-btn" style="margin-left:6px;">
            <span id="mcp_user_hint" style="margin-left:8px; color:#555; font-size:12px;">
                <?= $userInfo ?>
            </span>
        </td>
    </tr>
    <tr>
        <td><b>Активен:</b></td>
        <td>
            <input type="checkbox" name="ACTIVE" value="Y" id="mcp_active"
                <?= ($item['ACTIVE'] ?? 'Y') === 'Y' ? 'checked' : '' ?>>
            <label for="mcp_active">Да</label>
        </td>
    </tr>

    <?php $tabControl->BeginNextTab(); ?>

    <tr>
        <td colspan="2" style="padding:10px 0 6px;">
            <label style="margin-right:20px;">
                <input type="radio" name="tools_mode" value="all" id="tools_all"
                    <?= $toolsMode === 'all' ? 'checked' : '' ?>
                    onchange="document.getElementById('mcp_tools_table').style.display='none'">
                Все инструменты
            </label>
            <label>
                <input type="radio" name="tools_mode" value="selected" id="tools_selected"
                    <?= $toolsMode === 'selected' ? 'checked' : '' ?>
                    onchange="document.getElementById('mcp_tools_table').style.display=''">
                Только выбранные
            </label>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <div id="mcp_tools_table" style="<?= $toolsMode === 'selected' ? '' : 'display:none;' ?>">
                <?php if (!empty($allTools)): ?>
                <table cellpadding="4" cellspacing="0" style="border-collapse:collapse; min-width:400px;">
                    <thead>
                        <tr style="background:#f0f0f0; border-bottom:1px solid #ccc;">
                            <th style="text-align:left; padding:4px 8px;"><input type="checkbox" id="mcp_tools_all_cb" onchange="document.querySelectorAll('input[name=\'TOOLS[]\']').forEach(function(c){c.checked=this.checked},this)"> Все</th>
                            <th style="text-align:left; padding:4px 8px;">Код</th>
                            <th style="text-align:left; padding:4px 8px;">Название</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allTools as $tool): ?>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:4px 8px;">
                                <input type="checkbox" name="TOOLS[]"
                                       value="<?= htmlspecialcharsex($tool['NAME']) ?>"
                                       <?= in_array($tool['NAME'], $selectedTools, true) ? 'checked' : '' ?>>
                            </td>
                            <td style="padding:4px 8px; font-family:monospace; font-size:12px;">
                                <?= htmlspecialcharsex($tool['NAME']) ?>
                            </td>
                            <td style="padding:4px 8px;">
                                <?= htmlspecialcharsex($tool['TITLE']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color:#888; margin:8px 0;">Нет активных инструментов. Сначала создайте инструменты.</p>
                <?php endif; ?>
            </div>
        </td>
    </tr>

    <?php $tabControl->Buttons(['btnSave' => true, 'btnApply' => false, 'btnCancel' => true, 'back_url' => 'alexw_mcp_tokens.php']); ?>
</form>
<?php
$tabControl->End();
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
