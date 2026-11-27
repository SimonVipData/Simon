<?php
/** 控制面板 - 工程設置 - 文件分類：審批類型（kind=type）、專業分類（kind=disc） */
require_once __DIR__ . '/../inc/config.php';
$act = param('act', 'list');
$kind = param('kind') === 'disc' ? 'disc' : 'type';
$table = $kind === 'disc' ? 'pm_disciplines' : 'pm_doc_types';
$col = $kind === 'disc' ? 'disc_code' : 'type_code';

if ($act === 'list') {
    api_guard(false, false);
    $rows = db_rows("SELECT t.*, (SELECT COUNT(*) FROM pm_docs d WHERE d.$col = t.code) AS doc_count FROM $table t ORDER BY t.sort, t.id");
    json_out(array('total' => count($rows), 'rows' => $rows));
}
api_guard(true);

if ($act === 'save') {
    $id = param_int('id');
    $code = strtoupper(trim(param('code')));
    $name = param('name');
    if (!preg_match('/^[A-Z0-9]{1,20}$/', $code)) fail('代碼須為 1-20 位英文字母或數字');
    if ($name === '' || mb_strlen($name) > 50) fail('請輸入名稱（50 字以內）');
    if (db_val("SELECT id FROM $table WHERE code = ? AND id <> ?", array($code, $id))) fail('代碼已存在');
    $data = array('code' => $code, 'name' => $name, 'remark' => mb_substr(param('remark'), 0, 255));
    if ($kind === 'type') $data['name_en'] = mb_substr(param('name_en'), 0, 100);
    if ($id) {
        $old = db_row("SELECT * FROM $table WHERE id = ?", array($id));
        if (!$old) fail('分類不存在');
        if ($old['code'] !== $code && db_val("SELECT id FROM pm_docs WHERE $col = ? LIMIT 1", array($old['code']))) fail('已有文件使用該分類，不能修改代碼');
        db_update($table, $data, $id);
    } else {
        $data['sort'] = (int)db_val("SELECT COALESCE(MAX(sort), 0) + 1 FROM $table");
        db_insert($table, $data);
    }
    ok('保存成功');
}

if ($act === 'delete') {
    $old = db_row("SELECT * FROM $table WHERE id = ?", array(param_int('id')));
    if (!$old) fail('分類不存在');
    if (db_val("SELECT id FROM pm_docs WHERE $col = ? LIMIT 1", array($old['code']))) fail('已有文件使用該分類，不能刪除');
    db_q("DELETE FROM $table WHERE id = ?", array($old['id']));
    ok('刪除成功');
}

if ($act === 'move') {
    $rows = db_rows("SELECT id FROM $table ORDER BY sort, id");
    $ids = array_map('intval', array_column($rows, 'id'));
    $i = array_search(param_int('id'), $ids, true);
    if ($i === false) fail('分類不存在');
    $j = param('dir') === 'up' ? $i - 1 : $i + 1;
    if ($j < 0 || $j >= count($ids)) fail(param('dir') === 'up' ? '已經是第一項' : '已經是最後一項');
    $t = $ids[$i];
    $ids[$i] = $ids[$j];
    $ids[$j] = $t;
    foreach ($ids as $k => $id) db_q("UPDATE $table SET sort = ? WHERE id = ?", array($k + 1, $id));
    ok('');
}
fail('未知操作');
