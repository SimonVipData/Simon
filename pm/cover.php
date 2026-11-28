<?php
/** 工程文件封面打印（瀏覽器「打印 → 另存為 PDF」） */
require_once __DIR__ . '/inc/config.php';
require_login();
if (!empty($_SESSION['pm_locked'])) exit('系統界面已鎖定，請返回系統解鎖');
$doc = db_row("SELECT d.*, p.code AS project_code, p.name AS project_name, p.name_en AS project_en,
                      t.name AS type_name, t.name_en AS type_en, c.name AS disc_name,
                      COALESCE(NULLIF(u.real_name, ''), u.username) AS creator_name, u.company
               FROM pm_docs d LEFT JOIN pm_projects p ON p.id = d.project_id
               LEFT JOIN pm_doc_types t ON t.code = d.type_code LEFT JOIN pm_disciplines c ON c.code = d.disc_code
               LEFT JOIN pm_users u ON u.id = d.creator_id WHERE d.id = ?", array(param_int('id')));
if (!$doc || !can_see_project($doc['project_id'])) exit('文件不存在或您不屬於該工程');
$c = $doc['cover'] ? json_decode($doc['cover'], true) : array();
$c = is_array($c) ? $c : array();
$v = function ($k, $def = '') use ($c) { return isset($c[$k]) && $c[$k] !== '' ? $c[$k] : $def; };
$box = function ($k, $opts) use ($c) {
    $sel = isset($c[$k]) ? explode(',', $c[$k]) : array();
    $out = array();
    foreach ($opts as $o) $out[] = '<span class="ck">' . (in_array($o, $sel, true) ? '&#9745;' : '&#9744;') . ' ' . h($o) . '</span>';
    return implode(' ', $out);
};
$steps = db_rows('SELECT * FROM pm_doc_steps WHERE doc_id = ? AND round = ? AND step_no > 1 ORDER BY step_no', array($doc['id'], $doc['round']));
if (!$steps) {
    foreach (db_rows('SELECT s.step_no, r.name AS role_name FROM pm_flow_steps s LEFT JOIN pm_roles r ON r.id = s.role_id WHERE s.flow_id = ? AND s.step_no > 1 ORDER BY s.step_no', array($doc['flow_id'])) as $s) {
        $steps[] = $s + array('status' => STEP_WAIT, 'user_name' => '', 'comment' => '', 'acted_at' => '');
    }
}
$stepText = array(STEP_WAIT => '', STEP_CURRENT => '', STEP_PASS => '同意 Approved', STEP_REJECT => '駁回 Rejected', STEP_SUBMIT => '');
$titleEn = $doc['type_en'] !== null && $doc['type_en'] !== '' ? $doc['type_en'] : 'Document Submission';
$titleCn = $doc['type_name'] !== null && $doc['type_name'] !== '' ? $doc['type_name'] : '工程文件報審';
$hdr = cover_header_file($doc['project_id']);
$conclusion = array(DOC_APPROVED => '批准 Approved', DOC_REJECTED => '駁回 Rejected, 請修改後重新申報 Revise and Resubmit');
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title><?php echo h($doc['doc_no']); ?> 封面</title>
<style>
body{font-family:"Microsoft JhengHei","PMingLiU","SimSun",Arial,sans-serif;font-size:12px;color:#000;margin:0;background:#eee;}
.page{width:210mm;min-height:297mm;margin:10px auto;background:#fff;padding:10mm;box-sizing:border-box;box-shadow:0 0 6px rgba(0,0,0,.25);}
.hdr{margin:0 0 6px;text-align:center;} .hdr img{display:block;width:100%;max-height:45mm;object-fit:contain;}
h1{font-size:18px;text-align:center;margin:0 0 4px;} h2{font-size:14px;text-align:center;margin:0 0 10px;font-weight:normal;}
table{width:100%;border-collapse:collapse;margin-bottom:-1px;} td,th{border:1px solid #000;padding:4px 6px;vertical-align:top;}
th{background:#f2f2f2;font-weight:normal;text-align:left;width:22%;white-space:nowrap;} .ck{display:inline-block;margin-right:10px;white-space:nowrap;}
.sec{background:#dde6f0;font-weight:bold;} .tall{height:60px;} .sign{height:34px;}
.bar{text-align:center;margin:10px;} .bar button{font-size:14px;padding:4px 18px;}
@media print{body{background:#fff;} .page{margin:0;width:210mm;min-height:0;padding:10mm;box-shadow:none;} .bar{display:none;} @page{size:A4 portrait;margin:0;}
 *{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
</style></head><body>
<div class="bar"><button onclick="window.print()">打印 / 另存為 PDF</button></div>
<div class="page">
<?php if ($hdr): ?><div class="hdr"><img src="api/file.php?id=<?php echo (int)$hdr['id']; ?>&amp;inline=1" alt="<?php echo h($hdr['file_name']); ?>"/></div><?php endif; ?>
<h1><?php echo h($titleEn . ' (' . $doc['type_code'] . ')'); ?> - <?php echo h($titleCn); ?></h1>
<h2><?php echo h($doc['project_name']); ?><?php if ($doc['project_en'] !== ''): ?> / <?php echo h($doc['project_en']); ?><?php endif; ?></h2>
<table>
<tr><th>工程編號 Project No.</th><td><?php echo h($doc['project_code']); ?></td><th>文件編號 Ref. No.</th><td><b><?php echo h($doc['doc_no']); ?></b></td></tr>
<tr><th>工程名稱 Project</th><td><?php echo h($doc['project_name']); ?></td><th>版本 Revision</th><td><?php echo h($doc['version']); ?></td></tr>
<tr><th>發出日期 Issue Date</th><td><?php echo h($v('issue_date', substr($doc['created_at'], 0, 10))); ?></td><th>專業分類 Discipline</th><td><?php echo h($doc['disc_code'] . ' ' . $doc['disc_name']); ?></td></tr>
<tr><th>致 To</th><td><?php echo h($v('to')); ?></td><th>由 From</th><td><?php echo h($v('from', $doc['company'])); ?></td></tr>
<tr><th>副本 C.C.</th><td colspan="3"><?php echo h($v('cc')); ?></td></tr>
<tr><th>事由 Subject</th><td colspan="3"><b><?php echo h($v('subject', $doc['title'])); ?></b></td></tr>
<tr><th>專業 Category</th><td colspan="3"><?php echo $box('category', array('土木 Civil', '建築 Architectural', '機電 E&M', '屋宇設備 BS', '一般 General', '其它 Others')); ?></td></tr>
<tr><th>品牌 Brand</th><td><?php echo h($v('brand')); ?></td><th>型號 Model</th><td><?php echo h($v('model')); ?></td></tr>
<tr><th>產地 Origin</th><td><?php echo h($v('origin')); ?></td><th>其它資料 Others</th><td><?php echo h($v('other_info')); ?></td></tr>
<tr><th>與規範比較</th><td colspan="3"><?php echo $box('compliance', array('與規範相同 Same as specified', '未有規範 Not specified', '等同 Equivalent', '替代 Alternative')); ?><?php if ($v('spec_ref') !== ''): ?><br>規範條款：<?php echo h($v('spec_ref')); ?><?php endif; ?></td></tr>
<tr><th>數量 Quantity</th><td><?php echo h($v('quantity')); ?></td><th>交貨日期 Delivery</th><td><?php echo h($v('delivery')); ?></td></tr>
<tr><th>附件 Attachments</th><td colspan="3"><?php echo $box('attach', array('圖紙 Drawings', '文件 Documents', '技術資料 Technical data', '產品目錄 Catalogues', '計算書 Calculations', '樣板 Samples', '其它 Others')); ?><?php if ($doc['file_name'] !== ''): ?><br>附件文件：<?php echo h($doc['file_name']); ?><?php endif; ?></td></tr>
<tr><th>內容 Contents</th><td colspan="3" class="tall"><?php echo nl2br(h($v('contents', (string)$doc['content']))); ?></td></tr>
<tr><th>申報聲明</th><td colspan="3"><?php echo nl2br(h($v('statement', '茲證明以上資料符合合約及規範要求。We certify that the above complies with the contract and specification.'))); ?></td></tr>
<tr><th>申報人 Submitted by</th><td class="sign"><?php echo h($v('signer', $doc['creator_name'])); ?></td><th>日期 Date</th><td><?php echo h($v('sign_date', $doc['submitted_at'] ? substr($doc['submitted_at'], 0, 10) : '')); ?></td></tr>
<tr><td colspan="4" class="sec">審批意見 Comments</td></tr>
<?php foreach ($steps as $s): ?>
<tr><th><?php echo h($s['role_name']); ?></th><td colspan="3" class="tall"><?php echo $stepText[(int)$s['status']] !== '' ? '<b>' . h($stepText[(int)$s['status']]) . '</b><br>' : ''; ?><?php echo nl2br(h($s['comment'])); ?>
<div style="text-align:right;margin-top:6px;">簽署 Signature：<?php echo h($s['user_name']); ?>　　日期 Date：<?php echo h($s['acted_at'] ? substr($s['acted_at'], 0, 10) : ''); ?></div></td></tr>
<?php endforeach; ?>
<tr><td colspan="4" class="sec">結論 Conclusion</td></tr>
<tr><td colspan="4" class="tall"><?php echo isset($conclusion[(int)$doc['status']]) ? h($conclusion[(int)$doc['status']]) : '審批中 Under review'; ?><?php if ($v('remarks') !== ''): ?><br>備註 Remarks：<?php echo nl2br(h($v('remarks'))); ?><?php endif; ?></td></tr>
</table>
</div>
</body></html>
