<?php
/** 系統消息貼子（閱讀端）：列表、查看並標記已讀 */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/files.php';
$act = param('act', 'list');
api_guard(false, $act !== 'list');
$me = current_user();

if ($act === 'list') {
    list($limit, $offset) = page_args();
    $rows = db_rows("SELECT n.id, n.title, n.content, n.created_at, COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name,
                            (SELECT COUNT(*) FROM pm_notice_reads r WHERE r.notice_id = n.id AND r.user_id = ?) AS is_read
                     FROM pm_notices n LEFT JOIN pm_users u ON u.id = n.user_id ORDER BY n.id DESC LIMIT $limit OFFSET $offset", array($me['id']));
    $ids = array();
    foreach ($rows as $r) $ids[] = (int)$r['id'];
    $fmap = files_map('notice', $ids);
    foreach ($rows as &$r) $r['files_att'] = isset($fmap[(int)$r['id']]['att']) ? $fmap[(int)$r['id']]['att'] : array();
    unset($r);
    json_out(array('total' => (int)db_val('SELECT COUNT(*) FROM pm_notices'), 'rows' => $rows));
}

if ($act === 'read') {
    $id = param_int('id');
    if ($id) {
        if (db_val('SELECT id FROM pm_notices WHERE id = ?', array($id))) db_q('INSERT IGNORE INTO pm_notice_reads (notice_id, user_id, read_at) VALUES (?, ?, ?)', array($id, $me['id'], now()));
    } else {
        db_q('INSERT IGNORE INTO pm_notice_reads (notice_id, user_id, read_at) SELECT id, ?, ? FROM pm_notices', array($me['id'], now()));
    }
    ok('');
}
fail('未知操作');
