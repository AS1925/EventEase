<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('organizer');
require_once __DIR__ . '/../includes/db.php';

$user_id = (int)$_SESSION['user_id'];
$profile_statement = $conn->prepare(
    'SELECT organization_name, verification_status FROM organizer_profiles WHERE user_id = ?'
);
$profile_statement->bind_param('i', $user_id);
$profile_statement->execute();
$profile = $profile_statement->get_result()->fetch_assoc();
if (!$profile) {
    http_response_code(500);
    exit('Organizer profile is unavailable.');
}

$events_statement = $conn->prepare(
    "SELECT events.id, events.event_name, events.event_date, events.category,
            events.publish_status, events.attendance_enabled, events.max_participants,
            COUNT(DISTINCT CASE WHEN registrations.registration_status = 'active' THEN registrations.id END) AS active_registrations,
            COUNT(DISTINCT CASE WHEN registrations.registration_status = 'active' AND event_attendance.id IS NOT NULL THEN registrations.id END) AS checked_in_count,
            COUNT(DISTINCT event_reviews.id) AS review_count,
            ROUND(AVG(CASE WHEN event_reviews.status = 'Published' THEN event_reviews.rating END), 1) AS average_rating
     FROM events
     LEFT JOIN registrations ON registrations.event_id = events.id
     LEFT JOIN event_attendance ON event_attendance.registration_id = registrations.id
     LEFT JOIN event_reviews ON event_reviews.event_id = events.id
     WHERE events.organizer_id = ?
     GROUP BY events.id
     ORDER BY events.event_date DESC, events.id DESC"
);
$events_statement->bind_param('i', $user_id);
$events_statement->execute();
$events_result = $events_statement->get_result();
$event_rows = array();
$event_count = 0;
$active_registrations = 0;
$seats_left = 0;
$tracked_registrations = 0;
$checked_in_registrations = 0;
$review_count = 0;
while ($event = $events_result->fetch_assoc()) {
    $event['active_registrations'] = (int)$event['active_registrations'];
    $event['checked_in_count'] = (int)$event['checked_in_count'];
    $event['review_count'] = (int)$event['review_count'];
    $event['seats_left'] = max(0, (int)$event['max_participants'] - $event['active_registrations']);
    $event['attendance_rate'] = null;
    if ((int)$event['attendance_enabled'] === 1) {
        $event['attendance_rate'] = $event['active_registrations'] === 0
            ? null
            : round(100 * $event['checked_in_count'] / $event['active_registrations'], 1);
        $tracked_registrations += $event['active_registrations'];
        $checked_in_registrations += $event['checked_in_count'];
    }
    $event_rows[] = $event;
    $event_count++;
    $active_registrations += $event['active_registrations'];
    $seats_left += $event['seats_left'];
    $review_count += $event['review_count'];
}
$overall_attendance_rate = $tracked_registrations === 0
    ? null
    : round(100 * $checked_in_registrations / $tracked_registrations, 1);

$pending_questions_statement = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM event_questions
     INNER JOIN events ON events.id = event_questions.event_id
     WHERE events.organizer_id = ? AND event_questions.status = 'pending'"
);
$pending_questions_statement->bind_param('i', $user_id);
$pending_questions_statement->execute();
$pending_questions = (int)$pending_questions_statement->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Dashboard - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3"><?php include '../includes/organizer_sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <p class="text-primary fw-semibold mb-1">Organizer Overview</p>
                            <h1 class="h3 fw-bold mb-1"><?php echo htmlspecialchars($profile['organization_name']); ?></h1>
                            <a href="profile.php" class="small">Edit organizer profile</a>
                            <p class="text-muted mb-0">Verification:
                                <span class="badge <?php echo $profile['verification_status'] === 'verified' ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($profile['verification_status'])); ?>
                                </span>
                            </p>
                        </div>
                        <?php if ($profile['verification_status'] === 'verified'): ?>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="../admin/add_event.php" class="btn btn-primary">Create Event</a>
                                <a href="../vendor_services.php" class="btn btn-outline-primary">Browse Vendors</a>
                                <a href="vendor_requests.php" class="btn btn-outline-secondary">Vendor Requests</a>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">Event creation is available after admin verification.</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl-3"><div class="card stat-card h-100"><h6 class="text-muted">Your Events</h6><h2 class="fw-bold mb-0"><?php echo $event_count; ?></h2></div></div>
                    <div class="col-6 col-xl-3"><div class="card stat-card h-100"><h6 class="text-muted">Active Registrations</h6><h2 class="fw-bold mb-0"><?php echo $active_registrations; ?></h2></div></div>
                    <div class="col-6 col-xl-3"><div class="card stat-card h-100"><h6 class="text-muted">Seats Left</h6><h2 class="fw-bold mb-0"><?php echo $seats_left; ?></h2></div></div>
                    <div class="col-6 col-xl-3"><div class="card stat-card h-100"><h6 class="text-muted">Attendance Rate</h6><h2 class="fw-bold mb-0"><?php echo $overall_attendance_rate === null ? '—' : htmlspecialchars((string)$overall_attendance_rate) . '%'; ?></h2></div></div>
                    <div class="col-6 col-xl-3"><div class="card stat-card h-100"><h6 class="text-muted">Reviews</h6><h2 class="fw-bold mb-0"><?php echo $review_count; ?></h2></div></div>
                    <div class="col-6 col-xl-3"><a href="questions.php" class="card stat-card h-100 text-decoration-none"><h6 class="text-muted">Pending Questions</h6><h2 class="fw-bold mb-0"><?php echo $pending_questions; ?></h2></a></div>
                </div>
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0">Your Event Listings</h2>
                        <div class="d-flex gap-2">
                            <a href="registrations.php" class="btn btn-outline-secondary btn-sm">Registrations</a>
                            <a href="reviews.php" class="btn btn-outline-secondary btn-sm">Reviews</a>
                            <a href="../admin/view_events.php" class="btn btn-outline-primary btn-sm">Manage Events</a>
                        </div>
                    </div>
                    <?php if (count($event_rows) === 0): ?>
                        <p class="text-muted mb-0">You have not created any events yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle modern-table">
                                <thead><tr><th>Event</th><th>Date</th><th>Publish status</th><th>Registrations</th><th>Seats left</th><th>Attendance</th><th>Reviews</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($event_rows as $event): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($event['event_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($event['category'] ?? ''); ?></small></td>
                                            <td><?php echo htmlspecialchars($event['event_date']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($event['publish_status'])); ?></td>
                                            <td><?php echo $event['active_registrations']; ?> / <?php echo (int)$event['max_participants']; ?></td>
                                            <td><?php echo $event['seats_left']; ?></td>
                                            <td><?php echo (int)$event['attendance_enabled'] !== 1 ? 'Not enabled' : ($event['attendance_rate'] === null ? '— (0/0)' : htmlspecialchars((string)$event['attendance_rate']) . '% (' . $event['checked_in_count'] . '/' . $event['active_registrations'] . ')'); ?></td>
                                            <td><?php echo $event['review_count']; ?></td>
                                            <td class="text-nowrap">
                                                <?php if ($event['publish_status'] === 'published'): ?>
                                                    <a class="btn btn-outline-secondary btn-sm" href="../event.php?id=<?php echo (int)$event['id']; ?>">View public page</a>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-share-event data-share-url="../event.php?id=<?php echo (int)$event['id']; ?>" data-share-title="<?php echo htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8'); ?>" data-share-text="View this event on EventEase." data-share-label="Share">Share</button>
                                                <?php endif; ?>
                                                <a class="btn btn-outline-primary btn-sm" href="../admin/edit_event.php?id=<?php echo (int)$event['id']; ?>">Edit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
