<?php
/** 控制面板 API（管理員） */
require_once __DIR__ . '/../inc/config.php';
api_guard(true);
if (param('act') === 'save') {
    $vals = array();
    foreach (panel_defs() as $k => $d) {
        if (isset($_POST[$k])) $vals[$k] = param_int($k) === 1 ? '1' : '0';
    }
    $langs = 0;
    foreach (panel_lang_defs() as $k => $t) $langs += isset($vals['lang_' . $k]) ? (int)$vals['lang_' . $k] : panel_get('lang_' . $k);
    if (!$langs) fail('至少須開啟一種系統語言');
    foreach ($vals as $k => $v) db_q('REPLACE INTO pm_meta (k, v) VALUES (?, ?)', array('panel_' . $k, $v));
    ok('保存成功');
}
fail('未知操作');
