<?php
/** 用戶注冊：設置（存於 pm_meta）與用戶名檢測 */

/** 可配置的注冊信息：key => array(標題, 默認方式)；方式 0 不提供 / 1 選填 / 2 必填。用戶名、密碼固定必填 */
function reg_field_defs() {
    return array(
        'real_name' => array('姓名', 2), 'email' => array('郵箱', 1), 'phone' => array('電話', 1),
        'company' => array('公司名稱', 1), 'position_id' => array('職位', 1), 'role_id' => array('角色', 0),
    );
}

function reg_user_roles() {
    return db_rows("SELECT id, name FROM pm_roles WHERE type = 'user' ORDER BY sort, id");
}

function reg_settings() {
    $m = array();
    foreach (db_rows("SELECT k, v FROM pm_meta WHERE k IN ('reg_enabled', 'reg_audit', 'reg_role', 'reg_fields')") as $r) $m[$r['k']] = $r['v'];
    $fields = array();
    $saved = array();
    foreach (explode(',', isset($m['reg_fields']) ? $m['reg_fields'] : '') as $kv) {
        $p = explode(':', $kv);
        if (count($p) === 2) $saved[$p[0]] = (int)$p[1];
    }
    foreach (reg_field_defs() as $k => $d) $fields[$k] = isset($saved[$k]) ? max(0, min(2, $saved[$k])) : $d[1];
    $roles = reg_user_roles();
    $roleId = isset($m['reg_role']) ? (int)$m['reg_role'] : 0;
    $ids = array_map(function ($r) { return (int)$r['id']; }, $roles);
    if (!in_array($roleId, $ids, true)) $roleId = $ids ? $ids[0] : 0;
    return array(
        'enabled' => isset($m['reg_enabled']) && $m['reg_enabled'] === '1' ? 1 : 0,
        // 默認需要管理員審核後才能登錄
        'audit' => isset($m['reg_audit']) && $m['reg_audit'] === '0' ? 0 : 1,
        'role_id' => $roleId, 'fields' => $fields,
    );
}

function reg_save_settings($enabled, $audit, $roleId, $fields) {
    $pairs = array();
    foreach (reg_field_defs() as $k => $d) $pairs[] = $k . ':' . (isset($fields[$k]) ? max(0, min(2, (int)$fields[$k])) : $d[1]);
    foreach (array('reg_enabled' => $enabled ? '1' : '0', 'reg_audit' => $audit ? '1' : '0', 'reg_role' => (string)(int)$roleId, 'reg_fields' => implode(',', $pairs)) as $k => $v) {
        db_q('REPLACE INTO pm_meta (k, v) VALUES (?, ?)', array($k, $v));
    }
}

/** 與已有帳號不重複的建議用戶名（最多 3 個） */
function reg_suggest($name) {
    $base = substr(preg_replace('/\d+$/', '', $name), 0, 44);
    if (strlen($base) < 2) $base = substr($name, 0, 44);
    $cands = array();
    for ($i = 1; $i <= 9; $i++) $cands[] = $base . $i;
    $cands[] = $base . date('Y');
    $cands[] = $base . '_' . date('y');
    for ($i = 0; $i < 5; $i++) $cands[] = $base . random_int(10, 999);
    $cands = array_values(array_unique($cands));
    $taken = array();
    foreach (db_rows('SELECT username FROM pm_users WHERE username IN (' . implode(',', array_fill(0, count($cands), '?')) . ')', $cands) as $r) {
        $taken[strtolower($r['username'])] = true;
    }
    $out = array();
    foreach ($cands as $c) {
        if (!isset($taken[strtolower($c)])) $out[] = $c;
        if (count($out) >= 3) break;
    }
    return $out;
}

/** 檢測用戶名：返回 valid（格式正確）、exists（已被使用）、msg、suggest */
function reg_check_username($name) {
    if (!is_string($name) || !preg_match('/^[A-Za-z0-9_.\-]{2,50}$/', $name)) {
        return array('valid' => false, 'exists' => false, 'msg' => '用戶名須為 2-50 位字母、數字、下劃線、點或橫線', 'suggest' => array());
    }
    if (db_val('SELECT id FROM pm_users WHERE username = ?', array($name))) {
        $s = reg_suggest($name);
        return array('valid' => true, 'exists' => true, 'msg' => '該用戶名已被使用，請更改' . ($s ? '，建議使用：' . implode('、', $s) : ''), 'suggest' => $s);
    }
    return array('valid' => true, 'exists' => false, 'msg' => '用戶名可以使用', 'suggest' => array());
}
