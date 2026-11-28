<?php
/** 控制面板：系統級開關（存於 pm_meta） */

/** 普通用戶可開關的菜單項：頁面名 => 菜單名 */
function panel_menu_defs() {
    return array(
        'project' => '工程信息', 'doc_mine' => '我的文件', 'doc_todo' => '待辦審批', 'doc_done' => '已辦審批', 'doc_all' => '全部文件',
        'office_remind' => '消息提醒', 'office_chat' => '即時溝通', 'office_mail' => '內部郵件', 'office_vote' => '投票',
        'note' => '記事本', 'todo' => '待辦事項', 'worklist' => '工作清單', 'work_calendar' => '工程日曆', 'customer' => '客戶管理',
        'material' => '材料管理', 'sub' => '分包管理', 'labor' => '勞工管理',
        'appr_inquiry' => '詢價審批', 'appr_expense' => '費用審批', 'appr_matter' => '事項審批',
        'disk_personal' => '個人文件', 'disk_share' => '共享文件', 'disk_company' => '公司文件', 'forum' => '工程論壇',
    );
}

/** 系統語言：代碼 => 名稱 */
function panel_lang_defs() {
    return array('tc' => '繁體中文', 'sc' => '簡體中文', 'en' => 'English');
}

/** 開關定義：鍵 => array(名稱, 默認值) */
function panel_defs() {
    $defs = array(
        'cal_own_only' => array('工程日曆默認只顯示自己創建的事件', 1),
        'doc_user_del' => array('普通用戶刪除報審記錄', 0),
    );
    foreach (panel_lang_defs() as $k => $t) $defs['lang_' . $k] = array($t, $k === 'en' ? 0 : 1);
    foreach (panel_menu_defs() as $k => $t) $defs['menu_' . $k] = array($t, 1);
    return $defs;
}

function panel_get($k) {
    static $cache = null;
    if ($k === null) { $cache = null; return null; }
    if ($cache === null) {
        $cache = array();
        foreach (db_rows("SELECT k, v FROM pm_meta WHERE k LIKE 'panel\\_%'") as $r) $cache[substr($r['k'], 6)] = (int)$r['v'];
    }
    if (isset($cache[$k])) return $cache[$k];
    $defs = panel_defs();
    return isset($defs[$k]) ? (int)$defs[$k][1] : 0;
}

/** 工程日曆：普通用戶是否只能查看自己創建的事件（管理員不受限） */
function cal_own_only() {
    return !is_admin() && panel_get('cal_own_only') === 1;
}

/** 當前用戶能否刪除報審記錄（普通用戶需控制面板開啟） */
function doc_can_delete() {
    return is_admin() || panel_get('doc_user_del') === 1;
}

/** 菜單項是否對當前用戶開放（管理員不受限） */
function menu_on($page) {
    if (is_admin()) return true;
    $defs = panel_menu_defs();
    return !isset($defs[$page]) || panel_get('menu_' . $page) === 1;
}

/** 頁面所屬的菜單項（詳情頁、子頁面歸屬到對應菜單） */
function menu_key_of_page($name) {
    $map = array('mail_view' => 'office_mail', 'vote_view' => 'office_vote', 'forum_thread' => 'forum',
        'customer_quote' => 'customer', 'sub_quote' => 'sub');
    return isset($map[$name]) ? $map[$name] : $name;
}

/** API 入口：菜單項被關閉時拒絕訪問 */
function menu_guard($page) {
    if (!menu_on($page)) fail('該功能已被管理員關閉');
}

/** 已啟用的系統語言 */
function lang_enabled() {
    $out = array();
    foreach (panel_lang_defs() as $k => $t) if (panel_get('lang_' . $k) === 1) $out[] = $k;
    return $out ? $out : array('tc');
}

/** 輸出前端語言配置（須在 pm.lang.js 之前） */
function lang_script() {
    return '<script type="text/javascript">var PM_LANGS = ' . json_encode(lang_enabled()) . ';</script>';
}
