<?php
/** 網盤資料 API：個人文件 / 共享文件 / 公司文件 */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/files.php';
$act = param('act', 'list');
api_guard(false, !in_array($act, array('list', 'tree'), true));
$me = current_user();
$uid = (int)$me['id'];
$admin = is_admin();
$spaces = disk_spaces();
$space = param('space');
if (!isset($spaces[$space])) fail('未知的網盤');
menu_guard('disk_' . $space);

/** 當前用戶在該網盤可見的條件（個人文件只看自己的） */
function disk_scope($space, $uid, $alias = '') {
    return $alias . "space = '" . $space . "'" . ($space === 'personal' ? ' AND ' . $alias . 'user_id = ' . (int)$uid : '');
}

function disk_can_create($space, $admin) {
    return $space !== 'company' || $admin;
}

function disk_can_write($space, $row, $uid, $admin) {
    if ($space === 'personal') return (int)$row['user_id'] === $uid;
    if ($space === 'company') return $admin;
    return $admin || (int)$row['user_id'] === $uid;
}

/** 校驗目標文件夾（0 為根目錄） */
function disk_dir($space, $id, $uid) {
    if ($id === 0) return null;
    $d = db_row('SELECT * FROM pm_disk WHERE id = ? AND is_dir = 1 AND ' . disk_scope($space, $uid), array($id));
    if (!$d) fail('文件夾不存在');
    return $d;
}

function disk_dirs($space, $uid) {
    $map = array();
    foreach (db_rows('SELECT id, parent_id, name FROM pm_disk WHERE is_dir = 1 AND ' . disk_scope($space, $uid) . ' ORDER BY name, id') as $d) $map[(int)$d['id']] = $d;
    return $map;
}

/** 從根到該文件夾的路徑 [{id,name}] */
function disk_crumbs($dirs, $id) {
    $out = array();
    for ($i = 0; $id && isset($dirs[$id]) && $i < 100; $i++) {
        array_unshift($out, array('id' => $id, 'name' => $dirs[$id]['name']));
        $id = (int)$dirs[$id]['parent_id'];
    }
    return $out;
}

function disk_name($name) {
    $name = trim(preg_replace('/[\x00-\x1f\/\\\\:*?"<>|]+/u', ' ', $name));
    if ($name === '' || $name === '.' || $name === '..') fail('名稱不能為空，且不能包含 / \\ : * ? " < > |');
    if (mb_strlen($name) > 150) fail('名稱不能超過 150 個字');
    return $name;
}

function disk_exists($space, $uid, $parent, $name, $isDir, $exceptId = 0) {
    return (bool)db_val('SELECT id FROM pm_disk WHERE parent_id = ? AND is_dir = ? AND name = ? AND id <> ? AND ' . disk_scope($space, $uid) . ' LIMIT 1',
        array($parent, $isDir, $name, $exceptId));
}

/** 同一文件夾內文件重名時自動加序號：a.pdf → a(1).pdf */
function disk_free_name($space, $uid, $parent, $name) {
    if (!disk_exists($space, $uid, $parent, $name, 0)) return $name;
    $dot = strrpos($name, '.');
    $base = $dot ? substr($name, 0, $dot) : $name;
    $ext = $dot ? substr($name, $dot) : '';
    for ($i = 1; $i < 1000; $i++) {
        $try = $base . '(' . $i . ')' . $ext;
        if (!disk_exists($space, $uid, $parent, $try, 0)) return $try;
    }
    fail('同名文件過多，請先整理文件夾');
}

if ($act === 'tree') {
    $kids = array();
    foreach (disk_dirs($space, $uid) as $id => $d) $kids[(int)$d['parent_id']][] = $d;
    $build = function ($pid, $depth) use (&$build, $kids) {
        $out = array();
        if ($depth > 50 || empty($kids[$pid])) return $out;
        foreach ($kids[$pid] as $d) {
            $out[] = array('id' => (int)$d['id'], 'text' => $d['name'], 'children' => $build((int)$d['id'], $depth + 1));
        }
        return $out;
    };
    json_out(array(array('id' => 0, 'text' => $spaces[$space], 'children' => $build(0, 0))));
}

if ($act === 'list') {
    list($limit, $offset) = page_args();
    $parent = param_int('parent_id');
    $dirs = disk_dirs($space, $uid);
    if ($parent && !isset($dirs[$parent])) $parent = 0;
    $where = array(disk_scope($space, $uid, 'd.'));
    $args = array();
    $kw = param('keyword');
    $from = date_or_null(param('start'));
    $to = date_or_null(param('end'));
    $search = $kw !== '' || $from || $to;
    if ($kw !== '') {
        $where[] = '(d.name LIKE ? OR d.remark LIKE ?)';
        array_push($args, like_kw($kw), like_kw($kw));
    }
    if ($from) { $where[] = 'd.created_at >= ?'; $args[] = $from . ' 00:00:00'; }
    if ($to) { $where[] = 'd.created_at <= ?'; $args[] = $to . ' 23:59:59'; }
    // 有查詢條件時搜索整個網盤，否則只列出當前文件夾
    if (!$search) { $where[] = 'd.parent_id = ?'; $args[] = $parent; }
    $w = implode(' AND ', $where);
    $rows = db_rows("SELECT d.id, d.parent_id, d.is_dir, d.name, d.remark, d.size, d.user_id, d.created_at, d.updated_at,
                            u.real_name AS creator_name, f.id AS file_id
                     FROM pm_disk d LEFT JOIN pm_users u ON u.id = d.user_id
                     LEFT JOIN pm_files f ON f.module = 'disk' AND f.ref_id = d.id
                     WHERE $w ORDER BY d.is_dir DESC, d.name, d.id LIMIT $limit OFFSET $offset", $args);
    foreach ($rows as &$r) {
        $r['can_write'] = disk_can_write($space, $r, $uid, $admin) ? 1 : 0;
        $path = array_map(function ($c) { return $c['name']; }, disk_crumbs($dirs, (int)$r['parent_id']));
        $r['path'] = '/' . implode('/', $path);
    }
    unset($r);
    json_out(array('total' => (int)db_val("SELECT COUNT(*) FROM pm_disk d WHERE $w", $args), 'rows' => $rows,
        'parent_id' => $parent, 'crumbs' => disk_crumbs($dirs, $parent), 'search' => $search ? 1 : 0));
}

try {
    if ($act === 'mkdir') {
        if (!disk_can_create($space, $admin)) fail('只有管理員才能管理公司文件', 403);
        $parent = param_int('parent_id');
        disk_dir($space, $parent, $uid);
        $name = disk_name(param('name'));
        if (disk_exists($space, $uid, $parent, $name, 1)) fail('同一目錄下已有同名文件夾');
        $id = db_insert('pm_disk', array('space' => $space, 'parent_id' => $parent, 'is_dir' => 1, 'name' => $name,
            'remark' => mb_substr(param('remark'), 0, 255), 'user_id' => $uid, 'created_at' => now()));
        ok('文件夾已創建', array('id' => $id));
    }

    if ($act === 'upload') {
        if (!disk_can_create($space, $admin)) fail('只有管理員才能管理公司文件', 403);
        $parent = param_int('parent_id');
        disk_dir($space, $parent, $uid);
        $list = files_collect('f_file');
        if (!$list) fail('請選擇要上傳的文件');
        $remark = mb_substr(param('remark'), 0, 255);
        foreach ($list as $i => $f) $list[$i]['name'] = disk_name($f['name']);
        $ids = db_tx(function () use ($list, $space, $parent, $uid, $remark) {
            $ids = array();
            foreach ($list as $f) {
                $f['name'] = disk_free_name($space, $uid, $parent, $f['name']);
                $id = db_insert('pm_disk', array('space' => $space, 'parent_id' => $parent, 'is_dir' => 0, 'name' => $f['name'],
                    'remark' => $remark, 'size' => $f['size'], 'user_id' => $uid, 'created_at' => now()));
                files_store(array($f), 'disk', $id, 'file', $uid);
                $ids[] = $id;
            }
            return $ids;
        });
        ok('已上傳 ' . count($ids) . ' 個文件', array('ids' => $ids));
    }

    if ($act === 'save') {
        $row = db_row('SELECT * FROM pm_disk WHERE id = ? AND ' . disk_scope($space, $uid), array(param_int('id')));
        if (!$row) fail('文件不存在');
        if (!disk_can_write($space, $row, $uid, $admin)) fail($space === 'company' ? '只有管理員才能管理公司文件' : '只能修改自己創建的文件', 403);
        $id = (int)$row['id'];
        $isDir = (int)$row['is_dir'];
        $name = disk_name(param('name'));
        if (!$isDir) {
            // 文件改名不允許改變擴展名
            $ext = strtolower(pathinfo($row['name'], PATHINFO_EXTENSION));
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $ext) $name .= '.' . $ext;
        }
        $parent = isset($_POST['parent_id']) ? param_int('parent_id') : (int)$row['parent_id'];
        disk_dir($space, $parent, $uid);
        if ($isDir && $parent !== (int)$row['parent_id']) {
            $dirs = disk_dirs($space, $uid);
            foreach (disk_crumbs($dirs, $parent) as $c) {
                if ($c['id'] === $id) fail('不能把文件夾移動到它自己或其子文件夾內');
            }
        }
        if (disk_exists($space, $uid, $parent, $name, $isDir, $id)) fail($isDir ? '同一目錄下已有同名文件夾' : '目標文件夾內已有同名文件');
        db_tx(function () use ($id, $name, $parent, $isDir) {
            db_q('UPDATE pm_disk SET name = ?, remark = ?, parent_id = ?, updated_at = ? WHERE id = ?',
                array($name, mb_substr(param('remark'), 0, 255), $parent, now(), $id));
            if (!$isDir) db_q("UPDATE pm_files SET file_name = ? WHERE module = 'disk' AND ref_id = ?", array($name, $id));
        });
        ok('保存成功');
    }

    if ($act === 'delete') {
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', param('ids'))))));
        if (!$ids) fail('請選擇要刪除的文件');
        if (count($ids) > 200) fail('一次最多刪除 200 項');
        $rows = db_rows('SELECT * FROM pm_disk WHERE id IN (' . implode(',', $ids) . ') AND ' . disk_scope($space, $uid));
        if (count($rows) !== count($ids)) fail('文件不存在或已被刪除');
        foreach ($rows as $r) {
            if (!disk_can_write($space, $r, $uid, $admin)) fail($space === 'company' ? '只有管理員才能管理公司文件' : '只能刪除自己創建的文件', 403);
            if ($r['is_dir'] && db_val('SELECT id FROM pm_disk WHERE parent_id = ? AND id NOT IN (' . implode(',', $ids) . ') LIMIT 1', array($r['id']))) {
                fail('文件夾「' . $r['name'] . '」內還有文件，請先清空');
            }
        }
        // 被一併勾選的子項隨文件夾刪除；未勾選的子項已在上面攔截
        foreach ($rows as $r) {
            db_q('DELETE FROM pm_disk WHERE id = ?', array($r['id']));
            if (!$r['is_dir']) files_delete('disk', (int)$r['id']);
        }
        ok('已刪除 ' . count($rows) . ' 項');
    }
} catch (PmError $e) {
    fail($e->getMessage());
}
fail('未知操作');
