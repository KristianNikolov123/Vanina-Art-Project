from flask import Flask, render_template, request, redirect, url_for, flash, jsonify
from flask_login import LoginManager, login_user, logout_user, login_required, current_user
from models import User
from database import get_db_connection, init_db
import sqlite3
from datetime import datetime, timezone, timedelta
import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
import os
from dotenv import load_dotenv
import pytz
load_dotenv()

app = Flask(__name__)
app.secret_key = 'your-secret-key-here'  # Промени това на нещо случайно и сложно!

db_initialized = False
# Настройка на Flask-Login
login_manager = LoginManager()
login_manager.init_app(app)
login_manager.login_view = 'login'

# Email настройки
ADMIN_EMAIL = os.getenv('ADMIN_EMAIL')
ADMIN_EMAIL_PASSWORD = os.getenv('ADMIN_EMAIL_PASSWORD')
SMTP_SERVER = "smtp.gmail.com"
SMTP_PORT = int(os.getenv("SMTP_PORT", 465))

# print(f"===> ADMIN_EMAIL: {ADMIN_EMAIL}")
# print(f"===> ADMIN_EMAIL_PASSWORD: {bool(ADMIN_EMAIL_PASSWORD)}")


# def send_verification_email(to_email, username):
#     print("===> ВЛЯЗОХ В send_verification_email")
#     msg = MIMEMultipart()
#     msg['From'] = ADMIN_EMAIL
#     msg['To'] = to_email
#     msg['Subject'] = "Верификация на акаунт - Ванина Арт"
    
#     body = f"""
#     Здравейте {username},
    
#     Моля, потвърдете вашия имейл адрес, като отговорите на този имейл.
#     След потвърждение, ще получите достъп до системата.
    
#     Поздрави,
#     Ванина Арт
#     """
    
#     msg.attach(MIMEText(body, 'plain'))
    
#     try:
#         server = smtplib.SMTP_SSL(SMTP_SERVER, SMTP_PORT)
#         server.login(ADMIN_EMAIL, ADMIN_EMAIL_PASSWORD)
#         server.send_message(msg)
#         return True
#     except Exception as e:
#         print(f"Error sending email: {e}")
#         return False

def send_verification_email_to_admin(new_user_email, new_user_username):
    # print("===> ВЛЯЗОХ В send_verification_email_to_admin")
    msg = MIMEMultipart()
    msg['From'] = ADMIN_EMAIL
    msg['To'] = ADMIN_EMAIL
    msg['Subject'] = "Нова заявка за регистрация - Ванина Арт"

    body = f"""
    Нов потребител иска да се регистрира:
    Потребителско име: {new_user_username}
    Имейл: {new_user_email}

    За да активирате акаунта, кликнете тук:
    {url_for('verify_email', email=new_user_email, _external=True)}
    """
    msg.attach(MIMEText(body, 'plain'))

    try:
        # print("===> Опит за свързване със SMTP...")
        with smtplib.SMTP_SSL(SMTP_SERVER, SMTP_PORT) as server:
            server.login(ADMIN_EMAIL, ADMIN_EMAIL_PASSWORD)
            # print("===> Изпращане на съобщение...")
            # print("===> Съобщението изглежда така:")
            # print(msg.as_string())
            server.send_message(msg)
        # print("===> Имейлът е изпратен успешно!")
        return True
    except Exception as e:
        print(f"Error sending email: {e}")
        flash(f"Грешка при изпращане на имейл: {e}", 'error')
        return False


@login_manager.user_loader
def load_user(user_id):
    return User.get(user_id)

init_db()

@app.before_request
def initialize_database():
    global db_initialized
    if not db_initialized:
        init_db()
        db_initialized = True

@app.route('/')
@login_required
def home():
    conn = get_db_connection()
    feedbacks = conn.execute('SELECT * FROM feedback ORDER BY created_at DESC').fetchall()
    conn.close()
    
    feedbacks_list = []
    bulgaria_tz = pytz.timezone('Europe/Sofia')
    for fb in feedbacks:
        feedback_dict = dict(fb)
        dt = datetime.strptime(feedback_dict['created_at'], '%Y-%m-%d %H:%M:%S')
        dt = dt.replace(tzinfo=timezone.utc)
        local_dt = dt.astimezone(bulgaria_tz)
        feedback_dict['formatted_time'] = local_dt.strftime('%d.%m.%Y %H:%M')
        feedbacks_list.append(feedback_dict)
    
    return render_template('home.html', feedbacks=feedbacks_list)

@app.route('/login', methods=['GET', 'POST'])
def login():
    if current_user.is_authenticated:
        return redirect(url_for('home'))
        
    if request.method == 'POST':
        email = request.form['email']
        password = request.form['password']
        
        user = User.get_by_email(email)
        if user and user.check_password(password):
            if not user.is_verified:
                flash('Моля, потвърдете вашия имейл преди да влезете.', 'warning')
                return redirect(url_for('login'))
            
            login_user(user)
            User.log_login(user.id, user.username)
            flash('Успешен вход!', 'success')
            return redirect(url_for('home'))
        else:
            flash('Грешен имейл или парола!', 'danger')
    
    return render_template('login.html')

@app.route('/signup', methods=['GET', 'POST'])
def signup():
    # print("===> ВЛЯЗОХ В signup")   
    if current_user.is_authenticated:
        return redirect(url_for('home'))
        
    if request.method == 'POST':
        # print("===> ВЛЯЗОХ В POST")
        username = request.form['username']
        email = request.form['email']
        password = request.form['password']
        confirm_password = request.form['confirm_password']
        
        # if not User.is_admin_email(email):
        #     flash('Този имейл няма право на регистрация!', 'danger')
        #     return redirect(url_for('signup'))
        
        if password != confirm_password:
            flash('Паролите не съвпадат!', 'danger')
            return redirect(url_for('signup'))
            
        if User.create(username, email, password):
            # print("===> REGISTRATION OK, sending email to admin...")
            if send_verification_email_to_admin(email, username):
                flash('Регистрацията беше успешна! Моля, изчакайте администратор да потвърди акаунта ви.', 'success')
            else:
                flash('Регистрацията беше успешна, но има проблем с уведомяването на администратора.', 'warning')
            return redirect(url_for('login'))
        else:
            flash('Потребителското име или имейлът вече съществуват!', 'danger')
    
    return render_template('signup.html')

@app.route('/verify/<email>')
def verify_email(email):
    if User.verify_user(email):
        flash('Имейлът е потвърден успешно! Можете да влезете в системата.', 'success')
    else:
        flash('Грешка при потвърждаване на имейла.', 'danger')
    return redirect(url_for('login'))

@app.route('/archives')
@login_required
def archives():
    if not User.is_admin_email(current_user.email):
        flash('Нямате достъп до тази страница!', 'danger')
        return redirect(url_for('home'))
    
    logs = User.get_login_logs()
    return render_template('archives.html', logs=logs)

@app.route('/check_auth')
def check_auth():
    return jsonify({'authenticated': current_user.is_authenticated})

@app.route('/logout')
@login_required
def logout():
    logout_user()
    flash('Успешно излязохте от системата!', 'success')
    return redirect(url_for('login'))

@app.route('/submit_feedback', methods=['POST'])
@login_required
def submit_feedback():
    # print("===> ВЛЯЗОХ В submit_feedback")
    message = request.form.get('message', '').strip()
    if message:
        conn = get_db_connection()
        conn.execute('INSERT INTO feedback (message) VALUES (?)', (message,))
        conn.commit()
        conn.close()
        flash('Обратната връзка беше изпратена успешно!', 'success')
    return redirect(url_for('home'))

@app.route('/delete_feedback/<int:feedback_id>', methods=['POST'])
@login_required
def delete_feedback(feedback_id):
    conn = get_db_connection()
    conn.execute('DELETE FROM feedback WHERE id = ?', (feedback_id,))
    conn.commit()
    conn.close()
    flash('Обратната връзка е изтрита.', 'success')
    return redirect(url_for('home'))

@app.route('/edit_feedback/<int:feedback_id>', methods=['POST'])
@login_required
def edit_feedback(feedback_id):
    new_message = request.form.get('edit_message', '').strip()
    if new_message:
        conn = get_db_connection()
        conn.execute('UPDATE feedback SET message = ? WHERE id = ?', (new_message, feedback_id))
        conn.commit()
        conn.close()
        flash('Обратната връзка е редактирана.', 'success')
    return redirect(url_for('home'))

@app.route('/interface')
@login_required
def interface():
    conn = get_db_connection()
    # Get all orders and sort them by order_number and sub_order_number
    orders = conn.execute('''
        SELECT * FROM orders 
        ORDER BY order_number, sub_order_number
    ''').fetchall()
    profiles = conn.execute('SELECT * FROM profiles').fetchall()
    passepartouts = conn.execute('SELECT * FROM passepartouts').fetchall()
    conn.close()
    return render_template('interface.html', orders=orders, profiles=profiles, passepartouts=passepartouts)

@app.route('/profiles')
@login_required
def profiles():
    conn = get_db_connection()
    profiles = conn.execute('SELECT * FROM profiles').fetchall()
    conn.close()
    return render_template('profiles.html', profiles=profiles)

@app.route('/glasses')
@login_required
def glasses():
    conn = get_db_connection()
    glasses = conn.execute('SELECT * FROM glasses').fetchall()
    conn.close()
    return render_template('glasses.html', glasses=glasses)

@app.route('/passepartouts')
@login_required
def passepartouts():
    conn = get_db_connection()
    passepartouts = conn.execute('SELECT * FROM passepartouts').fetchall()
    conn.close()
    return render_template('passepartouts.html', passepartouts=passepartouts)

@app.route('/add_order', methods=['POST'])
@login_required
def add_order():
    conn = get_db_connection()
    c = conn.cursor()
    
    last_order = c.execute('SELECT MAX(order_number) FROM orders').fetchone()[0]
    order_number = (last_order or 0) + 1
    
    try:
        additional_profiles = request.form.getlist('additional_profiles[]')
        if additional_profiles:
            additional_profiles = ', '.join(additional_profiles)
        else:
            additional_profiles = None

        c.execute('''
            INSERT INTO orders (
                order_number, sub_order_number, date, width, height, profile, glass, passepartout,
                back, hanging, customer_name, price, paid, collected,
                additional_profiles, frame_count, advance_payment, discount, description
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ''', (
            order_number,
            0,  # sub_order_number starts at 0 for main orders
            request.form['date'],
            request.form['width'],
            request.form['height'],
            request.form['profile'],
            request.form['glass'],
            request.form['passepartout'],
            request.form['back'],
            request.form['hanging'],
            request.form['customer_name'],
            request.form['price'],
            'paid' in request.form,
            'collected' in request.form,
            additional_profiles,
            request.form.get('frame_count', 1),
            request.form.get('advance_payment'),
            request.form.get('discount'),
            request.form.get('description')
        ))
        conn.commit()
        flash('Поръчката е добавена успешно!', 'success')
    except Exception as e:
        flash(f'Грешка при добавяне на поръчката: {str(e)}', 'error')
    finally:
        conn.close()
    return redirect(url_for('interface'))

@app.route('/edit_order/<int:order_id>', methods=['POST'])
@login_required
def edit_order(order_id):
    conn = get_db_connection()
    c = conn.cursor()
    try:
        additional_profiles = request.form.getlist('additional_profiles[]')
        if additional_profiles:
            additional_profiles = ', '.join(additional_profiles)
        else:
            additional_profiles = None

        c.execute('''
            UPDATE orders
            SET date = ?, width = ?, height = ?, profile = ?, glass = ?,
                passepartout = ?, back = ?, hanging = ?, customer_name = ?,
                price = ?, paid = ?, collected = ?, additional_profiles = ?,
                frame_count = ?, advance_payment = ?, discount = ?, description = ?
            WHERE id = ?
        ''', (
            request.form['date'],
            request.form['width'],
            request.form['height'],
            request.form['profile'],
            request.form['glass'],
            request.form['passepartout'],
            request.form['back'],
            request.form['hanging'],
            request.form['customer_name'],
            request.form['price'],
            'paid' in request.form,
            'collected' in request.form,
            additional_profiles,
            request.form.get('frame_count', 1),
            request.form.get('advance_payment'),
            request.form.get('discount'),
            request.form.get('description'),
            order_id
        ))
        conn.commit()
        flash('Поръчката е редактирана успешно!', 'success')
    except Exception as e:
        flash(f'Грешка при редактиране на поръчката: {str(e)}', 'error')
    finally:
        conn.close()
    return redirect(url_for('interface'))

@app.route('/delete_order/<int:order_id>')
@login_required
def delete_order(order_id):
    conn = get_db_connection()
    c = conn.cursor()
    
    try:
        c.execute('DELETE FROM orders WHERE id = ?', (order_id,))
        conn.commit()
        flash('Поръчката е изтрита успешно!', 'success')
    except Exception as e:
        flash(f'Грешка при изтриване на поръчка: {str(e)}', 'danger')
    finally:
        conn.close()
    
    return redirect(url_for('interface'))

@app.route('/add_profile', methods=['POST'])
@login_required
def add_profile():
    conn = get_db_connection()
    c = conn.cursor()
    
    try:
        c.execute('''
            INSERT INTO profiles (name, price, stock)
            VALUES (?, ?, ?)
        ''', (
            request.form['name'],
            request.form['price'],
            request.form['stock']
        ))
        conn.commit()
        flash('Профилът е добавен успешно!', 'success')
    except Exception as e:
        flash(f'Грешка при добавяне на профил: {str(e)}', 'danger')
    finally:
        conn.close()
    
    return redirect(url_for('profiles'))

@app.route('/edit_profile/<int:profile_id>', methods=['POST'])
@login_required
def edit_profile(profile_id):
    conn = get_db_connection()
    c = conn.cursor()
    
    try:
        c.execute('''
            UPDATE profiles
            SET name = ?, price = ?, stock = ?
            WHERE id = ?
        ''', (
            request.form['name'],
            request.form['price'],
            request.form['stock'],
            profile_id
        ))
        conn.commit()
        flash('Профилът е редактиран успешно!', 'success')
    except Exception as e:
        flash(f'Грешка при редактиране на профил: {str(e)}', 'danger')
    finally:
        conn.close()
    
    return redirect(url_for('profiles'))

@app.route('/delete_profile/<int:profile_id>')
@login_required
def delete_profile(profile_id):
    conn = get_db_connection()
    c = conn.cursor()
    
    try:
        c.execute('DELETE FROM profiles WHERE id = ?', (profile_id,))
        conn.commit()
        flash('Профилът е изтрит успешно!', 'success')
    except Exception as e:
        flash(f'Грешка при изтриване на профил: {str(e)}', 'danger')
    finally:
        conn.close()
    
    return redirect(url_for('profiles'))

@app.route('/add_glass', methods=['POST'])
@login_required
def add_glass():
    conn = get_db_connection()
    c = conn.cursor()
    
    try:
        c.execute('''
            INSERT INTO glasses (name, price, stock)
            VALUES (?, ?, ?)
        ''', (
            request.form['name'],
            request.form['price'],
            request.form['stock']
        ))
        conn.commit()
        flash('Стъклото е добавено успешно!', 'success')
    except Exception as e:
        flash(f'Грешка при добавяне на стъкло: {str(e)}', 'danger')
    finally:
        conn.close()
    
    return redirect(url_for('glasses'))

@app.route('/edit_glass/<int:glass_id>', methods=['POST'])
@login_required
def edit_glass(glass_id):
    conn = get_db_connection()
    c = conn.cursor()
    
    try:
        c.execute('''
            UPDATE glasses
            SET name = ?, price = ?, stock = ?
            WHERE id = ?
        ''', (
            request.form['name'],
            request.form['price'],
            request.form['stock'],
            glass_id
        ))
        conn.commit()
        flash('Стъклото е редактирано успешно!', 'success')
    except Exception as e:
        flash(f'Грешка при редактиране на стъкло: {str(e)}', 'danger')
    finally:
        conn.close()
    
    return redirect(url_for('glasses'))

@app.route('/delete_glass/<int:glass_id>')
@login_required
def delete_glass(glass_id):
    conn = get_db_connection()
    c = conn.cursor()
    
    try:
        c.execute('DELETE FROM glasses WHERE id = ?', (glass_id,))
        conn.commit()
        flash('Стъклото е изтрито успешно!', 'success')
    except Exception as e:
        flash(f'Грешка при изтриване на стъкло: {str(e)}', 'danger')
    finally:
        conn.close()
    
    return redirect(url_for('glasses'))

@app.route('/add_passepartout', methods=['POST'])
@login_required
def add_passepartout():
    conn = get_db_connection()
    c = conn.cursor()
    
    try:
        c.execute('''
            INSERT INTO passepartouts (name, price, stock)
            VALUES (?, ?, ?)
        ''', (
            request.form['name'],
            request.form['price'],
            request.form['stock']
        ))
        conn.commit()
        flash('Паспартуто е добавено успешно!', 'success')
    except Exception as e:
        flash(f'Грешка при добавяне на паспарту: {str(e)}', 'danger')
    finally:
        conn.close()
    
    return redirect(url_for('passepartouts'))

@app.route('/edit_passepartout/<int:passepartout_id>', methods=['POST'])
@login_required
def edit_passepartout(passepartout_id):
    conn = get_db_connection()
    c = conn.cursor()
    
    try:
        c.execute('''
            UPDATE passepartouts
            SET name = ?, price = ?, stock = ?
            WHERE id = ?
        ''', (
            request.form['name'],
            request.form['price'],
            request.form['stock'],
            passepartout_id
        ))
        conn.commit()
        flash('Паспартуто е редактирано успешно!', 'success')
    except Exception as e:
        flash(f'Грешка при редактиране на паспарту: {str(e)}', 'danger')
    finally:
        conn.close()
    
    return redirect(url_for('passepartouts'))

@app.route('/delete_passepartout/<int:passepartout_id>')
@login_required
def delete_passepartout(passepartout_id):
    conn = get_db_connection()
    c = conn.cursor()
    
    try:
        c.execute('DELETE FROM passepartouts WHERE id = ?', (passepartout_id,))
        conn.commit()
        flash('Паспартуто е изтрито успешно!', 'success')
    except Exception as e:
        flash(f'Грешка при изтриване на паспарту: {str(e)}', 'danger')
    finally:
        conn.close()
    
    return redirect(url_for('passepartouts'))

@app.route('/add_sub_order/<int:order_id>', methods=['POST'])
@login_required
def add_sub_order(order_id):
    conn = get_db_connection()
    c = conn.cursor()
    
    try:
        main_order = c.execute('SELECT * FROM orders WHERE id = ?', (order_id,)).fetchone()
        if not main_order:
            flash('Основната поръчка не е намерена!', 'error')
            return redirect(url_for('interface'))

        last_sub_order = c.execute('''
            SELECT MAX(sub_order_number) 
            FROM orders 
            WHERE order_number = ?
        ''', (main_order['order_number'],)).fetchone()[0]
        
        # If this is the first sub-order, set main order to 1.1 and new order to 1.2
        if last_sub_order == 0:
            sub_order_number = 2
            # Update main order to 1.1
            c.execute('''
                UPDATE orders 
                SET sub_order_number = 1 
                WHERE id = ?
            ''', (order_id,))
        else:
            # For subsequent sub-orders, increment by 1
            sub_order_number = last_sub_order + 1

        additional_profiles = request.form.getlist('additional_profiles[]')
        if additional_profiles:
            additional_profiles = ', '.join(additional_profiles)
        else:
            additional_profiles = None

        c.execute('''
            INSERT INTO orders (
                order_number, sub_order_number, date, width, height, profile, glass, passepartout,
                back, hanging, customer_name, price, paid, collected,
                additional_profiles, frame_count, advance_payment, discount, description
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ''', (
            main_order['order_number'],
            sub_order_number,
            request.form['date'],
            request.form['width'],
            request.form['height'],
            request.form['profile'],
            request.form['glass'],
            request.form['passepartout'],
            request.form['back'],
            request.form['hanging'],
            main_order['customer_name'],
            request.form['price'],
            'paid' in request.form,
            'collected' in request.form,
            additional_profiles,
            request.form.get('frame_count', 1),
            request.form.get('advance_payment'),
            request.form.get('discount'),
            request.form.get('description')
        ))

        conn.commit()
        flash('Подпоръчката е добавена успешно!', 'success')
    except Exception as e:
        flash(f'Грешка при добавяне на подпоръчката: {str(e)}', 'error')
    finally:
        conn.close()
    return redirect(url_for('interface'))

def format_date_ddmmyyyy(date_str):
    try:
        return datetime.strptime(date_str, '%Y-%m-%d').strftime('%d/%m/%Y')
    except Exception:
        return date_str

app.jinja_env.filters['format_date_ddmmyyyy'] = format_date_ddmmyyyy

@app.template_filter('short_glass')
def short_glass(value):
    mapping = {
        'Антирефлексно': 'Анти',
        'Нормално': 'Норм',
        'Музейно': 'Музейно',
        'Консервационно': 'Конс',
        'Огледало': 'Огледало',
        'Плексиглас': 'Плекси'
    }
    return mapping.get(value, value)

@app.template_filter('short_back')
def short_back(value):
    mapping = {
        'Велпапе': 'Велп',
        'Бирен картон': 'Бирен',
        'Сив картон': 'Сив'
    }
    return mapping.get(value, value)

@app.template_filter('short_hanging')
def short_hanging(value):
    mapping = {
        'Закачалка': 'Закач',
        'Две закачалки': '2 закач',
        'Връзка': 'Връзка'
    }
    return mapping.get(value, value)

if __name__ == '__main__':
    init_db()
    app.run(debug=True) 