<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/menus.php';
require_login();

$id = param('id');
$current = null;
foreach (pm_menus() as $m) {
    if ($m['id'] === $id) { $current = $m; break; }
}
if (!$current) {
    echo '菜單不存在';
    exit;
}
?>
<div class="easyui-layout" fit="true" border="false">
    <div region="west" split="true" title="<?php echo h($current['title']); ?>" style="width:220px;padding:1px;">
        <ul class="ul-menu">
            <?php foreach ($current['children'] as $c): ?>
            <li><a href="page.php?name=<?php echo $c['page']; ?>" target="navTab" rel="<?php echo $c['rel']; ?>" external="false" fresh="false" title="<?php echo h($c['title']); ?>"><img src="resource/images/menu/<?php echo $c['icon']; ?>"/>&nbsp;&nbsp;<?php echo h($c['title']); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div region="center" style="overflow:hidden;border-top:none">
        <div class="maintabs" fit="true" border="false"></div>
    </div>
</div>
