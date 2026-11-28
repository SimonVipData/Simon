<?php
/** 天氣預測分屏牆：讀取 / 保存用戶設置；管理員可設定所有用戶的默認設置 */
require_once __DIR__ . '/../inc/config.php';
$act = param('act');
api_guard(false, $act !== 'get');
menu_guard('weather');
$uid = (int)current_user()['id'];

function wx_cfg_of($uid) {
    $v = db_val('SELECT cfg FROM pm_wx_cfg WHERE user_id = ?', array($uid));
    $c = $v ? json_decode($v, true) : null;
    return is_array($c) ? $c : null;
}

/** 校驗並規範化設置：layout 4/6/8；最多 8 個分屏 */
function wx_clean($raw) {
    $c = json_decode($raw, true);
    if (!is_array($c) || !isset($c['panes']) || !is_array($c['panes'])) fail('設置格式錯誤');
    $layout = isset($c['layout']) ? (int)$c['layout'] : 4;
    if (!in_array($layout, array(4, 6, 8), true)) $layout = 4;
    $panes = array();
    foreach (array_slice(array_values($c['panes']), 0, 8) as $p) {
        if (!is_array($p)) $p = array();
        $key = isset($p['p']) && is_string($p['p']) && preg_match('/^[a-z0-9_]{1,30}$/', $p['p']) ? $p['p'] : 'custom';
        $url = isset($p['u']) && is_string($p['u']) ? trim($p['u']) : '';
        if ($url !== '' && (strlen($url) > 500 || !preg_match('#^https?://[^\s"\'<>]+$#i', $url))) fail('網址必須以 http:// 或 https:// 開頭');
        $title = isset($p['t']) && is_string($p['t']) ? mb_substr(trim($p['t']), 0, 50, 'UTF-8') : '';
        $r = isset($p['r']) ? (int)$p['r'] : 0;
        $panes[] = array('p' => $key, 't' => $title, 'u' => $url, 'c' => isset($p['c']) ? max(0, (int)$p['c']) : 0, 'r' => max(0, min(1440, $r)));
    }
    return array('layout' => $layout, 'panes' => $panes);
}

function wx_put($uid, $cfg) {
    db_q('REPLACE INTO pm_wx_cfg (user_id, cfg, updated_at) VALUES (?, ?, ?)', array($uid, json_encode($cfg, JSON_UNESCAPED_UNICODE), now()));
}

if ($act === 'get') {
    json_out(array('ok' => true, 'def' => wx_cfg_of(0), 'mine' => wx_cfg_of($uid)));
}
if ($act === 'save') {
    wx_put($uid, wx_clean(param('cfg')));
    ok('');
}
if ($act === 'reset') {
    db_q('DELETE FROM pm_wx_cfg WHERE user_id = ?', array($uid));
    ok('已恢復默認設置');
}
if ($act === 'save_default' || $act === 'reset_default') {
    if (!is_admin()) fail('只有管理員可以設定默認設置');
    if ($act === 'save_default') {
        wx_put(0, wx_clean(param('cfg')));
        ok('已設為所有用戶的默認設置（已自行修改過的用戶，點「恢復默認」後生效）');
    }
    db_q('DELETE FROM pm_wx_cfg WHERE user_id = 0');
    ok('已恢復系統內置的默認設置');
}
fail('未知操作');
