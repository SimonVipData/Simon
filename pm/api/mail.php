<?php
/** 內部郵件：收件箱 / 已發送 / 草稿箱 */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/files.php';
$act = param('act', 'list');
api_guard(false, $act !== 'list' && $act !== 'get');
$me = current_user();
$uid = (int)$me['id'];
menu_guard('office_mail');

if ($act === 'list') {
    list($limit, $offset) = page_args();
    $box = param('box', 'inbox');
    $kw = param('keyword');
    $args = array($uid);
    $k = '';
    if ($kw !== '') { $k = ' AND (m.subject LIKE ? OR m.content LIKE ?)'; $args[] = like_kw($kw); $args[] = like_kw($kw); }
    $name = "COALESCE(NULLIF(u.real_name, ''), u.username) AS from_name";
    $fc = "(SELECT COUNT(*) FROM pm_files f WHERE f.module = 'mail' AND f.ref_id = m.id) AS file_count";
    if ($box === 'inbox') {
        $from = "FROM pm_mail_to t JOIN pm_mails m ON m.id = t.mail_id AND m.status = 1 LEFT JOIN pm_users u ON u.id = m.user_id WHERE t.user_id = ? AND t.deleted = 0$k";
        $rows = db_rows("SELECT m.id, m.subject, m.to_names, m.sent_at, m.status, t.is_read, $name, $fc $from ORDER BY m.sent_at DESC, m.id DESC LIMIT $limit OFFSET $offset", $args);
    } else {
        $st = $box === 'draft' ? 0 : 1;
        $from = "FROM pm_mails m LEFT JOIN pm_users u ON u.id = m.user_id WHERE m.user_id = ? AND m.status = $st AND m.from_deleted = 0$k";
        $rows = db_rows("SELECT m.id, m.subject, m.to_names, COALESCE(m.sent_at, m.updated_at) AS sent_at, m.status, 1 AS is_read, $name, $fc $from ORDER BY m.id DESC LIMIT $limit OFFSET $offset", $args);
    }
    json_out(array('total' => (int)db_val("SELECT COUNT(*) $from", $args), 'rows' => $rows));
}

if ($act === 'get') {
    $id = param_int('id');
    $m = db_row("SELECT m.id, m.user_id, m.subject, m.content, m.status, m.sent_at, COALESCE(NULLIF(u.real_name, ''), u.username) AS from_name
                 FROM pm_mails m LEFT JOIN pm_users u ON u.id = m.user_id
                 WHERE m.id = ? AND (m.user_id = ? OR (m.status = 1 AND EXISTS (SELECT 1 FROM pm_mail_to t WHERE t.mail_id = m.id AND t.user_id = ?)))", array($id, $uid, $uid));
    if (!$m) fail('郵件不存在');
    $to = array();
    foreach (db_rows('SELECT user_id FROM pm_mail_to WHERE mail_id = ?', array($id)) as $r) $to[] = (int)$r['user_id'];
    $fm = files_map('mail', array($id));
    json_out(array('ok' => true, 'mail' => $m, 'to_ids' => (int)$m['user_id'] === $uid ? $to : array(), 'files' => isset($fm[$id]['att']) ? $fm[$id]['att'] : array()));
}

if ($act === 'send') {
    $id = param_int('id');
    $draft = param('draft') === '1';
    $subject = param('subject');
    $content = param('content');
    $toIds = array_values(array_unique(array_filter(array_map('intval', explode(',', param('to_ids'))))));
    if ($subject === '' || mb_strlen($subject) > 200) fail('請輸入郵件主題（200 字以內）');
    if (mb_strlen($content) > 50000) fail('郵件內容過長');
    $users = array();
    foreach (user_options() as $u) $users[(int)$u['id']] = user_label($u);
    $names = array();
    foreach ($toIds as $t) {
        if (!isset($users[$t])) fail('收件人不存在或已停用');
        $names[] = $users[$t];
    }
    if (!$draft && !$toIds) fail('請選擇收件人');
    $uploads = files_collect('f_att');
    db_tx(function () use (&$id, $draft, $subject, $content, $toIds, $names, $uploads, $uid) {
        $now = now();
        $data = array('subject' => $subject, 'content' => $content, 'to_names' => mb_substr(implode('、', $names), 0, 1000), 'status' => $draft ? 0 : 1,
            'sent_at' => $draft ? null : $now, 'updated_at' => $now);
        if ($id) {
            $old = db_row('SELECT * FROM pm_mails WHERE id = ? AND user_id = ? AND status = 0 FOR UPDATE', array($id, $uid));
            if (!$old) throw new PmError('草稿不存在');
            db_update('pm_mails', $data, $id);
            db_q('DELETE FROM pm_mail_to WHERE mail_id = ?', array($id));
            if ($del = files_del_param()) files_delete('mail', $id, $del);
        } else {
            $id = db_insert('pm_mails', $data + array('user_id' => $uid, 'created_at' => $now));
        }
        foreach ($toIds as $t) db_insert('pm_mail_to', array('mail_id' => $id, 'user_id' => $t));
        files_store($uploads, 'mail', $id, 'att', $uid);
    });
    ok($draft ? '已保存到草稿箱' : '郵件已發送');
}

if ($act === 'read') {
    db_q('UPDATE pm_mail_to SET is_read = 1, read_at = ? WHERE mail_id = ? AND user_id = ? AND is_read = 0', array(now(), param_int('id'), $uid));
    ok('');
}

if ($act === 'delete') {
    $id = param_int('id');
    if (param('box') === 'inbox') {
        db_q('UPDATE pm_mail_to SET deleted = 1 WHERE mail_id = ? AND user_id = ?', array($id, $uid));
    } else {
        $m = db_row('SELECT id, status FROM pm_mails WHERE id = ? AND user_id = ?', array($id, $uid));
        if (!$m) fail('郵件不存在');
        if ((int)$m['status'] === 0) {
            db_q('DELETE FROM pm_mail_to WHERE mail_id = ?', array($id));
            files_delete('mail', $id);
            db_q('DELETE FROM pm_mails WHERE id = ?', array($id));
        } else {
            db_q('UPDATE pm_mails SET from_deleted = 1 WHERE id = ?', array($id));
        }
    }
    ok('刪除成功');
}
fail('未知操作');
