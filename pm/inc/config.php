<?php
/**
 * 澳門工程管理網站 — 全局配置與公共函數
 */
define('APP_NAME', '澳門工程管理網站');
define('APP_COPYRIGHT', 'Copyright © 澳門工程管理網站 ' . date('Y'));
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('BACKUP_DIR', APP_ROOT . '/backups');
define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024);

date_default_timezone_set('Asia/Macau');
ini_set('display_errors', '0');
// 未捕獲異常：記錄日志，不向瀏覽器輸出細節
set_exception_handler(function ($e) {
    error_log('[pm] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array('ok' => false, 'msg' => '系統內部錯誤，請稍後重試或聯繫管理員'), JSON_UNESCAPED_UNICODE);
    exit;
});
mb_internal_encoding('UTF-8');

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PMSESSID');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(array('lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'));
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', false, true);
    }
    session_start();
}

require_once __DIR__ . '/db.php';

/** 文件狀態 */
define('DOC_DRAFT', 0);
define('DOC_PENDING', 1);
define('DOC_APPROVED', 2);
define('DOC_REJECTED', 3);

/** 審批步驟狀態 */
define('STEP_WAIT', 0);
define('STEP_CURRENT', 1);
define('STEP_PASS', 2);
define('STEP_REJECT', 3);
define('STEP_SUBMIT', 4);

/** 業務校驗異常（訊息可直接展示給用戶） */
class PmError extends Exception {}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function ok($msg = '操作成功', $extra = array()) {
    json_out(array_merge(array('ok' => true, 'msg' => $msg), $extra));
}

function fail($msg, $code = 200) {
    json_out(array('ok' => false, 'msg' => $msg), $code);
}

/** 讀取請求參數（字符串，已去除首尾空白） */
function param($name, $default = '') {
    $v = isset($_POST[$name]) ? $_POST[$name] : (isset($_GET[$name]) ? $_GET[$name] : $default);
    return is_string($v) ? trim($v) : $default;
}

function param_int($name, $default = 0) {
    $v = param($name, null);
    return ($v === null || $v === '' || !is_numeric($v)) ? $default : (int)$v;
}

/** 當前登錄用戶（每次請求從數據庫讀取，停用/刪除即時生效） */
function current_user() {
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (!empty($_SESSION['pm_uid'])) {
        $row = db_row('SELECT u.id,u.username,u.real_name,u.role_id,u.company,u.phone,u.email,u.status,u.last_login,u.created_at,
                              r.name AS role_name, r.type AS role_type
                       FROM pm_users u LEFT JOIN pm_roles r ON r.id = u.role_id WHERE u.id = ?', array($_SESSION['pm_uid']));
        if ($row && (int)$row['status'] === 1) {
            $user = $row;
        } else {
            unset($_SESSION['pm_uid']);
        }
    }
    return $user;
}

function is_logged_in() {
    return current_user() !== null;
}

function is_admin() {
    $u = current_user();
    return $u && $u['role_type'] === 'admin';
}

/** 頁面用：未登錄跳轉登錄頁 */
function require_login() {
    if (!is_logged_in()) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            echo '<script>top.location.href="index.php";</script>';
        } else {
            header('Location: index.php');
        }
        exit;
    }
}

/** 頁面用：必須為管理員 */
function require_admin_page() {
    require_login();
    if (!is_admin()) {
        echo '<div style="padding:20px;color:#c00;">無權限訪問此頁面</div>';
        exit;
    }
}

function csrf_token() {
    if (empty($_SESSION['pm_csrf'])) {
        $_SESSION['pm_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['pm_csrf'];
}

/**
 * API 入口守衛：登錄校驗、CSRF 校驗、（可選）管理員校驗
 */
function api_guard($admin = false, $csrf = true) {
    if (!is_logged_in()) {
        json_out(array('ok' => false, 'timeout' => true, 'msg' => '登錄已超時，請重新登錄'), 401);
    }
    if ($csrf) {
        $t = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : param('_csrf');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_string($t) || !hash_equals(csrf_token(), $t)) {
            fail('請求校驗失敗，請刷新頁面後重試', 403);
        }
    }
    if ($admin && !is_admin()) {
        fail('無權限執行此操作', 403);
    }
}

/** datagrid 分頁參數 */
function page_args() {
    $page = max(1, param_int('page', 1));
    $rows = min(200, max(1, param_int('rows', 20)));
    return array($rows, ($page - 1) * $rows);
}

function client_ip() {
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
}

function now() {
    return date('Y-m-d H:i:s');
}

function doc_status_text($s) {
    $m = array(DOC_DRAFT => '草稿', DOC_PENDING => '審批中', DOC_APPROVED => '已通過', DOC_REJECTED => '已退回');
    return isset($m[(int)$s]) ? $m[(int)$s] : '';
}
