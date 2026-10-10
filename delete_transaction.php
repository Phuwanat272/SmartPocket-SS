<?php
// delete_transaction.php - Enforces Requirement 3 (Data Ownership)
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    $trans_id = intval($_POST['transaction_id'] ?? 0);

    $pdo = getDBConnection();

    // 1. Ownership Check: Fetch transaction owner
    $stmt = $pdo->prepare("SELECT user_id FROM transactions WHERE id = :id");
    $stmt->execute([':id' => $trans_id]);
    $trans = $stmt->fetch();

    if (!$trans) {
        header("Location: index.php?alert=" . urlencode("ไม่พบรายการที่ต้องการลบ") . "&type=warning");
        exit();
    }

    // STRICT OWNERSHIP ENFORCEMENT: Only owner or admin can delete
    if ($trans['user_id'] != $user_id && $role !== 'admin') {
        header("Location: index.php?alert=" . urlencode("คุณไม่มีสิทธิ์ลบรายการของผู้อื่น (Data Ownership Constraint)") . "&type=danger");
        exit();
    }

    // Perform Delete
    $delStmt = $pdo->prepare("DELETE FROM transactions WHERE id = :id");
    $delStmt->execute([':id' => $trans_id]);

    header("Location: index.php?alert=" . urlencode("ลบรายการเรียบร้อยแล้ว") . "&type=success");
    exit();
}
?>
