<?php
require_once __DIR__ . '/includes/session.php';
eventease_start_session();
require_once __DIR__ . '/includes/access_control.php';
require_once __DIR__ . '/includes/db.php';

$event_id_input = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
if (!ctype_digit($event_id_input) || (int)$event_id_input < 1) {
    http_response_code(404);
    exit('Event not found.');
}

$event_id = (int)$event_id_input;
$event_statement = $conn->prepare(
    "SELECT events.*,
            (
                SELECT COUNT(*)
                FROM registrations
                WHERE registrations.event_id = events.id
                  AND registrations.registration_status = 'active'
            ) AS active_registrations,
            (
                events.event_date > CURDATE()
                OR (
                    events.event_date = CURDATE()
                    AND COALESCE(NULLIF(events.start_time, '00:00:00'), events.event_time, '23:59:59') > CURTIME()
                )
            ) AS event_not_started,
            (events.registration_open_at IS NULL OR events.registration_open_at <= NOW()) AS registration_open,
            (events.registration_close_at IS NULL OR events.registration_close_at >= NOW()) AS registration_not_closed
     FROM events
     WHERE events.id = ? AND events.publish_status = 'published'
     LIMIT 1"
);
$event_statement->bind_param('i', $event_id);
$event_statement->execute();
$event = $event_statement->get_result()->fetch_assoc();

if (!$event) {
    http_response_code(404);
    exit('This event is not available.');
}

$current_role = eventease_current_role();
$capacity = max(0, (int)$event['max_participants']);
$active_registrations = (int)$event['active_registrations'];
$available_seats = max(0, $capacity - $active_registrations);
$event_is_full = $available_seats === 0;
$registration_is_open = (int)$event['event_not_started'] === 1
    && (int)$event['registration_open'] === 1
    && (int)$event['registration_not_closed'] === 1;
$already_registered = false;

if ($current_role === 'customer') {
    $user_id = (int)$_SESSION['user_id'];
    $registration_statement = $conn->prepare(
        "SELECT id
         FROM registrations
         WHERE user_id = ? AND event_id = ? AND registration_status = 'active'
         LIMIT 1"
    );
    $registration_statement->bind_param('ii', $user_id, $event_id);
    $registration_statement->execute();
    $already_registered = $registration_statement->get_result()->num_rows > 0;
}

$event_title = (string)$event['event_name'];
$event_description = trim(strip_tags((string)$event['description']));
if ($event_description === '') {
    $event_description = 'View event details and registration information on EventEase.';
} elseif (strlen($event_description) > 200) {
    $event_description = substr($event_description, 0, 197) . '...';
}
$event_image = trim((string)$event['image']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($event_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($event_title, ENT_QUOTES, 'UTF-8'); ?> - EventEase">
    <meta property="og:description" content="<?php echo htmlspecialchars($event_description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($event_image !== ''): ?>
        <meta property="og:image" content="events/<?php echo rawurlencode($event_image); ?>">
    <?php endif; ?>
    <title><?php echo htmlspecialchars($event_title, ENT_QUOTES, 'UTF-8'); ?> - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="events/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                <article class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <?php if ($event_image !== ''): ?>
                        <img src="events/<?php echo rawurlencode($event_image); ?>" class="event-detail-image" alt="<?php echo htmlspecialchars($event_title, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php else: ?>
                        <div class="event-detail-placeholder" aria-hidden="true"><i class="fa-solid fa-calendar-days"></i></div>
                    <?php endif; ?>
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <span class="badge bg-soft-primary text-primary mb-2"><?php echo htmlspecialchars((string)($event['category'] ?: $event['event_type']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <h1 class="h2 fw-bold mb-1"><?php echo htmlspecialchars($event_title, ENT_QUOTES, 'UTF-8'); ?></h1>
                            </div>
                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                data-share-event
                                data-share-title="<?php echo htmlspecialchars($event_title, ENT_QUOTES, 'UTF-8'); ?>"
                                data-share-text="<?php echo htmlspecialchars($event_description, ENT_QUOTES, 'UTF-8'); ?>"
                                data-share-label="Share event"
                            >
                                <i class="fa-solid fa-share-nodes me-2" aria-hidden="true"></i>Share event
                            </button>
                        </div>
                        <?php if (trim((string)$event['description']) !== ''): ?>
                            <p class="text-muted mb-4"><?php echo nl2br(htmlspecialchars((string)$event['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <?php endif; ?>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="event-detail-fact">
                                    <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                                    <div><strong>Date and time</strong><br>
                                        <?php echo htmlspecialchars((string)$event['event_date'], ENT_QUOTES, 'UTF-8'); ?>,
                                        <?php echo htmlspecialchars(substr((string)($event['start_time'] ?: $event['event_time']), 0, 5), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (!empty($event['end_time'])): ?>–<?php echo htmlspecialchars(substr((string)$event['end_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="event-detail-fact">
                                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                    <div><strong>Venue</strong><br>
                                        <?php echo htmlspecialchars((string)$event['venue'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (!empty($event['address'])): ?>, <?php echo htmlspecialchars((string)$event['address'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="event-detail-fact">
                                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                                    <div><strong>Availability</strong><br><?php echo $available_seats; ?> of <?php echo $capacity; ?> seats left</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="event-detail-fact">
                                    <i class="fa-solid fa-ticket" aria-hidden="true"></i>
                                    <div><strong>Registration</strong><br>
                                        <?php if ($event_is_full): ?>
                                            Full — all seats booked
                                        <?php elseif (!$registration_is_open): ?>
                                            Closed
                                        <?php else: ?>
                                            Open
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="event-detail-fact">
                                    <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                                    <div><strong>Price per attendee</strong><br>
                                        <?php echo (float)$event['ticket_price'] > 0 ? '₹' . htmlspecialchars(number_format((float)$event['ticket_price'], 2)) : 'Free'; ?>
                                        <br><small class="text-muted">Offline payment only. Pay the organizer directly; no online payment is collected.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($current_role === 'customer' && $already_registered): ?>
                                <a href="user/my_events.php" class="btn btn-primary">View My Events</a>
                            <?php elseif ($current_role === 'customer' && $event_is_full): ?>
                                <button type="button" class="btn btn-secondary" disabled>Event full — seats booked</button>
                            <?php elseif ($current_role === 'customer' && $registration_is_open): ?>
                                <a href="register_event.php?id=<?php echo $event_id; ?>" class="btn btn-primary">Register Now</a>
                            <?php elseif ($current_role === 'customer'): ?>
                                <button type="button" class="btn btn-secondary" disabled>Registration unavailable</button>
                            <?php elseif ($current_role === 'admin'): ?>
                                <a href="admin/dashboard.php" class="btn btn-primary">Admin Dashboard</a>
                            <?php elseif ($current_role === 'organizer'): ?>
                                <a href="organizer/dashboard.php" class="btn btn-primary">Organizer Dashboard</a>
                            <?php elseif ($current_role === 'vendor'): ?>
                                <a href="vendor/dashboard.php" class="btn btn-primary">Vendor Dashboard</a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-primary">Login to Register</a>
                            <?php endif; ?>
                            <a href="events.php" class="btn btn-outline-secondary">Browse Events</a>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
