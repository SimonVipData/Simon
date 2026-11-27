<?php
/** 工程權限：管理員可查閱全部工程；普通用戶只能查閱「所屬工程」，沒有所屬工程則不能查閱工程文件及相關信息 */

/** 當前用戶可查閱的工程 ID；管理員返回 null（不限） */
function my_project_ids() {
    static $cache = array();
    if (is_admin()) return null;
    $me = current_user();
    if (!$me) return array();
    $uid = (int)$me['id'];
    if (!isset($cache[$uid])) {
        $cache[$uid] = array_map('intval', array_column(db_rows('SELECT project_id FROM pm_user_projects WHERE user_id = ?', array($uid)), 'project_id'));
    }
    return $cache[$uid];
}

/** SQL 條件：$col 為工程 ID 字段 */
function project_scope_sql($col) {
    $ids = my_project_ids();
    if ($ids === null) return '1=1';
    return $ids ? "$col IN (" . implode(',', $ids) . ')' : '1=0';
}

function can_see_project($pid) {
    $ids = my_project_ids();
    return $ids === null || in_array((int)$pid, $ids, true);
}

/** 工程英文名用於編號：空格轉橫線，只保留字母、數字、橫線 */
function project_en_token($s) {
    return trim(preg_replace('/-+/', '-', preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', trim($s)))), '-');
}
