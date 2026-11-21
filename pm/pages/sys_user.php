<?php
/** 用戶管理 */
$roles = db_rows('SELECT id, name, type FROM pm_roles ORDER BY sort, id');
?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <form onsubmit="return datagridSearch(this,'<?php echo $uid; ?>_dg');">
        <div class="search-content">
            <span><label>關鍵字：</label><input type="text" name="keyword" style="width:160px;" placeholder="帳號 / 姓名 / 單位"/></span>
            <span><label>角色：</label>
                <select name="role_id" style="width:140px;">
                    <option value="">全部角色</option>
                    <?php foreach ($roles as $r): ?><option value="<?php echo (int)$r['id']; ?>"><?php echo h($r['name']); ?></option><?php endforeach; ?>
                </select>
            </span>
        </div>
        <div class="search-toolbar">
            <span style="float:left;">
                <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">新增用戶</a>
                <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit">修改</a>
                <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
            </span>
            <span style="float:right;"><button class="btn btn-primary btn-small" type="submit">查詢</button>&nbsp;<button class="btn btn-small clear" type="button">清空</button></span>
        </div>
    </form>
</div>
<table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg';
    var roles = <?php echo json_encode(array_map(function ($r) {
        return array('value' => (string)$r['id'], 'text' => $r['name'] . ($r['type'] === 'admin' ? '（管理員）' : ''));
    }, $roles), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    PM.grid(dg, {
        url: 'api/user.php?act=list',
        columns: [[
            {field: 'username', title: '帳號', width: 110, align: 'center'},
            {field: 'real_name', title: '姓名', width: 110, align: 'center'},
            {field: 'role_name', title: '角色', width: 110, align: 'center'},
            {field: 'role_type', title: '類別', width: 80, align: 'center', formatter: function(v){ return v === 'admin' ? '<span style="color:#d93025">管理員</span>' : '普通用戶'; }},
            {field: 'company', title: '單位', width: 160},
            {field: 'phone', title: '電話', width: 110, align: 'center'},
            {field: 'email', title: '郵箱', width: 160},
            {field: 'status', title: '狀態', width: 60, align: 'center', formatter: function(v){ return v == 1 ? '<span style="color:#2e8b57">啟用</span>' : '<span style="color:#d93025">停用</span>'; }},
            {field: 'last_login', title: '最後登錄', width: 150, align: 'center'},
            {field: 'created_at', title: '創建時間', width: 150, align: 'center'}
        ]]
    });
    function edit(row) {
        PM.form({
            title: row ? '修改用戶' : '新增用戶', width: 560, data: row || {status: 1},
            url: 'api/user.php?act=save', extra: {id: row ? row.id : 0},
            fields: [
                {name: 'username', label: '帳號', required: true},
                {name: 'password', label: '密碼', type: 'password', required: !row, hint: row ? '留空表示不修改密碼' : '至少 6 位'},
                {name: 'real_name', label: '姓名'},
                {name: 'role_id', label: '角色', type: 'select', options: roles, required: true},
                {name: 'company', label: '單位'},
                {name: 'phone', label: '電話'},
                {name: 'email', label: '郵箱'},
                {name: 'status', label: '狀態', type: 'select', options: [{value: '1', text: '啟用'}, {value: '0', text: '停用'}]}
            ],
            onOk: function(){ PM.reload(dg); }
        });
    }
    $(U + '_add').click(function(){ edit(null); });
    $(U + '_edit').click(function(){ var r = PM.selected(dg); if (r) edit(r); });
    $(U + '_del').click(function(){
        var r = PM.selected(dg);
        if (r) PM.confirm('確定刪除用戶「' + r.username + '」嗎？', function(){
            PM.post('api/user.php?act=delete', {id: r.id}, function(){ PM.reload(dg); });
        });
    });
});
</script>
