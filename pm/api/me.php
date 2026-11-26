<?php
/** 個人中心 API：個人信息、修改密碼、登錄日志 */
require_once __DIR__ . '/../inc/config.php';
$act = param('act');

/** 各類待處理/未讀數量 */
function pm_counts($me) {
    $uid = (int)$me['id'];
    $c = array(
        'doc' => is_admin() ? 0 : (int)db_val('SELECT COUNT(*) FROM pm_docs d JOIN pm_doc_steps s ON s.doc_id = d.id AND s.round = d.round
                                               WHERE d.status = ? AND s.status = ? AND s.role_id = ?', array(DOC_PENDING, STEP_CURRENT, $me['role_id'])),
        'appr' => (int)db_val('SELECT COUNT(*) FROM pm_apprs a JOIN pm_appr_steps s ON s.appr_id = a.id WHERE a.status = ? AND s.status = ? AND s.user_id = ?', array(DOC_PENDING, STEP_CURRENT, $uid)),
        'notice' => (int)db_val('SELECT COUNT(*) FROM pm_notices n WHERE NOT EXISTS (SELECT 1 FROM pm_notice_reads r WHERE r.notice_id = n.id AND r.user_id = ?)', array($uid)),
        'mail' => (int)db_val('SELECT COUNT(*) FROM pm_mail_to t JOIN pm_mails m ON m.id = t.mail_id AND m.status = 1 WHERE t.user_id = ? AND t.deleted = 0 AND t.is_read = 0', array($uid)),
        'chat' => (int)db_val('SELECT COUNT(*) FROM pm_chat_msgs WHERE to_id = ? AND is_read = 0', array($uid))
                + (int)db_val('SELECT COUNT(*) FROM pm_chat_msgs WHERE to_id = 0 AND from_id <> ? AND id > COALESCE((SELECT last_id FROM pm_chat_reads WHERE user_id = ?), 0)', array($uid, $uid)),
    );
    // 管理員：待審核的注冊申請
    $c['reg'] = is_admin() ? (int)db_val('SELECT COUNT(*) FROM pm_users WHERE reg_state = 1 AND status = 0') : 0;
    $c['total'] = array_sum($c);
    return $c;
}

if ($act === 'logs') {
    api_guard(false, false);
    $me = current_user();
    list($limit, $offset) = page_args();
    // 管理員可查看全部登錄日志，普通用戶僅查看自己的
    $w = is_admin() ? '1=1' : 'user_id = ' . (int)$me['id'];
    json_out(array(
        'total' => (int)db_val("SELECT COUNT(*) FROM pm_login_logs WHERE $w"),
        'rows' => db_rows("SELECT id, username, ip, user_agent, result, created_at FROM pm_login_logs WHERE $w ORDER BY id DESC LIMIT $limit OFFSET $offset"),
    ));
}

if ($act === 'ping') {
    // 心跳：扣減積分（current_user 內完成）、更新在線時間、返回各類未讀數；鎖定時仍可調用
    $GLOBALS['pm_lock_ok'] = true;
    api_guard(false, false);
    $me = current_user();
    db_q('UPDATE pm_users SET active_at = ? WHERE id = ?', array(now(), $me['id']));
    json_out(array('ok' => true, 'locked' => !empty($_SESSION['pm_locked']), 'limited' => $me['role_type'] === 'user', 'credits' => (int)$me['credits']) + pm_counts($me));
}

/** 鎖定界面：鎖定狀態記在會話中，刷新頁面或新開標籤頁仍需輸入密碼 */
if ($act === 'lock' || $act === 'unlock') {
    $GLOBALS['pm_lock_ok'] = true;
    api_guard();
    $me = current_user();
    if ($act === 'lock') {
        $_SESSION['pm_locked'] = 1;
        $_SESSION['pm_unlock_fail'] = 0;
        ok('');
    }
    if (empty($_SESSION['pm_locked'])) ok('');
    $pw = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    if ($pw === '') fail('請輸入登錄密碼');
    if (!password_verify($pw, (string)db_val('SELECT password FROM pm_users WHERE id = ?', array($me['id'])))) {
        $n = empty($_SESSION['pm_unlock_fail']) ? 1 : (int)$_SESSION['pm_unlock_fail'] + 1;
        if ($n >= UNLOCK_MAX_FAIL) {
            $_SESSION = array();
            session_destroy();
            json_out(array('ok' => false, 'timeout' => true, 'msg' => '密碼錯誤次數過多，已退出系統，請重新登錄'), 401);
        }
        $_SESSION['pm_unlock_fail'] = $n;
        fail('密碼不正確，還可嘗試 ' . (UNLOCK_MAX_FAIL - $n) . ' 次');
    }
    unset($_SESSION['pm_locked'], $_SESSION['pm_unlock_fail']);
    ok('解鎖成功');
}

api_guard();
$me = current_user();

if ($act === 'profile') {
    $email = param('email');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('郵箱格式不正確');
    db_update('pm_users', array(
        'real_name' => mb_substr(param('real_name'), 0, 50), 'company' => mb_substr(param('company'), 0, 100),
        'phone' => mb_substr(param('phone'), 0, 30), 'email' => $email,
    ), $me['id']);
    ok('保存成功');
}

if ($act === 'password') {
    $old = isset($_POST['old']) && is_string($_POST['old']) ? $_POST['old'] : '';
    $new = isset($_POST['new']) && is_string($_POST['new']) ? $_POST['new'] : '';
    $confirm = isset($_POST['confirm']) && is_string($_POST['confirm']) ? $_POST['confirm'] : '';
    if (!password_verify($old, (string)db_val('SELECT password FROM pm_users WHERE id = ?', array($me['id'])))) fail('原密碼不正確');
    if (strlen($new) < 6 || strlen($new) > 64) fail('新密碼長度須為 6-64 位');
    if ($new !== $confirm) fail('兩次輸入的新密碼不一致');
    db_update('pm_users', array('password' => password_hash($new, PASSWORD_DEFAULT)), $me['id']);
    ok('密碼修改成功');
}
fail('未知操作');
