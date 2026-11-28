<?php
/** 附件管理 API（管理員）：查找、下載、替換工程審批文件的提交附件和審批回覆附件 */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/files.php';
require_once __DIR__ . '/../inc/docfile.php';
$act = param('act', 'list');
api_guard(true, $act === 'replace');
$me = current_user();

/** 全部審批附件：main = 文件提交附件，reply = 審批回覆附件 */
function dfa_union() {
    return "SELECT 'main' AS src, d.id AS ref_id, d.id AS doc_id, d.file_name, d.file_path, d.file_size, d.creator_id AS user_id,
                   (SELECT s.role_name FROM pm_doc_steps s WHERE s.doc_id = d.id AND s.step_no = 1 ORDER BY s.round DESC LIMIT 1) AS role_name,
                   COALESCE(d.submitted_at, d.updated_at, d.created_at) AS up_at
            FROM pm_docs d WHERE d.file_name <> ''
            UNION ALL
            SELECT 'reply', f.id, f.doc_id, f.file_name, f.file_path, f.file_size, f.user_id, f.role_name, f.created_at FROM pm_doc_files f";
}

/** 取一條附件記錄 */
function dfa_get($src, $id) {
    if ($src === 'main') return db_row("SELECT id, id AS doc_id, file_name, file_path, file_size FROM pm_docs WHERE id = ? AND file_name <> ''", array($id));
    if ($src === 'reply') return db_row('SELECT id, doc_id, file_name, file_path, file_size FROM pm_doc_files WHERE id = ?', array($id));
    return null;
}

if ($act === 'list') {
    list($limit, $offset) = page_args();
    $where = array('1=1');
    $args = array();
    if (($v = param('src')) === 'main' || $v === 'reply') { $where[] = 'a.src = ?'; $args[] = $v; }
    if (($v = param_int('project_id')) > 0) { $where[] = 'd.project_id = ?'; $args[] = $v; }
    if (($kw = param('keyword')) !== '') {
        $where[] = '(d.doc_no LIKE ? OR d.title LIKE ? OR a.file_name LIKE ?)';
        $like = '%' . addcslashes($kw, '%_\\') . '%';
        array_push($args, $like, $like, $like);
    }
    if (($kw = param('uploader')) !== '') {
        $where[] = '(u.username LIKE ? OR u.real_name LIKE ?)';
        $like = '%' . addcslashes($kw, '%_\\') . '%';
        array_push($args, $like, $like);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v = param('start'))) { $where[] = 'a.up_at >= ?'; $args[] = $v . ' 00:00:00'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v = param('end'))) { $where[] = 'a.up_at <= ?'; $args[] = $v . ' 23:59:59'; }
    $w = implode(' AND ', $where);
    $from = '(' . dfa_union() . ') a JOIN pm_docs d ON d.id = a.doc_id LEFT JOIN pm_projects p ON p.id = d.project_id LEFT JOIN pm_users u ON u.id = a.user_id';
    $rows = db_rows("SELECT a.src, a.ref_id, a.doc_id, a.file_name, a.file_size, a.role_name, a.up_at, d.doc_no, d.title, d.type_code, d.disc_code,
                            p.code AS project_code, p.name AS project_name, COALESCE(NULLIF(u.real_name, ''), u.username) AS uploader,
                            (SELECT CONCAT(l.created_at, ' ', COALESCE(NULLIF(lu.real_name, ''), lu.username, ''))
                               FROM pm_doc_file_logs l LEFT JOIN pm_users lu ON lu.id = l.user_id WHERE l.src = a.src AND l.ref_id = a.ref_id ORDER BY l.id DESC LIMIT 1) AS replaced
                     FROM $from WHERE $w ORDER BY a.up_at DESC, a.ref_id DESC LIMIT $limit OFFSET $offset", $args);
    json_out(array('total' => (int)db_val("SELECT COUNT(*) FROM $from WHERE $w", $args), 'rows' => $rows));
}

if ($act === 'download') {
    $f = dfa_get(param('src'), param_int('id'));
    if (!$f || !docf_exists($f['file_path'])) {
        http_response_code(404);
        exit('附件不存在');
    }
    $path = docf_abs($f['file_path']);
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($path));
    header("Content-Disposition: attachment; filename=\"download\"; filename*=UTF-8''" . rawurlencode($f['file_name']));
    readfile($path);
    exit;
}

if ($act === 'logs') {
    $rows = db_rows("SELECT l.*, COALESCE(NULLIF(u.real_name, ''), u.username) AS user_name FROM pm_doc_file_logs l LEFT JOIN pm_users u ON u.id = l.user_id
                     WHERE l.src = ? AND l.ref_id = ? ORDER BY l.id DESC", array(param('src'), param_int('id')));
    json_out(array('total' => count($rows), 'rows' => $rows));
}

if ($act === 'replace') {
    // 替換：保留原文件名（後綴隨新文件）與目錄，舊文件移到 docs_bak/ 留底，並記錄替換人和時間
    $src = param('src');
    $id = param_int('id');
    try {
        $up = docf_upload_one('file');
    } catch (PmError $e) {
        fail($e->getMessage());
    }
    if (!$up) fail('請選擇用於替換的文件');
    $res = db_tx(function () use ($src, $id, $up, $me) {
        $f = dfa_get($src, $id);
        if (!$f) throw new PmError('附件不存在');
        $table = $src === 'main' ? 'pm_docs' : 'pm_doc_files';
        db_row("SELECT id FROM $table WHERE id = ? FOR UPDATE", array($id));
        $now = now();
        $base = pathinfo($f['file_name'], PATHINFO_FILENAME);
        $bak = '';
        if (docf_exists($f['file_path'])) {
            $bakDir = 'docs_bak/' . date('Ym');
            $bakName = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . docf_seg($f['file_name'], 180);
            if (!is_dir(UPLOAD_DIR . '/' . $bakDir)) @mkdir(UPLOAD_DIR . '/' . $bakDir, 0750, true);
            if (!@rename(docf_abs($f['file_path']), UPLOAD_DIR . '/' . $bakDir . '/' . $bakName)) throw new PmError('舊附件留底失敗，請檢查 uploads 目錄權限');
            $bak = $bakDir . '/' . $bakName;
        }
        try {
            if (strpos($f['file_path'], 'docs/') === 0) {
                $placed = docf_place($up['tmp'], dirname($f['file_path']), $base, $up['ext'], true);
            } else {
                // 未提交（擬稿）的舊式附件：仍用隨機文件名存放，提交時再按規則重命名
                $stored = date('Ym') . '_' . bin2hex(random_bytes(16)) . '.bin';
                if (!move_uploaded_file($up['tmp'], UPLOAD_DIR . '/' . $stored)) throw new PmError('附件保存失敗，請檢查 uploads 目錄權限');
                $placed = array('file_name' => docf_seg($base, 180) . '.' . $up['ext'], 'file_path' => $stored);
            }
        } catch (PmError $e) {
            if ($bak !== '') @rename(UPLOAD_DIR . '/' . $bak, docf_abs($f['file_path']));
            throw $e;
        }
        $data = $placed + array('file_size' => $up['size']);
        if ($src === 'reply') $data['updated_at'] = $now;
        db_update($table, $data, $id);
        db_insert('pm_doc_file_logs', array('src' => $src, 'ref_id' => $id, 'doc_id' => $f['doc_id'], 'old_name' => $f['file_name'], 'new_name' => $placed['file_name'],
            'upload_name' => $up['name'], 'old_size' => $f['file_size'], 'new_size' => $up['size'], 'backup_path' => $bak, 'user_id' => $me['id'], 'created_at' => $now));
        return $placed['file_name'];
    });
    json_out(array('ok' => true, 'msg' => '替換成功：' . $res));
}
fail('未知操作');
