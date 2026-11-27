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
                    <option value="0">擬稿</option><option value="1">審批中</option><option value="2">已通過</option><option value="3">已駁回</option>
                </select>
            </span>
            <span style="white-space:nowrap;"><label style="width:auto;"><input type="checkbox" name="latest" value="1" checked style="margin:0 3px 0 0;vertical-align:middle;"/>只顯示最新版本</label></span>
            <?php endif; ?>
        </div>
        <div class="search-toolbar">
            <span style="float:left;">
                <?php if ($scope === 'mine'): ?>
                <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">新建文件</a>
                <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit">修改</a>
                <a class="easyui-linkbutton" icon="icon-ok" plain="true" id="<?php echo $uid; ?>_submit">提交審批</a>
                <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_cover">編輯封面</a>
                <a class="easyui-linkbutton" icon="icon-redo" plain="true" id="<?php echo $uid; ?>_upgrade">駁回升版</a>
                <?php endif; ?>
                <?php if ($scope === 'todo'): ?>
                <a class="easyui-linkbutton" icon="icon-ok" plain="true" id="<?php echo $uid; ?>_view">審批</a>
                <?php else: ?>
                <a class="easyui-linkbutton" icon="icon-search" plain="true" id="<?php echo $uid; ?>_view">查看</a>
                <?php endif; ?>
                <a class="easyui-linkbutton" icon="icon-print" plain="true" id="<?php echo $uid; ?>_print">打印封面</a>
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
        url: 'api/doc.php?act=list&scope=<?php echo h($scope); ?>', queryParams: {latest: <?php echo $scope === 'todo' ? 0 : 1; ?>},
        columns: [[
            {field: 'doc_no', title: '文件編號', width: 170, align: 'center'},
            {field: 'version', title: '版本', width: 45, align: 'center'},
            {field: 'ver_text', title: '版本說明', width: 70, align: 'center'},
            {field: 'type_code', title: '審批類型', width: 90, align: 'center', formatter: function(v, row){ return v ? PM.esc(v + ' ' + (row.type_name || '')) : ''; }},
            {field: 'disc_code', title: '專業分類', width: 90, align: 'center', formatter: function(v, row){ return v ? PM.esc(v + ' ' + (row.disc_name || '')) : ''; }},
            {field: 'title', title: '文件標題', width: 260, formatter: function(v, row){ return '<a href="javascript:void(0)" onclick="PM.viewDoc(' + parseInt(row.id, 10) + ')">' + PM.esc(v) + '</a>'; }},
            {field: 'project_name', title: '所屬工程', width: 160},
            {field: 'creator_name', title: '提交人', width: 90, align: 'center'},
            {field: 'status', title: '狀態', width: 70, align: 'center', formatter: PM.docStatus},
            {field: 'cur_role', title: '當前審批角色', width: 100, align: 'center'},
            {field: 'file_name', title: '附件', width: 160, formatter: function(v, row){ return v ? '<a href="api/doc.php?act=download&id=' + parseInt(row.id, 10) + '">' + PM.esc(v) + '</a>' : ''; }},
            {field: 'submitted_at', title: '提交時間', width: 150, align: 'center'}
        ]],
        onDblClickRow: function(i, row){ PM.viewDoc(row.id); }
    });

    function edit(row) {
        $.getJSON('api/doc.php', {act: 'form_opts'}, function(o){
            if (!o || !o.ok) return PM.alert(o && o.msg ? o.msg : '讀取失敗');
            if (!o.has_project) return PM.alert('您尚未指定所屬工程，不能新建文件，請聯繫管理員。');
            if (!row && !o.projects.length) return PM.alert('您的角色不是所屬工程審批流程的提交角色（第 1 步），不能新建文件。');
            var projects = $.map(o.projects, function(p){ return {value: String(p.id), text: p.name_en + ' ' + p.name + '（' + p.step_text + '）'}; });
            var types = $.map(o.types, function(t){ return {value: t.code, text: t.code + ' ' + t.name}; });
            var discs = [{value: '', text: '請選擇'}].concat($.map(o.discs, function(t){ return {value: t.code, text: t.code + ' ' + t.name}; }));
            var fields = row ? [
                {name: 'doc_no', label: '文件編號', type: 'static'}
            ] : [
                {name: 'project_id', label: '所屬工程', type: 'select', options: projects, required: true, hint: '括號內為該工程的文件審批流程'},
                {name: 'type_code', label: '審批類型', type: 'select', options: types, required: true, hint: '保存後自動生成文件編號：工程英文名-審批類型-三位流水號，擬稿即占用編號'}
            ];
            fields.push(
                {name: 'disc_code', label: '專業分類', type: 'select', options: discs, required: true},
                {name: 'title', label: '文件標題', required: true},
                {name: 'content', label: '文件說明', type: 'textarea', rows: 5},
                {name: 'file', label: '附件', type: 'file', hint: (row && row.file_name ? '當前附件：' + row.file_name + '；重新選擇將替換。' : '') + '支持 pdf、office、dwg、圖片、壓縮包等，最大 50MB'}
            );
            PM.form({
                title: row ? '修改文件' : '新建文件', width: 640, data: row || {},
                url: 'api/doc.php?act=save', extra: {id: row ? row.id : 0}, fields: fields,
                buttons: [
                    {text: '保存擬稿', iconCls: 'icon-save'},
                    {text: '保存並提交審批', iconCls: 'icon-ok', extra: {submit: 1}}
                ],
                onOk: function(res){ PM.reloadDocs(); if (!row && res && res.doc_no) PM.tip('文件編號：' + res.doc_no); }
            });
        });
    }
    function coverEdit(r) {
        $.getJSON('api/doc.php', {act: 'get', id: r.id}, function(res){
            if (!res || !res.ok) return PM.alert(res && res.msg ? res.msg : '讀取失敗');
            var c = res.doc.cover || {subject: res.doc.title, contents: res.doc.content || '', issue_date: PM.today ? PM.today() : ''};
            var g = function(k, opts){ return PM.checkGroup($.map(opts, function(x){ return {value: x, text: x}; }), c[k] ? String(c[k]).split(',') : []); };
            var cat = g('category', ['土木 Civil', '建築 Architectural', '機電 E&M', '屋宇設備 BS', '一般 General', '其它 Others']);
            var cmp = g('compliance', ['與規範相同 Same as specified', '未有規範 Not specified', '等同 Equivalent', '替代 Alternative']);
            var att = g('attach', ['圖紙 Drawings', '文件 Documents', '技術資料 Technical data', '產品目錄 Catalogues', '計算書 Calculations', '樣板 Samples', '其它 Others']);
            var cg = function(name, label, grp){ return {name: name, label: label, type: 'custom', render: grp.render, getValue: grp.getValue}; };
            PM.form({
                title: '編輯封面：' + r.doc_no, width: 720, data: c,
                url: 'api/doc.php?act=cover_save', extra: {id: r.id},
                fields: [
                    {name: 'to', label: '致 To'}, {name: 'from', label: '由 From'}, {name: 'cc', label: '副本 C.C.'},
                    {name: 'issue_date', label: '發出日期', type: 'date'},
                    {name: 'subject', label: '事由 Subject', required: true},
                    cg('category', '專業 Category', cat),
                    {name: 'brand', label: '品牌 Brand'}, {name: 'model', label: '型號 Model'}, {name: 'origin', label: '產地 Origin'},
                    {name: 'other_info', label: '其它資料'},
                    cg('compliance', '與規範比較', cmp),
                    {name: 'spec_ref', label: '規範條款'},
                    {name: 'quantity', label: '數量 Quantity'}, {name: 'delivery', label: '交貨日期'},
                    cg('attach', '附件 Attachments', att),
                    {name: 'contents', label: '內容 Contents', type: 'textarea', rows: 4},
                    {name: 'statement', label: '申報聲明', type: 'textarea', rows: 2},
                    {name: 'signer', label: '申報人'}, {name: 'sign_date', label: '申報日期', type: 'date'},
                    {name: 'remarks', label: '備註', type: 'textarea', rows: 2}
                ],
                buttons: [
                    {text: '保存封面', iconCls: 'icon-save'},
                ],
                onOk: function(){ PM.reloadDocs(); }
            });
        });
    }
    function editable(r) {
        if (r.status == 3) { PM.alert('已駁回的文件須先「駁回升版」，再修改新版本並重新申報'); return false; }
        if (r.status != 0) { PM.alert('只有擬稿狀態的文件可以操作'); return false; }
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
    $(U + '_cover').click(function(){ var r = PM.selected(dg); if (r && editable(r)) coverEdit(r); });
    $(U + '_print').click(function(){ var r = PM.selected(dg); if (r) window.open('cover.php?id=' + parseInt(r.id, 10)); });
    $(U + '_upgrade').click(function(){
        var r = PM.selected(dg);
        if (!r) return;
        if (r.status != 3) return PM.alert('只有被駁回的文件才能升版');
        if (r.is_latest != 1) return PM.alert('該文件已升版，請在最新版本上操作');
        PM.confirm('確定將「' + r.doc_no + '」升版嗎？將保留原版本記錄，並生成下一版本（擬稿）供修改後重新申報。', function(){
            PM.post('api/doc.php?act=upgrade', {id: r.id}, PM.reloadDocs);
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
