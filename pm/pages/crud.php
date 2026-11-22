<?php
/** 通用數據模塊頁面（定義見 inc/modules.php）；$crudName 由 page.php 傳入 */
require_once __DIR__ . '/../inc/modules.php';
$mod = pm_module($crudName);
$parentId = isset($mod['parent']) ? param_int('parent_id') : 0;
$defs = array();
$filters = array();
$hasSearch = false;
foreach ($mod['fields'] as $k => $f) {
    $d = array('name' => $k, 'label' => $f['label'], 'type' => isset($f['type']) ? $f['type'] : 'text', 'required' => !empty($f['required']),
        'width' => isset($f['width']) ? $f['width'] : 120, 'rows' => isset($f['rows']) ? $f['rows'] : 4);
    if (isset($f['default'])) $d['value'] = $f['default'];
    if (isset($f['options'])) {
        $d['options'] = array();
        foreach ($f['options'] as $v => $text) $d['options'][] = array('value' => (string)$v, 'text' => $text);
        if (!empty($f['filter'])) $filters[] = $d;
    }
    if (!empty($f['search'])) $hasSearch = true;
    $defs[] = $d;
}
$files = array();
foreach (isset($mod['files']) ? $mod['files'] : array() as $k => $label) $files[] = array('name' => $k, 'label' => $label);
$canAdd = $mod['scope'] !== 'admin' || $admin;
?>
<div id="<?php echo $uid; ?>_tb" class="search-div">
    <form onsubmit="return datagridSearch(this,'<?php echo $uid; ?>_dg');">
        <?php if ($hasSearch || $filters): ?>
        <div class="search-content">
            <?php if ($hasSearch): ?><span><label>關鍵字：</label><input type="text" name="keyword" style="width:160px;"/></span><?php endif; ?>
            <?php foreach ($filters as $f): ?>
            <span><label><?php echo h($f['label']); ?>：</label>
                <select name="f_<?php echo $f['name']; ?>" style="width:120px;"><option value="">全部</option>
                    <?php foreach ($f['options'] as $o): ?><option value="<?php echo h($o['value']); ?>"><?php echo h($o['text']); ?></option><?php endforeach; ?>
                </select></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="search-toolbar">
            <span style="float:left;">
                <?php if ($canAdd): ?>
                <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add">新增</a>
                <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit">修改</a>
                <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">刪除</a>
                <?php endif; ?>
                <a class="easyui-linkbutton" icon="icon-search" plain="true" id="<?php echo $uid; ?>_view">查看</a>
                <?php if (!empty($mod['toggle'])): ?><a class="easyui-linkbutton" icon="icon-ok" plain="true" id="<?php echo $uid; ?>_toggle">完成 / 取消完成</a><?php endif; ?>
                <?php if (!empty($mod['child'])): ?><a class="easyui-linkbutton" icon="icon-tip" plain="true" id="<?php echo $uid; ?>_child"><?php echo h($mod['child'][1]); ?></a><?php endif; ?>
                <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">刷新</a>
            </span>
            <?php if ($hasSearch || $filters): ?>
            <span style="float:right;"><button class="btn btn-primary btn-small" type="submit">查詢</button>&nbsp;<button class="btn btn-small clear" type="button">清空</button></span>
            <?php endif; ?>
        </div>
    </form>
</div>
<table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg', M = <?php echo js_json($crudName); ?>, title = <?php echo js_json($mod['title']); ?>;
    var defs = <?php echo js_json($defs); ?>, files = <?php echo js_json($files); ?>, parentId = <?php echo (int)$parentId; ?>;
    var api = 'api/crud.php?m=' + M, cols = [];
    $.each(defs, function(i, d){
        var c = {field: d.name, title: d.label, width: d.width, align: d.type === 'textarea' || d.width > 150 ? 'left' : 'center'};
        if (d.options) c.formatter = function(v){ return PM.esc(PM.optText(d.options, v)); };
        if (d.type === 'textarea') c.formatter = function(v){ v = v || ''; return PM.esc(v.length > 60 ? v.substr(0, 60) + '…' : v); };
        cols.push(c);
    });
    $.each(files, function(i, f){ cols.push({field: 'files_' + f.name, title: f.label, width: 150, formatter: PM.fileLinks}); });
    <?php if ($mod['scope'] !== 'own'): ?>cols.push({field: 'creator_name', title: '創建人', width: 90, align: 'center'});<?php endif; ?>
    cols.push({field: 'updated_at', title: '更新時間', width: 140, align: 'center'});
    PM.grid(dg, {url: api + '&act=list', queryParams: {parent_id: parentId}, columns: [cols], onDblClickRow: function(i, row){ view(row); }});
    function fields(row) {
        var fs = $.map(defs, function(d){ return $.extend({}, d); });
        $.each(files, function(i, f){ fs.push({name: f.name, label: f.label, type: 'files', files: row ? row['files_' + f.name] : []}); });
        return fs;
    }
    function edit(row) {
        if (row && !row.can_write) return PM.alert('只能修改自己創建的記錄');
        PM.form({title: (row ? '修改' : '新增') + title, width: 620, data: row || {}, url: api + '&act=save', extra: {id: row ? row.id : 0, parent_id: parentId},
            fields: fields(row), onOk: function(){ PM.reload(dg); }});
    }
    function view(row) { PM.detail(title + '詳情', fields(row), row); }
    $(U + '_add').click(function(){ edit(null); });
    $(U + '_edit').click(function(){ var r = PM.selected(dg); if (r) edit(r); });
    $(U + '_view').click(function(){ var r = PM.selected(dg); if (r) view(r); });
    $(U + '_del').click(function(){
        var r = PM.selected(dg);
        if (!r) return;
        if (!r.can_write) return PM.alert('只能刪除自己創建的記錄');
        PM.confirm('確定刪除所選' + title + '嗎？', function(){ PM.post(api + '&act=delete', {id: r.id}, function(){ PM.reload(dg); }); });
    });
    $(U + '_toggle').click(function(){ var r = PM.selected(dg); if (r) PM.post(api + '&act=toggle', {id: r.id}, function(){ PM.reload(dg); }); });
    <?php if (!empty($mod['child'])): ?>
    $(U + '_child').click(function(){
        var r = PM.selected(dg);
        if (r) PM.dialog(<?php echo js_json($mod['child'][1]); ?> + ' - ' + r[defs[0].name], 'page.php?name=<?php echo $mod['child'][0]; ?>&parent_id=' + r.id, {width: 1000, height: 520});
    });
    <?php endif; ?>
    $(U + '_reload').click(function(){ PM.reload(dg); });
});
</script>
