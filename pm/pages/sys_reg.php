<?php
/** 注冊管理：用戶注冊功能的開關、注冊時需提供的信息、待審核的注冊申請 */
require_once __DIR__ . '/../inc/reg.php';
$set = reg_settings();
$defs = reg_field_defs();
$roles = reg_user_roles();
$modes = array(0 => '不提供', 1 => '選填', 2 => '必填');
$notes = array('position_id' => '從「職位管理」的職位中選擇', 'role_id' => '由注冊人自選普通用戶角色；不提供時使用默認角色');
?>
<div class="easyui-layout" fit="true" border="false">
    <div region="north" border="false" split="true" style="height:330px;overflow:auto;">
        <form class="pm-box" id="<?php echo $uid; ?>_form" onsubmit="return false;">
            <h4>注冊設置</h4>
            <table class="pm-detail" style="max-width:860px;">
                <tr><th>用戶注冊功能</th><td>
                    <label class="pm-radio"><input type="radio" name="enabled" value="1"<?php echo $set['enabled'] ? ' checked="checked"' : ''; ?>/>啟用</label>
                    <label class="pm-radio"><input type="radio" name="enabled" value="0"<?php echo $set['enabled'] ? '' : ' checked="checked"'; ?>/>關閉</label>
                    <span class="pm-note">啟用後登錄頁會出現「立即注冊」入口；關閉後注冊頁不可用。</span></td></tr>
                <tr><th>注冊審核</th><td>
                    <label class="pm-radio"><input type="radio" name="audit" value="1"<?php echo $set['audit'] ? ' checked="checked"' : ''; ?>/>需管理員審核後才能登錄</label>
                    <label class="pm-radio"><input type="radio" name="audit" value="0"<?php echo $set['audit'] ? '' : ' checked="checked"'; ?>/>注冊後可直接登錄</label></td></tr>
                <tr><th>默認角色</th><td>
                    <select name="role_id" style="width:160px;margin:0;">
                        <?php foreach ($roles as $r): ?><option value="<?php echo (int)$r['id']; ?>"<?php echo (int)$r['id'] === $set['role_id'] ? ' selected="selected"' : ''; ?>><?php echo h($r['name']); ?></option><?php endforeach; ?>
                    </select>
                    <span class="pm-note">注冊用戶的角色（只能是普通用戶角色），新用戶初始積分 <?php echo CREDIT_INIT_MIN; ?> 分鐘。</span></td></tr>
                <tr><th>注冊信息</th><td>
                    <table class="pm-reg-fields">
                        <tr><td>用戶名</td><td><select disabled="disabled"><option>必填</option></select></td><td class="pm-note">自動檢測是否與已有帳號重複，重複時建議可用的用戶名</td></tr>
                        <tr><td>密碼</td><td><select disabled="disabled"><option>必填</option></select></td><td class="pm-note">6-64 位，需輸入兩次</td></tr>
                        <?php foreach ($defs as $k => $d): ?>
                        <tr><td><?php echo h($d[0]); ?></td><td>
                            <select name="f_<?php echo $k; ?>">
                                <?php foreach ($modes as $mv => $mt): ?><option value="<?php echo $mv; ?>"<?php echo $set['fields'][$k] === $mv ? ' selected="selected"' : ''; ?>><?php echo $mt; ?></option><?php endforeach; ?>
                            </select></td><td class="pm-note"><?php echo isset($notes[$k]) ? h($notes[$k]) : ''; ?></td></tr>
                        <?php endforeach; ?>
                    </table></td></tr>
                <tr><th></th><td><a class="easyui-linkbutton" icon="icon-ok" id="<?php echo $uid; ?>_save">保存設置</a>
                    <a href="register.php" target="_blank" style="margin-left:14px;">打開注冊頁</a></td></tr>
            </table>
        </form>
    </div>
    <div region="center" border="false" title="待審核的注冊申請">
        <div id="<?php echo $uid; ?>_tb" style="padding:3px 6px;">
            <a class="easyui-linkbutton" icon="icon-ok" plain="true" id="<?php echo $uid; ?>_pass">通過</a>
            <a class="easyui-linkbutton" icon="icon-no" plain="true" id="<?php echo $uid; ?>_reject">拒絕並刪除</a>
            <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">刷新</a>
            <span class="pm-note">通過後用戶即可登錄；已通過的用戶在「用戶管理」中維護。</span>
        </div>
        <table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
    </div>
</div>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg';
    PM.grid(dg, {
        url: 'api/reg.php?act=pending', singleSelect: false,
        columns: [[
            {field: 'ck', checkbox: true},
            {field: 'username', title: '用戶名', width: 120, align: 'center'},
            {field: 'real_name', title: '姓名', width: 110, align: 'center'},
            {field: 'role_name', title: '角色', width: 100, align: 'center'},
            {field: 'position_name', title: '職位', width: 100, align: 'center'},
            {field: 'company', title: '公司名稱', width: 180},
            {field: 'phone', title: '電話', width: 120, align: 'center'},
            {field: 'email', title: '郵箱', width: 180},
            {field: 'ip', title: '注冊 IP', width: 120, align: 'center'},
            {field: 'created_at', title: '注冊時間', width: 140, align: 'center'}
        ]]
    });
    function act(name, text) {
        var rows = $(dg).datagrid('getChecked');
        if (!rows.length) return PM.alert('請先勾選注冊申請');
        PM.confirm('確定要' + text + '勾選的 ' + rows.length + ' 個注冊申請嗎？', function(){
            PM.post('api/reg.php?act=' + name, {ids: $.map(rows, function(r){ return r.id; }).join(',')}, function(){
                $(dg).datagrid('clearSelections').datagrid('clearChecked').datagrid('reload');
                PM.ping();
            });
        });
    }
    $(U + '_pass').click(function(){ act('approve', '通過'); });
    $(U + '_reject').click(function(){ act('reject', '拒絕並刪除'); });
    $(U + '_reload').click(function(){ PM.reload(dg); });
    $(U + '_save').click(function(){ PM.post('api/reg.php?act=save', $(U + '_form').serialize()); });
});
</script>
