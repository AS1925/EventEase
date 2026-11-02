<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("admin", "organizer");
include("../includes/db.php");
$role = eventease_current_role();
$user_id = (int)$_SESSION['user_id'];
$is_admin = $role === 'admin';

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: view_events.php");
    exit();
}

$id = (int)$_GET['id'];
if ($is_admin) {
    $statement = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $statement->bind_param("i", $id);
} else {
    $statement = $conn->prepare("SELECT * FROM events WHERE id = ? AND organizer_id = ?");
    $statement->bind_param("ii", $id, $user_id);
}
$statement->execute();
$row = $statement->get_result()->fetch_assoc();
if (!$row) {
    header("Location: view_events.php");
    exit();
}
$category_options = array("Wedding", "Birthday", "Corporate", "Conference", "Reception", "others");
$current_category = (string)($row['category'] ?: $row['event_type'] ?: '');
if ($current_category !== '' && !in_array($current_category, $category_options, true)) {
    $category_options[] = $current_category;
}

$message = "";
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
    $image = $row['image'];
    $new_image_path = null;

    if (
        $event_name === '' || $category === '' || $venue === '' || $address === ''
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)
        || !checkdate((int)substr($event_date, 5, 2), (int)substr($event_date, 8, 2), (int)substr($event_date, 0, 4))
        || !$latitude_valid
        || !$longitude_valid
        || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start_time)
        || ($end_time !== '' && (
            !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end_time)
            || $start_time >= $end_time
        ))
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
        $message = "Enter valid event details, times, coordinates, and capacity.";
    } elseif (
        $registration_open_at === false
        || $registration_close_at === false
        || ($registration_open_at !== null && $registration_close_at !== null && $registration_open_at > $registration_close_at)
    ) {
        $message = "Enter a valid registration window.";
    } elseif (!$is_admin && $publish_status === 'published') {
        $verification = $conn->prepare("SELECT verification_status FROM organizer_profiles WHERE user_id = ?");
        $verification->bind_param("i", $user_id);
        $verification->execute();
        $organizer_profile = $verification->get_result()->fetch_assoc();
        if (!$organizer_profile || $organizer_profile['verification_status'] !== 'verified') {
            $message = "Your organizer account must be verified before publishing events.";
        }
    }

    if ($message === "" && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!eventease_is_valid_image_upload($_FILES['image'])) {
            $message = "Upload a valid JPG, JPEG, PNG or WEBP image under 5 MB.";
        } else {
            $image = bin2hex(random_bytes(8)) . "." . $extension;
            $new_image_path = "../events/" . $image;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $new_image_path)) {
                $message = "The image could not be uploaded. Please try again.";
            }
        }
    }

    if ($message === "") {
        $where_clause = $is_admin ? " WHERE id = ?" : " WHERE id = ? AND organizer_id = ?";
        $update_types = str_repeat("s", 6) . "is" . str_repeat("s", 4) . "dd" . str_repeat("s", 3) . "isiisi";
        try {
            $update = $conn->prepare(
                "UPDATE events SET event_name = ?, event_type = ?, event_date = ?, event_time = ?, venue = ?, description = ?, max_participants = ?, image = ?, category = ?, start_time = ?, end_time = ?, address = ?, latitude = ?, longitude = ?, registration_open_at = ?, registration_close_at = ?, publish_status = ?, attendance_enabled = ?, checkin_method = ?, venue_verification_enabled = ?, checkin_radius_m = ?, ticket_price = ?" . $where_clause
            );
            if ($is_admin) {
                $update->bind_param(
                    $update_types,
                    $event_name,
                    $category,
                    $event_date,
                    $start_time,
                    $venue,
                    $description,
                    $max_participants,
                    $image,
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
                    $ticket_price,
                    $id
                );
            } else {
                $update->bind_param(
                    $update_types . "i",
                    $event_name,
                    $category,
                    $event_date,
                    $start_time,
                    $venue,
                    $description,
                    $max_participants,
                    $image,
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
                    $ticket_price,
                    $id,
                    $user_id
                );
            }
            if (!$update->execute()) {
                throw new RuntimeException("Event update query did not execute.");
            }
            if ($new_image_path !== null && $row['image'] !== '' && is_file("../events/" . $row['image'])) {
                unlink("../events/" . $row['image']);
            }
            header("Location: view_events.php?updated=1");
            exit();
        } catch (Throwable $exception) {
            error_log(
                'Event update failed for event ' . $id . ' (' . get_class($exception)
                . ', code ' . $exception->getCode() . '): ' . $exception->getMessage()
            );
            if ($new_image_path !== null && is_file($new_image_path)) {
                unlink($new_image_path);
            }
            $message = "The event could not be updated. Please review the details and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - EventEase</title>
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
                    <h1 class="h3 fw-bold mb-1">Edit Event</h1>
                    <p class="text-muted mb-4">Update the event details shown to attendees.</p>
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                        <div class="col-md-6"><label class="form-label" for="event-name">Event Name</label><input id="event-name" type="text" name="event_name" class="form-control" value="<?php echo htmlspecialchars($row['event_name']); ?>" required></div>
                        <div class="col-md-6"><label class="form-label" for="category">Category</label><select id="category" name="category" class="form-select" required><?php foreach ($category_options as $type): ?><option value="<?php echo htmlspecialchars($type); ?>" <?php echo $current_category === $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label" for="event-date">Event Date</label><input id="event-date" type="date" name="event_date" class="form-control" value="<?php echo htmlspecialchars($row['event_date']); ?>" required></div>
                        <div class="col-md-6"><label class="form-label" for="start-time">Start Time</label><input id="start-time" type="time" name="start_time" class="form-control" value="<?php echo htmlspecialchars(substr($row['start_time'] ?: $row['event_time'], 0, 5)); ?>" required></div>
                        <div class="col-md-6"><label class="form-label" for="end-time">End Time (optional for legacy events)</label><input id="end-time" type="time" name="end_time" class="form-control" value="<?php echo htmlspecialchars(substr((string)($row['end_time'] ?? ''), 0, 5)); ?>"></div>
                        <div class="col-md-6"><label class="form-label" for="venue">Venue</label><input id="venue" type="text" name="venue" class="form-control" value="<?php echo htmlspecialchars($row['venue']); ?>" required></div>
                        <div class="col-md-6"><label class="form-label" for="address">Address</label><input id="address" type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($row['address'] ?? ''); ?>" required></div>
                        <div class="col-md-6"><label class="form-label" for="latitude">Latitude (optional)</label><input id="latitude" type="number" name="latitude" min="-90" max="90" step="any" class="form-control" value="<?php echo htmlspecialchars((string)($row['latitude'] ?? '')); ?>"></div>
                        <div class="col-md-6"><label class="form-label" for="longitude">Longitude (optional)</label><input id="longitude" type="number" name="longitude" min="-180" max="180" step="any" class="form-control" value="<?php echo htmlspecialchars((string)($row['longitude'] ?? '')); ?>"></div>
                        <div class="col-md-6"><label class="form-label" for="max-participants">Maximum Participants</label><input id="max-participants" type="number" name="max_participants" min="1" class="form-control" value="<?php echo (int)$row['max_participants']; ?>" required></div>
                        <div class="col-md-6"><label class="form-label" for="ticket-price">Price per attendee (INR)</label><input id="ticket-price" type="number" name="ticket_price" min="0" max="99999999.99" step="0.01" value="<?php echo htmlspecialchars(number_format((float)($row['ticket_price'] ?? 0), 2, '.', '')); ?>" class="form-control" required><div class="form-text">Set 0.00 for a free event. Payment is offline only; attendees pay the organizer directly.</div></div>
                        <div class="col-md-6"><label class="form-label" for="registration-open">Registration Opens (optional)</label><input id="registration-open" type="datetime-local" name="registration_open_at" class="form-control" value="<?php echo $row['registration_open_at'] ? htmlspecialchars(str_replace(' ', 'T', substr($row['registration_open_at'], 0, 16))) : ''; ?>"></div>
                        <div class="col-md-6"><label class="form-label" for="registration-close">Registration Closes (optional)</label><input id="registration-close" type="datetime-local" name="registration_close_at" class="form-control" value="<?php echo $row['registration_close_at'] ? htmlspecialchars(str_replace(' ', 'T', substr($row['registration_close_at'], 0, 16))) : ''; ?>"></div>
                        <div class="col-md-6"><label class="form-label" for="publish-status">Publish Status</label><select id="publish-status" name="publish_status" class="form-select" required><?php foreach (array('draft', 'published', 'archived') as $status): ?><option value="<?php echo $status; ?>" <?php echo $row['publish_status'] === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($status)); ?></option><?php endforeach; ?></select></div>
                        <div class="col-12">
                            <label class="form-label" for="attendance-enabled">Enable Attendance Tracking</label>
                            <select id="attendance-enabled" name="attendance_enabled" class="form-select">
                                <option value="0" <?php echo empty($row['attendance_enabled']) ? 'selected' : ''; ?>>No</option>
                                <option value="1" <?php echo !empty($row['attendance_enabled']) ? 'selected' : ''; ?>>Yes</option>
                            </select>
                            <div class="form-text">Attendance is disabled for legacy events unless explicitly enabled here.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="checkin-method">Check-in method</label>
                            <select id="checkin-method" name="checkin_method" class="form-select">
                                <option value="organizer_manual" <?php echo $row['checkin_method'] === 'organizer_manual' ? 'selected' : ''; ?>>Organizer confirms in person</option>
                                <option value="gps" <?php echo $row['checkin_method'] === 'gps' ? 'selected' : ''; ?>>Participant GPS venue check</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="venue-verification-enabled">Require venue verification</label>
                            <select id="venue-verification-enabled" name="venue_verification_enabled" class="form-select">
                                <option value="0" <?php echo empty($row['venue_verification_enabled']) ? 'selected' : ''; ?>>No</option>
                                <option value="1" <?php echo !empty($row['venue_verification_enabled']) ? 'selected' : ''; ?>>Yes</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="checkin-radius">Allowed GPS radius (metres)</label>
                            <input id="checkin-radius" type="number" name="checkin_radius_m" min="10" max="5000" step="1" value="<?php echo (int)$row['checkin_radius_m']; ?>" class="form-control">
                            <div class="form-text">GPS requires venue verification and valid venue coordinates. Radius must be 10–5,000 m.</div>
                        </div>
                        <div class="col-12"><label class="form-label" for="description">Description</label><textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($row['description']); ?></textarea></div>
                        <div class="col-12">
                            <label class="form-label" for="image">Replace Image (optional)</label>
                            <?php if ($row['image'] !== ""): ?><div class="mb-2"><img src="../events/<?php echo rawurlencode($row['image']); ?>" alt="Current event image" class="img-thumbnail" style="max-width: 180px; max-height: 120px; object-fit: cover;"></div><?php endif; ?>
                            <input id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="form-control">
                        </div>
                        <?php if ($message !== ""): ?><div class="col-12"><div class="alert alert-danger mb-0" role="alert"><?php echo htmlspecialchars($message); ?></div></div><?php endif; ?>
                        <div class="col-12 d-flex flex-wrap gap-2"><button type="submit" name="update" class="btn btn-primary">Save Changes</button><a href="view_events.php" class="btn btn-outline-secondary">Cancel</a></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>