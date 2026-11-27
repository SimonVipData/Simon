<?php
/** 工程信息：只有管理員可新增工程；新增時必須填寫工程中英文名並設置工程文件審批流程 */
$roles = array();
foreach (db_rows("SELECT id, name FROM pm_roles WHERE type = 'user' ORDER BY sort, id") as $r) $roles[] = array('id' => (int)$r['id'], 'name' => $r['name']);
$noProject = !$admin && !my_project_ids();
?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <div class="search-toolbar">
        <span style="float:left;">
            <?php if ($admin): ?>
            <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">新增工程</a>
            <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit">修改 / 審批流程</a>
            <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
            <?php endif; ?>
            <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">刷新</a>
        </span>
        <?php if ($noProject): ?><span style="float:right;color:#d93025;line-height:28px;padding-right:8px;">您尚未指定所屬工程，不能查閱工程文件及相關信息，請聯繫管理員</span><?php endif; ?>
    </div>
</div>
<table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg', roles = <?php echo js_json($roles); ?>;
    PM.grid(dg, {
        url: 'api/project.php?act=list',
        columns: [[
            {field: 'code', title: '工程編號', width: 170, align: 'center'},
            {field: 'name', title: '工程中文名', width: 220},
            {field: 'name_en', title: '工程英文名', width: 150},
            {field: 'step_text', title: '文件審批流程', width: 300, formatter: function(v){ return v ? '<span style="color:#1F5FA8;font-weight:bold;">' + PM.esc(v) + '</span>' : '<span style="color:#d93025">未設置</span>'; }},
            {field: 'location', title: '工程地點', width: 120},
            {field: 'owner_unit', title: '業主單位', width: 120},
            {field: 'contractor_unit', title: '承建商', width: 120},
            {field: 'start_date', title: '開工日期', width: 90, align: 'center'},
            {field: 'end_date', title: '竣工日期', width: 90, align: 'center'},
            {field: 'status', title: '狀態', width: 70, align: 'center'},
            <?php if ($admin): ?>{field: 'user_count', title: '所屬用戶', width: 70, align: 'center'},<?php endif; ?>
            {field: 'remark', title: '備註', width: 160}
        ]]
    });
    function edit(row) {
        var ed = PM.stepEditor(roles, $.map(row ? row.steps : [], function(s){ return {id: parseInt(s.role_id, 10), name: s.role_name}; }));
        $.getJSON('api/flow.php', {act: 'options'}, function(tpls){
            var tplOpts = [{value: '', text: '（不使用模板，手動逐條配置）'}].concat($.map(tpls || [], function(t){ return {value: String(t.id), text: t.name + '：' + t.step_text}; }));
            var $dlg = PM.form({
                title: row ? '修改工程' : '新增工程', width: 680, data: row || {status: '進行中'},
                url: 'api/project.php?act=save', extra: {id: row ? row.id : 0},
                fields: [
                    {name: 'code', label: '工程編號', type: 'static', value: '保存後自動生成（工程英文名-當年月-三位年度流水號）'},
                    {name: 'name', label: '工程中文名', required: true},
                    {name: 'name_en', label: '工程英文名', required: true, placeholder: '例如：MBRC', hint: '只能包含英文字母、數字、空格或橫線；用於工程編號及文件編號'},
                    {name: 'tpl', label: '流程模板', type: 'select', options: tplOpts, hint: '選擇模板可自動填充下面的審批步驟，填充後仍可逐條修改'},
                    {name: 'role_ids', label: '文件審批流程', type: 'custom', required: true, render: ed.render, getValue: ed.getValue},
                    {name: 'location', label: '工程地點'},
                    {name: 'owner_unit', label: '業主單位'},
                    {name: 'contractor_unit', label: '承建商'},
                    {name: 'start_date', label: '開工日期', type: 'date'},
                    {name: 'end_date', label: '竣工日期', type: 'date'},
                    {name: 'status', label: '狀態', type: 'select', options: [{value: '籌備中', text: '籌備中'}, {value: '進行中', text: '進行中'}, {value: '已竣工', text: '已竣工'}, {value: '已暫停', text: '已暫停'}]},
                    {name: 'remark', label: '備註', type: 'textarea', rows: 2}
                ],
                onOk: function(res){ PM.reload(dg); if (!row && res && res.code) PM.alert('工程已創建，工程編號：' + res.code); }
            });
            $dlg.find('select[name="tpl"]').change(function(){
                var id = this.value, t = $.grep(tpls || [], function(x){ return String(x.id) === id; })[0];
                if (t) ed.set($.map(t.steps, function(s){ return {id: parseInt(s.role_id, 10), name: s.role_name}; }));
            });
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
