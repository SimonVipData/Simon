<?php
/**
 * 建表與初始數據（首次訪問登錄頁時自動執行，已存在則跳過）
 */
function pm_schema() {
    $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    return array(
        "CREATE TABLE IF NOT EXISTS `pm_roles` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(50) NOT NULL,
            `type` VARCHAR(10) NOT NULL DEFAULT 'user',
            `remark` VARCHAR(255) NOT NULL DEFAULT '',
            `sort` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_role_name` (`name`)
        ) $t",
        "CREATE TABLE IF NOT EXISTS `pm_users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(50) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `real_name` VARCHAR(50) NOT NULL DEFAULT '',
            `role_id` INT UNSIGNED NOT NULL,
            `company` VARCHAR(100) NOT NULL DEFAULT '',
            `phone` VARCHAR(30) NOT NULL DEFAULT '',
            `email` VARCHAR(100) NOT NULL DEFAULT '',
            `status` TINYINT NOT NULL DEFAULT 1,
            `last_login` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_username` (`username`), KEY `idx_role` (`role_id`)
        ) $t",
        "CREATE TABLE IF NOT EXISTS `pm_projects` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(50) NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `location` VARCHAR(200) NOT NULL DEFAULT '',
            `owner_unit` VARCHAR(100) NOT NULL DEFAULT '',
            `contractor_unit` VARCHAR(100) NOT NULL DEFAULT '',
            `start_date` DATE NULL,
            `end_date` DATE NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT '進行中',
            `remark` VARCHAR(500) NOT NULL DEFAULT '',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_project_code` (`code`)
        ) $t",
        "CREATE TABLE IF NOT EXISTS `pm_flows` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `project_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `doc_type` VARCHAR(50) NOT NULL DEFAULT '',
            `remark` VARCHAR(255) NOT NULL DEFAULT '',
            `status` TINYINT NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `idx_flow_project` (`project_id`)
        ) $t",
        "CREATE TABLE IF NOT EXISTS `pm_flow_steps` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `flow_id` INT UNSIGNED NOT NULL,
            `step_no` INT NOT NULL,
            `role_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`), KEY `idx_step_flow` (`flow_id`, `step_no`)
        ) $t",
        "CREATE TABLE IF NOT EXISTS `pm_docs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `doc_no` VARCHAR(50) NOT NULL,
            `project_id` INT UNSIGNED NOT NULL,
            `flow_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(200) NOT NULL,
            `doc_type` VARCHAR(50) NOT NULL DEFAULT '',
            `content` TEXT NULL,
            `file_name` VARCHAR(255) NOT NULL DEFAULT '',
            `file_path` VARCHAR(255) NOT NULL DEFAULT '',
            `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
            `creator_id` INT UNSIGNED NOT NULL,
            `status` TINYINT NOT NULL DEFAULT 0,
            `current_step` INT NOT NULL DEFAULT 0,
            `round` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            `submitted_at` DATETIME NULL,
            `finished_at` DATETIME NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_doc_no` (`doc_no`), KEY `idx_doc_creator` (`creator_id`), KEY `idx_doc_status` (`status`)
        ) $t",
        "CREATE TABLE IF NOT EXISTS `pm_doc_steps` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `doc_id` INT UNSIGNED NOT NULL,
            `round` INT NOT NULL DEFAULT 1,
            `step_no` INT NOT NULL,
            `role_id` INT UNSIGNED NOT NULL,
            `role_name` VARCHAR(50) NOT NULL DEFAULT '',
            `status` TINYINT NOT NULL DEFAULT 0,
            `user_id` INT UNSIGNED NULL,
            `user_name` VARCHAR(50) NOT NULL DEFAULT '',
            `comment` VARCHAR(500) NOT NULL DEFAULT '',
            `acted_at` DATETIME NULL,
            PRIMARY KEY (`id`), KEY `idx_ds_doc` (`doc_id`, `round`, `step_no`), KEY `idx_ds_role` (`role_id`, `status`), KEY `idx_ds_user` (`user_id`)
        ) $t",
        "CREATE TABLE IF NOT EXISTS `pm_login_logs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `username` VARCHAR(50) NOT NULL DEFAULT '',
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
            `result` VARCHAR(20) NOT NULL DEFAULT '',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `idx_log_user` (`user_id`)
        ) $t",
    );
}

function pm_install_if_needed() {
    if (db_val("SHOW TABLES LIKE 'pm\\_users'")) return;
    foreach (pm_schema() as $sql) db()->exec($sql);
    if ((int)db_val('SELECT COUNT(*) FROM pm_users') > 0) return;

    $now = now();
    // 角色：管理員 + 六類普通用戶
    $roles = array(
        array('管理員', 'admin', '系統管理員，負責角色、用戶、數據庫及審批設置'),
        array('業主', 'user', ''), array('承建商', 'user', ''), array('設計', 'user', ''),
        array('顧問', 'user', ''), array('第三方質控', 'user', ''), array('廠家', 'user', ''),
    );
    $rid = array();
    foreach ($roles as $i => $r) {
        $rid[$r[0]] = db_insert('pm_roles', array('name' => $r[0], 'type' => $r[1], 'remark' => $r[2], 'sort' => $i + 1, 'created_at' => $now));
    }
    // 預設帳號（密碼均為 111111）
    $users = array(
        array('admin', '系統管理員', '管理員'), array('simon', 'Simon', '承建商'), array('owner', '業主代表', '業主'),
        array('contractor', '承建商代表', '承建商'), array('consultant', '顧問代表', '顧問'), array('designer', '設計代表', '設計'),
        array('inspector', '質控代表', '第三方質控'), array('factory', '廠家代表', '廠家'),
    );
    $hash = password_hash('111111', PASSWORD_DEFAULT);
    $uid = array();
    foreach ($users as $u) {
        $uid[$u[0]] = db_insert('pm_users', array('username' => $u[0], 'password' => $hash, 'real_name' => $u[1], 'role_id' => $rid[$u[2]], 'created_at' => $now));
    }
    // 示例數據：各一條
    $pid = db_insert('pm_projects', array(
        'code' => 'MO-PM-001', 'name' => '澳門工程管理示例項目', 'location' => '澳門', 'owner_unit' => '業主單位', 'contractor_unit' => '承建商單位',
        'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+1 year')), 'status' => '進行中', 'remark' => '示例工程', 'created_at' => $now,
    ));
    $fid = db_insert('pm_flows', array('project_id' => $pid, 'name' => '施工文件報審流程', 'doc_type' => '施工文件', 'remark' => '承建商提交後依次審批', 'created_at' => $now, 'updated_at' => $now));
    $order = array('承建商', '第三方質控', '顧問', '設計', '業主');
    foreach ($order as $i => $name) {
        db_insert('pm_flow_steps', array('flow_id' => $fid, 'step_no' => $i + 1, 'role_id' => $rid[$name]));
    }
    $did = db_insert('pm_docs', array(
        'doc_no' => 'DOC-' . date('Ymd') . '-001', 'project_id' => $pid, 'flow_id' => $fid, 'title' => '施工組織設計方案報審', 'doc_type' => '施工文件',
        'content' => '示例文件：施工組織設計方案，請各方審批。', 'creator_id' => $uid['simon'], 'status' => DOC_PENDING, 'current_step' => 2, 'round' => 1,
        'created_at' => $now, 'updated_at' => $now, 'submitted_at' => $now,
    ));
    foreach ($order as $i => $name) {
        $first = $i === 0;
        db_insert('pm_doc_steps', array(
            'doc_id' => $did, 'round' => 1, 'step_no' => $i + 1, 'role_id' => $rid[$name], 'role_name' => $name,
            'status' => $first ? STEP_SUBMIT : ($i === 1 ? STEP_CURRENT : STEP_WAIT),
            'user_id' => $first ? $uid['simon'] : null, 'user_name' => $first ? 'Simon' : '', 'comment' => $first ? '提交審批' : '',
            'acted_at' => $first ? $now : null,
        ));
    }
}
