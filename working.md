# TurfKick: Technical Understanding Document

## 1. System Flow (Step-by-Step)

### A. Registration Flow
1.  **Frontend**: User fills out the registration form (`userreg.html` or `Owner_register.html`). JavaScript (`js/auth.js`) intercepts the submit event, validates password matching, and fetches a CSRF token from `api/get_token.php`.
2.  **AJAX Request**: A `POST` request containing user data, files (if owner), and the CSRF token is sent to `api/register.php`.
3.  **Backend**: PHP validates the CSRF token, sanitizes inputs, hashes the password using `password_hash()`, and handles file uploads to the `/uploads` directory.
4.  **Database**: Inserts a new record into the `users` table. If the role is 'owner', an initial entry is also created in the `turfs` table.
5.  **Response**: Returns a JSON success message, and the frontend redirects the user to the login page.

### B. Login Flow
1.  **Frontend**: User enters credentials in the modal on `index.html`.
2.  **AJAX Request**: `js/auth.js` sends a `POST` request to `api/login.php`.
3.  **Backend**: PHP verifies the email and role, then checks the password using `password_verify()`. On success, it initializes `$_SESSION` variables (`user_id`, `user_name`, `role`).
4.  **Response**: Returns a JSON object with a `redirect` URL (either `browse_turfs.html` or `owner_dashboard.html`).

### C. Turf & Slot Selection
1.  **Frontend**: On `browse_turfs.html`, `js/bookings.js` fetches all active turfs from `api/get_turfs.php` and renders them.
2.  **Interaction**: When a turf is clicked, the app fetches available time slots from `api/get_slots.php`.
3.  **Real-time Check**: When a user clicks a slot, an AJAX request is sent to `api/check_availability.php` to verify if the slot was booked in the last few seconds by someone else.

### D. Booking Flow
1.  **AJAX Request**: `js/bookings.js` sends turf ID, slot ID, and date to `api/create_booking.php`.
2.  **Backend Logic**: 
    - Validates session and CSRF token.
    - Starts a **Database Transaction**.
    - Performs a `SELECT ... FOR UPDATE` query to lock the slot and re-verify availability.
3.  **Database**: If available, inserts records into `bookings` and `payments` tables.
4.  **Response**: Returns confirmation. The frontend refreshes the booking history.

### E. Cancellation Flow
1.  **Interaction**: User clicks "Cancel" in their history log.
2.  **Backend**: `api/cancel_booking.php` verifies the booking belongs to the user and is still in 'upcoming' status before updating it to 'cancelled'.

---

## 2. Project Structure

```text
TurfKick/
├── index.html              # Landing page with Login/Auth Modals
├── browse_turfs.html       # User Dashboard (Turf listing & Booking)
├── owner_dashboard.html    # Owner Dashboard (Turf management)
├── userreg.html            # User Registration Page
├── Owner_register.html     # Owner Registration Page (with file uploads)
├── api/                    # AJAX Endpoints (PHP)
│   ├── login.php           # Authenticates users
│   ├── register.php        # Handles new account creation
│   ├── get_token.php       # Provides CSRF tokens for forms
│   ├── get_turfs.php       # Returns active turfs as JSON
│   ├── get_slots.php       # Returns time slots for a specific turf
│   ├── check_availability.php # Real-time slot status check
│   ├── create_booking.php  # Handles booking transactions
│   ├── cancel_booking.php  # Processes cancellations
│   ├── manage_turfs.php    # Owner CRUD actions for turfs
│   └── get_user.php        # Returns session data
├── config/
│   └── db.php              # PDO Database connection settings
├── includes/
│   └── helpers.php         # Utility functions (JSON, CSRF, Sanitization)
├── js/                     # Frontend Logic
│   ├── auth.js             # Handles login/register AJAX & CSRF
│   ├── bookings.js         # Handles user dashboard & booking UI
│   └── owner.js            # Handles owner turf updates
├── uploads/                # Stores owner ID/License documents
└── schema.sql              # Database structure
```

---

## 3. JavaScript Logic (AJAX & Event Handling)

### Form Handling
All forms use `event.preventDefault()` to stop the standard browser submission.
```javascript
// Example from js/auth.js
form.addEventListener('submit', async (e) => {
    e.preventDefault(); // Stop page reload
    const formData = new FormData(form);
    const response = await fetch(endpoint, { method: 'POST', body: formData });
    const result = await response.json();
    // Update UI based on result
});
```

### Data Synchronization
The frontend uses the **Fetch API** to communicate with the backend. Data is sent as `FormData` (multipart/form-data) to support both text fields and file uploads.

---

## 4. Database Design

### Schema Overview
- **users**: Stores ID, name, email, hashed password, and role ('user' or 'owner').
- **turfs**: Links to an `owner_id`. Stores location, price, and category.
- **time_slots**: Predefined slots (e.g., 4 PM - 5 PM) linked to a `turf_id`.
- **bookings**: Links a user to a turf and a specific slot on a specific date.
- **payments**: Tracks payment status for every booking.

### Constraints & Double Booking Prevention
1.  **Unique Key**: The `bookings` table has a composite unique index:
    ```sql
    UNIQUE KEY unique_booking (turf_id, booking_date, slot_id)
    ```
    This prevents the database from ever accepting two bookings for the same turf/date/slot combination at a physical level.
2.  **Status Logic**: The availability check ignores bookings with `status = 'cancelled'`.

---

## 5. Backend (PHP) Architecture

### Security Implementations
- **Prepared Statements**: Uses PDO to prevent SQL Injection.
  ```php
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([$email]);
  ```
- **CSRF Protection**: Every state-changing request (POST) requires a `csrf_token` that matches the one stored in `$_SESSION`.
- **Input Sanitization**: `htmlspecialchars()` and `trim()` are applied to all incoming text to prevent XSS.
- **Session Security**: Critical actions check `is_logged_in()` and `is_owner()` before execution.

---

## 6. AJAX / API Flow Example

### Request (Frontend -> Backend)
**Endpoint**: `api/create_booking.php`  
**Method**: `POST`  
**Payload**:
```json
{
  "turf_id": 1,
  "slot_id": 5,
  "date": "2026-04-24",
  "csrf_token": "a7b8c9..."
}
```

### Response (Backend -> Frontend)
**Format**:
```json
{
  "status": "success",
  "message": "Booking confirmed!",
  "data": { "booking_id": 102 }
}
```

---

## 7. Architecture Overview

1.  **Presentation Layer**: HTML/CSS for structure and styling.
2.  **Interaction Layer**: JavaScript (Fetch API) manages state and UI updates without refreshing.
3.  **Application Layer**: PHP (API endpoints) handles business logic, auth, and validation.
4.  **Data Layer**: MySQL (via PDO) ensures data persistence and integrity.

---

## 8. Mental Model: "The Movie Theater Analogy"

Imagine TurfKick is a **Digital Movie Theater**:
- **The Turf**: This is the **Theater Hall**.
- **The Slots**: These are the **Showtimes**.
- **The Booking**: This is your **Assigned Seat**.
- **The AJAX Check**: Just like a ticket counter, even if you see a seat on the screen, the system double-checks if someone else bought it 1 second ago before printing your ticket.
- **The CSRF Token**: Think of this as a **"Security Wristband"** given to you when you enter. You must show it every time you try to buy a snack or a ticket, or the staff will reject you.

---

## 9. Issues & Improvements

### Current Scalability Concerns
- **Concurrency**: While `FOR UPDATE` handles race conditions, under extremely high load, it can lead to database locks if many people try to book the same turf at once.
- **Image Handling**: Images are stored locally. In a production environment, these should be moved to a Cloud Storage service (like AWS S3).

### Suggestions for Improvement
1.  **Notification System**: Implement an AJAX poller or WebSockets to notify owners instantly when a new booking is made.
2.  **Payment Gateway**: Integrate a real API (like Stripe or Razorpay) instead of just marking payments as 'pending'.
3.  **Search Optimization**: Implement a "Debounce" on the search input to reduce the number of API calls while typing.
4.  **Admin Panel**: Build a dedicated interface for the 'admin' role (defined in the schema) to approve/reject new turf owners.
