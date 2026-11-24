<?php
/** 注冊管理 API（管理員）：注冊設置、待審核的注冊申請 */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/reg.php';
$act = param('act', 'pending');

if ($act === 'pending') {
    api_guard(true, false);
    list($limit, $offset) = page_args();
    $w = 'u.reg_state = 1 AND u.status = 0';
    $rows = db_rows("SELECT u.id, u.username, u.real_name, u.company, u.phone, u.email, u.created_at, r.name AS role_name, p.name AS position_name,
                            (SELECT l.ip FROM pm_login_logs l WHERE l.user_id = u.id AND l.result = '注冊' ORDER BY l.id DESC LIMIT 1) AS ip
                     FROM pm_users u LEFT JOIN pm_roles r ON r.id = u.role_id LEFT JOIN pm_positions p ON p.id = u.position_id
                     WHERE $w ORDER BY u.id DESC LIMIT $limit OFFSET $offset");
    json_out(array('total' => (int)db_val("SELECT COUNT(*) FROM pm_users u WHERE $w"), 'rows' => $rows));
}

api_guard(true);

if ($act === 'save') {
    $roleId = param_int('role_id');
    if (!db_val("SELECT id FROM pm_roles WHERE id = ? AND type = 'user'", array($roleId))) fail('請選擇注冊用戶的默認角色（須為普通用戶角色）');
    $fields = array();
    foreach (reg_field_defs() as $k => $d) $fields[$k] = param_int('f_' . $k, $d[1]);
    reg_save_settings(param_int('enabled') === 1, param_int('audit', 1) === 1, $roleId, $fields);
    ok('保存成功');
}

if ($act === 'approve' || $act === 'reject') {
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', param('ids'))))));
    if (!$ids) fail('請選擇注冊申請');
    $in = implode(',', $ids);
    // 只處理仍在待審核狀態的注冊申請
    $rows = db_rows("SELECT id FROM pm_users WHERE id IN ($in) AND reg_state = 1 AND status = 0");
    if (count($rows) !== count($ids)) fail('部分申請已被處理，請刷新後重試');
    if ($act === 'approve') {
        db_q("UPDATE pm_users SET status = 1, reg_state = 0 WHERE id IN ($in)");
        ok('已通過 ' . count($ids) . ' 個注冊申請，用戶現在可以登錄');
    }
    db_tx(function () use ($in) {
        db_q("DELETE FROM pm_credit_logs WHERE target_id IN ($in)");
        db_q("DELETE FROM pm_users WHERE id IN ($in)");
    });
    ok('已拒絕並刪除 ' . count($ids) . ' 個注冊申請');
}
fail('未知操作');
