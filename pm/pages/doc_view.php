<?php
/** 文件詳情與審批 */
$doc = db_row("SELECT d.*, p.name AS project_name, p.code AS project_code, f.name AS flow_name,
                      COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name
               FROM pm_docs d
               LEFT JOIN pm_projects p ON p.id = d.project_id
               LEFT JOIN pm_flows f ON f.id = d.flow_id
               LEFT JOIN pm_users u ON u.id = d.creator_id
               WHERE d.id = ?", array(param_int('id')));
if (!$doc) {
    echo '<div style="padding:20px;">文件不存在</div>';
    return;
}
$steps = db_rows('SELECT * FROM pm_doc_steps WHERE doc_id = ? ORDER BY round, step_no', array($doc['id']));
$current = array();
$cur = null;
foreach ($steps as $s) {
    if ((int)$s['round'] !== (int)$doc['round']) continue;
    $current[] = $s;
    if ((int)$s['status'] === STEP_CURRENT) $cur = $s;
}
if (!$current) {
    // 草稿：按審批設置預覽順序
    foreach (db_rows('SELECT s.step_no, r.name AS role_name FROM pm_flow_steps s LEFT JOIN pm_roles r ON r.id = s.role_id WHERE s.flow_id = ? ORDER BY s.step_no', array($doc['flow_id'])) as $s) {
        $current[] = $s + array('status' => STEP_WAIT);
    }
}
$pending = (int)$doc['status'] === DOC_PENDING;
$canApprove = $pending && $cur && (int)$cur['role_id'] === (int)$user['role_id'];
$canSubmit = (int)$doc['creator_id'] === (int)$user['id'] && in_array((int)$doc['status'], array(DOC_DRAFT, DOC_REJECTED), true);
$stepText = array(STEP_WAIT => '待審批', STEP_CURRENT => '審批中', STEP_PASS => '通過', STEP_REJECT => '退回', STEP_SUBMIT => '提交');
$statusColor = array(DOC_DRAFT => '#888', DOC_PENDING => '#e68a00', DOC_APPROVED => '#2e8b57', DOC_REJECTED => '#d93025');
?>
<div class="pm-box" id="<?php echo $uid; ?>_box">
    <h4>文件信息</h4>
    <table class="pm-detail">
        <tr><th>文件編號</th><td><?php echo h($doc['doc_no']); ?></td><th>狀態</th>
            <td><b style="color:<?php echo $statusColor[(int)$doc['status']]; ?>"><?php echo doc_status_text($doc['status']); ?></b><?php if ($pending && $cur): ?>（當前審批角色：<?php echo h($cur['role_name']); ?>）<?php endif; ?></td></tr>
        <tr><th>文件標題</th><td colspan="3"><?php echo h($doc['title']); ?></td></tr>
        <tr><th>所屬工程</th><td><?php echo h($doc['project_code'] . ' ' . $doc['project_name']); ?></td><th>文件名稱/類別</th><td><?php echo h($doc['flow_name'] . ($doc['doc_type'] !== '' ? '（' . $doc['doc_type'] . '）' : '')); ?></td></tr>
        <tr><th>提交人</th><td><?php echo h($doc['creator_name']); ?></td><th>提交時間</th><td><?php echo h($doc['submitted_at']); ?></td></tr>
        <tr><th>文件說明</th><td colspan="3"><?php echo nl2br(h($doc['content'])); ?></td></tr>
        <tr><th>附件</th><td colspan="3"><?php if ($doc['file_name'] !== ''): ?><a href="api/doc.php?act=download&id=<?php echo (int)$doc['id']; ?>"><?php echo h($doc['file_name']); ?></a>（<?php echo number_format($doc['file_size'] / 1024, 1); ?> KB）<?php else: ?>無<?php endif; ?></td></tr>
    </table>

    <h4>審批先後順序<?php if ((int)$doc['round'] > 1): ?>（第 <?php echo (int)$doc['round']; ?> 輪）<?php endif; ?></h4>
    <div class="pm-flowline">
        <?php foreach ($current as $i => $s): ?><?php if ($i > 0): ?><i>→</i><?php endif; ?><span class="s<?php echo (int)$s['status']; ?>"><?php echo ($i + 1) . '. ' . h($s['role_name']); ?></span><?php endforeach; ?>
    </div>

    <?php if ($steps): ?>
    <h4>審批記錄</h4>
    <table class="pm-detail">
        <thead><tr><th>輪次</th><th>步驟</th><th>角色</th><th>處理人</th><th>結果</th><th>意見</th><th>處理時間</th></tr></thead>
        <tbody>
        <?php foreach ($steps as $s): ?>
        <tr>
            <td style="text-align:center;"><?php echo (int)$s['round']; ?></td>
            <td style="text-align:center;"><?php echo (int)$s['step_no']; ?></td>
            <td style="text-align:center;"><?php echo h($s['role_name']); ?></td>
            <td style="text-align:center;"><?php echo h($s['user_name']); ?></td>
            <td style="text-align:center;"><?php echo (int)$s['round'] !== (int)$doc['round'] && (int)$s['status'] <= STEP_CURRENT ? '—' : $stepText[(int)$s['status']]; ?></td>
            <td><?php echo h($s['comment']); ?></td>
            <td style="text-align:center;"><?php echo h($s['acted_at']); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($canApprove): ?>
    <h4>我的審批（<?php echo h($user['role_name']); ?>）</h4>
    <textarea id="<?php echo $uid; ?>_comment" rows="3" style="width:98%;box-sizing:border-box;" placeholder="審批意見（退回時必填）" maxlength="500"></textarea>
    <div style="text-align:center;padding:6px 0 10px;">
        <a class="btn btn-primary" id="<?php echo $uid; ?>_pass">通 過</a>&nbsp;&nbsp;
        <a class="btn btn-danger" id="<?php echo $uid; ?>_reject">退 回</a>
    </div>
    <?php elseif ($canSubmit): ?>
    <div style="text-align:center;padding:6px 0 10px;"><a class="btn btn-primary" id="<?php echo $uid; ?>_submit">提交審批</a></div>
    <?php endif; ?>
</div>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', id = <?php echo (int)$doc['id']; ?>;
    function done() {
        $(U + '_box').closest('.panel-body').dialog('close');
        PM.reloadDocs();
    }
    function act(name) {
        PM.post('api/doc.php?act=' + name, {id: id, comment: $(U + '_comment').val()}, done);
    }
    $(U + '_pass').click(function(){ PM.confirm('確定審批通過嗎？', function(){ act('approve'); }); });
    $(U + '_reject').click(function(){
        if ($.trim($(U + '_comment').val()) === '') return PM.alert('退回時請填寫審批意見');
        PM.confirm('確定退回該文件嗎？', function(){ act('reject'); });
    });
    $(U + '_submit').click(function(){ PM.confirm('確定提交審批嗎？', function(){ act('submit'); }); });
});
</script>
