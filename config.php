<?php
// config.php - Database Configuration & Security Helpers

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Headers (Security Requirement: 10 pts)
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Standard XAMPP default username
define('DB_PASS', '');          // Standard XAMPP default password (empty)
define('DB_NAME', 'income_expense_db');

function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // Try connecting to MySQL (XAMPP default)
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            // Fallback to SQLite database file if MySQL is not running locally!
            $sqliteFile = __DIR__ . '/database.sqlite';
            try {
                $pdo = new PDO("sqlite:" . $sqliteFile, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                initSQLiteSchema($pdo);
            } catch (PDOException $ex) {
                die("Database connection failed: " . $ex->getMessage());
            }
        }
    }
    return $pdo;
}

function initSQLiteSchema($pdo) {
    // Create Users table for SQLite fallback
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user',
            is_deleted INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Create Categories table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER DEFAULT NULL,
            name TEXT NOT NULL,
            type TEXT NOT NULL,
            icon TEXT DEFAULT 'bi-tag',
            color TEXT DEFAULT '#4F46E5'
        );
    ");

    // Create Transactions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            amount REAL NOT NULL,
            type TEXT NOT NULL,
            category_name TEXT NOT NULL,
            date TEXT NOT NULL,
            note TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Check if seed data exists
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    if ($stmt->fetch()['count'] == 0) {
        $passAdmin = password_hash('admin123', PASSWORD_DEFAULT);
        $passUser  = password_hash('123456', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES ('admin', 'admin@system.local', ?, 'admin')")->execute([$passAdmin]);
        $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES ('demo_user', 'demo@system.local', ?, 'user')")->execute([$passUser]);
        $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES ('test_user1', 'test1@system.local', ?, 'user')")->execute([$passUser]);
        $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES ('somchai_dev', 'somchai@system.local', ?, 'user')")->execute([$passUser]);

        // Default categories (ภาษาไทย)
        $categories = [
            [NULL, 'เงินเดือน', 'income', 'bi-briefcase', '#10B981'],
            [NULL, 'ธุรกิจ/โบนัส', 'income', 'bi-shop', '#F59E0B'],
            [NULL, 'การลงทุน', 'income', 'bi-graph-up-arrow', '#3B82F6'],
            [NULL, 'รายรับอื่นๆ', 'income', 'bi-wallet2', '#8B5CF6'],
            [NULL, 'ค่าสาธารณูปโภค', 'expense', 'bi-receipt', '#6366F1'],
            [NULL, 'ค่ารักษาพยาบาล', 'expense', 'bi-heart-pulse', '#EF4444'],
            [NULL, 'ค่าเดินทาง', 'expense', 'bi-car-front', '#F97316'],
            [NULL, 'ค่าบันเทิง/ท่องเที่ยว', 'expense', 'bi-film', '#EC4899'],
            [NULL, 'ค่าอาหาร', 'expense', 'bi-cup-hot', '#EAB308'],
            [NULL, 'รายจ่ายอื่นๆ', 'expense', 'bi-cart', '#64748B']
        ];
        $catStmt = $pdo->prepare("INSERT INTO categories (user_id, name, type, icon, color) VALUES (?, ?, ?, ?, ?)");
        foreach ($categories as $c) {
            $catStmt->execute($c);
        }

        // Sample transactions for demo_user (id=2)
        $trans = [
            [2, 'เงินเดือนประจำเดือน', 15000.00, 'income', 'เงินเดือน', '2026-10-01', 'เงินเดือนตุลาคม'],
            [2, 'โบนัสปลายเดือน', 3000.00, 'income', 'ธุรกิจ/โบนัส', '2026-10-05', 'โบนัสไตรมาส'],
            [2, 'ค่าอาหาร/ค่ากินประจำวัน', 350.00, 'expense', 'ค่าอาหาร', '2026-10-08', 'ข้าวเที่ยงร้านอาหารใกล้ที่ทำงาน'],
            [2, 'ค่าไฟฟ้า + ค่าน้ำประจำเดือน', 1200.00, 'expense', 'ค่าสาธารณูปโภค', '2026-10-07', 'ค่าสาธารณูปโภคประจำเดือน'],
            [2, 'ค่าเดินทางรถสายสาธารณะ', 120.00, 'expense', 'ค่าเดินทาง', '2026-10-09', 'ค่าเดินทางในเมือง'],
            [2, 'ดูหนัง/ค่าบันเทิง', 250.00, 'expense', 'ค่าบันเทิง/ท่องเที่ยว', '2026-10-06', 'ต๋วหนังและป๊อบคอร์น']
        ];
        $tStmt = $pdo->prepare("INSERT INTO transactions (user_id, title, amount, type, category_name, date, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($trans as $t) {
            $tStmt->execute($t);
        }
    }
}

// XSS Protection Helpers (Based on Instructor Slides 10-12)
function secureStr($str) {
    if (is_null($str)) return '';
    $str = trim($str);
    // Convert special characters to HTML entities (Slide 10 & 11)
    $str = htmlentities($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $str;
}

function sanitize($data) {
    if (is_null($data)) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// SPAM & Bot Protection (Instructor Slide 25: กำหนดระยะห่างเวลาของการโพส)
function checkRateLimit($minSeconds = 1) {
    $currentTime = time();
    if (isset($_SESSION['last_post_time'])) {
        if (($currentTime - $_SESSION['last_post_time']) < $minSeconds) {
            header("Location: index.php?alert=" . urlencode("ระบบตรวจพบการส่งข้อมูลถี่เกินไป (Anti-SPAM Protection)") . "&type=warning");
            exit();
        }
    }
    $_SESSION['last_post_time'] = $currentTime;
}

// Authentication Check Helpers
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php?alert=" . urlencode("กรุณาเข้าสู่ระบบก่อนใช้งาน") . "&type=warning");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header("Location: index.php?alert=" . urlencode("คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (สำหรับ Admin เท่านั้น)") . "&type=danger");
        exit();
    }
}
?>
