<?php
/** 郵件詳情（收件人打開即標記已讀） */
require_once __DIR__ . '/../inc/files.php';
$id = param_int('id');
$m = db_row("SELECT m.*, COALESCE(NULLIF(u.real_name, ''), u.username) AS from_name FROM pm_mails m LEFT JOIN pm_users u ON u.id = m.user_id
             WHERE m.id = ? AND (m.user_id = ? OR (m.status = 1 AND EXISTS (SELECT 1 FROM pm_mail_to t WHERE t.mail_id = m.id AND t.user_id = ?)))", array($id, $user['id'], $user['id']));
if (!$m) { echo '<div style="padding:20px;">郵件不存在</div>'; return; }
db_q('UPDATE pm_mail_to SET is_read = 1, read_at = ? WHERE mail_id = ? AND user_id = ? AND is_read = 0', array(now(), $id, $user['id']));
$fm = files_map('mail', array($id));
$files = isset($fm[$id]['att']) ? $fm[$id]['att'] : array();
?>
<div class="pm-box">
    <table class="pm-detail">
        <tr><th>主題</th><td><?php echo h($m['subject']); ?></td></tr>
        <tr><th>發件人</th><td><?php echo h($m['from_name']); ?></td></tr>
        <tr><th>收件人</th><td><?php echo h($m['to_names']); ?></td></tr>
        <tr><th>時間</th><td><?php echo h($m['sent_at'] ? $m['sent_at'] : $m['updated_at']); ?><?php if (!(int)$m['status']): ?>（草稿）<?php endif; ?></td></tr>
        <tr><th>內容</th><td><?php echo nl2br(h($m['content'])); ?></td></tr>
        <tr><th>附件</th><td><?php foreach ($files as $f): ?><a href="api/file.php?id=<?php echo $f['id']; ?>"><?php echo h($f['name']); ?></a>（<?php echo number_format($f['size'] / 1024, 1); ?> KB）<br/><?php endforeach; ?><?php if (!$files) echo '無'; ?></td></tr>
    </table>
</div>
<script type="text/javascript">PM.ping();</script>
