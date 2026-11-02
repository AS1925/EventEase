<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

$attendance_statuses = array(
    'not_checked_in' => 'Not checked in',
    'checked_in' => 'Checked in',
    'no_show' => 'No-show'
);
$registration_statuses = array(
    'active' => 'Registered',
    'cancelled' => 'Cancelled'
);

$search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
$event_id_input = isset($_GET['event_id']) && is_string($_GET['event_id'])
    ? $_GET['event_id']
    : '';
$status_filter = isset($_GET['registration_status']) && is_string($_GET['registration_status'])
    ? $_GET['registration_status']
    : '';
if (strlen($search) > 40 || ($event_id_input !== '' && !ctype_digit($event_id_input))) {
    http_response_code(400);
    exit('Invalid registration filter.');
}
if ($status_filter !== '' && !isset($registration_statuses[$status_filter])) {
    http_response_code(400);
    exit('Invalid registration status filter.');
}
$filter_event_id = $event_id_input === '' ? 0 : (int)$event_id_input;
$search_pattern = '%' . addcslashes($search, '\\%_') . '%';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();

    $registration_id_input = isset($_POST['registration_id']) && is_string($_POST['registration_id'])
        ? $_POST['registration_id']
        : '';
    $registration_status = isset($_POST['registration_status']) && is_string($_POST['registration_status'])
        ? $_POST['registration_status']
        : '';
    $attendance_status = isset($_POST['attendance_status']) && is_string($_POST['attendance_status'])
        ? $_POST['attendance_status']
        : '';

    if (
        !ctype_digit($registration_id_input)
        || (int)$registration_id_input < 1
        || !isset($registration_statuses[$registration_status])
        || !isset($attendance_statuses[$attendance_status])
    ) {
        http_response_code(400);
        exit('Invalid registration update.');
    }

    $registration_id = (int)$registration_id_input;
    $update_result = 'not_found';
    $transaction_started = false;
    try {
        $conn->begin_transaction();
        $transaction_started = true;

        $event_lookup = $conn->prepare('SELECT event_id FROM registrations WHERE id = ?');
        $event_lookup->bind_param('i', $registration_id);
        $event_lookup->execute();
        $event_row = $event_lookup->get_result()->fetch_assoc();

        if ($event_row) {
            $event_id = (int)$event_row['event_id'];
            $event_lock = $conn->prepare('SELECT max_participants FROM events WHERE id = ? FOR UPDATE');
            $event_lock->bind_param('i', $event_id);
            $event_lock->execute();
            $event = $event_lock->get_result()->fetch_assoc();

            $registration_lookup = $conn->prepare(
                'SELECT user_id, event_id, registration_status
                 FROM registrations
                 WHERE id = ? AND event_id = ?
                 FOR UPDATE'
            );
            $registration_lookup->bind_param('ii', $registration_id, $event_id);
            $registration_lookup->execute();
            $registration = $registration_lookup->get_result()->fetch_assoc();

            if ($event && $registration) {
                $can_activate = true;
                if ($registration_status === 'active' && $registration['registration_status'] !== 'active') {
                    $duplicate_lookup = $conn->prepare(
                        "SELECT id
                         FROM registrations
                         WHERE user_id = ? AND event_id = ?
                           AND registration_status = 'active' AND id <> ?
                         LIMIT 1"
                    );
                    $duplicate_lookup->bind_param('iii', $registration['user_id'], $event_id, $registration_id);
                    $duplicate_lookup->execute();
                    if ($duplicate_lookup->get_result()->num_rows > 0) {
                        $update_result = 'duplicate';
                        $can_activate = false;
                    }

                    if ($can_activate) {
                        $capacity_lookup = $conn->prepare(
                            "SELECT COUNT(*) AS total
                             FROM registrations
                             WHERE event_id = ? AND registration_status = 'active'"
                        );
                        $capacity_lookup->bind_param('i', $event_id);
                        $capacity_lookup->execute();
                        $active_registrations = (int)$capacity_lookup->get_result()->fetch_assoc()['total'];
                        if ($active_registrations >= (int)$event['max_participants']) {
                            $update_result = 'full';
                            $can_activate = false;
                        }
                    }
                }

                if ($can_activate) {
                    $update = $conn->prepare(
                        'UPDATE registrations
                         SET registration_status = ?, attendance_status = ?
                         WHERE id = ?'
                    );
                    $update->bind_param('ssi', $registration_status, $attendance_status, $registration_id);
                    $update->execute();
                    $conn->commit();
                    $transaction_started = false;
                    $update_result = '1';
                }
            }
        }

        if ($transaction_started) {
            $conn->rollback();
            $transaction_started = false;
        }
    } catch (mysqli_sql_exception $exception) {
        if ($transaction_started) {
            $conn->rollback();
        }
        error_log('Admin registration update failed with MySQL error code ' . $exception->getCode() . '.');
        $update_result = 'error';
    }

    $redirect_parameters = $_GET;
    $redirect_parameters['updated'] = $update_result;
    header('Location: view_registrations.php?' . http_build_query($redirect_parameters));
    exit();
}

$events_query = $conn->query(
    'SELECT DISTINCT events.id, events.event_name
     FROM events
     INNER JOIN registrations ON registrations.event_id = events.id
     ORDER BY events.event_name, events.id'
);
$events = array();
while ($event_option = $events_query->fetch_assoc()) {
    $events[] = $event_option;
}

$query = $conn->prepare(
    'SELECT registrations.id, registrations.registration_number,
            registrations.registration_status, registrations.attendance_status,
            registrations.registration_date, users.name, users.email,
            events.id AS event_id, events.event_name, events.event_date,
            events.start_time, events.event_time, events.end_time, events.venue
     FROM registrations
     INNER JOIN users ON users.id = registrations.user_id
     INNER JOIN events ON events.id = registrations.event_id
     WHERE (? = \'\' OR registrations.registration_number LIKE ?)
       AND (? = 0 OR events.id = ?)
       AND (? = \'\' OR registrations.registration_status = ?)
     ORDER BY registrations.registration_date DESC, registrations.id DESC'
);
$query->bind_param(
    'ssiiss',
    $search,
    $search_pattern,
    $filter_event_id,
    $filter_event_id,
    $status_filter,
    $status_filter
);
$query->execute();
$result = $query->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <title>View Registrations</title>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <main class="container py-5">
        <div class="row g-4">
            <aside class="col-lg-3"><?php include '../includes/admin_sidebar.php'; ?></aside>
            <section class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h1 class="h3 fw-bold mb-3">Event Registrations</h1>
                    <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
                        <div class="alert alert-success" role="status">Registration details updated.</div>
                    <?php elseif (isset($_GET['updated']) && $_GET['updated'] === 'full'): ?>
                        <div class="alert alert-warning" role="status">This event has no seats available; the registration was not reactivated.</div>
                    <?php elseif (isset($_GET['updated']) && $_GET['updated'] === 'duplicate'): ?>
                        <div class="alert alert-warning" role="status">This participant already has an active registration for the event.</div>
                    <?php elseif (isset($_GET['updated']) && $_GET['updated'] === 'not_found'): ?>
                        <div class="alert alert-warning" role="status">The selected registration was not found.</div>
                    <?php elseif (isset($_GET['updated']) && $_GET['updated'] === 'error'): ?>
                        <div class="alert alert-danger" role="alert">The registration could not be updated. Please try again.</div>
                    <?php endif; ?>
                    <form method="GET" class="row g-3 align-items-end mb-4">
                        <div class="col-md-5">
                            <label class="form-label" for="registration-search">Registration number</label>
                            <input id="registration-search" class="form-control font-monospace" type="search" name="search" maxlength="40" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="event-filter">Event</label>
                            <select id="event-filter" name="event_id" class="form-select">
                                <option value="">All events</option>
                                <?php foreach ($events as $event_option): ?>
                                    <option value="<?php echo (int)$event_option['id']; ?>" <?php echo $filter_event_id === (int)$event_option['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event_option['event_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="status-filter">Status</label>
                            <select id="status-filter" name="registration_status" class="form-select">
                                <option value="">All</option>
                                <?php foreach ($registration_statuses as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $status_filter === $value ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                    </form>
                    <?php if ($result->num_rows === 0): ?>
                        <p class="text-muted mb-0">No registrations match these filters.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle modern-table">
                                <thead>
                                    <tr><th>Registration No.</th><th>Participant</th><th>Event</th><th>Event Date/Time</th><th>Venue</th><th>Registered On</th><th>Registration</th><th>Attendance</th><th>Update</th></tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="font-monospace"><?php echo htmlspecialchars($row['registration_number']); ?></td>
                                            <td><?php echo htmlspecialchars($row['name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($row['email']); ?></small></td>
                                            <td><?php echo htmlspecialchars($row['event_name']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($row['event_date']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($row['start_time'] ?: $row['event_time'], 0, 5)); ?><?php if (!empty($row['end_time'])): ?>–<?php echo htmlspecialchars(substr($row['end_time'], 0, 5)); ?><?php endif; ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['venue']); ?></td>
                                            <td><?php echo htmlspecialchars($row['registration_date']); ?></td>
                                            <td><span class="badge bg-soft-primary text-primary"><?php echo htmlspecialchars($registration_statuses[$row['registration_status']]); ?></span></td>
                                            <td><span class="badge bg-soft-secondary text-secondary"><?php echo htmlspecialchars($attendance_statuses[$row['attendance_status']]); ?></span></td>
                                            <td>
                                                <form method="POST" class="d-flex flex-column gap-2 align-items-stretch">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                                    <input type="hidden" name="registration_id" value="<?php echo (int)$row['id']; ?>">
                                                    <label class="visually-hidden" for="registration-status-<?php echo (int)$row['id']; ?>">Registration status</label>
                                                    <select id="registration-status-<?php echo (int)$row['id']; ?>" name="registration_status" class="form-select form-select-sm">
                                                        <?php foreach ($registration_statuses as $value => $label): ?>
                                                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $row['registration_status'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label class="visually-hidden" for="attendance-status-<?php echo (int)$row['id']; ?>">Attendance status</label>
                                                    <select id="attendance-status-<?php echo (int)$row['id']; ?>" name="attendance_status" class="form-select form-select-sm">
                                                        <?php foreach ($attendance_statuses as $value => $label): ?>
                                                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $row['attendance_status'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-outline-primary btn-sm">Save</button>
                                                </form>
                                            </td>
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
