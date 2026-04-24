<?php
// api/get_token.php
require_once '../includes/helpers.php';

send_json_response('success', 'Token generated', ['csrf_token' => get_csrf_token()]);
?>
