<?php require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("user");
include('../includes/db.php');
$user_id = (int)$_SESSION['user_id'];

$stats_statement = $conn->prepare(
    "SELECT COUNT(*) AS active_registrations,
            SUM(CASE WHEN events.event_date >= CURDATE() THEN 1 ELSE 0 END) AS upcoming_events
     FROM registrations
     INNER JOIN events ON events.id = registrations.event_id
     WHERE registrations.user_id = ? AND registrations.registration_status = 'active'"
);
$stats_statement->bind_param("i", $user_id);
$stats_statement->execute();
$stats = $stats_statement->get_result()->fetch_assoc();

$question_count_statement = $conn->prepare(
    "SELECT COUNT(*) AS total FROM event_questions WHERE user_id = ? AND status = 'pending'"
);
$question_count_statement->bind_param('i', $user_id);
$question_count_statement->execute();
$pending_questions = (int)$question_count_statement->get_result()->fetch_assoc()['total'];

$service_count_statement = $conn->prepare('SELECT COUNT(*) AS total FROM service_requests WHERE user_id = ?');
$service_count_statement->bind_param('i', $user_id);
$service_count_statement->execute();
$service_count = (int)$service_count_statement->get_result()->fetch_assoc()['total'];

$registrations_statement = $conn->prepare(
    'SELECT registrations.registration_number, registrations.registration_status,
            registrations.attendance_status, events.event_name, events.event_date
     FROM registrations
     INNER JOIN events ON events.id = registrations.event_id
     WHERE registrations.user_id = ?
     ORDER BY registrations.registration_date DESC, registrations.id DESC
     LIMIT 5'
);
$registrations_statement->bind_param('i', $user_id);
$registrations_statement->execute();
$recent_registrations = $registrations_statement->get_result();

$questions_statement = $conn->prepare(
    'SELECT event_questions.id, event_questions.status, event_questions.question_text,
            event_questions.answer_text, events.event_name
     FROM event_questions
     INNER JOIN events ON events.id = event_questions.event_id
     WHERE event_questions.user_id = ?
     ORDER BY event_questions.created_at DESC, event_questions.id DESC
     LIMIT 3'
);
$questions_statement->bind_param('i', $user_id);
$questions_statement->execute();
$recent_questions = $questions_statement->get_result();

$services_statement = $conn->prepare(
    'SELECT event_type, event_date, status FROM service_requests
     WHERE user_id = ? ORDER BY id DESC LIMIT 3'
);
$services_statement->bind_param('i', $user_id);
$services_statement->execute();
$recent_services = $services_statement->get_result();
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
    <title>User Dashboard - Event Ease</title>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3">
                <?php include '../includes/user_sidebar.php'; ?>
            </div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-lg-5 mb-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <p class="text-primary fw-semibold mb-2">Welcome back</p>
                            <h2 class="fw-bold mb-1">Hello, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                            <p class="text-muted mb-0">Here’s an overview of your event activity and next steps.</p>
                        </div>
                        <a href="service_request.php" class="btn btn-primary">Request a Service</a>
                    </div>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="icon-badge"><i class="fa-solid fa-ticket"></i></div><h6 class="text-muted">Active Registrations</h6><h3 class="fw-bold mb-0"><?php echo (int)$stats['active_registrations']; ?></h3></div></div>
                    <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="icon-badge"><i class="fa-solid fa-calendar-days"></i></div><h6 class="text-muted">Upcoming Events</h6><h3 class="fw-bold mb-0"><?php echo (int)($stats['upcoming_events'] ?? 0); ?></h3></div></div>
                    <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="icon-badge"><i class="fa-solid fa-circle-question"></i></div><h6 class="text-muted">Questions Awaiting Reply</h6><h3 class="fw-bold mb-0"><?php echo $pending_questions; ?></h3></div></div>
                    <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="icon-badge"><i class="fa-solid fa-handshake"></i></div><h6 class="text-muted">Service Requests</h6><h3 class="fw-bold mb-0"><?php echo $service_count; ?></h3></div></div>
                </div>
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h5 fw-bold mb-0">Recent Registrations</h2>
                        <a href="my_events.php" class="btn btn-outline-primary btn-sm">View all</a>
                    </div>
                    <?php if ($recent_registrations->num_rows === 0): ?>
                        <p class="text-muted mb-0">No registrations yet. <a href="../events.php">Explore events</a>.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle modern-table">
                                <thead><tr><th>Event</th><th>Date</th><th>Registration No.</th><th>Registration status</th><th>Attendance</th></tr></thead>
                                <tbody>
                                    <?php while ($registration = $recent_registrations->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($registration['event_name']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['event_date']); ?></td>
                                            <td class="font-monospace"><?php echo htmlspecialchars($registration['registration_number']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($registration['registration_status'])); ?></td>
                                            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $registration['attendance_status']))); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h5 fw-bold mb-0">Recent Questions</h2>
                                <a href="my_events.php" class="btn btn-outline-primary btn-sm">My Events</a>
                            </div>
                            <?php if ($recent_questions->num_rows === 0): ?>
                                <p class="text-muted mb-0">You have not asked any event questions yet.</p>
                            <?php else: ?>
                                <div class="vstack gap-3">
                                    <?php while ($question = $recent_questions->fetch_assoc()): ?>
                                        <div class="border-bottom pb-2">
                                            <div class="d-flex justify-content-between gap-2">
                                                <strong><?php echo htmlspecialchars($question['event_name']); ?></strong>
                                                <span class="badge <?php echo $question['status'] === 'answered' ? 'bg-success' : ($question['status'] === 'closed' ? 'bg-secondary' : 'bg-warning text-dark'); ?>"><?php echo htmlspecialchars(ucfirst($question['status'])); ?></span>
                                            </div>
                                            <p class="small mb-0 mt-1"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                            <?php if ($question['answer_text'] !== null && $question['answer_text'] !== ''): ?><p class="small text-muted mb-0 mt-1">Reply: <?php echo htmlspecialchars($question['answer_text']); ?></p><?php endif; ?>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h5 fw-bold mb-0">Recent Service Requests</h2>
                                <a href="my_requests.php" class="btn btn-outline-primary btn-sm">All requests</a>
                            </div>
                            <?php if ($recent_services->num_rows === 0): ?>
                                <p class="text-muted mb-3">No service requests yet.</p>
                                <a href="service_request.php" class="btn btn-primary btn-sm align-self-start">Request a service</a>
                            <?php else: ?>
                                <div class="vstack gap-3">
                                    <?php while ($service = $recent_services->fetch_assoc()): ?>
                                        <div class="d-flex justify-content-between border-bottom pb-2 gap-2">
                                            <span><?php echo htmlspecialchars($service['event_type']); ?> · <?php echo htmlspecialchars($service['event_date']); ?></span>
                                            <span class="badge bg-soft-primary text-primary"><?php echo htmlspecialchars($service['status']); ?></span>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>