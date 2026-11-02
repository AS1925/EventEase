<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('user');
require_once __DIR__ . '/../includes/db.php';

$request_id_input = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['request_id'] ?? '')
    : ($_GET['request_id'] ?? '');
if (!is_string($request_id_input) || !ctype_digit($request_id_input)) {
    header('Location: my_requests.php');
    exit();
}
$request_id = (int)$request_id_input;
$user_id = (int)$_SESSION['user_id'];
$booking_statement = $conn->prepare(
    "SELECT service_requests.id, service_requests.event_type,
            vendor_profiles.business_name, service_requests.vendor_id
     FROM service_requests
     INNER JOIN vendor_profiles ON vendor_profiles.user_id = service_requests.vendor_id
     WHERE service_requests.id = ? AND service_requests.user_id = ?
       AND service_requests.booking_status = 'completed'
     LIMIT 1"
);
$booking_statement->bind_param('ii', $request_id, $user_id);
$booking_statement->execute();
$booking = $booking_statement->get_result()->fetch_assoc();
if (!$booking) {
    http_response_code(404);
    exit('Completed vendor booking not found.');
}

$existing_statement = $conn->prepare(
    'SELECT rating, review_text, status FROM vendor_reviews WHERE request_id = ? AND customer_id = ?'
);
$existing_statement->bind_param('ii', $request_id, $user_id);
$existing_statement->execute();
$existing_review = $existing_statement->get_result()->fetch_assoc();
$message = '';
$message_type = 'success';
$rating = $existing_review ? (int)$existing_review['rating'] : 5;
$review_text = $existing_review ? $existing_review['review_text'] : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $rating_input = isset($_POST['rating']) && is_string($_POST['rating']) ? $_POST['rating'] : '';
    $review_text = isset($_POST['review_text']) && is_string($_POST['review_text']) ? trim($_POST['review_text']) : '';
    $rating = ctype_digit($rating_input) ? (int)$rating_input : 0;
    if ($rating < 1 || $rating > 5 || $review_text === '' || strlen($review_text) > 12000) {
        $message = 'Choose a rating from 1 to 5 and enter a review of up to 3,000 characters.';
        $message_type = 'danger';
    } elseif ($existing_review) {
        $message = 'A review has already been submitted for this completed booking.';
        $message_type = 'warning';
    } else {
        $vendor_id = (int)$booking['vendor_id'];
        $insert = $conn->prepare(
            "INSERT INTO vendor_reviews (request_id, vendor_id, customer_id, rating, review_text)
             VALUES (?, ?, ?, ?, ?)"
        );
        $insert->bind_param('iiiis', $request_id, $vendor_id, $user_id, $rating, $review_text);
        $insert->execute();
        header('Location: my_requests.php?review_submitted=1');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Vendor - EventEase</title>
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
                    <h1 class="h3 fw-bold mb-2">Review <?php echo htmlspecialchars($booking['business_name']); ?></h1>
                    <p class="text-muted"><?php echo htmlspecialchars($booking['event_type']); ?> booking</p>
                    <?php if ($message !== ''): ?><div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                    <?php if ($existing_review): ?>
                        <p>Your review is <?php echo htmlspecialchars($existing_review['status']); ?> and cannot be submitted again.</p>
                    <?php else: ?>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                            <input type="hidden" name="request_id" value="<?php echo (int)$request_id; ?>">
                            <div class="col-md-5">
                                <label class="form-label" for="rating">Rating</label>
                                <select id="rating" name="rating" class="form-select" required>
                                    <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                                        <option value="<?php echo $stars; ?>" <?php echo $rating === $stars ? 'selected' : ''; ?>><?php echo $stars; ?> of 5 stars</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="review-text">Review</label>
                                <textarea id="review-text" name="review_text" class="form-control" rows="5" maxlength="3000" required><?php echo htmlspecialchars($review_text); ?></textarea>
                            </div>
                            <div class="col-12"><button type="submit" class="btn btn-primary">Submit for moderation</button></div>
                        </form>
                    <?php endif; ?>
                    <a href="my_requests.php" class="btn btn-outline-secondary mt-3">Back to my requests</a>
                </section>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
