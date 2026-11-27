<?php
/** 用戶注冊（由管理員在「注冊管理」中啟用和配置） */
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/install.php';
require_once __DIR__ . '/inc/reg.php';
pm_install_if_needed();
$set = reg_settings();

// 用戶名檢測（輸入時調用）
if (isset($_GET['check'])) {
    if (!$set['enabled']) json_out(array('ok' => false, 'msg' => '用戶注冊功能未開放'), 403);
    json_out(array('ok' => true) + reg_check_username(param('check')));
}
if (is_logged_in()) {
    header('Location: main.php');
    exit;
}

$defs = reg_field_defs();
$fields = array();
foreach ($set['fields'] as $k => $mode) {
    if ($mode > 0) $fields[$k] = $mode;
}
$roles = isset($fields['role_id']) ? reg_user_roles() : array();
$positions = isset($fields['position_id']) ? db_rows('SELECT id, name FROM pm_positions ORDER BY sort, id') : array();
// 沒有可選項時不顯示該下拉字段
if (isset($fields['role_id']) && !$roles) unset($fields['role_id']);
if (isset($fields['position_id']) && !$positions) unset($fields['position_id']);

$error = '';
$done = '';
$v = array('username' => '');
foreach ($fields as $k => $mode) $v[$k] = '';

if ($set['enabled'] && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['username'] = param('username');
    foreach ($fields as $k => $mode) $v[$k] = param($k);
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $password2 = isset($_POST['password2']) && is_string($_POST['password2']) ? $_POST['password2'] : '';
    $captcha = isset($_SESSION['pm_captcha']) ? $_SESSION['pm_captcha'] : '';
    unset($_SESSION['pm_captcha']);
    $chk = reg_check_username($v['username']);

    if ($captcha === '' || !hash_equals($captcha, param('vercode'))) {
        $error = '驗證碼錯誤';
    } elseif (!$chk['valid'] || $chk['exists']) {
        $error = $chk['msg'];
    } elseif (strlen($password) < 6 || strlen($password) > 64) {
        $error = '密碼長度須為 6-64 位';
    } elseif ($password !== $password2) {
        $error = '兩次輸入的密碼不一致';
    } elseif ($set['role_id'] === 0) {
        $error = '系統尚未設置普通用戶角色，暫時無法注冊，請聯繫管理員';
    }
    $data = array('role_id' => $set['role_id'], 'position_id' => 0);
    foreach ($fields as $k => $mode) {
        if ($error !== '') break;
        $val = $v[$k];
        if ($val === '' || (($k === 'role_id' || $k === 'position_id') && (int)$val === 0)) {
            if ($mode === 2) $error = '請填寫' . $defs[$k][0];
            continue;
        }
        if ($k === 'email') {
            if (!filter_var($val, FILTER_VALIDATE_EMAIL) || strlen($val) > 100) $error = '郵箱格式不正確';
        } elseif ($k === 'phone') {
            if (!preg_match('/^[0-9+\-() ]{5,30}$/', $val)) $error = '電話格式不正確';
        } elseif ($k === 'role_id') {
            $val = (int)$val;
            if (!in_array($val, array_map(function ($r) { return (int)$r['id']; }, $roles), true)) $error = '請選擇角色';
        } elseif ($k === 'position_id') {
            $val = (int)$val;
            if (!in_array($val, array_map(function ($r) { return (int)$r['id']; }, $positions), true)) $error = '請選擇職位';
        } else {
            $val = mb_substr($val, 0, $k === 'company' ? 100 : 50);
        }
        $data[$k] = $val;
    }
    // 同一 IP 每小時最多注冊 10 個帳號
    if ($error === '' && (int)db_val("SELECT COUNT(*) FROM pm_login_logs WHERE result = '注冊' AND ip = ? AND created_at > ?",
            array(client_ip(), date('Y-m-d H:i:s', time() - 3600))) >= 10) {
        $error = '注冊過於頻繁，請稍後再試';
    }
    if ($error === '') {
        $data += array(
            'username' => $v['username'], 'password' => password_hash($password, PASSWORD_DEFAULT),
            'status' => $set['audit'] ? 0 : 1, 'reg_state' => $set['audit'] ? 1 : 0,
            'credits' => CREDIT_INIT_MIN * 60, 'created_at' => now(),
        );
        try {
            $newId = db_tx(function () use ($data) {
                $id = db_insert('pm_users', $data);
                db_insert('pm_credit_logs', array('target_id' => $id, 'delta' => CREDIT_INIT_MIN, 'balance' => CREDIT_INIT_MIN, 'remark' => '新用戶初始積分（自行注冊）',
                    'user_id' => 0, 'created_at' => now()));
                return $id;
            });
            db_insert('pm_login_logs', array(
                'user_id' => $newId, 'username' => $v['username'], 'ip' => client_ip(),
                'user_agent' => mb_substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 255),
                'result' => '注冊', 'created_at' => now(),
            ));
            $done = $set['audit'] ? '注冊申請已提交，請等待管理員審核通過後再登錄。' : '注冊成功，現在可以登錄了。';
        } catch (PDOException $e) {
            // 併發注冊同一用戶名（唯一索引）
            $error = '該用戶名已被使用，請更改';
        }
    }
}

function reg_label($k, $mode) {
    $defs = reg_field_defs();
    return ($mode === 2 ? '<em>*</em>' : '') . h($defs[$k][0]) . ($mode === 1 ? '（選填）' : '');
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8"/>
    <title>用戶注冊 - <?php echo APP_NAME; ?></title>
    <link href="favicon.ico" rel="SHORTCUT ICON"/>
    <link href="resource/css/login.css" rel="stylesheet" type="text/css"/>
</head>
<body>
<div class="login-wrap tall">
    <div class="login-box wide">
        <div class="login-lang"><a href="javascript:void(0)" onclick="PMLang.toggle()" id="pm_lang_btn">简体</a></div>
        <div class="login-title"><?php echo APP_NAME; ?></div>
        <div class="login-sub">用戶注冊</div>
        <?php if (!$set['enabled']): ?>
        <div class="login-error">用戶注冊功能未開放，請聯繫管理員開通帳號。</div>
        <?php elseif ($done !== ''): ?>
        <div class="login-ok"><?php echo h($done); ?></div>
        <?php else: ?>
        <?php if ($error !== ''): ?><div class="login-error"><?php echo h($error); ?></div><?php endif; ?>
        <form method="post" action="register.php" autocomplete="off">
            <div class="form-item">
                <label><em>*</em>用戶名</label>
                <input type="text" name="username" id="reg_username" value="<?php echo h($v['username']); ?>" placeholder="2-50 位字母、數字、下劃線、點或橫線" maxlength="50" autofocus/>
                <div class="form-hint" id="reg_hint"></div>
            </div>
            <div class="form-row">
                <div class="form-item">
                    <label><em>*</em>密碼</label>
                    <input type="password" name="password" placeholder="6-64 位" maxlength="64"/>
                </div>
                <div class="form-item">
                    <label><em>*</em>確認密碼</label>
                    <input type="password" name="password2" placeholder="再次輸入密碼" maxlength="64"/>
                </div>
            </div>
            <?php foreach ($fields as $k => $mode): ?>
            <div class="form-item">
                <label><?php echo reg_label($k, $mode); ?></label>
                <?php if ($k === 'role_id' || $k === 'position_id'): ?>
                <select name="<?php echo $k; ?>">
                    <option value="0">請選擇</option>
                    <?php foreach ($k === 'role_id' ? $roles : $positions as $o): ?>
                    <option value="<?php echo (int)$o['id']; ?>"<?php echo (int)$v[$k] === (int)$o['id'] ? ' selected="selected"' : ''; ?>><?php echo h($o['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="<?php echo $k === 'email' ? 'email' : 'text'; ?>" name="<?php echo $k; ?>" value="<?php echo h($v[$k]); ?>" maxlength="<?php echo $k === 'phone' ? 30 : ($k === 'real_name' ? 50 : 100); ?>"/>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="form-item">
                <label><em>*</em>驗證碼</label>
                <div class="captcha-row">
                    <input type="text" name="vercode" placeholder="請輸入驗證碼" maxlength="4"/>
                    <img src="captcha.php" alt="驗證碼" title="點擊刷新" onclick="this.src='captcha.php?t='+Date.now()"/>
                </div>
            </div>
            <button type="submit" class="login-btn">注冊</button>
        </form>
        <?php endif; ?>
        <div class="login-link"><?php echo $done !== '' ? '' : '已有帳號？'; ?><a href="index.php">返回登錄</a></div>
        <div class="login-footer"><?php echo APP_COPYRIGHT; ?></div>
    </div>
</div>
<script type="text/javascript">
// 輸入用戶名時自動檢測是否已被使用，並給出可用的建議
(function () {
    var input = document.getElementById('reg_username'), hint = document.getElementById('reg_hint'), timer = null, seq = 0;
    if (!input) return;
    function show(cls, text, suggest) {
        hint.className = 'form-hint ' + cls;
        hint.innerHTML = '';
        hint.appendChild(document.createTextNode(text));
        for (var i = 0; i < (suggest || []).length; i++) {
            var a = document.createElement('a');
            a.appendChild(document.createTextNode(suggest[i]));
            a.onclick = (function (name) { return function () { input.value = name; check(); }; })(suggest[i]);
            hint.appendChild(document.createTextNode(' '));
            hint.appendChild(a);
        }
    }
    function check() {
        var name = input.value.replace(/^\s+|\s+$/g, ''), my = ++seq;
        if (name === '') return show('', '');
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'register.php?check=' + encodeURIComponent(name), true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4 || my !== seq) return;
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (e) {}
            if (!res || !res.ok) return show('', '');
            if (!res.valid) show('bad', res.msg);
            else if (res.exists) show('bad', '該用戶名已被使用，請更改。' + (res.suggest.length ? '建議使用：' : ''), res.suggest);
            else show('ok', '✓ ' + res.msg);
        };
        xhr.send();
    }
    input.oninput = function () { clearTimeout(timer); timer = setTimeout(check, 400); };
    input.onblur = check;
    if (input.value !== '') check();
})();
</script>
<script type="text/javascript" charset="utf-8" src="resource/js/pm.lang.js?v=<?php echo @filemtime(__DIR__ . '/resource/js/pm.lang.js'); ?>"></script>
</body>
</html>
