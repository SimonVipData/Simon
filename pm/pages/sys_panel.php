<?php
/** 控制面板：系統功能開關 */
$own = panel_get('cal_own_only');
$radio = function ($k, $on = '開啟', $off = '關閉') {
    $v = panel_get($k);
    return '<label class="pm-radio"><input type="radio" name="' . $k . '" value="1"' . ($v ? ' checked="checked"' : '') . '/>' . $on . '</label>'
        . '<label class="pm-radio"><input type="radio" name="' . $k . '" value="0"' . ($v ? '' : ' checked="checked"') . '/>' . $off . '</label>';
};
$menuGroups = array(
    '工程文件' => array('project', 'doc_mine', 'doc_todo', 'doc_done', 'doc_all'),
    '個人辦公' => array('office_remind', 'office_chat', 'office_mail', 'office_vote'),
    '工程實務' => array('note', 'todo', 'worklist', 'work_calendar', 'customer', 'material', 'sub', 'labor'),
    '內部審批' => array('appr_inquiry', 'appr_expense', 'appr_matter'),
    '網盤資料' => array('disk_personal', 'disk_share', 'disk_company'),
    '工程輔助' => array('weather'),
    '工程論壇' => array('forum'),
);
$menuNames = panel_menu_defs();
?>
<div class="easyui-tabs" fit="true" border="false" id="<?php echo $uid; ?>_tabs">
<div title="系統開關" style="overflow:auto;">
<form class="pm-box pm-panel-form" onsubmit="return false;">
    <h4>工程日曆</h4>
    <table class="pm-detail" style="max-width:860px;">
        <tr><th>默認只顯示「自己創建的事件」</th><td><?php echo $radio('cal_own_only'); ?>
            <div class="pm-note" style="margin:4px 0 0 0;">開啟：用戶在「工程日曆」只能查看自己創建的事件；關閉：可查看所有用戶創建的事件。管理員始終可查看全部事件。</div></td></tr>
        <tr><th></th><td><a class="easyui-linkbutton pm-panel-save" icon="icon-ok">保存設置</a></td></tr>
    </table>
</form>
</div>
<div title="用戶權限" style="overflow:auto;">
<form class="pm-box pm-panel-form" onsubmit="return false;">
    <h4>普通用戶權限</h4>
    <table class="pm-detail" style="max-width:860px;">
        <tr><th>普通用戶刪除報審記錄</th><td><?php echo $radio('doc_user_del'); ?>
            <div class="pm-note" style="margin:4px 0 0 0;">關閉（默認）：普通用戶「我的文件」沒有「刪除」功能，只有 admin、xadmin 可刪除報審記錄；開啟：普通用戶可刪除自己創建的擬稿文件。</div></td></tr>
        <tr><th></th><td><a class="easyui-linkbutton pm-panel-save" icon="icon-ok">保存設置</a></td></tr>
    </table>
</form>
</div>
<div title="用戶菜單顯示" style="overflow:auto;">
<form class="pm-box pm-panel-form" onsubmit="return false;">
    <h4>普通用戶菜單訪問權限 <span class="pm-note" style="font-weight:normal;margin-left:10px;">關閉後普通用戶看不到對應菜單項，也不能訪問該功能；管理員不受影響。</span></h4>
    <table class="pm-detail" style="max-width:860px;">
        <?php foreach ($menuGroups as $g => $pages): ?>
        <tr><th><?php echo h($g); ?></th><td>
            <?php foreach ($pages as $pg): $k = 'menu_' . $pg; $v = panel_get($k); ?>
            <span style="display:inline-block;min-width:200px;margin:2px 0;"><b style="display:inline-block;width:70px;font-weight:normal;"><?php echo h($menuNames[$pg]); ?></b>
                <label class="pm-radio"><input type="radio" name="<?php echo $k; ?>" value="1"<?php echo $v ? ' checked="checked"' : ''; ?>/>開啟</label>
                <label class="pm-radio"><input type="radio" name="<?php echo $k; ?>" value="0"<?php echo $v ? '' : ' checked="checked"'; ?>/>關閉</label></span>
            <?php endforeach; ?>
        </td></tr>
        <?php endforeach; ?>
        <tr><th></th><td><a class="easyui-linkbutton pm-panel-save" icon="icon-ok">保存設置</a>
            <a class="easyui-linkbutton pm-panel-all" icon="icon-redo" plain="true" data-v="1">全部開啟</a></td></tr>
    </table>
</form>
</div>
<div title="系統語言" style="overflow:auto;">
<form class="pm-box pm-panel-form" onsubmit="return false;">
    <h4>系統語言</h4>
    <table class="pm-detail" style="max-width:860px;">
        <?php foreach (panel_lang_defs() as $lk => $lt): ?>
        <tr><th><?php echo h($lt); ?></th><td><?php echo $radio('lang_' . $lk); ?></td></tr>
        <?php endforeach; ?>
        <tr><th></th><td><div class="pm-note" style="margin:0 0 6px 0;">開啟的語言會顯示在登錄頁和系統右上角供用戶切換，至少須開啟一種；英文目前翻譯菜單、按鈕和常用欄目，內容數據保持原文。</div>
            <a class="easyui-linkbutton pm-panel-save" icon="icon-ok">保存設置</a></td></tr>
    </table>
</form>
</div>
<div title="項目文件頁眉">
    <div id="<?php echo $uid; ?>_hd_tb" class="search-div"><div class="search-toolbar"><span style="float:left;">
        <a class="easyui-linkbutton" icon="icon-add" plain="true" data-h="up">上傳 / 更換頁眉圖片</a>
        <a class="easyui-linkbutton" icon="icon-remove" plain="true" data-h="del">刪除頁眉圖片</a>
        <a class="easyui-linkbutton" icon="icon-reload" plain="true" data-h="reload">刷新</a>
    </span><span style="float:right;color:#888;line-height:28px;padding-right:8px;">打印審批文件封面（A4）時，頁眉圖片顯示在頂部並鋪滿頁寬；項目未設置時使用默認頁眉，都沒有則不顯示頁眉</span></div></div>
    <table id="<?php echo $uid; ?>_hd_dg" toolbar="#<?php echo $uid; ?>_hd_tb"></table>
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
    var hd = U + '_hd_dg';
    PM.grid(hd, {url: 'api/panel.php?act=header_list', pagination: false, nowrap: false, columns: [[
        {field: 'code', title: '工程編號', width: 150},
        {field: 'name', title: '工程名稱', width: 220},
        {field: 'file_id', title: '頁眉圖片', width: 460, formatter: function(v){
            return v ? '<a href="api/file.php?id=' + v + '&inline=1" target="_blank"><img src="api/file.php?id=' + v + '&inline=1" style="max-width:440px;max-height:60px;margin:3px 0;border:1px solid #ddd;"/></a>' : '<span style="color:#999">未設置</span>';
        }},
        {field: 'file_name', title: '圖片文件', width: 160},
        {field: 'user_name', title: '上傳人', width: 80, align: 'center'},
        {field: 'uploaded_at', title: '上傳時間', width: 140, align: 'center'}
    ]]});
    $(U + '_tabs').on('click', 'a[data-h]', function(){
        var a = $(this).data('h');
        if (a === 'reload') return PM.reload(hd);
        var r = PM.selected(hd);
        if (!r) return;
        var title = r.id ? (r.code + ' ' + r.name) : '默認頁眉';
        if (a === 'up') {
            PM.form({title: '上傳頁眉圖片 - ' + title, width: 560, url: 'api/panel.php?act=header_upload', extra: {project_id: r.id},
                fields: [{name: 'img', label: '頁眉圖片', type: 'custom', render: function($el){
                    $('<input type="file" name="f_img[]" accept=".jpg,.jpeg,.png,.gif"/>').appendTo($el);
                }, getValue: function(){ return ''; }, hint: 'JPG / PNG / GIF，不超過 5MB；建議寬高比約 6:1（例如 1800×300 像素），上傳後替換原有圖片'}],
                onOk: function(){ PM.reload(hd); }});
        } else if (a === 'del') {
            if (!r.file_id) return PM.alert('該項目未設置頁眉圖片');
            PM.confirm('確定刪除「' + title + '」的頁眉圖片嗎？', function(){ PM.post('api/panel.php?act=header_delete', {project_id: r.id}, function(){ PM.reload(hd); }); });
        }
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
    $(U + '_tabs').on('click', '.pm-panel-save', function(){ PM.post('api/panel.php?act=save', $(this).closest('form').serialize()); });
    $(U + '_tabs').on('click', '.pm-panel-all', function(){ $(this).closest('form').find('input[type=radio][value=1]').prop('checked', true); });
});
</script>
