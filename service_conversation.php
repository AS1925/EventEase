<?php
require_once __DIR__ . '/includes/session.php';
eventease_start_session();
require_once __DIR__ . '/includes/access_control.php';
require_role('user', 'organizer', 'vendor');
require_once __DIR__ . '/includes/db.php';

$request_id_input = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['request_id'] ?? '')
    : ($_GET['id'] ?? '');
if (!is_string($request_id_input) || !ctype_digit($request_id_input)) {
    http_response_code(404);
    exit('Booking not found.');
}
$request_id = (int)$request_id_input;
$user_id = (int)$_SESSION['user_id'];
$role = eventease_current_role();
$booking_statement = $conn->prepare(
    'SELECT service_requests.id, service_requests.user_id, service_requests.vendor_id,
            service_requests.event_type, service_requests.event_date, service_requests.venue,
            service_requests.booking_status, users.name AS customer_name,
            vendor_profiles.business_name
     FROM service_requests
     INNER JOIN users ON users.id = service_requests.user_id
     LEFT JOIN vendor_profiles ON vendor_profiles.user_id = service_requests.vendor_id
     WHERE service_requests.id = ?
     LIMIT 1'
);
$booking_statement->bind_param('i', $request_id);
$booking_statement->execute();
$booking = $booking_statement->get_result()->fetch_assoc();
$authorized = $booking && (
    ($role === 'customer' && (int)$booking['user_id'] === $user_id)
    || ($role === 'organizer' && (int)$booking['user_id'] === $user_id)
    || ($role === 'vendor' && (int)$booking['vendor_id'] === $user_id)
);
if (!$authorized) {
    http_response_code(404);
    exit('Booking not found.');
}

$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $message_text = isset($_POST['message_text']) && is_string($_POST['message_text'])
        ? trim($_POST['message_text'])
        : '';
    if ($message_text === '' || strlen($message_text) > 12000) {
        $message = 'Enter a message of up to 3,000 characters.';
        $message_type = 'danger';
    } else {
        $insert = $conn->prepare(
            'INSERT INTO service_messages (request_id, sender_id, message_text) VALUES (?, ?, ?)'
        );
        $insert->bind_param('iis', $request_id, $user_id, $message_text);
        $insert->execute();
        header('Location: service_conversation.php?id=' . $request_id . '&sent=1');
        exit();
    }
}

$messages_statement = $conn->prepare(
    'SELECT service_messages.message_text, service_messages.created_at,
            users.name AS sender_name, users.role AS sender_role
     FROM service_messages
     INNER JOIN users ON users.id = service_messages.sender_id
     WHERE service_messages.request_id = ?
     ORDER BY service_messages.created_at, service_messages.id'
);
$messages_statement->bind_param('i', $request_id);
$messages_statement->execute();
$messages = $messages_statement->get_result();
$back_link = $role === 'vendor'
    ? 'vendor/requests.php'
    : ($role === 'organizer' ? 'organizer/vendor_requests.php' : 'user/my_requests.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Messages - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="events/logo.png" type="image/png">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <section class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
                    <h1 class="h3 fw-bold mb-1">Booking conversation</h1>
                    <p class="text-muted"><?php echo htmlspecialchars($booking['event_type'] . ' · ' . $booking['event_date'] . ' · ' . $booking['venue']); ?></p>
                    <?php if (isset($_GET['sent'])): ?><div class="alert alert-success" role="status">Message sent.</div><?php endif; ?>
                    <?php if ($message !== ''): ?><div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                    <div class="vstack gap-3 mb-4">
                        <?php if ($messages->num_rows === 0): ?>
                            <p class="text-muted mb-0">No messages yet. Start the conversation about this booking.</p>
                        <?php endif; ?>
                        <?php while ($item = $messages->fetch_assoc()): ?>
                            <article class="border rounded-3 p-3">
                                <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                    <strong><?php echo htmlspecialchars($item['sender_name']); ?> <span class="text-muted fw-normal">(<?php echo htmlspecialchars(ucfirst($item['sender_role'])); ?>)</span></strong>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['created_at']); ?></small>
                                </div>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($item['message_text'])); ?></p>
                            </article>
                        <?php endwhile; ?>
                    </div>
                    <?php if (!in_array($booking['booking_status'], array('cancelled', 'rejected'), true)): ?>
                        <form method="POST" class="d-grid gap-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                            <input type="hidden" name="request_id" value="<?php echo (int)$request_id; ?>">
                            <div>
                                <label class="form-label" for="message-text">Message</label>
                                <textarea id="message-text" name="message_text" class="form-control" rows="4" maxlength="3000" required></textarea>
                            </div>
                            <div><button type="submit" class="btn btn-primary">Send message</button></div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-secondary">Messaging is closed for this booking.</div>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars($back_link); ?>" class="btn btn-outline-secondary mt-3">Back to bookings</a>
                </section>
            </div>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
