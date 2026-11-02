<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('vendor');
require_once __DIR__ . '/../includes/db.php';

$vendor_id = (int)$_SESSION['user_id'];
$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'add') {
        $category = isset($_POST['category']) && is_string($_POST['category']) ? trim($_POST['category']) : '';
        $service_name = isset($_POST['service_name']) && is_string($_POST['service_name']) ? trim($_POST['service_name']) : '';
        $description = isset($_POST['description']) && is_string($_POST['description']) ? trim($_POST['description']) : '';
        $package_name = isset($_POST['package_name']) && is_string($_POST['package_name']) ? trim($_POST['package_name']) : '';
        $package_details = isset($_POST['package_details']) && is_string($_POST['package_details']) ? trim($_POST['package_details']) : '';
        $price_input = isset($_POST['base_price']) && is_string($_POST['base_price']) ? trim($_POST['base_price']) : '';
        $price_valid = $price_input !== '' && is_numeric($price_input) && is_finite((float)$price_input);
        $price = $price_valid ? (float)$price_input : 0;
        if (
            $category === '' || strlen($category) > 100
            || $service_name === '' || strlen($service_name) > 150
            || $package_name === '' || strlen($package_name) > 150
            || strlen($description) > 12000 || strlen($package_details) > 12000
            || !$price_valid || $price < 0 || $price > 99999999.99
        ) {
            $message = 'Enter a valid category, service, package, description, and price.';
            $message_type = 'danger';
        } else {
            $insert = $conn->prepare(
                'INSERT INTO vendor_services
                    (vendor_id, category, service_name, description, package_name, package_details, base_price)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->bind_param('isssssd', $vendor_id, $category, $service_name, $description, $package_name, $package_details, $price);
            $insert->execute();
            header('Location: services.php?saved=1');
            exit();
        }
    } elseif ($action === 'toggle' && isset($_POST['service_id']) && is_string($_POST['service_id']) && ctype_digit($_POST['service_id'])) {
        $service_id = (int)$_POST['service_id'];
        $toggle = $conn->prepare(
            'UPDATE vendor_services SET is_active = IF(is_active = 1, 0, 1)
             WHERE id = ? AND vendor_id = ?'
        );
        $toggle->bind_param('ii', $service_id, $vendor_id);
        $toggle->execute();
        if ($toggle->affected_rows === 1) {
            header('Location: services.php?updated=1');
            exit();
        }
        $message = 'The package was not found in your account.';
        $message_type = 'warning';
    } else {
        $message = 'The package action was invalid.';
        $message_type = 'danger';
    }
}

$services_statement = $conn->prepare(
    'SELECT id, category, service_name, description, package_name, package_details,
            base_price, is_active, created_at
     FROM vendor_services WHERE vendor_id = ? ORDER BY created_at DESC, id DESC'
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
    <title>Services and Packages - EventEase Vendor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <main class="container py-5">
        <section class="card border-0 shadow-sm rounded-4 p-4 p-lg-5 mb-4">
            <h1 class="h3 fw-bold mb-3">Services and Packages</h1>
            <?php if (isset($_GET['saved'])): ?><div class="alert alert-success" role="status">Package saved.</div><?php endif; ?>
            <?php if (isset($_GET['updated'])): ?><div class="alert alert-success" role="status">Package visibility updated.</div><?php endif; ?>
            <?php if ($message !== ''): ?><div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <form method="POST" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                <input type="hidden" name="action" value="add">
                <div class="col-md-6"><label class="form-label" for="category">Category</label><input id="category" name="category" class="form-control" maxlength="100" placeholder="Photography, catering, decor…" required></div>
                <div class="col-md-6"><label class="form-label" for="service-name">Service</label><input id="service-name" name="service_name" class="form-control" maxlength="150" required></div>
                <div class="col-md-6"><label class="form-label" for="package-name">Package name</label><input id="package-name" name="package_name" class="form-control" maxlength="150" required></div>
                <div class="col-md-6"><label class="form-label" for="base-price">Starting price</label><input id="base-price" type="number" name="base_price" class="form-control" min="0" max="99999999.99" step="0.01" required></div>
                <div class="col-12"><label class="form-label" for="description">Service description</label><textarea id="description" name="description" class="form-control" rows="3" maxlength="3000"></textarea></div>
                <div class="col-12"><label class="form-label" for="package-details">What's included</label><textarea id="package-details" name="package_details" class="form-control" rows="3" maxlength="3000"></textarea></div>
                <div class="col-12"><button type="submit" class="btn btn-primary">Add package</button></div>
            </form>
        </section>
        <section class="card border-0 shadow-sm rounded-4 p-4">
            <h2 class="h5 fw-bold mb-3">Your packages</h2>
            <?php if ($services->num_rows === 0): ?>
                <p class="text-muted mb-0">No packages have been added yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle modern-table">
                        <thead><tr><th>Category</th><th>Service & package</th><th>Starting price</th><th>Marketplace status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php while ($service = $services->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($service['category']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($service['service_name']); ?></strong> · <?php echo htmlspecialchars($service['package_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($service['package_details'] ?? ''); ?></small></td>
                                    <td><?php echo htmlspecialchars(number_format((float)$service['base_price'], 2)); ?></td>
                                    <td><?php echo (int)$service['is_active'] === 1 ? 'Active' : 'Hidden'; ?></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="service_id" value="<?php echo (int)$service['id']; ?>">
                                            <button type="submit" class="btn btn-outline-primary btn-sm"><?php echo (int)$service['is_active'] === 1 ? 'Hide' : 'Show'; ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
