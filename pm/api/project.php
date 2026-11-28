<?php
/** 工程信息 API：只有管理員可創建、修改、刪除；普通用戶只能查閱所屬工程 */
require_once __DIR__ . '/../inc/config.php';
$act = param('act', 'list');

/** 工程審批流程步驟：project_id => array(steps) */
function project_steps($flowIds) {
    $map = array();
    $flowIds = array_filter(array_map('intval', $flowIds));
    if (!$flowIds) return $map;
    $rows = db_rows('SELECT s.flow_id, s.step_no, s.role_id, r.name AS role_name FROM pm_flow_steps s LEFT JOIN pm_roles r ON r.id = s.role_id
                     WHERE s.flow_id IN (' . implode(',', $flowIds) . ') ORDER BY s.flow_id, s.step_no');
    foreach ($rows as $r) $map[$r['flow_id']][] = $r;
    return $map;
}

if ($act === 'list' || $act === 'options') {
    if ($act === 'list' && is_logged_in()) menu_guard('project');
    api_guard(false, false);
    $w = project_scope_sql('p.id');
    if ($act === 'options') json_out(db_rows("SELECT p.id, p.code, p.name, p.name_en FROM pm_projects p WHERE $w ORDER BY p.id"));
    list($limit, $offset) = page_args();
    $rows = db_rows("SELECT p.*, (SELECT COUNT(*) FROM pm_user_projects up WHERE up.project_id = p.id) AS user_count,
                            (SELECT COUNT(*) FROM pm_docs d WHERE d.project_id = p.id) AS doc_count
                     FROM pm_projects p WHERE $w ORDER BY p.id LIMIT $limit OFFSET $offset");
    $steps = project_steps(array_column($rows, 'flow_id'));
    foreach ($rows as &$r) {
        $s = isset($steps[$r['flow_id']]) ? $steps[$r['flow_id']] : array();
        $r['steps'] = $s;
        $r['step_text'] = implode(' → ', array_column($s, 'role_name'));
    }
    unset($r);
    json_out(array('total' => (int)db_val("SELECT COUNT(*) FROM pm_projects p WHERE $w"), 'rows' => $rows));
}

api_guard(true);

if ($act === 'save') {
    $id = param_int('id');
    $name = param('name');
    $nameEn = trim(preg_replace('/\s+/', ' ', param('name_en')));
    if ($name === '' || mb_strlen($name) > 200) fail('請輸入工程中文名（200 字以內）');
    if ($nameEn === '' || strlen($nameEn) > 40 || !preg_match('/^[A-Za-z0-9 \-]+$/', $nameEn)) fail('請輸入工程英文名（40 字符以內，只能包含英文字母、數字、空格或橫線）');
    $token = project_en_token($nameEn);
    if ($token === '') fail('工程英文名須包含英文字母或數字');
    $roleIds = array_values(array_filter(array_map('intval', explode(',', param('role_ids')))));
    if (count($roleIds) < 2) fail('請設置工程文件審批流程（至少 2 個角色，第 1 步為提交角色）');
    if (count($roleIds) > 20) fail('審批流程最多 20 步');
    if (count(array_unique($roleIds)) !== count($roleIds)) fail('審批流程中同一角色不能重複');
    if ((int)db_val('SELECT COUNT(*) FROM pm_roles WHERE id IN (' . implode(',', $roleIds) . ") AND type = 'user'") !== count($roleIds)) fail('審批角色只能選擇普通用戶角色');
    foreach (array('start_date', 'end_date') as $k) {
        $v = param($k);
        if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) fail('日期格式不正確');
    }
    $data = array(
        'name' => $name, 'name_en' => $nameEn, 'location' => mb_substr(param('location'), 0, 200), 'owner_unit' => mb_substr(param('owner_unit'), 0, 100),
        'contractor_unit' => mb_substr(param('contractor_unit'), 0, 100),
        'start_date' => param('start_date') !== '' ? param('start_date') : null, 'end_date' => param('end_date') !== '' ? param('end_date') : null,
        'status' => mb_substr(param('status', '進行中'), 0, 20), 'remark' => mb_substr(param('remark'), 0, 500),
    );
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $now = now();
        if ($id) {
            $p = db_row('SELECT * FROM pm_projects WHERE id = ? FOR UPDATE', array($id));
            if (!$p) throw new PmError('工程不存在');
            db_update('pm_projects', $data, $id);
            $flowId = (int)$p['flow_id'];
        } else {
            // 工程編號 = 工程英文名-當年月-三位年度流水號（每年從 001 開始）
            $year = (int)date('Y');
            $seq = (int)db_val('SELECT MAX(code_seq) FROM pm_projects WHERE code_year = ? FOR UPDATE', array($year));
            do {
                $seq++;
                $code = $token . '-' . date('Ym') . '-' . sprintf('%03d', $seq);
            } while (db_val('SELECT id FROM pm_projects WHERE code = ?', array($code)));
            $id = db_insert('pm_projects', $data + array('code' => $code, 'code_year' => $year, 'code_seq' => $seq, 'created_at' => $now));
            $flowId = 0;
        }
        if ($flowId && db_val('SELECT id FROM pm_flows WHERE id = ?', array($flowId))) {
            db_update('pm_flows', array('status' => 1, 'updated_at' => $now), $flowId);
            db_q('DELETE FROM pm_flow_steps WHERE flow_id = ?', array($flowId));
        } else {
            $flowId = db_insert('pm_flows', array('project_id' => $id, 'name' => '工程文件審批流程', 'doc_type' => '', 'remark' => '', 'status' => 1, 'created_at' => $now, 'updated_at' => $now));
            db_update('pm_projects', array('flow_id' => $flowId), $id);
        }
        foreach ($roleIds as $i => $rid) db_insert('pm_flow_steps', array('flow_id' => $flowId, 'step_no' => $i + 1, 'role_id' => $rid));
        $pdo->commit();
    } catch (PmError $e) {
        $pdo->rollBack();
        fail($e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    ok('保存成功', array('id' => $id, 'code' => db_val('SELECT code FROM pm_projects WHERE id = ?', array($id))));
}

if ($act === 'delete') {
    $id = param_int('id');
    if (!db_val('SELECT id FROM pm_projects WHERE id = ?', array($id))) fail('工程不存在');
    if (db_val('SELECT id FROM pm_docs WHERE project_id = ? LIMIT 1', array($id))) fail('該工程已有文件，不能刪除');
    foreach (db_rows('SELECT id FROM pm_flows WHERE project_id = ?', array($id)) as $f) db_q('DELETE FROM pm_flow_steps WHERE flow_id = ?', array($f['id']));
    db_q('DELETE FROM pm_flows WHERE project_id = ?', array($id));
    db_q('DELETE FROM pm_user_projects WHERE project_id = ?', array($id));
    db_q('DELETE FROM pm_projects WHERE id = ?', array($id));
    ok('刪除成功');
}
fail('未知操作');
