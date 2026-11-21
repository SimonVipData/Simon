<?php
/** 個人中心 API：個人信息、修改密碼、登錄日志 */
require_once __DIR__ . '/../inc/config.php';
$act = param('act');

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
