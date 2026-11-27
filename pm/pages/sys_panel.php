<?php
/** 控制面板：系統功能開關 */
require_once __DIR__ . '/../inc/panel.php';
$own = panel_get('cal_own_only');
?>
<div class="easyui-tabs" fit="true" border="false" id="<?php echo $uid; ?>_tabs">
<div title="系統開關" style="overflow:auto;">
<form class="pm-box" id="<?php echo $uid; ?>_form" onsubmit="return false;">
    <h4>工程日曆</h4>
    <table class="pm-detail" style="max-width:860px;">
        <tr><th>默認只顯示「自己創建的事件」</th><td>
            <label class="pm-radio"><input type="radio" name="cal_own_only" value="1"<?php echo $own ? ' checked="checked"' : ''; ?>/>開啟</label>
            <label class="pm-radio"><input type="radio" name="cal_own_only" value="0"<?php echo $own ? '' : ' checked="checked"'; ?>/>關閉</label>
            <div class="pm-note" style="margin:4px 0 0 0;">開啟：用戶在「工程日曆」只能查看自己創建的事件；關閉：可查看所有用戶創建的事件。管理員始終可查看全部事件。</div></td></tr>
        <tr><th></th><td><a class="easyui-linkbutton" icon="icon-ok" id="<?php echo $uid; ?>_save">保存設置</a></td></tr>
    </table>
</form>
</div>
<?php foreach (array('type' => '審批類型', 'disc' => '專業分類') as $k => $t): ?>
<div title="工程設置 - 文件分類：<?php echo $t; ?>">
    <div id="<?php echo $uid . '_' . $k; ?>_tb" class="search-div"><div class="search-toolbar"><span style="float:left;">
        <a class="easyui-linkbutton" icon="icon-add" plain="true" data-k="<?php echo $k; ?>" data-a="add">新增<?php echo $t; ?></a>
        <a class="easyui-linkbutton" icon="icon-edit" plain="true" data-k="<?php echo $k; ?>" data-a="edit">修改</a>
        <a class="easyui-linkbutton" icon="icon-remove" plain="true" data-k="<?php echo $k; ?>" data-a="del">刪除</a>
        <a class="easyui-linkbutton" icon="icon-up" plain="true" data-k="<?php echo $k; ?>" data-a="up">上移</a>
        <a class="easyui-linkbutton" icon="icon-down" plain="true" data-k="<?php echo $k; ?>" data-a="down">下移</a>
    </span><span style="float:right;color:#888;line-height:28px;padding-right:8px;">代碼用於文件編號（工程英文名-審批類型代碼-流水號）；已被文件使用的分類不能刪除</span></div></div>
    <table id="<?php echo $uid . '_' . $k; ?>_dg" toolbar="#<?php echo $uid . '_' . $k; ?>_tb"></table>
</div>
<?php endforeach; ?>
</div>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>';
    var names = {type: '審批類型', disc: '專業分類'};
    $.each(['type', 'disc'], function(i, k){
        var cols = [{field: 'code', title: '代碼', width: 90, align: 'center'}, {field: 'name', title: '名稱', width: 160}];
        if (k === 'type') cols.push({field: 'name_en', title: '英文名稱（封面標題）', width: 220});
        cols.push({field: 'remark', title: '說明', width: 220}, {field: 'doc_count', title: '已用文件數', width: 80, align: 'center'});
        PM.grid(U + '_' + k + '_dg', {url: 'api/dict.php?act=list&kind=' + k, pagination: false, columns: [cols]});
    });
    function dictEdit(k, row) {
        var fields = [{name: 'code', label: '代碼', required: true, placeholder: k === 'type' ? '例如：MAT' : '例如：FS'}, {name: 'name', label: '名稱', required: true}];
        if (k === 'type') fields.push({name: 'name_en', label: '英文名稱', placeholder: '例如：Materials Approval Form', hint: '用於文件封面標題'});
        fields.push({name: 'remark', label: '說明', type: 'textarea', rows: 2});
        PM.form({title: (row ? '修改' : '新增') + names[k], width: 520, data: row || {}, url: 'api/dict.php?act=save&kind=' + k, extra: {id: row ? row.id : 0},
            fields: fields, onOk: function(){ PM.reload(U + '_' + k + '_dg'); }});
    }
    $(U + '_tabs').on('click', 'a[data-a]', function(){
        var k = $(this).data('k'), a = $(this).data('a'), dg = U + '_' + k + '_dg';
        if (a === 'add') return dictEdit(k, null);
        var r = PM.selected(dg);
        if (!r) return;
        if (a === 'edit') dictEdit(k, r);
        else if (a === 'del') PM.confirm('確定刪除「' + r.code + ' ' + r.name + '」嗎？', function(){ PM.post('api/dict.php?act=delete&kind=' + k, {id: r.id}, function(){ PM.reload(dg); }); });
        else PM.post('api/dict.php?act=move&kind=' + k, {id: r.id, dir: a}, function(){ $(dg).datagrid('reload'); });
    });
    $(U + '_save').click(function(){ PM.post('api/panel.php?act=save', $(U + '_form').serialize()); });
});
</script>
