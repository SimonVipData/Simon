<?php
/** 工程論壇：主題列表 */
$boards = db_rows('SELECT id, name, remark FROM pm_forum_boards ORDER BY sort, id');
?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <form onsubmit="return datagridSearch(this,'<?php echo $uid; ?>_dg');">
        <div class="search-content">
            <span><label>版塊：</label>
                <select name="board_id" style="width:160px;" id="<?php echo $uid; ?>_board"><option value="">全部版塊</option>
                    <?php foreach ($boards as $b): ?><option value="<?php echo (int)$b['id']; ?>" title="<?php echo h($b['remark']); ?>"><?php echo h($b['name']); ?></option><?php endforeach; ?>
                </select></span>
            <span><label>關鍵字：</label><input type="text" name="keyword" style="width:160px;" placeholder="標題 / 內容"/></span>
        </div>
        <div class="search-toolbar">
            <span style="float:left;">
                <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">發表主題</a>
                <a class="easyui-linkbutton" icon="icon-search" plain="true" id="<?php echo $uid; ?>_view">查看 / 回覆</a>
                <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit">修改</a>
                <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
                <?php if ($admin): ?>
                <a class="easyui-linkbutton" icon="icon-tip" plain="true" id="<?php echo $uid; ?>_top">置頂 / 取消置頂</a>
                <a class="easyui-linkbutton" icon="icon-no" plain="true" id="<?php echo $uid; ?>_lock">鎖定 / 解鎖</a>
                <?php endif; ?>
            </span>
            <span style="float:right;"><button class="btn btn-primary btn-small" type="submit">查詢</button>&nbsp;<button class="btn btn-small clear" type="button">清空</button></span>
        </div>
    </form>
</div>
<table id="<?php echo $uid; ?>_dg" class="pm-forum-grid" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg';
    var boards = <?php echo js_json(array_map(function ($b) { return array('value' => (string)$b['id'], 'text' => $b['name']); }, $boards)); ?>;
    PM.grid(dg, {
        url: 'api/forum.php?act=list',
        columns: [[
            {field: 'title', title: '主題', width: 380, formatter: function(v, row){
                return (row.is_top == 1 ? '<span style="color:#d93025">[置頂]</span> ' : '') + (row.is_locked == 1 ? '<span style="color:#888">[鎖定]</span> ' : '') + PM.esc(v); }},
            {field: 'pay_view', title: '所需積分', width: 120, align: 'center', formatter: function(v, row){
                var a = [];
                if (row.pay_view > 0) a.push('<span style="color:' + (row.need_view ? '#d93025' : '#888') + '">查看 ' + parseInt(row.pay_view, 10) + '</span>');
                if (row.pay_file > 0) a.push('<span style="color:' + (row.need_file ? '#d93025' : '#888') + '">附件 ' + parseInt(row.pay_file, 10) + '</span>');
                return a.length ? a.join(' / ') : '<span style="color:#888">免費</span>'; }},
            {field: 'board_name', title: '版塊', width: 110, align: 'center'},
            {field: 'creator_name', title: '作者', width: 100, align: 'center'},
            {field: 'replies', title: '回覆', width: 50, align: 'center'},
            {field: 'views', title: '瀏覽', width: 50, align: 'center'},
            {field: 'last_reply_user', title: '最後回覆', width: 130, align: 'center'},
            {field: 'last_reply_at', title: '最後回覆時間', width: 140, align: 'center'},
            {field: 'created_at', title: '發表時間', width: 140, align: 'center'}
        ]],
        onDblClickRow: function(i, row){ view(row); }
    });
    $(U + '_board').change(function(){ $(this).closest('form').submit(); });
    function view(row) {
        PM.dialog(row.title, 'page.php?name=forum_thread&id=' + row.id, {width: 900, height: 600, onClose: function(){ $(this).dialog('destroy'); PM.reload(dg); }});
    }
    function form(init, id, canPrice) {
        if (!boards.length) return PM.alert('尚未設置論壇版塊，請聯繫管理員');
        PM.form({title: id ? '修改主題' : '發表主題', width: 680, data: init, url: 'api/forum.php?act=save', extra: {id: id || 0}, okText: '發表',
            fields: [
                {name: 'board_id', label: '版塊', type: 'select', options: boards, required: true},
                {name: 'title', label: '標題', required: true},
                {name: 'content', label: '內容', type: 'textarea', rows: 10, required: true},
                {name: 'att', label: '附件', type: 'files', files: init.files || []}
            ].concat(canPrice ? [
                {name: 'pay_view', label: '查看需付積分', type: 'number', value: 0, hint: '其他用戶需支付該積分後才能查看貼子內容和回覆；0 為免費'},
                {name: 'pay_file', label: '下載需付積分', type: 'number', value: 0, hint: '其他用戶需支付該積分後才能下載附件；0 為免費。積分全數轉給您，每人每項只付一次（1 積分 = 1 分鐘）'}
            ] : []),
            onOk: function(){ PM.reload(dg); }});
    }
    $(U + '_add').click(function(){ form({board_id: $(U + '_board').val()}, 0, <?php echo $admin ? 'false' : 'true'; ?>); });
    $(U + '_view').click(function(){ var r = PM.selected(dg); if (r) view(r); });
    $(U + '_edit').click(function(){
        var r = PM.selected(dg);
        if (!r) return;
        if (!r.can_write) return PM.alert('只能修改自己發表的主題');
        $.getJSON('api/forum.php', {act: 'get', id: r.id}, function(res){ if (res && res.ok) form($.extend({}, res.thread, {files: res.files}), r.id, res.thread.can_price); });
    });
    $(U + '_del').click(function(){
        var r = PM.selected(dg);
        if (!r) return;
        if (!r.can_write) return PM.alert('只能刪除自己發表的主題');
        PM.confirm('確定刪除主題「' + r.title + '」及其全部回覆嗎？', function(){ PM.post('api/forum.php?act=delete', {id: r.id}, function(){ PM.reload(dg); }); });
    });
    $.each(['top', 'lock'], function(i, a){
        $(U + '_' + a).click(function(){ var r = PM.selected(dg); if (r) PM.post('api/forum.php?act=' + a, {id: r.id}, function(){ PM.reload(dg); }); });
    });
});
</script>
