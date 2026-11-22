<?php
/** 內部郵件 */
$users = user_options($user['id']);
?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <form onsubmit="return datagridSearch(this,'<?php echo $uid; ?>_dg');">
        <div class="search-content">
            <span><label>郵箱：</label>
                <select name="box" style="width:120px;" id="<?php echo $uid; ?>_box"><option value="inbox">收件箱</option><option value="sent">已發送</option><option value="draft">草稿箱</option></select></span>
            <span><label>關鍵字：</label><input type="text" name="keyword" style="width:160px;" placeholder="主題 / 內容"/></span>
        </div>
        <div class="search-toolbar">
            <span style="float:left;">
                <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_new">寫郵件</a>
                <a class="easyui-linkbutton" icon="icon-search" plain="true" id="<?php echo $uid; ?>_view">查看</a>
                <a class="easyui-linkbutton" icon="icon-redo" plain="true" id="<?php echo $uid; ?>_reply">回覆</a>
                <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit">編輯草稿</a>
                <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
            </span>
            <span style="float:right;"><button class="btn btn-primary btn-small" type="submit">查詢</button></span>
        </div>
    </form>
</div>
<table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg', users = <?php echo js_json($users); ?>;
    function box(){ return $(U + '_box').val(); }
    function bold(v, row){ return row.is_read == 1 ? PM.esc(v) : '<b>' + PM.esc(v) + '</b>'; }
    PM.grid(dg, {
        url: 'api/mail.php?act=list', queryParams: {box: 'inbox'},
        columns: [[
            {field: 'is_read', title: '狀態', width: 60, align: 'center', formatter: function(v, row){ return row.status == 0 ? '草稿' : (v == 1 ? '已讀' : '<b style="color:#d93025">未讀</b>'); }},
            {field: 'subject', title: '主題', width: 320, formatter: bold},
            {field: 'from_name', title: '發件人', width: 110, align: 'center'},
            {field: 'to_names', title: '收件人', width: 220},
            {field: 'file_count', title: '附件', width: 50, align: 'center', formatter: function(v){ return v > 0 ? v + ' 個' : ''; }},
            {field: 'sent_at', title: '時間', width: 150, align: 'center'}
        ]],
        onDblClickRow: function(i, row){ view(row); }
    });
    $(U + '_box').change(function(){ $(this).closest('form').submit(); });
    function view(row) {
        PM.dialog('郵件詳情', 'page.php?name=mail_view&id=' + row.id, {width: 760, height: 480, onClose: function(){ $(this).dialog('destroy'); PM.reload(dg); }});
    }
    function compose(init, id) {
        var picker = PM.userPicker(users, init.to || []);
        PM.form({
            title: '寫郵件', width: 660, data: init, url: 'api/mail.php?act=send', extra: {id: id || 0},
            fields: [
                {name: 'to_ids', label: '收件人', type: 'custom', render: picker.render, getValue: picker.getValue},
                {name: 'subject', label: '主題', required: true},
                {name: 'content', label: '內容', type: 'textarea', rows: 9},
                {name: 'att', label: '附件', type: 'files', files: init.files || []}
            ],
            buttons: [{text: '發送', iconCls: 'icon-ok', extra: {draft: 0}}, {text: '存為草稿', iconCls: 'icon-save', extra: {draft: 1}}],
            onOk: function(){ PM.reload(dg); }
        });
    }
    $(U + '_new').click(function(){ compose({}); });
    $(U + '_view').click(function(){ var r = PM.selected(dg); if (r) view(r); });
    $(U + '_reply').click(function(){
        var r = PM.selected(dg);
        if (!r) return;
        if (box() !== 'inbox') return PM.alert('請在收件箱中選擇要回覆的郵件');
        $.getJSON('api/mail.php', {act: 'get', id: r.id}, function(res){
            if (res && res.ok) compose({to: [res.mail.user_id], subject: 'Re: ' + res.mail.subject, content: '\n\n------ 原郵件（' + res.mail.from_name + ' ' + res.mail.sent_at + '）------\n' + (res.mail.content || '')});
        });
    });
    $(U + '_edit').click(function(){
        var r = PM.selected(dg);
        if (!r) return;
        if (r.status != 0) return PM.alert('只有草稿箱中的郵件可以編輯');
        $.getJSON('api/mail.php', {act: 'get', id: r.id}, function(res){
            if (res && res.ok) compose({to: res.to_ids, subject: res.mail.subject, content: res.mail.content, files: res.files}, r.id);
        });
    });
    $(U + '_del').click(function(){
        var r = PM.selected(dg);
        if (r) PM.confirm('確定刪除郵件「' + r.subject + '」嗎？', function(){ PM.post('api/mail.php?act=delete', {id: r.id, box: box()}, function(){ PM.reload(dg); PM.ping(); }); });
    });
});
</script>
