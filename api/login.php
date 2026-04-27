<?php
// api/login.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config/db.php';
require_once '../includes/helpers.php';

// Prevent direct access if not a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid request method.');
}

$email = strtolower(sanitize_input($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$role = sanitize_input($_POST['role'] ?? 'user');
$csrf_token = $_POST['csrf_token'] ?? '';

// Validate CSRF
if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF token validation failed.');
}

if (empty($email) || empty($password)) {
    send_json_response('error', 'Please enter email and password.');
}

try {
    $hasDeletedFlag = false;
    $columnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_deleted'");
    $hasDeletedFlag = (bool) $columnStmt->fetch();

    if (!in_array($role, ['user', 'owner', 'admin'], true)) {
        send_json_response('error', 'Invalid login type.');
    }

    $sql = "SELECT * FROM users WHERE email = ?";
    $params = [$email];

    if ($role === 'admin') {
        $sql .= " AND role = ?";
        $params[] = 'admin';
    } else {
        $sql .= " AND role IN (?, ?)";
        $params[] = 'user';
        $params[] = 'owner';
    }

    if ($hasDeletedFlag) {
        $sql .= " AND is_deleted = 0";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $user = $stmt->fetch();

    if (!$user && $hasDeletedFlag) {
        $deletedSql = "SELECT id FROM users WHERE email = ?";
        $deletedParams = [$email];

        if ($role === 'admin') {
            $deletedSql .= " AND role = ?";
            $deletedParams[] = 'admin';
        } else {
            $deletedSql .= " AND role IN (?, ?)";
            $deletedParams[] = 'user';
            $deletedParams[] = 'owner';
        }

        $deletedSql .= " AND is_deleted = 1";
        $deletedStmt = $pdo->prepare($deletedSql);
        $deletedStmt->execute($deletedParams);

        if ($deletedStmt->fetch()) {
            send_json_response('error', 'This account has been deleted or disabled. Contact the admin.');
        }
    }

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        $redirect = 'browse_turfs.html';
        if ($user['role'] === 'owner') {
            $redirect = 'owner_dashboard.html';
        } elseif ($user['role'] === 'admin') {
            $redirect = 'admin_dashboard.html';
        }
        
        send_json_response('success', 'Login successful.', ['redirect' => $redirect]);
    }

    if ($role === 'admin') {
        $adminTable = $pdo->query("SHOW TABLES LIKE 'admins'")->fetch();
        if ($adminTable) {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['user_id'] = 0;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['user_name'] = $admin['username'];
                $_SESSION['role'] = 'admin';

                send_json_response('success', 'Login successful.', ['redirect' => 'admin_dashboard.html']);
            }
        }
    }

    send_json_response('error', 'Invalid email or password.');
} catch (Exception $e) {
    send_json_response('error', 'Login error: ' . $e->getMessage());
}
?>
