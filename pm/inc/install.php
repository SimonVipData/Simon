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
            `position_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `credits` INT NOT NULL DEFAULT 1800,
            `credit_at` DATETIME NULL,
            `active_at` DATETIME NULL,
            `reg_state` TINYINT NOT NULL DEFAULT 0,
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
        // ---- 第二期：職位、積分、消息、個人辦公、工程實務、內部審批、論壇 ----
        "CREATE TABLE IF NOT EXISTS `pm_positions` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(50) NOT NULL DEFAULT '', `remark` VARCHAR(255) NOT NULL DEFAULT '', `sort` INT NOT NULL DEFAULT 0, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_position_name` (`name`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_credit_logs` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `target_id` INT UNSIGNED NOT NULL DEFAULT 0, `delta` INT NOT NULL DEFAULT 0, `balance` INT NOT NULL DEFAULT 0, `remark` VARCHAR(255) NOT NULL DEFAULT '', `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_cl_target` (`target_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_notices` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `title` VARCHAR(200) NOT NULL DEFAULT '', `content` TEXT NULL, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_notice_reads` (`notice_id` INT UNSIGNED NOT NULL, `user_id` INT UNSIGNED NOT NULL, `read_at` DATETIME NOT NULL, PRIMARY KEY (`notice_id`, `user_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_files` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `module` VARCHAR(30) NOT NULL DEFAULT '', `ref_id` INT UNSIGNED NOT NULL DEFAULT 0, `field` VARCHAR(30) NOT NULL DEFAULT '', `file_name` VARCHAR(255) NOT NULL DEFAULT '', `file_path` VARCHAR(255) NOT NULL DEFAULT '', `file_size` INT UNSIGNED NOT NULL DEFAULT 0, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_file_ref` (`module`, `ref_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_chat_msgs` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `from_id` INT UNSIGNED NOT NULL DEFAULT 0, `to_id` INT UNSIGNED NOT NULL DEFAULT 0, `content` VARCHAR(2000) NOT NULL DEFAULT '', `is_read` TINYINT NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `idx_chat_to` (`to_id`, `is_read`), KEY `idx_chat_pair` (`from_id`, `to_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_chat_reads` (`user_id` INT UNSIGNED NOT NULL, `last_id` INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (`user_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_mails` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `subject` VARCHAR(200) NOT NULL DEFAULT '', `content` MEDIUMTEXT NULL, `to_names` VARCHAR(1000) NOT NULL DEFAULT '', `status` TINYINT NOT NULL DEFAULT 0, `from_deleted` TINYINT NOT NULL DEFAULT 0, `sent_at` DATETIME NULL, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_mail_from` (`user_id`, `status`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_mail_to` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `mail_id` INT UNSIGNED NOT NULL DEFAULT 0, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `is_read` TINYINT NOT NULL DEFAULT 0, `read_at` DATETIME NULL, `deleted` TINYINT NOT NULL DEFAULT 0, PRIMARY KEY (`id`), KEY `idx_mt_user` (`user_id`, `deleted`), KEY `idx_mt_mail` (`mail_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_votes` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `title` VARCHAR(200) NOT NULL DEFAULT '', `content` TEXT NULL, `multi` TINYINT NOT NULL DEFAULT 0, `anonymous` TINYINT NOT NULL DEFAULT 0, `end_date` DATE NULL, `status` TINYINT NOT NULL DEFAULT 1, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_vote_options` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `vote_id` INT UNSIGNED NOT NULL DEFAULT 0, `title` VARCHAR(200) NOT NULL DEFAULT '', `sort` INT NOT NULL DEFAULT 0, PRIMARY KEY (`id`), KEY `idx_vo_vote` (`vote_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_vote_records` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `vote_id` INT UNSIGNED NOT NULL DEFAULT 0, `option_id` INT UNSIGNED NOT NULL DEFAULT 0, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_vr` (`option_id`, `user_id`), KEY `idx_vr_vote` (`vote_id`, `user_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_notes` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `category` VARCHAR(50) NOT NULL DEFAULT '', `title` VARCHAR(200) NOT NULL DEFAULT '', `content` MEDIUMTEXT NULL, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_note_user` (`user_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_todos` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `title` VARCHAR(200) NOT NULL DEFAULT '', `content` TEXT NULL, `priority` VARCHAR(10) NOT NULL DEFAULT '', `due_date` DATE NULL, `status` TINYINT NOT NULL DEFAULT 0, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_todo_user` (`user_id`, `status`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_events` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `project_id` INT UNSIGNED NOT NULL DEFAULT 0, `title` VARCHAR(200) NOT NULL DEFAULT '', `content` TEXT NULL, `event_date` DATE NOT NULL, `start_time` VARCHAR(5) NOT NULL DEFAULT '', `end_time` VARCHAR(5) NOT NULL DEFAULT '', `color` VARCHAR(7) NOT NULL DEFAULT '#3a87ad', `is_star` TINYINT NOT NULL DEFAULT 0, `is_node` TINYINT NOT NULL DEFAULT 0, `is_material` TINYINT NOT NULL DEFAULT 0, `is_person` TINYINT NOT NULL DEFAULT 0, `is_plan` TINYINT NOT NULL DEFAULT 0, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_event_date` (`event_date`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_customers` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `company_name` VARCHAR(255) NOT NULL DEFAULT '', `contact_person` VARCHAR(100) NOT NULL DEFAULT '', `contact_phone` VARCHAR(100) NOT NULL DEFAULT '', `business_reg` VARCHAR(255) NOT NULL DEFAULT '', `category` VARCHAR(30) NOT NULL DEFAULT '', `rating` VARCHAR(10) NOT NULL DEFAULT '', `remarks` TEXT NULL, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_customer_quotes` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `parent_id` INT UNSIGNED NOT NULL DEFAULT 0, `project` VARCHAR(255) NOT NULL DEFAULT '', `quote_date` DATE NULL, `amount` DECIMAL(14,2) NULL, `remarks` TEXT NULL, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_cq_parent` (`parent_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_subs` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `company_name` VARCHAR(255) NOT NULL DEFAULT '', `contact_person` VARCHAR(100) NOT NULL DEFAULT '', `contact_phone` VARCHAR(100) NOT NULL DEFAULT '', `business_reg` VARCHAR(255) NOT NULL DEFAULT '', `category` VARCHAR(30) NOT NULL DEFAULT '', `rating` VARCHAR(10) NOT NULL DEFAULT '', `remarks` TEXT NULL, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_sub_quotes` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `parent_id` INT UNSIGNED NOT NULL DEFAULT 0, `project` VARCHAR(255) NOT NULL DEFAULT '', `quote_date` DATE NULL, `amount` DECIMAL(14,2) NULL, `remarks` TEXT NULL, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_sq_parent` (`parent_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_materials` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(255) NOT NULL DEFAULT '', `model` VARCHAR(255) NOT NULL DEFAULT '', `category` VARCHAR(30) NOT NULL DEFAULT '', `company` VARCHAR(255) NOT NULL DEFAULT '', `contact_person` VARCHAR(100) NOT NULL DEFAULT '', `contact_phone` VARCHAR(100) NOT NULL DEFAULT '', `price` DECIMAL(14,2) NULL, `unit` VARCHAR(20) NOT NULL DEFAULT '', `stock` VARCHAR(100) NOT NULL DEFAULT '', `remarks` TEXT NULL, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_labors` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(60) NOT NULL DEFAULT '', `id_type` VARCHAR(100) NOT NULL DEFAULT '', `id_no` VARCHAR(60) NOT NULL DEFAULT '', `id_expiry` DATE NULL, `position` VARCHAR(150) NOT NULL DEFAULT '', `safety_card_no` VARCHAR(100) NOT NULL DEFAULT '', `safety_card_expiry` DATE NULL, `company_name` VARCHAR(255) NOT NULL DEFAULT '', `approval_no` VARCHAR(150) NOT NULL DEFAULT '', `remarks` TEXT NULL, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_apprs` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `type` VARCHAR(10) NOT NULL DEFAULT '', `appr_no` VARCHAR(50) NOT NULL DEFAULT '', `title` VARCHAR(200) NOT NULL DEFAULT '', `project_id` INT UNSIGNED NOT NULL DEFAULT 0, `category` VARCHAR(50) NOT NULL DEFAULT '', `party` VARCHAR(255) NOT NULL DEFAULT '', `amount` DECIMAL(14,2) NULL, `content` TEXT NULL, `status` TINYINT NOT NULL DEFAULT 0, `current_step` INT NOT NULL DEFAULT 0, `submitted_at` DATETIME NULL, `finished_at` DATETIME NULL, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_appr_no` (`appr_no`), KEY `idx_appr_user` (`user_id`), KEY `idx_appr_type` (`type`, `status`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_appr_steps` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `appr_id` INT UNSIGNED NOT NULL DEFAULT 0, `step_no` INT NOT NULL DEFAULT 0, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `user_name` VARCHAR(50) NOT NULL DEFAULT '', `status` TINYINT NOT NULL DEFAULT 0, `comment` VARCHAR(500) NOT NULL DEFAULT '', `acted_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_as_appr` (`appr_id`, `step_no`), KEY `idx_as_user` (`user_id`, `status`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_appr_logs` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `appr_id` INT UNSIGNED NOT NULL DEFAULT 0, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `user_name` VARCHAR(50) NOT NULL DEFAULT '', `action` VARCHAR(20) NOT NULL DEFAULT '', `comment` VARCHAR(500) NOT NULL DEFAULT '', `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `idx_al_appr` (`appr_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_forum_boards` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(50) NOT NULL DEFAULT '', `remark` VARCHAR(255) NOT NULL DEFAULT '', `sort` INT NOT NULL DEFAULT 0, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_forum_threads` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `board_id` INT UNSIGNED NOT NULL DEFAULT 0, `title` VARCHAR(200) NOT NULL DEFAULT '', `content` MEDIUMTEXT NULL, `is_top` TINYINT NOT NULL DEFAULT 0, `is_locked` TINYINT NOT NULL DEFAULT 0, `views` INT NOT NULL DEFAULT 0, `replies` INT NOT NULL DEFAULT 0, `last_reply_at` DATETIME NULL, `last_reply_user` VARCHAR(50) NOT NULL DEFAULT '', `pay_view` INT NOT NULL DEFAULT 0, `pay_file` INT NOT NULL DEFAULT 0, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_ft_board` (`board_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_forum_posts` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `thread_id` INT UNSIGNED NOT NULL DEFAULT 0, `content` MEDIUMTEXT NULL, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_fp_thread` (`thread_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_disk` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `space` VARCHAR(10) NOT NULL DEFAULT 'personal', `parent_id` INT UNSIGNED NOT NULL DEFAULT 0, `is_dir` TINYINT NOT NULL DEFAULT 0, `name` VARCHAR(200) NOT NULL DEFAULT '', `remark` VARCHAR(255) NOT NULL DEFAULT '', `size` BIGINT NOT NULL DEFAULT 0, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_parent` (`space`, `parent_id`), KEY `idx_user` (`user_id`)) $t",
        // 論壇付費記錄：kind = view 查看貼子 / file 下載附件；amount 為支付的積分（分鐘）
        "CREATE TABLE IF NOT EXISTS `pm_forum_pays` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `thread_id` INT UNSIGNED NOT NULL DEFAULT 0, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `kind` VARCHAR(4) NOT NULL DEFAULT 'view', `amount` INT NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_fpay` (`thread_id`, `user_id`, `kind`)) $t",
        // 流程模板：新建工程時可選模板自動填充審批步驟
        "CREATE TABLE IF NOT EXISTS `pm_flow_tpls` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(100) NOT NULL DEFAULT '', `remark` VARCHAR(255) NOT NULL DEFAULT '', `sort` INT NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_flow_tpl_steps` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `tpl_id` INT UNSIGNED NOT NULL DEFAULT 0, `step_no` INT NOT NULL DEFAULT 0, `role_id` INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (`id`), KEY `idx_fts_tpl` (`tpl_id`, `step_no`)) $t",
        // 文件分類：審批類型、專業分類
        "CREATE TABLE IF NOT EXISTS `pm_doc_types` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(20) NOT NULL DEFAULT '', `name` VARCHAR(50) NOT NULL DEFAULT '', `name_en` VARCHAR(100) NOT NULL DEFAULT '', `remark` VARCHAR(255) NOT NULL DEFAULT '', `sort` INT NOT NULL DEFAULT 0, PRIMARY KEY (`id`), UNIQUE KEY `uk_dtype_code` (`code`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_disciplines` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(20) NOT NULL DEFAULT '', `name` VARCHAR(50) NOT NULL DEFAULT '', `remark` VARCHAR(255) NOT NULL DEFAULT '', `sort` INT NOT NULL DEFAULT 0, PRIMARY KEY (`id`), UNIQUE KEY `uk_disc_code` (`code`)) $t",
        // 用戶所屬工程（普通用戶）
        "CREATE TABLE IF NOT EXISTS `pm_user_projects` (`user_id` INT UNSIGNED NOT NULL, `project_id` INT UNSIGNED NOT NULL, PRIMARY KEY (`user_id`, `project_id`), KEY `idx_up_project` (`project_id`)) $t",
        // 文件編號流水號：prefix = 工程英文名-審批類型，擬稿即占號
        "CREATE TABLE IF NOT EXISTS `pm_doc_seq` (`prefix` VARCHAR(150) NOT NULL, `last_no` INT NOT NULL DEFAULT 0, PRIMARY KEY (`prefix`)) $t",
        // ---- 審批回覆附件、附件替換記錄 ----
        "CREATE TABLE IF NOT EXISTS `pm_doc_files` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `doc_id` INT UNSIGNED NOT NULL DEFAULT 0, `round` INT NOT NULL DEFAULT 0, `step_id` INT UNSIGNED NOT NULL DEFAULT 0, `role_name` VARCHAR(50) NOT NULL DEFAULT '', `file_name` VARCHAR(255) NOT NULL DEFAULT '', `file_path` VARCHAR(500) NOT NULL DEFAULT '', `file_size` INT UNSIGNED NOT NULL DEFAULT 0, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_df_doc` (`doc_id`), KEY `idx_df_step` (`step_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_doc_file_logs` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `src` VARCHAR(10) NOT NULL DEFAULT '', `ref_id` INT UNSIGNED NOT NULL DEFAULT 0, `doc_id` INT UNSIGNED NOT NULL DEFAULT 0, `old_name` VARCHAR(255) NOT NULL DEFAULT '', `new_name` VARCHAR(255) NOT NULL DEFAULT '', `upload_name` VARCHAR(255) NOT NULL DEFAULT '', `old_size` INT UNSIGNED NOT NULL DEFAULT 0, `new_size` INT UNSIGNED NOT NULL DEFAULT 0, `backup_path` VARCHAR(500) NOT NULL DEFAULT '', `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `idx_dfl_ref` (`src`, `ref_id`)) $t",
        // 工作清單：followers 為跟進人員 ID（逗號分隔，空 = 所有用戶可跟進）；上傳閉合附件即閉合
        "CREATE TABLE IF NOT EXISTS `pm_worklist` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `category` VARCHAR(50) NOT NULL DEFAULT '', `content` TEXT NULL, `followers` VARCHAR(500) NOT NULL DEFAULT '', `close_desc` TEXT NULL, `close_user_id` INT UNSIGNED NOT NULL DEFAULT 0, `closed_at` DATETIME NULL, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_wl_user` (`user_id`)) $t",
        // 天氣預測分屏牆設置：user_id = 0 為管理員設定的默認設置
        "CREATE TABLE IF NOT EXISTS `pm_wx_cfg` (`user_id` INT UNSIGNED NOT NULL, `cfg` TEXT NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`user_id`)) $t",
        "CREATE TABLE IF NOT EXISTS `pm_meta` (`k` VARCHAR(50) NOT NULL, `v` VARCHAR(255) NOT NULL DEFAULT '', PRIMARY KEY (`k`)) $t",
    );
}

define('PM_SCHEMA_VER', '10');

/** 首次訪問建表並寫入初始數據；之後按版本做增量升級 */
function pm_install_if_needed() {
    if (db_val("SHOW TABLES LIKE 'pm\\_users'")) {
        $ver = db_val("SHOW TABLES LIKE 'pm\\_meta'") ? db_val("SELECT v FROM pm_meta WHERE k = 'schema'") : null;
        if ($ver !== PM_SCHEMA_VER) pm_migrate();
        return;
    }
    pm_seed_v1();
    pm_migrate();
}

/**
 * 增量升級（可重複執行）：補齊數據表與字段、超級管理員、第二期示例數據。
 * 恢復舊版本備份後也會調用。
 */
function pm_migrate() {
    foreach (pm_schema() as $sql) db()->exec($sql);
    $cols = array();
    foreach (db_rows('SHOW COLUMNS FROM pm_users') as $c) $cols[$c['Field']] = true;
    $add = array(
        'position_id' => 'INT UNSIGNED NOT NULL DEFAULT 0', 'credits' => 'INT NOT NULL DEFAULT ' . (CREDIT_INIT_MIN * 60),
        'credit_at' => 'DATETIME NULL', 'active_at' => 'DATETIME NULL',
        // 1 = 自行注冊、等待管理員審核
        'reg_state' => 'TINYINT NOT NULL DEFAULT 0',
    );
    foreach ($add as $name => $def) {
        if (!isset($cols[$name])) db()->exec("ALTER TABLE pm_users ADD COLUMN `$name` $def");
    }
    // 論壇付費貼：查看 / 下載附件所需積分（分鐘），0 為免費
    $cols = array();
    foreach (db_rows('SHOW COLUMNS FROM pm_forum_threads') as $c) $cols[$c['Field']] = true;
    foreach (array('pay_view', 'pay_file') as $name) {
        if (!isset($cols[$name])) db()->exec("ALTER TABLE pm_forum_threads ADD COLUMN `$name` INT NOT NULL DEFAULT 0");
    }
    // 工程：英文名、年度流水號、工程審批流程
    pm_add_cols('pm_projects', array(
        'name_en' => "VARCHAR(100) NOT NULL DEFAULT ''", 'code_year' => 'INT NOT NULL DEFAULT 0', 'code_seq' => 'INT NOT NULL DEFAULT 0',
        'flow_id' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    ));
    db()->exec('ALTER TABLE pm_projects MODIFY `code` VARCHAR(100) NOT NULL');
    // 工程文件：審批類型、專業分類、版本、封面
    pm_add_cols('pm_docs', array(
        'type_code' => "VARCHAR(20) NOT NULL DEFAULT ''", 'disc_code' => "VARCHAR(20) NOT NULL DEFAULT ''",
        'base_no' => "VARCHAR(150) NOT NULL DEFAULT ''", 'version' => "VARCHAR(4) NOT NULL DEFAULT 'A'",
        'ver_src' => 'TINYINT NOT NULL DEFAULT 0', 'parent_id' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'is_latest' => 'TINYINT NOT NULL DEFAULT 1', 'cover' => 'MEDIUMTEXT NULL',
    ));
    // 工作清單所屬項目（0 = 舊記錄，未指定項目）
    pm_add_cols('pm_worklist', array('project_id' => 'INT UNSIGNED NOT NULL DEFAULT 0'));
    db()->exec('ALTER TABLE pm_docs MODIFY `doc_no` VARCHAR(160) NOT NULL');
    db()->exec('ALTER TABLE pm_docs MODIFY `file_path` VARCHAR(500) NOT NULL DEFAULT \'\'');
    if (!db_val("SELECT v FROM pm_meta WHERE k = 'seed6'")) {
        pm_seed_v6();
        db_q("REPLACE INTO pm_meta (k, v) VALUES ('seed6', '1')");
    }
    $now = now();
    // 超級管理員角色與 xadmin 帳號
    $superId = db_val("SELECT id FROM pm_roles WHERE type = 'super' ORDER BY id LIMIT 1");
    if (!$superId) {
        $name = '超級管理員';
        while (db_val('SELECT id FROM pm_roles WHERE name = ?', array($name))) $name .= '_';
        $superId = db_insert('pm_roles', array('name' => $name, 'type' => 'super', 'remark' => '擁有管理員全部功能，並可備份、恢復數據庫', 'sort' => 0, 'created_at' => $now));
    }
    if (!db_val('SELECT id FROM pm_users WHERE role_id = ? LIMIT 1', array($superId)) && !db_val("SELECT id FROM pm_users WHERE username = 'xadmin'")) {
        db_insert('pm_users', array('username' => 'xadmin', 'password' => password_hash('111111', PASSWORD_DEFAULT), 'real_name' => '超級管理員', 'role_id' => $superId, 'created_at' => $now));
    }
    // 第二期示例數據（只寫入一次）
    if (!db_val("SELECT v FROM pm_meta WHERE k = 'seed2'")) {
        pm_seed_v2();
        db_q("REPLACE INTO pm_meta (k, v) VALUES ('seed2', '1')");
    }
    // 網盤資料示例（只寫入一次）
    if (!db_val("SELECT v FROM pm_meta WHERE k = 'seed3'")) {
        if (!db_val('SELECT COUNT(*) FROM pm_disk')) {
            $adminId = (int)db_val("SELECT u.id FROM pm_users u JOIN pm_roles r ON r.id = u.role_id WHERE r.type IN ('admin', 'super') ORDER BY r.type, u.id LIMIT 1");
            db_insert('pm_disk', array('space' => 'company', 'is_dir' => 1, 'name' => '工程資料', 'remark' => '示例文件夾', 'user_id' => $adminId, 'created_at' => $now));
        }
        db_q("REPLACE INTO pm_meta (k, v) VALUES ('seed3', '1')");
    }
    db_q("REPLACE INTO pm_meta (k, v) VALUES ('schema', ?)", array(PM_SCHEMA_VER));
}

function pm_add_cols($table, $add) {
    $cols = array();
    foreach (db_rows("SHOW COLUMNS FROM `$table`") as $c) $cols[$c['Field']] = true;
    foreach ($add as $name => $def) {
        if (!isset($cols[$name])) db()->exec("ALTER TABLE `$table` ADD COLUMN `$name` $def");
    }
}

/** 第六期：文件分類初始數據、現有審批設置轉為工程流程與模板、現有普通用戶歸屬現有工程 */
function pm_seed_v6() {
    $now = now();
    if (!db_val('SELECT COUNT(*) FROM pm_doc_types')) {
        $types = array(
            array('RFI', '信息請求', 'Request for Information'), array('MST', '方案報審', 'Method Statement'),
            array('MAT', '材料報審', 'Materials Approval Form'), array('RSI', '工序驗收', 'Request for Site Inspection'),
            array('TST', '測試報告', 'Test Report'), array('VAF', '設計變更', 'Variation Approval Form'),
            array('DWG', '圖紙報審', 'Drawing Submission'), array('RIN', '工序見證', 'Request for Inspection Witness'),
        );
        foreach ($types as $i => $t) db_insert('pm_doc_types', array('code' => $t[0], 'name' => $t[1], 'name_en' => $t[2], 'sort' => $i + 1));
    }
    if (!db_val('SELECT COUNT(*) FROM pm_disciplines')) {
        $discs = array(array('EVL', '弱電'), array('PD', '給排水'), array('EL', '機電'), array('FO', '建築'), array('BR', '結構'),
            array('HVAC', '冷氣通風'), array('FS', '消防'), array('CCTV', '監控'), array('OT', '其它'));
        foreach ($discs as $i => $d) db_insert('pm_disciplines', array('code' => $d[0], 'name' => $d[1], 'sort' => $i + 1));
    }
    foreach (db_rows('SELECT id, code, name_en, flow_id FROM pm_projects') as $p) {
        $data = array();
        if ($p['name_en'] === '') $data['name_en'] = preg_replace('/[^A-Za-z0-9\-]/', '', $p['code']) ?: 'P' . $p['id'];
        if (!(int)$p['flow_id']) {
            $fid = (int)db_val('SELECT id FROM pm_flows WHERE project_id = ? ORDER BY status DESC, id LIMIT 1', array($p['id']));
            if ($fid) $data['flow_id'] = $fid;
        }
        if ($data) db_update('pm_projects', $data, $p['id']);
    }
    if (!db_val('SELECT COUNT(*) FROM pm_flow_tpls')) {
        $seen = array();
        foreach (db_rows('SELECT id, name FROM pm_flows ORDER BY id') as $f) {
            $roles = array_map('intval', array_column(db_rows('SELECT role_id FROM pm_flow_steps WHERE flow_id = ? ORDER BY step_no', array($f['id'])), 'role_id'));
            $key = implode(',', $roles);
            if (count($roles) < 2 || isset($seen[$key])) continue;
            $seen[$key] = true;
            $tid = db_insert('pm_flow_tpls', array('name' => $f['name'], 'sort' => count($seen), 'created_at' => $now));
            foreach ($roles as $i => $rid) db_insert('pm_flow_tpl_steps', array('tpl_id' => $tid, 'step_no' => $i + 1, 'role_id' => $rid));
        }
    }
    db_q("UPDATE pm_docs SET base_no = doc_no WHERE base_no = ''");
    db_q("INSERT IGNORE INTO pm_user_projects (user_id, project_id)
          SELECT u.id, p.id FROM pm_users u JOIN pm_roles r ON r.id = u.role_id AND r.type = 'user' CROSS JOIN pm_projects p");
}

function pm_seed_v2() {
    $now = now();
    $admin = (int)db_val("SELECT u.id FROM pm_users u JOIN pm_roles r ON r.id = u.role_id WHERE r.type IN ('admin', 'super') ORDER BY r.type, u.id LIMIT 1");
    $std = array('user_id' => $admin, 'created_at' => $now, 'updated_at' => $now);
    if (!db_val('SELECT COUNT(*) FROM pm_positions')) {
        foreach (array('項目經理', '工程師', '資料員') as $i => $n) db_insert('pm_positions', array('name' => $n, 'sort' => $i + 1) + $std);
    }
    if (!db_val('SELECT COUNT(*) FROM pm_notices')) {
        db_insert('pm_notices', array('title' => '歡迎使用澳門工程管理網站', 'content' => "系統已新增個人辦公、工程實務、內部審批及工程論壇功能。\n普通用戶的積分即可使用本系統的分鐘數，積分用完後請聯繫管理員充值。") + $std);
    }
    if (!db_val('SELECT COUNT(*) FROM pm_forum_boards')) {
        $bid = db_insert('pm_forum_boards', array('name' => '綜合討論區', 'remark' => '工程技術與管理經驗交流', 'sort' => 1) + $std);
        db_insert('pm_forum_threads', array('board_id' => $bid, 'title' => '歡迎來到工程論壇', 'content' => '歡迎各參建單位在此交流工程技術、施工管理與協調事項。', 'last_reply_at' => $now) + $std);
    }
    if (!db_val('SELECT COUNT(*) FROM pm_customers')) {
        db_insert('pm_customers', array('company_name' => '示例客戶有限公司', 'contact_person' => '陳先生', 'contact_phone' => '2800 0000', 'category' => 'private', 'rating' => 'A', 'remarks' => '示例數據') + $std);
    }
    if (!db_val('SELECT COUNT(*) FROM pm_subs')) {
        db_insert('pm_subs', array('company_name' => '示例分包工程公司', 'contact_person' => '李先生', 'contact_phone' => '2800 0001', 'category' => 'labor', 'rating' => 'A', 'remarks' => '示例數據') + $std);
    }
    if (!db_val('SELECT COUNT(*) FROM pm_materials')) {
        db_insert('pm_materials', array('name' => '鋼筋', 'model' => 'HRB400 Φ16', 'category' => 'steel', 'company' => '示例建材供應商', 'price' => '4500.00', 'unit' => '噸', 'stock' => '現貨', 'remarks' => '示例數據') + $std);
    }
    if (!db_val('SELECT COUNT(*) FROM pm_labors')) {
        db_insert('pm_labors', array('name' => '張三', 'id_type' => 'local', 'position' => '紮鐵工', 'company_name' => '示例分包工程公司', 'remarks' => '示例數據') + $std);
    }
}

function pm_seed_v1() {
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
