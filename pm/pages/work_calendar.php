<?php
/** 工程日曆（參考 project-approval/calendar.php：月視圖 + 當日事件 + 關注/節點/材料/人員/計劃標記） */
$projects = db_rows('SELECT p.id, p.code, p.name FROM pm_projects p WHERE ' . project_scope_sql('p.id') . ' ORDER BY p.id');
?>
<div class="easyui-layout pm-cal" fit="true" border="false" id="<?php echo $uid; ?>_box">
    <div region="center" border="false" style="padding:6px;">
        <div class="pm-cal-tools">
            <span><label>工程：</label>
                <select id="<?php echo $uid; ?>_project" style="width:200px;"><option value="0">全部工程</option>
                    <?php foreach ($projects as $p): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo h($p['code'] . ' ' . $p['name']); ?></option><?php endforeach; ?>
                </select></span>
            <span><label>關鍵字：</label><input type="text" id="<?php echo $uid; ?>_kw" style="width:140px;"/></span>
            <button class="btn btn-primary btn-small" type="button" id="<?php echo $uid; ?>_search">查詢</button>
            <button class="btn btn-small" type="button" id="<?php echo $uid; ?>_clear">清空</button>
            <span style="float:right;">
                <?php require_once __DIR__ . '/../inc/panel.php'; if (cal_own_only()): ?><span class="pm-note" style="margin-right:12px;">只顯示自己創建的事件</span><?php endif; ?>
                <label class="pm-cal-chk"><input type="checkbox" id="<?php echo $uid; ?>_hol"/><i class="mo"></i>澳門假期</label>
                <label class="pm-cal-chk"><input type="checkbox" id="<?php echo $uid; ?>_cn"/><i class="cn"></i>中國假期</label>
            </span>
        </div>
        <div class="pm-cal-nav">
            <a class="easyui-linkbutton" plain="true" id="<?php echo $uid; ?>_prev">◀ 上月</a>
            <b id="<?php echo $uid; ?>_title"></b>
            <a class="easyui-linkbutton" plain="true" id="<?php echo $uid; ?>_next">下月 ▶</a>
            <a class="easyui-linkbutton" plain="true" id="<?php echo $uid; ?>_today">今天</a>
            <a class="easyui-linkbutton" icon="icon-add" plain="true" id="<?php echo $uid; ?>_add" style="float:right;">新建事件</a>
        </div>
        <table class="pm-cal-grid">
            <thead><tr><th class="we">日</th><th>一</th><th>二</th><th>三</th><th>四</th><th>五</th><th class="we">六</th></tr></thead>
            <tbody id="<?php echo $uid; ?>_days"></tbody>
        </table>
        <div id="<?php echo $uid; ?>_timeline"></div>
    </div>
    <div region="east" split="true" title="當日事件" style="width:340px;" id="<?php echo $uid; ?>_east">
        <div id="<?php echo $uid; ?>_day" class="pm-cal-list"></div>
        <div id="<?php echo $uid; ?>_marks"></div>
    </div>
</div>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', projects = <?php echo js_json(array_map(function ($p) { return array('value' => (string)$p['id'], 'text' => $p['code'] . ' ' . $p['name']); }, $projects)); ?>;
    var MARKS = [{k: 'is_star', t: '關注事件', i: '★', c: '#f0a500'}, {k: 'is_node', t: '工程節點', i: '⚑', c: '#d93025'}, {k: 'is_material', t: '工程材料', i: '⚙', c: '#e68a00'},
                 {k: 'is_person', t: '人員安排', i: '☻', c: '#1F5FA8'}, {k: 'is_plan', t: '工程計劃', i: '✎', c: '#2e8b57'}];
    // 假期開關記在瀏覽器本地：澳門假期默認顯示，中國假期默認顯示
    function pref(k){ try { return window.localStorage.getItem(k); } catch (e) { return null; } }
    function setPref(k, v){ try { window.localStorage.setItem(k, v); } catch (e) {} }
    $(U + '_hol').prop('checked', pref('pm_cal_mo') !== '0');
    $(U + '_cn').prop('checked', pref('pm_cal_cn') !== '0');
    function holTip(h){
        var t = [];
        if (h.mo) t.push('澳門：' + h.mo.n + '（' + h.mo.t + '假日）');
        if (h.cn) t.push('中國內地：' + h.cn.n + (h.cn.legal ? '（法定節日）' : '（放假安排）'));
        return t;
    }
    var ym = '', sel = '', data = null;
    function pad(n){ return n < 10 ? '0' + n : '' + n; }
    function fmt(d){ return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function parse(s){ var a = s.split('-'); return new Date(+a[0], +a[1] - 1, +a[2]); }
    function load(keep) {
        $.ajax({url: 'api/event.php', data: {act: 'month', ym: ym, project_id: $(U + '_project').val(), keyword: $(U + '_kw').val()}, dataType: 'json'}).done(function(res){
            if (!res || !res.ok) return;
            data = res; ym = res.ym;
            if (!keep || !sel) sel = res.today.substr(0, 7) === ym ? res.today : ym + '-01';
            draw();
        });
    }
    function eventsOn(date){ return $.grep(data.events, function(e){ return e.event_date === date; }); }
    function draw() {
        var a = ym.split('-'), $b = $(U + '_days').empty(), d = parse(data.from), showMo = $(U + '_hol').prop('checked'), showCn = $(U + '_cn').prop('checked');
        $(U + '_title').text(a[0] + ' 年 ' + (+a[1]) + ' 月');
        for (var w = 0; w < 6; w++) {
            var $tr = $('<tr/>').appendTo($b);
            for (var i = 0; i < 7; i++) {
                (function(date, dt){
                    var evs = eventsOn(date), h = PMHoliday.get(date), mo = showMo && h.mo, cn = showCn && h.cn;
                    var $td = $('<td/>').toggleClass('other', date.substr(0, 7) !== ym).toggleClass('today', date === data.today).toggleClass('sel', date === sel)
                        .toggleClass('we', dt.getDay() === 0 || dt.getDay() === 6).appendTo($tr);
                    var $n = $('<div class="n"/>').text(dt.getDate()).appendTo($td);
                    // 同一天兩地都放假時，底色以澳門為準
                    if (mo) { $td.addClass('hol mo'); $('<span class="mo"/>').text(mo.s).appendTo($n); }
                    if (cn) { $td.addClass(mo ? '' : 'cn'); $('<span class="cn"/>').text(cn.s).appendTo($n); }
                    if (mo || cn) $td.attr('title', holTip({mo: mo, cn: cn}).join('\n'));
                    $.each(evs.slice(0, 3), function(j, e){
                        $('<div class="e"/>').css('background', e.color).attr('title', e.title).text((e.start_time ? e.start_time + ' ' : '') + e.title).appendTo($td);
                    });
                    if (evs.length > 3) $('<div class="m"/>').text('+' + (evs.length - 3) + ' 更多').appendTo($td);
                    $td.click(function(){ sel = date; draw(); }).dblclick(function(){ edit(null, date); });
                })(fmt(d), new Date(d.getTime()));
                d.setDate(d.getDate() + 1);
            }
        }
        $(U + '_box').layout('panel', 'east').panel('setTitle', sel + ' 當日事件');
        var $day = $(U + '_day').empty(), evs = eventsOn(sel), hs = PMHoliday.get(sel);
        $.each(holTip({mo: showMo && hs.mo, cn: showCn && hs.cn}), function(i, t){ $('<div class="pm-cal-hol"/>').text(t).appendTo($day); });
        if (!evs.length) $('<div class="empty"/>').text('當天暫無事件，雙擊日期或點「新建事件」添加').appendTo($day);
        $.each(evs, function(i, e){ item(e, true).appendTo($day); });
        var $m = $(U + '_marks').empty();
        $.each(MARKS, function(i, mk){
            var list = data.marks[mk.k];
            if (!list.length) return;
            $('<div class="pm-cal-h"/>').html('<span style="color:' + mk.c + '">' + mk.i + '</span> ' + mk.t + '（' + list.length + '）').appendTo($m);
            var $l = $('<div class="pm-cal-list"/>').appendTo($m);
            $.each(list, function(j, e){ item(e, false).appendTo($l); });
        });
        var $t = $(U + '_timeline').empty();
        if (data.timeline) {
            $('<div class="pm-cal-h"/>').text('篩選結果時間線（' + data.timeline.length + ' 條）').appendTo($t);
            var $tl = $('<div class="pm-cal-list"/>').appendTo($t);
            if (!data.timeline.length) $('<div class="empty"/>').text('未找到相關記錄').appendTo($tl);
            $.each(data.timeline, function(j, e){ item(e, true).appendTo($tl); });
        }
    }
    function item(e, full) {
        var $i = $('<div class="pm-cal-item"/>').css('border-left-color', e.color);
        var $h = $('<div class="t"/>').appendTo($i);
        $.each(MARKS, function(j, mk){
            $('<a class="mk" href="javascript:void(0)"/>').text(mk.i).attr('title', mk.t).css('color', e[mk.k] == 1 ? mk.c : '#ccc').click(function(){
                if (!e.can_write) return PM.alert('只能標記自己創建的事件');
                PM.post('api/event.php?act=mark', {id: e.id, k: mk.k}, function(){ load(true); }, true);
            }).appendTo($h);
        });
        $('<b/>').text(' ' + e.title).appendTo($h);
        $('<div class="meta"/>').text(e.event_date + (e.start_time ? ' ' + e.start_time + (e.end_time ? '-' + e.end_time : '') : '') + (e.project_name ? '　' + e.project_name : '') + '　' + (e.creator_name || '')).appendTo($i);
        if (full) {
            if (e.content) $('<div class="c"/>').text(e.content).appendTo($i);
            var $f = $('<div class="f"/>').appendTo($i);
            $.each(e.files, function(j, x){
                if (/\.(jpe?g|png|gif)$/i.test(x.name)) $('<a target="_blank"/>').attr({href: 'api/file.php?inline=1&id=' + x.id, title: x.name}).append($('<img/>').attr('src', 'api/file.php?inline=1&id=' + x.id)).appendTo($f);
                else $('<a class="lnk"/>').attr('href', 'api/file.php?id=' + x.id).text(x.name).appendTo($f);
            });
            if (e.can_write) {
                var $o = $('<div class="o"/>').appendTo($i);
                $('<a href="javascript:void(0)"/>').text('修改').click(function(){ edit(e); }).appendTo($o);
                $('<a href="javascript:void(0)"/>').text('刪除').click(function(){
                    PM.confirm('確定刪除事件「' + e.title + '」嗎？', function(){ PM.post('api/event.php?act=delete', {id: e.id}, function(){ load(true); }); });
                }).appendTo($o);
            }
        } else {
            $i.css('cursor', 'pointer').click(function(ev){ if ($(ev.target).is('.mk')) return; ym = e.event_date.substr(0, 7); sel = e.event_date; load(true); });
        }
        return $i;
    }
    function edit(e, date) {
        var yn = [{value: '0', text: '否'}, {value: '1', text: '是'}], fields = [
            {name: 'title', label: '事件標題', required: true},
            {name: 'project_id', label: '所屬工程', type: 'select', options: [{value: '0', text: '（不關聯工程）'}].concat(projects)},
            {name: 'event_date', label: '日期', type: 'date', required: true},
            {name: 'start_time', label: '開始時間', type: 'time'},
            {name: 'end_time', label: '結束時間', type: 'time'},
            {name: 'color', label: '顏色', type: 'color'},
            {name: 'content', label: '內容', type: 'textarea', rows: 4}
        ];
        $.each(MARKS, function(i, mk){ fields.push({name: mk.k, label: mk.t, type: 'select', options: yn}); });
        fields.push({name: 'att', label: '圖片 / 附件', type: 'files', files: e ? e.files : []});
        PM.form({title: e ? '修改事件' : '新建事件', width: 620, url: 'api/event.php?act=save', extra: {id: e ? e.id : 0},
            data: e || {event_date: date || sel, color: '#3a87ad', project_id: $(U + '_project').val()}, fields: fields,
            onOk: function(){ load(true); }});
    }
    function shift(n){ var a = ym.split('-'), d = new Date(+a[0], +a[1] - 1 + n, 1); ym = d.getFullYear() + '-' + pad(d.getMonth() + 1); sel = ''; load(false); }
    $(U + '_prev').click(function(){ shift(-1); });
    $(U + '_next').click(function(){ shift(1); });
    $(U + '_today').click(function(){ ym = ''; sel = ''; load(false); });
    $(U + '_add').click(function(){ edit(null, sel); });
    $(U + '_search').click(function(){ load(true); });
    $(U + '_kw').keydown(function(ev){ if (ev.keyCode === 13) load(true); });
    $(U + '_project').change(function(){ load(true); });
    $(U + '_clear').click(function(){ $(U + '_kw').val(''); $(U + '_project').val('0'); load(true); });
    $(U + '_hol').change(function(){ setPref('pm_cal_mo', this.checked ? '1' : '0'); draw(); });
    $(U + '_cn').change(function(){ setPref('pm_cal_cn', this.checked ? '1' : '0'); draw(); });
    load(false);
});
</script>
