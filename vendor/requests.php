<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('vendor');
require_once __DIR__ . '/../includes/db.php';

$vendor_id = (int)$_SESSION['user_id'];
$profile_statement = $conn->prepare('SELECT verification_status FROM vendor_profiles WHERE user_id = ?');
$profile_statement->bind_param('i', $vendor_id);
$profile_statement->execute();
$vendor_profile = $profile_statement->get_result()->fetch_assoc();
if (!$vendor_profile) {
    http_response_code(500);
    exit('Vendor profile is unavailable.');
}

$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $request_id_input = isset($_POST['request_id']) && is_string($_POST['request_id']) ? $_POST['request_id'] : '';
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    if (!ctype_digit($request_id_input) || !in_array($action, array('quote', 'accept', 'reject', 'complete'), true)) {
        $message = 'The booking update was invalid.';
        $message_type = 'danger';
    } elseif ($vendor_profile['verification_status'] !== 'verified') {
        $message = 'Your vendor account must be verified before managing bookings.';
        $message_type = 'danger';
    } else {
        $request_id = (int)$request_id_input;
        if ($action === 'quote') {
            $price_input = isset($_POST['quote_amount']) && is_string($_POST['quote_amount']) ? trim($_POST['quote_amount']) : '';
            $quote_message = isset($_POST['quote_message']) && is_string($_POST['quote_message']) ? trim($_POST['quote_message']) : '';
            if ($price_input === '' || !is_numeric($price_input) || !is_finite((float)$price_input) || (float)$price_input < 0 || (float)$price_input > 99999999.99 || strlen($quote_message) > 12000) {
                $message = 'Enter a valid quote amount and optional message.';
                $message_type = 'danger';
            } else {
                $quote_amount = (float)$price_input;
                $update = $conn->prepare(
                    "UPDATE service_requests
                     SET quote_amount = ?, quote_message = ?, booking_status = 'quoted',
                         quoted_at = NOW(), booking_updated_at = NOW()
                     WHERE id = ? AND vendor_id = ?
                       AND booking_status IN ('requested','quoted')"
                );
                $update->bind_param('dsii', $quote_amount, $quote_message, $request_id, $vendor_id);
                $update->execute();
                if ($update->affected_rows === 1) {
                    header('Location: requests.php?updated=quoted');
                    exit();
                }
                $message = 'The booking cannot be quoted; it may not belong to you or may have changed status.';
                $message_type = 'warning';
            }
        } elseif ($action === 'accept') {
            $update = $conn->prepare(
                "UPDATE service_requests SET booking_status = 'accepted', booking_updated_at = NOW()
                 WHERE id = ? AND vendor_id = ? AND booking_status = 'requested'"
            );
            $update->bind_param('ii', $request_id, $vendor_id);
            $update->execute();
            if ($update->affected_rows === 1) {
                header('Location: requests.php?updated=accepted');
                exit();
            }
            $message = 'The request cannot be accepted; it may not belong to you or may have changed status.';
            $message_type = 'warning';
        } elseif ($action === 'reject') {
            $update = $conn->prepare(
                "UPDATE service_requests SET booking_status = 'rejected', booking_updated_at = NOW()
                 WHERE id = ? AND vendor_id = ? AND booking_status IN ('requested','quoted')"
            );
            $update->bind_param('ii', $request_id, $vendor_id);
            $update->execute();
            if ($update->affected_rows === 1) {
                header('Location: requests.php?updated=rejected');
                exit();
            }
            $message = 'The booking cannot be rejected; it may not belong to you or may have changed status.';
            $message_type = 'warning';
        } else {
            $update = $conn->prepare(
                "UPDATE service_requests SET booking_status = 'completed', booking_updated_at = NOW()
                 WHERE id = ? AND vendor_id = ? AND booking_status = 'accepted'"
            );
            $update->bind_param('ii', $request_id, $vendor_id);
            $update->execute();
            if ($update->affected_rows === 1) {
                header('Location: requests.php?updated=completed');
                exit();
            }
            $message = 'Only an accepted booking can be marked completed.';
            $message_type = 'warning';
        }
    }
}

$requests_statement = $conn->prepare(
    'SELECT service_requests.id, service_requests.event_type, service_requests.event_date,
            service_requests.venue, service_requests.guests, service_requests.additional_requirements,
            service_requests.booking_status, service_requests.quote_amount, service_requests.quote_message,
            service_requests.quoted_at, users.name AS customer_name, users.email AS customer_email,
            users.role AS requester_role, organizer_profiles.organization_name,
            vendor_services.service_name, vendor_services.package_name
     FROM service_requests
     INNER JOIN users ON users.id = service_requests.user_id
     LEFT JOIN organizer_profiles ON organizer_profiles.user_id = users.id
     LEFT JOIN vendor_services ON vendor_services.id = service_requests.vendor_service_id
     WHERE service_requests.vendor_id = ?
     ORDER BY service_requests.id DESC'
);
$requests_statement->bind_param('i', $vendor_id);
$requests_statement->execute();
$requests = $requests_statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Bookings - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <main class="container py-5">
        <section class="card border-0 shadow-sm rounded-4 p-4">
            <h1 class="h3 fw-bold mb-3">Bookings and Quotes</h1>
            <?php if (isset($_GET['updated'])): ?><div class="alert alert-success" role="status">Booking updated: <?php echo htmlspecialchars($_GET['updated']); ?>.</div><?php endif; ?>
            <?php if ($message !== ''): ?><div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($vendor_profile['verification_status'] !== 'verified'): ?>
                <p class="text-muted mb-0">Bookings become available after admin verification.</p>
            <?php elseif ($requests->num_rows === 0): ?>
                <p class="text-muted mb-0">No vendor requests have been assigned to your services.</p>
            <?php else: ?>
                <div class="vstack gap-3">
                    <?php while ($request = $requests->fetch_assoc()): ?>
                        <article class="border rounded-3 p-3 p-md-4">
                            <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                <div>
                                    <h2 class="h5 fw-bold mb-1"><?php echo htmlspecialchars($request['event_type']); ?> · <?php echo htmlspecialchars($request['event_date']); ?></h2>
                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($request['requester_role'] === 'organizer' && $request['organization_name'] ? $request['organization_name'] : $request['customer_name']); ?> · <?php echo htmlspecialchars(ucfirst($request['requester_role'])); ?> · <?php echo htmlspecialchars($request['customer_email']); ?></p>
                                </div>
                                <span class="badge bg-primary-subtle text-primary"><?php echo htmlspecialchars(ucfirst($request['booking_status'])); ?></span>
                            </div>
                            <p class="mb-1"><?php echo htmlspecialchars($request['venue']); ?> · <?php echo (int)$request['guests']; ?> guests</p>
                            <?php if ($request['service_name']): ?><p class="small text-muted"><?php echo htmlspecialchars($request['service_name'] . ' · ' . $request['package_name']); ?></p><?php endif; ?>
                            <?php if (!empty($request['additional_requirements'])): ?><p><?php echo nl2br(htmlspecialchars($request['additional_requirements'])); ?></p><?php endif; ?>
                            <?php if ($request['quote_amount'] !== null): ?><p class="mb-1"><strong>Current quote:</strong> <?php echo htmlspecialchars(number_format((float)$request['quote_amount'], 2)); ?></p><?php endif; ?>
                            <?php if (!empty($request['quote_message'])): ?><p class="text-muted"><?php echo nl2br(htmlspecialchars($request['quote_message'])); ?></p><?php endif; ?>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <a href="../service_conversation.php?id=<?php echo (int)$request['id']; ?>" class="btn btn-outline-secondary btn-sm">Messages</a>
                            </div>
                            <?php if (in_array($request['booking_status'], array('requested', 'quoted'), true)): ?>
                                <?php if ($request['booking_status'] === 'requested'): ?>
                                    <form method="POST" class="mb-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                        <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn btn-outline-success btn-sm">Accept request</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" class="row g-2 align-items-end">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                    <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                                    <input type="hidden" name="action" value="quote">
                                    <div class="col-md-3"><label class="form-label" for="quote-<?php echo (int)$request['id']; ?>">Quote amount</label><input id="quote-<?php echo (int)$request['id']; ?>" type="number" min="0" max="99999999.99" step="0.01" name="quote_amount" value="<?php echo $request['quote_amount'] === null ? '' : htmlspecialchars((string)$request['quote_amount']); ?>" class="form-control" required></div>
                                    <div class="col-md-7"><label class="form-label" for="quote-message-<?php echo (int)$request['id']; ?>">Quote message</label><input id="quote-message-<?php echo (int)$request['id']; ?>" type="text" name="quote_message" maxlength="3000" class="form-control" value="<?php echo htmlspecialchars($request['quote_message'] ?? ''); ?>"></div>
                                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Send quote</button></div>
                                </form>
                                <form method="POST" class="mt-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                    <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Reject request</button>
                                </form>
                            <?php elseif ($request['booking_status'] === 'accepted'): ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                    <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Mark completed</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
