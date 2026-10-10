import sqlite3
import os
from werkzeug.security import generate_password_hash

db_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'database.db')

conn = sqlite3.connect(db_path)
cursor = conn.cursor()

admin_hash = generate_password_hash("admin123")
user_hash = generate_password_hash("user123")

# Set ID 1 to Admin ICE with admin123 password
cursor.execute(
    "UPDATE users SET username = 'Admin ICE', email = 'ice@admin.local', password_hash = ?, is_deleted = 0 WHERE id = 1",
    (admin_hash,)
)

# Set ID 2 to demo_user with user123 password
cursor.execute(
    "UPDATE users SET password_hash = ?, is_deleted = 0 WHERE username = 'demo_user'",
    (user_hash,)
)

# Set icesyy password to user123 if exists
cursor.execute(
    "UPDATE users SET password_hash = ?, is_deleted = 0 WHERE username = 'icesyy'",
    (user_hash,)
)

conn.commit()

cursor.execute("SELECT id, username, email, role, is_deleted FROM users")
print("Updated Database Users:")
for u in cursor.fetchall():
    print(dict(zip([d[0] for d in cursor.description], u)))

conn.close()
