<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('user');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}
eventease_require_valid_csrf_post();

$registration_id_input = isset($_POST['id']) && is_string($_POST['id']) ? $_POST['id'] : '';
if (!ctype_digit($registration_id_input) || (int)$registration_id_input < 1) {
    http_response_code(400);
    exit('Invalid registration.');
}

$registration_id = (int)$registration_id_input;
$user_id = (int)$_SESSION['user_id'];
$cancel_result = 'not_allowed';
$transaction_started = false;

try {
    $conn->begin_transaction();
    $transaction_started = true;

    $lookup = $conn->prepare(
        "SELECT event_id
         FROM registrations
         WHERE id = ? AND user_id = ? AND registration_status = 'active'"
    );
    $lookup->bind_param('ii', $registration_id, $user_id);
    $lookup->execute();
    $registration = $lookup->get_result()->fetch_assoc();

    if ($registration) {
        $event_id = (int)$registration['event_id'];
        $event_lock = $conn->prepare('SELECT id FROM events WHERE id = ? FOR UPDATE');
        $event_lock->bind_param('i', $event_id);
        $event_lock->execute();
        $event_exists = $event_lock->get_result()->num_rows === 1;

        if ($event_exists) {
            $eligible = $conn->prepare(
                "SELECT registrations.id
                 FROM registrations
                 INNER JOIN events ON events.id = registrations.event_id
                 WHERE registrations.id = ?
                   AND registrations.user_id = ?
                   AND registrations.registration_status = 'active'
                   AND registrations.attendance_status <> 'checked_in'
                   AND (
                       events.event_date > CURDATE()
                       OR (
                           events.event_date = CURDATE()
                           AND COALESCE(events.start_time, events.event_time, '23:59:59') > CURTIME()
                       )
                   )
                   AND NOT EXISTS (
                       SELECT 1
                       FROM event_attendance
                       WHERE event_attendance.registration_id = registrations.id
                   )
                 FOR UPDATE"
            );
            $eligible->bind_param('ii', $registration_id, $user_id);
            $eligible->execute();

            if ($eligible->get_result()->num_rows === 1) {
                $cancel = $conn->prepare(
                    "UPDATE registrations
                     SET registration_status = 'cancelled'
                     WHERE id = ?
                       AND user_id = ?
                       AND registration_status = 'active'
                       AND attendance_status <> 'checked_in'"
                );
                $cancel->bind_param('ii', $registration_id, $user_id);
                $cancel->execute();

                if ($cancel->affected_rows === 1) {
                    $conn->commit();
                    $transaction_started = false;
                    $cancel_result = '1';
                }
            }
        }
    }

    if ($transaction_started) {
        $conn->rollback();
        $transaction_started = false;
    }
} catch (mysqli_sql_exception $exception) {
    if ($transaction_started) {
        $conn->rollback();
    }
    error_log('Registration cancellation failed with MySQL error code ' . $exception->getCode() . '.');
    $cancel_result = 'error';
}

header('Location: my_events.php?cancelled=' . rawurlencode($cancel_result));
exit();
