<?php
/** 通用附件下載（按所屬模塊校驗權限） */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/modules.php';
require_once __DIR__ . '/../inc/files.php';
api_guard(false, false);
$me = current_user();
$f = db_row('SELECT * FROM pm_files WHERE id = ?', array(param_int('id')));
$allow = false;
if ($f) {
    $m = $f['module'];
    $rid = (int)$f['ref_id'];
    $mod = pm_module($m);
    if ($mod) {
        $allow = $mod['scope'] !== 'own' || (int)db_val("SELECT user_id FROM `{$mod['table']}` WHERE id = ?", array($rid)) === (int)$me['id'];
    } elseif ($m === 'mail') {
        $allow = (bool)db_val('SELECT 1 FROM pm_mails m WHERE m.id = ? AND (m.user_id = ? OR EXISTS (SELECT 1 FROM pm_mail_to t WHERE t.mail_id = m.id AND t.user_id = ? AND m.status = 1))',
            array($rid, $me['id'], $me['id']));
    } elseif ($m === 'appr') {
        $allow = is_admin() || db_val('SELECT 1 FROM pm_apprs a WHERE a.id = ? AND (a.user_id = ? OR EXISTS (SELECT 1 FROM pm_appr_steps s WHERE s.appr_id = a.id AND s.user_id = ?))',
            array($rid, $me['id'], $me['id']));
    } elseif ($m === 'disk') {
        $d = db_row('SELECT space, user_id FROM pm_disk WHERE id = ?', array($rid));
        $allow = $d && ($d['space'] !== 'personal' || (int)$d['user_id'] === (int)$me['id']);
    } elseif ($m === 'forum') {
        // 付費貼：需已支付（或為發貼人、管理員）才能下載附件
        require_once __DIR__ . '/../inc/forum.php';
        $t = db_row('SELECT id, user_id, pay_view, pay_file FROM pm_forum_threads WHERE id = ?', array($rid));
        if ($t) {
            $acc = forum_access($t, $me);
            $allow = $acc['file'];
        }
    } elseif ($m === 'event') {
        require_once __DIR__ . '/../inc/panel.php';
        $allow = !cal_own_only() || (int)db_val('SELECT user_id FROM pm_events WHERE id = ?', array($rid)) === (int)$me['id'];
    }
}
$path = $f ? UPLOAD_DIR . '/' . $f['file_path'] : '';
if (!$allow || !preg_match('/^[0-9a-f_]+\.bin$/', $f['file_path']) || !is_file($path)) {
    http_response_code(404);
    exit('附件不存在');
}
$img = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif');
$ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
$inline = param('inline') === '1' && isset($img[$ext]);
header('Content-Type: ' . ($inline ? $img[$ext] : 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"download\"; filename*=UTF-8''" . rawurlencode($f['file_name']));
header('Cache-Control: private, max-age=3600');
readfile($path);
