<?php
/** 首頁：工程概況、待辦審批、文件統計 */
require_once __DIR__ . '/inc/config.php';
require_login();
$user = current_user();
$admin = is_admin();
$project = db_row('SELECT * FROM pm_projects ORDER BY id LIMIT 1');
$stat = array(DOC_DRAFT => 0, DOC_PENDING => 0, DOC_APPROVED => 0, DOC_REJECTED => 0);
foreach (db_rows('SELECT status, COUNT(*) AS n FROM pm_docs GROUP BY status') as $r) $stat[(int)$r['status']] = (int)$r['n'];
$todo = $admin ? array() : db_rows(
    "SELECT d.id, d.doc_no, d.title, d.submitted_at, COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name
     FROM pm_docs d JOIN pm_doc_steps s ON s.doc_id = d.id AND s.round = d.round
     LEFT JOIN pm_users u ON u.id = d.creator_id
     WHERE d.status = ? AND s.status = ? AND s.role_id = ? ORDER BY d.id DESC LIMIT 10", array(DOC_PENDING, STEP_CURRENT, $user['role_id']));
$flows = db_rows('SELECT f.id, f.name, p.name AS project_name FROM pm_flows f LEFT JOIN pm_projects p ON p.id = f.project_id WHERE f.status = 1 ORDER BY f.id LIMIT 10');
?>
<div style="padding:8px;overflow:auto;height:100%;box-sizing:border-box;">
    <div class="easyui-panel" title="工程概況" style="padding:6px;margin-bottom:8px;" data-options="collapsible:true">
        <table class="table table-bordered table-condensed table-striped" style="margin:0;">
            <thead><tr><th>工程編號</th><th>工程名稱</th><th>工程地點</th><th>業主單位</th><th>承建商</th><th>開工日期</th><th>竣工日期</th><th>狀態</th></tr></thead>
            <tbody>
            <?php if ($project): ?>
            <tr><td><?php echo h($project['code']); ?></td><td><?php echo h($project['name']); ?></td><td><?php echo h($project['location']); ?></td><td><?php echo h($project['owner_unit']); ?></td>
                <td><?php echo h($project['contractor_unit']); ?></td><td><?php echo h($project['start_date']); ?></td><td><?php echo h($project['end_date']); ?></td><td><?php echo h($project['status']); ?></td></tr>
            <?php else: ?><tr><td colspan="8" style="text-align:center;color:#999;">暫無工程</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div style="height:8px;"></div>
    <?php if (!$admin): ?>
    <div class="easyui-panel" title="待辦審批（<?php echo h($user['role_name']); ?>）" style="padding:6px;" data-options="collapsible:true">
        <table class="table table-bordered table-condensed table-striped" style="margin:0;">
            <thead><tr><th width="150">文件編號</th><th>文件標題</th><th width="120">提交人</th><th width="160">提交時間</th><th width="80">操作</th></tr></thead>
            <tbody>
            <?php foreach ($todo as $d): ?>
            <tr><td><?php echo h($d['doc_no']); ?></td><td><?php echo h($d['title']); ?></td><td><?php echo h($d['creator_name']); ?></td><td><?php echo h($d['submitted_at']); ?></td>
                <td><a href="javascript:void(0)" onclick="PM.viewDoc(<?php echo (int)$d['id']; ?>)">審批</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$todo): ?><tr><td colspan="5" style="text-align:center;color:#999;">暫無待辦審批</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div style="height:8px;"></div>
    <?php endif; ?>
    <div class="easyui-panel" title="文件統計" style="padding:6px;" data-options="collapsible:true">
        <table class="table table-bordered table-condensed" style="margin:0;text-align:center;">
            <thead><tr><th style="text-align:center;">草稿</th><th style="text-align:center;">審批中</th><th style="text-align:center;">已通過</th><th style="text-align:center;">已退回</th></tr></thead>
            <tbody><tr>
                <td><?php echo $stat[DOC_DRAFT]; ?></td><td style="color:#e68a00;font-weight:bold;"><?php echo $stat[DOC_PENDING]; ?></td>
                <td style="color:#2e8b57;font-weight:bold;"><?php echo $stat[DOC_APPROVED]; ?></td><td style="color:#d93025;font-weight:bold;"><?php echo $stat[DOC_REJECTED]; ?></td>
            </tr></tbody>
        </table>
    </div>
</div>
