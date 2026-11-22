<?php
/** 投票詳情：參與投票 / 查看結果 */
$v = db_row("SELECT v.*, COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name FROM pm_votes v LEFT JOIN pm_users u ON u.id = v.user_id WHERE v.id = ?", array(param_int('id')));
if (!$v) { echo '<div style="padding:20px;">投票不存在</div>'; return; }
$opts = db_rows('SELECT o.id, o.title, (SELECT COUNT(*) FROM pm_vote_records r WHERE r.option_id = o.id) AS n FROM pm_vote_options o WHERE o.vote_id = ? ORDER BY o.sort, o.id', array($v['id']));
$mine = array();
foreach (db_rows('SELECT option_id FROM pm_vote_records WHERE vote_id = ? AND user_id = ?', array($v['id'], $user['id'])) as $r) $mine[] = (int)$r['option_id'];
$open = (int)$v['status'] === 1 && ($v['end_date'] === null || $v['end_date'] >= date('Y-m-d'));
$canVote = $open && !$mine;
$total = 0;
foreach ($opts as $o) $total += (int)$o['n'];
$voters = (int)db_val('SELECT COUNT(DISTINCT user_id) FROM pm_vote_records WHERE vote_id = ?', array($v['id']));
$names = array();
if (!(int)$v['anonymous']) {
    foreach (db_rows("SELECT r.option_id, COALESCE(NULLIF(u.real_name, ''), u.username) AS name FROM pm_vote_records r LEFT JOIN pm_users u ON u.id = r.user_id WHERE r.vote_id = ? ORDER BY r.id", array($v['id'])) as $r) {
        $names[(int)$r['option_id']][] = $r['name'];
    }
}
?>
<div class="pm-box" id="<?php echo $uid; ?>_box">
    <table class="pm-detail">
        <tr><th>投票主題</th><td><?php echo h($v['title']); ?></td></tr>
        <tr><th>說明</th><td><?php echo nl2br(h($v['content'])); ?></td></tr>
        <tr><th>發起人</th><td><?php echo h($v['creator_name']); ?>　<?php echo h($v['created_at']); ?></td></tr>
        <tr><th>規則</th><td><?php echo (int)$v['multi'] ? '多選' : '單選'; ?>，<?php echo (int)$v['anonymous'] ? '匿名投票' : '記名投票'; ?>，截止日期：<?php echo $v['end_date'] ? h($v['end_date']) : '不限'; ?>，
            狀態：<b style="color:<?php echo $open ? '#2e8b57' : '#888'; ?>"><?php echo $open ? '進行中' : '已結束'; ?></b>，已有 <?php echo $voters; ?> 人投票</td></tr>
    </table>
    <h4><?php echo $canVote ? '請選擇' : '投票結果'; ?></h4>
    <table class="pm-detail">
        <?php foreach ($opts as $o): $pct = $total ? round($o['n'] * 100 / $total) : 0; $me = in_array((int)$o['id'], $mine, true); ?>
        <tr>
            <td style="width:45%;">
                <?php if ($canVote): ?><label style="font-weight:normal;margin:0;"><input type="<?php echo (int)$v['multi'] ? 'checkbox' : 'radio'; ?>" name="<?php echo $uid; ?>_opt" value="<?php echo (int)$o['id']; ?>" style="margin:0 6px 0 0;"/><?php echo h($o['title']); ?></label>
                <?php else: ?><?php echo h($o['title']); ?><?php if ($me): ?> <b style="color:#1F5FA8;">（我的選擇）</b><?php endif; ?><?php endif; ?>
            </td>
            <td>
                <?php if (!$canVote): ?>
                <div style="background:#e6eef8;height:14px;width:60%;display:inline-block;vertical-align:middle;"><div style="background:#1F5FA8;height:14px;width:<?php echo $pct; ?>%;"></div></div>
                <?php echo (int)$o['n']; ?> 票（<?php echo $pct; ?>%）
                <?php if (!empty($names[(int)$o['id']])): ?><div style="color:#888;font-size:12px;"><?php echo h(implode('、', $names[(int)$o['id']])); ?></div><?php endif; ?>
                <?php else: ?><span style="color:#999;">投票後可查看結果</span><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php if ($canVote): ?><div style="text-align:center;"><a class="easyui-linkbutton" icon="icon-ok" id="<?php echo $uid; ?>_cast">提交投票</a></div><?php endif; ?>
</div>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>';
    $(U + '_cast').click(function(){
        var ids = $(U + '_box').find('input[name="<?php echo $uid; ?>_opt"]:checked').map(function(){ return this.value; }).get();
        if (!ids.length) return PM.alert('請選擇選項');
        PM.post('api/vote.php?act=cast', {id: <?php echo (int)$v['id']; ?>, option_ids: ids.join(',')}, function(){
            $(U + '_box').closest('.panel-body').panel('refresh');
            $('table.pm-vote-grid').each(function(){ if ($.data(this, 'datagrid')) $(this).datagrid('reload'); });
        });
    });
});
</script>
