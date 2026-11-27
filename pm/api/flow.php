<?php
/** 流程模板 API：新建/修改工程時可選模板自動填充審批步驟 */
require_once __DIR__ . '/../inc/config.php';
$act = param('act', 'list');

function tpl_rows() {
    $rows = db_rows('SELECT * FROM pm_flow_tpls ORDER BY sort, id');
    $map = array();
    if ($rows) {
        foreach (db_rows('SELECT s.tpl_id, s.step_no, s.role_id, r.name AS role_name FROM pm_flow_tpl_steps s LEFT JOIN pm_roles r ON r.id = s.role_id
                          WHERE s.tpl_id IN (' . implode(',', array_map('intval', array_column($rows, 'id'))) . ') ORDER BY s.tpl_id, s.step_no') as $s) {
            $map[$s['tpl_id']][] = $s;
        }
    }
    foreach ($rows as &$r) {
        $r['steps'] = isset($map[$r['id']]) ? $map[$r['id']] : array();
        $r['step_text'] = implode(' → ', array_column($r['steps'], 'role_name'));
    }
    unset($r);
    return $rows;
}

api_guard(true, $act !== 'list' && $act !== 'options');

if ($act === 'list') {
    $rows = tpl_rows();
    json_out(array('total' => count($rows), 'rows' => $rows));
}
if ($act === 'options') json_out(tpl_rows());

if ($act === 'save') {
    $id = param_int('id');
    $name = param('name');
    $roleIds = array_values(array_filter(array_map('intval', explode(',', param('role_ids')))));
    if ($name === '' || mb_strlen($name) > 100) fail('請輸入模板名稱（100 字以內）');
    if (count($roleIds) < 2) fail('審批步驟至少需要 2 個角色（第 1 步為提交角色）');
    if (count($roleIds) > 20) fail('審批步驟最多 20 步');
    if (count(array_unique($roleIds)) !== count($roleIds)) fail('同一角色不能重複');
    if ((int)db_val('SELECT COUNT(*) FROM pm_roles WHERE id IN (' . implode(',', $roleIds) . ") AND type = 'user'") !== count($roleIds)) fail('審批角色只能選擇普通用戶角色');
    $data = array('name' => $name, 'remark' => mb_substr(param('remark'), 0, 255), 'sort' => param_int('sort'), 'updated_at' => now());
    $pdo = db();
    $pdo->beginTransaction();
    if ($id) {
        if (!db_val('SELECT id FROM pm_flow_tpls WHERE id = ?', array($id))) { $pdo->rollBack(); fail('模板不存在'); }
        db_update('pm_flow_tpls', $data, $id);
        db_q('DELETE FROM pm_flow_tpl_steps WHERE tpl_id = ?', array($id));
    } else {
        $id = db_insert('pm_flow_tpls', $data + array('created_at' => now()));
    }
    foreach ($roleIds as $i => $rid) db_insert('pm_flow_tpl_steps', array('tpl_id' => $id, 'step_no' => $i + 1, 'role_id' => $rid));
    $pdo->commit();
    ok('保存成功');
}

if ($act === 'delete') {
    $id = param_int('id');
    db_q('DELETE FROM pm_flow_tpl_steps WHERE tpl_id = ?', array($id));
    db_q('DELETE FROM pm_flow_tpls WHERE id = ?', array($id));
    ok('刪除成功');
}
fail('未知操作');
