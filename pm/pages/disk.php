<?php
/** 網盤資料：$name 為 disk_personal | disk_share | disk_company */
require_once __DIR__ . '/../inc/files.php';
$space = substr($name, 5);
$spaces = disk_spaces();
$canCreate = $space !== 'company' || $admin;
$hints = array(
    'personal' => '個人文件只有自己可見。',
    'share' => '共享文件所有用戶可見、可上傳，只能修改或刪除自己創建的文件。',
    'company' => '公司文件所有用戶可見、可下載，由管理員維護。',
);
?>
<div class="easyui-layout" fit="true" border="false" id="<?php echo $uid; ?>_box">
    <div region="west" split="true" title="目錄列表" style="width:220px;padding:4px;">
        <ul id="<?php echo $uid; ?>_tree" class="pm-disk-tree"></ul>
    </div>
    <div region="center" border="false" style="overflow:hidden;">
        <div id="<?php echo $uid; ?>_tb" class="search-div">
            <form id="<?php echo $uid; ?>_form" onsubmit="return false;">
                <div class="search-content">
                    <span><label>文件名稱：</label><input type="text" name="keyword" style="width:150px;" placeholder="文件名 / 描述"/></span>
                    <span><label>創建時間：</label><input type="date" name="start" style="width:135px;"/> -- <input type="date" name="end" style="width:135px;"/></span>
                </div>
                <div class="search-toolbar">
                    <span style="float:left;">
                        <?php if ($canCreate): ?>
                        <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_mkdir">創建文件夾</a>
                        <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_upload">上傳文件</a>
                        <a class="easyui-linkbutton" icon="icon-edit" plain="true" id="<?php echo $uid; ?>_edit" title="修改名稱、描述或移動到其他文件夾">修改 / 移動</a>
                        <a class="easyui-linkbutton" icon="icon-remove" plain="true" id="<?php echo $uid; ?>_del">批量刪除</a>
                        <a class="easyui-linkbutton" icon="icon-redo" plain="true" id="<?php echo $uid; ?>_uncheck">清空勾選</a>
                        <?php endif; ?>
                        <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">刷新</a>
                    </span>
                    <span style="float:right;"><button class="btn btn-primary btn-small" type="submit">查詢</button>&nbsp;<button class="btn btn-small" type="button" id="<?php echo $uid; ?>_clear">清空</button></span>
                </div>
                <div class="pm-crumb"><span id="<?php echo $uid; ?>_crumb"></span><span class="pm-crumb-hint"><?php echo h($hints[$space] . ($canCreate ? '可把文件直接拖拉到列表或文件夾上傳。' : '')); ?></span></div>
            </form>
        </div>
        <table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
    </div>
</div>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg', tree = U + '_tree', form = $(U + '_form')[0];
    var space = '<?php echo $space; ?>', rootName = <?php echo js_json($spaces[$space]); ?>, canCreate = <?php echo $canCreate ? 'true' : 'false'; ?>;
    var url = 'api/disk.php?space=' + space, cur = 0, crumbs = [], searching = false, dirs = [];

    function query(){ return {parent_id: cur, keyword: $.trim(form.keyword.value), start: form.start.value, end: form.end.value}; }
    function load(){ $(dg).datagrid('clearSelections').datagrid('clearChecked').datagrid('load', query()); }
    function open(id){ cur = id; form.reset(); load(); }
    function rowById(id){ return $.grep($(dg).datagrid('getRows'), function(r){ return r.id == id; })[0]; }
    function pathText(){ return '/' + $.map(crumbs, function(c){ return c.name; }).join('/'); }

    // ---- 目錄樹 ----
    function loadTree(){
        $.getJSON(url + '&act=tree', function(data){
            dirs = [];
            (function walk(nodes, depth, path){
                $.each(nodes, function(i, n){
                    if (n.id) dirs.push({id: n.id, name: n.text, depth: depth, path: path.concat(n.id)});
                    walk(n.children || [], n.id ? depth + 1 : depth, n.id ? path.concat(n.id) : path);
                    n.text = PM.esc(n.text);
                });
            })(data, 0, []);
            $(tree).tree('loadData', data);
            markTree();
        });
    }
    function markTree(){
        var n = $(tree).tree('find', cur);
        if (n) { $(tree).tree('expandTo', n.target).tree('select', n.target); }
    }
    $(tree).tree({animate: false, lines: true, data: [], onClick: function(node){ open(node.id); }});

    // ---- 文件列表 ----
    var cols = [];
    if (canCreate) cols.push({field: 'ck', checkbox: true});
    cols.push({field: 'name', title: '文件名', width: 320, formatter: function(v, row){
        return '<a href="javascript:void(0)" class="pm-disk-name" data-id="' + row.id + '"><img src="resource/images/icons/' + (row.is_dir == 1 ? 'folder_Close.gif' : 'page.gif') + '" align="top"/>&nbsp;'
            + PM.esc(v) + '</a>' + (searching ? '<div class="pm-disk-path">位置：' + PM.esc(row.path) + '</div>' : '');
    }});
    cols.push({field: 'remark', title: '描述', width: 220});
    cols.push({field: 'size', title: '大小', width: 80, align: 'right', formatter: function(v, row){ return row.is_dir == 1 ? '' : PM.fileSize(v); }});
    if (space !== 'personal') cols.push({field: 'creator_name', title: '創建人', width: 100, align: 'center'});
    cols.push({field: 'created_at', title: '創建時間', width: 140, align: 'center'});
    cols.push({field: 'op', title: '操作', width: 110, align: 'center', formatter: function(v, row){
        var a = [];
        if (row.is_dir != 1 && row.file_id) a.push('<a href="api/file.php?id=' + row.file_id + '" target="_blank" title="點擊下載">下載</a>');
        if (row.can_write == 1) a.push('<a href="javascript:void(0)" class="pm-disk-del" data-id="' + row.id + '">刪除</a>');
        return a.join('&nbsp;|&nbsp;');
    }});
    PM.grid(dg, {
        url: url + '&act=list', queryParams: query(), singleSelect: false, columns: [cols],
        loadFilter: function(d){ searching = d.search == 1; crumbs = d.crumbs || []; cur = d.parent_id || 0; return d; },
        onLoadSuccess: function(){
            drawCrumb();
            markTree();
            // datagrid 會阻止單元格點擊事件冒泡，鏈接需直接綁定
            var $p = $(dg).datagrid('getPanel');
            $p.find('.pm-disk-name').click(clickName);
            $p.find('.pm-disk-del').click(clickDel);
        }
    });
    function drawCrumb(){
        var $c = $(U + '_crumb').empty().append('位置：');
        $('<a href="javascript:void(0)"/>').text(rootName).data('id', 0).appendTo($c);
        if (searching) return $c.append(' <span style="color:#d93025;">（搜索結果，範圍：整個' + PM.esc(rootName) + '）</span>');
        $.each(crumbs, function(i, c){ $c.append(' &gt;&gt; '); $('<a href="javascript:void(0)"/>').text(c.name).data('id', c.id).appendTo($c); });
    }
    $(U + '_crumb').on('click', 'a', function(){ open($(this).data('id')); });
    function clickName(e){
        e.stopPropagation();
        var row = rowById($(this).data('id'));
        if (!row) return;
        if (row.is_dir == 1) return open(row.id);
        var fields = [{name: 'name', label: '文件名'}, {name: 'path', label: '所在文件夾'}, {name: 'remark', label: '描述'},
            {name: 'size', label: '大小', html: function(r){ return PM.fileSize(r.size); }},
            {name: 'creator_name', label: '創建人'}, {name: 'created_at', label: '創建時間'}, {name: 'updated_at', label: '修改時間'},
            {name: 'op', label: '操作', html: function(r){
                if (!r.file_id) return '';
                var s = '<a href="api/file.php?id=' + r.file_id + '" target="_blank" style="color:blue;">下載</a>';
                if (/\.(jpe?g|png|gif)$/i.test(r.name)) s += '&nbsp;&nbsp;<a href="api/file.php?id=' + r.file_id + '&inline=1" target="_blank" style="color:blue;">預覽</a>';
                return s;
            }}];
        PM.detail('文件詳情', fields, row, {width: 560});
    }
    function clickDel(e){
        e.stopPropagation();
        var row = rowById($(this).data('id'));
        if (row) del([row]);
    }

    // ---- 操作 ----
    function done(dirChanged){ load(); if (dirChanged) loadTree(); }
    function del(rows){
        var hasDir = $.grep(rows, function(r){ return r.is_dir == 1; }).length > 0;
        var msg = rows.length === 1 ? '確定要刪除「' + rows[0].name + '」嗎？' : '確定要刪除勾選的 ' + rows.length + ' 項嗎？';
        if (hasDir) msg += '（注：如果文件夾內有文件，請先清空文件夾內文件）';
        PM.confirm(msg, function(){
            PM.post(url + '&act=delete', {ids: $.map(rows, function(r){ return r.id; }).join(',')}, function(){ done(hasDir); });
        });
    }
    function checked(){
        var rows = $(dg).datagrid('getChecked');
        if (!rows.length) PM.alert('請先勾選文件或文件夾');
        return rows;
    }
    $(U + '_form').submit(function(){ load(); return false; });
    $(U + '_clear').click(function(){ form.reset(); load(); });
    $(U + '_reload').click(function(){ done(true); });
    $(U + '_uncheck').click(function(){ $(dg).datagrid('clearSelections').datagrid('clearChecked'); });
    $(U + '_mkdir').click(function(){
        PM.form({title: '創建文件夾', width: 520, url: url + '&act=mkdir', extra: {parent_id: cur},
            fields: [
                {name: 'path', label: '所在位置', type: 'static', value: pathText()},
                {name: 'name', label: '文件夾名', required: true, hint: '同一目錄下文件夾名稱不可重複'},
                {name: 'remark', label: '備注', type: 'textarea', rows: 2}
            ],
            onOk: function(){ done(true); }});
    });
    $(U + '_upload').click(function(){
        PM.form({title: '上傳文件', width: 560, url: url + '&act=upload', extra: {parent_id: cur}, okText: '上傳',
            fields: [
                {name: 'path', label: '保存到', type: 'static', value: pathText()},
                {name: 'file', label: '文件', type: 'files', hint: '可一次選擇多個文件，單個文件不超過 50MB'},
                {name: 'remark', label: '描述', type: 'textarea', rows: 2}
            ],
            onOk: function(){ done(false); }});
    });
    $(U + '_edit').click(function(){
        var rows = checked();
        if (!rows.length) return;
        if (rows.length > 1) return PM.alert('一次只能修改一項');
        var r = rows[0];
        if (r.can_write != 1) return PM.alert(space === 'company' ? '只有管理員才能管理公司文件' : '只能修改自己創建的文件');
        var opts = [{value: '0', text: '/ ' + rootName}];
        $.each(dirs, function(i, d){
            // 文件夾不能移動到自己或其子文件夾
            if (r.is_dir == 1 && $.inArray(r.id, d.path) >= 0) return;
            opts.push({value: String(d.id), text: new Array(d.depth + 2).join('　') + d.name});
        });
        PM.form({title: r.is_dir == 1 ? '修改文件夾' : '修改文件', width: 560, data: r, url: url + '&act=save', extra: {id: r.id},
            fields: [
                {name: 'name', label: r.is_dir == 1 ? '文件夾名' : '文件名', required: true, hint: r.is_dir == 1 ? '' : '不能更改文件的擴展名'},
                {name: 'parent_id', label: '所在文件夾', type: 'select', options: opts, hint: '選擇其他文件夾即可移動'},
                {name: 'remark', label: '描述', type: 'textarea', rows: 2}
            ],
            onOk: function(){ done(r.is_dir == 1); }});
    });
    $(U + '_del').click(function(){
        var rows = checked();
        if (!rows.length) return;
        if ($.grep(rows, function(r){ return r.can_write != 1; }).length) return PM.alert(space === 'company' ? '只有管理員才能管理公司文件' : '勾選項中包含他人創建的文件，只能刪除自己創建的文件');
        del(rows);
    });

    // ---- 拖拉上傳：拖到文件夾行或目錄樹節點 = 上傳到該文件夾；拖到列表其他位置 = 上傳到當前文件夾 ----
    var MAX_SIZE = <?php echo (int)UPLOAD_MAX_SIZE; ?>, $mark = null;
    function hasFiles(e){
        var dt = e.originalEvent.dataTransfer;
        return !!dt && Array.prototype.indexOf.call(dt.types || [], 'Files') >= 0;
    }
    function dropTarget(e){
        var $t = $(e.target), $row = $t.closest('tr.datagrid-row'), $node = $t.closest('.tree-node');
        if ($row.length) {
            var row = $(dg).datagrid('getRows')[parseInt($row.attr('datagrid-row-index'), 10)];
            if (row && row.is_dir == 1) return {id: row.id, name: row.name, $el: $row};
        }
        if ($node.length && $.contains($(tree)[0], $node[0])) {
            var n = $(tree).tree('getNode', $node[0]);
            return {id: n.id, name: n.id ? $('<i/>').html(n.text).text() : rootName, $el: $node};
        }
        return {id: cur, name: crumbs.length ? crumbs[crumbs.length - 1].name : rootName, $el: $(dg).datagrid('getPanel')};
    }
    function mark($el){
        if ($mark) $mark.removeClass('pm-drop');
        $mark = $el ? $el.addClass('pm-drop') : null;
    }
    function uploadFiles(files, target, skipped){
        var i = 0, okN = 0, errs = [];
        if (skipped) errs.push('暫不支持拖拉整個文件夾，已略過 ' + skipped + ' 個文件夾');
        if (!files.length) return PM.alert(errs[0] || '沒有可上傳的文件');
        $.messager.progress({title: '上傳文件', msg: '準備上傳…', text: ''});
        (function next(){
            if (i >= files.length) {
                $.messager.progress('close');
                if (okN) PM.tip('已上傳 ' + okN + ' 個文件到「' + target.name + '」');
                if (errs.length) $.messager.alert('提示', $.map(errs, PM.esc).join('<br/>'), 'warning');
                return done(false);
            }
            var f = files[i++], fd = new FormData();
            $('.messager-p-msg').text('正在上傳 ' + i + ' / ' + files.length + '：' + f.name);
            if (f.size > MAX_SIZE) { errs.push(f.name + '：文件不能超過 50MB'); return next(); }
            fd.append('parent_id', target.id);
            fd.append('f_file[]', f, f.name);
            $.ajax({url: url + '&act=upload', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json'}).done(function(res){
                if (res && res.ok) okN++;
                else errs.push(f.name + '：' + (res && res.msg ? res.msg : '上傳失敗'));
                next();
            }).fail(function(xhr){
                var res = xhr.responseJSON;
                if (xhr.status === 401) { $.messager.progress('close'); return PM.kick(res && res.msg); }
                errs.push(f.name + '：' + (res && res.msg ? res.msg : (xhr.status === 413 ? '文件過大，服務器拒絕接收' : '上傳失敗（' + xhr.status + '）')));
                next();
            });
        })();
    }
    if (canCreate && window.FormData) {
        $(U + '_box').on('dragenter dragover', function(e){
            if (!hasFiles(e)) return;
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'copy';
            var t = dropTarget(e);
            if (!$mark || $mark[0] !== t.$el[0]) mark(t.$el);
        }).on('dragleave', function(e){
            var to = e.originalEvent.relatedTarget;
            if (!to || !$.contains(this, to)) mark(null);
        }).on('drop', function(e){
            if (!hasFiles(e)) return;
            e.preventDefault();
            var t = dropTarget(e), dt = e.originalEvent.dataTransfer, files = [], skipped = 0, k;
            mark(null);
            if (dt.items && dt.items.length) {
                for (k = 0; k < dt.items.length; k++) {
                    var it = dt.items[k];
                    if (it.kind !== 'file') continue;
                    var en = it.webkitGetAsEntry ? it.webkitGetAsEntry() : null, f = it.getAsFile();
                    if (en && en.isDirectory) skipped++;
                    else if (f) files.push(f);
                }
            } else {
                for (k = 0; k < dt.files.length; k++) files.push(dt.files[k]);
            }
            uploadFiles(files, t, skipped);
        });
    }
    loadTree();
});
</script>
