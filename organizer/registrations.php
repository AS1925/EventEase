<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('organizer');
require_once __DIR__ . '/../includes/db.php';

$organizer_id = (int)$_SESSION['user_id'];
$events_statement = $conn->prepare(
    'SELECT id, event_name, event_date, max_participants
     FROM events
     WHERE organizer_id = ?
     ORDER BY event_date DESC, id DESC'
);
$events_statement->bind_param('i', $organizer_id);
$events_statement->execute();
$events_result = $events_statement->get_result();
$events = array();
while ($event = $events_result->fetch_assoc()) {
    $events[] = $event;
}

$event_id_input = isset($_GET['event_id']) && is_string($_GET['event_id'])
    ? $_GET['event_id']
    : '';
$event_id = 0;
if ($event_id_input !== '') {
    if (!ctype_digit($event_id_input) || (int)$event_id_input < 1) {
        http_response_code(400);
        exit('Invalid event.');
    }
    $event_id = (int)$event_id_input;
    $owned_event_ids = array_map(static function ($event) {
        return (int)$event['id'];
    }, $events);
    if (!in_array($event_id, $owned_event_ids, true)) {
        http_response_code(404);
        exit('Event not found.');
    }
}

$search = isset($_GET['registration_number']) && is_string($_GET['registration_number'])
    ? trim($_GET['registration_number'])
    : '';
if (strlen($search) > 40) {
    http_response_code(400);
    exit('Registration number search is too long.');
}
$search_pattern = $search === '' ? '%' : '%' . addcslashes($search, '\\%_') . '%';
$registrations_statement = $conn->prepare(
    'SELECT registrations.registration_number, registrations.registration_status,
            registrations.attendance_status, registrations.registration_date,
            users.name AS participant_name, users.email AS participant_email,
            events.id AS event_id, events.event_name, events.event_date
     FROM registrations
     INNER JOIN events ON events.id = registrations.event_id
     INNER JOIN users ON users.id = registrations.user_id
     WHERE events.organizer_id = ?
       AND (? = 0 OR events.id = ?)
       AND registrations.registration_number LIKE ?
     ORDER BY registrations.registration_date DESC, registrations.id DESC'
);
$registrations_statement->bind_param(
    'iiis',
    $organizer_id,
    $event_id,
    $event_id,
    $search_pattern
);
$registrations_statement->execute();
$registrations = $registrations_statement->get_result();

$capacity_statement = $conn->prepare(
    'SELECT COALESCE(SUM(max_participants), 0) AS total_capacity
     FROM events
     WHERE organizer_id = ?
       AND (? = 0 OR id = ?)'
);
$capacity_statement->bind_param('iii', $organizer_id, $event_id, $event_id);
$capacity_statement->execute();
$total_capacity = (int)$capacity_statement->get_result()->fetch_assoc()['total_capacity'];

$summary_statement = $conn->prepare(
    "SELECT COUNT(DISTINCT CASE
                WHEN registrations.registration_status = 'active' THEN registrations.id
            END) AS active_registrations,
            COUNT(DISTINCT CASE
                WHEN registrations.registration_status = 'active'
                 AND (
                    registrations.attendance_status = 'checked_in'
                    OR event_attendance.id IS NOT NULL
                 )
                THEN registrations.id
            END) AS checked_in_count
     FROM events
     LEFT JOIN registrations ON registrations.event_id = events.id
     LEFT JOIN event_attendance
       ON event_attendance.registration_id = registrations.id
      AND event_attendance.event_id = events.id
     WHERE events.organizer_id = ?
       AND (? = 0 OR events.id = ?)"
);
$summary_statement->bind_param('iii', $organizer_id, $event_id, $event_id);
$summary_statement->execute();
$summary = $summary_statement->get_result()->fetch_assoc();
$active_registrations = (int)$summary['active_registrations'];
$available_seats = max(0, $total_capacity - $active_registrations);
$checked_in_count = (int)$summary['checked_in_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registrations - EventEase Organizer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <main class="container py-5">
        <div class="row g-4">
            <aside class="col-lg-3"><?php include '../includes/organizer_sidebar.php'; ?></aside>
            <section class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <h1 class="h3 fw-bold mb-1">Event Registrations</h1>
                            <p class="text-muted mb-0">Registration details are limited to events you own.</p>
                        </div>
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-sm">
                                <label class="form-label" for="event-id">Event</label>
                                <select class="form-select" id="event-id" name="event_id">
                                    <option value="">All your events</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo (int)$event['id']; ?>" <?php echo $event_id === (int)$event['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($event['event_name'] . ' · ' . $event['event_date']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm">
                                <label class="form-label" for="registration-number">Registration number</label>
                                <input class="form-control font-monospace" id="registration-number" name="registration_number" maxlength="40" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-auto"><button class="btn btn-primary" type="submit">Filter</button></div>
                        </form>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><h2 class="h6 text-muted">Active registrations</h2><p class="h3 fw-bold mb-0"><?php echo $active_registrations; ?></p></div></div>
                        <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><h2 class="h6 text-muted">Total capacity</h2><p class="h3 fw-bold mb-0"><?php echo $total_capacity; ?></p></div></div>
                        <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><h2 class="h6 text-muted">Seats remaining</h2><p class="h3 fw-bold mb-0"><?php echo $available_seats; ?></p></div></div>
                        <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><h2 class="h6 text-muted">Checked in</h2><p class="h3 fw-bold mb-0"><?php echo $checked_in_count; ?></p></div></div>
                    </div>
                    <?php if ($registrations->num_rows === 0): ?>
                        <p class="text-muted mb-0">No registrations match your filters.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle modern-table">
                                <thead>
                                    <tr><th>Registration No.</th><th>Participant</th><th>Event</th><th>Date</th><th>Registration</th><th>Attendance</th></tr>
                                </thead>
                                <tbody>
                                    <?php while ($registration = $registrations->fetch_assoc()): ?>
                                        <tr>
                                            <td class="font-monospace"><?php echo htmlspecialchars($registration['registration_number']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($registration['participant_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($registration['participant_email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($registration['event_name']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['event_date']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($registration['registration_status'])); ?></td>
                                            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $registration['attendance_status']))); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
