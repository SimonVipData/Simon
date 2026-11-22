<?php
/** 內部審批詳情與審批 */
require_once __DIR__ . '/../inc/files.php';
$a = db_row("SELECT a.*, p.name AS project_name, p.code AS project_code, COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name
             FROM pm_apprs a LEFT JOIN pm_projects p ON p.id = a.project_id LEFT JOIN pm_users u ON u.id = a.user_id WHERE a.id = ?", array(param_int('id')));
$steps = $a ? db_rows('SELECT * FROM pm_appr_steps WHERE appr_id = ? ORDER BY step_no', array($a['id'])) : array();
$involved = $a && ($admin || (int)$a['user_id'] === (int)$user['id']);
$cur = null;
foreach ($steps as $s) {
    if ((int)$s['user_id'] === (int)$user['id']) $involved = true;
    if ((int)$s['status'] === STEP_CURRENT) $cur = $s;
}
if (!$a || !$involved) { echo '<div style="padding:20px;">審批單不存在或無權查看</div>'; return; }
$labels = array(
    'inquiry' => array('詢價審批', '供應商 / 分包商', '報價金額(MOP)', '詢價類別'),
    'expense' => array('費用審批', '收款單位', '申請金額(MOP)', '費用類別'),
    'matter' => array('事項審批', '', '', '事項類別'),
);
$c = $labels[$a['type']];
$pending = (int)$a['status'] === DOC_PENDING;
$canApprove = $pending && $cur && (int)$cur['user_id'] === (int)$user['id'];
$fm = files_map('appr', array((int)$a['id']));
$files = isset($fm[(int)$a['id']]['att']) ? $fm[(int)$a['id']]['att'] : array();
$logs = db_rows('SELECT * FROM pm_appr_logs WHERE appr_id = ? ORDER BY id', array($a['id']));
$actText = array('submit' => '提交', 'approve' => '通過', 'reject' => '退回');
$statusColor = array(DOC_DRAFT => '#888', DOC_PENDING => '#e68a00', DOC_APPROVED => '#2e8b57', DOC_REJECTED => '#d93025');
?>
<div class="pm-box" id="<?php echo $uid; ?>_box">
    <h4><?php echo $c[0]; ?>信息</h4>
    <table class="pm-detail">
        <tr><th>審批編號</th><td><?php echo h($a['appr_no']); ?></td><th>狀態</th>
            <td><b style="color:<?php echo $statusColor[(int)$a['status']]; ?>"><?php echo doc_status_text($a['status']); ?></b><?php if ($pending && $cur): ?>（當前審批人：<?php echo h($cur['user_name']); ?>）<?php endif; ?></td></tr>
        <tr><th>標題</th><td colspan="3"><?php echo h($a['title']); ?></td></tr>
        <tr><th>所屬工程</th><td><?php echo h(trim($a['project_code'] . ' ' . $a['project_name'])); ?></td><th><?php echo $c[3]; ?></th><td><?php echo h($a['category']); ?></td></tr>
        <?php if ($a['type'] !== 'matter'): ?>
        <tr><th><?php echo $c[1]; ?></th><td><?php echo h($a['party']); ?></td><th><?php echo $c[2]; ?></th><td><b><?php echo $a['amount'] === null ? '' : number_format((float)$a['amount'], 2); ?></b></td></tr>
        <?php endif; ?>
        <tr><th>申請人</th><td><?php echo h($a['creator_name']); ?></td><th>提交時間</th><td><?php echo h($a['submitted_at']); ?></td></tr>
        <tr><th><?php echo $a['type'] === 'matter' ? '事項內容' : '說明'; ?></th><td colspan="3"><?php echo nl2br(h($a['content'])); ?></td></tr>
        <tr><th>附件</th><td colspan="3"><?php foreach ($files as $f): ?><a href="api/file.php?id=<?php echo $f['id']; ?>"><?php echo h($f['name']); ?></a>（<?php echo number_format($f['size'] / 1024, 1); ?> KB）<br/><?php endforeach; ?><?php if (!$files) echo '無'; ?></td></tr>
    </table>
    <h4>審批順序</h4>
    <div class="pm-flowline">
        <span class="s4">申請人：<?php echo h($a['creator_name']); ?></span>
        <?php foreach ($steps as $s): ?><i>→</i><span class="s<?php echo (int)$s['status']; ?>"><?php echo (int)$s['step_no']; ?>. <?php echo h($s['user_name']); ?></span><?php endforeach; ?>
    </div>
    <?php if ($canApprove): ?>
    <h4>審批意見</h4>
    <div style="margin-bottom:12px;">
        <textarea id="<?php echo $uid; ?>_comment" rows="3" style="width:100%;box-sizing:border-box;" maxlength="500" placeholder="請填寫審批意見（退回時必填）"></textarea>
        <a class="easyui-linkbutton" icon="icon-ok" id="<?php echo $uid; ?>_pass">通過</a>
        <a class="easyui-linkbutton" icon="icon-undo" id="<?php echo $uid; ?>_reject">退回</a>
    </div>
    <?php endif; ?>
    <h4>審批記錄</h4>
    <table class="pm-detail">
        <thead><tr><th>時間</th><th>處理人</th><th>操作</th><th>意見</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
        <tr><td style="width:150px;text-align:center;"><?php echo h($l['created_at']); ?></td><td style="width:180px;text-align:center;"><?php echo h($l['user_name']); ?></td>
            <td style="width:70px;text-align:center;"><?php echo h(isset($actText[$l['action']]) ? $actText[$l['action']] : $l['action']); ?></td><td><?php echo nl2br(h($l['comment'])); ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="4" style="text-align:center;color:#999;">尚未提交</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>';
    function act(name) {
        var comment = $.trim($(U + '_comment').val());
        if (name === 'reject' && !comment) return PM.alert('退回時請填寫意見');
        PM.confirm(name === 'approve' ? '確定通過該審批嗎？' : '確定退回該審批嗎？', function(){
            PM.post('api/appr.php?act=' + name, {id: <?php echo (int)$a['id']; ?>, comment: comment}, function(){
                $(U + '_box').closest('.panel-body').panel('refresh');
                $('table.pm-appr-grid').each(function(){ if ($.data(this, 'datagrid')) $(this).datagrid('reload'); });
                PM.ping();
            });
        });
    }
    $(U + '_pass').click(function(){ act('approve'); });
    $(U + '_reject').click(function(){ act('reject'); });
});
</script>
