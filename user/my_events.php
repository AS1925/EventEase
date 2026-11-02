<?php require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("user");
include("../includes/db.php");
$user_id = (int)$_SESSION['user_id'];
$query = $conn->prepare(
    "SELECT registrations.id AS reg_id, registrations.registration_number,
            registrations.registration_status, registrations.attendance_status,
            registrations.registration_date,
            EXISTS (
                SELECT 1 FROM event_attendance
                WHERE event_attendance.registration_id = registrations.id
            ) AS has_attendance_record,
            (
                registrations.registration_status = 'active'
                AND registrations.attendance_status <> 'checked_in'
                AND (
                    events.event_date > CURDATE()
                    OR (
                        events.event_date = CURDATE()
                        AND COALESCE(events.start_time, events.event_time, '23:59:59') > CURTIME()
                    )
                )
                AND NOT EXISTS (
                    SELECT 1 FROM event_attendance AS cancel_checkin
                    WHERE cancel_checkin.registration_id = registrations.id
                )
            ) AS can_cancel,
            events.id AS event_id, events.event_name, events.category, events.event_type,
            events.event_date, events.start_time, events.event_time, events.end_time,
            events.venue, events.attendance_enabled, events.checkin_method,
            events.venue_verification_enabled, events.checkin_radius_m
     FROM registrations
     INNER JOIN events ON registrations.event_id = events.id
     WHERE registrations.user_id = ?
     ORDER BY events.event_date ASC, events.start_time ASC, registrations.id DESC"
);
$query->bind_param("i", $user_id);
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
    <title>My Events</title>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3">
                <?php include '../includes/user_sidebar.php'; ?>
            </div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h3 class="fw-bold mb-1">My Registered Events</h3>
                            <p class="text-muted mb-0">Track registrations and manage cancellations.</p>
                        </div>
                    </div>
                    <?php if (isset($_GET['registered']) && $_GET['registered'] === '1'): ?>
                        <div class="alert alert-success" role="status">Your event registration is confirmed. Your registration number is shown below.</div>
                    <?php elseif (isset($_GET['cancelled']) && $_GET['cancelled'] === '1'): ?>
                        <div class="alert alert-success" role="status">Your registration was cancelled. The seat is available again.</div>
                    <?php elseif (isset($_GET['cancelled']) && $_GET['cancelled'] === 'not_allowed'): ?>
                        <div class="alert alert-warning" role="status">This registration is no longer eligible for cancellation.</div>
                    <?php elseif (isset($_GET['cancelled']) && $_GET['cancelled'] === 'error'): ?>
                        <div class="alert alert-danger" role="alert">We could not process the cancellation. Please try again.</div>
                    <?php endif; ?>
                    <?php if ($result->num_rows === 0): ?>
                        <div class="text-center py-5">
                            <i class="fa-solid fa-calendar-plus text-primary fs-2 mb-3"></i>
                            <h4 class="h5 fw-semibold">No registered events</h4>
                            <p class="text-muted">Browse available events to find your next experience.</p>
                            <a href="../events.php" class="btn btn-primary">Explore Events</a>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle modern-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Event</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Venue</th>
                                    <th>Registration Number</th>
                                    <th>Registered On</th>
                                    <th>Registration Status</th>
                                    <th>Attendance Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $count=1; while($row = $result->fetch_assoc()){ ?>
                                <?php
                                    $checkin_window = eventease_checkin_window_status($row);
                                    $active_registration = $row['registration_status'] === 'active';
                                    $attendance_enabled = (int)$row['attendance_enabled'] === 1;
                                    $checkin_already_recorded = $row['attendance_status'] === 'checked_in'
                                        || (int)$row['has_attendance_record'] === 1;
                                    $can_gps_check_in = $active_registration
                                        && $attendance_enabled
                                        && $row['checkin_method'] === 'gps'
                                        && (int)$row['venue_verification_enabled'] === 1
                                        && $checkin_window['status'] === 'open'
                                        && !$checkin_already_recorded;
                                ?>
                                <tr>
                                    <td><?php echo $count++;?></td>
                                    <td><?php echo htmlspecialchars($row['event_name']);?></td>
                                    <td><?php echo htmlspecialchars($row['category'] ?: $row['event_type']);?></td>
                                    <td><?php echo htmlspecialchars($row['event_date']);?></td>
                                    <td>
                                        <?php echo htmlspecialchars(substr($row['start_time'] ?: $row['event_time'], 0, 5)); ?>
                                        <?php if (!empty($row['end_time'])): ?>–<?php echo htmlspecialchars(substr($row['end_time'], 0, 5)); ?><?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['venue']);?></td>
                                    <td>
                                        <span class="font-monospace fw-semibold"><?php echo htmlspecialchars($row['registration_number']);?></span>
                                        <button type="button" class="btn btn-outline-secondary btn-sm ms-1" data-copy-registration-number="<?php echo htmlspecialchars($row['registration_number'], ENT_QUOTES, 'UTF-8'); ?>">Copy</button>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['registration_date']);?></td>
                                    <td><span class="badge <?php echo $row['registration_status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'; ?>"><?php echo $row['registration_status'] === 'active' ? 'Registered' : 'Cancelled';?></span></td>
                                    <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['attendance_status'])));?></td>
                                    <td class="d-flex flex-wrap gap-2">
                                        <?php if ($can_gps_check_in): ?>
                                            <a href="check_in.php?registration_number=<?php echo rawurlencode($row['registration_number']); ?>" class="btn btn-primary btn-sm">Check in</a>
                                        <?php elseif ($attendance_enabled && $active_registration && !$checkin_already_recorded && $checkin_window['status'] === 'not_open'): ?>
                                            <span class="small text-muted">Check-in opens <?php echo htmlspecialchars($checkin_window['opens_at']); ?></span>
                                        <?php elseif ($attendance_enabled && $active_registration && !$checkin_already_recorded && $checkin_window['status'] === 'closed'): ?>
                                            <span class="small text-muted">Check-in closed</span>
                                        <?php elseif ($attendance_enabled && $active_registration && !$checkin_already_recorded && $row['checkin_method'] === 'organizer_manual'): ?>
                                            <span class="small text-muted">Organizer check-in</span>
                                        <?php elseif (
                                            $row['event_date'] < date('Y-m-d')
                                            && $active_registration
                                            && (
                                                (int)$row['attendance_enabled'] === 0
                                                || (int)$row['has_attendance_record'] === 1
                                            )
                                        ): ?>
                                            <a href="event_review.php?id=<?php echo (int)$row['event_id']; ?>" class="btn btn-outline-primary btn-sm">Review</a>
                                        <?php elseif ((int)$row['can_cancel'] === 1): ?>
                                            <form method="POST" action="cancel_registration.php" class="m-0">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int)$row['reg_id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Cancel Registration?')">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($row['registration_status'] === 'active'): ?>
                                            <a href="event_questions.php?id=<?php echo (int)$row['event_id']; ?>" class="btn btn-outline-secondary btn-sm">Questions</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php } ?>
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