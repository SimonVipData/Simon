<?php
/** 即時溝通：一對一私聊與公共群聊（peer=0） */
require_once __DIR__ . '/../inc/config.php';
$act = param('act', 'users');
api_guard(false, $act === 'send');
$me = current_user();
$uid = (int)$me['id'];

if ($act === 'users') {
    $unread = array();
    foreach (db_rows('SELECT from_id, COUNT(*) AS n FROM pm_chat_msgs WHERE to_id = ? AND is_read = 0 GROUP BY from_id', array($uid)) as $r) $unread[(int)$r['from_id']] = (int)$r['n'];
    $online = date('Y-m-d H:i:s', time() - 90);
    $users = array();
    foreach (user_options($uid) as $u) {
        $users[] = array('id' => (int)$u['id'], 'name' => user_label($u), 'role' => $u['role_name'], 'unread' => isset($unread[(int)$u['id']]) ? $unread[(int)$u['id']] : 0,
            'online' => (bool)db_val('SELECT 1 FROM pm_users WHERE id = ? AND active_at >= ?', array($u['id'], $online)));
    }
    $pub = (int)db_val('SELECT COUNT(*) FROM pm_chat_msgs WHERE to_id = 0 AND from_id <> ? AND id > COALESCE((SELECT last_id FROM pm_chat_reads WHERE user_id = ?), 0)', array($uid, $uid));
    json_out(array('ok' => true, 'users' => $users, 'pub_unread' => $pub));
}

if ($act === 'msgs') {
    $peer = param_int('peer');
    $after = param_int('after');
    if ($peer) {
        $w = '((m.from_id = ? AND m.to_id = ?) OR (m.from_id = ? AND m.to_id = ?))';
        $args = array($uid, $peer, $peer, $uid);
    } else {
        $w = 'm.to_id = 0';
        $args = array();
    }
    $args[] = $after;
    $rows = db_rows("SELECT * FROM (SELECT m.id, m.from_id, m.content, m.created_at, COALESCE(NULLIF(u.real_name, ''), u.username) AS from_name
                     FROM pm_chat_msgs m LEFT JOIN pm_users u ON u.id = m.from_id WHERE $w AND m.id > ? ORDER BY m.id DESC LIMIT 200) x ORDER BY id", $args);
    if ($peer) {
        db_q('UPDATE pm_chat_msgs SET is_read = 1 WHERE to_id = ? AND from_id = ? AND is_read = 0', array($uid, $peer));
    } elseif ($rows) {
        $last = (int)$rows[count($rows) - 1]['id'];
        db_q('INSERT INTO pm_chat_reads (user_id, last_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE last_id = GREATEST(last_id, VALUES(last_id))', array($uid, $last));
    }
    json_out(array('ok' => true, 'rows' => $rows));
}

if ($act === 'send') {
    $peer = param_int('peer');
    $content = param('content');
    if ($content === '' || mb_strlen($content) > 1000) fail('請輸入 1000 字以內的內容');
    if ($peer && ($peer === $uid || !db_val('SELECT id FROM pm_users WHERE id = ? AND status = 1', array($peer)))) fail('對方不存在或已停用');
    $id = db_insert('pm_chat_msgs', array('from_id' => $uid, 'to_id' => $peer, 'content' => $content, 'created_at' => now()));
    json_out(array('ok' => true, 'id' => $id));
}
fail('未知操作');
