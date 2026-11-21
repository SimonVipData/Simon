<?php
/**
 * 菜單定義：一級為頂部標籤，二級為左側導航
 */
function pm_menus() {
    $admin = is_admin();
    $doc = array(array('title' => '工程信息', 'icon' => 'bricks.png', 'rel' => 'pm_project', 'page' => 'project'));
    if (!$admin) {
        $doc[] = array('title' => '我的文件', 'icon' => 'Icon_2430308_s.gif', 'rel' => 'pm_doc_mine', 'page' => 'doc_mine');
        $doc[] = array('title' => '待辦審批', 'icon' => '24102_s.gif', 'rel' => 'pm_doc_todo', 'page' => 'doc_todo');
        $doc[] = array('title' => '已辦審批', 'icon' => '2430309_s.gif', 'rel' => 'pm_doc_done', 'page' => 'doc_done');
    }
    $doc[] = array('title' => '全部文件', 'icon' => 'building_link.png', 'rel' => 'pm_doc_all', 'page' => 'doc_all');

    $menus = array(array('id' => 'doc', 'title' => '工程文件', 'icon' => 'resource/images/menu/gzlc.png', 'children' => $doc));
    if ($admin) {
        $menus[] = array('id' => 'sys', 'title' => '系統管理', 'icon' => 'resource/images/menu/yzsz.png', 'children' => array(
            array('title' => '角色管理', 'icon' => 'gstxl.png', 'rel' => 'pm_sys_role', 'page' => 'sys_role'),
            array('title' => '用戶管理', 'icon' => 'grtxl.png', 'rel' => 'pm_sys_user', 'page' => 'sys_user'),
            array('title' => '文件審批設置', 'icon' => '24502_s.gif', 'rel' => 'pm_sys_flow', 'page' => 'sys_flow'),
            array('title' => '數據庫管理', 'icon' => 'gear.png', 'rel' => 'pm_sys_db', 'page' => 'sys_db'),
        ));
    }
    $menus[] = array('id' => 'me', 'title' => '個人中心', 'icon' => 'resource/images/menu/grtxl.png', 'children' => array(
        array('title' => '個人信息', 'icon' => 'grtxl.png', 'rel' => 'pm_user_info', 'page' => 'user_info'),
        array('title' => '修改密碼', 'icon' => '18.png', 'rel' => 'pm_user_pw', 'page' => 'user_pw'),
        array('title' => '登錄日志', 'icon' => 'dlrz.png', 'rel' => 'pm_log_login', 'page' => 'log_login'),
    ));
    return $menus;
}

/** 頁面白名單：name => array(文件, 是否僅管理員) */
function pm_pages() {
    return array(
        'project' => array('project', false),
        'doc_mine' => array('doc_list', false), 'doc_todo' => array('doc_list', false),
        'doc_done' => array('doc_list', false), 'doc_all' => array('doc_list', false),
        'doc_view' => array('doc_view', false),
        'sys_role' => array('sys_role', true), 'sys_user' => array('sys_user', true),
        'sys_flow' => array('sys_flow', true), 'sys_db' => array('sys_db', true),
        'user_info' => array('user_info', false), 'user_pw' => array('user_pw', false), 'log_login' => array('log_login', false),
    );
}
