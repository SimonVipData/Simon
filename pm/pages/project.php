<?php /** 工程信息 */ ?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <div class="search-toolbar">
        <span style="float:left;">
            <?php if ($admin): ?>
            <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">新增工程</a>
            <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit">修改</a>
            <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
            <?php endif; ?>
            <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">刷新</a>
        </span>
    </div>
</div>
<table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg';
    PM.grid(dg, {
        url: 'api/project.php?act=list',
        columns: [[
            {field: 'code', title: '工程編號', width: 110, align: 'center'},
            {field: 'name', title: '工程名稱', width: 240},
            {field: 'location', title: '工程地點', width: 140},
            {field: 'owner_unit', title: '業主單位', width: 140},
            {field: 'contractor_unit', title: '承建商', width: 140},
            {field: 'start_date', title: '開工日期', width: 100, align: 'center'},
            {field: 'end_date', title: '竣工日期', width: 100, align: 'center'},
            {field: 'status', title: '狀態', width: 80, align: 'center'},
            {field: 'remark', title: '備註', width: 200}
        ]]
    });
    function edit(row) {
        PM.form({
            title: row ? '修改工程' : '新增工程', width: 580, data: row || {status: '進行中'},
            url: 'api/project.php?act=save', extra: {id: row ? row.id : 0},
            fields: [
                {name: 'code', label: '工程編號', required: true},
                {name: 'name', label: '工程名稱', required: true},
                {name: 'location', label: '工程地點'},
                {name: 'owner_unit', label: '業主單位'},
                {name: 'contractor_unit', label: '承建商'},
                {name: 'start_date', label: '開工日期', type: 'date'},
                {name: 'end_date', label: '竣工日期', type: 'date'},
                {name: 'status', label: '狀態', type: 'select', options: [{value: '籌備中', text: '籌備中'}, {value: '進行中', text: '進行中'}, {value: '已竣工', text: '已竣工'}, {value: '已暫停', text: '已暫停'}]},
                {name: 'remark', label: '備註', type: 'textarea', rows: 3}
            ],
            onOk: function(){ PM.reload(dg); }
        });
    }
    $(U + '_add').click(function(){ edit(null); });
    $(U + '_edit').click(function(){ var r = PM.selected(dg); if (r) edit(r); });
    $(U + '_del').click(function(){
        var r = PM.selected(dg);
        if (r) PM.confirm('確定刪除工程「' + r.name + '」嗎？', function(){
            PM.post('api/project.php?act=delete', {id: r.id}, function(){ PM.reload(dg); });
        });
    });
    $(U + '_reload').click(function(){ PM.reload(dg); });
});
</script>
