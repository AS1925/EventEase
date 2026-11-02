<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("admin");
include("../includes/db.php");

$statuses = array("Pending", "Published", "Rejected");
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id'], $_POST['status']) && is_string($_POST['review_id']) && ctype_digit($_POST['review_id']) && in_array($_POST['status'], $statuses, true)) {
    $review_id = (int)$_POST['review_id'];
    $status = $_POST['status'];
    $update = $conn->prepare("UPDATE event_reviews SET status = ? WHERE id = ?");
    $update->bind_param("si", $status, $review_id);
    $update->execute();
    header("Location: manage_reviews.php?updated=1");
    exit();
}

$reviews = $conn->query("SELECT event_reviews.id, event_reviews.rating, event_reviews.review_text, event_reviews.status, event_reviews.created_at, users.name, events.event_name FROM event_reviews INNER JOIN users ON users.id = event_reviews.user_id INNER JOIN events ON events.id = event_reviews.event_id ORDER BY FIELD(event_reviews.status, 'Pending', 'Published', 'Rejected'), event_reviews.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reviews - EventEase Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3"><?php include '../includes/admin_sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="mb-4">
                        <h1 class="h3 fw-bold mb-1">Event Reviews</h1>
                        <p class="text-muted mb-0">Approve reviews before they appear on the public homepage and reports.</p>
                    </div>
                    <?php if (isset($_GET['updated'])): ?><div class="alert alert-success" role="status">Review status updated.</div><?php endif; ?>
                    <?php if ($reviews->num_rows === 0): ?>
                        <div class="text-center py-5">
                            <i class="fa-solid fa-star text-primary fs-2 mb-3"></i>
                            <h2 class="h5 fw-semibold">No reviews submitted</h2>
                            <p class="text-muted mb-0">User reviews will appear here for moderation.</p>
                        </div>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php while ($review = $reviews->fetch_assoc()): ?>
                                <article class="border rounded-3 p-3 p-md-4">
                                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                        <div>
                                            <h2 class="h5 fw-bold mb-1"><?php echo htmlspecialchars($review['event_name']); ?></h2>
                                            <p class="text-muted small mb-0">By <?php echo htmlspecialchars($review['name']); ?> · <?php echo htmlspecialchars($review['created_at']); ?></p>
                                        </div>
                                        <span class="badge <?php echo $review['status'] === 'Published' ? 'bg-success' : ($review['status'] === 'Rejected' ? 'bg-danger' : 'bg-warning text-dark'); ?> align-self-start"><?php echo htmlspecialchars($review['status']); ?></span>
                                    </div>
                                    <p class="text-warning mb-2" aria-label="<?php echo (int)$review['rating']; ?> out of 5 stars"><?php echo str_repeat('★', (int)$review['rating']); ?><?php echo str_repeat('☆', 5 - (int)$review['rating']); ?></p>
                                    <p class="mb-3"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                    <form method="POST" class="d-flex flex-wrap gap-2 align-items-center">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                        <input type="hidden" name="review_id" value="<?php echo (int)$review['id']; ?>">
                                        <label class="visually-hidden" for="review-status-<?php echo (int)$review['id']; ?>">Review status</label>
                                        <select id="review-status-<?php echo (int)$review['id']; ?>" name="status" class="form-select form-select-sm w-auto">
                                            <?php foreach ($statuses as $status): ?><option value="<?php echo htmlspecialchars($status); ?>" <?php echo $review['status'] === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option><?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">Save Status</button>
                                    </form>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
