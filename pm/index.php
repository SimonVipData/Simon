<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/install.php';
pm_install_if_needed();

if (is_logged_in()) {
    header('Location: main.php');
    exit;
}

$regOn = (bool)db_val("SELECT v FROM pm_meta WHERE k = 'reg_enabled' AND v = '1'");
$error = isset($_SESSION['pm_flash']) ? (string)$_SESSION['pm_flash'] : '';
unset($_SESSION['pm_flash']);
$name = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = '';
    $name = param('name');
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $code = param('vercode');
    $captcha = isset($_SESSION['pm_captcha']) ? $_SESSION['pm_captcha'] : '';
    unset($_SESSION['pm_captcha']);

    if ($captcha === '' || !hash_equals($captcha, $code)) {
        $error = '驗證碼錯誤';
    } elseif ($name === '' || $password === '') {
        $error = '請輸入帳號和密碼';
    } else {
        $u = db_row('SELECT u.id, u.username, u.password, u.status, u.credits, u.reg_state, r.type AS role_type
                     FROM pm_users u LEFT JOIN pm_roles r ON r.id = u.role_id WHERE u.username = ?', array($name));
        $pass = $u && password_verify($password, $u['password']);
        if ($pass && (int)$u['status'] !== 1) {
            $error = (int)$u['reg_state'] === 1 ? '您的注冊申請正在等待管理員審核' : '該帳號已被停用';
        } elseif (!$pass) {
            $error = '帳號或密碼錯誤';
        } elseif ($u['role_type'] === 'user' && (int)$u['credits'] <= 0) {
            $error = '您的積分已用完，請聯繫管理員充值';
        }
        db_insert('pm_login_logs', array(
            'user_id' => $u ? $u['id'] : 0, 'username' => mb_substr($name, 0, 50), 'ip' => client_ip(),
            'user_agent' => mb_substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 255),
            'result' => $error === '' ? '成功' : '失敗', 'created_at' => now(),
        ));
        if ($error === '') {
            session_regenerate_id(true);
            $_SESSION['pm_uid'] = (int)$u['id'];
            unset($_SESSION['pm_csrf']);
            db_q('UPDATE pm_users SET last_login = ?, credit_at = ?, active_at = ? WHERE id = ?', array(now(), now(), now(), $u['id']));
            header('Location: main.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8"/>
    <title><?php echo APP_NAME; ?></title>
    <link href="favicon.ico" rel="SHORTCUT ICON"/>
    <link href="resource/css/login.css" rel="stylesheet" type="text/css"/>
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <div class="login-lang" id="pm_lang_btn"></div>
        <div class="login-title"><?php echo APP_NAME; ?></div>
        <div class="login-sub">工程文件協作與審批平臺</div>
        <?php if ($error !== ''): ?><div class="login-error"><?php echo h($error); ?></div><?php endif; ?>
        <form method="post" action="index.php" autocomplete="off">
            <div class="form-item">
                <label>帳號</label>
                <input type="text" name="name" value="<?php echo h($name); ?>" placeholder="請輸入帳號" autofocus/>
            </div>
            <div class="form-item">
                <label>密碼</label>
                <input type="password" name="password" placeholder="請輸入密碼"/>
            </div>
            <div class="form-item">
                <label>驗證碼</label>
                <div class="captcha-row">
                    <input type="text" name="vercode" placeholder="請輸入驗證碼" maxlength="4"/>
                    <img src="captcha.php" alt="驗證碼" title="點擊刷新" onclick="this.src='captcha.php?t='+Date.now()"/>
                </div>
            </div>
            <button type="submit" class="login-btn">登錄</button>
        </form>
        <?php if ($regOn): ?><div class="login-link">還沒有帳號？<a href="register.php">立即注冊</a></div><?php endif; ?>
        <div class="login-footer"><?php echo APP_COPYRIGHT; ?></div>
    </div>
</div>
<?php echo lang_script(); ?>
<script type="text/javascript" charset="utf-8" src="resource/js/pm.lang.js?v=<?php echo @filemtime(__DIR__ . '/resource/js/pm.lang.js'); ?>"></script>
</body>
</html>
