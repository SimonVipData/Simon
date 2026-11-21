<?php
/** 工程文件列表：我的文件 / 待辦審批 / 已辦審批 / 全部文件 */
$scope = substr($name, 4);
?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <form onsubmit="return datagridSearch(this,'<?php echo $uid; ?>_dg');">
        <div class="search-content">
            <span><label>關鍵字：</label><input type="text" name="keyword" style="width:180px;" placeholder="文件編號 / 標題"/></span>
            <?php if ($scope !== 'todo'): ?>
            <span><label>狀態：</label>
                <select name="status" style="width:110px;">
                    <option value="">全部狀態</option>
                    <option value="0">草稿</option><option value="1">審批中</option><option value="2">已通過</option><option value="3">已退回</option>
                </select>
            </span>
            <?php endif; ?>
        </div>
        <div class="search-toolbar">
            <span style="float:left;">
                <?php if ($scope === 'mine'): ?>
                <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">新建文件</a>
                <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit">修改</a>
                <a class="easyui-linkbutton" icon="icon-ok" plain="true" id="<?php echo $uid; ?>_submit">提交審批</a>
                <?php endif; ?>
                <?php if ($scope === 'todo'): ?>
                <a class="easyui-linkbutton" icon="icon-ok" plain="true" id="<?php echo $uid; ?>_view">審批</a>
                <?php else: ?>
                <a class="easyui-linkbutton" icon="icon-search" plain="true" id="<?php echo $uid; ?>_view">查看</a>
                <?php endif; ?>
                <?php if ($scope === 'mine' || ($scope === 'all' && $admin)): ?>
                <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
                <?php endif; ?>
            </span>
            <span style="float:right;"><button class="btn btn-primary btn-small" type="submit">查詢</button>&nbsp;<button class="btn btn-small clear" type="button">清空</button></span>
        </div>
    </form>
</div>
<table id="<?php echo $uid; ?>_dg" class="pm-doc-grid" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg';
    PM.grid(dg, {
        url: 'api/doc.php?act=list&scope=<?php echo h($scope); ?>',
        columns: [[
            {field: 'doc_no', title: '文件編號', width: 140, align: 'center'},
            {field: 'title', title: '文件標題', width: 260, formatter: function(v, row){ return '<a href="javascript:void(0)" onclick="PM.viewDoc(' + parseInt(row.id, 10) + ')">' + PM.esc(v) + '</a>'; }},
            {field: 'project_name', title: '所屬工程', width: 180},
            {field: 'flow_name', title: '文件名稱/類別', width: 150},
            {field: 'creator_name', title: '提交人', width: 90, align: 'center'},
            {field: 'status', title: '狀態', width: 70, align: 'center', formatter: PM.docStatus},
            {field: 'cur_role', title: '當前審批角色', width: 100, align: 'center'},
            {field: 'file_name', title: '附件', width: 160, formatter: function(v, row){ return v ? '<a href="api/doc.php?act=download&id=' + parseInt(row.id, 10) + '">' + PM.esc(v) + '</a>' : ''; }},
            {field: 'submitted_at', title: '提交時間', width: 150, align: 'center'}
        ]],
        onDblClickRow: function(i, row){ PM.viewDoc(row.id); }
    });

    function edit(row) {
        $.getJSON('api/flow.php', {act: 'options'}, function(flows){
            if (!flows || !flows.length) return PM.alert('您的角色不是任何文件審批設置的提交角色（第 1 步），不能新建文件。');
            var opts = $.map(flows, function(f){ return {value: String(f.id), text: f.text + '（' + f.step_text + '）'}; });
            PM.form({
                title: row ? '修改文件' : '新建文件', width: 640, data: row || {},
                url: 'api/doc.php?act=save', extra: {id: row ? row.id : 0},
                fields: [
                    {name: 'flow_id', label: '文件類別', type: 'select', options: opts, required: true, hint: '括號內為管理員設定的審批先後順序'},
                    {name: 'title', label: '文件標題', required: true},
                    {name: 'content', label: '文件說明', type: 'textarea', rows: 5},
                    {name: 'file', label: '附件', type: 'file', hint: (row && row.file_name ? '當前附件：' + row.file_name + '；重新選擇將替換。' : '') + '支持 pdf、office、dwg、圖片、壓縮包等，最大 50MB'}
                ],
                buttons: [
                    {text: '保存草稿', iconCls: 'icon-save'},
                    {text: '保存並提交審批', iconCls: 'icon-ok', extra: {submit: 1}}
                ],
                onOk: function(){ PM.reloadDocs(); }
            });
        });
    }
    function editable(r) {
        if (r.status != 0 && r.status != 3) { PM.alert('只有草稿或已退回的文件可以操作'); return false; }
        return true;
    }
    $(U + '_add').click(function(){ edit(null); });
    $(U + '_edit').click(function(){
        var r = PM.selected(dg);
        if (!r || !editable(r)) return;
        // 取文件說明（列表不含正文）
        $.getJSON('api/doc.php', {act: 'get', id: r.id}, function(res){
            if (res && res.ok) edit(res.doc); else PM.alert(res && res.msg ? res.msg : '讀取失敗');
        });
    });
    $(U + '_submit').click(function(){
        var r = PM.selected(dg);
        if (r && editable(r)) PM.confirm('確定提交「' + r.title + '」進入審批嗎？', function(){
            PM.post('api/doc.php?act=submit', {id: r.id}, PM.reloadDocs);
        });
    });
    $(U + '_view').click(function(){ var r = PM.selected(dg); if (r) PM.viewDoc(r.id); });
    $(U + '_del').click(function(){
        var r = PM.selected(dg);
        if (r) PM.confirm('確定刪除文件「' + r.title + '」嗎？', function(){
            PM.post('api/doc.php?act=delete', {id: r.id}, PM.reloadDocs);
        });
    });
});
</script>
