<?php
// index.php - Main User Dashboard (Matching Image 2 - CerdasDuit Style)
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

$pdo = getDBConnection();

// 1. Fetch User Transactions (Data Ownership Enforcement - Requirement 3)
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = :uid ORDER BY date DESC, id DESC");
$stmt->execute([':uid' => $user_id]);
$transactions = $stmt->fetchAll();

// 2. Calculate Totals
$stmtInc = $pdo->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = :uid AND type = 'income'");
$stmtInc->execute([':uid' => $user_id]);
$total_income = floatval($stmtInc->fetch()['total'] ?? 0);

$stmtExp = $pdo->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = :uid AND type = 'expense'");
$stmtExp->execute([':uid' => $user_id]);
$total_expense = floatval($stmtExp->fetch()['total'] ?? 0);

$balance = $total_income - $total_expense;

// 3. Category Breakdown for Income Chart
$stmtCatInc = $pdo->prepare("
    SELECT category_name, SUM(amount) as total 
    FROM transactions 
    WHERE user_id = :uid AND type = 'income'
    GROUP BY category_name
");
$stmtCatInc->execute([':uid' => $user_id]);
$income_categories = $stmtCatInc->fetchAll();

// 4. Category Breakdown for Expense Chart
$stmtCatExp = $pdo->prepare("
    SELECT category_name, SUM(amount) as total 
    FROM transactions 
    WHERE user_id = :uid AND type = 'expense'
    GROUP BY category_name
");
$stmtCatExp->execute([':uid' => $user_id]);
$expense_categories = $stmtCatExp->fetchAll();

// 5. Fetch Categories for Dropdowns (Default System + User Custom)
$stmtCats = $pdo->prepare("SELECT * FROM categories WHERE user_id IS NULL OR user_id = :uid ORDER BY name ASC");
$stmtCats->execute([':uid' => $user_id]);
$categories = $stmtCats->fetchAll();

$alert = sanitize($_GET['alert'] ?? '');
$alertType = sanitize($_GET['type'] ?? 'info');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartPocket SS — ระบบบันทึกรายรับรายจ่าย</title>
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-body">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg sticky-top smartpocket-navbar py-2">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <div class="brand-logo bg-teal text-white rounded-3 p-1.5 d-flex align-items-center justify-content-center">
                    <i class="bi bi-wallet2 fs-5"></i>
                </div>
                <span class="fw-bold fs-5 text-white">SmartPocket SS</span>
                <span class="badge bg-primary-subtle text-primary fw-semibold fs-8 rounded-pill ms-1">Term Project by Phuwanat</span>
            </a>

            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-md-flex align-items-center owner-badge me-1">
                    <i class="bi bi-person-badge-fill me-1" style="color:#7dd3fc"></i>
                    <span class="fw-semibold" style="color:#bfdbfe;font-size:0.73rem">Phuwanat Phuthongkhao 68011211138</span>
                </div>
                <?php if ($role === 'admin'): ?>
                    <a href="admin.php" class="btn btn-dark btn-sm rounded-pill px-3 shadow-xs">
                        <i class="bi bi-shield-lock-fill me-1.5 text-warning"></i>หน้าผู้ดูแลระบบ (Admin)
                    </a>
                <?php endif; ?>

                <div class="dropdown">
                    <button class="btn btn-light btn-sm rounded-pill px-3 border dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <div class="avatar-circle bg-primary text-white fs-7 fw-bold">
                            <?= strtoupper(substr($username, 0, 1)) ?>
                        </div>
                        <span class="fw-semibold text-dark fs-7"><?= htmlspecialchars($username) ?></span>
                        <span class="badge bg-<?= $role === 'admin' ? 'danger' : 'primary' ?>-subtle text-<?= $role === 'admin' ? 'danger' : 'primary' ?> fs-8 me-1"><?= strtoupper($role) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-2">
                        <li><span class="dropdown-header">สิทธิ์การใช้งาน: <b><?= htmlspecialchars($role) ?></b></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container-fluid px-4 py-4">

        <?php if (!empty($alert)): ?>
            <div class="alert alert-<?= $alertType ?> alert-dismissible fade show auto-dismiss rounded-3 shadow-xs mb-4" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i><?= $alert ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Metric Summary Cards Row (Matching Image 2 Top Cards) -->
        <div class="row g-3 mb-4">
            <!-- 1. Total Balance Card (Dark Blue Card) -->
            <div class="col-12 col-md-4">
                <div class="card card-summary balance-card text-white h-100 p-4 border-0 rounded-4 shadow-sm position-relative overflow-hidden">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="text-white-50 fs-7 fw-medium d-block mb-1">ยอดเงินคงเหลือ (Balance)</span>
                            <h2 class="fw-extrabold m-0 display-6">฿<?= number_format($balance, 2) ?></h2>
                        </div>
                        <div class="icon-box bg-white-10 rounded-3 p-2 text-white">
                            <i class="bi bi-wallet-fill fs-4"></i>
                        </div>
                    </div>
                    <small class="text-white-50 fs-8">Saldo bersih Anda (ยอดเงินสุทธิคำนวณอัตโนมัติ)</small>
                </div>
            </div>

            <!-- 2. Income Card -->
            <div class="col-12 col-md-4">
                <div class="card card-summary bg-white h-100 p-4 border rounded-4 shadow-xs">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="text-muted fs-7 fw-medium d-block mb-1">รายรับทั้งหมด</span>
                            <h2 class="fw-bold text-dark m-0 text-success">฿<?= number_format($total_income, 2) ?></h2>
                        </div>
                        <div class="icon-box bg-success-subtle text-success rounded-3 p-2">
                            <i class="bi bi-graph-up-arrow fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted fs-8">ยอดรวมรายรับในระบบ</small>
                </div>
            </div>

            <!-- 3. Expense Card -->
            <div class="col-12 col-md-4">
                <div class="card card-summary bg-white h-100 p-4 border rounded-4 shadow-xs">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="text-muted fs-7 fw-medium d-block mb-1">รายจ่ายทั้งหมด</span>
                            <h2 class="fw-bold text-dark m-0 text-danger">฿<?= number_format($total_expense, 2) ?></h2>
                        </div>
                        <div class="icon-box bg-danger-subtle text-danger rounded-3 p-2">
                            <i class="bi bi-graph-down-arrow fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted fs-8">ยอดรวมรายจ่ายในระบบ</small>
                </div>
            </div>
        </div>

        <!-- Main Dashboard Split: Left Column (Transactions) & Right Column (Charts & Categories) -->
        <div class="row g-4">
            
            <!-- Left Column: Transactions List (CRUD Requirement) -->
            <div class="col-12 col-lg-7">
                <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                        <div>
                            <h5 class="fw-bold text-dark m-0">รายการล่าสุด</h5>
                            <small class="text-muted">บัญชีของ: <?= htmlspecialchars($username) ?></small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="export_csv.php" class="btn btn-light border btn-sm rounded-3 px-3 font-medium">
                                <i class="bi bi-download me-1.5 text-secondary"></i>Ekspor CSV
                            </a>
                            <!-- Form 2 Modal Trigger -->
                            <button type="button" class="btn btn-outline-teal btn-sm rounded-3 px-3 font-medium" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="bi bi-folder-plus me-1.5"></i>+ หมวดหมู่
                            </button>
                            <button type="button" class="btn btn-teal btn-sm rounded-3 px-3 fw-semibold shadow-xs" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                <i class="bi bi-plus-lg me-1"></i>+ บันทึกรายการ
                            </button>
                        </div>
                    </div>

                    <!-- Transactions List -->
                    <div class="transaction-list">
                        <?php if (empty($transactions)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                <span>ยังไม่มีรายการบันทึก คลิกปุ่ม <b>+ Tambah</b> ด้านบนเพื่อเพิ่มรายการใหม่</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                                <div class="transaction-item d-flex align-items-center justify-content-between p-3 mb-2 rounded-3 bg-light-subtle hover-shadow">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="trans-icon rounded-3 p-2.5 bg-white shadow-xs text-<?= $t['type'] === 'income' ? 'success' : 'danger' ?>">
                                            <i class="bi <?= $t['type'] === 'income' ? 'bi-arrow-up-right' : 'bi-arrow-down-left' ?> fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold text-dark m-0"><?= htmlspecialchars($t['title']) ?></h6>
                                            <div class="d-flex align-items-center gap-2 mt-1 fs-8 text-muted">
                                                <span><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($t['date'])) ?></span>
                                                <span>•</span>
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill"><?= htmlspecialchars($t['category_name']) ?></span>
                                                <?php if (!empty($t['note'])): ?>
                                                    <span>•</span>
                                                    <span class="text-truncate max-w-200" title="<?= htmlspecialchars($t['note']) ?>"><?= htmlspecialchars($t['note']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-3">
                                        <span class="fw-bold fs-6 <?= $t['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
                                            <?= $t['type'] === 'income' ? '+฿' : '-฿' ?><?= number_format($t['amount'], 2) ?>
                                        </span>
                                        <!-- Data Ownership Delete Action -->
                                        <form action="delete_transaction.php" method="POST" onsubmit="return confirm('ยืนยันการลบรายการนี้?');" class="m-0">
                                            <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn btn-link text-muted p-0 border-0 hover-danger" title="ลบรายการ">
                                                <i class="bi bi-trash fs-6"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Charts & Categorized Summaries (Matching Image 2 Right Panel) -->
            <div class="col-12 col-lg-5">
                
                <!-- Income by Category Card -->
                <div class="card border-0 shadow-xs rounded-4 p-4 bg-white mb-4">
                    <h6 class="fw-bold text-dark mb-3">รายรับตามหมวดหมู่</h6>
                    <div class="row align-items-center">
                        <div class="col-6">
                            <div class="chart-container position-relative" style="height: 160px;">
                                <canvas id="incomeChart"></canvas>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="category-breakdown-list fs-8">
                                <?php if (empty($income_categories)): ?>
                                    <span class="text-muted">ยังไม่มีข้อมูลรายรับ</span>
                                <?php else: ?>
                                    <?php foreach ($income_categories as $ic): ?>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="d-flex align-items-center gap-1.5 text-muted">
                                                <span class="dot bg-success rounded-circle"></span>
                                                <?= htmlspecialchars($ic['category_name']) ?>
                                            </span>
                                            <span class="fw-semibold text-dark">฿<?= number_format($ic['total'], 0) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expense by Category Card -->
                <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
                    <h6 class="fw-bold text-dark mb-3">รายจ่ายตามหมวดหมู่</h6>
                    <div class="row align-items-center">
                        <div class="col-6">
                            <div class="chart-container position-relative" style="height: 160px;">
                                <canvas id="expenseChart"></canvas>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="category-breakdown-list fs-8">
                                <?php if (empty($expense_categories)): ?>
                                    <span class="text-muted">ยังไม่มีข้อมูลรายจ่าย</span>
                                <?php else: ?>
                                    <?php foreach ($expense_categories as $ec): ?>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="d-flex align-items-center gap-1.5 text-muted">
                                                <span class="dot bg-danger rounded-circle"></span>
                                                <?= htmlspecialchars($ec['category_name']) ?>
                                            </span>
                                            <span class="fw-semibold text-dark">฿<?= number_format($ec['total'], 0) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- MODAL FORM 1: บันทึกรายการใหม่ (Requirement 4 - Form 1) -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">บันทึกรายการใหม่ (+ Tambah)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_transaction.php" method="POST">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">ประเภทรายการ</label>
                            <div class="d-flex gap-3">
                                <div class="form-check flex-fill p-3 border rounded-3 text-center bg-light cursor-pointer">
                                    <input class="form-check-input float-none me-1" type="radio" name="type" id="typeExpense" value="expense" checked>
                                    <label class="form-check-label fw-bold text-danger" for="typeExpense">
                                        <i class="bi bi-arrow-down-left me-1"></i>รายจ่าย (Expense)
                                    </label>
                                </div>
                                <div class="form-check flex-fill p-3 border rounded-3 text-center bg-light cursor-pointer">
                                    <input class="form-check-input float-none me-1" type="radio" name="type" id="typeIncome" value="income">
                                    <label class="form-check-label fw-bold text-success" for="typeIncome">
                                        <i class="bi bi-arrow-up-right me-1"></i>รายรับ (Income)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">ชื่อรายการ</label>
                            <input type="text" name="title" class="form-control" placeholder="เช่น ค่าอาหารกลางวัน, เงินเดือน" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">จำนวนเงิน (บาท)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">฿</span>
                                <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">หมวดหมู่</label>
                            <select name="category_name" class="form-select" required>
                                <option value="" disabled selected>-- เลือกหมวดหมู่ --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?> (<?= $cat['type'] === 'income' ? 'รายรับ' : 'รายจ่าย' ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">วันที่</label>
                            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">บันทึกย่อ (Note)</label>
                            <textarea name="note" class="form-control" rows="2" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-teal rounded-3 px-4 fw-semibold">บันทึกรายการ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL FORM 2: Add Custom Category (Requirement 4 - Form 2) -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">เพิ่มหมวดหมู่ส่วนตัว (Form 2)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_category.php" method="POST">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">ชื่อหมวดหมู่ใหม่</label>
                            <input type="text" name="name" class="form-control" placeholder="เช่น ค่าผ่อนบ้าน, ค่าเกม" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">ประเภท</label>
                            <select name="type" class="form-select" required>
                                <option value="expense">รายจ่าย (Expense)</option>
                                <option value="income">รายรับ (Income)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">สีประจำหมวดหมู่</label>
                            <input type="color" name="color" class="form-control form-control-color w-100" value="#4F46E5">
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-outline-teal rounded-3 px-4 fw-semibold">สร้างหมวดหมู่</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Chart Data Pass to JS -->
    <script>
        const incomeChartData = {
            labels: <?= json_encode(array_column($income_categories, 'category_name')) ?>,
            data: <?= json_encode(array_map('floatval', array_column($income_categories, 'total'))) ?>
        };
        const expenseChartData = {
            labels: <?= json_encode(array_column($expense_categories, 'category_name')) ?>,
            data: <?= json_encode(array_map('floatval', array_column($expense_categories, 'total'))) ?>
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
