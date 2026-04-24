<?php
// api/get_user.php
require_once '../includes/helpers.php';

if (!is_logged_in()) {
    send_json_response('error', 'Not logged in');
}

send_json_response('success', 'User session active', [
    'user_id' => $_SESSION['user_id'],
    'user_name' => $_SESSION['user_name'],
    'role' => $_SESSION['role']
]);
?>
