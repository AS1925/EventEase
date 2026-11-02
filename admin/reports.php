<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("admin");
include("../includes/db.php");

$users = (int)$conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$registrations = (int)$conn->query("SELECT COUNT(*) AS total FROM registrations WHERE registration_status = 'active'")->fetch_assoc()['total'];
$requests = (int)$conn->query("SELECT COUNT(*) AS total FROM service_requests")->fetch_assoc()['total'];
$events_result = $conn->query(
    "SELECT events.id, events.event_name, events.event_type, events.event_date,
            events.event_time, events.venue, events.max_participants,
            CASE WHEN events.event_date < CURDATE() THEN 'Completed'
                 WHEN events.event_date = CURDATE() THEN 'Ongoing'
                 ELSE 'Upcoming' END AS event_status,
            COALESCE(registration_totals.registered_count, 0) AS registered_count,
            COALESCE(registration_totals.attended_count, 0) AS attended_count,
            COALESCE(review_totals.review_count, 0) AS review_count,
            review_totals.average_rating
     FROM events
     LEFT JOIN (
         SELECT registrations.event_id,
                COUNT(*) AS registered_count,
                COUNT(event_attendance.registration_id) AS attended_count
         FROM registrations
         LEFT JOIN event_attendance ON event_attendance.registration_id = registrations.id
         WHERE registrations.registration_status = 'active'
         GROUP BY registrations.event_id
     ) AS registration_totals ON registration_totals.event_id = events.id
     LEFT JOIN (
         SELECT event_id, COUNT(*) AS review_count, ROUND(AVG(rating), 1) AS average_rating
         FROM event_reviews
         WHERE status = 'Published'
         GROUP BY event_id
     ) AS review_totals ON review_totals.event_id = events.id
     ORDER BY events.event_date DESC, events.event_time DESC"
);
$event_rows = array();
$lifecycle_totals = array('Upcoming' => 0, 'Ongoing' => 0, 'Completed' => 0);
while ($event = $events_result->fetch_assoc()) {
    $event_rows[] = $event;
    $lifecycle_totals[$event['event_status']]++;
}
$attendees = $conn->query(
    "SELECT registrations.registration_number, registrations.attendance_status,
            events.event_name, events.event_date, users.name, users.email
     FROM registrations
     INNER JOIN events ON events.id = registrations.event_id
     INNER JOIN users ON users.id = registrations.user_id
     WHERE registrations.registration_status = 'active'
     ORDER BY events.event_date DESC, events.event_time DESC, users.name"
);
$published_reviews = $conn->query("SELECT events.event_name, event_reviews.rating, event_reviews.review_text, event_reviews.created_at, users.name FROM event_reviews INNER JOIN events ON events.id = event_reviews.event_id INNER JOIN users ON users.id = event_reviews.user_id WHERE event_reviews.status = 'Published' ORDER BY event_reviews.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event and Participation Reports - EventEase Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3 report-sidebar"><?php include '../includes/admin_sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4 report-actions">
                    <div>
                        <p class="text-primary fw-semibold mb-1">Admin Reports</p>
                        <h1 class="h3 fw-bold mb-1">Events, Attendance & Reviews</h1>
                        <p class="text-muted mb-0">Events scheduled today are ongoing; earlier dates are completed.</p>
                    </div>
                    <button onclick="window.print()" class="btn btn-primary"><i class="fa-solid fa-print me-2"></i>Print Full Report</button>
                </div>
                <div id="printReport">
                    <p class="small text-muted d-none print-only">EventEase Admin Report · Generated <?php echo date('Y-m-d H:i'); ?></p>
                    <div class="row g-3 mb-4 report-summary">
                        <div class="col-6 col-xl-3"><div class="card stat-card h-100"><h2 class="h6 text-muted">Upcoming</h2><p class="h3 fw-bold mb-0"><?php echo $lifecycle_totals['Upcoming']; ?></p></div></div>
                        <div class="col-6 col-xl-3"><div class="card stat-card h-100"><h2 class="h6 text-muted">Ongoing Today</h2><p class="h3 fw-bold mb-0"><?php echo $lifecycle_totals['Ongoing']; ?></p></div></div>
                        <div class="col-6 col-xl-3"><div class="card stat-card h-100"><h2 class="h6 text-muted">Completed</h2><p class="h3 fw-bold mb-0"><?php echo $lifecycle_totals['Completed']; ?></p></div></div>
                        <div class="col-6 col-xl-3"><div class="card stat-card h-100"><h2 class="h6 text-muted">Registered</h2><p class="h3 fw-bold mb-0"><?php echo $registrations; ?></p></div></div>
                    </div>
                    <section class="card border-0 shadow-sm rounded-4 p-4 mb-4 report-section">
                        <h2 class="h5 fw-bold mb-1">Event Performance</h2>
                        <p class="text-muted small">Counts active registrations, saved check-ins, and published reviews from the database.</p>
                        <div class="table-responsive">
                            <table class="table modern-table align-middle">
                                <thead><tr><th>Event</th><th>Schedule</th><th>Status</th><th>Registered</th><th>Checked in</th><th>Published reviews</th><th>Avg. Rating</th></tr></thead>
                                <tbody>
                                    <?php if (count($event_rows) === 0): ?><tr><td colspan="7" class="text-center text-muted py-4">No events to report.</td></tr><?php endif; ?>
                                    <?php foreach ($event_rows as $event): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($event['event_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($event['event_type']); ?> · <?php echo htmlspecialchars($event['venue']); ?></small></td>
                                            <td><?php echo htmlspecialchars($event['event_date']); ?><br><small class="text-muted"><?php echo htmlspecialchars($event['event_time']); ?></small></td>
                                            <td><?php echo htmlspecialchars($event['event_status']); ?></td>
                                            <td><?php echo (int)$event['registered_count']; ?> / <?php echo (int)$event['max_participants']; ?></td>
                                            <td><?php echo (int)$event['attended_count']; ?></td>
                                            <td><?php echo (int)$event['review_count']; ?></td>
                                            <td><?php echo $event['average_rating'] === null ? 'No reviews' : htmlspecialchars($event['average_rating']) . ' / 5'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="card border-0 shadow-sm rounded-4 p-4 mb-4 report-section">
                        <h2 class="h5 fw-bold mb-3">Active Registered Participants</h2>
                        <div class="table-responsive">
                            <table class="table modern-table align-middle">
                                <thead><tr><th>Participant</th><th>Email</th><th>Registration No.</th><th>Event</th><th>Date</th><th>Attendance</th></tr></thead>
                                <tbody>
                                    <?php if ($attendees->num_rows === 0): ?><tr><td colspan="6" class="text-center text-muted py-4">No active registrations yet.</td></tr><?php endif; ?>
                                    <?php while ($attendee = $attendees->fetch_assoc()): ?>
                                        <tr><td><?php echo htmlspecialchars($attendee['name']); ?></td><td><?php echo htmlspecialchars($attendee['email']); ?></td><td class="font-monospace"><?php echo htmlspecialchars($attendee['registration_number']); ?></td><td><?php echo htmlspecialchars($attendee['event_name']); ?></td><td><?php echo htmlspecialchars($attendee['event_date']); ?></td><td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $attendee['attendance_status']))); ?></td></tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="card border-0 shadow-sm rounded-4 p-4 report-section">
                        <h2 class="h5 fw-bold mb-3">Published Reviews</h2>
                        <?php if ($published_reviews->num_rows === 0): ?>
                            <p class="text-muted mb-0">No published reviews yet. Pending reviews are available in Event Reviews.</p>
                        <?php else: ?>
                            <div class="vstack gap-3">
                                <?php while ($review = $published_reviews->fetch_assoc()): ?>
                                    <article class="border-bottom pb-3">
                                        <div class="d-flex flex-wrap justify-content-between gap-2"><strong><?php echo htmlspecialchars($review['event_name']); ?></strong><span class="text-warning"><?php echo str_repeat('★', (int)$review['rating']); ?> <span class="text-muted"><?php echo (int)$review['rating']; ?>/5</span></span></div>
                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                        <small class="text-muted">By <?php echo htmlspecialchars($review['name']); ?> · <?php echo htmlspecialchars($review['created_at']); ?></small>
                                    </article>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                    <p class="small text-muted mt-3">Total users: <?php echo $users; ?> · Total service requests: <?php echo $requests; ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>