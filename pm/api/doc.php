<?php
/** 工程文件 API：創建、提交、按角色順序審批 */
require_once __DIR__ . '/../inc/config.php';
$act = param('act', 'list');

function doc_allowed_ext() {
    return array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'dwg', 'dxf', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar', '7z', 'txt', 'csv');
}

/** 保存上傳附件，返回 array(file_name, file_path, file_size)；無上傳返回 null */
function doc_store_upload() {
    if (empty($_FILES['file']) || !is_array($_FILES['file']) || is_array($_FILES['file']['error'])) return null;
    $f = $_FILES['file'];
    if ($f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > UPLOAD_MAX_SIZE) throw new PmError('附件不能超過 50MB');
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) throw new PmError('附件上傳失敗');
    $name = mb_substr(basename(str_replace('\\', '/', $f['name'])), -200);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, doc_allowed_ext(), true)) throw new PmError('不支持的附件類型，允許：' . implode('、', doc_allowed_ext()));
    $stored = date('Ym') . '_' . bin2hex(random_bytes(16)) . '.bin';
    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0750, true);
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $stored)) throw new PmError('附件保存失敗，請檢查 uploads 目錄權限');
    return array('file_name' => $name, 'file_path' => $stored, 'file_size' => (int)$f['size']);
}

function doc_remove_file($path) {
    if ($path !== '' && preg_match('/^[0-9a-f_]+\.bin$/', $path) && is_file(UPLOAD_DIR . '/' . $path)) @unlink(UPLOAD_DIR . '/' . $path);
}

/** 文件編號：工程英文名-審批類型-三位流水號（擬稿即占號，需在事務內調用），返回 base_no */
function doc_reserve_no($nameEn, $typeCode) {
    $prefix = project_en_token($nameEn) . '-' . $typeCode;
    db_q('INSERT INTO pm_doc_seq (prefix, last_no) VALUES (?, 0) ON DUPLICATE KEY UPDATE last_no = last_no', array($prefix));
    $n = (int)db_val('SELECT last_no FROM pm_doc_seq WHERE prefix = ? FOR UPDATE', array($prefix));
    do {
        $n++;
        $base = $prefix . '-' . sprintf('%03d', $n);
    } while (db_val('SELECT id FROM pm_docs WHERE base_no = ? OR doc_no = ? LIMIT 1', array($base, $base)));
    db_q('UPDATE pm_doc_seq SET last_no = ? WHERE prefix = ?', array($n, $prefix));
    return $base;
}

/** 下一版本號：A → B → … → Z */
function doc_next_ver($v) {
    if (!preg_match('/^[A-Y]$/', $v)) throw new PmError('版本已達上限，不能再升版');
    return chr(ord($v) + 1);
}

function doc_copy_file($path) {
    if ($path === '' || !preg_match('/^[0-9a-f_]+\.bin$/', $path) || !is_file(UPLOAD_DIR . '/' . $path)) return '';
    $new = date('Ym') . '_' . bin2hex(random_bytes(16)) . '.bin';
    return @copy(UPLOAD_DIR . '/' . $path, UPLOAD_DIR . '/' . $new) ? $new : '';
}

/** 封面字段白名單 */
function doc_cover_keys() {
    return array('to', 'from', 'cc', 'subject', 'issue_date', 'category', 'brand', 'model', 'origin', 'other_info', 'compliance', 'spec_ref',
        'quantity', 'delivery', 'attach', 'contents', 'signer', 'sign_date', 'statement', 'remarks');
}

/** 提交審批：按審批設置生成本輪步驟（需在事務內調用） */
function doc_submit($docId, $me) {
    $doc = db_row('SELECT * FROM pm_docs WHERE id = ? FOR UPDATE', array($docId));
    if (!$doc) throw new PmError('文件不存在');
    if ((int)$doc['creator_id'] !== (int)$me['id']) throw new PmError('只能提交自己創建的文件');
    if ((int)$doc['status'] === DOC_REJECTED) throw new PmError('已駁回的文件須先「升版」，再以新版本重新申報');
    if ((int)$doc['status'] !== DOC_DRAFT) throw new PmError('該文件當前狀態不能提交');
    if (!can_see_project($doc['project_id'])) throw new PmError('您不屬於該工程，不能提交');
    if (!db_val('SELECT id FROM pm_flows WHERE id = ? AND status = 1', array($doc['flow_id']))) throw new PmError('該文件的審批設置已停用或不存在，請聯繫管理員');
    $steps = db_rows('SELECT s.step_no, s.role_id, r.name AS role_name FROM pm_flow_steps s JOIN pm_roles r ON r.id = s.role_id
                      WHERE s.flow_id = ? ORDER BY s.step_no', array($doc['flow_id']));
    if (count($steps) < 2) throw new PmError('審批設置不完整，請聯繫管理員');
    if ((int)$steps[0]['role_id'] !== (int)$me['role_id']) throw new PmError('您的角色不是該文件審批順序的提交角色');

    $round = (int)$doc['round'] + 1;
    $now = now();
    foreach ($steps as $i => $s) {
        $first = $i === 0;
        db_insert('pm_doc_steps', array(
            'doc_id' => $docId, 'round' => $round, 'step_no' => $i + 1, 'role_id' => $s['role_id'], 'role_name' => $s['role_name'],
            'status' => $first ? STEP_SUBMIT : ($i === 1 ? STEP_CURRENT : STEP_WAIT),
            'user_id' => $first ? $me['id'] : null, 'user_name' => $first ? ($me['real_name'] !== '' ? $me['real_name'] : $me['username']) : '',
            'comment' => $first ? ($round > 1 ? '重新提交審批' : '提交審批') : '', 'acted_at' => $first ? $now : null,
        ));
    }
    db_update('pm_docs', array('status' => DOC_PENDING, 'current_step' => 2, 'round' => $round, 'submitted_at' => $now, 'updated_at' => $now, 'finished_at' => null), $docId);
}

function doc_tx($fn) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (PmError $e) {
        $pdo->rollBack();
        fail($e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

$me = null;
if (in_array($act, array('list', 'get', 'todo_count', 'download', 'form_opts'), true)) {
    api_guard(false, false);
} else {
    api_guard();
}
$me = current_user();
$admin = is_admin();
$scopeSql = project_scope_sql('d.project_id');

if ($act === 'todo_count') {
    json_out(array('ok' => true, 'count' => (int)db_val(
        'SELECT COUNT(*) FROM pm_docs d JOIN pm_doc_steps s ON s.doc_id = d.id AND s.round = d.round
         WHERE d.status = ? AND s.status = ? AND s.role_id = ? AND ' . $scopeSql, array(DOC_PENDING, STEP_CURRENT, $me['role_id']))));
}

if ($act === 'list') {
    list($limit, $offset) = page_args();
    $where = array($scopeSql);
    $args = array();
    if (param_int('latest') === 1) $where[] = 'd.is_latest = 1';
    foreach (array('type_code', 'disc_code') as $k) {
        if (($v = param($k)) !== '') { $where[] = "d.$k = ?"; $args[] = $v; }
    }
    if (($pid = param_int('project_id')) > 0) { $where[] = 'd.project_id = ?'; $args[] = $pid; }
    $scope = param('scope', 'all');
    if ($scope === 'mine') {
        $where[] = 'd.creator_id = ?';
        $args[] = $me['id'];
    } elseif ($scope === 'todo') {
        $where[] = 'd.status = ' . DOC_PENDING . ' AND EXISTS (SELECT 1 FROM pm_doc_steps s WHERE s.doc_id = d.id AND s.round = d.round AND s.status = ' . STEP_CURRENT . ' AND s.role_id = ?)';
        $args[] = $me['role_id'];
    } elseif ($scope === 'done') {
        $where[] = 'EXISTS (SELECT 1 FROM pm_doc_steps s WHERE s.doc_id = d.id AND s.user_id = ? AND s.status IN (' . STEP_PASS . ',' . STEP_REJECT . '))';
        $args[] = $me['id'];
    }
    if (($kw = param('keyword')) !== '') {
        $where[] = '(d.title LIKE ? OR d.doc_no LIKE ?)';
        $like = '%' . addcslashes($kw, '%_\\') . '%';
        array_push($args, $like, $like);
    }
    $st = param('status');
    if ($st !== '' && is_numeric($st)) {
        $where[] = 'd.status = ?';
        $args[] = (int)$st;
    }
    $w = implode(' AND ', $where);
    $rows = db_rows("SELECT d.id, d.doc_no, d.title, d.doc_type, d.file_name, d.file_size, d.creator_id, d.status, d.round, d.created_at, d.submitted_at, d.finished_at,
                            d.project_id, d.type_code, d.disc_code, d.base_no, d.version, d.ver_src, d.is_latest, d.parent_id,
                            p.name AS project_name, p.name_en AS project_en, t.name AS type_name, c.name AS disc_name, f.name AS flow_name,
                            COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name,
                            (SELECT s.role_name FROM pm_doc_steps s WHERE s.doc_id = d.id AND s.round = d.round AND s.status = " . STEP_CURRENT . " LIMIT 1) AS cur_role
                     FROM pm_docs d
                     LEFT JOIN pm_projects p ON p.id = d.project_id
                     LEFT JOIN pm_flows f ON f.id = d.flow_id
                     LEFT JOIN pm_users u ON u.id = d.creator_id
                     LEFT JOIN pm_doc_types t ON t.code = d.type_code
                     LEFT JOIN pm_disciplines c ON c.code = d.disc_code
                     WHERE $w ORDER BY d.id DESC LIMIT $limit OFFSET $offset", $args);
    foreach ($rows as &$r) {
        $r['status_text'] = doc_status_text($r['status']);
        if ((int)$r['status'] !== DOC_PENDING) $r['cur_role'] = '';
        $r['ver_text'] = (int)$r['ver_src'] === 1 ? '駁回升版' : '初始版本';
    }
    unset($r);
    json_out(array('total' => (int)db_val("SELECT COUNT(*) FROM pm_docs d WHERE $w", $args), 'rows' => $rows));
}

if ($act === 'get') {
    $doc = db_row('SELECT id, doc_no, project_id, flow_id, type_code, disc_code, title, content, file_name, status, creator_id, cover FROM pm_docs WHERE id = ?', array(param_int('id')));
    if (!$doc || !can_see_project($doc['project_id'])) fail('文件不存在或您不屬於該工程');
    $doc['cover'] = $doc['cover'] ? json_decode($doc['cover'], true) : null;
    json_out(array('ok' => true, 'doc' => $doc));
}

if ($act === 'download') {
    $doc = db_row('SELECT project_id, file_name, file_path FROM pm_docs WHERE id = ?', array(param_int('id')));
    if ($doc && !can_see_project($doc['project_id'])) $doc = null;
    $path = $doc ? UPLOAD_DIR . '/' . $doc['file_path'] : '';
    if (!$doc || $doc['file_path'] === '' || !preg_match('/^[0-9a-f_]+\.bin$/', $doc['file_path']) || !is_file($path)) {
        http_response_code(404);
        exit('附件不存在');
    }
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($path));
    header("Content-Disposition: attachment; filename=\"download\"; filename*=UTF-8''" . rawurlencode($doc['file_name']));
    readfile($path);
    exit;
}

if ($act === 'form_opts') {
    // 新建文件可選項：所屬工程中，本人角色為審批流程第 1 步的工程
    $projects = db_rows("SELECT p.id, p.code, p.name, p.name_en, (SELECT GROUP_CONCAT(r.name ORDER BY s.step_no SEPARATOR ' → ') FROM pm_flow_steps s JOIN pm_roles r ON r.id = s.role_id WHERE s.flow_id = p.flow_id) AS step_text
                         FROM pm_projects p JOIN pm_flow_steps s1 ON s1.flow_id = p.flow_id AND s1.step_no = 1
                         JOIN pm_flows f ON f.id = p.flow_id AND f.status = 1
                         WHERE s1.role_id = ? AND " . project_scope_sql('p.id') . ' ORDER BY p.id', array($me['role_id']));
    json_out(array('ok' => true, 'projects' => $projects, 'has_project' => $admin || (bool)my_project_ids(),
        'types' => db_rows('SELECT code, name, name_en FROM pm_doc_types ORDER BY sort, id'),
        'discs' => db_rows('SELECT code, name FROM pm_disciplines ORDER BY sort, id')));
}

if ($act === 'save') {
    if ($admin) fail('管理員不參與文件提交，請使用普通用戶帳號');
    $id = param_int('id');
    $title = param('title');
    if ($title === '' || mb_strlen($title) > 200) fail('請輸入文件標題（200 字以內）');
    $content = param('content');
    if (mb_strlen($content) > 20000) fail('文件說明過長');
    $disc = param('disc_code');
    if (!db_val('SELECT id FROM pm_disciplines WHERE code = ?', array($disc))) fail('請選擇專業分類');
    if (!$id) {
        $project = db_row('SELECT p.*, (SELECT s.role_id FROM pm_flow_steps s WHERE s.flow_id = p.flow_id AND s.step_no = 1) AS first_role
                           FROM pm_projects p JOIN pm_flows f ON f.id = p.flow_id AND f.status = 1 WHERE p.id = ?', array(param_int('project_id')));
        if (!$project || !can_see_project($project['id'])) fail('請選擇所屬工程');
        if ((int)$project['first_role'] !== (int)$me['role_id']) fail('您的角色不是該工程審批流程的提交角色（第 1 步）');
        $type = db_row('SELECT code, name FROM pm_doc_types WHERE code = ?', array(param('type_code')));
        if (!$type) fail('請選擇審批類型');
    }
    try {
        $upload = doc_store_upload();
    } catch (PmError $e) {
        fail($e->getMessage());
    }
    $oldFile = '';
    $newId = doc_tx(function () use ($id, $title, $content, $disc, $upload, $me, &$oldFile, &$project, &$type) {
        $now = now();
        $data = array('title' => $title, 'content' => $content, 'disc_code' => $disc, 'updated_at' => $now);
        if ($upload) $data = array_merge($data, $upload);
        if ($id) {
            $doc = db_row('SELECT * FROM pm_docs WHERE id = ? FOR UPDATE', array($id));
            if (!$doc) throw new PmError('文件不存在');
            if ((int)$doc['creator_id'] !== (int)$me['id']) throw new PmError('只能修改自己創建的文件');
            if ((int)$doc['status'] === DOC_REJECTED) throw new PmError('已駁回的文件不能修改，請「升版」後修改新版本');
            if ((int)$doc['status'] !== DOC_DRAFT) throw new PmError('審批中或已通過的文件不能修改');
            if ($upload) $oldFile = $doc['file_path'];
            db_update('pm_docs', $data, $id);
        } else {
            $base = doc_reserve_no($project['name_en'], $type['code']);
            $data += array('project_id' => $project['id'], 'flow_id' => $project['flow_id'], 'type_code' => $type['code'], 'doc_type' => $type['name'],
                'base_no' => $base, 'version' => 'A', 'ver_src' => 0, 'is_latest' => 1, 'doc_no' => $base . '-A',
                'creator_id' => $me['id'], 'status' => DOC_DRAFT, 'created_at' => $now);
            $id = db_insert('pm_docs', $data);
        }
        if (param_int('submit') === 1) doc_submit($id, $me);
        return $id;
    });
    doc_remove_file($oldFile);
    ok(param_int('submit') === 1 ? '已提交審批' : '保存成功', array('id' => $newId, 'doc_no' => db_val('SELECT doc_no FROM pm_docs WHERE id = ?', array($newId))));
}

if ($act === 'upgrade') {
    // 駁回升版：保留原版本記錄，複製為下一版本（擬稿），修改後重新申報
    $id = param_int('id');
    $newId = doc_tx(function () use ($id, $me) {
        $doc = db_row('SELECT * FROM pm_docs WHERE id = ? FOR UPDATE', array($id));
        if (!$doc) throw new PmError('文件不存在');
        if ((int)$doc['creator_id'] !== (int)$me['id']) throw new PmError('只能為自己創建的文件升版');
        if ((int)$doc['status'] !== DOC_REJECTED) throw new PmError('只有被駁回的文件才能升版');
        if (!(int)$doc['is_latest']) throw new PmError('該文件已升版，請在最新版本上操作');
        if (!can_see_project($doc['project_id'])) throw new PmError('您不屬於該工程');
        $flowId = (int)db_val('SELECT flow_id FROM pm_projects WHERE id = ?', array($doc['project_id']));
        $ver = doc_next_ver($doc['version']);
        $base = $doc['base_no'] !== '' ? $doc['base_no'] : $doc['doc_no'];
        $now = now();
        $file = doc_copy_file($doc['file_path']);
        $newId = db_insert('pm_docs', array(
            'doc_no' => $base . '-' . $ver, 'base_no' => $base, 'version' => $ver, 'ver_src' => 1, 'parent_id' => $doc['id'], 'is_latest' => 1,
            'project_id' => $doc['project_id'], 'flow_id' => $flowId ? $flowId : $doc['flow_id'], 'title' => $doc['title'], 'doc_type' => $doc['doc_type'],
            'type_code' => $doc['type_code'], 'disc_code' => $doc['disc_code'], 'content' => $doc['content'], 'cover' => $doc['cover'],
            'file_name' => $file !== '' ? $doc['file_name'] : '', 'file_path' => $file, 'file_size' => $file !== '' ? $doc['file_size'] : 0,
            'creator_id' => $me['id'], 'status' => DOC_DRAFT, 'created_at' => $now, 'updated_at' => $now,
        ));
        db_update('pm_docs', array('is_latest' => 0), $doc['id']);
        return $newId;
    });
    ok('已升版為 ' . db_val('SELECT doc_no FROM pm_docs WHERE id = ?', array($newId)) . '，請修改後重新提交審批', array('id' => $newId));
}

if ($act === 'cover_save') {
    $id = param_int('id');
    $doc = db_row('SELECT * FROM pm_docs WHERE id = ?', array($id));
    if (!$doc) fail('文件不存在');
    if ((int)$doc['creator_id'] !== (int)$me['id']) fail('只能編輯自己創建的文件封面');
    if ((int)$doc['status'] !== DOC_DRAFT) fail('只有擬稿狀態的文件可以編輯封面');
    $cover = array();
    foreach (doc_cover_keys() as $k) $cover[$k] = mb_substr(param($k), 0, 2000);
    db_update('pm_docs', array('cover' => json_encode($cover, JSON_UNESCAPED_UNICODE), 'updated_at' => now()), $id);
    ok('封面已保存');
}

if ($act === 'submit') {
    $id = param_int('id');
    doc_tx(function () use ($id, $me) { doc_submit($id, $me); });
    ok('已提交審批');
}

if ($act === 'approve' || $act === 'reject') {
    $id = param_int('id');
    $comment = mb_substr(param('comment'), 0, 500);
    $pass = $act === 'approve';
    if (!$pass && $comment === '') fail('退回時請填寫審批意見');
    doc_tx(function () use ($id, $me, $comment, $pass) {
        $doc = db_row('SELECT * FROM pm_docs WHERE id = ? FOR UPDATE', array($id));
        if (!$doc || (int)$doc['status'] !== DOC_PENDING) throw new PmError('該文件當前不在審批中');
        if (!can_see_project($doc['project_id'])) throw new PmError('您不屬於該工程，不能審批');
        $step = db_row('SELECT * FROM pm_doc_steps WHERE doc_id = ? AND round = ? AND status = ? ORDER BY step_no LIMIT 1', array($id, $doc['round'], STEP_CURRENT));
        if (!$step) throw new PmError('未找到當前審批步驟');
        if ((int)$step['role_id'] !== (int)$me['role_id']) throw new PmError('尚未輪到您的角色審批（當前：' . $step['role_name'] . '）');
        $now = now();
        db_update('pm_doc_steps', array(
            'status' => $pass ? STEP_PASS : STEP_REJECT, 'user_id' => $me['id'],
            'user_name' => $me['real_name'] !== '' ? $me['real_name'] : $me['username'],
            'comment' => $comment !== '' ? $comment : '同意', 'acted_at' => $now,
        ), $step['id']);
        if (!$pass) {
            db_update('pm_docs', array('status' => DOC_REJECTED, 'current_step' => 0, 'updated_at' => $now, 'finished_at' => $now), $id);
            return;
        }
        $next = db_row('SELECT id, step_no FROM pm_doc_steps WHERE doc_id = ? AND round = ? AND step_no > ? ORDER BY step_no LIMIT 1', array($id, $doc['round'], $step['step_no']));
        if ($next) {
            db_update('pm_doc_steps', array('status' => STEP_CURRENT), $next['id']);
            db_update('pm_docs', array('current_step' => $next['step_no'], 'updated_at' => $now), $id);
        } else {
            db_update('pm_docs', array('status' => DOC_APPROVED, 'current_step' => 0, 'updated_at' => $now, 'finished_at' => $now), $id);
        }
    });
    ok($pass ? '審批通過' : '已退回');
}

if ($act === 'delete') {
    $id = param_int('id');
    $file = doc_tx(function () use ($id, $me, $admin) {
        $doc = db_row('SELECT * FROM pm_docs WHERE id = ? FOR UPDATE', array($id));
        if (!$doc) throw new PmError('文件不存在');
        if (!$admin) {
            if ((int)$doc['creator_id'] !== (int)$me['id']) throw new PmError('只能刪除自己創建的文件');
            if ((int)$doc['status'] !== DOC_DRAFT) throw new PmError('只有擬稿狀態的文件可以刪除（文件編號仍保留占用）');
        }
        if ((int)$doc['is_latest'] && (int)$doc['parent_id']) db_update('pm_docs', array('is_latest' => 1), $doc['parent_id']);
        db_q('DELETE FROM pm_doc_steps WHERE doc_id = ?', array($id));
        db_q('DELETE FROM pm_docs WHERE id = ?', array($id));
        return $doc['file_path'];
    });
    doc_remove_file($file);
    ok('刪除成功');
}
fail('未知操作');
