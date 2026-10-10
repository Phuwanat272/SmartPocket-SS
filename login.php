<?php
// login.php - User Login Page
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$alert = sanitize($_GET['alert'] ?? '');
$alertType = sanitize($_GET['type'] ?? 'info');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_email = sanitize($_POST['username_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username_email) || empty($password)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } else {
        $pdo = getDBConnection();
        // Case-insensitive query preventing SQL Injection
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(:input) OR LOWER(email) = LOWER(:input)");
        $stmt->execute([':input' => $username_email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = '❌ ไม่พบชื่อผู้ใช้หรืออีเมลนี้ในระบบ กรุณาตรวจสอบอีกครั้งหรือสมัครสมาชิกใหม่';
        } elseif (isset($user['is_deleted']) && $user['is_deleted'] == 1) {
            $error = '⚠️ บัญชีนี้ถูกปิดใช้งานหรือลบออกจากระบบแล้ว (กรุณาติดต่อผู้ดูแลระบบเพื่อกู้คืน)';
        } elseif (password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true); // Prevent Session Fixation attacks
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: index.php?alert=" . urlencode("ยินดีต้อนรับคุณ " . $user['username']) . "&type=success");
            exit();
        } else {
            $error = '❌ รหัสผ่านไม่ถูกต้อง! กรุณาตรวจสอบรหัสผ่านอีกครั้ง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - SmartPocket SS | ระบบบันทึกรายรับรายจ่าย</title>
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card shadow-lg">
            <div class="auth-header text-center">
                <div class="app-icon shadow-sm mx-auto mb-3">
                    <i class="bi bi-wallet2 text-primary fs-2"></i>
                </div>
                <h3 class="fw-bold text-dark">เข้าสู่ระบบ</h3>
                <p class="text-muted fs-7">SmartPocket SS — Term Project by Phuwanat</p>
            </div>

            <?php if (!empty($alert)): ?>
                <div class="alert alert-<?= $alertType ?> alert-dismissible fade show auto-dismiss" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i><?= $alert ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show auto-dismiss" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="auth-form mt-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">ชื่อผู้ใช้ หรือ อีเมล</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" name="username_email" class="form-control" placeholder="เช่น demo_user หรือ admin" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">รหัสผ่าน</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="รหัสผ่าน" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm mb-3">
                    <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
                </button>

                <div class="text-center mt-3">
                    <span class="text-muted fs-7">ยังไม่มีบัญชีผู้ใช้?</span>
                    <a href="register.php" class="text-primary fw-semibold fs-7 text-decoration-none ms-1">ลงทะเบียนสร้างบัญชี</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
