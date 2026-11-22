<?php
/** 數據庫管理 API（超級管理員）：備份、下載、刪除、恢復 */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/install.php';
$act = param('act', 'list');

define('BACKUP_MAGIC', '-- PM-BACKUP v1');
define('BACKUP_NAME_RE', '/^pm_backup_\d{8}_\d{6}_[0-9a-f]{32}\.sql$/');

function backup_tables() {
    $names = array();
    foreach (pm_schema() as $sql) {
        if (preg_match('/CREATE TABLE IF NOT EXISTS `(pm_[a-z_]+)`/', $sql, $m)) $names[] = $m[1];
    }
    return $names;
}

function backup_path($name) {
    return preg_match(BACKUP_NAME_RE, $name) ? BACKUP_DIR . '/' . $name : null;
}

/** 生成備份文件（每行一條語句），返回文件名 */
function backup_create($tag = '') {
    if (!is_dir(BACKUP_DIR)) @mkdir(BACKUP_DIR, 0750, true);
    $name = 'pm_backup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(16)) . '.sql';
    $fp = @fopen(BACKUP_DIR . '/' . $name, 'wb');
    if (!$fp) throw new PmError('無法寫入備份目錄，請檢查 backups 目錄權限');
    $pdo = db();
    fwrite($fp, BACKUP_MAGIC . "\n-- " . APP_NAME . " 數據庫備份" . ($tag !== '' ? "（{$tag}）" : '') . "\n-- time: " . now() . "\nSET NAMES utf8mb4;\n");
    foreach (backup_tables() as $t) {
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
        fwrite($fp, "DROP TABLE IF EXISTS `$t`;\n" . preg_replace('/\s*[\r\n]+\s*/', ' ', $create[1]) . ";\n");
        $st = $pdo->query("SELECT * FROM `$t`");
        $batch = array();
        $cols = null;
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            if ($cols === null) $cols = '`' . implode('`,`', array_keys($row)) . '`';
            $vals = array();
            foreach ($row as $v) $vals[] = $v === null ? 'NULL' : $pdo->quote((string)$v);
            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= 100) {
                fwrite($fp, "INSERT INTO `$t` ($cols) VALUES " . implode(',', $batch) . ";\n");
                $batch = array();
            }
        }
        if ($batch) fwrite($fp, "INSERT INTO `$t` ($cols) VALUES " . implode(',', $batch) . ";\n");
    }
    fclose($fp);
    return $name;
}

/** 校驗備份文件：僅允許本程序生成的格式與 pm_ 數據表；返回語句數 */
function backup_validate($path) {
    $fp = @fopen($path, 'rb');
    if (!$fp) throw new PmError('無法讀取備份文件');
    $tables = backup_tables();
    $first = rtrim((string)fgets($fp), "\r\n");
    if ($first !== BACKUP_MAGIC) {
        fclose($fp);
        throw new PmError('不是本程序生成的備份文件');
    }
    $n = 0;
    $created = array();
    while (($line = fgets($fp)) !== false) {
        $line = rtrim($line, "\r\n");
        if ($line === '' || strpos($line, '-- ') === 0) continue;
        if ($line === 'SET NAMES utf8mb4;') { $n++; continue; }
        if (!preg_match('/^(DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO) `(pm_[a-z_]+)`[ ;]/', $line, $m) || !in_array($m[2], $tables, true) || substr($line, -1) !== ';') {
            fclose($fp);
            throw new PmError('備份文件內容不合法，已拒絕恢復');
        }
        if ($m[1] === 'CREATE TABLE') $created[] = $m[2];
        $n++;
    }
    fclose($fp);
    // 舊版本備份可以只包含第一期的數據表，恢復後由 pm_migrate() 補齊
    $core = array('pm_roles', 'pm_users', 'pm_projects', 'pm_flows', 'pm_flow_steps', 'pm_docs', 'pm_doc_steps', 'pm_login_logs');
    if (array_diff($core, $created)) throw new PmError('備份文件不完整（缺少數據表），已拒絕恢復');
    return $n;
}

function backup_has_table($t) {
    return (bool)db_val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', array($t));
}

function backup_norm($create) {
    return preg_replace(array('/\s*[\r\n]+\s*/', '/ AUTO_INCREMENT=\d+/'), array(' ', ''), $create);
}

/**
 * 恢復備份。結構與當前一致的表只做 DELETE + INSERT（單一事務、不做 DDL，
 * 在磁盤較慢的服務器上也能很快完成）；結構不同的表才重建。
 * 備份中沒有的表（舊版本備份）會被清空，之後由 pm_migrate() 補齊。
 */
function backup_exec($path) {
    $pdo = db();
    $fp = fopen($path, 'rb');
    $inBackup = array();
    $rebuilt = array();
    while (($line = fgets($fp)) !== false) {
        if (!preg_match('/^CREATE TABLE `(pm_[a-z_]+)`/', $line, $m)) continue;
        $t = $m[1];
        $inBackup[$t] = true;
        $sql = substr(rtrim($line, "\r\n"), 0, -1);
        if (backup_has_table($t)) {
            $cur = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
            if (backup_norm($cur[1]) === backup_norm($sql)) continue;
        }
        $pdo->exec("DROP TABLE IF EXISTS `$t`");
        $pdo->exec($sql);
        $rebuilt[$t] = true;
    }
    $pdo->beginTransaction();
    try {
        foreach (backup_tables() as $t) {
            if (isset($rebuilt[$t])) continue;
            if (isset($inBackup[$t]) || backup_has_table($t)) $pdo->exec("DELETE FROM `$t`");
        }
        rewind($fp);
        while (($line = fgets($fp)) !== false) {
            if (strpos($line, 'INSERT INTO `') === 0) $pdo->exec(substr(rtrim($line, "\r\n"), 0, -1));
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fclose($fp);
        throw $e;
    }
    fclose($fp);
}

if ($act === 'list') {
    api_guard('super', false);
    $rows = array();
    foreach ((array)glob(BACKUP_DIR . '/pm_backup_*.sql') as $f) {
        $name = basename($f);
        if (!preg_match(BACKUP_NAME_RE, $name)) continue;
        $fp = fopen($f, 'rb');
        fgets($fp);
        $remark = preg_match('/（(.+)）/u', (string)fgets($fp), $m) ? $m[1] : '手動備份';
        fclose($fp);
        $rows[] = array('name' => $name, 'size' => filesize($f), 'time' => date('Y-m-d H:i:s', filemtime($f)), 'remark' => $remark);
    }
    usort($rows, function ($a, $b) { return strcmp($b['name'], $a['name']); });
    json_out(array('total' => count($rows), 'rows' => $rows));
}

if ($act === 'download') {
    api_guard('super', false);
    $path = backup_path(param('name'));
    if (!$path || !is_file($path)) {
        http_response_code(404);
        exit('備份文件不存在');
    }
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    readfile($path);
    exit;
}

api_guard('super');
@set_time_limit(300);

try {
    if ($act === 'backup') {
        $name = backup_create();
        ok('備份成功：' . $name, array('name' => $name));
    }

    if ($act === 'delete') {
        $path = backup_path(param('name'));
        if (!$path || !is_file($path)) fail('備份文件不存在');
        @unlink($path);
        ok('刪除成功');
    }

    if ($act === 'restore') {
        $uploaded = null;
        if (!empty($_FILES['file']) && !is_array($_FILES['file']['error']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['file'];
            if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) fail('備份文件上傳失敗（文件不能超過 50MB）');
            if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'sql') fail('請上傳 .sql 備份文件');
            $path = $uploaded = $f['tmp_name'];
        } else {
            $path = backup_path(param('name'));
            if (!$path || !is_file($path)) fail('備份文件不存在');
        }
        backup_validate($path);
        $safety = backup_create('恢復前自動備份');
        try {
            backup_exec($path);
            pm_migrate();
        } catch (Exception $e) {
            // 恢復失敗：回滾到恢復前的自動備份
            backup_exec(BACKUP_DIR . '/' . $safety);
            fail('恢復失敗，數據已還原到恢復前狀態');
        }
        ok('數據恢復成功（恢復前的數據已自動備份為 ' . $safety . '）');
    }
} catch (PmError $e) {
    fail($e->getMessage());
}
fail('未知操作');
