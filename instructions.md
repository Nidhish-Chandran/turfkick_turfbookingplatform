# TurfKick: Setup and Testing Guide

TurfKick is a modern, AJAX-based web application for discovering and booking sports turfs. It features a role-based system for Users, Turf Owners, and Administrators.

---

## 1. Project Overview
TurfKick streamlines the process of finding and booking sports facilities. 
- **Users**: Browse turfs, check real-time availability, and book slots.
- **Owners**: List their turfs, manage details, and track bookings.
- **Admins**: Moderate the platform by approving/disabling turfs and managing users.

### Tech Stack
- **Frontend**: HTML5, CSS3, JavaScript (Fetch API / AJAX).
- **Backend**: PHP 7.4+ (PDO for database interactions).
- **Database**: MySQL.
- **Security**: CSRF protection, password hashing (bcrypt), and SQL injection prevention.

---

## 2. Prerequisites
- **Web Server**: XAMPP, WAMP, or LAMP.
- **PHP Version**: 7.4 or higher.
- **Database**: MySQL / MariaDB.
- **Browser**: Modern browser (Chrome, Firefox, Edge).

---

## 3. Setup Instructions

### Step 1: Place Project Files
Copy the `TurfKick` folder into your server's root directory:
- **XAMPP**: `C:\xampp\htdocs\TurfKick`
- **WAMP**: `C:\wamp64\www\TurfKick`
- **Linux**: `/var/www/html/TurfKick`

### Step 2: Start Apache and MySQL
Open your XAMPP/WAMP Control Panel and start the **Apache** and **MySQL** services.

### Step 3: Create the Database
1. Open your browser and go to `http://localhost/phpmyadmin/`.
2. Click **New** and create a database named `turfkick_db`.
3. Select the database and click the **Import** tab.
4. Choose the `schema.sql` file from the project root and click **Go**.
5. Repeat for `admin_update.sql` to ensure the admin role and default user are added.

### Step 4: Configure Database Connection
Open `config/db.php` and verify the settings match your environment:
```php
$host = 'localhost';
$db   = 'turfkick_db';
$user = 'root'; // Default XAMPP user
$pass = '';     // Default XAMPP password
```

### Step 5: Folder Permissions
Ensure the `uploads/` folder is writable by the server so that owner documents can be saved correctly.

### Step 6: Access the Website
Open your browser and navigate to:
`http://localhost/TurfKick/index.html`

---

## 4. Running the Project
The application starts at `index.html`. Users can browse available turfs without logging in, but must authenticate to book.

---

## 5. Functional Testing

### A. Registration
- **Steps**: Click "Login" -> "Register Now" -> Fill user details -> Submit.
- **Expected Result**: "Registration successful!" alert and redirect to login.

### B. Login (Multi-Role)
- **Steps**: Open Login Modal -> Select Tab (User/Owner/Admin) -> Enter credentials -> Submit.
- **Admin Credentials**: `admin@turfkick.com` / `admin123`.
- **Expected Result**: Correct redirect (User -> Browse, Owner -> Dashboard, Admin -> Admin Panel).

### C. Turf Browsing
- **Steps**: On the landing page or User Dashboard, use the search bar or filters.
- **Expected Result**: Listings update instantly as you type or select a sport category.

### D. Slot Selection & Availability Check
- **Steps**: Click a Turf -> Click an available slot.
- **Expected Result**: The system sends a background request to `api/check_availability.php`. If the slot was just booked by someone else, an alert will appear immediately.

### E. Booking Flow
- **Steps**: Select a slot -> Click "Confirm and Pay".
- **Expected Result**: Booking is confirmed via AJAX; "My History" is updated without a page reload.

### F. Double Booking Prevention (Race Condition)
- **Simulation**: Open two different browsers. Log into both as different users. Try to book the **same slot** at the **same time**.
- **Expected Result**: One user succeeds; the other receives an error message: "Slot was just booked by someone else." (Handled by `FOR UPDATE` in SQL).

### G. Cancellation
- **Steps**: Go to "My History" -> Click a booking -> Click "Cancel Booking".
- **Expected Result**: Status changes to "cancelled"; slot becomes available for others.

### H. Admin Panel
- **Steps**: Log in as Admin -> Go to "Turfs" tab -> Toggle status (Approve/Disable).
- **Expected Result**: Turf status updates instantly; disabled turfs disappear from the user browse page.

---

## 6. API Testing
You can test the endpoints directly using tools like Postman. 
- **Example Request**: `GET http://localhost/TurfKick/api/get_turfs.php`
- **Sample Response**:
```json
{
  "status": "success",
  "message": "Turfs fetched successfully.",
  "data": [...]
}
```

---

## 7. Common Errors & Fixes
1. **DB Connection Error**: Verify `config/db.php` credentials and ensure MySQL is running.
2. **CSRF Token Error**: Ensure your browser accepts cookies and `session_start()` is working.
3. **Uploads Fail**: Create the `uploads/` folder manually if it's missing and check write permissions.
4. **404 Not Found**: Ensure the URL path matches your folder name (e.g., `localhost/TurfKick/`).

---

## 8. Folder Structure Overview
- `api/`: AJAX endpoints (Pure PHP returning JSON).
- `config/`: Database connection settings.
- `includes/`: Common helper functions and session logic.
- `js/`: Modular JavaScript logic (Auth, Bookings, Admin).
- `uploads/`: Storage for owner verification documents.

---

## 9. Security Notes
- **Passwords**: Never stored in plain text; always hashed using `bcrypt`.
- **SQLi**: Every query uses PDO prepared statements.
- **CSRF**: Every POST request is validated with a unique session-based token.
- **XSS**: All user-generated content is sanitized before display.

---
**Happy Testing!**
