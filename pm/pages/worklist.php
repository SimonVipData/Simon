<?php
/** 工作清單：所有用戶可按項目登記工作；跟進人員上傳閉合附件閉合；創建人、admin、xadmin 可刪除 */
?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <form onsubmit="return datagridSearch(this,'<?php echo $uid; ?>_dg');">
        <div class="search-content">
            <span><label>項目：</label><select name="project_id" id="<?php echo $uid; ?>_proj" style="width:200px;"><option value="">全部項目</option></select></span>
            <span><label>關鍵字：</label><input type="text" name="keyword" style="width:160px;" placeholder="分類 / 描述"/></span>
            <span><label>狀態：</label><select name="state" style="width:100px;"><option value="">全部</option><option value="open">未閉合</option><option value="closed">已閉合</option></select></span>
            <span><label>範圍：</label><select name="mine" style="width:120px;"><option value="">全部記錄</option><option value="created">我創建的</option><option value="follow">我可跟進的</option></select></span>
            <span><label>創建時間：</label><input type="date" name="created_from" style="width:135px;"/> -- <input type="date" name="created_to" style="width:135px;"/></span>
            <span><label>閉合時間：</label><input type="date" name="closed_from" style="width:135px;"/> -- <input type="date" name="closed_to" style="width:135px;"/></span>
        </div>
        <div class="search-toolbar">
            <span style="float:left;">
                <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">新增</a>
                <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit">修改</a>
                <a class="easyui-linkbutton" icon="icon-ok" plain="true" id="<?php echo $uid; ?>_close">跟進閉合</a>
                <a class="easyui-linkbutton" icon="icon-search" plain="true" id="<?php echo $uid; ?>_view">查看</a>
                <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
                <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">刷新</a>
            </span>
            <span style="float:right;"><button class="btn btn-primary btn-small" type="submit">查詢</button>&nbsp;<button class="btn btn-small clear" type="button">清空</button></span>
        </div>
    </form>
</div>
<table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg', api = 'api/worklist.php';
    function brief(v){ v = v || ''; return PM.esc(v.length > 60 ? v.substr(0, 60) + '…' : v); }
    PM.grid(dg, {
        url: api + '?act=list', rownumbers: true,
        columns: [[
            {field: 'creator_name', title: '創建人', width: 80, align: 'center'},
            {field: 'project_name', title: '項目', width: 150, formatter: function(v, row){ return v ? PM.esc((row.project_code ? row.project_code + ' ' : '') + v) : ''; }},
            {field: 'category', title: '分類', width: 90, align: 'center'},
            {field: 'content', title: '工作描述', width: 240, formatter: brief},
            {field: 'files_desc', title: '描述附件', width: 150, formatter: PM.fileLinks},
            {field: 'created_at', title: '創建時間', width: 140, align: 'center'},
            {field: 'follower_names', title: '跟進人員', width: 130},
            {field: 'close_desc', title: '閉合描述', width: 200, formatter: brief},
            {field: 'files_close', title: '閉合附件', width: 150, formatter: PM.fileLinks},
            {field: 'closed_at', title: '閉合時間', width: 140, align: 'center', formatter: function(v){ return v ? PM.esc(v) : '<span style="color:#d93025">未閉合</span>'; }},
            {field: 'closer_name', title: '閉合人', width: 80, align: 'center', formatter: function(v, row){ return row.close_user_id > 0 ? PM.esc(v || '') : ''; }}
        ]],
        onDblClickRow: function(i, row){ view(row); }
    });
    function opts(cb) {
        $.getJSON(api, {act: 'opts'}, function(o){ if (o && o.ok) cb(o); else PM.alert(o && o.msg ? o.msg : '讀取失敗'); });
    }
    function projText(p){ return (p.code ? p.code + ' ' : '') + p.name; }
    opts(function(o){ $.each(o.projects, function(i, p){ $('<option/>').val(p.id).text(projText(p)).appendTo(U + '_proj'); }); });
    function edit(row) {
        if (row && !row.is_owner) return PM.alert('只有創建人可以修改工作描述');
        opts(function(o){
            if (!o.projects.length) return PM.alert('您沒有所屬項目，不能登記工作');
            var picker, $cat, $proj, $fw, sel = row ? $.map(row.follower_ids, String) : [];
            function drawPicker() {
                if (picker) sel = picker.getValue() ? picker.getValue().split(',') : [];
                var pid = parseInt($proj.val(), 10) || 0;
                var us = $.grep(o.users, function(u){ return u.all || $.inArray(pid, u.pids) >= 0; });
                var ok = $.map(us, function(u){ return String(u.id); });
                picker = PM.userPicker(us, $.grep(sel, function(id){ return $.inArray(id, ok) >= 0; }));
                $fw.empty(); picker.render($fw);
            }
            PM.form({title: (row ? '修改' : '新增') + '工作', width: 680, data: row || {}, url: api + '?act=save', extra: {id: row ? row.id : 0},
                fields: [
                    {name: 'project_id', label: '項目', type: 'custom', required: true, render: function($el){
                        $proj = $('<select style="width:100%;"/>').appendTo($el);
                        if (!row || !row.project_id) $('<option value="">請選擇項目</option>').appendTo($proj);
                        $.each(o.projects, function(i, p){ $('<option/>').val(p.id).text(projText(p)).appendTo($proj); });
                        if (row && row.project_id) $proj.val(String(row.project_id));
                        else if (o.projects.length === 1) $proj.val(String(o.projects[0].id));
                        $proj.change(drawPicker);
                    }, getValue: function(){ return $proj.val() || ''; }},
                    {name: 'category', label: '分類', type: 'custom', render: function($el){
                        var lid = U.substr(1) + '_cats';
                        $cat = $('<input type="text" maxlength="50" autocomplete="off" placeholder="可輸入或從已有分類中選擇"/>').attr('list', lid).val(row ? row.category : '').appendTo($el);
                        var $dl = $('<datalist/>').attr('id', lid).appendTo($el);
                        $.each(o.cats, function(i, c){ $('<option/>').val(c).appendTo($dl); });
                    }, getValue: function(){ return $.trim($cat.val()); }},
                    {name: 'content', label: '工作描述', type: 'textarea', rows: 5, required: true},
                    {name: 'desc', label: '描述附件', type: 'files', files: row ? row.files_desc : []},
                    {name: 'followers', label: '跟進人員', type: 'custom', render: function($el){ $fw = $('<div/>').appendTo($el); drawPicker(); },
                        getValue: function(){ return picker ? picker.getValue() : ''; }, hint: '只列出該項目的用戶；不勾選則該項目所有用戶都可以跟進閉合'}
                ],
                onOk: function(){ PM.reload(dg); }});
        });
    }
    function closeRow(row) {
        if (!row.can_close) return PM.alert('您不是該記錄的跟進人員');
        PM.form({title: '跟進閉合 - ' + (row.category || '工作'), width: 620, data: row, url: api + '?act=close', extra: {id: row.id},
            fields: [
                {name: 'content', label: '工作描述', type: 'static'},
                {name: 'close_desc', label: '閉合描述', type: 'textarea', rows: 5},
                {name: 'close', label: '閉合附件', type: 'files', files: row.files_close, hint: '上傳閉合附件即閉合記錄，上傳時間為閉合時間；可勾選刪除後重新上傳'}
            ],
            onOk: function(){ PM.reload(dg); }});
    }
    function view(row) {
        PM.detail('工作詳情', [
            {name: 'creator_name', label: '創建人'},
            {name: 'project_name', label: '項目', html: function(r){ return r.project_name ? PM.esc((r.project_code ? r.project_code + ' ' : '') + r.project_name) : ''; }},
            {name: 'category', label: '分類'}, {name: 'content', label: '工作描述'},
            {name: 'desc', label: '描述附件', type: 'files', files: row.files_desc}, {name: 'created_at', label: '創建時間'},
            {name: 'follower_names', label: '跟進人員'}, {name: 'close_desc', label: '閉合描述'},
            {name: 'close', label: '閉合附件', type: 'files', files: row.files_close},
            {name: 'closed_at', label: '閉合時間', html: function(r){ return r.closed_at ? PM.esc(r.closed_at) : '未閉合'; }},
            {name: 'closer_name', label: '閉合人', html: function(r){ return r.close_user_id > 0 ? PM.esc(r.closer_name || '') : ''; }}
        ], row);
    }
    $(U + '_add').click(function(){ edit(null); });
    $(U + '_edit').click(function(){ var r = PM.selected(dg); if (r) edit(r); });
    $(U + '_close').click(function(){ var r = PM.selected(dg); if (r) closeRow(r); });
    $(U + '_view').click(function(){ var r = PM.selected(dg); if (r) view(r); });
    $(U + '_del').click(function(){
        var r = PM.selected(dg);
        if (!r) return;
        if (!r.can_delete) return PM.alert('只有創建人、admin、xadmin 才可以刪除記錄');
        PM.confirm('確定刪除所選工作記錄嗎？', function(){ PM.post(api + '?act=delete', {id: r.id}, function(){ PM.reload(dg); }); });
    });
    $(U + '_reload').click(function(){ PM.reload(dg); });
});
</script>
