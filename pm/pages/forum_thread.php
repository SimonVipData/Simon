<?php
/** 論壇主題詳情與回覆 */
require_once __DIR__ . '/../inc/files.php';
$sel = "COALESCE(NULLIF(u.real_name, ''), u.username) AS author, r.name AS role_name";
$t = db_row("SELECT t.*, b.name AS board_name, $sel FROM pm_forum_threads t LEFT JOIN pm_forum_boards b ON b.id = t.board_id
             LEFT JOIN pm_users u ON u.id = t.user_id LEFT JOIN pm_roles r ON r.id = u.role_id WHERE t.id = ?", array(param_int('id')));
if (!$t) { echo '<div style="padding:20px;">主題不存在</div>'; return; }
// 同一會話內重複打開不重複計數
if (empty($_SESSION['pm_forum_seen'][$t['id']])) {
    $_SESSION['pm_forum_seen'][$t['id']] = 1;
    db_q('UPDATE pm_forum_threads SET views = views + 1 WHERE id = ?', array($t['id']));
}
$posts = db_rows("SELECT p.*, $sel FROM pm_forum_posts p LEFT JOIN pm_users u ON u.id = p.user_id LEFT JOIN pm_roles r ON r.id = u.role_id WHERE p.thread_id = ? ORDER BY p.id", array($t['id']));
$fm = files_map('forum', array((int)$t['id']));
$files = isset($fm[(int)$t['id']]['att']) ? $fm[(int)$t['id']]['att'] : array();
?>
<div class="pm-box pm-forum" id="<?php echo $uid; ?>_box">
    <h4>[<?php echo h($t['board_name']); ?>] <?php echo h($t['title']); ?><?php if ((int)$t['is_locked']): ?> <span style="color:#888;">（已鎖定）</span><?php endif; ?></h4>
    <div class="pm-post">
        <div class="hd"><b><?php echo h($t['author']); ?></b>（<?php echo h($t['role_name']); ?>）　樓主　<?php echo h($t['created_at']); ?></div>
        <div class="bd"><?php echo nl2br(h($t['content'])); ?>
            <?php if ($files): ?><div class="att">附件：<?php foreach ($files as $f): ?><a href="api/file.php?id=<?php echo $f['id']; ?>"><?php echo h($f['name']); ?></a>　<?php endforeach; ?></div><?php endif; ?>
        </div>
    </div>
    <?php foreach ($posts as $i => $p): ?>
    <div class="pm-post">
        <div class="hd"><b><?php echo h($p['author']); ?></b>（<?php echo h($p['role_name']); ?>）　<?php echo $i + 1; ?> 樓　<?php echo h($p['created_at']); ?>
            <?php if ($admin || (int)$p['user_id'] === (int)$user['id']): ?><a href="javascript:void(0)" class="pm-post-del" data-id="<?php echo (int)$p['id']; ?>" style="float:right;">刪除</a><?php endif; ?></div>
        <div class="bd"><?php echo nl2br(h($p['content'])); ?></div>
    </div>
    <?php endforeach; ?>
    <?php if (!(int)$t['is_locked']): ?>
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
    $(U + '_box').find('.pm-post-del').click(function(){
        var pid = $(this).data('id');
        PM.confirm('確定刪除該回覆嗎？', function(){ PM.post('api/forum.php?act=del_post', {id: id, post_id: pid}, refresh); });
    });
});
</script>
