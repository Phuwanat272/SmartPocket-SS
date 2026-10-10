<?php
// export_csv.php - Export user transactions to CSV file
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT date, title, type, category_name, amount, note FROM transactions WHERE user_id = :uid ORDER BY date DESC");
$stmt->execute([':uid' => $user_id]);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=transactions_user_' . $user_id . '_' . date('Ymd') . '.csv');

$output = fopen('php://output', 'w');
// UTF-8 BOM for Excel compatibility
fputs($output, "\xEF\xBB\xBF");
fputcsv($output, ['วันที่ (Date)', 'รายการ (Title)', 'ประเภท (Type)', 'หมวดหมู่ (Category)', 'จำนวนเงิน (Amount THB)', 'บันทึกย่อ (Note)']);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['date'],
        $row['title'],
        $row['type'] === 'income' ? 'รายรับ' : 'รายจ่าย',
        $row['category_name'],
        $row['amount'],
        $row['note']
    ]);
}
fclose($output);
exit();
?>
