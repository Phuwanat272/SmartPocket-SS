import sqlite3
import os

db_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'database.db')

conn = sqlite3.connect(db_path)
cursor = conn.cursor()

# Clear existing Indonesian categories and transactions
cursor.execute("DELETE FROM categories;")
cursor.execute("DELETE FROM transactions;")

# Seed clean Thai Default Categories
thai_categories = [
    # รายรับ (Income)
    (None, 'เงินเดือน / ค่าจ้าง', 'income', 'bi-briefcase', '#10B981'),
    (None, 'ธุรกิจส่วนตัว / ค้าขาย', 'income', 'bi-shop', '#F59E0B'),
    (None, 'การลงทุน / ปันผล', 'income', 'bi-graph-up-arrow', '#3B82F6'),
    (None, 'โบนัส / รางวัล', 'income', 'bi-trophy', '#8B5CF6'),
    (None, 'รายรับอื่นๆ', 'income', 'bi-wallet2', '#06B6D4'),
    # รายจ่าย (Expense)
    (None, 'ค่าน้ำ / ค่าไฟ / ค่าเน็ต', 'expense', 'bi-receipt', '#6366F1'),
    (None, 'ค่ารักษาพยาบาล / สุขภาพ', 'expense', 'bi-heart-pulse', '#EF4444'),
    (None, 'ค่าเดินทาง / น้ำมัน', 'expense', 'bi-car-front', '#F97316'),
    (None, 'ความบันเทิง / ท่องเที่ยว', 'expense', 'bi-film', '#EC4899'),
    (None, 'อาหารและเครื่องดื่ม', 'expense', 'bi-cup-hot', '#EAB308'),
    (None, 'ค่าที่พัก / คอนโด', 'expense', 'bi-house-door', '#84CC16'),
    (None, 'ช้อปปิ้งและของใช้', 'expense', 'bi-cart3', '#A855F7'),
    (None, 'รายจ่ายอื่นๆ', 'expense', 'bi-tag', '#64748B')
]

for cat in thai_categories:
    cursor.execute("INSERT INTO categories (user_id, name, type, icon, color) VALUES (?, ?, ?, ?, ?)", cat)

# Re-seed clean Thai transactions for demo_user (user_id = 2)
sample_transactions_a = [
    (2, "เงินเดือนประจำเดือน", 35000, "income", "เงินเดือน / ค่าจ้าง", "2026-10-01", "เงินเดือนประจำเดือน ต.ค."),
    (2, "รายได้พิเศษขายของออนไลน์", 8500, "income", "ธุรกิจส่วนตัว / ค้าขาย", "2026-10-05", "กำไรจากการขายของ"),
    (2, "เงินปันผลหุ้นและกองทุน", 2400, "income", "การลงทุน / ปันผล", "2026-10-08", "ปันผลรอบไตรมาส 3"),
    (2, "ค่าผ่อนคอนโดประจำเดือน", 9500, "expense", "ค่าที่พัก / คอนโด", "2026-10-02", "จ่ายผ่านแอปธนาคาร"),
    (2, "ค่าน้ำ ค่าไฟ และอินเทอร์เน็ตบ้าน", 1850, "expense", "ค่าน้ำ / ค่าไฟ / ค่าเน็ต", "2026-10-04", "ค่าไฟ 1,200 + ค่าน้ำ 150 + เน็ต 500"),
    (2, "ซื้อของสดเข้าตู้เย็นซูเปอร์มาร์เก็ต", 2150, "expense", "อาหารและเครื่องดื่ม", "2026-10-06", "ซื้อของสดที่ Lotus's"),
    (2, "เติมน้ำมันรถยนต์เต็มถัง", 1200, "expense", "ค่าเดินทาง / น้ำมัน", "2026-10-07", "เติมเต็มถัง PTT"),
    (2, "ตั๋วคอนเสิร์ตและพักผ่อน", 1500, "expense", "ความบันเทิง / ท่องเที่ยว", "2026-10-09", "พักผ่อนวันหยุด")
]

for t in sample_transactions_a:
    cursor.execute('''
        INSERT INTO transactions (user_id, title, amount, type, category_name, date, note)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ''', t)

conn.commit()
conn.close()
print("Successfully replaced all categories and transactions with 100% Thai language!")
