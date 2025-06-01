from flask_login import UserMixin
from werkzeug.security import generate_password_hash, check_password_hash
from database import get_db_connection
from datetime import datetime, timezone
import sqlite3
import pytz

class User(UserMixin):
    def __init__(self, id, username, email, password_hash, is_verified=False):
        self.id = id
        self.username = username
        self.email = email
        self.password_hash = password_hash
        self.is_verified = is_verified
        bulgaria_tz = pytz.timezone('Europe/Sofia')
        self.created_at = datetime.now(bulgaria_tz)

    @staticmethod
    def get(user_id):
        conn = get_db_connection()
        user = conn.execute('SELECT * FROM users WHERE id = ?', (user_id,)).fetchone()
        conn.close()
        if user:
            return User(user['id'], user['username'], user['email'], 
                       user['password_hash'], user['is_verified'])
        return None

    @staticmethod
    def get_by_username(username):
        conn = get_db_connection()
        user = conn.execute('SELECT * FROM users WHERE username = ?', (username,)).fetchone()
        conn.close()
        if user:
            return User(user['id'], user['username'], user['email'], user['password_hash'])
        return None

    @staticmethod
    def get_by_email(email):
        conn = get_db_connection()
        user = conn.execute('SELECT * FROM users WHERE email = ?', (email,)).fetchone()
        conn.close()
        if user:
            return User(user['id'], user['username'], user['email'], 
                       user['password_hash'], user['is_verified'])
        return None

    def check_password(self, password):
        return check_password_hash(self.password_hash, password)

    @staticmethod
    def create(username, email, password):
        try:
            conn = get_db_connection()
            password_hash = generate_password_hash(password)
            cursor = conn.cursor()
            cursor.execute('''
                INSERT INTO users (username, email, password_hash, is_verified)
                VALUES (?, ?, ?, ?)
            ''', (username, email, password_hash, False))
            conn.commit()
            cursor.close()
            conn.close()
            return True
        except Exception as e:
            print(f"Error creating user: {e}")
            return False

    @staticmethod
    def verify_user(email):
        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            cursor.execute('''
                UPDATE users 
                SET is_verified = 1 
                WHERE email = ?
            ''', (email,))
            conn.commit()
            cursor.close()
            conn.close()
            return True
        except Exception as e:
            print(f"Error verifying user: {e}")
            return False

    @staticmethod
    def log_login(user_id, username):
        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            cursor.execute('''
                INSERT INTO login_logs (user_id, username)
                VALUES (?, ?)
            ''', (user_id, username))
            conn.commit()
            cursor.close()
            conn.close()
            return True
        except Exception as e:
            print(f"Error logging login: {e}")
            return False

    @staticmethod
    def get_login_logs(limit=50):
        conn = get_db_connection()
        logs = conn.execute('''
            SELECT l.*, u.email 
            FROM login_logs l 
            JOIN users u ON l.user_id = u.id 
            ORDER BY l.login_time DESC 
            LIMIT ?
        ''', (limit,)).fetchall()
        conn.close()
        
        bulgaria_tz = pytz.timezone('Europe/Sofia')
        formatted_logs = []
        for log in logs:
            log_dict = dict(log)
            if 'login_time' in log_dict:
                dt = datetime.strptime(log_dict['login_time'], '%Y-%m-%d %H:%M:%S')
                dt = dt.replace(tzinfo=timezone.utc)
                local_dt = dt.astimezone(bulgaria_tz)
                log_dict['login_time'] = local_dt.strftime('%d.%m.%Y %H:%M')
            formatted_logs.append(log_dict)
        return formatted_logs

    @staticmethod
    def is_admin_email(email):
        conn = get_db_connection()
        admin = conn.execute('''
            SELECT * FROM admin_emails 
            WHERE email = ? AND is_active = 1
        ''', (email,)).fetchone()
        conn.close()
        return admin is not None 