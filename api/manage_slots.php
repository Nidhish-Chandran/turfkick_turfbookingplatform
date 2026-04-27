<?php
// api/manage_slots.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in() || !is_owner()) {
    send_json_response('error', 'Unauthorized access.');
}

$owner_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $turf_id = sanitize_input($_GET['turf_id'] ?? 0);
    try {
        $check = $pdo->prepare("SELECT id FROM turfs WHERE id = ? AND owner_id = ?");
        $check->execute([$turf_id, $owner_id]);
        if (!$check->fetch()) send_json_response('error', 'Turf not found.');

        $stmt = $pdo->prepare("SELECT * FROM time_slots WHERE turf_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time ASC");
        $stmt->execute([$turf_id]);
        $slots = $stmt->fetchAll();
        send_json_response('success', 'Slots fetched.', $slots);
    } catch (Exception $e) {
        send_json_response('error', 'Error: ' . $e->getMessage());
    }
} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validate_csrf_token($csrf_token)) {
        send_json_response('error', 'CSRF validation failed.');
    }

    if ($action === 'save_day') {
        $turf_id = sanitize_input($_POST['turf_id'] ?? 0);
        $day = sanitize_input($_POST['day'] ?? '');
        $slots_data = json_decode($_POST['slots'] ?? '[]', true);

        try {
            $pdo->beginTransaction();
            
            // 1. Verify ownership
            $check = $pdo->prepare("SELECT id FROM turfs WHERE id = ? AND owner_id = ?");
            $check->execute([$turf_id, $owner_id]);
            if (!$check->fetch()) throw new Exception('Access denied.');

            // 2. Clear existing slots for this day
            $clear = $pdo->prepare("DELETE FROM time_slots WHERE turf_id = ? AND day_of_week = ?");
            $clear->execute([$turf_id, $day]);

            // 3. Insert new slots with overlap validation
            $insert = $pdo->prepare("INSERT INTO time_slots (turf_id, day_of_week, slot_name, slot_label, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($slots_data as $slot) {
                $start = $slot['start'];
                $end = $slot['end'];
                $name = sanitize_input($slot['name'] ?? '');
                
                if (strtotime($end) <= strtotime($start)) {
                    throw new Exception("Slot '$name': End time must be after start time.");
                }

                // Internal overlap check for the current batch
                foreach ($slots_data as $other) {
                    if ($slot === $other) continue;
                    if ($start < $other['end'] && $end > $other['start']) {
                        throw new Exception("Slot '$name' overlaps with another slot on the same day.");
                    }
                }

                $label = date("h:i A", strtotime($start)) . ' - ' . date("h:i A", strtotime($end));
                $insert->execute([$turf_id, $day, $name, $label, $start, $end]);
            }

            $pdo->commit();
            send_json_response('success', 'Schedule for ' . $day . ' updated.');
        } catch (Exception $e) {
            $pdo->rollBack();
            send_json_response('error', $e->getMessage());
        }
    } elseif ($action === 'copy_to_days') {
        $turf_id = sanitize_input($_POST['turf_id'] ?? 0);
        $source_day = sanitize_input($_POST['source_day'] ?? '');
        $target_days = json_decode($_POST['target_days'] ?? '[]', true);

        try {
            $pdo->beginTransaction();
            
            // Get source slots
            $stmt = $pdo->prepare("SELECT slot_name, start_time, end_time, slot_label FROM time_slots WHERE turf_id = ? AND day_of_week = ?");
            $stmt->execute([$turf_id, $source_day]);
            $source_slots = $stmt->fetchAll();

            foreach ($target_days as $day) {
                // Clear target
                $clear = $pdo->prepare("DELETE FROM time_slots WHERE turf_id = ? AND day_of_week = ?");
                $clear->execute([$turf_id, $day]);

                // Copy source to target
                $insert = $pdo->prepare("INSERT INTO time_slots (turf_id, day_of_week, slot_name, slot_label, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($source_slots as $s) {
                    $insert->execute([$turf_id, $day, $s['slot_name'], $s['slot_label'], $s['start_time'], $s['end_time']]);
                }
            }

            $pdo->commit();
            send_json_response('success', 'Schedule copied to selected days.');
        } catch (Exception $e) {
            $pdo->rollBack();
            send_json_response('error', $e->getMessage());
        }
    } elseif ($action === 'add_bulk_slot') {
        $turf_id = sanitize_input($_POST['turf_id'] ?? 0);
        $start = sanitize_input($_POST['start_time'] ?? '');
        $end = sanitize_input($_POST['end_time'] ?? '');
        $days = json_decode($_POST['days'] ?? '[]', true);

        if (empty($start) || empty($end) || empty($days)) {
            send_json_response('error', 'Times and at least one day are required.');
        }

        if (strtotime($end) <= strtotime($start)) {
            send_json_response('error', 'End time must be after start time.');
        }

        $label = date("h:i A", strtotime($start)) . ' - ' . date("h:i A", strtotime($end));

        try {
            $pdo->beginTransaction();
            
            $check = $pdo->prepare("SELECT id FROM turfs WHERE id = ? AND owner_id = ?");
            $check->execute([$turf_id, $owner_id]);
            if (!$check->fetch()) throw new Exception('Access denied.');

            $insert = $pdo->prepare("INSERT INTO time_slots (turf_id, day_of_week, slot_label, start_time, end_time) VALUES (?, ?, ?, ?, ?)");
            $overlap = $pdo->prepare("SELECT id FROM time_slots WHERE turf_id = ? AND day_of_week = ? AND start_time < ? AND end_time > ?");

            foreach ($days as $day) {
                // Check overlap for this specific day
                $overlap->execute([$turf_id, $day, $end, $start]);
                if ($overlap->fetch()) {
                    throw new Exception("The slot overlaps with an existing schedule on $day.");
                }
                $insert->execute([$turf_id, $day, $label, $start, $end]);
            }

            $pdo->commit();
            send_json_response('success', 'Slot added to selected days.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            send_json_response('error', $e->getMessage());
        }
    } elseif ($action === 'delete') {
        // ... maintain old delete if needed ...
        $slot_id = sanitize_input($_POST['slot_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE ts FROM time_slots ts JOIN turfs t ON ts.turf_id = t.id WHERE ts.id = ? AND t.owner_id = ?");
        $stmt->execute([$slot_id, $owner_id]);
        send_json_response('success', 'Slot deleted.');
    }
}
?>