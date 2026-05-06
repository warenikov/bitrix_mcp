<?php

use Alexw\Clientsmcp\ORM\ToolTable;
use Alexw\Clientsmcp\ToolTemplates;
use Bitrix\Main\Loader;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @var CUser $USER */
if (!$USER->IsAdmin()) $APPLICATION->AuthForm('Нет доступа');

Loader::includeModule('alexw.clientsmcp');
Loader::includeModule('iblock');

$ID    = (int)($_REQUEST['ID'] ?? 0);
$error = '';

// ─── Стандартные поля ИБ для вывода ─────────────────────────────────────────

$standardFields = [
    'ID'               => 'ID',
    'NAME'             => 'Название',
    'DETAIL_TEXT'      => 'Детальный текст',
    'PREVIEW_TEXT'     => 'Анонс',
    'DATE_ACTIVE_FROM' => 'Дата начала активности',
    'DATE_ACTIVE_TO'   => 'Дата окончания активности',
    'DATE_CREATE'      => 'Дата создания',
    'SORT'             => 'Сортировка',
    'CODE'             => 'Символьный код',
    'XML_ID'           => 'XML ID',
];

// ID отмечен по умолчанию и заблокирован; эти поля отмечены для новых инструментов
$defaultFields = ['ID', 'NAME', 'DETAIL_TEXT', 'DATE_CREATE'];

// ─── Helpers ────────────────────────────────────────────────────────────────

function checked_if(bool $cond): string  { return $cond ? ' checked' : ''; }
function selected_if(bool $cond): string { return $cond ? ' selected' : ''; }
function hsc(mixed $v): string           { return htmlspecialcharsex((string)$v); }

function renderFilterValue(string $key, string $value, array $filterableFields): string
{
    $field  = $filterableFields[$key] ?? [];
    $vtype  = $field['value_type'] ?? 'text';
    $values = $field['values']     ?? [];

    if (($vtype === 'section' || $vtype === 'enum') && !empty($values)) {
        $opts = '<option value="">— выберите —</option>';
        foreach ($values as $k => $label) {
            $sel   = ((string)$value === (string)$k) ? ' selected' : '';
            $opts .= '<option value="' . hsc((string)$k) . '"' . $sel . '>' . hsc($label) . '</option>';
        }
        return '<select name="filter_row_value[]">' . $opts . '</select>';
    }

    return match ($vtype) {
        'boolean' => '<select name="filter_row_value[]">'
                   . '<option value="Y"' . ($value === 'Y' ? ' selected' : '') . '>Да (Y)</option>'
                   . '<option value="N"' . ($value === 'N' ? ' selected' : '') . '>Нет (N)</option>'
                   . '</select>',
        'number'  => '<input type="number" name="filter_row_value[]" value="' . hsc($value) . '" size="10">',
        'date'    => '<input type="date"   name="filter_row_value[]" value="' . hsc($value) . '">',
        default   => '<input type="text"   name="filter_row_value[]" value="' . hsc($value) . '" size="25">',
    };
}

// ─── Удаление ────────────────────────────────────────────────────────────────

if (($_REQUEST['action'] ?? '') === 'delete' && $ID > 0 && check_bitrix_sessid()) {
    ToolTable::delete($ID);
    LocalRedirect('alexw_mcp_tools.php');
}

// ─── Загрузка существующего ──────────────────────────────────────────────────

$arItem   = [];
$arConfig = [];

if ($ID > 0) {
    $row = ToolTable::getById($ID)->fetch();
    if ($row) {
        $arItem   = $row;
        $arConfig = is_string($row['CONFIG']) ? (json_decode($row['CONFIG'], true) ?? []) : [];
    }
}

// ─── Применение шаблона (только для новых инструментов) ─────────────────────

if ($ID === 0 && isset($_GET['template']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $tpl = ToolTemplates::get((string)$_GET['template']);
    if ($tpl !== null) {
        $arItem   = $tpl['item'];
        $arConfig = $tpl['config'];
    }
}

// ─── Сохранение ──────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()
    && ($_POST['save_action'] ?? '') !== 'reload_iblock')
{
    $type   = $_POST['TYPE'] ?? 'builtin';
    $config = [];

    if ($type === 'iblock') {
        $iblockId  = (int)($_POST['iblock_id'] ?? 0);
        $operation = ($_POST['operation'] ?? 'list') === 'get' ? 'get' : 'list';
        $limit     = max(1, min(100, (int)($_POST['limit'] ?? 20)));

        // Поля вывода — ID всегда первым
        $iblockFields = array_values(array_filter((array)($_POST['fields'] ?? []), 'strlen'));
        if (!in_array('ID', $iblockFields, true)) array_unshift($iblockFields, 'ID');

        $properties = array_values(array_filter((array)($_POST['properties'] ?? []), 'strlen'));

        // Статический фильтр из строк ключ/значение
        $filterKeys   = (array)($_POST['filter_row_key']   ?? []);
        $filterValues = (array)($_POST['filter_row_value'] ?? []);
        $staticFilter = [];
        foreach ($filterKeys as $i => $fKey) {
            $fKey = trim($fKey);
            $fVal = trim((string)($filterValues[$i] ?? ''));
            if ($fKey !== '' && $fVal !== '') {
                $staticFilter[$fKey] = $fVal;
            }
        }

        $config = [
            'iblock_id'  => $iblockId,
            'operation'  => $operation,
            'fields'     => $iblockFields,
            'properties' => $properties,
            'limit'      => $limit,
            'filter'     => $staticFilter,
        ];

        if ($operation === 'list') {
            $config['search_properties'] = array_values(
                array_filter((array)($_POST['search_properties'] ?? []), 'strlen')
            );

            $filterPropEnabled = (array)($_POST['filter_prop_enabled'] ?? []);
            $filterPropDescs   = (array)($_POST['filter_prop_desc']    ?? []);
            $filterProps = [];
            foreach ($filterPropEnabled as $code) {
                $fp = ['code' => $code];
                if (!empty($filterPropDescs[$code])) {
                    $fp['description'] = trim($filterPropDescs[$code]);
                }
                $filterProps[] = $fp;
            }
            $config['filter_properties'] = $filterProps;
        }

    } elseif ($type === 'builtin' || $type === 'custom') {
        $config = ['class' => trim($_POST['class_name'] ?? '')];
    }

    $saveFields = [
        'NAME'          => trim($_POST['NAME'] ?? ''),
        'TITLE'         => trim($_POST['TITLE'] ?? ''),
        'DESCRIPTION'   => trim($_POST['DESCRIPTION'] ?? ''),
        'TYPE'          => $type,
        'AUTH_REQUIRED' => ($_POST['AUTH_REQUIRED'] ?? '') === 'Y' ? 'Y' : 'N',
        'ACTIVE'        => ($_POST['ACTIVE'] ?? '') === 'Y' ? 'Y' : 'N',
        'SORT'          => max(0, (int)($_POST['SORT'] ?? 500)),
        'CONFIG'        => json_encode($config, JSON_UNESCAPED_UNICODE),
    ];

    if ($ID > 0) {
        $result = ToolTable::update($ID, $saveFields);
    } else {
        $result = ToolTable::add($saveFields);
        if ($result->isSuccess()) $ID = $result->getId();
    }

    if ($result->isSuccess()) {
        $arItem   = array_merge($arItem, $saveFields);
        $arConfig = $config;
        if (isset($_POST['apply'])) {
            LocalRedirect('alexw_mcp_tool_edit.php?ID=' . $ID . '&mess_type=OK');
        } else {
            LocalRedirect('alexw_mcp_tools.php?mess_type=OK');
        }
    } else {
        $error = implode(', ', $result->getErrorMessages());
    }
}

// ─── Reload iblock: сохраняем значения формы, не записываем в БД ─────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['save_action'] ?? '') === 'reload_iblock') {
    $arItem['TYPE']          = $_POST['TYPE']          ?? ($arItem['TYPE']          ?? 'iblock');
    $arItem['NAME']          = $_POST['NAME']          ?? ($arItem['NAME']          ?? '');
    $arItem['TITLE']         = $_POST['TITLE']         ?? ($arItem['TITLE']         ?? '');
    $arItem['DESCRIPTION']   = $_POST['DESCRIPTION']   ?? ($arItem['DESCRIPTION']   ?? '');
    $arItem['AUTH_REQUIRED'] = $_POST['AUTH_REQUIRED'] ?? ($arItem['AUTH_REQUIRED'] ?? 'Y');
    $arItem['ACTIVE']        = $_POST['ACTIVE']        ?? ($arItem['ACTIVE']        ?? 'Y');
    $arItem['SORT']          = $_POST['SORT']          ?? ($arItem['SORT']          ?? 500);

    // Восстанавливаем фильтр из строк POST
    $filterKeys   = (array)($_POST['filter_row_key']   ?? []);
    $filterValues = (array)($_POST['filter_row_value'] ?? []);
    $reloadFilter = [];
    foreach ($filterKeys as $i => $fKey) {
        $fKey = trim($fKey);
        if ($fKey !== '') $reloadFilter[$fKey] = trim((string)($filterValues[$i] ?? ''));
    }

    $arConfig = array_merge($arConfig, [
        'iblock_id'         => (int)($_POST['iblock_id'] ?? 0),
        'operation'         => $_POST['operation'] ?? 'list',
        'fields'            => (array)($_POST['fields'] ?? []),
        'properties'        => (array)($_POST['properties'] ?? []),
        'search_properties' => (array)($_POST['search_properties'] ?? []),
        'limit'             => (int)($_POST['limit'] ?? 20),
        'filter'            => $reloadFilter,
    ]);
}

// ─── iblock_id для отображения свойств ──────────────────────────────────────

$displayIblockId = (int)($arConfig['iblock_id'] ?? 0);

// ─── Свойства выбранного ИБ ─────────────────────────────────────────────────

$iblockProperties     = [];  // code => name
$iblockPropertiesFull = [];  // code => full row (нужен тип)
$iblockPropertyEnums  = [];  // code => [value => label] для L-свойств
$iblockSections       = [];  // id => name

if ($displayIblockId > 0) {
    $propRes = CIBlockProperty::GetList(
        ['SORT' => 'ASC', 'NAME' => 'ASC'],
        ['IBLOCK_ID' => $displayIblockId, 'ACTIVE' => 'Y']
    );
    while ($prop = $propRes->Fetch()) {
        $iblockProperties[$prop['CODE']]     = $prop['NAME'] ?: $prop['CODE'];
        $iblockPropertiesFull[$prop['CODE']] = $prop;

        if ($prop['PROPERTY_TYPE'] === 'L') {
            $enumRes = CIBlockPropertyEnum::GetList(
                ['SORT' => 'ASC', 'VALUE' => 'ASC'],
                ['PROPERTY_ID' => $prop['ID'], 'ACTIVE' => 'Y']
            );
            $enumMap = [];
            while ($enum = $enumRes->Fetch()) {
                $enumMap[$enum['VALUE']] = $enum['VALUE'];
            }
            $iblockPropertyEnums[$prop['CODE']] = $enumMap;
        }
    }

    $secRes = CIBlockSection::GetList(
        ['LEFT_MARGIN' => 'ASC'],
        ['IBLOCK_ID' => $displayIblockId, 'ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N'],
        false,
        ['ID', 'NAME', 'DEPTH_LEVEL']
    );
    while ($sec = $secRes->Fetch()) {
        $prefix = str_repeat('— ', max(0, (int)$sec['DEPTH_LEVEL'] - 1));
        $iblockSections[(int)$sec['ID']] = $prefix . $sec['NAME'];
    }
}

// ─── Поля для статического фильтра (select + тип значения) ──────────────────

$filterableFields = [
    'ACTIVE'           => ['label' => 'Активность',         'value_type' => 'boolean'],
    'SECTION_ID'       => [
        'label'      => 'Раздел',
        'value_type' => !empty($iblockSections) ? 'section' : 'number',
        'values'     => $iblockSections,
    ],
    '%NAME'            => ['label' => 'Название (содержит)', 'value_type' => 'text'],
    'CODE'             => ['label' => 'Символьный код',      'value_type' => 'text'],
    'XML_ID'           => ['label' => 'XML ID',              'value_type' => 'text'],
    'DATE_ACTIVE_FROM' => ['label' => 'Дата активности от',  'value_type' => 'date'],
    'DATE_ACTIVE_TO'   => ['label' => 'Дата активности до',  'value_type' => 'date'],
    'DATE_CREATE'      => ['label' => 'Дата создания',       'value_type' => 'date'],
];

foreach ($iblockPropertiesFull as $pCode => $prop) {
    $isEnum = $prop['PROPERTY_TYPE'] === 'L' && !empty($iblockPropertyEnums[$pCode]);
    $filterableFields['PROPERTY_' . $pCode] = [
        'label'      => 'Свойство: ' . ($prop['NAME'] ?: $pCode),
        'value_type' => $isEnum ? 'enum' : ($prop['PROPERTY_TYPE'] === 'N' ? 'number' : 'text'),
        'values'     => $isEnum ? $iblockPropertyEnums[$pCode] : [],
    ];
}

// HTML строк <option> для JS-добавляемых строк фильтра
$filterSelectOptionsHtml  = '<option value="">— выберите поле —</option>';
$filterSelectOptionsHtml .= '<optgroup label="Стандартные поля">';
foreach (array_slice($filterableFields, 0, 8) as $key => $info) {
    $filterSelectOptionsHtml .= '<option value="' . hsc($key) . '">' . hsc($info['label']) . '</option>';
}
$filterSelectOptionsHtml .= '</optgroup>';
if (!empty($iblockPropertiesFull)) {
    $filterSelectOptionsHtml .= '<optgroup label="Свойства инфоблока">';
    foreach ($iblockPropertiesFull as $pCode => $prop) {
        $pKey = 'PROPERTY_' . $pCode;
        $filterSelectOptionsHtml .= '<option value="' . hsc($pKey) . '">Свойство: ' . hsc($prop['NAME'] ?: $pCode) . '</option>';
    }
    $filterSelectOptionsHtml .= '</optgroup>';
}

// ─── Список инфоблоков ───────────────────────────────────────────────────────

$allIblocks = [];
$ibRes = CIBlock::GetList(['SORT' => 'ASC', 'NAME' => 'ASC'], ['ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N']);
while ($ib = $ibRes->Fetch()) {
    $allIblocks[$ib['ID']] = '[' . $ib['ID'] . '] ' . $ib['NAME'];
}

// ─── Значения для формы ──────────────────────────────────────────────────────

$cfgFields         = (array)($arConfig['fields'] ?? []);
$cfgProperties     = (array)($arConfig['properties'] ?? []);
$cfgSearchProps    = (array)($arConfig['search_properties'] ?? []);
$cfgFilterProps    = (array)($arConfig['filter_properties'] ?? []);
$cfgFilterPropsMap = [];
foreach ($cfgFilterProps as $fp) {
    $code = is_array($fp) ? ($fp['code'] ?? '') : (string)$fp;
    $desc = is_array($fp) ? ($fp['description'] ?? '') : '';
    if ($code) $cfgFilterPropsMap[$code] = $desc;
}
$cfgOperation  = $arConfig['operation'] ?? 'list';
$cfgLimit      = $arConfig['limit'] ?? 20;
$cfgFilter     = (array)($arConfig['filter'] ?? []);

// Предустановки для нового инструмента
if (empty($cfgFields)) $cfgFields = $defaultFields;
// ID — всегда присутствует
if (!in_array('ID', $cfgFields, true)) array_unshift($cfgFields, 'ID');

$curType = $arItem['TYPE'] ?? 'iblock';

// ─── Шапка страницы ─────────────────────────────────────────────────────────

$aTabs = [
    ['DIV' => 'tab_main',   'TAB' => 'Основное',     'TITLE' => 'Основные параметры инструмента'],
    ['DIV' => 'tab_config', 'TAB' => 'Конфигурация', 'TITLE' => 'Настройки инструмента'],
];
$tabControl = new CAdminTabControl('tabControl', $aTabs);

$APPLICATION->SetTitle($ID > 0 ? 'Инструмент: ' . hsc($arItem['NAME'] ?? '') : 'Новый инструмент');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$ctxMenu = [['TEXT' => 'К списку', 'LINK' => 'alexw_mcp_tools.php', 'ICON' => 'btn_list']];
if ($ID > 0) {
    $ctxMenu[] = [
        'TEXT'   => 'Удалить',
        'ICON'   => 'btn_delete',
        'ACTION' => "if(confirm('Удалить инструмент?')) window.location='alexw_mcp_tool_edit.php?ID=$ID&action=delete&sessid=" . bitrix_sessid() . "'",
    ];
}
(new CAdminContextMenu($ctxMenu))->Show();

if (($_GET['mess_type'] ?? '') === 'OK') CAdminMessage::ShowMessage(['TYPE' => 'OK',   'MESSAGE' => 'Сохранено']);
if ($error)                              CAdminMessage::ShowMessage(['TYPE' => 'ERROR', 'MESSAGE' => $error]);

if ($ID === 0 && isset($_GET['template']) && ($tplInfo = ToolTemplates::get((string)$_GET['template'])) !== null) {
    CAdminMessage::ShowMessage(['TYPE' => 'OK', 'MESSAGE' =>
        'Шаблон «' . $tplInfo['label'] . '»: ' . $tplInfo['description']
        . ' Выберите инфоблок и нажмите «Загрузить свойства».'
    ]);
}
?>

<style>
.mcp-prop-table { border-collapse: collapse; width: 100%; margin-top: 8px; }
.mcp-prop-table th, .mcp-prop-table td { padding: 5px 8px; border: 1px solid #ddd; vertical-align: middle; }
.mcp-prop-table th { background: #f5f5f5; font-weight: bold; font-size: 12px; }
.mcp-prop-table td:first-child { font-family: monospace; font-size: 12px; }

.mcp-filter-table { border-collapse: collapse; }
.mcp-filter-table td { padding: 3px 6px; vertical-align: middle; }
.mcp-filter-table .mcp-filter-key select { min-width: 220px; }
.mcp-filter-table .mcp-filter-remove { color: #c00; cursor: pointer; font-size: 16px; border: none; background: none; padding: 0 4px; }

.mcp-config-section { display: none; }
.mcp-list-only      { display: none; }
.mcp-section-head   { font-weight: bold; margin: 14px 0 4px; font-size: 13px; color: #555; }
</style>

<script>
var mcpFilterFields      = <?= json_encode($filterableFields, JSON_UNESCAPED_UNICODE) ?>;
var mcpFilterOptionsHtml = <?= json_encode($filterSelectOptionsHtml) ?>;

function mcpUpdateType(type) {
    document.querySelectorAll('.mcp-config-section').forEach(function(el) { el.style.display = 'none'; });
    var s = document.getElementById('mcp_config_' + type);
    if (s) s.style.display = 'block';
}

function mcpUpdateOperation(op) {
    document.querySelectorAll('.mcp-list-only').forEach(function(el) {
        el.style.display = (op === 'list') ? '' : 'none';
    });
}

function mcpReloadIblock() {
    document.getElementById('mcp_save_action').value = 'reload_iblock';
    document.getElementById('mcp_form').submit();
}

// ── Статический фильтр ───────────────────────────────────────────────────────

function mcpEsc(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function mcpMakeValueInput(vtype, value, values) {
    value  = value  || '';
    values = values || {};
    switch (vtype) {
        case 'boolean':
            return '<select name="filter_row_value[]">'
                 + '<option value="Y"' + (value === 'Y' ? ' selected' : '') + '>Да (Y)</option>'
                 + '<option value="N"' + (value === 'N' ? ' selected' : '') + '>Нет (N)</option>'
                 + '</select>';
        case 'section':
        case 'enum':
            var opts = '<option value="">— выберите —</option>';
            for (var k in values) {
                if (!values.hasOwnProperty(k)) continue;
                opts += '<option value="' + mcpEsc(k) + '"'
                      + (String(value) === String(k) ? ' selected' : '')
                      + '>' + mcpEsc(values[k]) + '</option>';
            }
            return '<select name="filter_row_value[]">' + opts + '</select>';
        case 'number':
            return '<input type="number" name="filter_row_value[]" value="' + mcpEsc(value) + '" size="10">';
        case 'date':
            return '<input type="date" name="filter_row_value[]" value="' + mcpEsc(value) + '">';
        default:
            return '<input type="text" name="filter_row_value[]" value="' + mcpEsc(value) + '" size="25">';
    }
}

function mcpOnKeyChange(sel) {
    var key   = sel.value;
    var field = mcpFilterFields[key] || {value_type: 'text', values: {}};
    var cell  = sel.closest('tr').querySelector('.mcp-filter-value');
    cell.innerHTML = mcpMakeValueInput(field.value_type, '', field.values || {});
}

function mcpAddFilterRow(key, value) {
    var tbody = document.getElementById('mcp_filter_rows');
    var tr    = document.createElement('tr');
    var field = key ? (mcpFilterFields[key] || {value_type: 'text', values: {}}) : {value_type: 'text', values: {}};

    var keySelect = '<select name="filter_row_key[]" onchange="mcpOnKeyChange(this)">'
                  + mcpFilterOptionsHtml + '</select>';
    var valInput  = mcpMakeValueInput(field.value_type, value || '', field.values || {});

    tr.innerHTML = '<td class="mcp-filter-key">' + keySelect + '</td>'
                 + '<td class="mcp-filter-value">' + valInput + '</td>'
                 + '<td><button type="button" class="mcp-filter-remove" onclick="this.closest(\'tr\').remove()" title="Удалить">×</button></td>';
    tbody.appendChild(tr);

    if (key) tr.querySelector('select').value = key;
}

document.addEventListener('DOMContentLoaded', function() {
    mcpUpdateType('<?= hsc($curType) ?>');
    mcpUpdateOperation('<?= hsc($cfgOperation) ?>');
});
</script>

<form id="mcp_form" method="post" action="alexw_mcp_tool_edit.php?ID=<?= $ID ?>">
<?= bitrix_sessid_post() ?>
<input type="hidden" name="save_action" id="mcp_save_action" value="save">

<?php $tabControl->Begin(); ?>

<!-- ═══ Вкладка: Основное ══════════════════════════════════════════════════ -->
<?php $tabControl->BeginNextTab(); ?>

<tr>
    <td width="35%">Код инструмента (name)<span style="color:red">*</span>:</td>
    <td>
        <input type="text" name="NAME" value="<?= hsc($arItem['NAME'] ?? '') ?>" size="40"
               placeholder="search_menu" pattern="[a-z0-9_]+" title="Только строчные буквы, цифры и _">
        <small style="color:#888"> — этот код видит LLM, только [a-z0-9_]</small>
    </td>
</tr>
<tr>
    <td>Название (для админки):</td>
    <td><input type="text" name="TITLE" value="<?= hsc($arItem['TITLE'] ?? '') ?>" size="40"></td>
</tr>
<tr>
    <td>Описание для LLM:</td>
    <td>
        <textarea name="DESCRIPTION" rows="4" cols="60"><?= hsc($arItem['DESCRIPTION'] ?? '') ?></textarea>
        <br><small style="color:#888">LLM читает это описание, чтобы понять когда и как вызывать инструмент</small>
    </td>
</tr>
<tr>
    <td>Тип:</td>
    <td>
        <select name="TYPE" onchange="mcpUpdateType(this.value)">
            <option value="builtin"<?= selected_if($curType === 'builtin') ?>>Встроенный (builtin)</option>
            <option value="iblock"<?= selected_if($curType === 'iblock')  ?>>Инфоблок (iblock)</option>
            <option value="custom"<?= selected_if($curType === 'custom')  ?>>Кастомный (custom)</option>
        </select>
    </td>
</tr>
<tr>
    <td>Требует авторизации:</td>
    <td><input type="checkbox" name="AUTH_REQUIRED" value="Y"<?= checked_if(($arItem['AUTH_REQUIRED'] ?? 'Y') === 'Y') ?>></td>
</tr>
<tr>
    <td>Активен:</td>
    <td><input type="checkbox" name="ACTIVE" value="Y"<?= checked_if(($arItem['ACTIVE'] ?? 'Y') === 'Y') ?>></td>
</tr>
<tr>
    <td>Сортировка:</td>
    <td><input type="number" name="SORT" value="<?= hsc($arItem['SORT'] ?? 500) ?>" size="6"></td>
</tr>

<!-- ═══ Вкладка: Конфигурация ═════════════════════════════════════════════ -->
<?php $tabControl->BeginNextTab(); ?>

<!-- ── Builtin ─────────────────────────────────────────────────────────────── -->
<tr class="mcp-config-section" id="mcp_config_builtin">
    <td width="35%">PHP-класс:</td>
    <td>
        <input type="text" name="class_name" value="<?= hsc($arConfig['class'] ?? '') ?>" size="60"
               placeholder="Alexw\Clientsmcp\Tools\GetProfileTool">
    </td>
</tr>

<!-- ── Custom ──────────────────────────────────────────────────────────────── -->
<tr class="mcp-config-section" id="mcp_config_custom">
    <td width="35%">PHP-класс:</td>
    <td><input type="text" name="class_name" value="<?= hsc($arConfig['class'] ?? '') ?>" size="60"></td>
</tr>

<!-- ── Iblock ──────────────────────────────────────────────────────────────── -->
<tbody class="mcp-config-section" id="mcp_config_iblock">

<tr>
    <td width="35%">Инфоблок:</td>
    <td>
        <select name="iblock_id">
            <option value="0">— выберите инфоблок —</option>
            <?php foreach ($allIblocks as $ibId => $ibName): ?>
                <option value="<?= $ibId ?>"<?= selected_if($ibId === $displayIblockId) ?>><?= hsc($ibName) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" onclick="mcpReloadIblock()" style="margin-left:6px">Загрузить свойства</button>
    </td>
</tr>
<tr>
    <td>Операция:</td>
    <td>
        <select name="operation" onchange="mcpUpdateOperation(this.value)">
            <option value="list"<?= selected_if($cfgOperation === 'list') ?>>list — список с поиском</option>
            <option value="get"<?= selected_if($cfgOperation === 'get')  ?>>get — один элемент по ID</option>
        </select>
    </td>
</tr>

<!-- Стандартные поля вывода -->
<tr>
    <td>Поля для вывода:</td>
    <td>
        <?php foreach ($standardFields as $fCode => $fName):
            $isId      = $fCode === 'ID';
            $isChecked = in_array($fCode, $cfgFields, true);
        ?>
            <label style="margin-right:12px; white-space:nowrap;">
                <?php if ($isId): ?>
                    <input type="checkbox" checked disabled title="ID всегда включён">
                    <input type="hidden" name="fields[]" value="ID">
                <?php else: ?>
                    <input type="checkbox" name="fields[]" value="<?= hsc($fCode) ?>"<?= checked_if($isChecked) ?>>
                <?php endif; ?>
                <?= hsc($fName) ?>
            </label>
        <?php endforeach; ?>
    </td>
</tr>

<?php if (!empty($iblockProperties)): ?>
<!-- Свойства ИБ -->
<tr>
    <td colspan="2">
        <div class="mcp-section-head">Свойства инфоблока</div>
        <table class="mcp-prop-table">
            <thead><tr>
                <th>Код</th>
                <th>Название</th>
                <th title="Включить значение свойства в ответ">В ответ</th>
                <th class="mcp-list-only" title="Искать по этому свойству вместе с NAME">В поиск</th>
                <th class="mcp-list-only" title="LLM сможет фильтровать по этому свойству">Фильтр LLM</th>
                <th class="mcp-list-only" title="Описание параметра для LLM — укажи допустимые значения">Описание фильтра</th>
            </tr></thead>
            <tbody>
            <?php foreach ($iblockProperties as $pCode => $pName): ?>
            <tr>
                <td><?= hsc($pCode) ?></td>
                <td><?= hsc($pName) ?></td>
                <td style="text-align:center">
                    <input type="checkbox" name="properties[]" value="<?= hsc($pCode) ?>"
                           <?= checked_if(in_array($pCode, $cfgProperties, true)) ?>>
                </td>
                <td class="mcp-list-only" style="text-align:center">
                    <input type="checkbox" name="search_properties[]" value="<?= hsc($pCode) ?>"
                           <?= checked_if(in_array($pCode, $cfgSearchProps, true)) ?>>
                </td>
                <td class="mcp-list-only" style="text-align:center">
                    <input type="checkbox" name="filter_prop_enabled[]" value="<?= hsc($pCode) ?>"
                           <?= checked_if(isset($cfgFilterPropsMap[$pCode])) ?>>
                </td>
                <td class="mcp-list-only">
                    <input type="text" name="filter_prop_desc[<?= hsc($pCode) ?>]"
                           value="<?= hsc($cfgFilterPropsMap[$pCode] ?? '') ?>"
                           size="38" placeholder="Допустимые значения: ...">
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <script>mcpUpdateOperation('<?= hsc($cfgOperation) ?>');</script>
    </td>
</tr>
<?php elseif ($displayIblockId > 0): ?>
<tr><td colspan="2"><em>У этого инфоблока нет активных свойств.</em></td></tr>
<?php elseif ($curType === 'iblock'): ?>
<tr><td colspan="2"><em style="color:#888">Выберите инфоблок и нажмите «Загрузить свойства».</em></td></tr>
<?php endif; ?>

<!-- Лимит (только list) -->
<tr class="mcp-list-only">
    <td>Лимит элементов:</td>
    <td>
        <input type="number" name="limit" value="<?= hsc($cfgLimit) ?>" min="1" max="100" size="6">
        <small style="color:#888"> макс. 100</small>
    </td>
</tr>

<!-- Статический фильтр -->
<tr>
    <td>Статический фильтр:</td>
    <td>
        <table class="mcp-filter-table">
            <thead><tr>
                <th style="text-align:left; padding:2px 6px; color:#555; font-size:12px;">Поле</th>
                <th style="text-align:left; padding:2px 6px; color:#555; font-size:12px;">Значение</th>
                <th></th>
            </tr></thead>
            <tbody id="mcp_filter_rows">
            <?php foreach ($cfgFilter as $fKey => $fVal): ?>
            <tr>
                <td class="mcp-filter-key">
                    <select name="filter_row_key[]" onchange="mcpOnKeyChange(this)">
                        <?php foreach ($filterableFields as $optKey => $optInfo): ?>
                            <option value="<?= hsc($optKey) ?>"<?= selected_if($optKey === $fKey) ?>><?= hsc($optInfo['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="mcp-filter-value">
                    <?= renderFilterValue((string)$fKey, (string)$fVal, $filterableFields) ?>
                </td>
                <td>
                    <button type="button" class="mcp-filter-remove" onclick="this.closest('tr').remove()" title="Удалить">×</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" onclick="mcpAddFilterRow('', '')" style="margin-top:6px">+ Добавить фильтр</button>
        <br><small style="color:#888">Применяется ко всем запросам, LLM не может изменить эти условия</small>
    </td>
</tr>

</tbody><!-- /mcp_config_iblock -->

<?php
$tabControl->Buttons(['disabled' => false, 'back_url' => 'alexw_mcp_tools.php']);
$tabControl->End();
?>
</form>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
