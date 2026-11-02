<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('organizer', 'admin');
require_once __DIR__ . '/../includes/db.php';

$role = eventease_current_role();
$user_id = (int)$_SESSION['user_id'];
$is_admin = $role === 'admin';
$event_scope = $is_admin ? '' : 'WHERE organizer_id = ?';
$events_statement = $conn->prepare(
    'SELECT id, event_name, event_date, attendance_enabled
     FROM events ' . $event_scope . '
     ORDER BY event_date DESC, id DESC'
);
if (!$is_admin) {
    $events_statement->bind_param('i', $user_id);
}
$events_statement->execute();
$events_result = $events_statement->get_result();
$events = array();
$event_ids = array();
while ($event = $events_result->fetch_assoc()) {
    $event['id'] = (int)$event['id'];
    $event['attendance_enabled'] = (int)$event['attendance_enabled'];
    $events[] = $event;
    $event_ids[] = $event['id'];
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
    if (!in_array($event_id, $event_ids, true)) {
        http_response_code(404);
        exit('Event not found.');
    }
}

$registration_number = isset($_GET['registration_number']) && is_string($_GET['registration_number'])
    ? trim($_GET['registration_number'])
    : '';
if (strlen($registration_number) > 40) {
    http_response_code(400);
    exit('Registration number search is too long.');
}

$attendance_filter = isset($_GET['attendance_status']) && is_string($_GET['attendance_status'])
    ? $_GET['attendance_status']
    : 'all';
$allowed_filters = array('all', 'checked_in', 'not_checked_in', 'no_show', 'cancelled');
if (!in_array($attendance_filter, $allowed_filters, true)) {
    http_response_code(400);
    exit('Invalid attendance status filter.');
}

$scope_condition = $is_admin ? '1 = 1' : 'events.organizer_id = ?';
$summary_sql =
    'SELECT COUNT(DISTINCT CASE WHEN events.attendance_enabled = 1 AND registrations.registration_status = \'active\' THEN registrations.id END) AS eligible_count,
            COUNT(DISTINCT CASE WHEN events.attendance_enabled = 1 AND registrations.registration_status = \'active\'
                AND (registrations.attendance_status = \'checked_in\' OR event_attendance.id IS NOT NULL)
                THEN registrations.id END) AS checked_in_count,
            COUNT(DISTINCT CASE WHEN registrations.registration_status = \'cancelled\' THEN registrations.id END) AS cancelled_count
     FROM events
     LEFT JOIN registrations ON registrations.event_id = events.id
     LEFT JOIN event_attendance ON event_attendance.registration_id = registrations.id
     WHERE ' . $scope_condition . ' AND (? = 0 OR events.id = ?)';
$summary_statement = $conn->prepare($summary_sql);
if ($is_admin) {
    $summary_statement->bind_param('ii', $event_id, $event_id);
} else {
    $summary_statement->bind_param('iii', $user_id, $event_id, $event_id);
}
$summary_statement->execute();
$summary = $summary_statement->get_result()->fetch_assoc();
$eligible_count = (int)$summary['eligible_count'];
$checked_in_count = (int)$summary['checked_in_count'];
$cancelled_count = (int)$summary['cancelled_count'];
$attendance_rate = $eligible_count > 0
    ? round(100 * $checked_in_count / $eligible_count, 1)
    : null;

$rows_sql =
    'SELECT registrations.registration_number, registrations.registration_status,
            users.name AS participant_name, events.event_name, events.event_date,
            events.attendance_enabled, event_attendance.checked_in_at,
            registrations.attendance_status
     FROM events
     INNER JOIN registrations ON registrations.event_id = events.id
     INNER JOIN users ON users.id = registrations.user_id
     LEFT JOIN event_attendance ON event_attendance.registration_id = registrations.id
     WHERE ' . $scope_condition . '
       AND (? = 0 OR events.id = ?)
       AND registrations.registration_number LIKE ?
       AND (
            ? = \'all\'
            OR (? = \'checked_in\' AND registrations.registration_status = \'active\'
                AND (registrations.attendance_status = \'checked_in\' OR event_attendance.id IS NOT NULL))
            OR (? = \'not_checked_in\' AND registrations.registration_status = \'active\'
                AND registrations.attendance_status = \'not_checked_in\' AND event_attendance.id IS NULL)
            OR (? = \'no_show\' AND registrations.registration_status = \'active\' AND registrations.attendance_status = \'no_show\')
            OR (? = \'cancelled\' AND registrations.registration_status = \'cancelled\')
       )
     ORDER BY events.event_date DESC, events.event_name, registrations.registration_date DESC';
$rows_statement = $conn->prepare($rows_sql);
$search_pattern = $registration_number === ''
    ? '%'
    : '%' . addcslashes($registration_number, '\\%_') . '%';
if ($is_admin) {
    $rows_statement->bind_param(
        'iissssss',
        $event_id,
        $event_id,
        $search_pattern,
        $attendance_filter,
        $attendance_filter,
        $attendance_filter,
        $attendance_filter,
        $attendance_filter
    );
} else {
    $rows_statement->bind_param(
        'iiissssss',
        $user_id,
        $event_id,
        $event_id,
        $search_pattern,
        $attendance_filter,
        $attendance_filter,
        $attendance_filter,
        $attendance_filter,
        $attendance_filter
    );
}
$rows_statement->execute();
$attendance_rows = $rows_statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <main class="container py-5">
        <div class="row g-4">
            <aside class="col-lg-3">
                <?php if ($is_admin): ?>
                    <?php include '../includes/admin_sidebar.php'; ?>
                <?php else: ?>
                    <?php include '../includes/organizer_sidebar.php'; ?>
                <?php endif; ?>
            </aside>
            <section class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <p class="text-primary fw-semibold mb-1"><?php echo $is_admin ? 'Admin Report' : 'Organizer Report'; ?></p>
                            <h1 class="h3 fw-bold mb-1">Attendance</h1>
                            <p class="text-muted mb-0">Participant records are limited to events you are authorized to manage.</p>
                        </div>
                        <?php if (count($events) === 0): ?>
                            <span class="badge bg-secondary">No events available</span>
                        <?php endif; ?>
                    </div>
                    <form method="GET" class="row g-3 align-items-end mb-4">
                        <div class="col-md-5">
                            <label class="form-label" for="event-id">Event</label>
                            <select class="form-select" id="event-id" name="event_id">
                                <option value="">All authorized events</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['id']; ?>" <?php echo $event_id === $event['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['event_name'] . ' · ' . $event['event_date']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="registration-number">Registration number</label>
                            <input class="form-control font-monospace" id="registration-number" name="registration_number" maxlength="40" value="<?php echo htmlspecialchars($registration_number, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="attendance-status">Participant status</label>
                            <select class="form-select" id="attendance-status" name="attendance_status">
                                <option value="all" <?php echo $attendance_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="checked_in" <?php echo $attendance_filter === 'checked_in' ? 'selected' : ''; ?>>Checked in</option>
                                <option value="not_checked_in" <?php echo $attendance_filter === 'not_checked_in' ? 'selected' : ''; ?>>Not checked in</option>
                                <option value="no_show" <?php echo $attendance_filter === 'no_show' ? 'selected' : ''; ?>>No-show</option>
                                <option value="cancelled" <?php echo $attendance_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary" type="submit">Filter report</button>
                            <a class="btn btn-outline-secondary" href="attendance.php">Clear</a>
                        </div>
                    </form>
                    <div class="row g-3">
                        <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><h2 class="h6 text-muted">Eligible registrations</h2><p class="h3 fw-bold mb-0"><?php echo $eligible_count; ?></p></div></div>
                        <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><h2 class="h6 text-muted">Checked in</h2><p class="h3 fw-bold mb-0"><?php echo $checked_in_count; ?></p></div></div>
                        <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><h2 class="h6 text-muted">Attendance rate</h2><p class="h3 fw-bold mb-0"><?php echo $attendance_rate === null ? '—' : htmlspecialchars((string)$attendance_rate) . '%'; ?></p></div></div>
                        <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><h2 class="h6 text-muted">Cancelled</h2><p class="h3 fw-bold mb-0"><?php echo $cancelled_count; ?></p></div></div>
                    </div>
                    <?php
                    $selected_event = null;
                    foreach ($events as $event) {
                        if ($event['id'] === $event_id) {
                            $selected_event = $event;
                            break;
                        }
                    }
                    ?>
                    <?php if ($selected_event && $selected_event['attendance_enabled'] !== 1): ?>
                        <div class="alert alert-secondary mt-4 mb-0">Attendance tracking is disabled for this event. Registration records remain visible, but no attendance rate can be recorded.</div>
                    <?php endif; ?>
                </div>
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h2 class="h5 fw-bold mb-3">Participant attendance</h2>
                    <?php if ($attendance_rows->num_rows === 0): ?>
                        <p class="text-muted mb-0">No participant records match the selected filters.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle modern-table">
                                <thead><tr><th>Registration No.</th><th>Participant</th><th>Event</th><th>Registration status</th><th>Attendance status</th><th>Check-in time</th></tr></thead>
                                <tbody>
                                    <?php while ($row = $attendance_rows->fetch_assoc()): ?>
                                        <?php
                                        $is_cancelled = $row['registration_status'] === 'cancelled';
                                        $is_checked_in = !$is_cancelled
                                            && ($row['attendance_status'] === 'checked_in' || $row['checked_in_at'] !== null);
                                        $attendance_label = $is_checked_in
                                            ? 'Checked in'
                                            : ($row['attendance_status'] === 'no_show' ? 'No-show' : 'Not checked in');
                                        ?>
                                        <tr>
                                            <td class="font-monospace"><?php echo htmlspecialchars($row['registration_number']); ?></td>
                                            <td><?php echo htmlspecialchars($row['participant_name']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($row['event_name']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($row['event_date']); ?></small>
                                                <?php if ((int)$row['attendance_enabled'] !== 1): ?><br><small class="text-muted">Attendance tracking off</small><?php endif; ?>
                                            </td>
                                            <td><?php echo $is_cancelled ? 'Cancelled' : 'Registered'; ?></td>
                                            <td>
                                                <span class="badge <?php echo $is_cancelled ? 'bg-secondary' : ($is_checked_in ? 'bg-success' : 'bg-soft-primary'); ?>">
                                                    <?php echo htmlspecialchars($is_cancelled ? 'Not applicable' : $attendance_label); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $is_checked_in ? htmlspecialchars($row['checked_in_at']) : '—'; ?></td>
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
