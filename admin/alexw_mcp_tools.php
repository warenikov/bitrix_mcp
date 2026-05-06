<?php

use Alexw\Clientsmcp\ORM\ToolTable;
use Alexw\Clientsmcp\ToolTemplates;
use Bitrix\Main\Loader;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @var CUser $USER */
if (!$USER->IsAdmin()) $APPLICATION->AuthForm('Нет доступа');

Loader::includeModule('alexw.clientsmcp');

// ─── Групповые / одиночные действия ─────────────────────────────────────────

$oSort = new CAdminSorting('alexw_mcp_tools', 'SORT', 'ASC');
$oList = new CAdminList('alexw_mcp_tools', $oSort);

if ($oList->GroupAction() && check_bitrix_sessid()) {
    $ids = array_map('intval', (array)($_REQUEST['ID'] ?? []));
    $action = $_REQUEST['action_target'] ?? '';
    foreach ($ids as $id) {
        if ($id <= 0) continue;
        match ($action) {
            'delete'     => ToolTable::delete($id),
            'activate'   => ToolTable::update($id, ['ACTIVE' => 'Y']),
            'deactivate' => ToolTable::update($id, ['ACTIVE' => 'N']),
            default      => null,
        };
    }
    LocalRedirect($APPLICATION->GetCurPage());
}

if ($_REQUEST['action'] === 'delete' && (int)($_REQUEST['ID'] ?? 0) > 0 && check_bitrix_sessid()) {
    ToolTable::delete((int)$_REQUEST['ID']);
    LocalRedirect($APPLICATION->GetCurPage());
}

// ─── Заголовок и пролог ─────────────────────────────────────────────────────

$APPLICATION->SetTitle('MCP-инструменты');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

if (($_GET['mess_type'] ?? '') === 'OK') {
    CAdminMessage::ShowMessage(['TYPE' => 'OK', 'MESSAGE' => 'Инструмент сохранён']);
}

// ─── Список ─────────────────────────────────────────────────────────────────

$oList->AddAdminContextMenu([]);

// Форма «Создать из шаблона»
?><div style="margin:10px 0 14px; padding:9px 14px; background:#f5f7fa; border:1px solid #d8dde6; border-radius:3px; display:flex; align-items:center; gap:10px;">
    <strong style="white-space:nowrap;">Создать из шаблона:</strong>
    <form method="get" action="alexw_mcp_tool_edit.php" style="display:flex; align-items:center; gap:8px; margin:0;">
        <select name="template" style="min-width:220px;" id="mcp_tpl_select" onchange="document.getElementById('mcp_tpl_btn').disabled = !this.value;">
            <option value="">— выберите шаблон —</option>
            <?php foreach (ToolTemplates::labels() as $tKey => $tLabel): ?>
                <option value="<?= htmlspecialcharsex($tKey) ?>"><?= htmlspecialcharsex($tLabel) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="submit" id="mcp_tpl_btn" value="Создать" class="adm-btn-save" style="padding:3px 12px;" disabled>
    </form>
    <span id="mcp_template_hint" style="color:#888; font-size:12px;"></span>
</div>
<script>
(function() {
    var hints = <?= json_encode(
        array_map(fn(array $t) => $t['description'], ToolTemplates::all()),
        JSON_UNESCAPED_UNICODE
    ) ?>;
    var sel = document.querySelector('select[name="template"]');
    if (!sel) return;
    sel.addEventListener('change', function() {
        document.getElementById('mcp_template_hint').textContent = hints[this.value] || '';
    });
})();
</script>
<?php

$oList->AddHeaders([
    ['id' => 'ID',            'content' => 'ID',           'sort' => 'ID',    'default' => true],
    ['id' => 'NAME',          'content' => 'Код (name)',    'sort' => 'NAME',  'default' => true],
    ['id' => 'TITLE',         'content' => 'Название',      'sort' => 'TITLE', 'default' => true],
    ['id' => 'TYPE',          'content' => 'Тип',           'sort' => 'TYPE',  'default' => true],
    ['id' => 'AUTH_REQUIRED', 'content' => 'Авторизация',   'sort' => false,   'default' => true],
    ['id' => 'ACTIVE',        'content' => 'Активен',       'sort' => 'ACTIVE','default' => true],
    ['id' => 'SORT',          'content' => 'Сорт.',         'sort' => 'SORT',  'default' => true],
]);

$typeLabels = ['builtin' => 'Встроенный', 'iblock' => 'Инфоблок', 'custom' => 'Кастомный'];

$dbResult = ToolTable::getList([
    'order'  => [$oSort->getField() => $oSort->getOrder()],
    'select' => ['ID', 'NAME', 'TITLE', 'TYPE', 'AUTH_REQUIRED', 'ACTIVE', 'SORT'],
]);

while ($row = $dbResult->fetch()) {
    $editUrl = 'alexw_mcp_tool_edit.php?ID=' . $row['ID'];
    $oRow    = $oList->AddRow($row['ID'], $row, $editUrl);

    $oRow->AddViewField('NAME', '<a href="' . $editUrl . '">' . htmlspecialcharsex($row['NAME']) . '</a>');
    $oRow->AddViewField('TITLE', htmlspecialcharsex($row['TITLE']));
    $oRow->AddViewField('TYPE', $typeLabels[$row['TYPE']] ?? $row['TYPE']);
    $oRow->AddViewField('AUTH_REQUIRED', $row['AUTH_REQUIRED'] === 'Y' ? 'Да' : 'Нет');
    $oRow->AddCheckField('ACTIVE');
    $oRow->AddViewField('SORT', $row['SORT']);

    $oRow->AddActions([
        ['ICON' => 'edit',   'TEXT' => 'Редактировать',
         'ACTION' => $oList->ActionRedirect($editUrl)],
        ['ICON' => 'delete', 'TEXT' => 'Удалить',
         'ACTION' => "if(confirm('Удалить инструмент «" . CUtil::JSEscape($row['NAME']) . "»?')) " .
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
