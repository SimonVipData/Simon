<?php
/**
 * 數據庫訪問層（PDO + MySQL/MariaDB）
 */
function db_config() {
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/db.config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('缺少數據庫配置文件 inc/db.config.php（可參考 inc/db.config.sample.php）');
        }
        $cfg = require $file;
    }
    return $cfg;
}

function db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $c = db_config();
    try {
        $pdo = new PDO(
            'mysql:host=' . $c['host'] . ';port=' . $c['port'] . ';dbname=' . $c['name'] . ';charset=utf8mb4',
            $c['user'], $c['pass'],
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            )
        );
    } catch (Exception $e) {
        http_response_code(500);
        exit('數據庫連接失敗，請檢查 inc/db.config.php 配置');
    }
    return $pdo;
}

function db_q($sql, $params = array()) {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function db_rows($sql, $params = array()) {
    return db_q($sql, $params)->fetchAll();
}

function db_row($sql, $params = array()) {
    $r = db_q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function db_val($sql, $params = array()) {
    $v = db_q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function db_insert($table, $data) {
    $cols = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    db_q($sql, array_values($data));
    return (int)db()->lastInsertId();
}

function db_update($table, $data, $id) {
    $set = array();
    foreach (array_keys($data) as $c) $set[] = '`' . $c . '` = ?';
    $vals = array_values($data);
    $vals[] = $id;
    db_q('UPDATE `' . $table . '` SET ' . implode(',', $set) . ' WHERE id = ?', $vals);
}
