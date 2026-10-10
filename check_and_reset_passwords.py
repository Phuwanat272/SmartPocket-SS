import sqlite3
import os
from werkzeug.security import generate_password_hash, check_password_hash

db_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'database.db')

conn = sqlite3.connect(db_path)
conn.row_factory = sqlite3.Row
cursor = conn.cursor()

# Set clean hashed passwords for Admin ICE and demo_user
admin_hash = generate_password_hash("admin123")
user_hash = generate_password_hash("user123")

cursor.execute("UPDATE users SET password_hash = ?, is_deleted = 0 WHERE username = 'Admin ICE'", (admin_hash,))
cursor.execute("UPDATE users SET password_hash = ?, is_deleted = 0 WHERE username = 'demo_user'", (user_hash,))

conn.commit()

# Verify contents
cursor.execute("SELECT id, username, email, role, is_deleted FROM users")
users = cursor.fetchall()

print("Current Users in DB:")
for u in users:
    print(f"ID: {u['id']} | Username: '{u['username']}' | Email: '{u['email']}' | Role: {u['role']} | Deleted: {u['is_deleted']}")

conn.close()
