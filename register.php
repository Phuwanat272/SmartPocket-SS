<?php
// register.php - User Registration Page
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif ($password !== $confirm_password) {
        $error = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } else {
        $pdo = getDBConnection();
        // Check if username/email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u OR email = :e");
        $stmt->execute([':u' => $username, ':e' => $email]);
        
        if ($stmt->fetch()) {
            $error = 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว';
        } else {
            // Requirement 1: Password Hashing using password_hash (BCRYPT / Argon2)
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Prepared statement preventing SQL Injection
            $insert = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:u, :e, :p, 'user')");
            if ($insert->execute([':u' => $username, ':e' => $email, ':p' => $password_hash])) {
                header("Location: login.php?alert=" . urlencode("ลงทะเบียนสำเร็จ! กรุณาเข้าสู่ระบบ") . "&type=success");
                exit();
            } else {
                $error = 'เกิดข้อผิดพลาดในการลงทะเบียน กรุณาลองใหม่อีกครั้ง';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียน - SmartPocket SS | บันทึกรายรับรายจ่าย</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card shadow-lg">
            <div class="auth-header text-center">
                <div class="app-icon shadow-sm mx-auto mb-3 bg-success-subtle text-success">
                    <i class="bi bi-person-plus-fill fs-2"></i>
                </div>
                <h3 class="fw-bold text-dark">สร้างบัญชีผู้ใช้ใหม่</h3>
                <p class="text-muted fs-7">SmartPocket SS — Term Project by Phuwanat Phuthongkhao 68011211138</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show auto-dismiss" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="auth-form mt-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">ชื่อผู้ใช้ (Username)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="ตั้งชื่อผู้ใช้" required value="<?= sanitize($_POST['username'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">อีเมล (Email)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" placeholder="name@example.com" required value="<?= sanitize($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">รหัสผ่าน</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="อย่างน้อย 6 ตัวอักษร" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">ยืนยันรหัสผ่าน</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-lock"></i></span>
                        <input type="password" name="confirm_password" class="form-control" placeholder="กรอกรหัสผ่านอีกครั้ง" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-success w-100 py-2.5 fw-semibold shadow-sm mb-3">
                    <i class="bi bi-check-circle-fill me-2"></i>ยืนยันการสมัครสมาชิก
                </button>

                <div class="text-center mt-3">
                    <span class="text-muted fs-7">มีบัญชีผู้ใช้อยู่แล้ว?</span>
                    <a href="login.php" class="text-primary fw-semibold fs-7 text-decoration-none ms-1">เข้าสู่ระบบ</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
