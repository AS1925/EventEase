<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('user');
require_once __DIR__ . '/../includes/db.php';

$user_id = (int)$_SESSION['user_id'];
$registration_number = '';
$event = null;
$message = '';
$message_type = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $registration_number = isset($_POST['registration_number']) && is_string($_POST['registration_number'])
        ? strtoupper(trim($_POST['registration_number']))
        : '';
} elseif (isset($_GET['registration_number']) && is_string($_GET['registration_number'])) {
    $registration_number = strtoupper(trim($_GET['registration_number']));
}

if ($registration_number === '' || strlen($registration_number) > 40) {
    $message = 'Enter a valid registration number.';
} else {
    $registration_lookup = $conn->prepare(
        'SELECT registrations.id AS registration_id, registrations.registration_number,
                registrations.registration_status, registrations.attendance_status,
                events.id AS event_id, events.event_name, events.event_date,
                events.start_time, events.event_time, events.end_time, events.venue,
                events.attendance_enabled, events.checkin_method,
                events.venue_verification_enabled, events.checkin_radius_m,
                events.latitude, events.longitude, events.publish_status
         FROM registrations
         INNER JOIN events ON events.id = registrations.event_id
         WHERE registrations.registration_number = ? AND registrations.user_id = ?
         LIMIT 1'
    );
    $registration_lookup->bind_param('si', $registration_number, $user_id);
    $registration_lookup->execute();
    $event = $registration_lookup->get_result()->fetch_assoc();

    if (!$event) {
        $message = 'An event registration matching that number could not be found.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $latitude_input = isset($_POST['latitude']) && is_string($_POST['latitude'])
            ? trim($_POST['latitude'])
            : '';
        $longitude_input = isset($_POST['longitude']) && is_string($_POST['longitude'])
            ? trim($_POST['longitude'])
            : '';
        $accuracy_input = isset($_POST['accuracy']) && is_string($_POST['accuracy'])
            ? trim($_POST['accuracy'])
            : '';
        $consent_given = isset($_POST['location_consent']) && $_POST['location_consent'] === '1';

        if ($event['registration_status'] !== 'active') {
            $message = 'This registration was cancelled and cannot be checked in.';
        } elseif ($event['attendance_status'] === 'checked_in') {
            $message = 'This registration has already been checked in.';
        } elseif ((int)$event['attendance_enabled'] !== 1) {
            $message = 'Attendance tracking is disabled for this event.';
        } elseif ($event['publish_status'] !== 'published') {
            $message = 'Check-in is unavailable for this event.';
        } elseif ($event['checkin_method'] !== 'gps') {
            $message = 'This event uses organizer-confirmed check-in. Please contact the organizer.';
        } elseif ((int)$event['venue_verification_enabled'] !== 1) {
            $message = 'Venue verification is not configured for GPS check-in. Please contact the organizer.';
        } elseif ($event['latitude'] === null || $event['longitude'] === null) {
            $message = 'The event venue has no valid location configured. Please contact the organizer.';
        } elseif (!$consent_given) {
            $message = 'Location consent is required for this venue-verified check-in.';
        } elseif (
            !is_numeric($latitude_input)
            || !is_numeric($longitude_input)
            || !is_numeric($accuracy_input)
        ) {
            $message = 'Location is unavailable. Allow location access and try again.';
        } else {
            $latitude = (float)$latitude_input;
            $longitude = (float)$longitude_input;
            $accuracy_m = (float)$accuracy_input;

            if (
                !is_finite($latitude)
                || !is_finite($longitude)
                || !is_finite($accuracy_m)
                || $latitude < -90
                || $latitude > 90
                || $longitude < -180
                || $longitude > 180
                || $accuracy_m < 0
                || $accuracy_m > 50
            ) {
                $message = 'Your location is invalid or too inaccurate. Improve GPS accuracy and try again.';
            } else {
                $lat1 = deg2rad($latitude);
                $lat2 = deg2rad((float)$event['latitude']);
                $lat_delta = deg2rad((float)$event['latitude'] - $latitude);
                $lon_delta = deg2rad((float)$event['longitude'] - $longitude);
                $distance_component = sin($lat_delta / 2) ** 2
                    + cos($lat1) * cos($lat2) * sin($lon_delta / 2) ** 2;
                $distance_component = min(1, max(0, $distance_component));
                $distance_m = 6371000 * 2 * atan2(
                    sqrt($distance_component),
                    sqrt(1 - $distance_component)
                );
                $checkin_radius_m = (int)$event['checkin_radius_m'];

                if ($checkin_radius_m < 10 || $checkin_radius_m > 5000) {
                    $message = 'The event has an invalid check-in radius. Please contact the organizer.';
                } elseif ($distance_m + $accuracy_m > $checkin_radius_m) {
                    $message = 'You are outside the venue radius. Move closer and try again.';
                } else {
                    $transaction_started = false;
                    try {
                        $conn->begin_transaction();
                        $transaction_started = true;

                        $event_lock = $conn->prepare(
                            'SELECT id, attendance_enabled, checkin_method,
                                    venue_verification_enabled, checkin_radius_m,
                                    latitude, longitude, publish_status,
                                    event_date, start_time, event_time, end_time
                             FROM events
                             WHERE id = ?
                             FOR UPDATE'
                        );
                        $event_id = (int)$event['event_id'];
                        $event_lock->bind_param('i', $event_id);
                        $event_lock->execute();
                        $locked_event = $event_lock->get_result()->fetch_assoc();

                        $registration_lock = $conn->prepare(
                            'SELECT id, registration_status, attendance_status
                             FROM registrations
                             WHERE id = ? AND event_id = ? AND user_id = ?
                               AND registration_number = ?
                             FOR UPDATE'
                        );
                        $registration_id = (int)$event['registration_id'];
                        $registration_number_for_lock = $registration_number;
                        $registration_lock->bind_param(
                            'iiis',
                            $registration_id,
                            $event_id,
                            $user_id,
                            $registration_number_for_lock
                        );
                        $registration_lock->execute();
                        $locked_registration = $registration_lock->get_result()->fetch_assoc();
                        $window = eventease_checkin_window_status($locked_event);

                        if (!$locked_registration || $locked_registration['registration_status'] !== 'active') {
                            $message = 'This registration is no longer active.';
                        } elseif ($locked_registration['attendance_status'] === 'checked_in') {
                            $message = 'This registration has already been checked in.';
                        } elseif (
                            !$locked_event
                            || (int)$locked_event['attendance_enabled'] !== 1
                            || $locked_event['publish_status'] !== 'published'
                            || $locked_event['checkin_method'] !== 'gps'
                            || (int)$locked_event['venue_verification_enabled'] !== 1
                            || $locked_event['latitude'] === null
                            || $locked_event['longitude'] === null
                        ) {
                            $message = 'The event is not configured for GPS venue check-in.';
                        } elseif ($window['status'] === 'not_open') {
                            $message = 'Check-in opens two hours before the event starts.';
                        } elseif ($window['status'] === 'closed') {
                            $message = 'The check-in window for this event has closed.';
                        } elseif (
                            $distance_m + $accuracy_m > (int)$locked_event['checkin_radius_m']
                            || (float)$locked_event['latitude'] !== (float)$event['latitude']
                            || (float)$locked_event['longitude'] !== (float)$event['longitude']
                        ) {
                            $message = 'The event venue settings changed. Refresh the page and try again.';
                        } else {
                            $insert = $conn->prepare(
                                "INSERT INTO event_attendance
                                    (registration_id, event_id, checkin_method,
                                     verification_result, location_distance_m,
                                     location_accuracy_m, consented_at)
                                 VALUES (?, ?, 'gps', 'venue_verified', ?, ?, NOW())"
                            );
                            $insert->bind_param(
                                'iidd',
                                $registration_id,
                                $event_id,
                                $distance_m,
                                $accuracy_m
                            );
                            $insert->execute();

                            $mark_checked_in = $conn->prepare(
                                "UPDATE registrations
                                 SET attendance_status = 'checked_in'
                                 WHERE id = ? AND user_id = ?
                                   AND registration_status = 'active'
                                   AND attendance_status <> 'checked_in'"
                            );
                            $mark_checked_in->bind_param('ii', $registration_id, $user_id);
                            $mark_checked_in->execute();
                            if ($mark_checked_in->affected_rows !== 1) {
                                throw new RuntimeException('Registration status changed during check-in.');
                            }

                            $conn->commit();
                            $transaction_started = false;
                            $message = 'Check-in complete. GPS is an approximate signal and does not prove identity or physical presence.';
                            $message_type = 'success';
                            $event['attendance_status'] = 'checked_in';
                        }

                        if ($transaction_started) {
                            $conn->rollback();
                            $transaction_started = false;
                        }
                    } catch (mysqli_sql_exception $exception) {
                        if ($transaction_started) {
                            $conn->rollback();
                        }
                        if ((int)$exception->getCode() === 1062) {
                            $message = 'This registration has already been checked in.';
                        } else {
                            error_log('Participant attendance check-in failed with MySQL error code ' . $exception->getCode() . '.');
                            $message = 'Check-in could not be completed. Please try again.';
                        }
                    } catch (RuntimeException $exception) {
                        if ($transaction_started) {
                            $conn->rollback();
                        }
                        error_log('Participant attendance check-in failed: ' . $exception->getMessage());
                        $message = 'Check-in could not be completed. Please try again.';
                    }
                }
            }
        }
    }
}

$window = $event ? eventease_checkin_window_status($event) : null;
$is_gps_checkin = $event
    && (int)$event['attendance_enabled'] === 1
    && $event['checkin_method'] === 'gps'
    && (int)$event['venue_verification_enabled'] === 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Check-in - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <section class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
                    <h1 class="h3 fw-bold mb-2">Event check-in</h1>
                    <p class="text-muted">Check-in is available only for your own active registration during the event window.</p>
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="<?php echo $message_type === 'danger' ? 'alert' : 'status'; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($event): ?>
                        <div class="alert alert-light border">
                            <strong><?php echo htmlspecialchars($event['event_name']); ?></strong><br>
                            <?php echo htmlspecialchars($event['event_date']); ?> at
                            <?php echo htmlspecialchars(substr($event['start_time'] ?: $event['event_time'], 0, 5)); ?>
                            <?php if (!empty($event['end_time'])): ?>–<?php echo htmlspecialchars(substr($event['end_time'], 0, 5)); ?><?php endif; ?>
                            · <?php echo htmlspecialchars($event['venue']); ?><br>
                            <span class="small text-muted">Registration: <?php echo htmlspecialchars($event['registration_number']); ?></span>
                        </div>
                        <?php if ((int)$event['attendance_enabled'] !== 1): ?>
                            <div class="alert alert-secondary" role="status">Attendance tracking is disabled for this event.</div>
                        <?php elseif ($event['registration_status'] !== 'active'): ?>
                            <div class="alert alert-warning" role="status">Cancelled registrations are not eligible for check-in.</div>
                        <?php elseif ($event['attendance_status'] === 'checked_in'): ?>
                            <div class="alert alert-success" role="status">This registration is already checked in.</div>
                        <?php elseif ($window['status'] === 'not_open'): ?>
                            <div class="alert alert-info" role="status">Check-in opens two hours before the event starts.</div>
                        <?php elseif ($window['status'] === 'closed'): ?>
                            <div class="alert alert-secondary" role="status">The check-in window for this event has closed.</div>
                        <?php elseif ($event['checkin_method'] === 'organizer_manual'): ?>
                            <div class="alert alert-info" role="status">Please show your registration number to the event organizer for in-person check-in.</div>
                        <?php elseif ($event['publish_status'] !== 'published'): ?>
                            <div class="alert alert-secondary" role="status">Check-in is unavailable for this event.</div>
                        <?php elseif ($event['latitude'] === null || $event['longitude'] === null): ?>
                            <div class="alert alert-danger" role="alert">Venue verification is not configured correctly. Contact the organizer.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!$event || $is_gps_checkin): ?>
                        <form method="POST" id="check-in-form" class="d-grid gap-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                            <input type="hidden" name="accuracy" id="accuracy">
                            <div>
                                <label class="form-label" for="registration-number">Registration number</label>
                                <input type="text" id="registration-number" name="registration_number"
                                       class="form-control font-monospace" maxlength="40" autocomplete="off"
                                       value="<?php echo htmlspecialchars($registration_number); ?>" required>
                            </div>
                            <?php if ($is_gps_checkin): ?>
                                <div class="form-check">
                                    <input type="checkbox" id="location-consent" name="location_consent"
                                           value="1" class="form-check-input" required>
                                    <label class="form-check-label" for="location-consent">
                                        I consent to a one-time location check for venue verification.
                                    </label>
                                </div>
                                <p class="small text-muted mb-0">
                                    The server checks your location within <?php echo (int)$event['checkin_radius_m']; ?> metres
                                    and accepts GPS accuracy up to 50 metres. Exact coordinates are not stored.
                                    GPS can be spoofed and does not prove identity or physical presence.
                                </p>
                                <button type="button" id="check-in-button" class="btn btn-primary">Share location and check in</button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-primary">Find my registration</button>
                            <?php endif; ?>
                            <a href="my_events.php" class="btn btn-outline-secondary">Back to My Events</a>
                            <div id="location-error" class="small text-danger" role="alert" aria-live="polite"></div>
                        </form>
                    <?php else: ?>
                        <a href="my_events.php" class="btn btn-outline-secondary">Back to My Events</a>
                    <?php endif; ?>
                    <?php if ($is_gps_checkin): ?>
                        <noscript><p class="text-danger mt-3 mb-0">JavaScript is required to request location for venue verification.</p></noscript>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
    <?php if ($is_gps_checkin): ?>
        <script>
            document.getElementById('check-in-button').addEventListener('click', function () {
                const consent = document.getElementById('location-consent');
                const error = document.getElementById('location-error');
                error.textContent = '';

                if (!document.getElementById('registration-number').reportValidity()) {
                    return;
                }
                if (!consent.checked) {
                    consent.reportValidity();
                    return;
                }
                if (!navigator.geolocation) {
                    error.textContent = 'This browser does not support location access.';
                    return;
                }

                const button = this;
                button.disabled = true;
                button.textContent = 'Checking location...';
                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        document.getElementById('latitude').value = position.coords.latitude;
                        document.getElementById('longitude').value = position.coords.longitude;
                        document.getElementById('accuracy').value = position.coords.accuracy;
                        document.getElementById('check-in-form').requestSubmit();
                    },
                    function (positionError) {
                        if (positionError.code === positionError.PERMISSION_DENIED) {
                            error.textContent = 'Location permission was denied. Allow location access and try again.';
                        } else if (positionError.code === positionError.POSITION_UNAVAILABLE) {
                            error.textContent = 'Your location is unavailable. Move to an area with GPS reception and try again.';
                        } else if (positionError.code === positionError.TIMEOUT) {
                            error.textContent = 'Location request timed out. Try again when your device has a clear signal.';
                        } else {
                            error.textContent = 'Location could not be read. Try again.';
                        }
                        button.disabled = false;
                        button.textContent = 'Share location and check in';
                    },
                    {enableHighAccuracy: true, maximumAge: 0, timeout: 15000}
                );
            });
        </script>
    <?php endif; ?>
</body>
</html>
