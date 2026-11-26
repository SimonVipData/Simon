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
    if (($pid = param_int('position_id')) > 0) {
        $where[] = 'u.position_id = ?';
        $args[] = $pid;
    }
    if (param('type') === 'user') $where[] = "u.role_id IN (SELECT id FROM pm_roles WHERE type = 'user')";
    $w = implode(' AND ', $where);
    $rows = db_rows("SELECT u.id,u.username,u.real_name,u.role_id,u.position_id,u.company,u.phone,u.email,u.status,u.last_login,u.created_at,
                            CEIL(u.credits / 60) AS credit_min, u.active_at,
                            r.name AS role_name, r.type AS role_type, p.name AS position_name
                     FROM pm_users u LEFT JOIN pm_roles r ON r.id = u.role_id LEFT JOIN pm_positions p ON p.id = u.position_id
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
    $role = db_row('SELECT id, type FROM pm_roles WHERE id = ?', array($roleId));
    if (!$role) fail('請選擇角色');
    if ($role['type'] === 'super' && !is_super()) fail('只有超級管理員才能設置超級管理員帳號');
    $positionId = param_int('position_id');
    if ($positionId && !db_val('SELECT id FROM pm_positions WHERE id = ?', array($positionId))) fail('職位不存在');
    if ($password !== '' && (strlen($password) < 6 || strlen($password) > 64)) fail('密碼長度須為 6-64 位');
    if (($email = param('email')) !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('郵箱格式不正確');

    $data = array(
        'username' => $username, 'real_name' => mb_substr(param('real_name'), 0, 50), 'role_id' => $roleId, 'position_id' => $positionId,
        'company' => mb_substr(param('company'), 0, 100), 'phone' => mb_substr(param('phone'), 0, 30), 'email' => $email, 'status' => $status,
    );
    if ($password !== '') $data['password'] = password_hash($password, PASSWORD_DEFAULT);

    if ($id) {
        $old = db_row('SELECT u.id, r.type FROM pm_users u LEFT JOIN pm_roles r ON r.id = u.role_id WHERE u.id = ?', array($id));
        if (!$old) fail('用戶不存在');
        if ($old['type'] === 'super' && !is_super()) fail('只有超級管理員才能修改超級管理員帳號');
        if ($id === (int)$me['id'] && ($roleId !== (int)$me['role_id'] || $status !== 1)) fail('不能修改自己的角色或停用自己');
        if ($status === 1) $data['reg_state'] = 0;
        db_update('pm_users', $data, $id);
    } else {
        if ($password === '') fail('請輸入密碼');
        $data['created_at'] = now();
        $data['credits'] = CREDIT_INIT_MIN * 60;
        $newId = db_insert('pm_users', $data);
        db_insert('pm_credit_logs', array('target_id' => $newId, 'delta' => CREDIT_INIT_MIN, 'balance' => CREDIT_INIT_MIN, 'remark' => '新用戶初始積分',
            'user_id' => $me['id'], 'created_at' => now()));
    }
    ok('保存成功');
}

if ($act === 'delete') {
    $id = param_int('id');
    if ($id === (int)$me['id']) fail('不能刪除當前登錄的帳號');
    $old = db_row('SELECT u.id, r.type FROM pm_users u LEFT JOIN pm_roles r ON r.id = u.role_id WHERE u.id = ?', array($id));
    if (!$old) fail('用戶不存在');
    if ($old['type'] === 'super' && !is_super()) fail('只有超級管理員才能刪除超級管理員帳號');
    if (db_val('SELECT id FROM pm_apprs WHERE user_id = ? LIMIT 1', array($id)) || db_val('SELECT id FROM pm_appr_steps WHERE user_id = ? LIMIT 1', array($id))) fail('該用戶已參與內部審批，不能刪除，可改為停用');
    if (db_val('SELECT id FROM pm_docs WHERE creator_id = ? LIMIT 1', array($id))) fail('該用戶已創建工程文件，不能刪除，可改為停用');
    // 刪除該用戶的個人網盤文件
    require_once __DIR__ . '/../inc/files.php';
    foreach (db_rows("SELECT id, is_dir FROM pm_disk WHERE space = 'personal' AND user_id = ?", array($id)) as $d) {
        if (!$d['is_dir']) files_delete('disk', (int)$d['id']);
        db_q('DELETE FROM pm_disk WHERE id = ?', array($d['id']));
    }
    db_q('DELETE FROM pm_forum_pays WHERE user_id = ?', array($id));
    db_q('DELETE FROM pm_users WHERE id = ?', array($id));
    ok('刪除成功');
}
fail('未知操作');
