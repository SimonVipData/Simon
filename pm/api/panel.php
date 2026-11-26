<?php
/** 控制面板 API（管理員） */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/panel.php';
api_guard(true);
if (param('act') === 'save') {
    foreach (panel_defs() as $k => $d) {
        if (!isset($_POST[$k])) continue;
        db_q('REPLACE INTO pm_meta (k, v) VALUES (?, ?)', array('panel_' . $k, param_int($k) === 1 ? '1' : '0'));
    }
    ok('保存成功');
}
fail('未知操作');
