<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('organizer');
require_once __DIR__ . '/../includes/db.php';

$organizer_id = (int)$_SESSION['user_id'];
$reviews_statement = $conn->prepare(
    'SELECT event_reviews.rating, event_reviews.review_text, event_reviews.status,
            event_reviews.created_at, users.name AS participant_name, events.event_name
     FROM event_reviews
     INNER JOIN events ON events.id = event_reviews.event_id
     INNER JOIN users ON users.id = event_reviews.user_id
     WHERE events.organizer_id = ?
     ORDER BY event_reviews.created_at DESC, event_reviews.id DESC'
);
$reviews_statement->bind_param('i', $organizer_id);
$reviews_statement->execute();
$reviews = $reviews_statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Reviews - EventEase Organizer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3"><?php include '../includes/organizer_sidebar.php'; ?></div>
            <main class="col-lg-9">
                <section class="card border-0 shadow-sm rounded-4 p-4">
                    <h1 class="h3 fw-bold mb-3">Reviews for Your Events</h1>
                    <?php if ($reviews->num_rows === 0): ?>
                        <p class="text-muted mb-0">No reviews have been submitted for your events.</p>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php while ($review = $reviews->fetch_assoc()): ?>
                                <article class="border rounded-3 p-3 p-md-4">
                                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                        <div>
                                            <h2 class="h5 fw-bold mb-1"><?php echo htmlspecialchars($review['event_name']); ?></h2>
                                            <small class="text-muted">By <?php echo htmlspecialchars($review['participant_name']); ?> · <?php echo htmlspecialchars($review['created_at']); ?></small>
                                        </div>
                                        <span class="badge <?php echo $review['status'] === 'Published' ? 'bg-success' : ($review['status'] === 'Rejected' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                            <?php echo htmlspecialchars($review['status']); ?>
                                        </span>
                                    </div>
                                    <p class="text-warning mb-2" aria-label="<?php echo (int)$review['rating']; ?> out of 5 stars">
                                        <?php echo str_repeat('★', (int)$review['rating']); ?><?php echo str_repeat('☆', 5 - (int)$review['rating']); ?>
                                    </p>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
