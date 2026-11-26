<?php
/**
 * 菜單定義：一級為頂部標籤，二級為左側導航
 */
function pm_menus() {
    $admin = is_admin();
    $item = function ($title, $icon, $page) {
        return array('title' => $title, 'icon' => $icon, 'rel' => 'pm_' . $page, 'page' => $page);
    };
    $doc = array($item('工程信息', 'bricks.png', 'project'));
    if (!$admin) {
        $doc[] = $item('我的文件', 'Icon_2430308_s.gif', 'doc_mine');
        $doc[] = $item('待辦審批', '24102_s.gif', 'doc_todo');
        $doc[] = $item('已辦審批', '2430309_s.gif', 'doc_done');
    }
    $doc[] = $item('全部文件', 'building_link.png', 'doc_all');

    $menus = array(
        array('id' => 'doc', 'title' => '工程文件', 'icon' => 'resource/images/menu/gzlc.png', 'children' => $doc),
        array('id' => 'office', 'title' => '個人辦公', 'icon' => 'resource/images/menu/xzbg.png', 'children' => array(
            $item('消息提醒', 'msg.gif', 'office_remind'),
            $item('即時溝通', 'reply.gif', 'office_chat'),
            $item('內部郵件', 'mail_new.gif', 'office_mail'),
            $item('投票', 'chart_line.png', 'office_vote'),
        )),
        array('id' => 'work', 'title' => '工程實務', 'icon' => 'resource/images/menu/ggpt.png', 'children' => array(
            $item('記事本', 'mail_edit.png', 'note'),
            $item('待辦事項', '24101_s.gif', 'todo'),
            $item('工程日曆', '25008_s.gif', 'work_calendar'),
            $item('客戶管理', 'gstxl.png', 'customer'),
            $item('材料管理', 'bricks.png', 'material'),
            $item('分包管理', 'building_edit.png', 'sub'),
            $item('勞工管理', 'grtxl.png', 'labor'),
        )),
        array('id' => 'appr', 'title' => '內部審批', 'icon' => 'resource/images/menu/wdgl.gif', 'children' => array(
            $item('詢價審批', '24001_s.gif', 'appr_inquiry'),
            $item('費用審批', '24002_s.gif', 'appr_expense'),
            $item('事項審批', '24301_s.gif', 'appr_matter'),
        )),
        array('id' => 'disk', 'title' => '網盤資料', 'icon' => 'resource/images/menu/32.png', 'children' => array(
            $item('個人文件', '32.png', 'disk_personal'),
            $item('共享文件', '32.png', 'disk_share'),
            $item('公司文件', '32.png', 'disk_company'),
        )),
        array('id' => 'forum', 'title' => '工程論壇', 'icon' => 'resource/images/menu/xtgg.png', 'children' => array(
            $item('工程論壇', 'xtgg.png', 'forum'),
        )),
    );
    if ($admin) {
        $sys = array(
            $item('控制面板', 'gear.png', 'sys_panel'),
            $item('角色管理', 'gstxl.png', 'sys_role'),
            $item('職位管理', '26.png', 'position'),
            $item('用戶管理', 'grtxl.png', 'sys_user'),
            $item('文件審批設置', '24502_s.gif', 'sys_flow'),
            $item('注冊管理', 'grtxl.png', 'sys_reg'),
            $item('積分管理', '30.png', 'sys_credit'),
            $item('消息發布', 'msg.gif', 'notice'),
            $item('論壇版塊', 'xtgg.png', 'forum_board'),
        );
        if (is_super()) $sys[] = $item('數據庫管理', 'gear.png', 'sys_db');
        $menus[] = array('id' => 'sys', 'title' => '系統管理', 'icon' => 'resource/images/menu/yzsz.png', 'children' => $sys);
    }
    $menus[] = array('id' => 'me', 'title' => '個人中心', 'icon' => 'resource/images/menu/grtxl.png', 'children' => array(
        $item('個人信息', 'grtxl.png', 'user_info'),
        $item('修改密碼', '18.png', 'user_pw'),
        $item('登錄日志', 'dlrz.png', 'log_login'),
    ));
    return $menus;
}

/** 頁面白名單：name => array(文件, 權限(false 全員 / true 管理員 / 'super' 超級管理員)[, 通用模塊名]) */
function pm_pages() {
    $pages = array(
        'project' => array('project', false),
        'doc_mine' => array('doc_list', false), 'doc_todo' => array('doc_list', false),
        'doc_done' => array('doc_list', false), 'doc_all' => array('doc_list', false),
        'doc_view' => array('doc_view', false),
        'sys_role' => array('sys_role', true), 'sys_user' => array('sys_user', true),
        'sys_flow' => array('sys_flow', true), 'sys_db' => array('sys_db', 'super'),
        'sys_credit' => array('sys_credit', true), 'sys_reg' => array('sys_reg', true),
        'sys_panel' => array('sys_panel', true),
        'user_info' => array('user_info', false), 'user_pw' => array('user_pw', false), 'log_login' => array('log_login', false),
        'office_remind' => array('office_remind', false), 'office_chat' => array('office_chat', false),
        'office_mail' => array('office_mail', false), 'mail_view' => array('mail_view', false),
        'office_vote' => array('office_vote', false), 'vote_view' => array('vote_view', false),
        'work_calendar' => array('work_calendar', false),
        'appr_inquiry' => array('appr_list', false), 'appr_expense' => array('appr_list', false), 'appr_matter' => array('appr_list', false),
        'appr_view' => array('appr_view', false),
        'forum' => array('forum', false), 'forum_thread' => array('forum_thread', false),
        'disk_personal' => array('disk', false), 'disk_share' => array('disk', false), 'disk_company' => array('disk', false),
    );
    foreach (array('position' => true, 'notice' => true, 'forum_board' => true, 'note' => false, 'todo' => false, 'customer' => false,
                 'customer_quote' => false, 'sub' => false, 'sub_quote' => false, 'material' => false, 'labor' => false) as $m => $adminOnly) {
        $pages[$m] = array('crud', $adminOnly, $m);
    }
    return $pages;
}
