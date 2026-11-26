<?php
/** 工程日曆：事件、圖片/附件、標記（關注 / 工程節點 / 工程材料 / 人員 / 計劃） */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/files.php';
require_once __DIR__ . '/../inc/panel.php';
$act = param('act', 'month');
api_guard(false, $act !== 'month');
$me = current_user();
$uid = (int)$me['id'];
$marks = array('is_star', 'is_node', 'is_material', 'is_person', 'is_plan');

function event_rows($where, $args, $tail = '') {
    $rows = db_rows("SELECT e.*, p.name AS project_name, COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name
                     FROM pm_events e LEFT JOIN pm_projects p ON p.id = e.project_id LEFT JOIN pm_users u ON u.id = e.user_id
                     WHERE $where $tail", $args);
    $ids = array();
    foreach ($rows as $r) $ids[] = (int)$r['id'];
    $fmap = files_map('event', $ids);
    $me = current_user();
    foreach ($rows as &$r) {
        $r['files'] = isset($fmap[(int)$r['id']]['att']) ? $fmap[(int)$r['id']]['att'] : array();
        $r['can_write'] = is_admin() || (int)$r['user_id'] === (int)$me['id'];
    }
    unset($r);
    return $rows;
}

if ($act === 'month') {
    $ym = param('ym');
    if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m) || !checkdate((int)$m[2], 1, (int)$m[1])) $ym = date('Y-m');
    $first = strtotime($ym . '-01');
    // 月視圖連同前後補齊的日期
    $from = date('Y-m-d', strtotime('-' . date('w', $first) . ' day', $first));
    $to = date('Y-m-d', strtotime('+41 day', strtotime($from)));
    $where = array('1=1');
    $args = array();
    // 控制面板開啟「只顯示自己創建的事件」時，普通用戶只看到自己的事件
    if (cal_own_only()) { $where[] = 'e.user_id = ?'; $args[] = $uid; }
    if (($pid = param_int('project_id')) > 0) { $where[] = 'e.project_id = ?'; $args[] = $pid; }
    if (($kw = param('keyword')) !== '') { $where[] = '(e.title LIKE ? OR e.content LIKE ?)'; $args[] = like_kw($kw); $args[] = like_kw($kw); }
    $w = implode(' AND ', $where);
    $out = array('ok' => true, 'own_only' => cal_own_only(), 'ym' => $ym, 'from' => $from, 'today' => date('Y-m-d'),
        'events' => event_rows("$w AND e.event_date BETWEEN ? AND ?", array_merge($args, array($from, $to)), 'ORDER BY e.event_date, e.start_time, e.id'),
        'marks' => array());
    foreach ($marks as $k) $out['marks'][$k] = event_rows("$w AND e.`$k` = 1", $args, 'ORDER BY e.event_date DESC, e.id DESC LIMIT 50');
    // 篩選工程或關鍵字時，另給出全部匹配記錄的時間線
    $out['timeline'] = ($pid > 0 || $kw !== '') ? event_rows($w, $args, 'ORDER BY e.event_date DESC, e.id DESC LIMIT 200') : null;
    json_out($out);
}

function event_editable($id, $uid) {
    $e = db_row('SELECT * FROM pm_events WHERE id = ?', array($id));
    if (!$e) fail('事件不存在');
    if (!is_admin() && (int)$e['user_id'] !== $uid) fail('只能修改或刪除自己創建的事件', 403);
    return $e;
}

if ($act === 'save') {
    $id = param_int('id');
    $title = param('title');
    $date = param('event_date');
    if ($title === '' || mb_strlen($title) > 200) fail('請輸入事件標題（200 字以內）');
    if (date_or_null($date) === null) fail('請選擇日期');
    $st = param('start_time');
    $et = param('end_time');
    foreach (array($st, $et) as $t) if ($t !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)) fail('時間格式不正確');
    if ($st !== '' && $et !== '' && $et < $st) fail('結束時間不能早於開始時間');
    $color = param('color', '#3a87ad');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#3a87ad';
    $pid = param_int('project_id');
    if ($pid && !db_val('SELECT id FROM pm_projects WHERE id = ?', array($pid))) fail('工程不存在');
    if (mb_strlen(param('content')) > 20000) fail('內容過長');
    $data = array('project_id' => $pid, 'title' => $title, 'content' => param('content'), 'event_date' => $date, 'start_time' => $st, 'end_time' => $et,
        'color' => $color, 'updated_at' => now());
    foreach ($marks as $k) $data[$k] = param($k) === '1' ? 1 : 0;
    $uploads = files_collect('f_att');
    if ($id) event_editable($id, $uid);
    db_tx(function () use (&$id, $data, $uploads, $uid) {
        if ($id) {
            db_update('pm_events', $data, $id);
            if ($del = files_del_param()) files_delete('event', $id, $del);
        } else {
            $id = db_insert('pm_events', $data + array('user_id' => $uid, 'created_at' => now()));
        }
        files_store($uploads, 'event', $id, 'att', $uid);
    });
    ok('保存成功', array('id' => $id));
}

if ($act === 'delete') {
    $e = event_editable(param_int('id'), $uid);
    db_tx(function () use ($e) {
        files_delete('event', $e['id']);
        db_q('DELETE FROM pm_events WHERE id = ?', array($e['id']));
    });
    ok('刪除成功');
}

if ($act === 'mark') {
    $k = param('k');
    if (!in_array($k, $marks, true)) fail('標記不正確');
    $e = event_editable(param_int('id'), $uid);
    db_update('pm_events', array($k => (int)$e[$k] ? 0 : 1, 'updated_at' => now()), $e['id']);
    ok('');
}
fail('未知操作');
