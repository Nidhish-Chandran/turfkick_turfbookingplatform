<?php
// api/owner/reply_review.php
require_once '../../config/db.php';
require_once '../../includes/helpers.php';

if (!is_logged_in() || !is_owner()) {
    send_json_response('error', 'Unauthorized access.');
}

$owner_id = $_SESSION['user_id'];
$comment_id = sanitize_input($_POST['review_id'] ?? 0); // Using 'review_id' from frontend but it maps to comment_id
$reply = sanitize_input($_POST['reply'] ?? '');
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF validation failed.');
}

try {
    // 1. Verify that the comment belongs to a turf owned by this owner
    $stmt = $pdo->prepare("
        SELECT c.id FROM comments c
        JOIN turfs t ON c.turf_id = t.id
        WHERE c.id = ? AND t.owner_id = ?
    ");
    $stmt->execute([$comment_id, $owner_id]);
    if (!$stmt->fetch()) throw new Exception('Access denied.');

    // 2. Insert or update reply
    $check = $pdo->prepare("SELECT id FROM replies WHERE comment_id = ?");
    $check->execute([$comment_id]);
    if ($check->fetch()) {
        $stmt = $pdo->prepare("UPDATE replies SET reply = ?, created_at = CURRENT_TIMESTAMP WHERE comment_id = ?");
        $stmt->execute([$reply, $comment_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO replies (comment_id, owner_id, reply) VALUES (?, ?, ?)");
        $stmt->execute([$comment_id, $owner_id, $reply]);
    }

    send_json_response('success', 'Reply sent successfully.');
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
