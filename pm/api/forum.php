<?php
/** 工程論壇：主題、回覆、置頂、鎖定、付費查看 / 下載附件 */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/files.php';
require_once __DIR__ . '/../inc/forum.php';
$act = param('act', 'list');
api_guard(false, $act !== 'list' && $act !== 'get');
$me = current_user();
$uid = (int)$me['id'];

if ($act === 'list') {
    list($limit, $offset) = page_args();
    $where = array('1=1');
    $args = array();
    if (($bid = param_int('board_id')) > 0) { $where[] = 't.board_id = ?'; $args[] = $bid; }
    if (($kw = param('keyword')) !== '') {
        // 未付費的貼子不按內容搜索，避免通過關鍵字試探付費內容
        $can = is_admin() ? '1=1' : '(t.pay_view = 0 OR t.user_id = ' . $uid . " OR EXISTS (SELECT 1 FROM pm_forum_pays fp WHERE fp.thread_id = t.id AND fp.user_id = $uid AND fp.kind = 'view'))";
        $where[] = "(t.title LIKE ? OR (t.content LIKE ? AND $can))";
        $args[] = like_kw($kw);
        $args[] = like_kw($kw);
    }
    $w = implode(' AND ', $where);
    $rows = db_rows("SELECT t.id, t.board_id, t.title, t.is_top, t.is_locked, t.views, t.replies, t.last_reply_at, t.last_reply_user, t.user_id, t.created_at, t.pay_view, t.pay_file,
                            b.name AS board_name, COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name
                     FROM pm_forum_threads t LEFT JOIN pm_forum_boards b ON b.id = t.board_id LEFT JOIN pm_users u ON u.id = t.user_id
                     WHERE $w ORDER BY t.is_top DESC, COALESCE(t.last_reply_at, t.created_at) DESC, t.id DESC LIMIT $limit OFFSET $offset", $args);
    $paid = array();
    if ($rows) {
        $ids = implode(',', array_map(function ($r) { return (int)$r['id']; }, $rows));
        foreach (db_rows("SELECT thread_id, kind FROM pm_forum_pays WHERE user_id = ? AND thread_id IN ($ids)", array($uid)) as $p) $paid[$p['thread_id'] . $p['kind']] = true;
    }
    foreach ($rows as &$r) {
        $r['can_write'] = is_admin() || (int)$r['user_id'] === $uid;
        // 是否仍需支付（發貼人、管理員、已支付者為 0）
        $r['need_view'] = !$r['can_write'] && (int)$r['pay_view'] > 0 && !isset($paid[$r['id'] . 'view']) ? 1 : 0;
        $r['need_file'] = !$r['can_write'] && (int)$r['pay_file'] > 0 && !isset($paid[$r['id'] . 'file']) ? 1 : 0;
    }
    unset($r);
    json_out(array('total' => (int)db_val("SELECT COUNT(*) FROM pm_forum_threads t WHERE $w", $args), 'rows' => $rows));
}

if ($act === 'get') {
    $id = param_int('id');
    $t = db_row('SELECT id, board_id, title, content, user_id, pay_view, pay_file FROM pm_forum_threads WHERE id = ?', array($id));
    if (!$t) fail('主題不存在');
    if (!is_admin() && (int)$t['user_id'] !== $uid) fail('只能修改自己發表的主題', 403);
    $t['can_price'] = forum_can_price($t['user_id']);
    $fm = files_map('forum', array($id));
    json_out(array('ok' => true, 'thread' => $t, 'files' => isset($fm[$id]['att']) ? $fm[$id]['att'] : array()));
}

if ($act === 'save') {
    $id = param_int('id');
    $title = param('title');
    $content = param('content');
    $bid = param_int('board_id');
    if ($title === '' || mb_strlen($title) > 200) fail('請輸入主題標題（200 字以內）');
    if ($content === '' || mb_strlen($content) > 50000) fail('請輸入內容（50000 字以內）');
    if (!db_val('SELECT id FROM pm_forum_boards WHERE id = ?', array($bid))) fail('請選擇版塊');
    $uploads = files_collect('f_att');
    $data = array('board_id' => $bid, 'title' => $title, 'content' => $content, 'updated_at' => now());
    if ($id) {
        $old = db_row('SELECT user_id FROM pm_forum_threads WHERE id = ?', array($id));
        if (!$old) fail('主題不存在');
        if (!is_admin() && (int)$old['user_id'] !== $uid) fail('只能修改自己發表的主題', 403);
    }
    // 付費設置：只有普通用戶發表的貼子可以收費
    $priced = forum_can_price($id ? $old['user_id'] : $uid);
    foreach (array('pay_view' => '查看貼子', 'pay_file' => '下載附件') as $k => $label) {
        if (!isset($_POST[$k])) continue;
        $v = trim((string)$_POST[$k]);
        if ($v === '') $v = '0';
        if (!preg_match('/^\d{1,5}$/', $v) || (int)$v > FORUM_PAY_MAX) fail($label . '所需積分請輸入 0-' . FORUM_PAY_MAX . ' 之間的整數');
        $data[$k] = $priced ? (int)$v : 0;
    }
    db_tx(function () use (&$id, $data, $uploads, $uid) {
        if ($id) {
            db_update('pm_forum_threads', $data, $id);
            if ($del = files_del_param()) files_delete('forum', $id, $del);
        } else {
            $id = db_insert('pm_forum_threads', $data + array('user_id' => $uid, 'created_at' => now(), 'last_reply_at' => now()));
        }
        files_store($uploads, 'forum', $id, 'att', $uid);
    });
    ok('發表成功', array('id' => $id));
}

$thread = db_row('SELECT * FROM pm_forum_threads WHERE id = ?', array(param_int('id')));
if (!$thread) fail('主題不存在');

if ($act === 'pay') {
    $kind = param('kind') === 'file' ? 'file' : 'view';
    $acc = forum_access($thread, $me);
    if ($kind === 'file' && !$acc['view']) fail('請先支付積分查看貼子');
    if ($acc[$kind]) ok('無需支付', array('credits' => (int)$me['credits']));
    $left = db_tx(function () use ($thread, $kind, $me) { return forum_pay($thread, $kind, $me); });
    ok('支付成功', array('credits' => $left));
}

if ($act === 'reply') {
    if ((int)$thread['is_locked']) fail('該主題已鎖定，不能回覆');
    $acc = forum_access($thread, $me);
    if (!$acc['view']) fail('請先支付積分查看貼子', 403);
    $content = param('content');
    if ($content === '' || mb_strlen($content) > 20000) fail('請輸入回覆內容（20000 字以內）');
    db_tx(function () use ($thread, $content, $me, $uid) {
        db_insert('pm_forum_posts', array('thread_id' => $thread['id'], 'content' => $content, 'user_id' => $uid, 'created_at' => now()));
        db_q('UPDATE pm_forum_threads SET replies = (SELECT COUNT(*) FROM pm_forum_posts WHERE thread_id = ?), last_reply_at = ?, last_reply_user = ? WHERE id = ?',
            array($thread['id'], now(), user_label($me), $thread['id']));
    });
    ok('回覆成功');
}

if ($act === 'del_post') {
    $p = db_row('SELECT * FROM pm_forum_posts WHERE id = ? AND thread_id = ?', array(param_int('post_id'), $thread['id']));
    if (!$p) fail('回覆不存在');
    if (!is_admin() && (int)$p['user_id'] !== $uid) fail('只能刪除自己的回覆', 403);
    db_tx(function () use ($p, $thread) {
        db_q('DELETE FROM pm_forum_posts WHERE id = ?', array($p['id']));
        db_q('UPDATE pm_forum_threads SET replies = (SELECT COUNT(*) FROM pm_forum_posts WHERE thread_id = ?) WHERE id = ?', array($thread['id'], $thread['id']));
    });
    ok('刪除成功');
}

if ($act === 'delete') {
    if (!is_admin() && (int)$thread['user_id'] !== $uid) fail('只能刪除自己發表的主題', 403);
    db_tx(function () use ($thread) {
        files_delete('forum', $thread['id']);
        db_q('DELETE FROM pm_forum_posts WHERE thread_id = ?', array($thread['id']));
        db_q('DELETE FROM pm_forum_pays WHERE thread_id = ?', array($thread['id']));
        db_q('DELETE FROM pm_forum_threads WHERE id = ?', array($thread['id']));
    });
    ok('刪除成功');
}

if ($act === 'top' || $act === 'lock') {
    if (!is_admin()) fail('只有管理員可以置頂或鎖定主題', 403);
    $k = $act === 'top' ? 'is_top' : 'is_locked';
    db_update('pm_forum_threads', array($k => (int)$thread[$k] ? 0 : 1), $thread['id']);
    ok('操作成功');
}
fail('未知操作');
