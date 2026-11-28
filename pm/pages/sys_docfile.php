<?php
/** 附件管理（管理員）：查找、下載、替換工程審批文件的附件 */
$projects = db_rows('SELECT id, code, name FROM pm_projects ORDER BY id DESC');
?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <form onsubmit="return datagridSearch(this,'<?php echo $uid; ?>_dg');">
        <div class="search-content">
            <span><label>所屬工程：</label>
                <select name="project_id" style="width:200px;">
                    <option value="">全部工程</option>
                    <?php foreach ($projects as $p): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo h($p['code'] . ' ' . $p['name']); ?></option><?php endforeach; ?>
                </select>
            </span>
            <span><label>關鍵字：</label><input type="text" name="keyword" style="width:180px;" placeholder="文件編號 / 標題 / 附件名"/></span>
            <span><label>上傳人：</label><input type="text" name="uploader" style="width:110px;" placeholder="帳號 / 姓名"/></span>
            <span><label>類別：</label>
                <select name="src" style="width:110px;"><option value="">全部</option><option value="main">提交附件</option><option value="reply">回覆附件</option></select>
            </span>
            <span><label>上傳時間：</label><input type="date" name="start" style="width:135px;"/> -- <input type="date" name="end" style="width:135px;"/></span>
        </div>
        <div class="search-toolbar">
            <span style="float:left;">
                <a class="easyui-linkbutton" icon="icon-redo" plain="true" id="<?php echo $uid; ?>_down">下載</a>
                <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_replace">替換附件</a>
                <a class="easyui-linkbutton" icon="icon-search" plain="true" id="<?php echo $uid; ?>_log">替換記錄</a>
                <a class="easyui-linkbutton" icon="icon-search" plain="true" id="<?php echo $uid; ?>_view">查看文件</a>
                <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">刷新</a>
            </span>
            <span style="float:right;"><button class="btn btn-primary btn-small" type="submit">查詢</button>&nbsp;<button class="btn btn-small clear" type="button">清空</button></span>
        </div>
    </form>
</div>
<table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg';
    function url(r) { return 'api/docfile.php?act=download&src=' + r.src + '&id=' + parseInt(r.ref_id, 10); }
    function size(v) { v = parseInt(v, 10) || 0; return v >= 1048576 ? (v / 1048576).toFixed(1) + ' MB' : (v / 1024).toFixed(1) + ' KB'; }
    PM.grid(dg, {
        url: 'api/docfile.php?act=list',
        columns: [[
            {field: 'src', title: '類別', width: 70, align: 'center', formatter: function(v){ return v === 'main' ? '提交附件' : '<span style="color:#2e6da4">回覆附件</span>'; }},
            {field: 'doc_no', title: '文件編號', width: 160, align: 'center'},
            {field: 'title', title: '文件標題', width: 200},
            {field: 'project_code', title: '工程編號', width: 140, align: 'center'},
            {field: 'role_name', title: '角色', width: 90, align: 'center'},
            {field: 'file_name', title: '附件名稱', width: 300, formatter: function(v, row){ return '<a href="' + url(row) + '">' + PM.esc(v) + '</a>'; }},
            {field: 'file_size', title: '大小', width: 75, align: 'right', formatter: size},
            {field: 'uploader', title: '上傳人', width: 90, align: 'center'},
            {field: 'up_at', title: '上傳時間', width: 140, align: 'center'},
            {field: 'replaced', title: '最近替換', width: 180, align: 'center'}
        ]],
        onDblClickRow: function(i, row){ window.location.href = url(row); }
    });
    $(U + '_reload').click(function(){ PM.reload(dg); });
    $(U + '_down').click(function(){ var r = PM.selected(dg); if (r) window.location.href = url(r); });
    $(U + '_view').click(function(){ var r = PM.selected(dg); if (r) PM.viewDoc(r.doc_id); });
    $(U + '_replace').click(function(){
        var r = PM.selected(dg);
        if (!r) return;
        PM.form({
            title: '替換附件 - ' + r.doc_no, width: 620, url: 'api/docfile.php?act=replace', extra: {src: r.src, id: r.ref_id},
            data: {doc: r.doc_no + ' ' + r.title, cur: r.file_name + '（' + size(r.file_size) + '）'},
            fields: [
                {name: 'doc', label: '審批文件', type: 'static'},
                {name: 'cur', label: '當前附件', type: 'static'},
                {name: 'file', label: '新文件', type: 'file', required: true, hint: '替換後保留原文件名和存放目錄（後綴按新文件），舊文件留底備查，並記錄替換人和時間'}
            ],
            onOk: function(){ PM.reload(dg); }
        });
    });
    $(U + '_log').click(function(){
        var r = PM.selected(dg);
        if (!r) return;
        var $d = $('<div/>').appendTo('body'), $g = $('<table/>').appendTo($d);
        $d.dialog({title: '替換記錄 - ' + r.file_name, width: 820, height: 400, modal: true, onClose: function(){ $d.dialog('destroy'); }});
        PM.grid($g, {
            url: 'api/docfile.php?act=logs', queryParams: {src: r.src, id: r.ref_id}, pagination: false,
            columns: [[
                {field: 'created_at', title: '替換時間', width: 140, align: 'center'},
                {field: 'user_name', title: '替換人', width: 90, align: 'center'},
                {field: 'old_name', title: '原附件', width: 220},
                {field: 'upload_name', title: '上傳的文件', width: 200},
                {field: 'new_size', title: '新文件大小', width: 90, align: 'right', formatter: size}
            ]]
        });
    });
});
</script>
