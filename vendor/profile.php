<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('vendor');
require_once __DIR__ . '/../includes/db.php';

$vendor_id = (int)$_SESSION['user_id'];
$statement = $conn->prepare(
    'SELECT business_name, description, service_area, verification_status
     FROM vendor_profiles WHERE user_id = ?'
);
$statement->bind_param('i', $vendor_id);
$statement->execute();
$profile = $statement->get_result()->fetch_assoc();
if (!$profile) {
    http_response_code(500);
    exit('Vendor profile is unavailable.');
}

$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $business_name = isset($_POST['business_name']) && is_string($_POST['business_name'])
        ? trim($_POST['business_name'])
        : '';
    $description = isset($_POST['description']) && is_string($_POST['description'])
        ? trim($_POST['description'])
        : '';
    $service_area = isset($_POST['service_area']) && is_string($_POST['service_area'])
        ? trim($_POST['service_area'])
        : '';
    if ($business_name === '' || strlen($business_name) > 150 || strlen($service_area) > 255 || strlen($description) > 12000) {
        $message = 'Enter a business name and valid profile details.';
        $message_type = 'danger';
    } else {
        $save = $conn->prepare(
            'UPDATE vendor_profiles SET business_name = ?, description = ?, service_area = ?
             WHERE user_id = ?'
        );
        $save->bind_param('sssi', $business_name, $description, $service_area, $vendor_id);
        $save->execute();
        $profile['business_name'] = $business_name;
        $profile['description'] = $description;
        $profile['service_area'] = $service_area;
        $message = 'Your business profile was saved.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Profile - EventEase</title>
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
                    <h1 class="h3 fw-bold mb-3">Business Profile</h1>
                    <p class="text-muted">Verification status: <strong><?php echo htmlspecialchars(ucfirst($profile['verification_status'])); ?></strong>. Editing your profile does not bypass administrator verification.</p>
                    <?php if ($message !== ''): ?><div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                        <div class="col-12">
                            <label class="form-label" for="business-name">Business name</label>
                            <input type="text" id="business-name" name="business_name" class="form-control" maxlength="150" value="<?php echo htmlspecialchars($profile['business_name']); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="service-area">Service area</label>
                            <input type="text" id="service-area" name="service_area" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($profile['service_area'] ?? ''); ?>" placeholder="City, region or travel radius">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">About your business</label>
                            <textarea id="description" name="description" class="form-control" rows="6" maxlength="3000"><?php echo htmlspecialchars($profile['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12"><button type="submit" class="btn btn-primary">Save profile</button></div>
                    </form>
                </section>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
