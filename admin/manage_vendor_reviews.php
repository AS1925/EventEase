<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

$statuses = array('pending', 'published', 'rejected');
$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $review_id_input = isset($_POST['review_id']) && is_string($_POST['review_id']) ? $_POST['review_id'] : '';
    $status = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
    if (!ctype_digit($review_id_input) || !in_array($status, $statuses, true)) {
        $message = 'The vendor review update was invalid.';
        $message_type = 'danger';
    } else {
        $review_id = (int)$review_id_input;
        $admin_id = (int)$_SESSION['user_id'];
        $update = $conn->prepare(
            'UPDATE vendor_reviews SET status = ?, moderated_by = ?, moderated_at = NOW()
             WHERE id = ?'
        );
        $update->bind_param('sii', $status, $admin_id, $review_id);
        $update->execute();
        header('Location: manage_vendor_reviews.php?updated=1');
        exit();
    }
}

$reviews = $conn->query(
    "SELECT vendor_reviews.id, vendor_reviews.rating, vendor_reviews.review_text,
            vendor_reviews.status, vendor_reviews.created_at,
            vendor_profiles.business_name, customers.name AS customer_name,
            service_requests.event_type, service_requests.event_date
     FROM vendor_reviews
     INNER JOIN vendor_profiles ON vendor_profiles.user_id = vendor_reviews.vendor_id
     INNER JOIN users AS customers ON customers.id = vendor_reviews.customer_id
     INNER JOIN service_requests ON service_requests.id = vendor_reviews.request_id
     ORDER BY FIELD(vendor_reviews.status, 'pending', 'published', 'rejected'),
              vendor_reviews.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderate Vendor Reviews - EventEase Admin</title>
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
                    <h1 class="h3 fw-bold mb-3">Vendor Reviews</h1>
                    <?php if (isset($_GET['updated'])): ?><div class="alert alert-success" role="status">Vendor review moderation updated.</div><?php endif; ?>
                    <?php if ($message !== ''): ?><div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                    <?php if ($reviews->num_rows === 0): ?>
                        <p class="text-muted mb-0">No vendor reviews have been submitted.</p>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php while ($review = $reviews->fetch_assoc()): ?>
                                <article class="border rounded-3 p-3 p-md-4">
                                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                        <div>
                                            <h2 class="h5 fw-bold mb-1"><?php echo htmlspecialchars($review['business_name']); ?></h2>
                                            <small class="text-muted">By <?php echo htmlspecialchars($review['customer_name']); ?> · <?php echo htmlspecialchars($review['event_type'] . ' · ' . $review['event_date']); ?></small>
                                        </div>
                                        <span class="badge <?php echo $review['status'] === 'published' ? 'bg-success' : ($review['status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark'); ?>"><?php echo htmlspecialchars(ucfirst($review['status'])); ?></span>
                                    </div>
                                    <p class="text-warning mb-2"><?php echo str_repeat('★', (int)$review['rating']); ?><?php echo str_repeat('☆', 5 - (int)$review['rating']); ?></p>
                                    <p><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                    <form method="POST" class="d-flex flex-wrap gap-2 align-items-center">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                        <input type="hidden" name="review_id" value="<?php echo (int)$review['id']; ?>">
                                        <label class="visually-hidden" for="vendor-review-status-<?php echo (int)$review['id']; ?>">Review status</label>
                                        <select id="vendor-review-status-<?php echo (int)$review['id']; ?>" name="status" class="form-select form-select-sm w-auto">
                                            <?php foreach ($statuses as $status): ?><option value="<?php echo htmlspecialchars($status); ?>" <?php echo $review['status'] === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($status)); ?></option><?php endforeach; ?>
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
