<?php
/**
 * 通用數據模塊定義（api/crud.php 與 pages/crud.php 共用）
 * scope: own 僅本人數據；shared 全員可見、創建人或管理員可改；admin 僅管理員維護
 * 字段: label, type(text|textarea|select|number|date), options, required, max, width, grid(false 不在列表顯示), search
 */
function pm_modules() {
    $party = function ($cats) {
        return array(
            'company_name' => array('label' => '公司名稱', 'required' => true, 'max' => 255, 'width' => 200, 'search' => true),
            'category' => array('label' => '類別', 'type' => 'select', 'options' => $cats, 'width' => 90, 'filter' => true),
            'contact_person' => array('label' => '聯繫人', 'max' => 100, 'width' => 90, 'search' => true),
            'contact_phone' => array('label' => '聯繫電話', 'max' => 100, 'width' => 110),
            'business_reg' => array('label' => '商業登記', 'max' => 255, 'width' => 130),
            'rating' => array('label' => '評級', 'type' => 'select', 'options' => array('A' => '優', 'B' => '良', 'C' => '中', 'D' => '差'), 'width' => 60),
            'remarks' => array('label' => '備註', 'type' => 'textarea', 'width' => 200),
        );
    };
    $quote = array(
        'project' => array('label' => '工程項目', 'required' => true, 'max' => 255, 'width' => 220, 'search' => true),
        'quote_date' => array('label' => '報價日期', 'type' => 'date', 'width' => 100),
        'amount' => array('label' => '金額(MOP)', 'type' => 'number', 'width' => 110),
        'remarks' => array('label' => '備註', 'type' => 'textarea', 'width' => 180),
    );
    $quoteFiles = array('inquiry' => '詢價文件', 'quote' => '報價文件', 'award' => '中標文件', 'settle' => '結算文件');
    return array(
        'position' => array(
            'table' => 'pm_positions', 'title' => '職位', 'scope' => 'admin', 'order' => 'sort, id', 'unique' => 'name',
            'fields' => array(
                'name' => array('label' => '職位名稱', 'required' => true, 'max' => 50, 'width' => 180),
                'sort' => array('label' => '排序', 'type' => 'number', 'width' => 70, 'int' => true),
                'remark' => array('label' => '備註', 'type' => 'textarea', 'max' => 255, 'width' => 320),
            ),
        ),
        'notice' => array(
            'table' => 'pm_notices', 'title' => '消息貼子', 'scope' => 'admin', 'order' => 'id DESC',
            'fields' => array(
                'title' => array('label' => '標題', 'required' => true, 'max' => 200, 'width' => 300, 'search' => true),
                'content' => array('label' => '內容', 'type' => 'textarea', 'rows' => 8, 'required' => true, 'width' => 420),
            ),
            'files' => array('att' => '附件'),
        ),
        'forum_board' => array(
            'table' => 'pm_forum_boards', 'title' => '論壇版塊', 'scope' => 'admin', 'order' => 'sort, id',
            'fields' => array(
                'name' => array('label' => '版塊名稱', 'required' => true, 'max' => 50, 'width' => 180),
                'sort' => array('label' => '排序', 'type' => 'number', 'width' => 70, 'int' => true),
                'remark' => array('label' => '版塊說明', 'type' => 'textarea', 'max' => 255, 'width' => 320),
            ),
        ),
        'note' => array(
            'table' => 'pm_notes', 'title' => '記事', 'scope' => 'own', 'order' => 'updated_at DESC, id DESC',
            'fields' => array(
                'title' => array('label' => '標題', 'required' => true, 'max' => 200, 'width' => 220, 'search' => true),
                'category' => array('label' => '分類', 'max' => 50, 'width' => 100, 'search' => true),
                'content' => array('label' => '內容', 'type' => 'textarea', 'rows' => 10, 'width' => 420, 'search' => true),
            ),
            'files' => array('att' => '附件'),
        ),
        'todo' => array(
            'table' => 'pm_todos', 'title' => '待辦事項', 'scope' => 'own', 'order' => 'status, due_date IS NULL, due_date, id DESC', 'toggle' => 'status',
            'fields' => array(
                'title' => array('label' => '事項', 'required' => true, 'max' => 200, 'width' => 260, 'search' => true),
                'priority' => array('label' => '優先級', 'type' => 'select', 'options' => array('high' => '高', 'mid' => '中', 'low' => '低'), 'default' => 'mid', 'width' => 70, 'filter' => true),
                'due_date' => array('label' => '截止日期', 'type' => 'date', 'width' => 100),
                'status' => array('label' => '狀態', 'type' => 'select', 'options' => array('0' => '未完成', '1' => '已完成'), 'default' => '0', 'width' => 80, 'filter' => true),
                'content' => array('label' => '說明', 'type' => 'textarea', 'width' => 300),
            ),
        ),
        'customer' => array(
            'table' => 'pm_customers', 'title' => '客戶', 'scope' => 'shared', 'order' => 'id DESC',
            'fields' => $party(array('government' => '政府部門', 'private' => '私人機構', 'developer' => '發展商', 'other' => '其他')),
            'files' => array('att' => '附件'), 'child' => array('customer_quote', '報價記錄'),
        ),
        'customer_quote' => array(
            'table' => 'pm_customer_quotes', 'title' => '客戶報價記錄', 'scope' => 'shared', 'order' => 'id DESC', 'parent' => 'customer',
            'fields' => $quote, 'files' => $quoteFiles,
        ),
        'sub' => array(
            'table' => 'pm_subs', 'title' => '分包商', 'scope' => 'shared', 'order' => 'id DESC',
            'fields' => $party(array('labor' => '勞務分包', 'special' => '專業分包', 'material' => '材料供應', 'equipment' => '機械設備', 'other' => '其他')),
            'files' => array('att' => '附件'), 'child' => array('sub_quote', '報價記錄'),
        ),
        'sub_quote' => array(
            'table' => 'pm_sub_quotes', 'title' => '分包報價記錄', 'scope' => 'shared', 'order' => 'id DESC', 'parent' => 'sub',
            'fields' => $quote, 'files' => $quoteFiles,
        ),
        'material' => array(
            'table' => 'pm_materials', 'title' => '材料', 'scope' => 'shared', 'order' => 'id DESC',
            'fields' => array(
                'name' => array('label' => '材料名稱', 'required' => true, 'max' => 255, 'width' => 160, 'search' => true),
                'model' => array('label' => '規格型號', 'max' => 255, 'width' => 140, 'search' => true),
                'category' => array('label' => '類別', 'type' => 'select', 'width' => 90, 'filter' => true,
                    'options' => array('steel' => '鋼材', 'concrete' => '混凝土', 'masonry' => '砌體', 'waterproof' => '防水', 'decoration' => '裝飾', 'mep' => '機電', 'other' => '其他')),
                'company' => array('label' => '供應商', 'max' => 255, 'width' => 160, 'search' => true),
                'contact_person' => array('label' => '聯繫人', 'max' => 100, 'width' => 80),
                'contact_phone' => array('label' => '聯繫電話', 'max' => 100, 'width' => 110),
                'price' => array('label' => '單價(MOP)', 'type' => 'number', 'width' => 90),
                'unit' => array('label' => '單位', 'max' => 20, 'width' => 50),
                'stock' => array('label' => '庫存/供貨', 'max' => 100, 'width' => 90),
                'remarks' => array('label' => '備註', 'type' => 'textarea', 'width' => 160),
            ),
            'files' => array('product' => '產品資料', 'test' => '檢測報告'),
        ),
        'labor' => array(
            'table' => 'pm_labors', 'title' => '勞工', 'scope' => 'shared', 'order' => 'id DESC',
            'fields' => array(
                'name' => array('label' => '姓名', 'required' => true, 'max' => 60, 'width' => 90, 'search' => true),
                'id_type' => array('label' => '證件類型', 'type' => 'select', 'width' => 110, 'filter' => true,
                    'options' => array('local' => '澳門居民身份證', 'blue' => '外地僱員身份認別證', 'other' => '其他')),
                'id_no' => array('label' => '證件編號', 'max' => 60, 'width' => 120, 'search' => true),
                'id_expiry' => array('label' => '證件有效期', 'type' => 'date', 'width' => 100),
                'position' => array('label' => '工種/職位', 'max' => 150, 'width' => 100, 'search' => true),
                'safety_card_no' => array('label' => '職安卡編號', 'max' => 100, 'width' => 110),
                'safety_card_expiry' => array('label' => '職安卡有效期', 'type' => 'date', 'width' => 100),
                'company_name' => array('label' => '所屬公司', 'max' => 255, 'width' => 160, 'search' => true),
                'approval_no' => array('label' => '批示編號', 'max' => 150, 'width' => 110),
                'remarks' => array('label' => '備註', 'type' => 'textarea', 'width' => 140),
            ),
            'files' => array('att' => '證件附件'),
        ),
    );
}

function pm_module($name) {
    $all = pm_modules();
    return isset($all[$name]) ? $all[$name] : null;
}

/** 當前用戶能否修改/刪除該記錄 */
function module_can_write($mod, $row, $me) {
    if ($mod['scope'] === 'admin') return is_admin();
    if ($mod['scope'] === 'own') return (int)$row['user_id'] === (int)$me['id'];
    return is_admin() || (int)$row['user_id'] === (int)$me['id'];
}
