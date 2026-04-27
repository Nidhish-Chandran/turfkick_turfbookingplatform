# TurfKick Backend Setup Instructions

## 1. Database Setup
1. Open **phpMyAdmin** (usually at `http://localhost/phpmyadmin`).
2. Create a new database named `turfkick_db`.
3. Import the `schema.sql` file provided in the project root.
   - Or run the SQL commands inside `schema.sql` in the SQL tab.

## 2. PHP Environment
1. Install **XAMPP**, **WAMP**, or **LAMP**.
2. Place the project folder `TurfKick` inside the `htdocs` (XAMPP) or `www` (WAMP) directory.
3. Ensure the MySQL and Apache modules are running.

## 3. Database Configuration
1. Open `config/db.php`.
2. Update the `$user` and `$pass` variables if your MySQL credentials are different (default is `root` with no password).

## 4. Testing the Flow
1. **Registration**: 
   - Go to `userreg.html` to register as a user.
   - Go to `Owner_register.html` to register as an owner.
2. **Login**:
   - Use the login modal on `index.html`.
   - Use the credentials you just registered.
3. **Booking**:
   - Logged-in users will be redirected to `browse_turfs.html` (Note: You may need to update the JS in `browse_turfs.html` to fetch from `booking/get_turfs.php`).
   - Selecting a slot and clicking "Confirm" will hit `booking/create_booking.php`.

## 5. Security Features Implemented
- **PDO Prepared Statements**: Prevents SQL Injection.
- **Password Hashing**: Uses `password_hash()` for secure storage.
- **Database Constraints**: `UNIQUE` constraint on `(turf_id, booking_date, slot_id)` prevents double bookings.
- **Transaction Safety**: Uses `BEGIN TRANSACTION` and `FOR UPDATE` to handle concurrent booking attempts safely.
