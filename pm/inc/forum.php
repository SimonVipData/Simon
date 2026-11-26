<?php
/** 工程論壇付費貼：發貼人可設定其他用戶支付積分後才能查看貼子或下載附件 */

/** 單項收費上限（積分 = 分鐘） */
define('FORUM_PAY_MAX', 1000);

/**
 * 當前用戶對主題的訪問權限：view 可查看內容與回覆，file 可下載附件。
 * 發貼人和管理員免付；其他用戶對同一貼子每項只需支付一次。
 */
function forum_access($t, $me) {
    $free = is_admin() || (int)$t['user_id'] === (int)$me['id'];
    $paid = array();
    if (!$free && ((int)$t['pay_view'] > 0 || (int)$t['pay_file'] > 0)) {
        foreach (db_rows('SELECT kind FROM pm_forum_pays WHERE thread_id = ? AND user_id = ?', array($t['id'], $me['id'])) as $r) $paid[$r['kind']] = true;
    }
    $view = $free || (int)$t['pay_view'] <= 0 || isset($paid['view']);
    return array('free' => $free, 'view' => $view, 'file' => $view && ($free || (int)$t['pay_file'] <= 0 || isset($paid['file'])));
}

/** 只有普通用戶（受積分限制）發表的貼子才能收費 */
function forum_can_price($userId) {
    return db_val('SELECT r.type FROM pm_users u JOIN pm_roles r ON r.id = u.role_id WHERE u.id = ?', array($userId)) === 'user';
}

/**
 * 支付積分：從付款人扣減並轉給發貼人，寫入積分記錄。返回付款人剩餘積分（秒）。
 * 需在事務內調用。
 */
function forum_pay($t, $kind, $me) {
    $amount = (int)$t[$kind === 'file' ? 'pay_file' : 'pay_view'];
    $uid = (int)$me['id'];
    $users = array();
    // 按 id 順序鎖定雙方，避免互相付費時死鎖
    foreach (db_rows('SELECT u.id, u.credits, r.type FROM pm_users u LEFT JOIN pm_roles r ON r.id = u.role_id WHERE u.id IN (?, ?) ORDER BY u.id FOR UPDATE',
        array($uid, (int)$t['user_id'])) as $r) $users[(int)$r['id']] = $r;
    $left = (int)$users[$uid]['credits'];
    if (db_val('SELECT id FROM pm_forum_pays WHERE thread_id = ? AND user_id = ? AND kind = ?', array($t['id'], $uid, $kind))) return $left;
    if ($left <= $amount * 60) throw new PmError('積分不足：需要 ' . $amount . ' 積分，您當前有 ' . (int)ceil($left / 60) . ' 積分');
    $what = $kind === 'file' ? '下載附件' : '查看';
    $title = '「' . mb_substr($t['title'], 0, 60) . '」';
    $left -= $amount * 60;
    db_q('UPDATE pm_users SET credits = ? WHERE id = ?', array($left, $uid));
    db_insert('pm_credit_logs', array('target_id' => $uid, 'delta' => -$amount, 'balance' => (int)ceil($left / 60),
        'remark' => '論壇付費' . $what . '：' . $title, 'user_id' => $uid, 'created_at' => now()));
    $au = isset($users[(int)$t['user_id']]) ? $users[(int)$t['user_id']] : null;
    if ($au && $au['type'] === 'user') {
        $bal = (int)$au['credits'] + $amount * 60;
        db_q('UPDATE pm_users SET credits = ? WHERE id = ?', array($bal, $au['id']));
        db_insert('pm_credit_logs', array('target_id' => $au['id'], 'delta' => $amount, 'balance' => (int)ceil($bal / 60),
            'remark' => '論壇收入：' . user_label($me) . ' 付費' . $what . $title, 'user_id' => $uid, 'created_at' => now()));
    }
    db_insert('pm_forum_pays', array('thread_id' => $t['id'], 'user_id' => $uid, 'kind' => $kind, 'amount' => $amount, 'created_at' => now()));
    return $left;
}
