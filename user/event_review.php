<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("user");
include("../includes/db.php");

if (!isset($_GET['id']) || !is_string($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: my_events.php");
    exit();
}

$event_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];
$event_statement = $conn->prepare(
    "SELECT events.id, events.event_name, events.event_date
     FROM events
     WHERE events.id = ? AND events.event_date < CURDATE()
       AND EXISTS (
           SELECT 1 FROM registrations
           WHERE registrations.event_id = events.id AND registrations.user_id = ?
             AND registrations.registration_status = 'active'
             AND (
                 events.attendance_enabled = 0
                 OR EXISTS (
                     SELECT 1 FROM event_attendance
                     WHERE event_attendance.registration_id = registrations.id
                 )
             )
       )
     LIMIT 1"
);
$event_statement->bind_param("ii", $event_id, $user_id);
$event_statement->execute();
$event = $event_statement->get_result()->fetch_assoc();
if (!$event) {
    header("Location: my_events.php");
    exit();
}

$review_statement = $conn->prepare("SELECT rating, review_text, status FROM event_reviews WHERE event_id = ? AND user_id = ? LIMIT 1");
$review_statement->bind_param("ii", $event_id, $user_id);
$review_statement->execute();
$review = $review_statement->get_result()->fetch_assoc();
$message = "";
$message_type = "success";
$rating = $review ? (int)$review['rating'] : 5;
$review_text = $review ? $review['review_text'] : "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $rating = (int)($_POST['rating'] ?? 0);
    $review_text = trim($_POST['review_text'] ?? "");
    if ($rating < 1 || $rating > 5 || $review_text === "") {
        $message = "Choose a rating and enter your review.";
        $message_type = "danger";
    } else {
        $save = $conn->prepare("INSERT INTO event_reviews (event_id, user_id, rating, review_text, status) VALUES (?, ?, ?, ?, 'Pending') ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text), status = 'Pending'");
        $save->bind_param("iiis", $event_id, $user_id, $rating, $review_text);
        if ($save->execute()) {
            $message = "Your review was submitted and is waiting for admin approval.";
        } else {
            $message = "Your review could not be saved. Please try again.";
            $message_type = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Event - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3"><?php include '../includes/user_sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
                    <h1 class="h3 fw-bold mb-1">Review Event</h1>
                    <p class="text-muted mb-4"><?php echo htmlspecialchars($event['event_name']); ?> · <?php echo htmlspecialchars($event['event_date']); ?></p>
                    <?php if ($message !== ""): ?><div class="alert alert-<?php echo $message_type; ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                    <?php if ($review && $message === ""): ?><p class="text-muted">Current review status: <?php echo htmlspecialchars($review['status']); ?></p><?php endif; ?>
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                        <div class="col-md-5">
                            <label class="form-label" for="rating">Rating</label>
                            <select id="rating" name="rating" class="form-select" required>
                                <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                                    <option value="<?php echo $stars; ?>" <?php echo $rating === $stars ? 'selected' : ''; ?>><?php echo $stars; ?> of 5 stars</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="review-text">Your Review</label>
                            <textarea id="review-text" name="review_text" class="form-control" rows="5" maxlength="3000" required><?php echo htmlspecialchars($review_text); ?></textarea>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Submit Review</button>
                            <a href="my_events.php" class="btn btn-outline-secondary">Back to My Events</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
