<?php
/** 投票：發起、參與、結束、刪除 */
require_once __DIR__ . '/../inc/config.php';
$act = param('act', 'list');
api_guard(false, $act !== 'list');
$me = current_user();
$uid = (int)$me['id'];

/** 是否仍可投票（未結束且未過截止日期） */
function vote_open($v) {
    return (int)$v['status'] === 1 && ($v['end_date'] === null || $v['end_date'] >= date('Y-m-d'));
}

if ($act === 'list') {
    list($limit, $offset) = page_args();
    $rows = db_rows("SELECT v.*, COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name,
                            (SELECT COUNT(DISTINCT r.user_id) FROM pm_vote_records r WHERE r.vote_id = v.id) AS voters,
                            (SELECT COUNT(*) FROM pm_vote_records r WHERE r.vote_id = v.id AND r.user_id = ?) AS voted
                     FROM pm_votes v LEFT JOIN pm_users u ON u.id = v.user_id ORDER BY v.id DESC LIMIT $limit OFFSET $offset", array($uid));
    foreach ($rows as &$r) {
        $r['open'] = vote_open($r);
        $r['can_write'] = is_admin() || (int)$r['user_id'] === $uid;
    }
    unset($r);
    json_out(array('total' => (int)db_val('SELECT COUNT(*) FROM pm_votes'), 'rows' => $rows));
}

if ($act === 'save') {
    $title = param('title');
    if ($title === '' || mb_strlen($title) > 200) fail('請輸入投票主題（200 字以內）');
    $opts = array();
    foreach (preg_split('/\r\n|\r|\n/', isset($_POST['options']) && is_string($_POST['options']) ? $_POST['options'] : '') as $o) {
        $o = trim($o);
        if ($o !== '' && !in_array($o, $opts, true)) $opts[] = mb_substr($o, 0, 200);
    }
    if (count($opts) < 2 || count($opts) > 30) fail('請填寫 2-30 個選項（每行一個）');
    $end = param('end_date');
    if ($end !== '' && (date_or_null($end) === null || $end < date('Y-m-d'))) fail('截止日期不正確');
    db_tx(function () use ($title, $opts, $end, $uid) {
        $vid = db_insert('pm_votes', array('title' => $title, 'content' => mb_substr(param('content'), 0, 5000), 'multi' => param('multi') === '1' ? 1 : 0,
            'anonymous' => param('anonymous') === '1' ? 1 : 0, 'end_date' => $end === '' ? null : $end, 'user_id' => $uid, 'created_at' => now(), 'updated_at' => now()));
        foreach ($opts as $i => $o) db_insert('pm_vote_options', array('vote_id' => $vid, 'title' => $o, 'sort' => $i + 1));
    });
    ok('投票已發起');
}

$vote = db_row('SELECT * FROM pm_votes WHERE id = ?', array(param_int('id')));
if (!$vote) fail('投票不存在');

if ($act === 'cast') {
    if (!vote_open($vote)) fail('該投票已結束');
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', param('option_ids'))))));
    if (!$ids) fail('請選擇選項');
    if (!(int)$vote['multi'] && count($ids) > 1) fail('該投票為單選');
    $valid = array();
    foreach (db_rows('SELECT id FROM pm_vote_options WHERE vote_id = ?', array($vote['id'])) as $o) $valid[] = (int)$o['id'];
    if (array_diff($ids, $valid)) fail('選項不正確');
    db_tx(function () use ($vote, $ids, $uid) {
        db_row('SELECT id FROM pm_votes WHERE id = ? FOR UPDATE', array($vote['id']));
        if (db_val('SELECT id FROM pm_vote_records WHERE vote_id = ? AND user_id = ? LIMIT 1', array($vote['id'], $uid))) throw new PmError('您已投過票');
        foreach ($ids as $oid) db_insert('pm_vote_records', array('vote_id' => $vote['id'], 'option_id' => $oid, 'user_id' => $uid, 'created_at' => now()));
    });
    ok('投票成功');
}

if (!is_admin() && (int)$vote['user_id'] !== $uid) fail('只有發起人或管理員可以操作', 403);

if ($act === 'close') {
    db_update('pm_votes', array('status' => 0, 'updated_at' => now()), $vote['id']);
    ok('投票已結束');
}

if ($act === 'delete') {
    db_tx(function () use ($vote) {
        db_q('DELETE FROM pm_vote_records WHERE vote_id = ?', array($vote['id']));
        db_q('DELETE FROM pm_vote_options WHERE vote_id = ?', array($vote['id']));
        db_q('DELETE FROM pm_votes WHERE id = ?', array($vote['id']));
    });
    ok('刪除成功');
}
fail('未知操作');
