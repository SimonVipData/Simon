<?php /** 數據庫管理：備份與恢復 */ ?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <div class="search-toolbar">
        <span style="float:left;">
            <a class="easyui-linkbutton" icon="icon-save" plain="true" id="<?php echo $uid; ?>_backup">立即備份</a>
            <a class="easyui-linkbutton" icon="icon-undo" plain="true" id="<?php echo $uid; ?>_restore">恢復所選備份</a>
            <a class="easyui-linkbutton" icon="icon-redo" plain="true" id="<?php echo $uid; ?>_upload">上傳備份文件並恢復</a>
            <a class="easyui-linkbutton" icon="icon-print" plain="true" id="<?php echo $uid; ?>_down">下載</a>
            <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
            <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">刷新</a>
        </span>
        <span style="float:right;color:#888;line-height:28px;padding-right:8px;">備份範圍：本程序全部數據表（不含已上傳的附件文件）</span>
    </div>
</div>
<table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg';
    PM.grid(dg, {
        url: 'api/db.php?act=list', pagination: false,
        columns: [[
            {field: 'name', title: '備份文件', width: 340},
            {field: 'size', title: '大小', width: 100, align: 'center', formatter: PM.fileSize},
            {field: 'time', title: '備份時間', width: 160, align: 'center'},
            {field: 'remark', title: '備註', width: 200, align: 'center'}
        ]]
    });
    function afterRestore() {
        $.messager.alert('提示', '數據恢復成功，頁面將重新載入。', 'info', function(){ window.location.reload(); });
    }
    $(U + '_backup').click(function(){
        PM.post('api/db.php?act=backup', {}, function(){ PM.reload(dg); });
    });
    $(U + '_restore').click(function(){
        var r = PM.selected(dg);
        if (r) PM.confirm('確定將數據庫恢復到備份「' + r.name + '」嗎？當前數據將被覆蓋（恢復前會自動備份當前數據）。', function(){
            PM.post('api/db.php?act=restore', {name: r.name}, afterRestore);
        });
    });
    $(U + '_upload').click(function(){
        PM.form({
            title: '上傳備份文件並恢復', width: 560, okText: '上傳並恢復', url: 'api/db.php?act=restore',
            fields: [{name: 'file', label: '備份文件', type: 'file', accept: '.sql', required: true, hint: '僅支持本程序生成的 .sql 備份文件；恢復將覆蓋當前數據（恢復前會自動備份）。'}],
            onOk: afterRestore
        });
    });
    $(U + '_down').click(function(){
        var r = PM.selected(dg);
        if (r) window.location.href = 'api/db.php?act=download&name=' + encodeURIComponent(r.name);
    });
    $(U + '_del').click(function(){
        var r = PM.selected(dg);
        if (r) PM.confirm('確定刪除備份「' + r.name + '」嗎？', function(){
            PM.post('api/db.php?act=delete', {name: r.name}, function(){ PM.reload(dg); });
        });
    });
    $(U + '_reload').click(function(){ PM.reload(dg); });
});
</script>
