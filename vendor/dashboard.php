<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('vendor');
require_once __DIR__ . '/../includes/db.php';

$vendor_id = (int)$_SESSION['user_id'];
$profile_statement = $conn->prepare(
    'SELECT business_name, verification_status FROM vendor_profiles WHERE user_id = ?'
);
$profile_statement->bind_param('i', $vendor_id);
$profile_statement->execute();
$profile = $profile_statement->get_result()->fetch_assoc();
if (!$profile) {
    http_response_code(500);
    exit('Vendor profile is unavailable.');
}

$service_count_statement = $conn->prepare('SELECT COUNT(*) AS total FROM vendor_services WHERE vendor_id = ?');
$service_count_statement->bind_param('i', $vendor_id);
$service_count_statement->execute();
$service_count = (int)$service_count_statement->get_result()->fetch_assoc()['total'];

$booking_statement = $conn->prepare(
    'SELECT COUNT(*) AS total FROM service_requests WHERE vendor_id = ?'
);
$booking_statement->bind_param('i', $vendor_id);
$booking_statement->execute();
$booking_count = (int)$booking_statement->get_result()->fetch_assoc()['total'];

$open_booking_statement = $conn->prepare(
    "SELECT COUNT(*) AS total FROM service_requests
     WHERE vendor_id = ? AND booking_status IN ('requested','quoted')"
);
$open_booking_statement->bind_param('i', $vendor_id);
$open_booking_statement->execute();
$open_booking_count = (int)$open_booking_statement->get_result()->fetch_assoc()['total'];

$services_statement = $conn->prepare(
    'SELECT id, category, service_name, package_name, base_price, is_active
     FROM vendor_services WHERE vendor_id = ? ORDER BY created_at DESC LIMIT 5'
);
$services_statement->bind_param('i', $vendor_id);
$services_statement->execute();
$services = $services_statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <main class="container py-5">
        <div class="row g-4">
            <aside class="col-lg-3">
                <div class="sidebar-card">
                    <div class="sidebar-brand"><h5 class="mb-1">Vendor Panel</h5><small class="text-muted">EventEase</small></div>
                    <div class="list-group list-group-flush">
                        <a href="dashboard.php" class="list-group-item list-group-item-action active">Dashboard</a>
                        <a href="profile.php" class="list-group-item list-group-item-action">Business Profile</a>
                        <a href="services.php" class="list-group-item list-group-item-action">Services & Packages</a>
                        <a href="portfolio.php" class="list-group-item list-group-item-action">Portfolio</a>
                        <a href="availability.php" class="list-group-item list-group-item-action">Availability</a>
                        <a href="requests.php" class="list-group-item list-group-item-action">Bookings & Quotes</a>
                        <a href="reviews.php" class="list-group-item list-group-item-action">Customer Reviews</a>
                        <a href="../vendor_services.php" class="list-group-item list-group-item-action">View Marketplace</a>
                        <form action="../logout.php" method="POST" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                            <button type="submit" class="list-group-item list-group-item-action text-danger text-start w-100 border-0 bg-transparent">Logout</button>
                        </form>
                    </div>
                </div>
            </aside>
            <div class="col-lg-9">
                <section class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <p class="text-primary fw-semibold mb-1">Vendor Overview</p>
                            <h1 class="h3 fw-bold mb-1"><?php echo htmlspecialchars($profile['business_name']); ?></h1>
                            <span class="badge <?php echo $profile['verification_status'] === 'verified' ? 'bg-success' : ($profile['verification_status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                <?php echo htmlspecialchars(ucfirst($profile['verification_status'])); ?>
                            </span>
                        </div>
                        <a href="../vendor_services.php" class="btn btn-outline-primary">View Marketplace</a>
                    </div>
                    <?php if ($profile['verification_status'] !== 'verified'): ?>
                        <div class="alert alert-warning mt-3 mb-0">Your business profile must be verified by an administrator before your packages appear in the marketplace or customers can send bookings.</div>
                    <?php endif; ?>
                </section>
                <div class="row g-3 mb-4">
                    <div class="col-md-4"><div class="card stat-card h-100"><h2 class="h6 text-muted">Packages</h2><p class="h3 fw-bold mb-0"><?php echo $service_count; ?></p></div></div>
                    <div class="col-md-4"><div class="card stat-card h-100"><h2 class="h6 text-muted">Bookings</h2><p class="h3 fw-bold mb-0"><?php echo $booking_count; ?></p></div></div>
                    <div class="col-md-4"><div class="card stat-card h-100"><h2 class="h6 text-muted">Needs response</h2><p class="h3 fw-bold mb-0"><?php echo $open_booking_count; ?></p></div></div>
                </div>
                <section class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0">Your Packages</h2>
                        <a href="services.php" class="btn btn-primary btn-sm">Manage packages</a>
                    </div>
                    <?php if ($services->num_rows === 0): ?>
                        <p class="text-muted mb-0">Add a package to start showcasing your services.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle modern-table">
                                <thead><tr><th>Category</th><th>Service</th><th>Package</th><th>Starting price</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php while ($service = $services->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($service['category']); ?></td>
                                            <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                            <td><?php echo htmlspecialchars($service['package_name']); ?></td>
                                            <td><?php echo htmlspecialchars(number_format((float)$service['base_price'], 2)); ?></td>
                                            <td><?php echo (int)$service['is_active'] === 1 ? 'Active' : 'Hidden'; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
