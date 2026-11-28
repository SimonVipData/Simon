<?php
/**
 * 工程審批文件附件：提交後按「工程編號/類型/專業/文件編號/角色名稱/」分類存放，並按規則重命名
 */

/** 文件夾或文件名片段：去掉路徑與 Windows 不允許的字符 */
function docf_seg($s, $max = 120) {
    $s = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F\x7F]+/u', '_', trim((string)$s));
    $s = trim(preg_replace('/\s+/u', ' ', $s), ' .');
    if ($s === '') $s = '_';
    return mb_substr($s, 0, $max);
}

/** 附件相對路徑是否合法：舊的隨機 .bin，或 docs/ 下的分類目錄 */
function docf_path_ok($p) {
    $p = (string)$p;
    if (preg_match('/^[0-9a-f_]+\.bin$/', $p)) return true;
    if (!preg_match('#^docs(_bak)?/#', $p) || strpos($p, '\\') !== false || strpos($p, "\0") !== false) return false;
    foreach (explode('/', $p) as $seg) {
        if ($seg === '' || $seg === '.' || $seg === '..') return false;
    }
    return true;
}

function docf_abs($p) {
    return docf_path_ok($p) ? UPLOAD_DIR . '/' . $p : '';
}

function docf_exists($p) {
    $abs = docf_abs($p);
    return $abs !== '' && is_file($abs);
}

function docf_unlink($p) {
    $abs = docf_abs($p);
    if ($abs !== '' && is_file($abs)) @unlink($abs);
}

/** 文件的分類目錄（相對 uploads） */
function docf_dir($doc, $roleName) {
    $code = (string)db_val('SELECT code FROM pm_projects WHERE id = ?', array($doc['project_id']));
    return 'docs/' . docf_seg($code) . '/' . docf_seg($doc['type_code']) . '/' . docf_seg($doc['disc_code']) . '/'
        . docf_seg($doc['doc_no']) . '/' . docf_seg($roleName);
}

/** 目錄下不重名的相對路徑：重名時加 (2)、(3)… */
function docf_unique($dir, $base, $ext) {
    $base = docf_seg($base, 180);
    $ext = $ext !== '' ? '.' . strtolower($ext) : '';
    $name = $base . $ext;
    for ($i = 2; is_file(UPLOAD_DIR . '/' . $dir . '/' . $name); $i++) $name = $base . '(' . $i . ')' . $ext;
    return $name;
}

/** 本次請求新放置的文件；事務回滾時刪除 */
function docf_track($rel = null) {
    static $list = array();
    if ($rel === null) { $r = $list; $list = array(); return $r; }
    $list[] = $rel;
}

/**
 * 放置文件到分類目錄，返回 array(file_name, file_path)
 * $src 為絕對路徑；$uploaded 為 true 時用 move_uploaded_file，否則 rename
 */
function docf_place($src, $dir, $base, $ext, $uploaded) {
    $absDir = UPLOAD_DIR . '/' . $dir;
    if (!is_dir($absDir) && !@mkdir($absDir, 0750, true) && !is_dir($absDir)) throw new PmError('附件目錄創建失敗，請檢查 uploads 目錄權限');
    $name = docf_unique($dir, $base, $ext);
    $ok = $uploaded ? move_uploaded_file($src, $absDir . '/' . $name) : @rename($src, $absDir . '/' . $name);
    if (!$ok) throw new PmError('附件保存失敗，請檢查 uploads 目錄權限');
    docf_track($dir . '/' . $name);
    return array('file_name' => $name, 'file_path' => $dir . '/' . $name);
}

/** 校驗單個上傳文件 $_FILES[$key]，返回 array(name, tmp, size, ext)；未上傳返回 null */
function docf_upload_one($key) {
    if (empty($_FILES[$key]) || !is_array($_FILES[$key]) || is_array($_FILES[$key]['error'])) return null;
    $f = $_FILES[$key];
    if ($f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > UPLOAD_MAX_SIZE) throw new PmError('附件不能超過 50MB');
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) throw new PmError('附件上傳失敗');
    $name = str_replace('\\', '/', (string)$f['name']);
    $name = mb_substr(trim(substr($name, (int)strrpos('/' . $name, '/'))), -200);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, file_allowed_ext(), true)) throw new PmError('不支持的附件類型，允許：' . implode('、', file_allowed_ext()));
    return array('name' => $name, 'tmp' => $f['tmp_name'], 'size' => (int)$f['size'], 'ext' => $ext);
}

/** 以 zip（不壓縮）輸出多個文件：$entries = [[絕對路徑, 壓縮包內路徑], ...] */
function docf_zip_send($zipName, $entries) {
    $files = array();
    $total = 0;
    foreach ($entries as $e) {
        if (!is_file($e[0])) continue;
        $size = filesize($e[0]);
        $total += $size;
        if ($total > 1800 * 1024 * 1024) throw new PmError('所選附件總大小超過 1.8GB，請分批下載');
        $mt = filemtime($e[0]);
        $d = getdate($mt);
        $files[] = array('abs' => $e[0], 'name' => $e[1], 'size' => $size, 'crc' => hexdec(hash_file('crc32b', $e[0])),
            'time' => (($d['hours'] << 11) | ($d['minutes'] << 5) | ($d['seconds'] >> 1)),
            'date' => ((max($d['year'] - 1980, 0) << 9) | ($d['mon'] << 5) | $d['mday']));
    }
    if (!$files) throw new PmError('所選文件沒有可下載的附件');
    $len = 22;
    foreach ($files as $f) $len += 30 + 46 + 2 * strlen($f['name']) + $f['size'];
    @set_time_limit(0);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/zip');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . $len);
    header("Content-Disposition: attachment; filename=\"attachments.zip\"; filename*=UTF-8''" . rawurlencode($zipName));
    $central = '';
    $offset = 0;
    foreach ($files as $f) {
        // 版本 20，標誌位 11 = 文件名 UTF-8，方法 0 = 存儲
        $head = pack('vvvvvVVVvv', 20, 0x0800, 0, $f['time'], $f['date'], $f['crc'], $f['size'], $f['size'], strlen($f['name']), 0);
        echo "PK\x03\x04" . $head . $f['name'];
        readfile($f['abs']);
        $central .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0x0800, 0, $f['time'], $f['date'], $f['crc'], $f['size'], $f['size'],
            strlen($f['name']), 0, 0, 0, 0, 0, $offset) . $f['name'];
        $offset += 30 + strlen($f['name']) + $f['size'];
    }
    echo $central . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($files), count($files), strlen($central), $offset, 0);
    exit;
}
