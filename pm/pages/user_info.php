<?php /** 個人信息 */ ?>
<div class="pm-box">
    <form class="pm-form" id="<?php echo $uid; ?>_form" onsubmit="return false;">
        <table style="width:auto;">
            <tr><td class="pm-form-label">帳號：</td><td class="pm-form-field"><span class="pm-form-static"><?php echo h($user['username']); ?></span></td></tr>
            <tr><td class="pm-form-label">角色：</td><td class="pm-form-field"><span class="pm-form-static"><?php echo h($user['role_name']); ?>（<?php echo $admin ? '管理員' : '普通用戶'; ?>）</span></td></tr>
            <tr><td class="pm-form-label">姓名：</td><td class="pm-form-field"><input type="text" name="real_name" style="width:280px;" value="<?php echo h($user['real_name']); ?>"/></td></tr>
            <tr><td class="pm-form-label">單位：</td><td class="pm-form-field"><input type="text" name="company" style="width:280px;" value="<?php echo h($user['company']); ?>"/></td></tr>
            <tr><td class="pm-form-label">電話：</td><td class="pm-form-field"><input type="text" name="phone" style="width:280px;" value="<?php echo h($user['phone']); ?>"/></td></tr>
            <tr><td class="pm-form-label">郵箱：</td><td class="pm-form-field"><input type="text" name="email" style="width:280px;" value="<?php echo h($user['email']); ?>"/></td></tr>
            <tr><td class="pm-form-label">最後登錄：</td><td class="pm-form-field"><span class="pm-form-static"><?php echo h($user['last_login']); ?></span></td></tr>
            <tr><td></td><td><a class="btn btn-primary btn-small" id="<?php echo $uid; ?>_save">保 存</a></td></tr>
        </table>
    </form>
</div>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>';
    $(U + '_save').click(function(){ PM.post('api/me.php?act=profile', $(U + '_form').serialize()); });
});
</script>
