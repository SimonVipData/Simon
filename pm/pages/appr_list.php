<?php
/** 內部審批列表：詢價 / 費用 / 事項（$name 為 appr_inquiry | appr_expense | appr_matter） */
$type = substr($name, 5);
$conf = array(
    'inquiry' => array('詢價審批', '供應商 / 分包商', '報價金額(MOP)', '詢價類別', array('材料', '設備', '分包工程', '勞務', '其他')),
    'expense' => array('費用審批', '收款單位', '申請金額(MOP)', '費用類別', array('材料費', '人工費', '機械費', '分包款', '辦公費', '差旅費', '其他')),
    'matter' => array('事項審批', '', '', '事項類別', array('請示', '報告', '請假', '用印', '採購', '其他')),
);
$c = $conf[$type];
$projects = db_rows('SELECT p.id, p.code, p.name FROM pm_projects p WHERE ' . project_scope_sql('p.id') . ' ORDER BY p.id');
$users = user_options($user['id']);
?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <form onsubmit="return datagridSearch(this,'<?php echo $uid; ?>_dg');">
        <div class="search-content">
            <span><label>範圍：</label>
                <select name="scope" style="width:120px;" id="<?php echo $uid; ?>_scope">
                    <option value="mine">我的申請</option><option value="todo">待我審批</option><option value="done">我已審批</option>
                    <?php if ($admin): ?><option value="all">全部（管理員）</option><?php endif; ?>
                </select></span>
            <span><label>狀態：</label>
                <select name="status" style="width:100px;"><option value="">全部</option><option value="0">草稿</option><option value="1">審批中</option><option value="2">已通過</option><option value="3">已退回</option></select></span>
            <span><label>關鍵字：</label><input type="text" name="keyword" style="width:150px;" placeholder="編號 / 標題"/></span>
        </div>
        <div class="search-toolbar">
            <span style="float:left;">
                <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">新建<?php echo $c[0]; ?></a>
                <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit">修改</a>
                <a class="easyui-linkbutton" icon="icon-redo" plain="true" id="<?php echo $uid; ?>_submit">提交</a>
                <a class="easyui-linkbutton" icon="icon-search" plain="true" id="<?php echo $uid; ?>_view">查看 / 審批</a>
                <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
            </span>
            <span style="float:right;"><button class="btn btn-primary btn-small" type="submit">查詢</button>&nbsp;<button class="btn btn-small clear" type="button">清空</button></span>
        </div>
    </form>
</div>
<table id="<?php echo $uid; ?>_dg" class="pm-appr-grid" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg', type = <?php echo js_json($type); ?>, c = <?php echo js_json($c); ?>;
    var users = <?php echo js_json($users); ?>;
    var projects = [{value: '0', text: '（不關聯工程）'}].concat(<?php echo js_json(array_map(function ($p) { return array('value' => (string)$p['id'], 'text' => $p['code'] . ' ' . $p['name']); }, $projects)); ?>);
    var cats = $.map(c[4], function(x){ return {value: x, text: x}; });
    var cols = [
        {field: 'appr_no', title: '審批編號', width: 120, align: 'center'},
        {field: 'title', title: '標題', width: 240},
        {field: 'category', title: c[3], width: 90, align: 'center'}
    ];
    if (type !== 'matter') {
        cols.push({field: 'party', title: c[1], width: 150});
        cols.push({field: 'amount', title: c[2], width: 110, align: 'right'});
    }
    cols.push({field: 'project_name', title: '所屬工程', width: 160},
        {field: 'status', title: '狀態', width: 70, align: 'center', formatter: PM.docStatus},
        {field: 'cur_name', title: '當前審批人', width: 130, align: 'center'},
        {field: 'flow_text', title: '審批順序', width: 260},
        {field: 'creator_name', title: '申請人', width: 90, align: 'center'},
        {field: 'submitted_at', title: '提交時間', width: 140, align: 'center'});
    PM.grid(dg, {url: 'api/appr.php?act=list&type=' + type, queryParams: {scope: 'mine'}, columns: [cols], onDblClickRow: function(i, row){ PM.viewAppr(row.id); }});
    $(U + '_scope').change(function(){ $(this).closest('form').submit(); });
    function form(init, id) {
        var picker = PM.userPicker(users, init.approvers || [], true);
        var fields = [
            {name: 'title', label: '標題', required: true},
            {name: 'project_id', label: '所屬工程', type: 'select', options: projects},
            {name: 'category', label: c[3], type: 'select', options: cats}
        ];
        if (type !== 'matter') {
            fields.push({name: 'party', label: c[1]});
            fields.push({name: 'amount', label: c[2], type: 'number', required: true});
        }
        fields.push({name: 'content', label: type === 'matter' ? '事項內容' : '說明', type: 'textarea', rows: 5, required: type === 'matter'},
            {name: 'approver_ids', label: '審批人', type: 'custom', required: true, render: picker.render, getValue: picker.getValue, hint: '按勾選的先後順序逐級審批'},
            {name: 'att', label: '附件', type: 'files', files: init.files || []});
        PM.form({title: (id ? '修改' : '新建') + c[0], width: 660, data: init, url: 'api/appr.php?act=save', extra: {id: id || 0, type: type}, fields: fields,
            buttons: [{text: '保存並提交', iconCls: 'icon-ok', extra: {submit: 1}}, {text: '保存草稿', iconCls: 'icon-save', extra: {submit: 0}}],
            onOk: function(){ PM.reload(dg); PM.ping(); }});
    }
    function editable(r) {
        if (!r) return false;
        if (r.user_id != <?php echo (int)$user['id']; ?>) { PM.alert('只能操作自己的申請'); return false; }
        if (r.status != 0 && r.status != 3) { PM.alert('只有草稿或已退回的審批單可以修改 / 提交'); return false; }
        return true;
    }
    $(U + '_add').click(function(){ form({}); });
    $(U + '_edit').click(function(){
        var r = PM.selected(dg);
        if (!editable(r)) return;
        $.getJSON('api/appr.php', {act: 'get', id: r.id}, function(res){
            if (res && res.ok) form($.extend({}, res.appr, {approvers: res.approver_ids, files: res.files}), r.id);
        });
    });
    $(U + '_submit').click(function(){
        var r = PM.selected(dg);
        if (editable(r)) PM.confirm('確定提交「' + r.title + '」進行審批嗎？', function(){ PM.post('api/appr.php?act=submit', {id: r.id}, function(){ PM.reload(dg); PM.ping(); }); });
    });
    $(U + '_view').click(function(){ var r = PM.selected(dg); if (r) PM.viewAppr(r.id); });
    $(U + '_del').click(function(){
        var r = PM.selected(dg);
        if (r) PM.confirm('確定刪除審批單「' + r.title + '」嗎？', function(){ PM.post('api/appr.php?act=delete', {id: r.id}, function(){ PM.reload(dg); PM.ping(); }); });
    });
});
</script>
