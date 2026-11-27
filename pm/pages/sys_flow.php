<?php
/** 流程模板：新建 / 修改工程時可選模板自動填充工程文件審批流程 */
$roles = array();
foreach (db_rows("SELECT id, name FROM pm_roles WHERE type = 'user' ORDER BY sort, id") as $r) $roles[] = array('id' => (int)$r['id'], 'name' => $r['name']);
?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <div class="search-toolbar">
        <span style="float:left;">
            <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">新增流程模板</a>
            <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit">修改</a>
            <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
            <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">刷新</a>
        </span>
        <span style="float:right;color:#888;line-height:28px;padding-right:8px;">每個工程的審批流程在「工程信息」中單獨設置，模板只用於快速填充步驟</span>
    </div>
</div>
<table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg', roles = <?php echo js_json($roles); ?>;
    PM.grid(dg, {
        url: 'api/flow.php?act=list', pagination: false,
        columns: [[
            {field: 'name', title: '模板名稱', width: 200},
            {field: 'step_text', title: '審批步驟（角色）', width: 420, formatter: function(v){ return '<span style="color:#1F5FA8;font-weight:bold;">' + PM.esc(v) + '</span>'; }},
            {field: 'sort', title: '排序', width: 60, align: 'center'},
            {field: 'remark', title: '備註', width: 220}
        ]]
    });
    function edit(row) {
        var ed = PM.stepEditor(roles, $.map(row ? row.steps : [], function(s){ return {id: parseInt(s.role_id, 10), name: s.role_name}; }));
        PM.form({
            title: row ? '修改流程模板' : '新增流程模板', width: 600, data: row || {sort: 0},
            url: 'api/flow.php?act=save', extra: {id: row ? row.id : 0},
            fields: [
                {name: 'name', label: '模板名稱', required: true, placeholder: '例如：承建商→設計→業主'},
                {name: 'role_ids', label: '審批步驟', type: 'custom', required: true, render: ed.render, getValue: ed.getValue},
                {name: 'sort', label: '排序'},
                {name: 'remark', label: '備註', type: 'textarea', rows: 2}
            ],
            onOk: function(){ PM.reload(dg); }
        });
    }
    $(U + '_add').click(function(){ edit(null); });
    $(U + '_edit').click(function(){ var r = PM.selected(dg); if (r) edit(r); });
    $(U + '_del').click(function(){
        var r = PM.selected(dg);
        if (r) PM.confirm('確定刪除模板「' + r.name + '」嗎？（不影響已設置的工程流程）', function(){ PM.post('api/flow.php?act=delete', {id: r.id}, function(){ PM.reload(dg); }); });
    });
    $(U + '_reload').click(function(){ PM.reload(dg); });
});
</script>
