<?php
/** 消息提醒：待審批文件、待審批內部審批、系統消息 */
$docs = $admin ? array() : db_rows(
    "SELECT d.id, d.doc_no, d.title, d.submitted_at, COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name
     FROM pm_docs d JOIN pm_doc_steps s ON s.doc_id = d.id AND s.round = d.round LEFT JOIN pm_users u ON u.id = d.creator_id
     WHERE d.status = ? AND s.status = ? AND s.role_id = ? ORDER BY d.id DESC LIMIT 50", array(DOC_PENDING, STEP_CURRENT, $user['role_id']));
$apprs = db_rows(
    "SELECT a.id, a.type, a.appr_no, a.title, a.submitted_at, COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name
     FROM pm_apprs a JOIN pm_appr_steps s ON s.appr_id = a.id LEFT JOIN pm_users u ON u.id = a.user_id
     WHERE a.status = ? AND s.status = ? AND s.user_id = ? ORDER BY a.id DESC LIMIT 50", array(DOC_PENDING, STEP_CURRENT, $user['id']));
$types = array('inquiry' => '詢價審批', 'expense' => '費用審批', 'matter' => '事項審批');
?>
<div class="easyui-layout" fit="true" border="false">
    <div region="north" border="false" style="height:230px;padding:6px;overflow:auto;" split="true">
        <?php if (!$admin): ?>
        <div class="pm-box" style="padding:0 0 6px 0;"><h4>待審批文件（<?php echo count($docs); ?>）</h4>
        <table class="table table-bordered table-condensed table-striped" style="margin:0;">
            <thead><tr><th width="150">文件編號</th><th>文件標題</th><th width="120">提交人</th><th width="160">提交時間</th><th width="60">操作</th></tr></thead>
            <tbody>
            <?php foreach ($docs as $d): ?>
            <tr><td><?php echo h($d['doc_no']); ?></td><td><?php echo h($d['title']); ?></td><td><?php echo h($d['creator_name']); ?></td><td><?php echo h($d['submitted_at']); ?></td>
                <td><a href="javascript:void(0)" onclick="PM.viewDoc(<?php echo (int)$d['id']; ?>)">審批</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$docs): ?><tr><td colspan="5" style="text-align:center;color:#999;">暫無待審批文件</td></tr><?php endif; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
        <div class="pm-box" style="padding:0;"><h4>待審批內部審批（<?php echo count($apprs); ?>）</h4>
        <table class="table table-bordered table-condensed table-striped" style="margin:0;">
            <thead><tr><th width="150">審批編號</th><th width="90">類別</th><th>標題</th><th width="120">申請人</th><th width="160">提交時間</th><th width="60">操作</th></tr></thead>
            <tbody>
            <?php foreach ($apprs as $a): ?>
            <tr><td><?php echo h($a['appr_no']); ?></td><td><?php echo h($types[$a['type']]); ?></td><td><?php echo h($a['title']); ?></td><td><?php echo h($a['creator_name']); ?></td><td><?php echo h($a['submitted_at']); ?></td>
                <td><a href="javascript:void(0)" onclick="PM.viewAppr(<?php echo (int)$a['id']; ?>)">審批</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$apprs): ?><tr><td colspan="6" style="text-align:center;color:#999;">暫無待審批事項</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>
    <div region="center" border="false" title="系統消息">
        <div id="<?php echo $uid; ?>_tb" class="search-div"><div class="search-toolbar"><span style="float:left;">
            <a class="easyui-linkbutton" icon="icon-search" plain="true" id="<?php echo $uid; ?>_view">查看</a>
            <a class="easyui-linkbutton" icon="icon-ok" plain="true" id="<?php echo $uid; ?>_all">全部標為已讀</a>
            <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">刷新</a>
        </span></div></div>
        <table id="<?php echo $uid; ?>_dg" toolbar="#<?php echo $uid; ?>_tb"></table>
    </div>
</div>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', dg = U + '_dg';
    function bold(v, row){ return row.is_read == 1 ? PM.esc(v) : '<b>' + PM.esc(v) + '</b>'; }
    PM.grid(dg, {
        url: 'api/notice.php?act=list',
        columns: [[
            {field: 'is_read', title: '狀態', width: 60, align: 'center', formatter: function(v){ return v == 1 ? '已讀' : '<b style="color:#d93025">未讀</b>'; }},
            {field: 'title', title: '標題', width: 320, formatter: bold},
            {field: 'creator_name', title: '發布人', width: 110, align: 'center'},
            {field: 'created_at', title: '發布時間', width: 150, align: 'center'},
            {field: 'files_att', title: '附件', width: 200, formatter: PM.fileLinks}
        ]],
        onDblClickRow: function(i, row){ view(row); }
    });
    function view(row) {
        PM.detail('系統消息', [{name: 'title', label: '標題'}, {name: 'creator_name', label: '發布人'}, {name: 'created_at', label: '發布時間'},
            {name: 'content', label: '內容'}, {name: 'att', label: '附件', type: 'files', files: row.files_att}], row);
        if (row.is_read != 1) PM.post('api/notice.php?act=read', {id: row.id}, function(){ PM.reload(dg); PM.ping(); });
    }
    $(U + '_view').click(function(){ var r = PM.selected(dg); if (r) view(r); });
    $(U + '_all').click(function(){ PM.post('api/notice.php?act=read', {}, function(){ PM.reload(dg); PM.ping(); }); });
    $(U + '_reload').click(function(){ PM.reload(dg); });
});
</script>
