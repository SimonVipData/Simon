<?php
/** 角色管理 API（管理員） */
require_once __DIR__ . '/../inc/config.php';
$act = param('act', 'list');

if ($act === 'list') {
    api_guard(true, false);
    list($limit, $offset) = page_args();
    $rows = db_rows("SELECT r.*, (SELECT COUNT(*) FROM pm_users u WHERE u.role_id = r.id) AS user_count
                     FROM pm_roles r ORDER BY r.sort, r.id LIMIT $limit OFFSET $offset");
    json_out(array('total' => (int)db_val('SELECT COUNT(*) FROM pm_roles'), 'rows' => $rows));
}

if ($act === 'options') {
    // 下拉選項：所有登錄用戶可讀
    api_guard(false, false);
    $where = param('type') === 'user' ? "WHERE type = 'user'" : '';
    json_out(db_rows("SELECT id, name, type FROM pm_roles $where ORDER BY sort, id"));
}

api_guard(true);
$me = current_user();

if ($act === 'save') {
    $id = param_int('id');
    $name = param('name');
    $type = param('type') === 'admin' ? 'admin' : 'user';
    if ($name === '' || mb_strlen($name) > 50) fail('請輸入角色名稱（50 字以內）');
    if (db_val('SELECT id FROM pm_roles WHERE name = ? AND id <> ?', array($name, $id))) fail('角色名稱已存在');
    $data = array('name' => $name, 'type' => $type, 'remark' => mb_substr(param('remark'), 0, 255), 'sort' => param_int('sort'));
    if ($id) {
        $old = db_row('SELECT * FROM pm_roles WHERE id = ?', array($id));
        if (!$old) fail('角色不存在');
        if ($old['type'] === 'super') {
            if (!is_super()) fail('超級管理員角色只能由超級管理員修改');
            $data['type'] = $type = 'super';
        }
        if ($id === (int)$me['role_id'] && $type === 'user') fail('不能將自己所屬角色改為普通用戶');
        if ($old['type'] !== $type && $type === 'admin' && db_val('SELECT id FROM pm_flow_steps WHERE role_id = ? LIMIT 1', array($id))) {
            fail('該角色已用於文件審批設置，不能改為管理員類別');
        }
        db_update('pm_roles', $data, $id);
        db_q('UPDATE pm_doc_steps SET role_name = ? WHERE role_id = ? AND status IN (?, ?)', array($name, $id, STEP_WAIT, STEP_CURRENT));
    } else {
        $data['created_at'] = now();
        db_insert('pm_roles', $data);
    }
    ok('保存成功');
}

if ($act === 'delete') {
    $id = param_int('id');
    if ($id === (int)$me['role_id']) fail('不能刪除自己所屬的角色');
    if (db_val("SELECT id FROM pm_roles WHERE id = ? AND type = 'super'", array($id))) fail('超級管理員角色不能刪除');
    if (!db_val('SELECT id FROM pm_roles WHERE id = ?', array($id))) fail('角色不存在');
    if (db_val('SELECT id FROM pm_users WHERE role_id = ? LIMIT 1', array($id))) fail('該角色下仍有用戶，請先刪除或調整用戶');
    if (db_val('SELECT id FROM pm_flow_steps WHERE role_id = ? LIMIT 1', array($id))) fail('該角色已用於文件審批設置，請先調整審批順序');
    if (db_val('SELECT s.id FROM pm_doc_steps s JOIN pm_docs d ON d.id = s.doc_id AND d.round = s.round WHERE s.role_id = ? AND d.status = ? LIMIT 1', array($id, DOC_PENDING))) {
        fail('有審批中的文件需要該角色處理，暫不能刪除');
    }
    db_q('DELETE FROM pm_roles WHERE id = ?', array($id));
    ok('刪除成功');
}
fail('未知操作');
