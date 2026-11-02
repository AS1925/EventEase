<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}
eventease_require_valid_csrf_post();

$event_id_input = isset($_POST['event_id']) && is_string($_POST['event_id'])
    ? $_POST['event_id']
    : '';
$organizer_id_input = isset($_POST['organizer_id']) && is_string($_POST['organizer_id'])
    ? $_POST['organizer_id']
    : '';
if (
    !ctype_digit($event_id_input)
    || (int)$event_id_input < 1
    || !ctype_digit($organizer_id_input)
    || (int)$organizer_id_input < 1
) {
    http_response_code(400);
    exit('Choose a valid event and organizer.');
}

$event_id = (int)$event_id_input;
$organizer_id = (int)$organizer_id_input;
$verified_organizer = $conn->prepare(
    "SELECT users.id
     FROM users
     INNER JOIN organizer_profiles ON organizer_profiles.user_id = users.id
     WHERE users.id = ? AND users.role = 'organizer'
       AND organizer_profiles.verification_status = 'verified'
     LIMIT 1"
);
$verified_organizer->bind_param('i', $organizer_id);
$verified_organizer->execute();
if (!$verified_organizer->get_result()->fetch_assoc()) {
    http_response_code(400);
    exit('Only verified organizers can be assigned events.');
}

$assign = $conn->prepare(
    'UPDATE events SET organizer_id = ? WHERE id = ? AND organizer_id IS NULL'
);
$assign->bind_param('ii', $organizer_id, $event_id);
$assign->execute();
if ($assign->affected_rows !== 1) {
    header('Location: view_events.php?owner_assignment=unchanged');
    exit();
}

header('Location: view_events.php?owner_assignment=assigned');
exit();
