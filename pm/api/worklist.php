<?php
/** 工作清單：創建人登記工作，跟進人員上傳閉合附件閉合；只有創建人可刪除 */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/files.php';
$act = param('act', 'list');
api_guard(false, !in_array($act, array('list', 'opts'), true));
$me = current_user();
$uid = (int)$me['id'];
menu_guard('worklist');

function wl_ids($s) {
    return array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$s)))));
}

/** 能否跟進閉合：創建人、指定跟進人員；未指定跟進人員時所有用戶 */
function wl_can_close($row, $uid) {
    $f = wl_ids($row['followers']);
    return (int)$row['user_id'] === $uid || !$f || in_array($uid, $f, true);
}

function wl_row($id) {
    $row = db_row('SELECT * FROM pm_worklist WHERE id = ?', array($id));
    if (!$row) throw new PmError('記錄不存在');
    return $row;
}

/** 只刪除指定字段（desc / close）下的附件 */
function wl_del_files($id, $field) {
    $ids = files_del_param();
    if (!$ids) return;
    $own = array_map('intval', array_column(db_rows("SELECT id FROM pm_files WHERE module = 'worklist' AND ref_id = ? AND field = ?", array($id, $field)), 'id'));
    $ids = array_values(array_intersect($ids, $own));
    if ($ids) files_delete('worklist', $id, $ids);
}

if ($act === 'opts') {
    $users = array();
    foreach (user_options() as $u) $users[] = array('id' => (int)$u['id'], 'username' => $u['username'], 'real_name' => $u['real_name'], 'role_name' => $u['role_name']);
    $cats = array_column(db_rows("SELECT DISTINCT category FROM pm_worklist WHERE category <> '' ORDER BY category LIMIT 200"), 'category');
    json_out(array('ok' => true, 'users' => $users, 'cats' => $cats));
}

if ($act === 'list') {
    list($limit, $offset) = page_args();
    $where = array('1=1');
    $args = array();
    if (($kw = param('keyword')) !== '') {
        $where[] = '(w.content LIKE ? OR w.close_desc LIKE ? OR w.category LIKE ?)';
        array_push($args, like_kw($kw), like_kw($kw), like_kw($kw));
    }
    if (($c = param('category')) !== '') { $where[] = 'w.category = ?'; $args[] = $c; }
    $st = param('state');
    if ($st === 'open') $where[] = 'w.closed_at IS NULL';
    if ($st === 'closed') $where[] = 'w.closed_at IS NOT NULL';
    $mine = param('mine');
    if ($mine === 'created') { $where[] = 'w.user_id = ?'; $args[] = $uid; }
    if ($mine === 'follow') { $where[] = "(w.followers = '' OR FIND_IN_SET(?, w.followers))"; $args[] = $uid; }
    foreach (array('created_from' => 'w.created_at >= ?', 'created_to' => 'w.created_at < ?', 'closed_from' => 'w.closed_at >= ?', 'closed_to' => 'w.closed_at < ?') as $k => $cond) {
        $d = date_or_null(param($k));
        if ($d === null) continue;
        $where[] = $cond;
        $args[] = substr($k, -3) === '_to' ? date('Y-m-d', strtotime($d . ' +1 day')) : $d;
    }
    $w = implode(' AND ', $where);
    $rows = db_rows("SELECT w.*, COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name, COALESCE(NULLIF(c.real_name, ''), c.username) AS closer_name
                     FROM pm_worklist w LEFT JOIN pm_users u ON u.id = w.user_id LEFT JOIN pm_users c ON c.id = w.close_user_id
                     WHERE $w ORDER BY w.id DESC LIMIT $limit OFFSET $offset", $args);
    $names = array();
    foreach (db_rows('SELECT id, real_name, username FROM pm_users') as $u) $names[(int)$u['id']] = user_label($u);
    $fmap = files_map('worklist', array_column($rows, 'id'));
    foreach ($rows as &$r) {
        $id = (int)$r['id'];
        $f = wl_ids($r['followers']);
        $r['follower_ids'] = $f;
        $r['follower_names'] = $f ? implode('、', array_map(function ($i) use ($names) { return isset($names[$i]) ? $names[$i] : '#' . $i; }, $f)) : '所有用戶';
        $r['files_desc'] = isset($fmap[$id]['desc']) ? $fmap[$id]['desc'] : array();
        $r['files_close'] = isset($fmap[$id]['close']) ? $fmap[$id]['close'] : array();
        $r['is_owner'] = (int)$r['user_id'] === $uid;
        $r['can_close'] = wl_can_close($r, $uid);
    }
    unset($r);
    json_out(array('total' => (int)db_val("SELECT COUNT(*) FROM pm_worklist w WHERE $w", $args), 'rows' => $rows));
}

if ($act === 'save') {
    $id = param_int('id');
    $cat = param('category');
    $content = param('content');
    if ($content === '') fail('請填寫工作描述');
    if (mb_strlen($cat) > 50) fail('分類不能超過 50 字');
    if (mb_strlen($content) > 20000) fail('工作描述過長');
    $f = wl_ids(param('followers'));
    if ($f) {
        $valid = array_map('intval', array_column(db_rows('SELECT id FROM pm_users WHERE status = 1 AND id IN (' . implode(',', $f) . ')'), 'id'));
        $f = array_values(array_intersect($f, $valid));
    }
    $followers = implode(',', $f);
    if (strlen($followers) > 500) fail('跟進人員過多');
    $files = files_collect('f_desc');
    db_tx(function () use (&$id, $cat, $content, $followers, $files, $uid) {
        $data = array('category' => $cat, 'content' => $content, 'followers' => $followers, 'updated_at' => now());
        if ($id) {
            $row = wl_row($id);
            if ((int)$row['user_id'] !== $uid) throw new PmError('只有創建人可以修改工作描述');
            db_update('pm_worklist', $data, $id);
            wl_del_files($id, 'desc');
        } else {
            $id = db_insert('pm_worklist', $data + array('user_id' => $uid, 'created_at' => now()));
        }
        files_store($files, 'worklist', $id, 'desc', $uid);
    });
    ok('保存成功', array('id' => $id));
}

if ($act === 'close') {
    $id = param_int('id');
    $desc = param('close_desc');
    if (mb_strlen($desc) > 20000) fail('閉合描述過長');
    $files = files_collect('f_close');
    $closed = db_tx(function () use ($id, $desc, $files, $uid) {
        $row = wl_row($id);
        if (!wl_can_close($row, $uid)) throw new PmError('您不是該記錄的跟進人員');
        wl_del_files($id, 'close');
        files_store($files, 'worklist', $id, 'close', $uid);
        $has = (bool)db_val("SELECT 1 FROM pm_files WHERE module = 'worklist' AND ref_id = ? AND field = 'close' LIMIT 1", array($id));
        $data = array('close_desc' => $desc, 'close_user_id' => $uid, 'updated_at' => now());
        // 上傳閉合附件的時間即閉合時間；閉合附件全部刪除則重新打開
        if ($files) $data['closed_at'] = now();
        elseif (!$has) $data['closed_at'] = null;
        db_update('pm_worklist', $data, $id);
        return $has;
    });
    ok($closed ? '已閉合' : '已保存（上傳閉合附件後才算閉合）', array('closed' => $closed));
}

if ($act === 'delete') {
    $id = param_int('id');
    db_tx(function () use ($id, $uid) {
        $row = wl_row($id);
        if ((int)$row['user_id'] !== $uid) throw new PmError('只有創建人可以刪除記錄');
        files_delete('worklist', $id);
        db_q('DELETE FROM pm_worklist WHERE id = ?', array($id));
    });
    ok('刪除成功');
}
fail('未知操作');
