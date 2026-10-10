import sqlite3
import os
from werkzeug.security import generate_password_hash, check_password_hash

DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'database.db')

def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    # Enable Foreign Key support in SQLite
    conn.execute("PRAGMA foreign_keys = ON;")
    return conn

def init_db():
    conn = get_db()
    cursor = conn.cursor()

    # 1. Users Table (Requirements 1 & 2: Password hashing & Roles)
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user', -- 'user' or 'admin'
            is_deleted INTEGER DEFAULT 0, -- 0 = active, 1 = soft deleted (Restorable)
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ''')

    # Migration check: add is_deleted column if missing from existing table
    try:
        cursor.execute("ALTER TABLE users ADD COLUMN is_deleted INTEGER DEFAULT 0")
    except sqlite3.OperationalError:
        pass # Column already exists

    # 2. Categories Table (Form 2 & Admin management)
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER, -- NULL for global system categories, or specific user_id for custom categories
            name TEXT NOT NULL,
            type TEXT NOT NULL CHECK(type IN ('income', 'expense')),
            icon TEXT DEFAULT 'bi-tag',
            color TEXT DEFAULT '#4F46E5',
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ''')

    # 3. Transactions Table (CRUD with Ownership - Requirement 3 & Form 1 - Requirement 4)
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL, -- Owner of the transaction (Ownership enforcement)
            title TEXT NOT NULL,
            amount REAL NOT NULL,
            type TEXT NOT NULL CHECK(type IN ('income', 'expense')),
            category_id INTEGER,
            category_name TEXT NOT NULL,
            date TEXT NOT NULL,
            note TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        );
    ''')

    # 4. Monthly Budget / Goals Table (Form 2 Extra Feature)
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS budgets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            category_name TEXT NOT NULL,
            monthly_limit REAL NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ''')

    conn.commit()

    # Seed Default Thai Categories if empty
    cursor.execute("SELECT COUNT(*) as count FROM categories WHERE user_id IS NULL")
    if cursor.fetchone()['count'] == 0:
        default_categories = [
            # รายรับ (Income)
            ('เงินเดือน', 'income', 'bi-briefcase', '#10B981'),
            ('รายได้เสริม / ธุรกิจ', 'income', 'bi-shop', '#F59E0B'),
            ('เงินปันผล / การลงทุน', 'income', 'bi-graph-up-arrow', '#3B82F6'),
            ('โบนัส / รางวัล', 'income', 'bi-trophy', '#8B5CF6'),
            ('รายรับอื่นๆ', 'income', 'bi-wallet2', '#06B6D4'),
            # รายจ่าย (Expense)
            ('ค่าที่พัก / คอนโด', 'expense', 'bi-house-door', '#6366F1'),
            ('ค่าน้ำ ค่าไฟ ค่าเน็ต', 'expense', 'bi-receipt', '#F97316'),
            ('ค่าอาหารและเครื่องดื่ม', 'expense', 'bi-cup-hot', '#EAB308'),
            ('ค่าเดินทางและน้ำมัน', 'expense', 'bi-car-front', '#0EA5E9'),
            ('ช้อปปิ้งและของใช้', 'expense', 'bi-cart3', '#EC4899'),
            ('ค่ารักษาพยาบาล / สุขภาพ', 'expense', 'bi-heart-pulse', '#EF4444'),
            ('ความบันเทิง / พักผ่อน', 'expense', 'bi-film', '#A855F7'),
            ('รายจ่ายอื่นๆ', 'expense', 'bi-tag', '#64748B')
        ]
        for name, type_, icon, color in default_categories:
            cursor.execute(
                "INSERT INTO categories (user_id, name, type, icon, color) VALUES (NULL, ?, ?, ?, ?)",
                (name, type_, icon, color)
            )
        conn.commit()

    # Seed Default Users & Demo Data
    cursor.execute("SELECT COUNT(*) as count FROM users")
    if cursor.fetchone()['count'] == 0:
        # 1. Admin Account (Requirement 2 & User Request)
        admin_pass = generate_password_hash("admin123")
        cursor.execute(
            "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)",
            ("Admin ICE", "ice@admin.local", admin_pass, "admin")
        )

        # 2. Demo User A (Requirement 3: Data Owner A)
        user_pass = generate_password_hash("user123")
        cursor.execute(
            "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)",
            ("demo_user", "demo@smartpocket.local", user_pass, "user")
        )

        # 3. Test User B (Requirement 3: Data Owner B - used to prove user A cannot access B's data)
        cursor.execute(
            "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)",
            ("user_b", "user_b@smartpocket.local", user_pass, "user")
        )

        conn.commit()

        # Fetch user IDs
        cursor.execute("SELECT id FROM users WHERE username = 'demo_user'")
        user_a_id = cursor.fetchone()['id']

        cursor.execute("SELECT id FROM users WHERE username = 'user_b'")
        user_b_id = cursor.fetchone()['id']

        # Seed sample transactions for demo_user (User A)
        sample_transactions_a = [
            ("เงินเดือนประจำ", 35000, "income", "เงินเดือน", "2026-10-01", "เงินเดือนประจำเดือน ต.ค."),
            ("รับงานฟรีแลนซ์ออกแบบ", 8500, "income", "รายได้เสริม / ธุรกิจ", "2026-10-05", "งาน UI ออกแบบเว็บไซต์"),
            ("เงินปันผลหุ้นและกองทุน", 2400, "income", "เงินปันผล / การลงทุน", "2026-10-08", "ปันผลรอบไตรมาส 3"),
            ("ค่าผ่อนคอนโด", 9500, "expense", "ค่าที่พัก / คอนโด", "2026-10-02", "จ่ายผ่านแอปธนาคาร"),
            ("ค่าน้ำ ค่าไฟ และอินเทอร์เน็ตบ้าน", 1850, "expense", "ค่าน้ำ ค่าไฟ ค่าเน็ต", "2026-10-04", "ค่าไฟ 1,200 + ค่าน้ำ 150 + เน็ต 500"),
            ("ซื้อของเข้าห้องซูเปอร์มาร์เก็ต", 2150, "expense", "ค่าอาหารและเครื่องดื่ม", "2026-10-06", "ซื้อของสดที่ Lotus's"),
            ("เติมน้ำมันรถยนต์", 1200, "expense", "ค่าเดินทางและน้ำมัน", "2026-10-07", "เติมเต็มถัง PTT"),
            ("ตั๋วคอนเสิร์ตและพักผ่อน", 1500, "expense", "ความบันเทิง / พักผ่อน", "2026-10-09", "พักผ่อนวันหยุด")
        ]

        for title, amount, type_, category_name, date_, note in sample_transactions_a:
            cursor.execute('''
                INSERT INTO transactions (user_id, title, amount, type, category_name, date, note)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ''', (user_a_id, title, amount, type_, category_name, date_, note))

        # Seed private transactions for user_b (User B - to test ownership)
        sample_transactions_b = [
            ("เงินเดือน User B", 42000, "income", "เงินเดือน", "2026-10-01", "เงินเดือนของ User B (User อื่นห้ามเห็น)"),
            ("ค่าเช่าหอพัก User B", 6000, "expense", "ค่าที่พัก / คอนโด", "2026-10-03", "ข้อมูลส่วนตัวของ User B")
        ]
        for title, amount, type_, category_name, date_, note in sample_transactions_b:
            cursor.execute('''
                INSERT INTO transactions (user_id, title, amount, type, category_name, date, note)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ''', (user_b_id, title, amount, type_, category_name, date_, note))

        conn.commit()

    conn.close()

if __name__ == '__main__':
    init_db()
    print("Database initialized successfully!")
