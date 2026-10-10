import sqlite3
import os

desktop_path = r"C:\Users\Asus\Desktop\User_Accounts_SmartPocket_SS.txt"
db_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'database.db')

conn = sqlite3.connect(db_path)
conn.row_factory = sqlite3.Row
cursor = conn.cursor()

cursor.execute("SELECT id, username, email, role, is_deleted FROM users ORDER BY id ASC")
users = cursor.fetchall()
conn.close()

content = []
content.append("=" * 65)
content.append("   ระบบ SmartPocket SS - ข้อมูลบัญชีผู้ใช้และรหัสผ่านสำหรับใช้งาน")
content.append("   ผู้พัฒนา: Phuwanat Phuthongkhao (68011211138)")
content.append("=" * 65)
content.append("")
content.append("🌐 URL ระบบ: http://127.0.0.1:5000/login")
content.append("")
content.append("-" * 65)
content.append("📌 1. บัญชีผู้ดูแลระบบ (Admin Account)")
content.append("-" * 65)
content.append("   - บทบาท (Role)        : ผู้ดูแลระบบ (ADMIN)")
content.append("   - ชื่อผู้ใช้ (Username) : Admin ICE")
content.append("   - อีเมล (Email)        : ice@admin.local")
content.append("   - รหัสผ่าน (Password)   : admin123")
content.append("   * สิทธิ์: จัดการผู้ใช้ทั้งหมด, รีเซ็ตรหัสผ่าน, ลบ/กู้คืนบัญชี, ดูสถิติระบบ")
content.append("")
content.append("-" * 65)
content.append("📌 2. บัญชีผู้ใช้ทั่วไป (User Accounts)")
content.append("-" * 65)
content.append("   [บัญชีที่ 1: บัญชีสาธิตระบบ (Demo User)]")
content.append("   - ชื่อผู้ใช้ (Username) : demo_user")
content.append("   - อีเมล (Email)        : demo@system.local")
content.append("   - รหัสผ่าน (Password)   : user123")
content.append("   * มีประวัติรายการบันทึกรายรับ-รายจ่ายตัวอย่างพร้อมแสดงผลกราฟ")
content.append("")
content.append("   [บัญชีที่ 2: บัญชีของคุณ (icesyy)]")
content.append("   - ชื่อผู้ใช้ (Username) : icesyy")
content.append("   - อีเมล (Email)        : ice12345@gmail.com")
content.append("   - รหัสผ่าน (Password)   : user123")
content.append("")
content.append("=" * 65)
content.append("📋 สรุปรายชื่อผู้ใช้ทั้งหมดในฐานข้อมูล (Database Users List):")
content.append("=" * 65)

for u in users:
    status = "อยู่ในถังขยะ (Deleted)" if u['is_deleted'] == 1 else "ใช้งานปกติ (Active)"
    default_pw = "admin123" if u['role'] == 'admin' else "user123"
    content.append(f"• ID #{u['id']:<2} | Username: {u['username']:<16} | Role: {u['role'].upper():<6} | รหัสผ่าน: {default_pw:<10} | สถานะ: {status}")

content.append("")
content.append("=" * 65)
content.append("💡 หมายเหตุ:")
content.append("1. ระบบรองรับการล็อกอินแบบ Case-Insensitive (พิมพ์ตัวเล็ก/ใหญ่ได้ เช่น admin ice หรือ Admin ICE)")
content.append("2. สำหรับกรณีนำขึ้นโฮสต์ PHP/XAMPP รหัสผ่านเริ่มต้นคือ: 123456")
content.append("=" * 65)

text_data = "\n".join(content)

with open(desktop_path, 'w', encoding='utf-8-sig') as f:
    f.write(text_data)

print(f"Created file on Desktop successfully at: {desktop_path}")
