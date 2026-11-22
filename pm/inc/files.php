<?php
/**
 * 通用附件（pm_files）：module + ref_id + field 定位所屬記錄
 */
function file_allowed_ext() {
    return array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'dwg', 'dxf', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar', '7z', 'txt', 'csv');
}

/** 校驗 $_FILES[$key]（多文件），返回待保存列表；不落盤 */
function files_collect($key) {
    $out = array();
    if (empty($_FILES[$key]) || !is_array($_FILES[$key]['error'])) return $out;
    $f = $_FILES[$key];
    foreach ($f['error'] as $i => $err) {
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || $f['size'][$i] > UPLOAD_MAX_SIZE) throw new PmError('附件不能超過 50MB');
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'][$i])) throw new PmError('附件上傳失敗');
        // 不用 basename()：PHP 7 在 C locale 下會丟掉開頭的中文字符
        $name = str_replace('\\', '/', (string)$f['name'][$i]);
        $name = mb_substr(trim(substr($name, (int)strrpos('/' . $name, '/'))), -200);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, file_allowed_ext(), true)) throw new PmError('不支持的附件類型，允許：' . implode('、', file_allowed_ext()));
        $out[] = array('name' => $name, 'tmp' => $f['tmp_name'][$i], 'size' => (int)$f['size'][$i]);
    }
    if (count($out) > 20) throw new PmError('單次最多上傳 20 個附件');
    return $out;
}

/** 保存 files_collect() 的結果到記錄 */
function files_store($list, $module, $refId, $field, $userId) {
    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0750, true);
    foreach ($list as $f) {
        $stored = date('Ym') . '_' . bin2hex(random_bytes(16)) . '.bin';
        if (!move_uploaded_file($f['tmp'], UPLOAD_DIR . '/' . $stored)) throw new PmError('附件保存失敗，請檢查 uploads 目錄權限');
        db_insert('pm_files', array('module' => $module, 'ref_id' => $refId, 'field' => $field, 'file_name' => $f['name'], 'file_path' => $stored,
            'file_size' => $f['size'], 'user_id' => $userId, 'created_at' => now()));
    }
}

function file_unlink($path) {
    if ($path !== '' && preg_match('/^[0-9a-f_]+\.bin$/', $path) && is_file(UPLOAD_DIR . '/' . $path)) @unlink(UPLOAD_DIR . '/' . $path);
}

/** 刪除記錄的附件；$ids 為 null 時刪除全部 */
function files_delete($module, $refId, $ids = null) {
    $rows = db_rows('SELECT id, file_path FROM pm_files WHERE module = ? AND ref_id = ?', array($module, $refId));
    foreach ($rows as $r) {
        if ($ids !== null && !in_array((int)$r['id'], $ids, true)) continue;
        db_q('DELETE FROM pm_files WHERE id = ?', array($r['id']));
        file_unlink($r['file_path']);
    }
}

/** 批量讀取附件：返回 ref_id => field => [ {id,name,size} ] */
function files_map($module, $refIds) {
    $map = array();
    if (!$refIds) return $map;
    $in = implode(',', array_map('intval', $refIds));
    foreach (db_rows("SELECT id, ref_id, field, file_name, file_size FROM pm_files WHERE module = ? AND ref_id IN ($in) ORDER BY id", array($module)) as $r) {
        $map[(int)$r['ref_id']][$r['field']][] = array('id' => (int)$r['id'], 'name' => $r['file_name'], 'size' => (int)$r['file_size']);
    }
    return $map;
}

/** 請求中要刪除的附件 ID 列表（del_files=1,2,3） */
function files_del_param() {
    return array_values(array_filter(array_map('intval', explode(',', param('del_files')))));
}

function file_is_image($name) {
    return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), array('jpg', 'jpeg', 'png', 'gif'), true);
}
