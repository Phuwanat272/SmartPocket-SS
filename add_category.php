<?php
// add_category.php - Form 2 Processor
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkRateLimit(1); // Slide 25: Anti-SPAM Rate Limit (ระยะห่างเวลาการโพส)
    $user_id = $_SESSION['user_id'];
    $name = sanitize($_POST['name'] ?? '');
    $type = sanitize($_POST['type'] ?? '');
    $color = sanitize($_POST['color'] ?? '#4F46E5');

    if (empty($name) || !in_array($type, ['income', 'expense'])) {
        header("Location: index.php?alert=" . urlencode("กรุณากรอกชื่อหมวดหมู่และประเภทให้ถูกต้อง") . "&type=danger");
        exit();
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, type, icon, color) VALUES (:uid, :name, :type, 'bi-tag', :color)");
    if ($stmt->execute([':uid' => $user_id, ':name' => $name, ':type' => $type, ':color' => $color])) {
        header("Location: index.php?alert=" . urlencode("เพิ่มหมวดหมู่ \"$name\" เรียบร้อยแล้ว") . "&type=success");
    } else {
        header("Location: index.php?alert=" . urlencode("เกิดข้อผิดพลาดในการสร้างหมวดหมู่") . "&type=danger");
    }
    exit();
}
?>
