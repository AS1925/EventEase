<?php 
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include('../includes/access_control.php');
require_role('admin', 'organizer');
include('../includes/db.php');
$role = eventease_current_role();
$user_id = (int)$_SESSION['user_id'];
$is_admin = $role === 'admin';
$msg = "";
$organizer_verified = true;
if (!$is_admin) {
    $verification = $conn->prepare("SELECT verification_status FROM organizer_profiles WHERE user_id = ?");
    $verification->bind_param("i", $user_id);
    $verification->execute();
    $organizer_verified = $verification->get_result()->fetch_assoc();
    $organizer_verified = $organizer_verified && $organizer_verified['verification_status'] === 'verified';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_string = function ($key) {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
    };
    $event_name = $post_string('event_name');
    $category = $post_string('category');
    $event_date = $post_string('event_date');
    $start_time = $post_string('start_time');
    $end_time = $post_string('end_time');
    $end_time_db = $end_time === '' ? null : $end_time;
    $venue = $post_string('venue');
    $address = $post_string('address');
    $description = $post_string('description');
    $max_participants = filter_var($post_string('max_participants'), FILTER_VALIDATE_INT);
    $ticket_price_input = $post_string('ticket_price');
    $ticket_price_valid = preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $ticket_price_input) === 1;
    $ticket_price = $ticket_price_valid ? number_format((float)$ticket_price_input, 2, '.', '') : null;
    $latitude_input = $post_string('latitude');
    $longitude_input = $post_string('longitude');
    $latitude_valid = $latitude_input === '' || is_numeric($latitude_input);
    $longitude_valid = $longitude_input === '' || is_numeric($longitude_input);
    $latitude = $latitude_input === '' || !$latitude_valid ? null : (float)$latitude_input;
    $longitude = $longitude_input === '' || !$longitude_valid ? null : (float)$longitude_input;
    $registration_open_at = eventease_parse_datetime_local(isset($_POST['registration_open_at']) ? $_POST['registration_open_at'] : '');
    $registration_close_at = eventease_parse_datetime_local(isset($_POST['registration_close_at']) ? $_POST['registration_close_at'] : '');
    $publish_status = $post_string('publish_status');
    $attendance_enabled = isset($_POST['attendance_enabled']) && $_POST['attendance_enabled'] === '1' ? 1 : 0;
    $checkin_method = $post_string('checkin_method');
    $venue_verification_enabled = isset($_POST['venue_verification_enabled'])
        && $_POST['venue_verification_enabled'] === '1' ? 1 : 0;
    $checkin_radius_m = filter_var($post_string('checkin_radius_m'), FILTER_VALIDATE_INT);
    $upload = $_FILES['image'] ?? array('error' => UPLOAD_ERR_NO_FILE);
    $has_image_upload = isset($upload['error']) && $upload['error'] !== UPLOAD_ERR_NO_FILE;

    if (!$organizer_verified) {
        http_response_code(403);
        $msg = "Your organizer account must be verified before creating events.";
    } elseif (
        $event_name === '' || $category === '' || $venue === '' || $address === ''
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)
        || !checkdate((int)substr($event_date, 5, 2), (int)substr($event_date, 8, 2), (int)substr($event_date, 0, 4))
        || !$latitude_valid
        || !$longitude_valid
        || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start_time)
        || ($end_time !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end_time))
        || strlen($event_name) > 150
        || strlen($venue) > 200
        || strlen($address) > 255
        || ($end_time !== '' && $start_time >= $end_time)
        || $max_participants === false
        || $max_participants < 1
        || !$ticket_price_valid
        || !in_array($publish_status, array('draft', 'published', 'archived'), true)
        || ($latitude !== null && ($latitude < -90 || $latitude > 90))
        || ($longitude !== null && ($longitude < -180 || $longitude > 180))
        || !in_array($checkin_method, array('gps', 'organizer_manual'), true)
        || $checkin_radius_m === false
        || $checkin_radius_m < 10
        || $checkin_radius_m > 5000
        || ($venue_verification_enabled === 1 && ($latitude === null || $longitude === null))
        || (
            $attendance_enabled === 1
            && (
                ($checkin_method === 'gps' && $venue_verification_enabled !== 1)
                || ($checkin_method === 'organizer_manual' && $venue_verification_enabled !== 0)
            )
        )
    ) {
        $msg = "Enter valid event details, times, coordinates, and capacity.";
    } elseif (
        $registration_open_at === false
        || $registration_close_at === false
        || ($registration_open_at !== null && $registration_close_at !== null && $registration_open_at > $registration_close_at)
    ) {
        $msg = "Enter a valid registration window.";
    } elseif ($has_image_upload && $upload['error'] !== UPLOAD_ERR_OK) {
        $msg = "The event image could not be uploaded. Please choose another file.";
    } else {
        $extension = $has_image_upload ? strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) : '';
        if ($has_image_upload && !eventease_is_valid_image_upload($upload)) {
            $msg = "Upload a valid JPG, JPEG, PNG or WEBP image under 5 MB.";
        } else {
            if ($is_admin) {
                $check = $conn->prepare(
                    'SELECT id FROM events WHERE event_name = ? AND event_date = ? LIMIT 1'
                );
                $check->bind_param('ss', $event_name, $event_date);
            } else {
                $check = $conn->prepare(
                    'SELECT id FROM events
                     WHERE event_name = ? AND event_date = ? AND organizer_id = ?
                     LIMIT 1'
                );
                $check->bind_param('ssi', $event_name, $event_date, $user_id);
            }
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $msg = "Event already exists.";
            } else {
                $image = $has_image_upload ? bin2hex(random_bytes(8)) . "." . $extension : null;
                if ($has_image_upload && !move_uploaded_file($upload['tmp_name'], "../events/" . $image)) {
                    $msg = "The image could not be uploaded. Please try again.";
                } else {
                    $organizer_id = $is_admin ? null : $user_id;
                    try {
                        $insert = $conn->prepare(
                            "INSERT INTO events (event_name, event_type, event_date, event_time, venue, description, max_participants, image, organizer_id, category, start_time, end_time, address, latitude, longitude, registration_open_at, registration_close_at, publish_status, attendance_enabled, checkin_method, venue_verification_enabled, checkin_radius_m, ticket_price)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        );
                        $insert_types = str_repeat('s', 6) . 'is' . 'i'
                            . str_repeat('s', 4) . 'dd' . str_repeat('s', 3) . 'isii' . 's';
                        $insert->bind_param(
                            $insert_types,
                            $event_name,
                            $category,
                            $event_date,
                            $start_time,
                            $venue,
                            $description,
                            $max_participants,
                            $image,
                            $organizer_id,
                            $category,
                            $start_time,
                            $end_time_db,
                            $address,
                            $latitude,
                            $longitude,
                            $registration_open_at,
                            $registration_close_at,
                            $publish_status,
                            $attendance_enabled,
                            $checkin_method,
                            $venue_verification_enabled,
                            $checkin_radius_m,
                            $ticket_price
                        );
                        $insert->execute();
                        header('Location: view_events.php?created=1');
                        exit();
                    } catch (Throwable $exception) {
                        error_log(
                            'Event creation failed (' . get_class($exception)
                            . ', code ' . $exception->getCode() . ').'
                        );
                        if ($image !== null && is_file("../events/" . $image)) {
                            unlink("../events/" . $image);
                        }
                        $msg = "The event could not be saved. Please review the details and try again.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Add Event</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="icon" href="../events/logo.png" type="image/png">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-lg-3">
                    <?php include ($is_admin ? '../includes/admin_sidebar.php' : '../includes/organizer_sidebar.php'); ?>
                </div>
                <div class="col-lg-9">
                    <div class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
                        <h3 class="fw-bold mb-3">Add New Event</h3>
                        <p class="text-muted mb-4">Create a polished event listing for attendees.</p>
                        <?php if (!$organizer_verified): ?><div class="alert alert-warning">Your organizer account is awaiting admin verification. You cannot create events yet.</div><?php endif; ?>
                        <form method="POST" enctype="multipart/form-data" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                            <div class="col-md-6"><label class="form-label">Event Name</label><input type="text" name="event_name" class="form-control" required></div>
                            <div class="col-md-6"><label class="form-label" for="category">Category</label><select id="category" name="category" class="form-select" required><option value="">Select</option><option>Wedding</option><option>Birthday</option><option>Corporate</option><option>Conference</option><option>Reception</option><option>others</option></select></div>
                            <div class="col-md-6"><label class="form-label">Event Date</label><input type="date" name="event_date" class="form-control" required></div>
                            <div class="col-md-6"><label class="form-label">Start Time</label><input type="time" name="start_time" class="form-control" required></div>
                            <div class="col-md-6"><label class="form-label">End Time (optional)</label><input type="time" name="end_time" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">Venue</label><input type="text" name="venue" class="form-control" required></div>
                            <div class="col-12"><label class="form-label">Address</label><input type="text" name="address" class="form-control" required></div>
                            <div class="col-md-6"><label class="form-label">Latitude (optional)</label><input type="number" name="latitude" min="-90" max="90" step="any" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">Longitude (optional)</label><input type="number" name="longitude" min="-180" max="180" step="any" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">Maximum Participants</label><input type="number" name="max_participants" min="1" class="form-control" required></div>
                            <div class="col-md-6"><label class="form-label" for="ticket-price">Price per attendee (INR)</label><input id="ticket-price" type="number" name="ticket_price" min="0" max="99999999.99" step="0.01" value="0.00" class="form-control" required><div class="form-text">Set 0.00 for a free event. Payment is offline only; attendees pay the organizer directly.</div></div>
                            <div class="col-md-6"><label class="form-label">Registration Opens (optional)</label><input type="datetime-local" name="registration_open_at" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">Registration Closes (optional)</label><input type="datetime-local" name="registration_close_at" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label" for="publish-status">Publish Status</label><select id="publish-status" name="publish_status" class="form-select" required><option value="published" selected>Published - visible on the public events page</option><option value="draft">Draft - hidden from public listings</option><option value="archived">Archived - hidden from public listings</option></select></div>
                            <div class="col-12">
                                <label class="form-label" for="attendance-enabled">Enable Attendance Tracking</label>
                                <select id="attendance-enabled" name="attendance_enabled" class="form-select">
                                    <option value="0" selected>No</option>
                                    <option value="1">Yes</option>
                                </select>
                                <div class="form-text">Attendance is off unless you enable it for this public event.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="checkin-method">Check-in method</label>
                                <select id="checkin-method" name="checkin_method" class="form-select">
                                    <option value="organizer_manual" selected>Organizer confirms in person</option>
                                    <option value="gps">Participant GPS venue check</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="venue-verification-enabled">Require venue verification</label>
                                <select id="venue-verification-enabled" name="venue_verification_enabled" class="form-select">
                                    <option value="0" selected>No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="checkin-radius">Allowed GPS radius (metres)</label>
                                <input id="checkin-radius" type="number" name="checkin_radius_m" min="10" max="5000" step="1" value="100" class="form-control">
                                <div class="form-text">GPS check-in requires venue verification and coordinates. Radius must be 10–5,000 m.</div>
                            </div>
                            <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4"></textarea></div>
                            <div class="col-12"><label class="form-label" for="event-image">Event Image (optional)</label><input id="event-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control"><div class="form-text">Optional JPG, PNG, or WebP image up to 5 MB. Events without an image use a placeholder.</div></div>
                            <?php if($msg != ""): ?><div class="col-12"><div class="alert <?php echo strpos($msg, 'successfully') !== false ? 'alert-success' : 'alert-danger'; ?> rounded-4 mb-0" role="alert"><?php echo htmlspecialchars($msg); ?></div></div><?php endif; ?>
                            <div class="col-12"><button type="submit" name="save" class="btn btn-primary" <?php echo !$organizer_verified ? 'disabled' : ''; ?>>Add Event</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../includes/footer.php'; ?>
    </body>    
</html>
