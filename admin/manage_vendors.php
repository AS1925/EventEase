<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

$statuses = array('pending', 'verified', 'rejected');
$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $vendor_id_input = isset($_POST['vendor_id']) && is_string($_POST['vendor_id']) ? $_POST['vendor_id'] : '';
    $status = isset($_POST['verification_status']) && is_string($_POST['verification_status'])
        ? $_POST['verification_status']
        : '';
    if (!ctype_digit($vendor_id_input) || !in_array($status, $statuses, true)) {
        $message = 'The vendor verification update was invalid.';
        $message_type = 'danger';
    } else {
        $vendor_id = (int)$vendor_id_input;
        $admin_id = (int)$_SESSION['user_id'];
        $update = $conn->prepare(
            "UPDATE vendor_profiles
             SET verification_status = ?,
                 verified_by = CASE WHEN ? = 'verified' THEN ? ELSE NULL END,
                 verified_at = CASE WHEN ? = 'verified' THEN NOW() ELSE NULL END
             WHERE user_id = ?"
        );
        $update->bind_param('ssisi', $status, $status, $admin_id, $status, $vendor_id);
        $update->execute();
        if ($update->affected_rows === 1) {
            header('Location: manage_vendors.php?updated=1');
            exit();
        }
        $message = 'No vendor profile was updated.';
        $message_type = 'warning';
    }
}

$vendors = $conn->query(
    "SELECT vendor_profiles.user_id, vendor_profiles.business_name, vendor_profiles.description,
            vendor_profiles.service_area, vendor_profiles.verification_status,
            vendor_profiles.created_at, users.name, users.email,
            COUNT(DISTINCT vendor_services.id) AS service_count
     FROM vendor_profiles
     INNER JOIN users ON users.id = vendor_profiles.user_id
     LEFT JOIN vendor_services ON vendor_services.vendor_id = vendor_profiles.user_id
     GROUP BY vendor_profiles.user_id
     ORDER BY FIELD(vendor_profiles.verification_status, 'pending', 'verified', 'rejected'),
              vendor_profiles.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vendors - EventEase Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <main class="container py-5">
        <div class="row g-4">
            <aside class="col-lg-3"><?php include '../includes/admin_sidebar.php'; ?></aside>
            <section class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h1 class="h3 fw-bold mb-3">Vendor Verification</h1>
                    <?php if (isset($_GET['updated'])): ?><div class="alert alert-success" role="status">Vendor verification updated.</div><?php endif; ?>
                    <?php if ($message !== ''): ?><div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                    <?php if ($vendors->num_rows === 0): ?>
                        <p class="text-muted mb-0">No vendor accounts yet.</p>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php while ($vendor = $vendors->fetch_assoc()): ?>
                                <article class="border rounded-3 p-3 p-md-4">
                                    <div class="d-flex justify-content-between flex-wrap gap-2">
                                        <div>
                                            <h2 class="h5 fw-bold mb-1"><?php echo htmlspecialchars($vendor['business_name']); ?></h2>
                                            <p class="text-muted mb-1"><?php echo htmlspecialchars($vendor['name']); ?> · <?php echo htmlspecialchars($vendor['email']); ?></p>
                                            <p class="small mb-1"><?php echo htmlspecialchars($vendor['service_area'] ?? ''); ?></p>
                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($vendor['description'] ?? '')); ?></p>
                                            <small class="text-muted"><?php echo (int)$vendor['service_count']; ?> packages · Registered <?php echo htmlspecialchars($vendor['created_at']); ?></small>
                                        </div>
                                        <span class="badge <?php echo $vendor['verification_status'] === 'verified' ? 'bg-success' : ($vendor['verification_status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark'); ?> align-self-start">
                                            <?php echo htmlspecialchars(ucfirst($vendor['verification_status'])); ?>
                                        </span>
                                    </div>
                                    <form method="POST" class="d-flex flex-wrap gap-2 align-items-center mt-3">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                        <input type="hidden" name="vendor_id" value="<?php echo (int)$vendor['user_id']; ?>">
                                        <label class="visually-hidden" for="vendor-status-<?php echo (int)$vendor['user_id']; ?>">Verification status</label>
                                        <select id="vendor-status-<?php echo (int)$vendor['user_id']; ?>" name="verification_status" class="form-select form-select-sm w-auto">
                                            <?php foreach ($statuses as $status): ?>
                                                <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $vendor['verification_status'] === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($status)); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">Save status</button>
                                    </form>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
