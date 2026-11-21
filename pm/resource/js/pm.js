/**
 * 澳門工程管理網站 前端公共方法
 */
var PM = {
    cfg: {},
    init: function (cfg) {
        PM.cfg = cfg;
        $.ajaxSetup({headers: {'X-CSRF-Token': cfg.csrf}});
    },
    esc: function (s) {
        return s === null || s === undefined ? '' : String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    },
    tip: function (msg) {
        $.messager.show({title: '提示', msg: PM.esc(msg), timeout: 2500, showType: 'slide'});
    },
    alert: function (msg, icon) {
        $.messager.alert('提示', PM.esc(msg), icon || 'warning');
    },
    confirm: function (msg, fn) {
        $.messager.confirm('確認', PM.esc(msg), function (r) { if (r) fn(); });
    },
    /** POST 請求；data 可為對象或 FormData */
    post: function (url, data, done) {
        var opt = {url: url, type: 'POST', data: data, dataType: 'json'};
        if (window.FormData && data instanceof FormData) {
            opt.processData = false;
            opt.contentType = false;
        }
        $.messager.progress({text: '處理中...'});
        $.ajax(opt).always(function () {
            $.messager.progress('close');
        }).done(function (res) {
            if (res && res.ok) {
                if (res.msg) PM.tip(res.msg);
                if (done) done(res);
            } else {
                PM.alert(res && res.msg ? res.msg : '操作失敗');
            }
        }).fail(function (xhr) {
            var res = xhr.responseJSON;
            if (xhr.status === 401) {
                window.top.location.href = 'index.php';
            } else {
                PM.alert(res && res.msg ? res.msg : '請求失敗（' + xhr.status + '）', 'error');
            }
        });
    },
    /** datagrid：默認對單元格內容做 HTML 轉義 */
    grid: function (sel, opt) {
        $.each(opt.columns[0], function (i, c) {
            if (!c.formatter && !c.checkbox) c.formatter = PM.esc;
        });
        return $(sel).datagrid($.extend({
            fit: true, pagination: true, pageSize: 20, rownumbers: true, singleSelect: true, striped: true, nowrap: false,
            onLoadError: function (xhr) { if (xhr && xhr.status === 401) window.top.location.href = 'index.php'; }
        }, opt));
    },
    selected: function (sel) {
        var row = $(sel).datagrid('getSelected');
        if (!row) PM.alert('請先選擇一條記錄');
        return row;
    },
    reload: function (sel) {
        var $g = $(sel);
        if ($g.length) $g.datagrid('reload');
    },
    /** 刷新所有已打開的文件列表 */
    reloadDocs: function () {
        $('table.pm-doc-grid').each(function () {
            if ($.data(this, 'datagrid')) $(this).datagrid('reload');
        });
        PM.refreshTodo();
    },
    refreshTodo: function () {
        if (PM.cfg.admin) return;
        $.getJSON('api/doc.php', {act: 'todo_count'}, function (res) {
            if (res && res.ok) $('#ft_todo').text(res.count);
        });
    },
    /** 打開模態窗口（加載頁面），關閉即銷毀 */
    dialog: function (title, url, opt) {
        var $d = $('<div/>').appendTo('body');
        $d.dialog($.extend({
            title: title, href: url, width: 800, height: 520, modal: true, resizable: true, maximizable: true, cache: false,
            onClose: function () { $d.dialog('destroy'); }
        }, opt || {}));
        return $d;
    },
    /**
     * 通用表單窗口
     * opt: {title, width, height, fields:[{name,label,type,options,required,value,hint,render,getValue}], data, url, extra, okText, onOk}
     */
    form: function (opt) {
        var data = opt.data || {};
        var $d = $('<div style="padding:15px 20px;"/>').appendTo('body');
        var $form = $('<form class="pm-form" onsubmit="return false;"/>').appendTo($d);
        var $table = $('<table style="width:100%;"/>').appendTo($form);
        var custom = [];
        $.each(opt.fields, function (i, f) {
            var val = data[f.name] !== undefined && data[f.name] !== null ? data[f.name] : (f.value !== undefined ? f.value : '');
            var $tr = $('<tr/>').appendTo($table);
            $('<td class="pm-form-label"/>').html((f.required ? '<span style="color:red">*</span> ' : '') + PM.esc(f.label) + '：').appendTo($tr);
            var $td = $('<td class="pm-form-field"/>').appendTo($tr);
            var $in;
            if (f.type === 'select') {
                $in = $('<select/>').attr('name', f.name);
                $.each(f.options || [], function (j, o) {
                    $('<option/>').val(o.value).text(o.text).appendTo($in);
                });
                $in.val(String(val));
                if ($in.val() === null && $in[0].options.length) $in[0].selectedIndex = 0;
            } else if (f.type === 'textarea') {
                $in = $('<textarea rows="' + (f.rows || 4) + '"/>').attr('name', f.name).val(val);
            } else if (f.type === 'custom') {
                $in = $('<div/>');
                custom.push({f: f, $el: $in});
            } else if (f.type === 'static') {
                $in = $('<span class="pm-form-static"/>').text(val);
            } else {
                $in = $('<input/>').attr({type: f.type || 'text', name: f.name, autocomplete: 'off'});
                if (f.type !== 'file') $in.val(val);
                if (f.accept) $in.attr('accept', f.accept);
                if (f.readonly) $in.prop('readonly', true);
            }
            if (f.placeholder) $in.attr('placeholder', f.placeholder);
            $in.appendTo($td);
            if (f.hint) $('<div class="pm-form-hint"/>').text(f.hint).appendTo($td);
        });
        $.each(custom, function (i, c) { c.f.render(c.$el, data); });

        function submit() {
            var fd = new FormData($form[0]);
            var err = null;
            $.each(opt.fields, function (i, f) {
                if (f.type === 'custom') {
                    var v = f.getValue();
                    if (f.required && !v) err = err || ('請設置' + f.label);
                    fd.append(f.name, v);
                } else if (f.required && f.type !== 'static') {
                    var $f = $form.find('[name="' + f.name + '"]');
                    if ($.trim($f.val() || '') === '') err = err || ('請填寫' + f.label);
                }
            });
            if (err) return PM.alert(err);
            $.each(opt.extra || {}, function (k, v) { fd.append(k, v); });
            if (opt.beforeSubmit && opt.beforeSubmit(fd, $form) === false) return;
            PM.post(opt.url, fd, function (res) {
                $d.dialog('close');
                if (opt.onOk) opt.onOk(res);
            });
        }
        var buttons = [];
        $.each(opt.buttons || [], function (i, b) {
            buttons.push({text: b.text, iconCls: b.iconCls, handler: function () {
                var old = opt.extra;
                opt.extra = $.extend({}, old || {}, b.extra || {});
                submit();
                opt.extra = old;
            }});
        });
        if (!opt.buttons) buttons.push({text: opt.okText || '保存', iconCls: 'icon-ok', handler: submit});
        buttons.push({text: '取消', iconCls: 'icon-cancel', handler: function () { $d.dialog('close'); }});
        $d.dialog({
            title: opt.title, width: opt.width || 560, height: opt.height || 'auto', modal: true, resizable: true, buttons: buttons,
            onClose: function () { $d.dialog('destroy'); }
        });
        return $d;
    },
    docStatus: function (v, row) {
        var color = {0: '#888', 1: '#e68a00', 2: '#2e8b57', 3: '#d93025'}[v] || '#333';
        return '<span style="color:' + color + ';font-weight:bold;">' + PM.esc(row.status_text) + '</span>';
    },
    fileSize: function (v) {
        v = parseInt(v, 10) || 0;
        if (v >= 1048576) return (v / 1048576).toFixed(2) + ' MB';
        if (v >= 1024) return (v / 1024).toFixed(1) + ' KB';
        return v + ' B';
    },
    viewDoc: function (id) {
        PM.dialog('文件詳情', 'page.php?name=doc_view&id=' + encodeURIComponent(id), {width: 900, height: 600});
    }
};
