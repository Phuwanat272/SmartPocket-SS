<?php
// admin.php - Admin Dashboard Page (Requirement 5 & Management Controls)
require_once 'config.php';
requireAdmin();

$pdo = getDBConnection();
$alert = sanitize($_GET['alert'] ?? '');
$alertType = sanitize($_GET['type'] ?? 'info');

// Handle Admin Actions: Toggle Role, Edit User, Reset Password, Soft Delete, Restore User
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target_user_id = intval($_POST['target_user_id'] ?? 0);

    if ($action === 'toggle_role') {
        if ($target_user_id === $_SESSION['user_id']) {
            header("Location: admin.php?alert=" . urlencode("ไม่สามารถเปลี่ยนสิทธิ์บัญชีของตนเองได้") . "&type=warning");
            exit();
        }
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
        $stmt->execute([':id' => $target_user_id]);
        $target = $stmt->fetch();
        if ($target) {
            $new_role = ($target['role'] === 'admin') ? 'user' : 'admin';
            $upStmt = $pdo->prepare("UPDATE users SET role = :r WHERE id = :id");
            $upStmt->execute([':r' => $new_role, ':id' => $target_user_id]);
            header("Location: admin.php?alert=" . urlencode("เปลี่ยนสิทธิ์ผู้ใช้งาน #$target_user_id เป็น $new_role เรียบร้อย") . "&type=success");
            exit();
        }
    } elseif ($action === 'edit_user') {
        $new_username = sanitize($_POST['username'] ?? '');
        $new_email = sanitize($_POST['email'] ?? '');
        if (!empty($new_username) && !empty($new_email)) {
            $stmt = $pdo->prepare("UPDATE users SET username = :u, email = :e WHERE id = :id");
            try {
                $stmt->execute([':u' => $new_username, ':e' => $new_email, ':id' => $target_user_id]);
                if ($target_user_id === $_SESSION['user_id']) {
                    $_SESSION['username'] = $new_username;
                }
                header("Location: admin.php?alert=" . urlencode("แก้ไขข้อมูลผู้ใช้งาน #$target_user_id เรียบร้อยแล้ว") . "&type=success");
                exit();
            } catch (PDOException $ex) {
                header("Location: admin.php?alert=" . urlencode("ชื่อผู้ใช้หรืออีเมลนี้มีในระบบแล้ว") . "&type=danger");
                exit();
            }
        }
    } elseif ($action === 'reset_password') {
        $new_pass = $_POST['new_password'] ?? '';
        if (strlen($new_pass) >= 6) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = :p WHERE id = :id");
            $stmt->execute([':p' => $hash, ':id' => $target_user_id]);
            header("Location: admin.php?alert=" . urlencode("รีเซ็ตรหัสผ่านสำหรับผู้ใช้งาน #$target_user_id เรียบร้อยแล้ว") . "&type=success");
            exit();
        } else {
            header("Location: admin.php?alert=" . urlencode("รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร") . "&type=danger");
            exit();
        }
    } elseif ($action === 'soft_delete') {
        if ($target_user_id === $_SESSION['user_id']) {
            header("Location: admin.php?alert=" . urlencode("ไม่สามารถย้ายบัญชีของตนเองเข้าถังขยะได้") . "&type=danger");
            exit();
        }
        $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE id = :id");
        $stmt->execute([':id' => $target_user_id]);
        header("Location: admin.php?alert=" . urlencode("ย้ายผู้ใช้งาน #$target_user_id ไปยังถังขยะเรียบร้อยแล้ว (สามารถกู้คืนได้เสมอ)") . "&type=warning");
        exit();
    } elseif ($action === 'restore_user') {
        $stmt = $pdo->prepare("UPDATE users SET is_deleted = 0 WHERE id = :id");
        $stmt->execute([':id' => $target_user_id]);
        header("Location: admin.php?alert=" . urlencode("กู้คืนบัญชีผู้ใช้งาน #$target_user_id กลับคืนสู่ระบบเรียบร้อยแล้ว!") . "&type=success");
        exit();
    }
}

// Fetch System Metrics
$totalUsers = $pdo->query("SELECT COUNT(*) as count FROM users WHERE COALESCE(is_deleted, 0) = 0")->fetch()['count'];
$totalDeleted = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_deleted = 1")->fetch()['count'];
$totalTransactions = $pdo->query("SELECT COUNT(*) as count FROM transactions")->fetch()['count'];
$sysIncome = $pdo->query("SELECT SUM(amount) as total FROM transactions WHERE type = 'income'")->fetch()['total'] ?? 0;
$sysExpense = $pdo->query("SELECT SUM(amount) as total FROM transactions WHERE type = 'expense'")->fetch()['total'] ?? 0;

// Fetch Active Users List
$users = $pdo->query("
    SELECT u.id, u.username, u.email, u.role, u.created_at, COUNT(t.id) as trans_count
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id
    WHERE COALESCE(u.is_deleted, 0) = 0
    GROUP BY u.id
    ORDER BY u.id ASC
")->fetchAll();

// Fetch Deleted Users List (Trash)
$deletedUsers = $pdo->query("
    SELECT u.id, u.username, u.email, u.role, u.created_at, COUNT(t.id) as trans_count
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id
    WHERE u.is_deleted = 1
    GROUP BY u.id
    ORDER BY u.id ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าผู้ดูแลระบบ - SmartPocket SS Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-body">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg sticky-top bg-dark text-white border-bottom shadow-xs py-2.5">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2 text-white" href="admin.php">
                <div class="brand-logo bg-danger text-white rounded-3 p-1.5 d-flex align-items-center justify-content-center">
                    <i class="bi bi-shield-lock-fill fs-5"></i>
                </div>
                <span class="fw-bold fs-5">SmartPocket SS — Admin Panel</span>
                <span class="badge bg-danger text-white fw-semibold fs-8 rounded-pill">แผงควบคุมผู้ดูแลระบบ</span>
            </a>

            <div class="d-flex align-items-center gap-3">
                <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="bi bi-speedometer2 me-1.5"></i>กลับสู่หน้า Dashboard หลัก
                </a>
                <a href="logout.php" class="btn btn-danger btn-sm rounded-pill px-3">
                    <i class="bi bi-box-arrow-right me-1"></i>ออกจากระบบ
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 py-4">

        <?php if (!empty($alert)): ?>
            <div class="alert alert-<?= $alertType ?> alert-dismissible fade show rounded-3 shadow-xs mb-4" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i><?= $alert ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- System Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card p-3 border-0 rounded-4 shadow-xs bg-white">
                    <span class="text-muted fs-7">ผู้ใช้งานที่ใช้งานอยู่</span>
                    <h3 class="fw-extrabold text-primary m-0 mt-1"><?= number_format($totalUsers) ?> <small class="fs-6 font-normal">คน</small></h3>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card p-3 border-0 rounded-4 shadow-xs bg-white">
                    <span class="text-muted fs-7">บัญชีในถังขยะ (ลบแล้ว)</span>
                    <h3 class="fw-extrabold text-warning m-0 mt-1"><?= number_format($totalDeleted) ?> <small class="fs-6 font-normal">บัญชี</small></h3>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card p-3 border-0 rounded-4 shadow-xs bg-white">
                    <span class="text-muted fs-7">หมุนเวียนรายรับทั้งระบบ</span>
                    <h3 class="fw-extrabold text-success m-0 mt-1">฿<?= number_format($sysIncome, 2) ?></h3>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card p-3 border-0 rounded-4 shadow-xs bg-white">
                    <span class="text-muted fs-7">หมุนเวียนรายจ่ายทั้งระบบ</span>
                    <h3 class="fw-extrabold text-danger m-0 mt-1">฿<?= number_format($sysExpense, 2) ?></h3>
                </div>
            </div>
        </div>

        <!-- User Management Table (Active Users) -->
        <div class="card border-0 shadow-xs rounded-4 p-4 bg-white mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark m-0">จัดการผู้ใช้งานในระบบ (Active Users)</h5>
                    <small class="text-muted">แก้ไขชื่อผู้ใช้, อีเมล, รีเซ็ตรหัสผ่าน, สลับสิทธิ์ หรือปิดใช้งานบัญชี</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-7">
                        <tr>
                            <th>ID</th>
                            <th>ชื่อผู้ใช้ (Username)</th>
                            <th>อีเมล (Email)</th>
                            <th>บทบาท (Role)</th>
                            <th>จำนวนรายการ</th>
                            <th>วันที่สมัคร</th>
                            <th class="text-end">การจัดการ (Actions)</th>
                        </tr>
                    </thead>
                    <tbody class="fs-7">
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="fw-bold">#<?= $u['id'] ?></td>
                                <td class="fw-semibold text-dark">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-circle bg-<?= $u['role'] === 'admin' ? 'danger' : 'primary' ?> text-white fs-8">
                                            <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                        </div>
                                        <?= htmlspecialchars($u['username']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'primary' ?>-subtle text-<?= $u['role'] === 'admin' ? 'danger' : 'primary' ?> px-2.5 py-1.5 rounded-pill fw-semibold">
                                        <?= strtoupper($u['role']) ?>
                                    </span>
                                </td>
                                <td><span class="fw-bold"><?= $u['trans_count'] ?></span> รายการ</td>
                                <td class="text-muted"><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <!-- Edit Username/Email Modal Button -->
                                        <button type="button" class="btn btn-outline-primary btn-sm px-2 py-1 fs-8" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editUserModal<?= $u['id'] ?>" 
                                                title="แก้ไขชื่อผู้ใช้และอีเมล">
                                            <i class="bi bi-pencil-square me-1"></i>แก้ไข
                                        </button>

                                        <!-- Reset Password Modal Button -->
                                        <button type="button" class="btn btn-outline-warning text-dark btn-sm px-2 py-1 fs-8" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#resetPassModal<?= $u['id'] ?>" 
                                                title="รีเซ็ตรหัสผ่านใหม่">
                                            <i class="bi bi-key me-1"></i>รีเซ็ตรหัส
                                        </button>

                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                            <!-- Toggle Role -->
                                            <form action="admin.php" method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_role">
                                                <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm px-2 py-1 fs-8" title="สลับสิทธิ์ระหว่าง User และ Admin">
                                                    <i class="bi bi-arrow-repeat me-1"></i>สลับสิทธิ์
                                                </button>
                                            </form>
                                            <!-- Soft Delete User -->
                                            <form action="admin.php" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการย้ายผู้ใช้ <?= htmlspecialchars($u['username']) ?> ไปยังถังขยะ?');">
                                                <input type="hidden" name="action" value="soft_delete">
                                                <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm px-2 py-1 fs-8">
                                                    <i class="bi bi-trash me-1"></i>ลบ
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary align-self-center ms-1">(บัญชีคุณ)</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <!-- MODAL 1: Edit User Modal -->
                            <div class="modal fade text-start" id="editUserModal<?= $u['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content rounded-4 border-0 shadow-lg">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold text-dark">แก้ไขข้อมูลผู้ใช้ #<?= $u['id'] ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="admin.php" method="POST">
                                            <input type="hidden" name="action" value="edit_user">
                                            <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                            <div class="modal-body p-4">
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">ชื่อผู้ใช้ (Username)</label>
                                                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($u['username']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">อีเมล (Email)</label>
                                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 pt-0">
                                                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">ยกเลิก</button>
                                                <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">บันทึกการแก้ไข</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- MODAL 2: Reset Password Modal -->
                            <div class="modal fade text-start" id="resetPassModal<?= $u['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content rounded-4 border-0 shadow-lg">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold text-dark"><i class="bi bi-shield-lock text-warning me-2"></i>รีเซ็ตรหัสผ่านใหม่สำหรับ <?= htmlspecialchars($u['username']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="admin.php" method="POST">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                            <div class="modal-body p-4">
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">รหัสผ่านใหม่ (New Password)</label>
                                                    <input type="password" name="new_password" class="form-control" placeholder="อย่างน้อย 6 ตัวอักษร" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 pt-0">
                                                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">ยกเลิก</button>
                                                <button type="submit" class="btn btn-warning rounded-3 px-4 fw-semibold text-dark">ยืนยันตั้งรหัสผ่านใหม่</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Restorable Trash / Deleted Users Table (ถังขยะสำหรับกู้คืนผู้ใช้) -->
        <div class="card border-0 shadow-xs rounded-4 p-4 bg-white mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark m-0"><i class="bi bi-arrow-counterclockwise text-warning me-2"></i>ถังขยะและบัญชีที่ถูกลบ (Deleted Users - Restorable)</h5>
                    <small class="text-muted">สามารถกดปุ่ม "กู้คืนบัญชี" เพื่อดึงผู้ใช้และข้อมูลการบันทึกกลับคืนสู่ระบบได้เสมอ</small>
                </div>
            </div>

            <div class="table-responsive">
                <?php if (empty($deletedUsers)): ?>
                    <div class="text-center py-4 text-muted fs-7">
                        <i class="bi bi-trash3 fs-3 d-block mb-1 text-secondary opacity-50"></i>
                        <span>ไม่มีบัญชีที่อยู่ในถังขยะ</span>
                    </div>
                <?php else: ?>
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light fs-7">
                            <tr>
                                <th>ID</th>
                                <th>ชื่อผู้ใช้ (Username)</th>
                                <th>อีเมล (Email)</th>
                                <th>บทบาทเดิม</th>
                                <th>จำนวนรายการเดิม</th>
                                <th class="text-end">การกู้คืน (Restore Action)</th>
                            </tr>
                        </thead>
                        <tbody class="fs-7">
                            <?php foreach ($deletedUsers as $du): ?>
                                <tr class="table-warning-subtle">
                                    <td class="fw-bold">#<?= $du['id'] ?></td>
                                    <td class="fw-semibold text-muted">
                                        <del><?= htmlspecialchars($du['username']) ?></del>
                                    </td>
                                    <td class="text-muted"><?= htmlspecialchars($du['email']) ?></td>
                                    <td><span class="badge bg-secondary-subtle text-secondary"><?= strtoupper($du['role']) ?></span></td>
                                    <td><span class="fw-bold"><?= $du['trans_count'] ?></span> รายการ</td>
                                    <td class="text-end">
                                        <form action="admin.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="restore_user">
                                            <input type="hidden" name="target_user_id" value="<?= $du['id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm px-3 py-1 fs-8 rounded-pill fw-semibold shadow-xs">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i>กู้คืนบัญชี (Restore)
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
