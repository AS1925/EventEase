<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('user');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_GET['id']) || !is_string($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(404);
    exit('Registration not found.');
}

$registration_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];
$statement = $conn->prepare(
    'SELECT registrations.registration_number, registrations.registration_status,
            registrations.attendance_status, registrations.registration_date,
            events.event_name, events.event_date, events.start_time, events.event_time,
            events.end_time, events.venue, events.ticket_price
     FROM registrations
     INNER JOIN events ON events.id = registrations.event_id
     WHERE registrations.id = ? AND registrations.user_id = ?'
);
$statement->bind_param('ii', $registration_id, $user_id);
$statement->execute();
$registration = $statement->get_result()->fetch_assoc();
if (!$registration) {
    http_response_code(404);
    exit('Registration not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmed - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <section class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
                    <div class="text-success fs-1 mb-3"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                    <h1 class="h3 fw-bold">Registration confirmed</h1>
                    <p class="text-muted">Keep your registration number for event-related assistance and check-in.</p>
                    <div class="alert alert-primary" role="status">
                        <strong>Registration number:</strong>
                        <span class="font-monospace"><?php echo htmlspecialchars($registration['registration_number']); ?></span>
                        <button type="button" class="btn btn-outline-primary btn-sm ms-2" data-copy-registration-number="<?php echo htmlspecialchars($registration['registration_number'], ENT_QUOTES, 'UTF-8'); ?>">Copy</button>
                    </div>
                    <h2 class="h5"><?php echo htmlspecialchars($registration['event_name']); ?></h2>
                    <p class="mb-1"><strong>Price per attendee:</strong> <?php echo (float)$registration['ticket_price'] > 0 ? '₹' . htmlspecialchars(number_format((float)$registration['ticket_price'], 2)) : 'Free'; ?></p>
                    <p class="text-muted">Payment is offline only. Pay the organizer directly; no online payment is collected.</p>
                    <p class="text-muted mb-4">
                        <?php echo htmlspecialchars($registration['event_date']); ?> at
                        <?php echo htmlspecialchars(substr($registration['start_time'] ?: $registration['event_time'], 0, 5)); ?>
                        <?php if (!empty($registration['end_time'])): ?>–<?php echo htmlspecialchars(substr($registration['end_time'], 0, 5)); ?><?php endif; ?>
                        · <?php echo htmlspecialchars($registration['venue']); ?>
                    </p>
                    <p class="small text-muted">
                        Registered <?php echo htmlspecialchars($registration['registration_date']); ?>
                        · Status: <?php echo $registration['registration_status'] === 'active' ? 'Registered' : 'Cancelled'; ?>
                        · Attendance: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $registration['attendance_status']))); ?>
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="my_events.php" class="btn btn-primary">View My Events</a>
                        <a href="../events.php" class="btn btn-outline-secondary">Browse Events</a>
                    </div>
                </section>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
