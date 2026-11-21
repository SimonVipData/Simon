<?php
/** 文件審批設置 API：某工程某類文件在各角色之間的審批先後順序 */
require_once __DIR__ . '/../inc/config.php';
$act = param('act', 'list');

function flow_steps($flowIds) {
    $map = array();
    if (!$flowIds) return $map;
    $in = implode(',', array_map('intval', $flowIds));
    $rows = db_rows("SELECT s.flow_id, s.step_no, s.role_id, r.name AS role_name
                     FROM pm_flow_steps s LEFT JOIN pm_roles r ON r.id = s.role_id
                     WHERE s.flow_id IN ($in) ORDER BY s.flow_id, s.step_no");
    foreach ($rows as $r) $map[$r['flow_id']][] = $r;
    return $map;
}

if ($act === 'list') {
    api_guard(true, false);
    list($limit, $offset) = page_args();
    $rows = db_rows("SELECT f.*, p.name AS project_name, p.code AS project_code,
                            (SELECT COUNT(*) FROM pm_docs d WHERE d.flow_id = f.id) AS doc_count
                     FROM pm_flows f LEFT JOIN pm_projects p ON p.id = f.project_id
                     ORDER BY f.id LIMIT $limit OFFSET $offset");
    $steps = flow_steps(array_column($rows, 'id'));
    foreach ($rows as &$r) {
        $r['steps'] = isset($steps[$r['id']]) ? $steps[$r['id']] : array();
        $r['step_text'] = implode(' → ', array_column($r['steps'], 'role_name'));
    }
    unset($r);
    json_out(array('total' => (int)db_val('SELECT COUNT(*) FROM pm_flows'), 'rows' => $rows));
}

if ($act === 'options') {
    // 當前用戶可發起的審批設置（其角色為第 1 步）
    api_guard(false, false);
    $me = current_user();
    $rows = db_rows('SELECT f.id, f.name, f.doc_type, f.project_id, p.name AS project_name
                     FROM pm_flows f JOIN pm_projects p ON p.id = f.project_id
                     JOIN pm_flow_steps s ON s.flow_id = f.id AND s.step_no = 1
                     WHERE f.status = 1 AND s.role_id = ? ORDER BY f.id', array($me['role_id']));
    $steps = flow_steps(array_column($rows, 'id'));
    foreach ($rows as &$r) {
        $r['step_text'] = isset($steps[$r['id']]) ? implode(' → ', array_column($steps[$r['id']], 'role_name')) : '';
        $r['text'] = $r['project_name'] . ' / ' . $r['name'];
    }
    unset($r);
    json_out($rows);
}

api_guard(true);

if ($act === 'save') {
    $id = param_int('id');
    $projectId = param_int('project_id');
    $name = param('name');
    $roleIds = array_values(array_filter(array_map('intval', explode(',', param('role_ids')))));

    if (!db_val('SELECT id FROM pm_projects WHERE id = ?', array($projectId))) fail('請選擇工程');
    if ($name === '' || mb_strlen($name) > 100) fail('請輸入文件名稱（100 字以內）');
    if (count($roleIds) < 2) fail('審批順序至少需要 2 個角色（第 1 步為提交角色）');
    if (count($roleIds) > 20) fail('審批順序最多 20 步');
    if (count(array_unique($roleIds)) !== count($roleIds)) fail('審批順序中同一角色不能重複');
    $in = implode(',', $roleIds);
    if ((int)db_val("SELECT COUNT(*) FROM pm_roles WHERE id IN ($in) AND type = 'user'") !== count($roleIds)) fail('審批角色只能選擇普通用戶角色');

    $data = array(
        'project_id' => $projectId, 'name' => $name, 'doc_type' => mb_substr(param('doc_type'), 0, 50),
        'remark' => mb_substr(param('remark'), 0, 255), 'status' => param_int('status', 1) === 1 ? 1 : 0, 'updated_at' => now(),
    );
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id) {
            if (!db_val('SELECT id FROM pm_flows WHERE id = ? FOR UPDATE', array($id))) throw new PmError('審批設置不存在');
            db_update('pm_flows', $data, $id);
            db_q('DELETE FROM pm_flow_steps WHERE flow_id = ?', array($id));
        } else {
            $data['created_at'] = now();
            $id = db_insert('pm_flows', $data);
        }
        foreach ($roleIds as $i => $rid) {
            db_insert('pm_flow_steps', array('flow_id' => $id, 'step_no' => $i + 1, 'role_id' => $rid));
        }
        $pdo->commit();
    } catch (PmError $e) {
        $pdo->rollBack();
        fail($e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    ok('保存成功（新的審批順序對之後提交的文件生效）');
}

if ($act === 'delete') {
    $id = param_int('id');
    if (!db_val('SELECT id FROM pm_flows WHERE id = ?', array($id))) fail('審批設置不存在');
    if (db_val('SELECT id FROM pm_docs WHERE flow_id = ? LIMIT 1', array($id))) fail('已有文件使用該審批設置，不能刪除，可改為停用');
    db_q('DELETE FROM pm_flow_steps WHERE flow_id = ?', array($id));
    db_q('DELETE FROM pm_flows WHERE id = ?', array($id));
    ok('刪除成功');
}
fail('未知操作');
