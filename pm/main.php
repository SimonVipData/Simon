<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/menus.php';
require_login();
$user = current_user();
$menus = pm_menus();
$admin = is_admin();
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <?php include __DIR__ . '/inc/head.php'; ?>
    <style>
        #div_top_2 a{text-decoration:none;}
        .pm-logo{float:left;margin-left:10px;font-size:22px;font-weight:bold;color:#1F5FA8;line-height:34px;letter-spacing:2px;text-shadow:1px 1px 0 #fff, 2px 2px 2px rgba(0,0,0,.25);white-space:nowrap;}
    </style>
</head>
<body>
<div id="sy_layout" class="easyui-layout" fit="true">
    <!-- 頂部 -->
    <div region="north" border="false" style="height: 38px; padding: 1px; overflow: hidden;">
        <div class="pm-logo"><?php echo APP_NAME; ?></div>
        <div style="padding-top:2px;background-color: #F9F9F9;height: 100%">
            <div style="float: right;">
                <a href="javascript:void(0);" class="easyui-menubutton" menu="#layout_north_gryhMenu" iconCls="ui-icon-cog">當前用戶：<?php echo h($user['username']); ?>（<?php echo h($user['role_name']); ?>）</a>
            </div>
            <div id="layout_north_gryhMenu" style="width:150px;display:none;">
                <div iconCls="icon-back" onclick="goOutSystem()">安全退出</div>
                <div class="menu-sep"></div>
                <div>
                    <span>個人中心</span>
                    <div style="width:150px;">
                        <div onclick="MUI.openDialog('個人信息','page.php?name=user_info','dlg_user_info',{width:700,height:380})">個人信息</div>
                        <div onclick="MUI.openDialog('登錄日志','page.php?name=log_login','dlg_log_login',{width:900,height:450})">登錄日志</div>
                        <div onclick="MUI.openDialog('修改密碼','page.php?name=user_pw','dlg_user_pw',{width:600,height:320})">修改密碼</div>
                    </div>
                </div>
            </div>
            <div id="div_top_2">
                <div>
                    <a href="javascript:void(0)" onclick="MUI.openDialog('消息提醒','page.php?name=office_remind','dlg_office_remind',{width:1000,height:520})" title="消息提醒（待審批文件、內部審批、系統消息）">
                        <img src="resource/images/menu/flow.gif" alt="消息提醒" style="vertical-align:middle;"/>(<span style="color:red" id="ft_todo">0</span>)</a>
                </div>
                <div>
                    <a href="javascript:void(0)" onclick="MUI.openDialog('內部郵件','page.php?name=office_mail','dlg_office_mail',{width:1000,height:520})" title="未讀郵件">
                        <img src="resource/images/menu/mail_new.gif" alt="內部郵件" style="vertical-align:middle;"/>(<span style="color:red" id="ft_mail">0</span>)</a>
                </div>
                <div>
                    <a href="javascript:void(0)" onclick="MUI.openDialog('即時溝通','page.php?name=office_chat','dlg_office_chat',{width:900,height:540})" title="未讀即時消息">
                        <img src="resource/images/menu/reply.gif" alt="即時溝通" style="vertical-align:middle;"/>(<span style="color:red" id="ft_chat">0</span>)</a>
                </div>
                <?php if (!$admin): ?>
                <div style="width:auto;padding:0 6px;" title="積分即可使用本系統的分鐘數，用完後將自動退出">積分：<b style="color:#d93025" id="ft_credit"><?php echo (int)ceil($user['credits'] / 60); ?></b> 分鐘</div>
                <?php endif; ?>
                <div style="width:auto;padding:0 6px;"><a href="javascript:void(0)" onclick="PMLang.toggle()" id="pm_lang_btn" title="切換繁體 / 簡體">简体</a></div>
                <div style="width:200px;"><span id="clock"></span></div>
            </div>
        </div>
    </div>

    <!-- 中間工作區 -->
    <div id="mainPanle" region="center" style="overflow:hidden;padding:1px">
        <div class="easyui-tab" id="mainWorkTab" fit="true" border="false" tabHeight="35">
            <div title="<img src='resource/images/menu/1_close.png'/> 首頁">
                <div class="easyui-layout" fit="true">
                    <div region="west" split="true" title="導航菜單" style="width:230px;padding:1px;">
                        <div id="nav" class="easyui-accordion" fit="true">
                            <div title="快速菜單" iconCls="ui-icon-computer">
                                <ul class="ul-menu" style="margin-left:2px;margin-right:2px;margin-top:2px">
                                    <?php foreach ($menus as $m): if ($m['id'] === 'me') continue; foreach ($m['children'] as $c): ?>
                                    <li><a href="page.php?name=<?php echo $c['page']; ?>" target="navTab" rel="home_<?php echo $c['rel']; ?>" external="false" title="<?php echo h($c['title']); ?>"><img src="resource/images/menu/<?php echo $c['icon']; ?>"/>&nbsp;&nbsp;<?php echo h($c['title']); ?></a></li>
                                    <?php endforeach; endforeach; ?>
                                </ul>
                            </div>
                            <div title="當前用戶" iconCls="ui-icon-group">
                                <div style="padding:10px;line-height:24px;">
                                    帳號：<?php echo h($user['username']); ?><br/>
                                    姓名：<?php echo h($user['real_name']); ?><br/>
                                    角色：<?php echo h($user['role_name']); ?><br/>
                                    職位：<?php echo h($user['position_name']); ?><br/>
                                    類別：<?php echo is_super() ? '超級管理員' : ($admin ? '管理員' : '普通用戶'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div region="center" style="overflow:hidden;border-top:none">
                        <div class="maintabs" fit="true" border="false">
                            <div id="tab-mainPage" title="首頁" href="home.php"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php foreach ($menus as $m): ?>
            <div title="<img src='<?php echo $m['icon']; ?>'/>  <?php echo h($m['title']); ?>" href="menu.php?id=<?php echo $m['id']; ?>"></div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 底部 -->
    <div region="south" border="false" style="height:30px;overflow:hidden;">
        <div id="div_footer"><?php echo APP_COPYRIGHT; ?></div>
    </div>
</div>

<div id="maintabs_menu" class="easyui-menu" style="width:150px;display:none">
    <div id="maintabs-update" iconCls="icon-reload">刷新</div>
    <div class="menu-sep"></div>
    <div id="maintabs-close" iconCls="icon-cancel">關閉</div>
    <div id="maintabs-closeall">關閉全部</div>
    <div id="maintabs-closeother">關閉其他</div>
    <div class="menu-sep"></div>
    <div id="maintabs-closeright">關閉右側</div>
    <div id="maintabs-closeleft">關閉左側</div>
</div>

<div id="taskbar" style="left:0px;display:none;">
    <div class="taskbarContent"><ul></ul></div>
    <div class="taskbarLeft taskbarLeftDisabled" style="display:none;">taskbarLeft</div>
    <div class="taskbarRight" style="display:none;">taskbarRight</div>
</div>

<script type="text/javascript">
    PM.init({csrf: '<?php echo csrf_token(); ?>', admin: <?php echo $admin ? 'true' : 'false'; ?>, userId: <?php echo (int)$user['id']; ?>, roleId: <?php echo (int)$user['role_id']; ?>});
    function goOutSystem() { window.location.href = 'logout.php'; }
    $(function(){
        function pad(n){return n<10?'0'+n:''+n;}
        function tick(){
            var d = new Date();
            $('#clock').text(d.getFullYear()+'年'+(d.getMonth()+1)+'月'+d.getDate()+'日 '+pad(d.getHours())+':'+pad(d.getMinutes())+':'+pad(d.getSeconds())+' 星期'+'日一二三四五六'.charAt(d.getDay()));
        }
        tick(); setInterval(tick, 1000);
        PM.ping(); setInterval(PM.ping, 30000);
    });
</script>
</body>
</html>
