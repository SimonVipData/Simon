<?php
/** 內部審批：詢價 / 費用 / 事項；申請人指定審批人及先後順序，逐級審批 */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/files.php';
$act = param('act', 'list');
api_guard(false, $act !== 'list' && $act !== 'get');
$me = current_user();
$uid = (int)$me['id'];
$types = array('inquiry' => 'XJ', 'expense' => 'FY', 'matter' => 'SX');
$apprType = param('type');
if (!isset($types[$apprType]) && param_int('id')) $apprType = (string)db_val('SELECT type FROM pm_apprs WHERE id = ?', array(param_int('id')));
if (isset($types[$apprType])) menu_guard('appr_' . $apprType);

function appr_log($id, $me, $action, $comment = '') {
    db_insert('pm_appr_logs', array('appr_id' => $id, 'user_id' => $me['id'], 'user_name' => user_label($me), 'action' => $action,
        'comment' => mb_substr($comment, 0, 500), 'created_at' => now()));
}

if ($act === 'list') {
    list($limit, $offset) = page_args();
    $type = param('type');
    if (!isset($types[$type])) fail('審批類別不正確');
    $where = array('a.type = ?');
    $args = array($type);
    $scope = param('scope', 'mine');
    if ($scope === 'todo') {
        $where[] = 'a.status = ' . DOC_PENDING . ' AND EXISTS (SELECT 1 FROM pm_appr_steps s WHERE s.appr_id = a.id AND s.user_id = ? AND s.status = ' . STEP_CURRENT . ')';
        $args[] = $uid;
    } elseif ($scope === 'done') {
        $where[] = 'EXISTS (SELECT 1 FROM pm_appr_logs l WHERE l.appr_id = a.id AND l.user_id = ? AND l.action IN (\'approve\', \'reject\'))';
        $args[] = $uid;
    } elseif ($scope === 'all' && is_admin()) {
        $where[] = 'a.status <> ' . DOC_DRAFT;
    } else {
        $where[] = 'a.user_id = ?';
        $args[] = $uid;
    }
    if (($kw = param('keyword')) !== '') { $where[] = '(a.title LIKE ? OR a.appr_no LIKE ? OR a.party LIKE ?)'; array_push($args, like_kw($kw), like_kw($kw), like_kw($kw)); }
    if (($st = param('status')) !== '' && in_array((int)$st, array(DOC_DRAFT, DOC_PENDING, DOC_APPROVED, DOC_REJECTED), true)) { $where[] = 'a.status = ?'; $args[] = (int)$st; }
    $w = implode(' AND ', $where);
    $rows = db_rows("SELECT a.*, p.name AS project_name, COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name,
                            (SELECT s.user_name FROM pm_appr_steps s WHERE s.appr_id = a.id AND s.status = " . STEP_CURRENT . " LIMIT 1) AS cur_name,
                            (SELECT GROUP_CONCAT(s.user_name ORDER BY s.step_no SEPARATOR ' → ') FROM pm_appr_steps s WHERE s.appr_id = a.id) AS flow_text
                     FROM pm_apprs a LEFT JOIN pm_projects p ON p.id = a.project_id LEFT JOIN pm_users u ON u.id = a.user_id
                     WHERE $w ORDER BY a.id DESC LIMIT $limit OFFSET $offset", $args);
    foreach ($rows as &$r) $r['status_text'] = doc_status_text($r['status']);
    unset($r);
    json_out(array('total' => (int)db_val("SELECT COUNT(*) FROM pm_apprs a WHERE $w", $args), 'rows' => $rows));
}

if ($act === 'get') {
    $id = param_int('id');
    $a = db_row('SELECT * FROM pm_apprs WHERE id = ? AND user_id = ?', array($id, $uid));
    if (!$a) fail('審批單不存在');
    $ids = array();
    foreach (db_rows('SELECT user_id FROM pm_appr_steps WHERE appr_id = ? ORDER BY step_no', array($id)) as $s) $ids[] = (int)$s['user_id'];
    $fm = files_map('appr', array($id));
    json_out(array('ok' => true, 'appr' => $a, 'approver_ids' => $ids, 'files' => isset($fm[$id]['att']) ? $fm[$id]['att'] : array()));
}

/** 提交：重置各級審批狀態，第一級進入審批中 */
function appr_submit($id, $me) {
    $steps = db_rows('SELECT id FROM pm_appr_steps WHERE appr_id = ? ORDER BY step_no', array($id));
    if (!$steps) throw new PmError('請先設置審批人');
    db_q("UPDATE pm_appr_steps SET status = ?, comment = '', acted_at = NULL WHERE appr_id = ?", array(STEP_WAIT, $id));
    db_q('UPDATE pm_appr_steps SET status = ? WHERE id = ?', array(STEP_CURRENT, $steps[0]['id']));
    db_update('pm_apprs', array('status' => DOC_PENDING, 'current_step' => 1, 'submitted_at' => now(), 'finished_at' => null, 'updated_at' => now()), $id);
    appr_log($id, $me, 'submit');
}

if ($act === 'save') {
    $id = param_int('id');
    $type = param('type');
    if (!isset($types[$type])) fail('審批類別不正確');
    $title = param('title');
    if ($title === '' || mb_strlen($title) > 200) fail('請輸入標題（200 字以內）');
    $amount = param('amount');
    if ($type !== 'matter') {
        if ($amount === '' || !is_numeric($amount) || (float)$amount < 0 || (float)$amount > 999999999999) fail('請輸入正確的金額');
    } else {
        $amount = null;
    }
    if (mb_strlen(param('content')) > 20000) fail('內容過長');
    $pid = param_int('project_id');
    if ($pid && !db_val('SELECT id FROM pm_projects WHERE id = ?', array($pid))) fail('工程不存在');
    $approvers = array_values(array_unique(array_filter(array_map('intval', explode(',', param('approver_ids'))))));
    if (!$approvers) fail('請選擇審批人');
    if (count($approvers) > 10) fail('審批人最多 10 位');
    $users = array();
    foreach (user_options($uid) as $u) $users[(int)$u['id']] = user_label($u);
    foreach ($approvers as $a) if (!isset($users[$a])) fail('審批人不存在、已停用或為申請人本人');
    $uploads = files_collect('f_att');
    $submit = param('submit') === '1';
    $data = array('title' => $title, 'project_id' => $pid, 'category' => mb_substr(param('category'), 0, 50), 'party' => mb_substr(param('party'), 0, 255),
        'amount' => $amount, 'content' => param('content'), 'updated_at' => now());
    db_tx(function () use (&$id, $type, $types, $data, $approvers, $users, $uploads, $submit, $me, $uid) {
        if ($id) {
            $old = db_row('SELECT * FROM pm_apprs WHERE id = ? AND user_id = ? FOR UPDATE', array($id, $uid));
            if (!$old) throw new PmError('審批單不存在');
            if (!in_array((int)$old['status'], array(DOC_DRAFT, DOC_REJECTED), true)) throw new PmError('審批中或已通過的審批單不能修改');
            db_update('pm_apprs', $data, $id);
            if ($del = files_del_param()) files_delete('appr', $id, $del);
        } else {
            // 編號：前綴 + 年月日 + 當日序號（唯一索引衝突時重試）
            $prefix = $types[$type] . date('Ymd');
            $n = (int)db_val('SELECT COUNT(*) FROM pm_apprs WHERE appr_no LIKE ?', array($prefix . '%'));
            do {
                $no = $prefix . sprintf('%03d', ++$n);
            } while (db_val('SELECT id FROM pm_apprs WHERE appr_no = ?', array($no)));
            $id = db_insert('pm_apprs', $data + array('type' => $type, 'appr_no' => $no, 'status' => DOC_DRAFT, 'user_id' => $uid, 'created_at' => now()));
        }
        db_q('DELETE FROM pm_appr_steps WHERE appr_id = ?', array($id));
        foreach ($approvers as $i => $a) db_insert('pm_appr_steps', array('appr_id' => $id, 'step_no' => $i + 1, 'user_id' => $a, 'user_name' => $users[$a], 'status' => STEP_WAIT));
        files_store($uploads, 'appr', $id, 'att', $uid);
        if ($submit) appr_submit($id, $me);
    });
    ok($submit ? '已提交審批' : '已保存為草稿', array('id' => $id));
}

$id = param_int('id');

if ($act === 'submit') {
    db_tx(function () use ($id, $me, $uid) {
        $a = db_row('SELECT * FROM pm_apprs WHERE id = ? AND user_id = ? FOR UPDATE', array($id, $uid));
        if (!$a) throw new PmError('審批單不存在');
        if (!in_array((int)$a['status'], array(DOC_DRAFT, DOC_REJECTED), true)) throw new PmError('該審批單已提交');
        appr_submit($id, $me);
    });
    ok('已提交審批');
}

if ($act === 'approve' || $act === 'reject') {
    $comment = param('comment');
    if ($act === 'reject' && $comment === '') fail('退回時請填寫意見');
    if (mb_strlen($comment) > 500) fail('意見不能超過 500 字');
    $msg = db_tx(function () use ($id, $act, $comment, $me, $uid) {
        $a = db_row('SELECT * FROM pm_apprs WHERE id = ? FOR UPDATE', array($id));
        if (!$a || (int)$a['status'] !== DOC_PENDING) throw new PmError('該審批單不在審批中');
        $cur = db_row('SELECT * FROM pm_appr_steps WHERE appr_id = ? AND status = ? ORDER BY step_no LIMIT 1', array($id, STEP_CURRENT));
        if (!$cur || (int)$cur['user_id'] !== $uid) throw new PmError('尚未輪到您審批');
        db_update('pm_appr_steps', array('status' => $act === 'approve' ? STEP_PASS : STEP_REJECT, 'comment' => $comment, 'acted_at' => now()), $cur['id']);
        appr_log($id, $me, $act, $comment);
        if ($act === 'reject') {
            db_update('pm_apprs', array('status' => DOC_REJECTED, 'current_step' => 0, 'finished_at' => now(), 'updated_at' => now()), $id);
            return '已退回申請人';
        }
        $next = db_row('SELECT * FROM pm_appr_steps WHERE appr_id = ? AND step_no > ? ORDER BY step_no LIMIT 1', array($id, $cur['step_no']));
        if ($next) {
            db_update('pm_appr_steps', array('status' => STEP_CURRENT), $next['id']);
            db_update('pm_apprs', array('current_step' => $next['step_no'], 'updated_at' => now()), $id);
            return '審批通過，已轉交下一位審批人：' . $next['user_name'];
        }
        db_update('pm_apprs', array('status' => DOC_APPROVED, 'current_step' => 0, 'finished_at' => now(), 'updated_at' => now()), $id);
        return '審批通過，流程已完成';
    });
    ok($msg);
}

if ($act === 'delete') {
    $a = db_row('SELECT * FROM pm_apprs WHERE id = ?', array($id));
    if (!$a) fail('審批單不存在');
    if (!is_admin()) {
        if ((int)$a['user_id'] !== $uid) fail('只能刪除自己的審批單', 403);
        if (!in_array((int)$a['status'], array(DOC_DRAFT, DOC_REJECTED), true)) fail('審批中或已通過的審批單不能刪除');
    }
    db_tx(function () use ($id) {
        files_delete('appr', $id);
        db_q('DELETE FROM pm_appr_steps WHERE appr_id = ?', array($id));
        db_q('DELETE FROM pm_appr_logs WHERE appr_id = ?', array($id));
        db_q('DELETE FROM pm_apprs WHERE id = ?', array($id));
    });
    ok('刪除成功');
}
fail('未知操作');
