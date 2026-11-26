<?php
/** 論壇主題詳情與回覆 */
require_once __DIR__ . '/../inc/files.php';
require_once __DIR__ . '/../inc/forum.php';
$sel = "COALESCE(NULLIF(u.real_name, ''), u.username) AS author, r.name AS role_name";
$t = db_row("SELECT t.*, b.name AS board_name, $sel FROM pm_forum_threads t LEFT JOIN pm_forum_boards b ON b.id = t.board_id
             LEFT JOIN pm_users u ON u.id = t.user_id LEFT JOIN pm_roles r ON r.id = u.role_id WHERE t.id = ?", array(param_int('id')));
if (!$t) { echo '<div style="padding:20px;">主題不存在</div>'; return; }
$acc = forum_access($t, $user);
$pv = (int)$t['pay_view'];
$pf = (int)$t['pay_file'];
// 同一會話內重複打開不重複計數（未付費不計）
if ($acc['view'] && empty($_SESSION['pm_forum_seen'][$t['id']])) {
    $_SESSION['pm_forum_seen'][$t['id']] = 1;
    db_q('UPDATE pm_forum_threads SET views = views + 1 WHERE id = ?', array($t['id']));
}
$posts = !$acc['view'] ? array() : db_rows("SELECT p.*, $sel FROM pm_forum_posts p LEFT JOIN pm_users u ON u.id = p.user_id LEFT JOIN pm_roles r ON r.id = u.role_id WHERE p.thread_id = ? ORDER BY p.id", array($t['id']));
$fm = files_map('forum', array((int)$t['id']));
$files = isset($fm[(int)$t['id']]['att']) ? $fm[(int)$t['id']]['att'] : array();
$income = null;
if ($acc['free'] && ($pv > 0 || $pf > 0)) {
    $income = array('view' => array(0, 0), 'file' => array(0, 0));
    foreach (db_rows('SELECT kind, COUNT(*) AS n, SUM(amount) AS s FROM pm_forum_pays WHERE thread_id = ? GROUP BY kind', array($t['id'])) as $r) $income[$r['kind']] = array((int)$r['n'], (int)$r['s']);
}
?>
<div class="pm-box pm-forum" id="<?php echo $uid; ?>_box">
    <h4>[<?php echo h($t['board_name']); ?>] <?php echo h($t['title']); ?><?php if ((int)$t['is_locked']): ?> <span style="color:#888;">（已鎖定）</span><?php endif; ?></h4>
    <div class="pm-post">
        <div class="hd"><b><?php echo h($t['author']); ?></b>（<?php echo h($t['role_name']); ?>）　樓主　<?php echo h($t['created_at']); ?></div>
        <div class="bd">
            <?php if (!$acc['view']): ?>
            <div class="pm-pay">本貼為付費貼，需支付 <b><?php echo $pv; ?></b> 積分才可查看內容和回覆<?php if ($files): ?>（含 <?php echo count($files); ?> 個附件<?php if ($pf > 0): ?>，下載需另付 <b><?php echo $pf; ?></b> 積分<?php endif; ?>）<?php endif; ?>。
                <a class="easyui-linkbutton pm-pay-btn" icon="icon-ok" data-kind="view" data-amount="<?php echo $pv; ?>">支付 <?php echo $pv; ?> 積分查看</a></div>
            <?php else: ?>
            <?php echo nl2br(h($t['content'])); ?>
            <?php if ($files): ?><div class="att">附件：<?php foreach ($files as $f): ?><?php if ($acc['file']): ?><a href="api/file.php?id=<?php echo $f['id']; ?>"><?php echo h($f['name']); ?></a><?php else: ?><span><?php echo h($f['name']); ?></span><?php endif; ?>　<?php endforeach; ?>
                <?php if (!$acc['file']): ?><div class="pm-pay">下載本貼附件需支付 <b><?php echo $pf; ?></b> 積分（支付一次可下載全部附件）。
                    <a class="easyui-linkbutton pm-pay-btn" icon="icon-ok" data-kind="file" data-amount="<?php echo $pf; ?>">支付 <?php echo $pf; ?> 積分下載附件</a></div><?php endif; ?>
            </div><?php endif; ?>
            <?php endif; ?>
            <?php if ($income): ?><div class="pm-pay-info">付費設置：查看 <?php echo $pv; ?> 積分（已有 <?php echo $income['view'][0]; ?> 人支付，共 <?php echo $income['view'][1]; ?> 積分）；下載附件 <?php echo $pf; ?> 積分（已有 <?php echo $income['file'][0]; ?> 人支付，共 <?php echo $income['file'][1]; ?> 積分）</div><?php endif; ?>
        </div>
    </div>
    <?php foreach ($posts as $i => $p): ?>
    <div class="pm-post">
        <div class="hd"><b><?php echo h($p['author']); ?></b>（<?php echo h($p['role_name']); ?>）　<?php echo $i + 1; ?> 樓　<?php echo h($p['created_at']); ?>
            <?php if ($admin || (int)$p['user_id'] === (int)$user['id']): ?><a href="javascript:void(0)" class="pm-post-del" data-id="<?php echo (int)$p['id']; ?>" style="float:right;">刪除</a><?php endif; ?></div>
        <div class="bd"><?php echo nl2br(h($p['content'])); ?></div>
    </div>
    <?php endforeach; ?>
    <?php if (!(int)$t['is_locked'] && $acc['view']): ?>
    <h4 style="margin-top:12px;">發表回覆</h4>
    <textarea id="<?php echo $uid; ?>_content" rows="4" style="width:100%;box-sizing:border-box;" maxlength="20000"></textarea>
    <a class="easyui-linkbutton" icon="icon-ok" id="<?php echo $uid; ?>_reply">回覆</a>
    <?php endif; ?>
</div>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', id = <?php echo (int)$t['id']; ?>;
    function refresh(){ $(U + '_box').closest('.panel-body').panel('refresh'); }
    $(U + '_reply').click(function(){
        var v = $.trim($(U + '_content').val());
        if (!v) return PM.alert('請輸入回覆內容');
        PM.post('api/forum.php?act=reply', {id: id, content: v}, refresh);
    });
    $(U + '_box').find('.pm-pay-btn').click(function(){
        var kind = $(this).data('kind'), amount = $(this).data('amount');
        var bal = PM.counts && PM.counts.limited ? '您當前有 ' + $('#ft_credit').text() + ' 積分，' : '';
        PM.confirm(bal + '確定支付 ' + amount + ' 積分' + (kind === 'file' ? '下載本貼附件' : '查看本貼') + '嗎？積分將轉給發貼人，同一貼子只需支付一次。', function(){
            PM.post('api/forum.php?act=pay', {id: id, kind: kind}, function(res){
                if (PM.counts && PM.counts.limited) PM.setCredits(res.credits);
                refresh();
            });
        });
    });
    $(U + '_box').find('.pm-post-del').click(function(){
        var pid = $(this).data('id');
        PM.confirm('確定刪除該回覆嗎？', function(){ PM.post('api/forum.php?act=del_post', {id: id, post_id: pid}, refresh); });
    });
});
</script>
