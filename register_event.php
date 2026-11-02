<?php
require_once("includes/access_control.php");
eventease_start_session();
include("includes/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$role = eventease_current_role();
if ($role !== 'customer') {
    if ($role === 'admin') {
        header("Location: admin/dashboard.php");
    } elseif ($role === 'organizer') {
        header("Location: organizer/dashboard.php");
    } elseif ($role === 'vendor') {
        header("Location: vendor/dashboard.php");
    } else {
        header("Location: login.php");
    }
    exit();
}

if (!isset($_GET['id']) || !is_string($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    exit('Invalid event.');
}

$user_id = (int)$_SESSION['user_id'];
$event_id_input = $_GET['id'];
if ((int)$event_id_input < 1) {
    http_response_code(400);
    exit('Invalid event.');
}
$event_id = (int)$event_id_input;
$event_statement = $conn->prepare(
    "SELECT events.*,
            (
                (
                    event_date > CURDATE()
                    OR (
                        event_date = CURDATE()
                        AND COALESCE(start_time, event_time, '23:59:59') > CURTIME()
                    )
                )
                AND (registration_open_at IS NULL OR registration_open_at <= NOW())
                AND (registration_close_at IS NULL OR registration_close_at >= NOW())
            ) AS registration_available,
            (
                SELECT COUNT(*)
                FROM registrations
                WHERE registrations.event_id = events.id
                  AND registrations.registration_status = 'active'
            ) AS active_registrations
     FROM events
     WHERE id = ? AND publish_status = 'published'"
);
$event_statement->bind_param("i", $event_id);
$event_statement->execute();
$event = $event_statement->get_result()->fetch_assoc();
if (!$event) {
    http_response_code(404);
    exit('This event is not available.');
}
$event_is_available = (int)$event['registration_available'] === 1;
$active_registrations = (int)$event['active_registrations'];
$available_seats = max(0, (int)$event['max_participants'] - $active_registrations);
$event_is_full = $available_seats === 0;

$message = "";
$message_type = 'warning';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    if ($event_is_full) {
        $message = 'This event has reached its maximum capacity. No more registrations are available.';
    } elseif (!$event_is_available) {
        $message = 'Registration for this event is not currently available.';
    } else {
        $transaction_started = false;
        try {
            $conn->begin_transaction();
            $transaction_started = true;

            $locked_event = $conn->prepare(
                "SELECT max_participants, publish_status,
                        (
                            event_date > CURDATE()
                            OR (
                                event_date = CURDATE()
                                AND COALESCE(start_time, event_time, '23:59:59') > CURTIME()
                            )
                        ) AS event_not_started,
                        (registration_open_at IS NULL OR registration_open_at <= NOW()) AS registration_open,
                        (registration_close_at IS NULL OR registration_close_at >= NOW()) AS registration_not_closed
                 FROM events
                 WHERE id = ?
                 FOR UPDATE"
            );
            $locked_event->bind_param("i", $event_id);
            $locked_event->execute();
            $capacity = $locked_event->get_result()->fetch_assoc();

            $existing = $conn->prepare(
                "SELECT id FROM registrations
                 WHERE user_id = ? AND event_id = ? AND registration_status = 'active'
                 LIMIT 1"
            );
            $existing->bind_param("ii", $user_id, $event_id);
            $existing->execute();
            $already_registered = $existing->get_result()->num_rows > 0;

            $count = $conn->prepare(
                "SELECT COUNT(*) AS total
                 FROM registrations
                 WHERE event_id = ? AND registration_status = 'active'"
            );
            $count->bind_param("i", $event_id);
            $count->execute();
            $registered = (int)$count->get_result()->fetch_assoc()['total'];

            if (
                !$capacity
                || $capacity['publish_status'] !== 'published'
                || (int)$capacity['event_not_started'] !== 1
                || (int)$capacity['registration_open'] !== 1
                || (int)$capacity['registration_not_closed'] !== 1
            ) {
                $message = "This event is no longer available for registration.";
            } elseif ($already_registered) {
                $message = "You are already registered for this event.";
            } elseif ($registered >= (int)$capacity['max_participants']) {
                $message = "This event is full and registration is closed.";
            } else {
                $insert = $conn->prepare(
                    "INSERT INTO registrations (user_id, event_id, registration_number, registration_status, attendance_status)
                     VALUES (?, ?, ?, 'active', 'not_checked_in')"
                );
                $inserted = false;
                for ($attempt = 0; $attempt < 3; $attempt++) {
                    $registration_number = eventease_generate_registration_number($conn);
                    $insert->bind_param("iis", $user_id, $event_id, $registration_number);
                    try {
                        if ($insert->execute()) {
                            $registration_id = (int)$conn->insert_id;
                            $inserted = true;
                            break;
                        }
                    } catch (mysqli_sql_exception $exception) {
                        if ((int)$exception->getCode() !== 1062) {
                            throw $exception;
                        }
                    }
                }
                if ($inserted) {
                    $conn->commit();
                    $transaction_started = false;
                    header("Location: user/registration_confirmation.php?id=" . $registration_id);
                    exit();
                }
                $message = "A unique registration number could not be generated. Please try again.";
            }

            if ($transaction_started) {
                $conn->rollback();
                $transaction_started = false;
            }
        } catch (mysqli_sql_exception $exception) {
            if ($transaction_started) {
                $conn->rollback();
            }
            error_log('Event registration failed with MySQL error code ' . $exception->getCode() . '.');
            $message = 'Registration could not be completed. Please try again.';
        } catch (RuntimeException $exception) {
            if ($transaction_started) {
                $conn->rollback();
            }
            error_log('Event registration failed: ' . $exception->getMessage());
            $message = 'Registration could not be completed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Registration - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="events/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <section class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <?php if ($event['image'] !== ""): ?>
                        <img src="events/<?php echo rawurlencode($event['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($event['event_name']); ?>" style="max-height: 320px; object-fit: cover;">
                    <?php endif; ?>
                    <div class="card-body p-4 p-lg-5">
                        <span class="badge bg-soft-primary text-primary mb-3"><?php echo htmlspecialchars($event['category'] ?: $event['event_type']); ?></span>
                        <h1 class="h2 fw-bold mb-3">Confirm your registration</h1>
                        <h2 class="h4 mb-3"><?php echo htmlspecialchars($event['event_name']); ?></h2>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                        <ul class="list-unstyled text-muted d-grid gap-2 mb-4">
                            <li><i class="fa-solid fa-calendar me-2"></i><?php echo htmlspecialchars($event['event_date']); ?>, <?php echo htmlspecialchars(substr($event['start_time'] ?: $event['event_time'], 0, 5)); ?><?php if (!empty($event['end_time'])): ?>–<?php echo htmlspecialchars(substr($event['end_time'], 0, 5)); ?><?php endif; ?></li>
                            <li><i class="fa-solid fa-location-dot me-2"></i><?php echo htmlspecialchars($event['venue']); ?><?php if (!empty($event['address'])): ?>, <?php echo htmlspecialchars($event['address']); ?><?php endif; ?></li>
                            <li><i class="fa-solid fa-users me-2"></i><?php echo $available_seats; ?> of <?php echo (int)$event['max_participants']; ?> seats left</li>
                            <li><i class="fa-solid fa-indian-rupee-sign me-2"></i><?php echo (float)$event['ticket_price'] > 0 ? '₹' . htmlspecialchars(number_format((float)$event['ticket_price'], 2)) : 'Free'; ?> per attendee · Offline payment only; pay the organizer directly.</li>
                        </ul>
                        <?php if ($event_is_full): ?>
                            <div class="alert alert-danger" role="status">This event is full. All available seats have been booked.</div>
                        <?php endif; ?>
                        <?php if ($message !== ""): ?><div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="<?php echo $message_type === 'danger' ? 'alert' : 'status'; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                        <?php if ($event_is_available && !$event_is_full): ?>
                            <form method="POST" class="d-flex flex-wrap gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-ticket me-2"></i>Confirm Registration</button>
                                <a href="events.php" class="btn btn-outline-secondary">Back to Events</a>
                            </form>
                        <?php else: ?>
                            <a href="events.php" class="btn btn-outline-secondary">Back to Events</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php include 'includes/footer.php'; ?>
</body>
</html>