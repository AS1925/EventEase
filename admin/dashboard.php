<?php 
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include('../includes/access_control.php');
require_role('admin');
include('../includes/db.php');

$total_users = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc();
$total_organizers = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'organizer'")->fetch_assoc();
$verified_organizers = $conn->query("SELECT COUNT(*) AS total FROM organizer_profiles WHERE verification_status = 'verified'")->fetch_assoc();
$total_vendors = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'vendor'")->fetch_assoc();
$total_events = $conn->query("SELECT COUNT(*) AS total FROM events")->fetch_assoc();
$total_registrations = $conn->query("SELECT COUNT(*) AS total FROM registrations WHERE registration_status = 'active'")->fetch_assoc();
$total_requests = $conn->query("SELECT COUNT(*) AS total FROM service_requests")->fetch_assoc();
$total_enquiries = $conn->query("SELECT COUNT(*) AS total FROM contact_messages")->fetch_assoc();
$pending_reviews = $conn->query("SELECT COUNT(*) AS total FROM event_reviews WHERE status = 'Pending'")->fetch_assoc();
$pending_questions = $conn->query("SELECT COUNT(*) AS total FROM event_questions WHERE status = 'pending'")->fetch_assoc();
$recent_registrations = $conn->query("SELECT registrations.id, registrations.registration_number, users.name, events.event_name, registrations.registration_status, registrations.attendance_status FROM registrations INNER JOIN users ON registrations.user_id = users.id INNER JOIN events ON registrations.event_id = events.id ORDER BY registrations.id DESC LIMIT 5");
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
        <title>Admin Dashboard - Event Ease</title>
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-lg-3">
                    <?php include '../includes/admin_sidebar.php'; ?>
                </div>
                <div class="col-lg-9">
                    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <p class="text-primary fw-semibold mb-1">Admin Overview</p>
                                <h2 class="fw-bold mb-1">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                                <p class="text-muted mb-0">Monitor events, registrations, and service requests from one dashboard.</p>
                            </div>
                            <a href="add_event.php" class="btn btn-primary">Add New Event</a>
                        </div>
                    </div>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6 col-xl-3"><div class="card stat-card h-100"><div class="icon-badge"><i class="fa-solid fa-users"></i></div><h6 class="text-muted">Total Users</h6><h3 class="fw-bold mb-0"><?php echo (int)$total_users['total']; ?></h3></div></div>
                        <div class="col-md-6 col-xl-3"><a href="manage_organizers.php" class="card stat-card h-100 text-decoration-none"><div class="icon-badge"><i class="fa-solid fa-user-check"></i></div><h6 class="text-muted">Verified Organizers</h6><h3 class="fw-bold mb-0"><?php echo (int)$verified_organizers['total']; ?> / <?php echo (int)$total_organizers['total']; ?></h3></a></div>
                        <div class="col-md-6 col-xl-3"><div class="card stat-card h-100"><div class="icon-badge"><i class="fa-solid fa-store"></i></div><h6 class="text-muted">Vendors</h6><h3 class="fw-bold mb-0"><?php echo (int)$total_vendors['total']; ?></h3></div></div>
                        <div class="col-md-6 col-xl-3"><div class="card stat-card h-100"><div class="icon-badge"><i class="fa-solid fa-calendar-days"></i></div><h6 class="text-muted">Events</h6><h3 class="fw-bold mb-0"><?php echo (int)$total_events['total']; ?></h3></div></div>
                        <div class="col-md-6 col-xl-3"><div class="card stat-card h-100"><div class="icon-badge"><i class="fa-solid fa-ticket-alt"></i></div><h6 class="text-muted">Active Registrations</h6><h3 class="fw-bold mb-0"><?php echo (int)$total_registrations['total']; ?></h3></div></div>
                        <div class="col-md-6 col-xl-3"><div class="card stat-card h-100"><div class="icon-badge"><i class="fa-solid fa-handshake"></i></div><h6 class="text-muted">Service Requests</h6><h3 class="fw-bold mb-0"><?php echo (int)$total_requests['total']; ?></h3></div></div>
                        <div class="col-md-6 col-xl-3"><a href="enquiries.php" class="card stat-card h-100 text-decoration-none"><div class="icon-badge"><i class="fa-solid fa-inbox"></i></div><h6 class="text-muted">Contact Enquiries</h6><h3 class="fw-bold mb-0"><?php echo (int)$total_enquiries['total']; ?></h3></a></div>
                        <div class="col-md-6 col-xl-3"><a href="manage_reviews.php" class="card stat-card h-100 text-decoration-none"><div class="icon-badge"><i class="fa-solid fa-star"></i></div><h6 class="text-muted">Pending Reviews</h6><h3 class="fw-bold mb-0"><?php echo (int)$pending_reviews['total']; ?></h3></a></div>
                        <div class="col-md-6 col-xl-3"><a href="manage_questions.php" class="card stat-card h-100 text-decoration-none"><div class="icon-badge"><i class="fa-solid fa-circle-question"></i></div><h6 class="text-muted">Pending Questions</h6><h3 class="fw-bold mb-0"><?php echo (int)$pending_questions['total']; ?></h3></a></div>
                    </div>
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <h5 class="fw-bold mb-3">Recent Registrations</h5>
                        <div class="table-responsive">
                            <table class="table modern-table align-middle">
                                <thead>
                                    <tr><th>#</th><th>User</th><th>Event</th><th>Registration No.</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php $count=1; while($row = $recent_registrations->fetch_assoc()){ ?>
                                    <tr><td><?php echo $count++; ?></td><td><?php echo htmlspecialchars($row['name']); ?></td><td><?php echo htmlspecialchars($row['event_name']); ?></td><td class="font-monospace"><?php echo htmlspecialchars($row['registration_number']); ?></td><td><span class="badge bg-soft-primary text-primary"><?php echo htmlspecialchars(ucfirst($row['registration_status']) . ' / ' . str_replace('_', ' ', ucfirst($row['attendance_status']))); ?></span></td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../includes/footer.php'; ?>
    </body>
</html>
