<?php
/** 控制面板：系統功能開關 */
require_once __DIR__ . '/../inc/panel.php';
$own = panel_get('cal_own_only');
?>
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
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>';
    $(U + '_save').click(function(){ PM.post('api/panel.php?act=save', $(U + '_form').serialize()); });
});
</script>
