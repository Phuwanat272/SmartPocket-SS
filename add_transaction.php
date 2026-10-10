<?php
// add_transaction.php - Form 1 Processor
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkRateLimit(1); // Slide 25: Anti-SPAM Rate Limit (ระยะห่างเวลาการโพส)
    $user_id = $_SESSION['user_id'];
    $title = sanitize($_POST['title'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $type = sanitize($_POST['type'] ?? '');
    $category_name = sanitize($_POST['category_name'] ?? '');
    $date = sanitize($_POST['date'] ?? '');
    $note = sanitize($_POST['note'] ?? '');

    if (empty($title) || $amount <= 0 || !in_array($type, ['income', 'expense']) || empty($category_name) || empty($date)) {
        header("Location: index.php?alert=" . urlencode("กรุณากรอกข้อมูลในฟอร์มให้ครบถ้วนถูกต้อง") . "&type=danger");
        exit();
    }

    $pdo = getDBConnection();
    // Prepared statement ensuring data ownership (Requirement 3 & Security)
    $stmt = $pdo->prepare("
        INSERT INTO transactions (user_id, title, amount, type, category_name, date, note)
        VALUES (:uid, :title, :amount, :type, :cat, :date, :note)
    ");

    if ($stmt->execute([
        ':uid' => $user_id,
        ':title' => $title,
        ':amount' => $amount,
        ':type' => $type,
        ':cat' => $category_name,
        ':date' => $date,
        ':note' => $note
    ])) {
        header("Location: index.php?alert=" . urlencode("บันทึกรายการสำเร็จ!") . "&type=success");
    } else {
        header("Location: index.php?alert=" . urlencode("เกิดข้อผิดพลาดในการบันทึกข้อมูล") . "&type=danger");
    }
    exit();
}
?>
