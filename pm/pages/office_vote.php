<?php /** 投票：發起或參與投票 */ ?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <div class="search-toolbar">
        <span style="float:left;">
            <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">發起投票</a>
            <a class="easyui-linkbutton" icon="icon-search" plain="true" id="<?php echo $uid; ?>_view">參與投票 / 查看結果</a>
            <a class="easyui-linkbutton" icon="icon-no" plain="true" id="<?php echo $uid; ?>_close">結束投票</a>
            <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
            <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">刷新</a>
        </span>
    </div>
</div>
<table id="<?php echo $uid; ?>_dg" class="pm-vote-grid" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg';
    PM.grid(dg, {
        url: 'api/vote.php?act=list',
        columns: [[
            {field: 'title', title: '投票主題', width: 320},
            {field: 'multi', title: '類型', width: 60, align: 'center', formatter: function(v){ return v == 1 ? '多選' : '單選'; }},
            {field: 'anonymous', title: '方式', width: 60, align: 'center', formatter: function(v){ return v == 1 ? '匿名' : '記名'; }},
            {field: 'open', title: '狀態', width: 70, align: 'center', formatter: function(v){ return v ? '<b style="color:#2e8b57">進行中</b>' : '<span style="color:#888">已結束</span>'; }},
            {field: 'voted', title: '我的參與', width: 80, align: 'center', formatter: function(v){ return v > 0 ? '已投票' : '<b style="color:#e68a00">未投票</b>'; }},
            {field: 'voters', title: '投票人數', width: 70, align: 'center'},
            {field: 'end_date', title: '截止日期', width: 100, align: 'center'},
            {field: 'creator_name', title: '發起人', width: 100, align: 'center'},
            {field: 'created_at', title: '發起時間', width: 150, align: 'center'}
        ]],
        onDblClickRow: function(i, row){ view(row); }
    });
    function view(row) { PM.dialog('投票 - ' + row.title, 'page.php?name=vote_view&id=' + row.id, {width: 760, height: 480}); }
    $(U + '_add').click(function(){
        PM.form({
            title: '發起投票', width: 620, url: 'api/vote.php?act=save', okText: '發起',
            fields: [
                {name: 'title', label: '投票主題', required: true},
                {name: 'content', label: '說明', type: 'textarea', rows: 3},
                {name: 'options', label: '選項', type: 'textarea', rows: 6, required: true, hint: '每行一個選項，至少 2 個'},
                {name: 'multi', label: '類型', type: 'select', options: [{value: '0', text: '單選'}, {value: '1', text: '多選'}]},
                {name: 'anonymous', label: '方式', type: 'select', options: [{value: '0', text: '記名投票'}, {value: '1', text: '匿名投票'}]},
                {name: 'end_date', label: '截止日期', type: 'date', hint: '留空表示不限期'}
            ],
            onOk: function(){ PM.reload(dg); }
        });
    });
    $(U + '_view').click(function(){ var r = PM.selected(dg); if (r) view(r); });
    $(U + '_close').click(function(){
        var r = PM.selected(dg);
        if (!r) return;
        if (!r.can_write) return PM.alert('只有發起人或管理員可以結束投票');
        PM.confirm('確定結束投票「' + r.title + '」嗎？', function(){ PM.post('api/vote.php?act=close', {id: r.id}, function(){ PM.reload(dg); }); });
    });
    $(U + '_del').click(function(){
        var r = PM.selected(dg);
        if (!r) return;
        if (!r.can_write) return PM.alert('只有發起人或管理員可以刪除投票');
        PM.confirm('確定刪除投票「' + r.title + '」嗎？', function(){ PM.post('api/vote.php?act=delete', {id: r.id}, function(){ PM.reload(dg); }); });
    });
    $(U + '_reload').click(function(){ PM.reload(dg); });
});
</script>
