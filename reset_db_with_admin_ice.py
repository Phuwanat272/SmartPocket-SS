import sqlite3
import os
from werkzeug.security import generate_password_hash

app_dir = os.path.dirname(os.path.abspath(__file__))
db_paths = [
    os.path.join(app_dir, 'database.db'),
    os.path.join(app_dir, 'database.sqlite')
]

for db_path in db_paths:
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()

    # Clear existing users & transactions to apply fresh seed
    cursor.execute("DELETE FROM transactions;")
    cursor.execute("DELETE FROM users;")
    cursor.execute("DELETE FROM categories WHERE user_id IS NOT NULL;")

    # Reset auto increment
    cursor.execute("DELETE FROM sqlite_sequence WHERE name IN ('users', 'transactions', 'categories');")

    admin_pass = generate_password_hash("admin123")
    user_pass = generate_password_hash("user123")

    # Insert Admin ICE (Requirement 2 & User Request)
    cursor.execute(
        "INSERT INTO users (id, username, email, password_hash, role) VALUES (1, ?, ?, ?, ?)",
        ("Admin ICE", "admin_ice@system.local", admin_pass, "admin")
    )

    # Insert demo_user
    cursor.execute(
        "INSERT INTO users (id, username, email, password_hash, role) VALUES (2, ?, ?, ?, ?)",
        ("demo_user", "demo@system.local", user_pass, "user")
    )

    # Insert Extra Users for deletion testing
    cursor.execute(
        "INSERT INTO users (id, username, email, password_hash, role) VALUES (3, ?, ?, ?, ?)",
        ("test_user_delete1", "test1@system.local", user_pass, "user")
    )
    cursor.execute(
        "INSERT INTO users (id, username, email, password_hash, role) VALUES (4, ?, ?, ?, ?)",
        ("test_user_delete2", "test2@system.local", user_pass, "user")
    )
    cursor.execute(
        "INSERT INTO users (id, username, email, password_hash, role) VALUES (5, ?, ?, ?, ?)",
        ("somchai_dev", "somchai@system.local", user_pass, "user")
    )

    # Re-seed transactions for demo_user
    sample_transactions = [
        (2, "Gaji bulanan", 5400000, "income", "Gaji", "2026-01-26", "เงินเดือนประจำเดือน"),
        (2, "Usaha kuliner", 5500000, "income", "Bisnis", "2026-01-25", "กำไรจากร้านอาหาร"),
        (2, "Listrik, PAM, Keamanan", 600000, "expense", "Tagihan", "2026-01-24", "ค่าน้ำ ค่าไฟ ค่าส่วนกลาง"),
        (2, "Pembayaran hutang", 650000, "income", "Lainnya (Income)", "2026-01-24", "รับชำระคืนค่างวด"),
        (2, "Check-up kesehatan rutin", 325000, "expense", "Kesehatan", "2026-01-23", "ตรวจสุขภาพประจำเดือน"),
        (2, "Taksi ke bandara", 175000, "expense", "Transportasi", "2026-01-22", "ค่าเดินทางสนามบิน"),
        (2, "Nonton film di bioskop", 120000, "expense", "Hiburan", "2026-01-20", "ตั๋วหนังและป๊อบคอร์น"),
        (2, "Reksadana", 2500000, "income", "Investasi", "2026-01-18", "ปันผลกองทุนรวม"),
        (2, "Ngopi dengan rekan kantor", 450000, "expense", "Lainnya (Expense)", "2026-01-17", "สังสรรค์กาแฟ"),
        (2, "Makan siang", 32000, "expense", "Makanan", "2026-01-16", "ข้าวเที่ยงประจำวัน"),
        (3, "ค่าอุปกรณ์เรียน (สำหรับลองลบ)", 1200, "expense", "Lainnya (Expense)", "2026-10-01", "ชีทเรียน"),
        (4, "ค่าซ่อมรถ (สำหรับลองลบ)", 3500, "expense", "Transportasi", "2026-10-02", "เปลี่ยนน้ำมันเครื่อง")
    ]

    for uid, title, amount, type_, category_name, date_, note in sample_transactions:
        cursor.execute('''
            INSERT INTO transactions (user_id, title, amount, type, category_name, date, note)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ''', (uid, title, amount, type_, category_name, date_, note))

    conn.commit()
    conn.close()
    print(f"Database at {db_path} updated with Admin ICE and test users!")
