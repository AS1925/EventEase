<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('vendor');
require_once __DIR__ . '/../includes/db.php';

$vendor_id = (int)$_SESSION['user_id'];
$reviews_statement = $conn->prepare(
    "SELECT vendor_reviews.rating, vendor_reviews.review_text, vendor_reviews.created_at,
            customers.name AS customer_name, service_requests.event_type
     FROM vendor_reviews
     INNER JOIN users AS customers ON customers.id = vendor_reviews.customer_id
     INNER JOIN service_requests ON service_requests.id = vendor_reviews.request_id
     WHERE vendor_reviews.vendor_id = ? AND vendor_reviews.status = 'published'
     ORDER BY vendor_reviews.created_at DESC"
);
$reviews_statement->bind_param('i', $vendor_id);
$reviews_statement->execute();
$reviews = $reviews_statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Reviews - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <section class="card border-0 shadow-sm rounded-4 p-4">
                    <h1 class="h3 fw-bold mb-3">Published Reviews</h1>
                    <?php if ($reviews->num_rows === 0): ?>
                        <p class="text-muted mb-0">No published reviews for your business yet.</p>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php while ($review = $reviews->fetch_assoc()): ?>
                                <article class="border rounded-3 p-3">
                                    <div class="d-flex justify-content-between flex-wrap gap-2">
                                        <strong><?php echo htmlspecialchars($review['event_type']); ?> · <?php echo htmlspecialchars($review['customer_name']); ?></strong>
                                        <span class="text-warning"><?php echo str_repeat('★', (int)$review['rating']); ?><?php echo str_repeat('☆', 5 - (int)$review['rating']); ?></span>
                                    </div>
                                    <p class="mt-2 mb-1"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                    <small class="text-muted"><?php echo htmlspecialchars($review['created_at']); ?></small>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
