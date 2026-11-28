<?php
/** 控制面板 API（管理員） */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/files.php';
$act = param('act');
api_guard(true, !in_array($act, array('header_list'), true));
if ($act === 'header_list') {
    $rows = array(array('id' => 0, 'code' => '', 'name' => '默認頁眉（未單獨設置頁眉的項目使用）'));
    foreach (db_rows('SELECT id, code, name FROM pm_projects ORDER BY id') as $p) $rows[] = $p;
    $files = array();
    foreach (db_rows("SELECT f.id, f.ref_id, f.file_name, f.file_size, f.created_at, COALESCE(NULLIF(u.real_name, ''), u.username) AS user_name
                      FROM pm_files f LEFT JOIN pm_users u ON u.id = f.user_id WHERE f.module = 'cover_header' ORDER BY f.id") as $f) $files[(int)$f['ref_id']] = $f;
    foreach ($rows as &$r) {
        $f = isset($files[(int)$r['id']]) ? $files[(int)$r['id']] : null;
        $r['file_id'] = $f ? (int)$f['id'] : 0;
        $r['file_name'] = $f ? $f['file_name'] : '';
        $r['uploaded_at'] = $f ? $f['created_at'] : '';
        $r['user_name'] = $f ? $f['user_name'] : '';
    }
    unset($r);
    json_out(array('total' => count($rows), 'rows' => $rows));
}
if ($act === 'header_upload') {
    $pid = param_int('project_id');
    if ($pid && !db_val('SELECT 1 FROM pm_projects WHERE id = ?', array($pid))) fail('項目不存在');
    $list = files_collect('f_img');
    if (count($list) !== 1) fail('請選擇一張頁眉圖片');
    $img = $list[0];
    if (!in_array(strtolower(pathinfo($img['name'], PATHINFO_EXTENSION)), array('jpg', 'jpeg', 'png', 'gif'), true)) fail('頁眉只能是 JPG、PNG 或 GIF 圖片');
    if ($img['size'] > 5 * 1024 * 1024) fail('頁眉圖片不能超過 5MB');
    if (!@getimagesize($img['tmp'])) fail('圖片文件無效');
    $me = current_user();
    db_tx(function () use ($pid, $list, $me) {
        files_delete('cover_header', $pid);
        files_store($list, 'cover_header', $pid, 'img', (int)$me['id']);
    });
    ok('頁眉圖片已上傳');
}
if ($act === 'header_delete') {
    files_delete('cover_header', param_int('project_id'));
    ok('頁眉圖片已刪除');
}
if ($act === 'save') {
    $vals = array();
    foreach (panel_defs() as $k => $d) {
        if (isset($_POST[$k])) $vals[$k] = param_int($k) === 1 ? '1' : '0';
    }
    $langs = 0;
    foreach (panel_lang_defs() as $k => $t) $langs += isset($vals['lang_' . $k]) ? (int)$vals['lang_' . $k] : panel_get('lang_' . $k);
    if (!$langs) fail('至少須開啟一種系統語言');
    foreach ($vals as $k => $v) db_q('REPLACE INTO pm_meta (k, v) VALUES (?, ?)', array('panel_' . $k, $v));
    ok('保存成功');
}
fail('未知操作');
