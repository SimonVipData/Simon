<?php
/** 通用數據模塊 API：list / save / delete / toggle */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/modules.php';
require_once __DIR__ . '/../inc/files.php';
$act = param('act', 'list');
$name = param('m');
$mod = pm_module($name);
if (!$mod) fail('模塊不存在', 404);
if (in_array(menu_key_of_page($name), array('note', 'todo', 'customer', 'material', 'sub', 'labor'), true) && is_logged_in()) menu_guard(menu_key_of_page($name));
$t = $mod['table'];
$fields = $mod['fields'];
$fileFields = isset($mod['files']) ? $mod['files'] : array();
$parent = isset($mod['parent']) ? pm_module($mod['parent']) : null;

if ($act === 'list') {
    api_guard($mod['scope'] === 'admin' && $name !== 'position' && $name !== 'forum_board', false);
    $me = current_user();
    list($limit, $offset) = page_args();
    $where = array('1=1');
    $args = array();
    if ($mod['scope'] === 'own') { $where[] = 't.user_id = ?'; $args[] = $me['id']; }
    if ($parent) { $where[] = 't.parent_id = ?'; $args[] = param_int('parent_id'); }
    if (($kw = param('keyword')) !== '') {
        $or = array();
        foreach ($fields as $k => $f) {
            if (!empty($f['search'])) { $or[] = "t.`$k` LIKE ?"; $args[] = like_kw($kw); }
        }
        if ($or) $where[] = '(' . implode(' OR ', $or) . ')';
    }
    foreach ($fields as $k => $f) {
        if (!empty($f['filter']) && ($v = param('f_' . $k)) !== '' && isset($f['options'][$v])) { $where[] = "t.`$k` = ?"; $args[] = $v; }
    }
    $w = implode(' AND ', $where);
    $order = preg_replace('/(^|,\s*)([a-z_]+)/', '$1t.$2', $mod['order']);
    $rows = db_rows("SELECT t.*, COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name FROM `$t` t LEFT JOIN pm_users u ON u.id = t.user_id
                     WHERE $w ORDER BY $order LIMIT $limit OFFSET $offset", $args);
    $ids = array();
    foreach ($rows as $r) $ids[] = (int)$r['id'];
    $fmap = $fileFields ? files_map($name, $ids) : array();
    foreach ($rows as &$r) {
        $r['can_write'] = module_can_write($mod, $r, $me);
        foreach ($fileFields as $k => $label) $r['files_' . $k] = isset($fmap[(int)$r['id']][$k]) ? $fmap[(int)$r['id']][$k] : array();
    }
    unset($r);
    json_out(array('total' => (int)db_val("SELECT COUNT(*) FROM `$t` t WHERE $w", $args), 'rows' => $rows));
}

api_guard($mod['scope'] === 'admin');
$me = current_user();

function crud_row($t, $id, $mod, $me) {
    $row = db_row("SELECT * FROM `$t` WHERE id = ?", array($id));
    if (!$row || ($mod['scope'] === 'own' && (int)$row['user_id'] !== (int)$me['id'])) throw new PmError('記錄不存在');
    if (!module_can_write($mod, $row, $me)) throw new PmError('只能修改或刪除自己創建的記錄');
    return $row;
}

if ($act === 'save') {
    $id = param_int('id');
    $data = array();
    foreach ($fields as $k => $f) {
        $type = isset($f['type']) ? $f['type'] : 'text';
        $v = param($k);
        if (!empty($f['required']) && $v === '') fail('請填寫' . $f['label']);
        if ($type === 'select') {
            if ($v === '' && isset($f['default'])) $v = $f['default'];
            if ($v !== '' && !isset($f['options'][$v])) fail($f['label'] . '選項不正確');
        } elseif ($type === 'date') {
            if ($v !== '' && date_or_null($v) === null) fail($f['label'] . '格式不正確');
            $v = $v === '' ? null : $v;
        } elseif ($type === 'number') {
            if ($v !== '' && (!is_numeric($v) || abs((float)$v) > 999999999999)) fail($f['label'] . '須為數字');
            $v = !empty($f['int']) ? (int)$v : ($v === '' ? null : $v);
        } else {
            $max = isset($f['max']) ? $f['max'] : ($type === 'textarea' ? 20000 : 255);
            if (mb_strlen($v) > $max) fail($f['label'] . '不能超過 ' . $max . ' 字');
        }
        $data[$k] = $v;
    }
    if (!empty($mod['unique']) && db_val("SELECT id FROM `$t` WHERE `{$mod['unique']}` = ? AND id <> ?", array($data[$mod['unique']], $id))) {
        fail($fields[$mod['unique']]['label'] . '已存在');
    }
    $uploads = array();
    foreach ($fileFields as $k => $label) $uploads[$k] = files_collect('f_' . $k);
    $data['updated_at'] = now();
    db_tx(function () use (&$id, $data, $t, $mod, $me, $parent, $name, $uploads) {
        if ($id) {
            crud_row($t, $id, $mod, $me);
            db_update($t, $data, $id);
            if ($del = files_del_param()) files_delete($name, $id, $del);
        } else {
            if ($parent) {
                $pid = param_int('parent_id');
                if (!db_val("SELECT id FROM `{$parent['table']}` WHERE id = ?", array($pid))) throw new PmError('所屬記錄不存在');
                $data['parent_id'] = $pid;
            }
            $data['user_id'] = $me['id'];
            $data['created_at'] = now();
            $id = db_insert($t, $data);
        }
        foreach ($uploads as $k => $list) files_store($list, $name, $id, $k, $me['id']);
    });
    ok('保存成功', array('id' => $id));
}

if ($act === 'delete') {
    $id = param_int('id');
    crud_row($t, $id, $mod, $me);
    if ($name === 'forum_board' && db_val('SELECT id FROM pm_forum_threads WHERE board_id = ? LIMIT 1', array($id))) fail('該版塊下仍有主題，不能刪除');
    db_tx(function () use ($id, $t, $mod, $name) {
        if (!empty($mod['child'])) {
            $cn = $mod['child'][0];
            $child = pm_module($cn);
            foreach (db_rows("SELECT id FROM `{$child['table']}` WHERE parent_id = ?", array($id)) as $c) files_delete($cn, $c['id']);
            db_q("DELETE FROM `{$child['table']}` WHERE parent_id = ?", array($id));
        }
        if ($name === 'position') db_q('UPDATE pm_users SET position_id = 0 WHERE position_id = ?', array($id));
        if ($name === 'notice') db_q('DELETE FROM pm_notice_reads WHERE notice_id = ?', array($id));
        files_delete($name, $id);
        db_q("DELETE FROM `$t` WHERE id = ?", array($id));
    });
    ok('刪除成功');
}

if ($act === 'toggle' && !empty($mod['toggle'])) {
    $row = crud_row($t, param_int('id'), $mod, $me);
    $k = $mod['toggle'];
    db_update($t, array($k => (int)$row[$k] ? 0 : 1, 'updated_at' => now()), $row['id']);
    ok('操作成功');
}
fail('未知操作');
