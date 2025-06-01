import sqlite3
from datetime import datetime, timezone
import pytz

def show_all_profile():
    conn = sqlite3.connect('profile.db')
    cursor = conn.cursor()
    
    cursor.execute("SELECT * FROM profiles")
    profiles = cursor.fetchall()
    
    cursor.close()
    conn.close()
    
    return profiles

def add_one_profile(name_of_profile, fabric_number, price_per_meter_profile, profile_in_store):
    conn = sqlite3.connect('profile.db')
    cursor = conn.cursor()
    
    cursor.execute("""
        INSERT INTO profiles (name_of_profile, fabric_number, price_per_meter_profile, profile_in_store)
        VALUES (?, ?, ?, ?)
    """, (name_of_profile, fabric_number, price_per_meter_profile, profile_in_store))
    
    conn.commit()
    cursor.close()
    conn.close()

def delete_one_profile(name_of_profile):
    conn = sqlite3.connect('profile.db')
    cursor = conn.cursor()
    
    cursor.execute("DELETE FROM profiles WHERE name_of_profile = ?", (name_of_profile,))
    conn.commit()
    
    cursor.close()
    conn.close()

def update_one_profile(name_of_profile, new_price):
    conn = sqlite3.connect('profile.db')
    cursor = conn.cursor()
    
    cursor.execute("""
        UPDATE profiles 
        SET price_per_meter_profile = ?
        WHERE name_of_profile = ?
    """, (new_price, name_of_profile))
    
    conn.commit()
    cursor.close()
    conn.close()

def create_table_profile():
    conn = sqlite3.connect('profile.db')
    cursor = conn.cursor()
    
    cursor.execute("""CREATE TABLE IF NOT EXISTS profiles(
        name_of_profile TEXT PRIMARY KEY,
        fabric_number TEXT,
        price_per_meter_profile REAL,
        profile_in_store INTEGER
    )""")
    
    conn.commit()
    cursor.close()
    conn.close()

def delete_table_profile():
    conn = sqlite3.connect('profile.db')
    cursor = conn.cursor()

    cursor.execute("DROP TABLE IF EXISTS profiles")

    conn.commit()
    cursor.close()
    conn.close()
    
    
      
def create_table_glass():
    conn = sqlite3.connect('glass.db')
    cursor = conn.cursor()

    cursor.execute("""CREATE TABLE IF NOT EXISTS glasses(
                      name_of_glass TEXT PRIMARY KEY,
                      price_per_sqrmeter_glass REAL
                      )""")

    conn.commit()
    cursor.close()
    conn.close()   

def show_all_glass():
    conn = sqlite3.connect('glass.db')
    cursor = conn.cursor()
    
    cursor.execute("SELECT * FROM glasses")
    glasses = cursor.fetchall()
    
    cursor.close()
    conn.close()
    
    return glasses

def add_one_glass(name_of_glass, price_per_sqrmeter_glass):
    conn = sqlite3.connect('glass.db')
    cursor = conn.cursor()
    
    cursor.execute("""
        INSERT INTO glasses (name_of_glass, price_per_sqrmeter_glass)
        VALUES (?, ?)
    """, (name_of_glass, price_per_sqrmeter_glass))
    
    conn.commit()
    cursor.close()
    conn.close()

def delete_one_glass(name_of_glass):
    conn = sqlite3.connect('glass.db')
    cursor = conn.cursor()
    
    cursor.execute("DELETE FROM glasses WHERE name_of_glass = ?", (name_of_glass,))
    conn.commit()
    
    cursor.close()
    conn.close()

def update_one_glass(name_of_glass, new_price):
    conn = sqlite3.connect('glass.db')
    cursor = conn.cursor()
    
    cursor.execute("""
        UPDATE glasses 
        SET price_per_sqrmeter_glass = ?
        WHERE name_of_glass = ?
    """, (new_price, name_of_glass))
    
    conn.commit()
    cursor.close()
    conn.close()


def create_table():
    conn = sqlite3.connect("passepartout.db")
    cursur = conn.cursor()

    cursur.execute("""CREATE TABLE IF NOT EXISTS passepartouts (
        name_of_passepartout TEXT, 
        fabric_number TEXT, 
        width_1 , price_1 REAL, 
        size_2 TEXT, price_2 REAL, 
        size_3 TEXT, price_3 REAL, 
        size_4 TEXT, price_4 REAL, 
        size_5 TEXT, price_5 REAL, 
        size_6 TEXT, price_6 REAL, 
        size_7 TEXT, price_7 REAL, 
        size_8 TEXT, price_8 REAL, 
        passepartout_in_store INTEGER)""")

    conn.commit()
    conn.close()
  

def show_all_passepartout():
    conn = sqlite3.connect('passepartout.db')
    cursor = conn.cursor()
    
    cursor.execute("SELECT * FROM passepartouts")
    passepartouts = cursor.fetchall()
    
    cursor.close()
    conn.close()
    
    return passepartouts

def add_one_passepartout(name_of_passepartout, fabric_number, sizes, prices, passepartout_in_store):
    conn = sqlite3.connect('passepartout.db')
    cursor = conn.cursor()
    
    fields = ["name_of_passepartout", "fabric_number"]
    placeholders = ["?", "?"]
    values = [name_of_passepartout, fabric_number]
    
    for i, (size, price) in enumerate(zip(sizes, prices), 1):
        fields.append(f"size_{i}")
        fields.append(f"price_{i}")
        placeholders.append("?")
        placeholders.append("?")
        values.extend([size, price])
    
    fields.append("passepartout_in_store")
    placeholders.append("?")
    values.append(passepartout_in_store)
    
    query = f"""INSERT INTO passepartouts ({', '.join(fields)})
                VALUES ({', '.join(placeholders)})"""
    
    cursor.execute(query, values)
    conn.commit()
    
    cursor.close()
    conn.close()

def delete_one_passepartout(name_of_passepartout):
    conn = sqlite3.connect('passepartout.db')
    cursor = conn.cursor()
    
    cursor.execute("DELETE FROM passepartouts WHERE name_of_passepartout = ?", (name_of_passepartout,))
    conn.commit()
    
    cursor.close()
    conn.close()

def update_one_passepartout(name_of_passepartout, fabric_number, sizes, prices, stock):
    conn = sqlite3.connect('passepartout.db')
    cursor = conn.cursor()
    
    update_fields = ["fabric_number = ?"]
    params = [fabric_number]
    
    for i, (size, price) in enumerate(zip(sizes, prices), 1):
        update_fields.append(f"size_{i} = ?")
        update_fields.append(f"price_{i} = ?")
        params.extend([size, price])
    
    update_fields.append("passepartout_in_store = ?")
    params.append(stock)
    params.append(name_of_passepartout)
    
    query = f"""UPDATE passepartouts 
               SET {', '.join(update_fields)}
               WHERE name_of_passepartout = ?"""
    
    cursor.execute(query, params)
    conn.commit()
    
    cursor.close()
    conn.close()

def create_table_passepartout():
    conn = sqlite3.connect('passepartout.db')
    cursor = conn.cursor()
    
    cursor.execute("""CREATE TABLE IF NOT EXISTS passepartouts(
        name_of_passepartout TEXT PRIMARY KEY,
        fabric_number TEXT,
        passepartout_in_store INTEGER
    )""")
    
    for i in range(1, 5):
        try:
            cursor.execute(f"ALTER TABLE passepartouts ADD COLUMN size_{i} TEXT")
            cursor.execute(f"ALTER TABLE passepartouts ADD COLUMN price_{i} REAL")
        except sqlite3.OperationalError:
            pass
    
    conn.commit()
    cursor.close()
    conn.close()

def show_all_orders():
    conn = sqlite3.connect('orders.db')
    cursor = conn.cursor()
    
    cursor.execute("SELECT * FROM orders ORDER BY date DESC")
    orders = cursor.fetchall()
    
    cursor.close()
    conn.close()
    
    return orders

def add_order(width, height, profile, glass, passepartout, back, hanging, 
             description, customer_name, price, discount, advance):
    conn = sqlite3.connect('orders.db')
    cursor = conn.cursor()
    
    bulgaria_tz = pytz.timezone('Europe/Sofia')
    current_time = datetime.now(bulgaria_tz)
    
    cursor.execute("""
        INSERT INTO orders (date, width, height, profile, glass, passepartout, 
                          back, hanging, description, customer_name, price, 
                          discount, advance, paid, taken)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)
    """, (current_time.strftime('%Y-%m-%d'), width, height, profile, glass,
          passepartout, back, hanging, description, customer_name, price,
          discount, advance))
    
    conn.commit()
    cursor.close()
    conn.close()

def update_order(order_id, width, height, profile, glass, passepartout, back, hanging,
                description, customer_name, price, discount, advance, paid):
    conn = sqlite3.connect('orders.db')
    cursor = conn.cursor()
    
    cursor.execute("""
        UPDATE orders 
        SET width = ?, height = ?, profile = ?, glass = ?, passepartout = ?,
            back = ?, hanging = ?, description = ?, customer_name = ?, price = ?,
            discount = ?, advance = ?, paid = ?
        WHERE id = ?
    """, (width, height, profile, glass, passepartout, back, hanging,
          description, customer_name, price, discount, advance, paid, order_id))
    
    conn.commit()
    cursor.close()
    conn.close()

def delete_order(order_id):
    conn = sqlite3.connect('orders.db')
    cursor = conn.cursor()
    
    cursor.execute("DELETE FROM orders WHERE id = ?", (order_id,))
    
    conn.commit()
    cursor.close()
    conn.close()

def create_table_orders():
    conn = sqlite3.connect('orders.db')
    cursor = conn.cursor()
    
    cursor.execute("""CREATE TABLE IF NOT EXISTS orders(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date TEXT,
        width INTEGER,
        height INTEGER,
        profile TEXT,
        glass TEXT,
        passepartout TEXT,
        back TEXT,
        hanging TEXT,
        description TEXT,
        customer_name TEXT,
        price REAL,
        discount INTEGER,
        advance REAL,
        paid BOOLEAN,
        taken BOOLEAN
    )""")
    
    conn.commit()
    cursor.close()
    conn.close()

def get_db_connection():
    conn = sqlite3.connect('database.db')
    conn.row_factory = sqlite3.Row
    return conn

def init_db():
    conn = get_db_connection()
    conn.execute('''
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            is_verified BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ''')
    
    conn.execute('''
        CREATE TABLE IF NOT EXISTS login_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            username TEXT NOT NULL,
            login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users (id)
        )
    ''')
    
    # conn.execute('''
    #     CREATE TABLE IF NOT EXISTS admin_emails (
    #         id INTEGER PRIMARY KEY AUTOINCREMENT,
    #         email TEXT UNIQUE NOT NULL,
    #         is_active BOOLEAN DEFAULT 1
    #     )
    # ''')
    
    conn.execute('''
        CREATE TABLE IF NOT EXISTS feedback (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ''')
    
    conn.execute('''
        CREATE TABLE IF NOT EXISTS profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            price REAL NOT NULL,
            stock INTEGER NOT NULL
        )
    ''')
    
    conn.execute('''
        CREATE TABLE IF NOT EXISTS glasses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            price REAL NOT NULL,
            stock INTEGER NOT NULL
        )
    ''')
    
    conn.execute('''
        CREATE TABLE IF NOT EXISTS passepartouts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            price REAL NOT NULL,
            stock INTEGER NOT NULL
        )
    ''')
    
    conn.execute('''
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number INTEGER NOT NULL,
            sub_order_number INTEGER DEFAULT 0,
            date TEXT NOT NULL,
            width REAL,
            height REAL,
            profile TEXT,
            additional_profiles TEXT,
            frame_count INTEGER DEFAULT 1,  
            description TEXT,
            glass TEXT,
            back TEXT,
            passepartout TEXT,
            hanging TEXT,
            price REAL,
            advance_payment REAL,
            discount REAL,
            customer_name TEXT,
            paid BOOLEAN DEFAULT 0,
            collected BOOLEAN DEFAULT 0
        )
    ''')
    conn.commit()
    conn.close()