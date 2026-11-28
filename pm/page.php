<?php
/**
 * 功能頁路由：page.php?name=xxx → pages/xxx.php
 */
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/menus.php';
require_login();
if (!empty($_SESSION['pm_locked'])) {
    echo '<div style="padding:20px;">系統界面已鎖定，請輸入密碼解鎖</div><script>PM.lockShow();</script>';
    exit;
}

$name = param('name');
$pages = pm_pages();
if (!isset($pages[$name])) {
    echo '<div style="padding:20px;">頁面不存在</div>';
    exit;
}
if ($pages[$name][1]) require_admin_page($pages[$name][1] === 'super');
if (!menu_on(menu_key_of_page($name))) {
    echo '<div style="padding:20px;">該功能已被管理員關閉</div>';
    exit;
}
$user = current_user();
$admin = is_admin();
// 同一頁面可能同時在多個標籤/窗口中打開，元素 ID 加上唯一前綴
$uid = 'p' . bin2hex(random_bytes(4));
$crudName = isset($pages[$name][2]) ? $pages[$name][2] : '';
require __DIR__ . '/pages/' . $pages[$name][0] . '.php';
