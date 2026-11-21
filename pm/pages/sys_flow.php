<?php
/** 文件審批設置：為工程的文件設定各角色之間的審批先後順序 */
$jf = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$projects = array();
foreach (db_rows('SELECT id, code, name FROM pm_projects ORDER BY id') as $p) {
    $projects[] = array('value' => (string)$p['id'], 'text' => $p['code'] . ' ' . $p['name']);
}
$roles = array();
foreach (db_rows("SELECT id, name FROM pm_roles WHERE type = 'user' ORDER BY sort, id") as $r) {
    $roles[] = array('id' => (int)$r['id'], 'name' => $r['name']);
}
?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <div class="search-toolbar">
        <span style="float:left;">
            <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">新增文件審批設置</a>
            <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit">修改 / 調整審批順序</a>
            <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
            <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">刷新</a>
        </span>
        <span style="float:right;color:#888;line-height:28px;padding-right:8px;">第 1 步角色負責提交文件，其後角色按先後順序逐級審批</span>
    </div>
</div>
<table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg';
    var projects = <?php echo json_encode($projects, $jf); ?>, roles = <?php echo json_encode($roles, $jf); ?>;
    PM.grid(dg, {
        url: 'api/flow.php?act=list',
        columns: [[
            {field: 'project_name', title: '所屬工程', width: 200},
            {field: 'name', title: '文件名稱', width: 180},
            {field: 'doc_type', title: '文件類別', width: 100, align: 'center'},
            {field: 'step_text', title: '審批先後順序（角色）', width: 360, formatter: function(v){ return '<span style="color:#1F5FA8;font-weight:bold;">' + PM.esc(v) + '</span>'; }},
            {field: 'doc_count', title: '已用文件數', width: 80, align: 'center'},
            {field: 'status', title: '狀態', width: 60, align: 'center', formatter: function(v){ return v == 1 ? '<span style="color:#2e8b57">啟用</span>' : '<span style="color:#d93025">停用</span>'; }},
            {field: 'remark', title: '備註', width: 200},
            {field: 'updated_at', title: '更新時間', width: 150, align: 'center'}
        ]]
    });

    function edit(row) {
        if (!projects.length) return PM.alert('請先在「工程信息」中創建工程');
        var steps = [];
        $.each(row ? row.steps : [], function(i, s){ steps.push({id: parseInt(s.role_id, 10), name: s.role_name}); });
        var $ul, $sel;
        function draw() {
            $ul.empty();
            $.each(steps, function(i, s){
                var $li = $('<li/>').appendTo($ul);
                $('<span class="pm-step-no"/>').text('第 ' + (i + 1) + ' 步（' + (i === 0 ? '提交' : '審批') + '）').appendTo($li);
                $('<span/>').text(s.name).appendTo($li);
                var $ops = $('<span class="pm-step-ops"/>').appendTo($li);
                if (i > 0) $('<a>上移</a>').click(function(){ steps.splice(i - 1, 0, steps.splice(i, 1)[0]); draw(); }).appendTo($ops);
                if (i < steps.length - 1) $('<a>下移</a>').click(function(){ steps.splice(i + 1, 0, steps.splice(i, 1)[0]); draw(); }).appendTo($ops);
                $('<a style="color:#d93025">移除</a>').click(function(){ steps.splice(i, 1); draw(); }).appendTo($ops);
            });
            if (!steps.length) $('<li style="color:#999;border-style:dashed;background:#fff;">尚未添加角色</li>').appendTo($ul);
            $sel.empty();
            $.each(roles, function(i, r){
                var used = $.grep(steps, function(s){ return s.id === r.id; }).length > 0;
                if (!used) $('<option/>').val(r.id).text(r.name).appendTo($sel);
            });
        }
        PM.form({
            title: row ? '修改文件審批設置' : '新增文件審批設置', width: 600, data: row || {status: 1, doc_type: '施工文件'},
            url: 'api/flow.php?act=save', extra: {id: row ? row.id : 0},
            fields: [
                {name: 'project_id', label: '所屬工程', type: 'select', options: projects, required: true},
                {name: 'name', label: '文件名稱', required: true, placeholder: '例如：施工方案報審'},
                {name: 'doc_type', label: '文件類別', placeholder: '例如：施工文件 / 材料報審 / 圖紙'},
                {name: 'role_ids', label: '審批順序', type: 'custom', required: true,
                    render: function($el){
                        $ul = $('<ul class="pm-steps"/>').appendTo($el);
                        $sel = $('<select style="width:200px;height:28px;margin:0 6px 0 0;padding:3px 6px;"/>').appendTo($el);
                        $('<a class="btn btn-small btn-primary">添加到末尾</a>').click(function(){
                            var id = parseInt($sel.val(), 10);
                            var r = $.grep(roles, function(x){ return x.id === id; })[0];
                            if (r) { steps.push({id: r.id, name: r.name}); draw(); }
                        }).appendTo($el);
                        $('<div class="pm-form-hint"/>').text('第 1 步為提交角色，之後按列表順序逐級審批；可用上移/下移調整先後。').appendTo($el);
                        draw();
                    },
                    getValue: function(){ return $.map(steps, function(s){ return s.id; }).join(','); }},
                {name: 'status', label: '狀態', type: 'select', options: [{value: '1', text: '啟用'}, {value: '0', text: '停用'}]},
                {name: 'remark', label: '備註', type: 'textarea', rows: 2}
            ],
            onOk: function(){ PM.reload(dg); }
        });
    }
    $(U + '_add').click(function(){ edit(null); });
    $(U + '_edit').click(function(){ var r = PM.selected(dg); if (r) edit(r); });
    $(U + '_del').click(function(){
        var r = PM.selected(dg);
        if (r) PM.confirm('確定刪除「' + r.name + '」的審批設置嗎？', function(){
            PM.post('api/flow.php?act=delete', {id: r.id}, function(){ PM.reload(dg); });
        });
    });
    $(U + '_reload').click(function(){ PM.reload(dg); });
});
</script>
