<?php
/** 用戶管理 API（管理員） */
require_once __DIR__ . '/../inc/config.php';
$act = param('act', 'list');

if ($act === 'list') {
    api_guard(true, false);
    list($limit, $offset) = page_args();
    $where = array('1=1');
    $args = array();
    if (($kw = param('keyword')) !== '') {
        $where[] = '(u.username LIKE ? OR u.real_name LIKE ? OR u.company LIKE ?)';
        $like = '%' . addcslashes($kw, '%_\\') . '%';
        array_push($args, $like, $like, $like);
    }
    if (($rid = param_int('role_id')) > 0) {
        $where[] = 'u.role_id = ?';
        $args[] = $rid;
    }
    $w = implode(' AND ', $where);
    $rows = db_rows("SELECT u.id,u.username,u.real_name,u.role_id,u.company,u.phone,u.email,u.status,u.last_login,u.created_at,
                            r.name AS role_name, r.type AS role_type
                     FROM pm_users u LEFT JOIN pm_roles r ON r.id = u.role_id
                     WHERE $w ORDER BY u.id LIMIT $limit OFFSET $offset", $args);
    json_out(array('total' => (int)db_val("SELECT COUNT(*) FROM pm_users u WHERE $w", $args), 'rows' => $rows));
}

api_guard(true);
$me = current_user();

if ($act === 'save') {
    $id = param_int('id');
    $username = param('username');
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $roleId = param_int('role_id');
    $status = param_int('status', 1) === 1 ? 1 : 0;

    if (!preg_match('/^[A-Za-z0-9_.\-]{2,50}$/', $username)) fail('帳號須為 2-50 位字母、數字、下劃線、點或橫線');
    if (db_val('SELECT id FROM pm_users WHERE username = ? AND id <> ?', array($username, $id))) fail('帳號已存在');
    if (!db_val('SELECT id FROM pm_roles WHERE id = ?', array($roleId))) fail('請選擇角色');
    if ($password !== '' && (strlen($password) < 6 || strlen($password) > 64)) fail('密碼長度須為 6-64 位');
    if (($email = param('email')) !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('郵箱格式不正確');

    $data = array(
        'username' => $username, 'real_name' => mb_substr(param('real_name'), 0, 50), 'role_id' => $roleId,
        'company' => mb_substr(param('company'), 0, 100), 'phone' => mb_substr(param('phone'), 0, 30), 'email' => $email, 'status' => $status,
    );
    if ($password !== '') $data['password'] = password_hash($password, PASSWORD_DEFAULT);

    if ($id) {
        if (!db_val('SELECT id FROM pm_users WHERE id = ?', array($id))) fail('用戶不存在');
        if ($id === (int)$me['id'] && ($roleId !== (int)$me['role_id'] || $status !== 1)) fail('不能修改自己的角色或停用自己');
        db_update('pm_users', $data, $id);
    } else {
        if ($password === '') fail('請輸入密碼');
        $data['created_at'] = now();
        db_insert('pm_users', $data);
    }
    ok('保存成功');
}

if ($act === 'delete') {
    $id = param_int('id');
    if ($id === (int)$me['id']) fail('不能刪除當前登錄的帳號');
    if (!db_val('SELECT id FROM pm_users WHERE id = ?', array($id))) fail('用戶不存在');
    if (db_val('SELECT id FROM pm_docs WHERE creator_id = ? LIMIT 1', array($id))) fail('該用戶已創建工程文件，不能刪除，可改為停用');
    db_q('DELETE FROM pm_users WHERE id = ?', array($id));
    ok('刪除成功');
}
fail('未知操作');
