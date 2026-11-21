<?php /** 修改密碼 */ ?>
<div class="pm-box">
    <form class="pm-form" id="<?php echo $uid; ?>_form" onsubmit="return false;" autocomplete="off">
        <table style="width:auto;">
            <tr><td class="pm-form-label">原密碼：</td><td class="pm-form-field"><input type="password" name="old" style="width:240px;"/></td></tr>
            <tr><td class="pm-form-label">新密碼：</td><td class="pm-form-field"><input type="password" name="new" style="width:240px;"/><div class="pm-form-hint">6-64 位</div></td></tr>
            <tr><td class="pm-form-label">確認新密碼：</td><td class="pm-form-field"><input type="password" name="confirm" style="width:240px;"/></td></tr>
            <tr><td></td><td><a class="btn btn-primary btn-small" id="<?php echo $uid; ?>_save">確 定</a></td></tr>
        </table>
    </form>
</div>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>';
    $(U + '_save').click(function(){
        var $f = $(U + '_form');
        PM.post('api/me.php?act=password', $f.serialize(), function(){ $f[0].reset(); });
    });
});
</script>
