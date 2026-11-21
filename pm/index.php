<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/install.php';
pm_install_if_needed();

if (is_logged_in()) {
    header('Location: main.php');
    exit;
}

$error = '';
$name = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $u = db_row('SELECT id, username, password, status FROM pm_users WHERE username = ?', array($name));
        $pass = $u && password_verify($password, $u['password']);
        if ($pass && (int)$u['status'] !== 1) {
            $error = '該帳號已被停用';
        } elseif (!$pass) {
            $error = '帳號或密碼錯誤';
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
            db_q('UPDATE pm_users SET last_login = ? WHERE id = ?', array(now(), $u['id']));
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
    <style>
        body{margin:0;padding:0;font-family:"Microsoft YaHei","PingFang SC",sans-serif;background:#3a6ea5;}
        .login-wrap{position:relative;height:100vh;background:linear-gradient(135deg,#2b5f8f 0%,#3a6ea5 100%);display:flex;align-items:center;justify-content:center;}
        .login-box{width:420px;background:#fff;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.3);padding:40px 40px 30px;box-sizing:border-box;}
        .login-title{text-align:center;font-size:22px;color:#2b5f8f;margin-bottom:6px;font-weight:bold;}
        .login-sub{text-align:center;font-size:13px;color:#888;margin-bottom:24px;}
        .form-item{margin-bottom:16px;}
        .form-item label{display:block;font-size:13px;color:#555;margin-bottom:6px;}
        .form-item input{width:100%;height:38px;border:1px solid #d5d5d5;border-radius:4px;padding:0 12px;font-size:14px;box-sizing:border-box;outline:none;}
        .form-item input:focus{border-color:#3a6ea5;}
        .captcha-row{display:flex;align-items:center;gap:10px;}
        .captcha-row input{flex:1;}
        .captcha-row img{height:38px;border:1px solid #d5d5d5;border-radius:4px;cursor:pointer;}
        .login-btn{width:100%;height:42px;background:#3a6ea5;border:none;border-radius:4px;color:#fff;font-size:16px;cursor:pointer;margin-top:8px;}
        .login-btn:hover{background:#2b5f8f;}
        .login-error{background:#fdecea;color:#d93025;padding:8px 12px;border-radius:4px;font-size:13px;margin-bottom:16px;text-align:center;}
        .login-footer{text-align:center;font-size:12px;color:#aaa;margin-top:20px;}
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
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
        <div class="login-footer"><?php echo APP_COPYRIGHT; ?></div>
    </div>
</div>
</body>
</html>
