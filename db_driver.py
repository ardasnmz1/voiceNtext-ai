import sqlite3
from typing import Dict, Optional, List

class DatabaseDriver:
    def __init__(self, db_path: str = 'auto_service.db'):
        self.db_path = db_path
        self.init_database()

    def init_database(self):
        with sqlite3.connect(self.db_path) as conn:
            cursor = conn.cursor()
            
            # Müşteri tablosu oluşturma
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS customers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    phone TEXT,
                    email TEXT
                )
            ''')
            
            # Araç tablosu oluşturma
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS vehicles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    vin TEXT UNIQUE,
                    make TEXT,
                    model TEXT,
                    year INTEGER,
                    customer_id INTEGER,
                    FOREIGN KEY (customer_id) REFERENCES customers (id)
                )
            ''')
            
            # Servis geçmişi tablosu oluşturma
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS service_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    vehicle_id INTEGER,
                    service_date TEXT,
                    service_type TEXT,
                    description TEXT,
                    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id)
                )
            ''')
            conn.commit()

    def lookup_vin(self, vin: str) -> Optional[Dict]:
        with sqlite3.connect(self.db_path) as conn:
            cursor = conn.cursor()
            cursor.execute('''
                SELECT v.*, c.name, c.phone, c.email
                FROM vehicles v
                LEFT JOIN customers c ON v.customer_id = c.id
                WHERE v.vin = ?
            ''', (vin,))
            result = cursor.fetchone()
            
            if result:
                return {
                    'vehicle': {
                        'id': result[0],
                        'vin': result[1],
                        'make': result[2],
                        'model': result[3],
                        'year': result[4]
                    },
                    'customer': {
                        'name': result[6],
                        'phone': result[7],
                        'email': result[8]
                    }
                }
            return None

    def create_customer(self, name: str, phone: str = None, email: str = None) -> int:
        with sqlite3.connect(self.db_path) as conn:
            cursor = conn.cursor()
            cursor.execute('''
                INSERT INTO customers (name, phone, email)
                VALUES (?, ?, ?)
            ''', (name, phone, email))
            conn.commit()
            return cursor.lastrowid

    def create_vehicle(self, vin: str, make: str, model: str, year: int, customer_id: int) -> int:
        with sqlite3.connect(self.db_path) as conn:
            cursor = conn.cursor()
            cursor.execute('''
                INSERT INTO vehicles (vin, make, model, year, customer_id)
                VALUES (?, ?, ?, ?, ?)
            ''', (vin, make, model, year, customer_id))
            conn.commit()
            return cursor.lastrowid

    def add_service_history(self, vehicle_id: int, service_type: str, description: str):
        with sqlite3.connect(self.db_path) as conn:
            cursor = conn.cursor()
            cursor.execute('''
                INSERT INTO service_history (vehicle_id, service_date, service_type, description)
                VALUES (?, DATE('now'), ?, ?)
            ''', (vehicle_id, service_type, description))
            conn.commit()

    def get_service_history(self, vehicle_id: int) -> List[Dict]:
        with sqlite3.connect(self.db_path) as conn:
            cursor = conn.cursor()
            cursor.execute('''
                SELECT service_date, service_type, description
                FROM service_history
                WHERE vehicle_id = ?
                ORDER BY service_date DESC
            ''', (vehicle_id,))
            results = cursor.fetchall()
            
            return [{
                'date': row[0],
                'type': row[1],
                'description': row[2]
            } for row in results]