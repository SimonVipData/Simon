<?php
/** 工程信息 API：所有用戶可查看，管理員可維護 */
require_once __DIR__ . '/../inc/config.php';
$act = param('act', 'list');

if ($act === 'list') {
    api_guard(false, false);
    list($limit, $offset) = page_args();
    json_out(array(
        'total' => (int)db_val('SELECT COUNT(*) FROM pm_projects'),
        'rows' => db_rows("SELECT * FROM pm_projects ORDER BY id LIMIT $limit OFFSET $offset"),
    ));
}
if ($act === 'options') {
    api_guard(false, false);
    json_out(db_rows('SELECT id, code, name FROM pm_projects ORDER BY id'));
}

api_guard(true);

function pm_date($name) {
    $v = param($name);
    if ($v === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) fail('日期格式不正確（YYYY-MM-DD）');
    return $v;
}

if ($act === 'save') {
    $id = param_int('id');
    $code = param('code');
    $name = param('name');
    if ($code === '' || mb_strlen($code) > 50) fail('請輸入工程編號（50 字以內）');
    if ($name === '' || mb_strlen($name) > 200) fail('請輸入工程名稱（200 字以內）');
    if (db_val('SELECT id FROM pm_projects WHERE code = ? AND id <> ?', array($code, $id))) fail('工程編號已存在');
    $data = array(
        'code' => $code, 'name' => $name, 'location' => mb_substr(param('location'), 0, 200),
        'owner_unit' => mb_substr(param('owner_unit'), 0, 100), 'contractor_unit' => mb_substr(param('contractor_unit'), 0, 100),
        'start_date' => pm_date('start_date'), 'end_date' => pm_date('end_date'),
        'status' => mb_substr(param('status', '進行中'), 0, 20), 'remark' => mb_substr(param('remark'), 0, 500),
    );
    if ($id) {
        if (!db_val('SELECT id FROM pm_projects WHERE id = ?', array($id))) fail('工程不存在');
        db_update('pm_projects', $data, $id);
    } else {
        $data['created_at'] = now();
        db_insert('pm_projects', $data);
    }
    ok('保存成功');
}

if ($act === 'delete') {
    $id = param_int('id');
    if (db_val('SELECT id FROM pm_flows WHERE project_id = ? LIMIT 1', array($id))) fail('該工程已有文件審批設置，不能刪除');
    if (db_val('SELECT id FROM pm_docs WHERE project_id = ? LIMIT 1', array($id))) fail('該工程已有文件，不能刪除');
    db_q('DELETE FROM pm_projects WHERE id = ?', array($id));
    ok('刪除成功');
}
fail('未知操作');
