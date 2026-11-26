<?php
/** 控制面板：系統級開關（存於 pm_meta） */

/** 開關定義：鍵 => array(名稱, 默認值) */
function panel_defs() {
    return array(
        'cal_own_only' => array('工程日曆默認只顯示自己創建的事件', 1),
    );
}

function panel_get($k) {
    $defs = panel_defs();
    $v = db_val('SELECT v FROM pm_meta WHERE k = ?', array('panel_' . $k));
    return $v === null || $v === false ? $defs[$k][1] : (int)$v;
}

/** 工程日曆：普通用戶是否只能查看自己創建的事件（管理員不受限） */
function cal_own_only() {
    return !is_admin() && panel_get('cal_own_only') === 1;
}
