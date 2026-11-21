<?php /** 登錄日志 */ ?>
<table id="<?php echo $uid; ?>_dg"></table>
<script type="text/javascript">
$(function(){
    PM.grid('#<?php echo $uid; ?>_dg', {
        url: 'api/me.php?act=logs',
        columns: [[
            {field: 'username', title: '帳號', width: 120, align: 'center'},
            {field: 'result', title: '結果', width: 70, align: 'center', formatter: function(v){ return '<span style="color:' + (v === '成功' ? '#2e8b57' : '#d93025') + '">' + PM.esc(v) + '</span>'; }},
            {field: 'ip', title: 'IP 地址', width: 130, align: 'center'},
            {field: 'created_at', title: '登錄時間', width: 150, align: 'center'},
            {field: 'user_agent', title: '瀏覽器', width: 420}
        ]]
    });
});
</script>
