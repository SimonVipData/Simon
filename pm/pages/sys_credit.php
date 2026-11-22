<?php /** 積分管理：積分 = 普通用戶可使用本系統的分鐘數 */ ?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <form onsubmit="return datagridSearch(this,'<?php echo $uid; ?>_dg');">
        <div class="search-content">
            <span><label>關鍵字：</label><input type="text" name="keyword" style="width:160px;" placeholder="帳號 / 姓名 / 單位"/></span>
            <span style="color:#888;">積分表示普通用戶可使用本系統的分鐘數：新用戶初始 30 分鐘，登錄後隨時間減少，積分為 0 時強制退出。管理員帳號不受限制。</span>
        </div>
        <div class="search-toolbar">
            <span style="float:left;">
                <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">充值積分</a>
                <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_sub">扣減積分</a>
                <a class="easyui-linkbutton" icon="icon-search" plain="true" id="<?php echo $uid; ?>_log">積分記錄</a>
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
    PM.grid(dg, {
        url: 'api/user.php?act=list&type=user',
        columns: [[
            {field: 'username', title: '帳號', width: 120, align: 'center'},
            {field: 'real_name', title: '姓名', width: 120, align: 'center'},
            {field: 'role_name', title: '角色', width: 110, align: 'center'},
            {field: 'position_name', title: '職位', width: 100, align: 'center'},
            {field: 'company', title: '單位', width: 160},
            {field: 'credit_min', title: '剩餘積分(分鐘)', width: 110, align: 'center', formatter: function(v){ return '<b style="color:' + (v > 0 ? '#2e8b57' : '#d93025') + '">' + PM.esc(v) + '</b>'; }},
            {field: 'status', title: '狀態', width: 60, align: 'center', formatter: function(v){ return v == 1 ? '<span style="color:#2e8b57">啟用</span>' : '<span style="color:#d93025">停用</span>'; }},
            {field: 'active_at', title: '最近在線', width: 150, align: 'center'},
            {field: 'last_login', title: '最後登錄', width: 150, align: 'center'}
        ]]
    });
    function adjust(op) {
        var r = PM.selected(dg);
        if (!r) return;
        PM.form({
            title: (op === 'add' ? '充值積分' : '扣減積分') + ' - ' + r.username, width: 520, url: 'api/credit.php?act=adjust', extra: {id: r.id, op: op},
            data: {cur: r.credit_min + ' 分鐘', minutes: 30},
            fields: [
                {name: 'cur', label: '當前積分', type: 'static'},
                {name: 'minutes', label: op === 'add' ? '充值分鐘數' : '扣減分鐘數', type: 'number', required: true},
                {name: 'remark', label: '備註'}
            ],
            onOk: function(){ PM.reload(dg); }
        });
    }
    $(U + '_add').click(function(){ adjust('add'); });
    $(U + '_sub').click(function(){ adjust('sub'); });
    $(U + '_log').click(function(){
        var r = $(dg).datagrid('getSelected');
        var $d = $('<div/>').appendTo('body'), $g = $('<table/>').appendTo($d);
        $d.dialog({title: '積分記錄' + (r ? ' - ' + r.username : '（全部用戶）'), width: 820, height: 460, modal: true, onClose: function(){ $d.dialog('destroy'); }});
        PM.grid($g, {
            url: 'api/credit.php?act=logs', queryParams: {target_id: r ? r.id : 0},
            columns: [[
                {field: 'created_at', title: '時間', width: 150, align: 'center'},
                {field: 'target_name', title: '用戶', width: 110, align: 'center'},
                {field: 'delta', title: '變動(分鐘)', width: 90, align: 'center', formatter: function(v){ return '<b style="color:' + (v >= 0 ? '#2e8b57' : '#d93025') + '">' + (v > 0 ? '+' : '') + PM.esc(v) + '</b>'; }},
                {field: 'balance', title: '變動後積分', width: 90, align: 'center'},
                {field: 'operator_name', title: '操作人', width: 110, align: 'center'},
                {field: 'remark', title: '備註', width: 200}
            ]]
        });
    });
    $(U + '_reload').click(function(){ PM.reload(dg); });
});
</script>
