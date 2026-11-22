<?php
/** 積分管理 API（管理員）：積分即普通用戶可使用本系統的分鐘數 */
require_once __DIR__ . '/../inc/config.php';
$act = param('act', 'logs');

if ($act === 'logs') {
    api_guard(true, false);
    list($limit, $offset) = page_args();
    $tid = param_int('target_id');
    $w = $tid ? 'l.target_id = ' . $tid : '1=1';
    json_out(array(
        'total' => (int)db_val("SELECT COUNT(*) FROM pm_credit_logs l WHERE $w"),
        'rows' => db_rows("SELECT l.*, t.username AS target_name, COALESCE(NULLIF(o.real_name, ''), o.username) AS operator_name
                           FROM pm_credit_logs l LEFT JOIN pm_users t ON t.id = l.target_id LEFT JOIN pm_users o ON o.id = l.user_id
                           WHERE $w ORDER BY l.id DESC LIMIT $limit OFFSET $offset"),
    ));
}

api_guard(true);
$me = current_user();

if ($act === 'adjust') {
    $id = param_int('id');
    $min = param_int('minutes');
    $sign = param('op') === 'sub' ? -1 : 1;
    if ($min <= 0 || $min > 100000) fail('請輸入 1-100000 之間的分鐘數');
    $bal = db_tx(function () use ($id, $min, $sign, $me) {
        $u = db_row('SELECT u.id, u.credits, r.type FROM pm_users u LEFT JOIN pm_roles r ON r.id = u.role_id WHERE u.id = ? FOR UPDATE', array($id));
        if (!$u) throw new PmError('用戶不存在');
        if ($u['type'] !== 'user') throw new PmError('管理員帳號不受積分限制，無需調整');
        $new = max(0, (int)$u['credits'] + $sign * $min * 60);
        db_q('UPDATE pm_users SET credits = ? WHERE id = ?', array($new, $id));
        $bal = (int)ceil($new / 60);
        db_insert('pm_credit_logs', array('target_id' => $id, 'delta' => $sign * $min, 'balance' => $bal, 'remark' => mb_substr(param('remark'), 0, 255),
            'user_id' => $me['id'], 'created_at' => now()));
        return $bal;
    });
    ok(($sign > 0 ? '充值' : '扣減') . '成功，當前積分 ' . $bal . ' 分鐘');
}
fail('未知操作');
