<?php
// includes/helpers.php

session_start();

/**
 * Send a JSON response and exit.
 */
function send_json_response($status, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

/**
 * Generate CSRF token if it doesn't exist.
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token.
 */
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if the user is logged in.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if the user is an owner.
 */
function is_owner() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'owner';
}

/**
 * Check if the user is an admin.
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Block access if not an admin.
 */
function require_admin() {
    if (!is_admin()) {
        send_json_response('error', 'Access denied. Administrator privileges required.');
    }
}

/**
 * Sanitize input data.
 */
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}
?>
