<?php /** 角色管理 */ ?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <div class="search-toolbar">
        <span style="float:left;">
            <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">新增角色</a>
            <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit">修改</a>
            <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
            <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">刷新</a>
        </span>
    </div>
</div>
<table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg';
    PM.grid(dg, {
        url: 'api/role.php?act=list',
        columns: [[
            {field: 'name', title: '角色名稱', width: 160, align: 'center'},
            {field: 'type', title: '角色類別', width: 110, align: 'center', formatter: function(v){ return v === 'admin' ? '<span style="color:#d93025">管理員</span>' : '普通用戶'; }},
            {field: 'user_count', title: '用戶數', width: 70, align: 'center'},
            {field: 'sort', title: '排序', width: 60, align: 'center'},
            {field: 'remark', title: '備註', width: 320},
            {field: 'created_at', title: '創建時間', width: 150, align: 'center'}
        ]]
    });
    function edit(row) {
        PM.form({
            title: row ? '修改角色' : '新增角色', width: 520, data: row || {sort: 10, type: 'user'},
            url: 'api/role.php?act=save', extra: {id: row ? row.id : 0},
            fields: [
                {name: 'name', label: '角色名稱', required: true},
                {name: 'type', label: '角色類別', type: 'select', options: [{value: 'user', text: '普通用戶'}, {value: 'admin', text: '管理員'}]},
                {name: 'sort', label: '排序', type: 'number'},
                {name: 'remark', label: '備註', type: 'textarea', rows: 3}
            ],
            onOk: function(){ PM.reload(dg); }
        });
    }
    $(U + '_add').click(function(){ edit(null); });
    $(U + '_edit').click(function(){ var r = PM.selected(dg); if (r) edit(r); });
    $(U + '_del').click(function(){
        var r = PM.selected(dg);
        if (r) PM.confirm('確定刪除角色「' + r.name + '」嗎？', function(){
            PM.post('api/role.php?act=delete', {id: r.id}, function(){ PM.reload(dg); });
        });
    });
    $(U + '_reload').click(function(){ PM.reload(dg); });
});
</script>
