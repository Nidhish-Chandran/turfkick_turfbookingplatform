<?php
// api/get_user.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in()) {
    send_json_response('error', 'Not logged in');
}

try {
    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        send_json_response('error', 'User not found.');
    }

    send_json_response('success', 'User session active', [
        'user_id' => $user['id'],
        'user_name' => $user['name'],
        'user_email' => $user['email'],
        'role' => $user['role']
    ]);
} catch (Exception $e) {
    send_json_response('error', 'Error fetching user: ' . $e->getMessage());
}
?>
