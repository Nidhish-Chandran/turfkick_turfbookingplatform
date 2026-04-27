<?php
// api/get_reviews.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

$turf_id = sanitize_input($_GET['turf_id'] ?? 0);
$role_request = $_GET['role'] ?? '';

try {
    if ($role_request === 'owner' && is_logged_in() && is_owner()) {
        $owner_id = $_SESSION['user_id'];
        // Fetch all comments for all turfs owned by this owner
        $stmt = $pdo->prepare("
            SELECT c.*, u.name as user_name, t.name as turf_name,
                   r.reply as owner_reply, r.created_at as reply_date
            FROM comments c
            JOIN users u ON c.user_id = u.id
            JOIN turfs t ON c.turf_id = t.id
            LEFT JOIN replies r ON c.id = r.comment_id
            WHERE t.owner_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$owner_id]);
    } else {
        if (!$turf_id) {
            send_json_response('error', 'Turf ID required.');
        }
        // Fetch comments for specific turf
        $stmt = $pdo->prepare("
            SELECT c.*, u.name as user_name, 
                   r.reply as owner_reply, r.created_at as reply_date
            FROM comments c
            JOIN users u ON c.user_id = u.id
            LEFT JOIN replies r ON c.id = r.comment_id
            WHERE c.turf_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$turf_id]);
    }
    $reviews = $stmt->fetchAll();
    send_json_response('success', 'Reviews fetched.', $reviews);
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
