<?php
/**
 * seed_passwords.php
 * -----------------------------------------
 * SmartPocket SS - Term Project by Phuwanat Phuthongkhao 68011211138
 * 
 * วิธีใช้: รันไฟล์นี้ใน browser หลังจาก import schema.sql แล้ว
 * URL: http://localhost/income-expense-app/seed_passwords.php
 * 
 * ไฟล์นี้จะ:
 *   1. สร้างและ insert users พร้อม hashed passwords
 *   2. Insert ข้อมูล categories (ถ้ายังไม่มี)
 *   3. Insert sample transactions สำหรับ demo_user
 * -----------------------------------------
 */

require_once 'config.php';

$pdo = getDBConnection();
$log = [];

// ตรวจสอบว่ามีข้อมูลแล้วหรือยัง
$existingUsers = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];

if ($existingUsers > 0) {
    echo "<div style='font-family:sans-serif;padding:20px;background:#fef3cd;border:1px solid #ffc107;border-radius:8px;'>";
    echo "<h2>⚠️ มีข้อมูล Users อยู่แล้ว ($existingUsers บัญชี)</h2>";
    echo "<p>ถ้าต้องการ Reset ให้ลบตาราง users, transactions, categories ใน phpMyAdmin แล้วรันใหม่</p>";
    echo "<a href='index.php' style='color:blue;'>→ กลับหน้าหลัก</a>";
    echo "</div>";
    exit();
}

// --- Insert Users ---
$users = [
    ['admin',           'admin@system.local',    'admin123', 'admin'],
    ['demo_user',       'demo@system.local',     '123456',   'user'],
    ['test_user1',      'test1@system.local',    '123456',   'user'],
    ['somchai_dev',     'somchai@system.local',  '123456',   'user'],
    ['nattaya_k',       'nattaya@system.local',  '123456',   'user'],
];

$insertUser = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
foreach ($users as $u) {
    $hash = password_hash($u[2], PASSWORD_DEFAULT);
    $insertUser->execute([$u[0], $u[1], $hash, $u[3]]);
    $log[] = "✅ Created user: <b>{$u[0]}</b> (role: {$u[3]}, password: {$u[2]})";
}

// --- Insert Default Categories (ภาษาไทย) ---
$existingCats = $pdo->query("SELECT COUNT(*) as count FROM categories")->fetch()['count'];
if ($existingCats == 0) {
    $cats = [
        [NULL, 'เงินเดือน/เงินเดือน',   'income',  'bi-briefcase',      '#10B981'],
        [NULL, 'ธุรกิจ/โบนัส',           'income',  'bi-shop',           '#F59E0B'],
        [NULL, 'การลงทุน',               'income',  'bi-graph-up-arrow', '#3B82F6'],
        [NULL, 'รายรับอื่นๆ',            'income',  'bi-wallet2',        '#8B5CF6'],
        [NULL, 'ค่าสาธารณูปโภค',         'expense', 'bi-receipt',        '#6366F1'],
        [NULL, 'ค่ารักษาพยาบาล',         'expense', 'bi-heart-pulse',    '#EF4444'],
        [NULL, 'ค่าเดินทาง',             'expense', 'bi-car-front',      '#F97316'],
        [NULL, 'ค่าบันเทิง/ท่องเที่ยว',  'expense', 'bi-film',           '#EC4899'],
        [NULL, 'ค่าอาหาร',               'expense', 'bi-cup-hot',        '#EAB308'],
        [NULL, 'รายจ่ายอื่นๆ',           'expense', 'bi-cart',           '#64748B'],
    ];
    $catStmt = $pdo->prepare("INSERT INTO categories (user_id, name, type, icon, color) VALUES (?, ?, ?, ?, ?)");
    foreach ($cats as $c) { $catStmt->execute($c); }
    $log[] = "✅ Inserted " . count($cats) . " default categories";
}

// --- Insert Sample Transactions for demo_user (id=2) ---
$demoUserId = $pdo->query("SELECT id FROM users WHERE username='demo_user' LIMIT 1")->fetch()['id'];
if ($demoUserId) {
    $trans = [
        [$demoUserId, 'เงินเดือนประจำเดือน',      15000.00, 'income',  'เงินเดือน/เงินเดือน',    '2026-10-01', 'เงินเดือนตุลาคม'],
        [$demoUserId, 'โบนัสปลายเดือน',            3000.00,  'income',  'ธุรกิจ/โบนัส',           '2026-10-05', 'โบนัสไตรมาส'],
        [$demoUserId, 'กำไรจากการลงทุน',           2500.00,  'income',  'การลงทุน',               '2026-10-03', 'ปันผลหุ้น'],
        [$demoUserId, 'ค่าอาหารประจำวัน',           350.00,   'expense', 'ค่าอาหาร',               '2026-10-08', 'ข้าวเที่ยงร้านอาหาร'],
        [$demoUserId, 'ค่าไฟฟ้า + ค่าน้ำ',         1200.00,  'expense', 'ค่าสาธารณูปโภค',         '2026-10-07', 'ค่าสาธารณูปโภคประจำเดือน'],
        [$demoUserId, 'ค่าเดินทางรถสาธารณะ',        120.00,   'expense', 'ค่าเดินทาง',             '2026-10-09', 'ค่าเดินทางในเมือง'],
        [$demoUserId, 'ดูหนัง + ค่าบันเทิง',       250.00,   'expense', 'ค่าบันเทิง/ท่องเที่ยว', '2026-10-06', 'ตั๋วหนังและป๊อบคอร์น'],
        [$demoUserId, 'ค่ายาและตรวจสุขภาพ',         450.00,   'expense', 'ค่ารักษาพยาบาล',         '2026-10-04', 'ตรวจสุขภาพประจำปี'],
    ];
    $tStmt = $pdo->prepare("INSERT INTO transactions (user_id, title, amount, type, category_name, date, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($trans as $t) { $tStmt->execute($t); }
    $log[] = "✅ Inserted " . count($trans) . " sample transactions for demo_user";
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seed Database - SmartPocket SS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:700px;">
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h3 class="fw-bold text-success mb-1">✅ Seed Database สำเร็จ!</h3>
        <p class="text-muted">SmartPocket SS — Term Project by Phuwanat Phuthongkhao 68011211138</p>
        <hr>
        <ul class="list-unstyled mb-4">
            <?php foreach ($log as $l): ?>
                <li class="mb-2"><?= $l ?></li>
            <?php endforeach; ?>
        </ul>

        <div class="alert alert-info rounded-3">
            <b>🔑 ข้อมูล Login:</b>
            <table class="table table-sm mt-2 mb-0">
                <thead><tr><th>Username</th><th>Password</th><th>Role</th></tr></thead>
                <tbody>
                    <tr><td><b>admin</b></td><td>admin123</td><td><span class="badge bg-danger">ADMIN</span></td></tr>
                    <tr><td>demo_user</td><td>123456</td><td><span class="badge bg-primary">USER</span></td></tr>
                    <tr><td>test_user1</td><td>123456</td><td><span class="badge bg-primary">USER</span></td></tr>
                    <tr><td>somchai_dev</td><td>123456</td><td><span class="badge bg-primary">USER</span></td></tr>
                    <tr><td>nattaya_k</td><td>123456</td><td><span class="badge bg-primary">USER</span></td></tr>
                </tbody>
            </table>
        </div>

        <a href="login.php" class="btn btn-primary rounded-3 px-4 fw-semibold">
            <i class="bi bi-box-arrow-in-right me-2"></i>ไปหน้า Login
        </a>
    </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</body>
</html>
