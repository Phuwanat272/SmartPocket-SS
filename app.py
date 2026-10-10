import os
import csv
import io
import secrets
from functools import wraps
from flask import Flask, render_template, request, redirect, url_for, flash, session, jsonify, Response, abort
from werkzeug.security import generate_password_hash, check_password_hash
from database import get_db, init_db

app = Flask(__name__, static_folder='static', static_url_path='/static')
app.secret_key = os.environ.get('SECRET_KEY', 'smartpocket_secure_session_key_2026_x89f')

# Initialize DB on startup
with app.app_context():
    init_db()

# --- Security Headers (Security Requirement: 10 pts) ---
@app.after_request
def apply_security_headers(response):
    response.headers['X-Content-Type-Options'] = 'nosniff'
    response.headers['X-Frame-Options'] = 'DENY'
    response.headers['X-XSS-Protection'] = '1; mode=block'
    response.headers['Referrer-Policy'] = 'strict-origin-when-cross-origin'
    return response

# --- CSRF Token Generation & Context Injection ---
@app.before_request
def generate_csrf_token():
    if 'csrf_token' not in session:
        session['csrf_token'] = secrets.token_hex(16)

@app.context_processor
def inject_csrf_token():
    return dict(csrf_token=session.get('csrf_token', ''))

def verify_csrf():
    token = request.form.get('csrf_token') or request.headers.get('X-CSRF-Token')
    if not token or token != session.get('csrf_token'):
        abort(403, description="CSRF Token mismatch or missing")

# --- Auth Decorators ---
def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if 'user_id' not in session:
            flash('กรุณาเข้าสู่ระบบก่อนใช้งาน', 'warning')
            return redirect(url_for('login'))
        return f(*args, **kwargs)
    return decorated_function

def admin_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if 'user_id' not in session:
            flash('กรุณาเข้าสู่ระบบก่อนใช้งาน', 'warning')
            return redirect(url_for('login'))
        if session.get('role') != 'admin':
            flash('คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (สำหรับ Admin เท่านั้น)', 'danger')
            return redirect(url_for('dashboard'))
        return f(*args, **kwargs)
    return decorated_function

# --- Authentication Routes (Requirement 1) ---
@app.route('/')
def index():
    if 'user_id' in session:
        return redirect(url_for('dashboard'))
    return redirect(url_for('login'))

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        verify_csrf()
        username_email = request.form.get('username_email', '').strip()
        password = request.form.get('password', '')

        if not username_email or not password:
            flash('กรุณากรอกข้อมูลให้ครบถ้วน', 'danger')
            return render_template('login.html')

        conn = get_db()
        cursor = conn.cursor()
        # Parameterized Case-Insensitive Query preventing SQL Injection
        cursor.execute(
            "SELECT * FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)", 
            (username_email, username_email)
        )
        user = cursor.fetchone()
        conn.close()

        if not user:
            flash('❌ ไม่พบชื่อผู้ใช้หรืออีเมลนี้ในระบบ กรุณาตรวจสอบอีกครั้งหรือสมัครสมาชิกใหม่', 'danger')
            return render_template('login.html')

        # Check if user is soft deleted
        if dict(user).get('is_deleted', 0) == 1:
            flash('⚠️ บัญชีนี้ถูกปิดใช้งานหรือลบออกจากระบบแล้ว (กรุณาติดต่อผู้ดูแลระบบเพื่อกู้คืน)', 'warning')
            return render_template('login.html')

        # Secure Password Hashing check (PBKDF2/SHA256)
        if check_password_hash(user['password_hash'], password):
            session['user_id'] = user['id']
            session['username'] = user['username']
            session['role'] = user['role']
            flash(f'ยินดีต้อนรับคุณ {user["username"]} เข้าสู่ระบบ!', 'success')
            return redirect(url_for('dashboard'))
        else:
            flash('❌ รหัสผ่านไม่ถูกต้อง! กรุณาตรวจสอบรหัสผ่านอีกครั้ง', 'danger')

    return render_template('login.html')

@app.route('/register', methods=['GET', 'POST'])
def register():
    if request.method == 'POST':
        verify_csrf()
        username = request.form.get('username', '').strip()
        email = request.form.get('email', '').strip()
        password = request.form.get('password', '')
        confirm_password = request.form.get('confirm_password', '')

        if not username or not email or not password:
            flash('กรุณากรอกข้อมูลให้ครบถ้วน', 'danger')
            return render_template('register.html')

        if password != confirm_password:
            flash('รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน', 'danger')
            return render_template('register.html')

        if len(password) < 6:
            flash('รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร', 'danger')
            return render_template('register.html')

        # Generate Secure Hash
        hashed_pw = generate_password_hash(password)

        conn = get_db()
        cursor = conn.cursor()
        try:
            # Secure Parameterized Insert
            cursor.execute(
                "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'user')",
                (username, email, hashed_pw)
            )
            conn.commit()
            flash('ลงทะเบียนสำเร็จ! สามารถเข้าสู่ระบบได้ทันที', 'success')
            conn.close()
            return redirect(url_for('login'))
        except Exception:
            conn.close()
            flash('ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น', 'danger')

    return render_template('register.html')

@app.route('/logout')
def logout():
    session.clear()
    flash('ออกจากระบบเรียบร้อยแล้ว', 'info')
    return redirect(url_for('login'))

# --- User Dashboard (Requirement 2, 3, 4) ---
@app.route('/dashboard')
@login_required
def dashboard():
    user_id = session['user_id']
    conn = get_db()
    cursor = conn.cursor()

    # Get User Transactions (Strict Ownership Enforcement: Only current user's records)
    cursor.execute(
        "SELECT * FROM transactions WHERE user_id = ? ORDER BY date DESC, id DESC",
        (user_id,)
    )
    transactions = [dict(row) for row in cursor.fetchall()]

    # Calculate Totals for this User
    cursor.execute(
        "SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'income'",
        (user_id,)
    )
    total_income = cursor.fetchone()['total'] or 0.0

    cursor.execute(
        "SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'expense'",
        (user_id,)
    )
    total_expense = cursor.fetchone()['total'] or 0.0

    balance = total_income - total_expense

    # Categories Breakdown for Income Chart
    cursor.execute('''
        SELECT category_name, SUM(amount) as total 
        FROM transactions 
        WHERE user_id = ? AND type = 'income'
        GROUP BY category_name
    ''', (user_id,))
    income_categories = [dict(row) for row in cursor.fetchall()]

    # Categories Breakdown for Expense Chart
    cursor.execute('''
        SELECT category_name, SUM(amount) as total 
        FROM transactions 
        WHERE user_id = ? AND type = 'expense'
        GROUP BY category_name
    ''', (user_id,))
    expense_categories = [dict(row) for row in cursor.fetchall()]

    # Get all categories (System default + User custom)
    cursor.execute(
        "SELECT * FROM categories WHERE user_id IS NULL OR user_id = ? ORDER BY name ASC",
        (user_id,)
    )
    categories = [dict(row) for row in cursor.fetchall()]

    conn.close()

    return render_template(
        'dashboard.html',
        transactions=transactions,
        total_income=total_income,
        total_expense=total_expense,
        balance=balance,
        income_categories=income_categories,
        expense_categories=expense_categories,
        categories=categories
    )

# --- CRUD APIs for Transactions (Requirement 3: Data Ownership Enforcement & Requirement 4: Form 1) ---

# 1. CREATE Transaction (Form 1)
@app.route('/api/transactions', methods=['POST'])
@login_required
def add_transaction():
    verify_csrf()
    user_id = session['user_id']
    title = request.form.get('title', '').strip()
    amount = request.form.get('amount', 0, type=float)
    type_ = request.form.get('type', '')
    category_name = request.form.get('category_name', '').strip()
    date_ = request.form.get('date', '')
    note = request.form.get('note', '').strip()

    if not title or amount <= 0 or type_ not in ('income', 'expense') or not category_name or not date_:
        flash('กรุณากรอกข้อมูลในฟอร์มให้ถูกต้องและครบถ้วน', 'danger')
        return redirect(url_for('dashboard'))

    conn = get_db()
    cursor = conn.cursor()
    # Insert with user_id to strictly bind data ownership to this user
    cursor.execute('''
        INSERT INTO transactions (user_id, title, amount, type, category_name, date, note)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ''', (user_id, title, amount, type_, category_name, date_, note))
    conn.commit()
    conn.close()

    flash(f'บันทึกรายการ "{title}" สำเร็จ!', 'success')
    return redirect(url_for('dashboard'))

# 2. READ Single Transaction (for Edit Modal)
@app.route('/api/transactions/<int:trans_id>', methods=['GET'])
@login_required
def get_transaction(trans_id):
    user_id = session['user_id']
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM transactions WHERE id = ?", (trans_id,))
    trans = cursor.fetchone()
    conn.close()

    if not trans:
        return jsonify({'error': 'Transaction not found'}), 404

    # Ownership check: User A cannot read details of User B's transaction
    if trans['user_id'] != user_id and session.get('role') != 'admin':
        return jsonify({'error': 'Forbidden: You do not own this transaction'}), 403

    return jsonify(dict(trans))

# 3. UPDATE Transaction (CRUD - Update with Ownership Verification)
@app.route('/api/transactions/<int:trans_id>/edit', methods=['POST'])
@login_required
def edit_transaction(trans_id):
    verify_csrf()
    user_id = session['user_id']
    title = request.form.get('title', '').strip()
    amount = request.form.get('amount', 0, type=float)
    type_ = request.form.get('type', '')
    category_name = request.form.get('category_name', '').strip()
    date_ = request.form.get('date', '')
    note = request.form.get('note', '').strip()

    if not title or amount <= 0 or type_ not in ('income', 'expense') or not category_name or not date_:
        flash('ข้อมูลที่ส่งมาไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง', 'danger')
        return redirect(url_for('dashboard'))

    conn = get_db()
    cursor = conn.cursor()

    # STRICT DATA OWNERSHIP CHECK: Ensure User A cannot update User B's transaction
    cursor.execute("SELECT user_id FROM transactions WHERE id = ?", (trans_id,))
    trans = cursor.fetchone()

    if not trans:
        flash('ไม่พบรายการที่ต้องการแก้ไข', 'danger')
    elif trans['user_id'] != user_id and session.get('role') != 'admin':
        flash('ตรวจพบการเข้าถึงโดยไม่ได้รับอนุญาต! คุณไม่สามารถแก้ไขข้อมูลของผู้อื่นได้ (Data Ownership Protection)', 'danger')
    else:
        cursor.execute('''
            UPDATE transactions 
            SET title = ?, amount = ?, type = ?, category_name = ?, date = ?, note = ?
            WHERE id = ?
        ''', (title, amount, type_, category_name, date_, note, trans_id))
        conn.commit()
        flash('แก้ไขรายการสำเร็จเรียบร้อยแล้ว!', 'success')

    conn.close()
    return redirect(url_for('dashboard'))

# 4. DELETE Transaction (CRUD - Delete with Ownership Verification)
@app.route('/api/transactions/<int:trans_id>/delete', methods=['POST'])
@login_required
def delete_transaction(trans_id):
    verify_csrf()
    user_id = session['user_id']
    conn = get_db()
    cursor = conn.cursor()

    # STRICT DATA OWNERSHIP CHECK: Ensure User A cannot delete User B's transaction
    cursor.execute("SELECT user_id, title FROM transactions WHERE id = ?", (trans_id,))
    trans = cursor.fetchone()

    if not trans:
        flash('ไม่พบรายการที่ต้องการลบ', 'danger')
    elif trans['user_id'] != user_id and session.get('role') != 'admin':
        flash('ตรวจพบการเข้าถึงโดยไม่ได้รับอนุญาต! คุณไม่มีสิทธิ์ลบรายการของผู้อื่น (Data Ownership Protection)', 'danger')
    else:
        cursor.execute("DELETE FROM transactions WHERE id = ?", (trans_id,))
        conn.commit()
        flash(f'ลบรายการ "{trans["title"]}" เรียบร้อยแล้ว', 'success')

    conn.close()
    return redirect(url_for('dashboard'))

# --- Form 2: Add Custom Category API (Requirement 4 - Form 2) ---
@app.route('/api/categories', methods=['POST'])
@login_required
def add_category():
    verify_csrf()
    user_id = session['user_id']
    name = request.form.get('name', '').strip()
    type_ = request.form.get('type', '')
    icon = request.form.get('icon', 'bi-tag')
    color = request.form.get('color', '#4F46E5')

    if not name or type_ not in ('income', 'expense'):
        flash('กรุณากรอกชื่อหมวดหมู่และประเภทให้ถูกต้อง', 'danger')
        return redirect(url_for('dashboard'))

    conn = get_db()
    cursor = conn.cursor()
    cursor.execute('''
        INSERT INTO categories (user_id, name, type, icon, color)
        VALUES (?, ?, ?, ?, ?)
    ''', (user_id, name, type_, icon, color))
    conn.commit()
    conn.close()

    flash(f'เพิ่มหมวดหมู่ส่วนตัว "{name}" เรียบร้อยแล้ว! (Form 2 บันทึกสำเร็จ)', 'success')
    return redirect(url_for('dashboard'))

# --- CSV Export API ---
@app.route('/api/export-csv')
@login_required
def export_csv():
    user_id = session['user_id']
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(
        "SELECT date, title, type, category_name, amount, note FROM transactions WHERE user_id = ? ORDER BY date DESC",
        (user_id,)
    )
    rows = cursor.fetchall()
    conn.close()

    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow(['Date', 'Title', 'Type', 'Category', 'Amount (THB)', 'Note'])

    for row in rows:
        writer.writerow([row['date'], row['title'], row['type'], row['category_name'], row['amount'], row['note']])

    output.seek(0)
    return Response(
        output.getvalue().encode('utf-8-sig'), # BOM for Excel Thai display
        mimetype='text/csv; charset=utf-8',
        headers={"Content-disposition": f"attachment; filename=transactions_user_{user_id}.csv"}
    )

# --- Admin Dashboard Page (Requirement 5) ---
@app.route('/admin')
@admin_required
def admin_dashboard():
    conn = get_db()
    cursor = conn.cursor()

    # System Metrics
    cursor.execute("SELECT COUNT(*) as count FROM users WHERE COALESCE(is_deleted, 0) = 0")
    total_users = cursor.fetchone()['count']

    cursor.execute("SELECT COUNT(*) as count FROM users WHERE is_deleted = 1")
    total_deleted_users = cursor.fetchone()['count']

    cursor.execute("SELECT COUNT(*) as count FROM transactions")
    total_transactions = cursor.fetchone()['count']

    cursor.execute("SELECT SUM(amount) as total FROM transactions WHERE type = 'income'")
    system_total_income = cursor.fetchone()['total'] or 0.0

    cursor.execute("SELECT SUM(amount) as total FROM transactions WHERE type = 'expense'")
    system_total_expense = cursor.fetchone()['total'] or 0.0

    # Active User List with Ownership count
    cursor.execute('''
        SELECT u.id, u.username, u.email, u.role, u.created_at,
               COUNT(t.id) as trans_count
        FROM users u
        LEFT JOIN transactions t ON u.id = t.user_id
        WHERE COALESCE(u.is_deleted, 0) = 0
        GROUP BY u.id
        ORDER BY u.id ASC
    ''')
    users = [dict(row) for row in cursor.fetchall()]

    # Soft Deleted Users List (Trash)
    cursor.execute('''
        SELECT u.id, u.username, u.email, u.role, u.created_at,
               COUNT(t.id) as trans_count
        FROM users u
        LEFT JOIN transactions t ON u.id = t.user_id
        WHERE u.is_deleted = 1
        GROUP BY u.id
        ORDER BY u.id ASC
    ''')
    deleted_users = [dict(row) for row in cursor.fetchall()]

    # System Categories list
    cursor.execute("SELECT * FROM categories ORDER BY user_id IS NULL DESC, id ASC")
    categories = [dict(row) for row in cursor.fetchall()]

    conn.close()

    return render_template(
        'admin.html',
        total_users=total_users,
        total_deleted_users=total_deleted_users,
        total_transactions=total_transactions,
        system_total_income=system_total_income,
        system_total_expense=system_total_expense,
        users=users,
        deleted_users=deleted_users,
        categories=categories
    )

@app.route('/admin/users/<int:target_user_id>/role', methods=['POST'])
@admin_required
def admin_toggle_role(target_user_id):
    verify_csrf()
    if target_user_id == session['user_id']:
        flash('ไม่สามารถเปลี่ยนสิทธิ์บัญชีของตนเองได้', 'warning')
        return redirect(url_for('admin_dashboard'))

    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT role, username FROM users WHERE id = ?", (target_user_id,))
    target = cursor.fetchone()

    if target:
        new_role = 'user' if target['role'] == 'admin' else 'admin'
        cursor.execute("UPDATE users SET role = ? WHERE id = ?", (new_role, target_user_id))
        conn.commit()
        flash(f'อัปเดตสิทธิ์ผู้ใช้งาน "{target["username"]}" เป็น {new_role} เรียบร้อยแล้ว', 'success')
    
    conn.close()
    return redirect(url_for('admin_dashboard'))

# Admin Edit User (Username & Email)
@app.route('/admin/users/<int:target_user_id>/edit', methods=['POST'])
@admin_required
def admin_edit_user(target_user_id):
    verify_csrf()
    new_username = request.form.get('username', '').strip()
    new_email = request.form.get('email', '').strip()

    if not new_username or not new_email:
        flash('กรุณากรอกชื่อผู้ใช้และอีเมลให้ถูกต้อง', 'danger')
        return redirect(url_for('admin_dashboard'))

    conn = get_db()
    cursor = conn.cursor()
    try:
        cursor.execute("UPDATE users SET username = ?, email = ? WHERE id = ?", (new_username, new_email, target_user_id))
        conn.commit()
        if target_user_id == session['user_id']:
            session['username'] = new_username
        flash(f'แก้ไขข้อมูลผู้ใช้ #{target_user_id} เป็น "{new_username}" สำเร็จแล้ว!', 'success')
    except sqlite3.IntegrityError:
        flash('ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว', 'danger')
    
    conn.close()
    return redirect(url_for('admin_dashboard'))

# Admin Reset User Password
@app.route('/admin/users/<int:target_user_id>/reset-password', methods=['POST'])
@admin_required
def admin_reset_password(target_user_id):
    verify_csrf()
    new_password = request.form.get('new_password', '')

    if len(new_password) < 6:
        flash('รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร', 'danger')
        return redirect(url_for('admin_dashboard'))

    hashed_pw = generate_password_hash(new_password)

    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("UPDATE users SET password_hash = ? WHERE id = ?", (hashed_pw, target_user_id))
    conn.commit()
    conn.close()

    flash(f'รีเซ็ตรหัสผ่านสำหรับผู้ใช้งาน #{target_user_id} เรียบร้อยแล้ว!', 'success')
    return redirect(url_for('admin_dashboard'))

# Admin Soft Delete User (Move to Trash)
@app.route('/admin/users/<int:target_user_id>/delete', methods=['POST'])
@admin_required
def admin_delete_user(target_user_id):
    verify_csrf()
    if target_user_id == session['user_id']:
        flash('ไม่สามารถลบบัญชีผู้ดูแลระบบที่กำลังใช้งานอยู่ได้', 'danger')
        return redirect(url_for('admin_dashboard'))

    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT username FROM users WHERE id = ?", (target_user_id,))
    target = cursor.fetchone()

    if target:
        cursor.execute("UPDATE users SET is_deleted = 1 WHERE id = ?", (target_user_id,))
        conn.commit()
        flash(f'ย้ายผู้ใช้งาน "{target["username"]}" ไปยังถังขยะเรียบร้อยแล้ว (สามารถกดกู้คืนได้เสมอ)', 'warning')

    conn.close()
    return redirect(url_for('admin_dashboard'))

# Admin Restore User (กู้คืนผู้ใช้)
@app.route('/admin/users/<int:target_user_id>/restore', methods=['POST'])
@admin_required
def admin_restore_user(target_user_id):
    verify_csrf()
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT username FROM users WHERE id = ?", (target_user_id,))
    target = cursor.fetchone()

    if target:
        cursor.execute("UPDATE users SET is_deleted = 0 WHERE id = ?", (target_user_id,))
        conn.commit()
        flash(f'กู้คืนบัญชีผู้ใช้งาน "{target["username"]}" กลับคืนสู่ระบบเรียบร้อยแล้ว!', 'success')

    conn.close()
    return redirect(url_for('admin_dashboard'))

if __name__ == '__main__':
    # Run server locally on port 5000
    app.run(host='0.0.0.0', port=5000, debug=True)
