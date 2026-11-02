<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('organizer');
require_once __DIR__ . '/../includes/db.php';

$organizer_id = (int)$_SESSION['user_id'];
$profile_statement = $conn->prepare(
    'SELECT verification_status FROM organizer_profiles WHERE user_id = ?'
);
$profile_statement->bind_param('i', $organizer_id);
$profile_statement->execute();
$organizer_profile = $profile_statement->get_result()->fetch_assoc();
if (!$organizer_profile || $organizer_profile['verification_status'] !== 'verified') {
    http_response_code(403);
    exit('A verified organizer account is required to request vendor services.');
}

$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $request_id_input = isset($_POST['request_id']) && is_string($_POST['request_id'])
        ? $_POST['request_id']
        : '';

    if ($action === 'create') {
        $event_id_input = isset($_POST['event_id']) && is_string($_POST['event_id'])
            ? $_POST['event_id']
            : '';
        $service_id_input = isset($_POST['service_id']) && is_string($_POST['service_id'])
            ? $_POST['service_id']
            : '';
        $guests = filter_var(
            isset($_POST['guests']) && is_string($_POST['guests']) ? $_POST['guests'] : '',
            FILTER_VALIDATE_INT
        );
        $requirements = isset($_POST['additional_requirements']) && is_string($_POST['additional_requirements'])
            ? trim($_POST['additional_requirements'])
            : '';

        if (
            !ctype_digit($event_id_input)
            || (int)$event_id_input < 1
            || !ctype_digit($service_id_input)
            || (int)$service_id_input < 1
            || $guests === false
            || $guests < 1
            || $guests > 100000
            || strlen($requirements) > 12000
        ) {
            $message = 'Choose one of your events and a valid vendor package, then enter a guest count from 1 to 100,000.';
            $message_type = 'danger';
        } else {
            $event_id = (int)$event_id_input;
            $service_id = (int)$service_id_input;
            $event_statement = $conn->prepare(
                'SELECT event_name, category, event_type, event_date, venue, max_participants
                 FROM events
                 WHERE id = ? AND organizer_id = ? AND event_date >= CURDATE()
                 LIMIT 1'
            );
            $event_statement->bind_param('ii', $event_id, $organizer_id);
            $event_statement->execute();
            $event = $event_statement->get_result()->fetch_assoc();

            $service_statement = $conn->prepare(
                "SELECT vendor_services.vendor_id, vendor_services.category,
                        vendor_services.service_name, vendor_services.package_name
                 FROM vendor_services
                 INNER JOIN vendor_profiles ON vendor_profiles.user_id = vendor_services.vendor_id
                 WHERE vendor_services.id = ? AND vendor_services.is_active = 1
                   AND vendor_profiles.verification_status = 'verified'
                 LIMIT 1"
            );
            $service_statement->bind_param('i', $service_id);
            $service_statement->execute();
            $service = $service_statement->get_result()->fetch_assoc();

            if (!$event) {
                $message = 'That event is not an upcoming event owned by your account.';
                $message_type = 'danger';
            } elseif (!$service) {
                $message = 'That vendor package is no longer available.';
                $message_type = 'danger';
            } else {
                try {
                    $event_type = $event['category'] ?: $event['event_type'];
                    $vendor_id = (int)$service['vendor_id'];
                    $duplicate_statement = $conn->prepare(
                        "SELECT id FROM service_requests
                         WHERE user_id = ? AND vendor_service_id = ?
                           AND event_type = ? AND event_date = ? AND venue = ?
                           AND booking_status IN ('requested', 'quoted', 'accepted')
                         LIMIT 1"
                    );
                    $duplicate_statement->bind_param(
                        'iisss',
                        $organizer_id,
                        $service_id,
                        $event_type,
                        $event['event_date'],
                        $event['venue']
                    );
                    $duplicate_statement->execute();
                    if ($duplicate_statement->get_result()->num_rows > 0) {
                        $message = 'You already have an active request for this package and event.';
                        $message_type = 'warning';
                    } else {
                        $insert = $conn->prepare(
                            "INSERT INTO service_requests
                                (user_id, event_type, event_date, venue, guests, decoration,
                                 photography, catering, dj, additional_requirements,
                                 vendor_id, vendor_service_id)
                             VALUES (?, ?, ?, ?, ?, 'No', 'No', 'No', 'No', ?, ?, ?)"
                        );
                        $insert->bind_param(
                            'isssisii',
                            $organizer_id,
                            $event_type,
                            $event['event_date'],
                            $event['venue'],
                            $guests,
                            $requirements,
                            $vendor_id,
                            $service_id
                        );
                        $insert->execute();
                        header('Location: vendor_requests.php?submitted=1');
                        exit();
                    }
                } catch (mysqli_sql_exception $exception) {
                    error_log('Organizer vendor request failed with MySQL error code ' . $exception->getCode() . '.');
                    $message = 'The vendor request could not be submitted. Please try again.';
                    $message_type = 'danger';
                }
            }
        }
    } elseif (
        ctype_digit($request_id_input)
        && (int)$request_id_input > 0
        && in_array($action, array('accept', 'reject', 'cancel'), true)
    ) {
        $request_id = (int)$request_id_input;
        if ($action === 'accept' || $action === 'reject') {
            $next_status = $action === 'accept' ? 'accepted' : 'rejected';
            $update = $conn->prepare(
                'UPDATE service_requests
                 SET booking_status = ?, booking_updated_at = NOW()
                 WHERE id = ? AND user_id = ? AND booking_status = \'quoted\''
            );
            $update->bind_param('sii', $next_status, $request_id, $organizer_id);
        } else {
            $update = $conn->prepare(
                "UPDATE service_requests
                 SET booking_status = 'cancelled', booking_updated_at = NOW()
                 WHERE id = ? AND user_id = ? AND booking_status IN ('requested', 'quoted')"
            );
            $update->bind_param('ii', $request_id, $organizer_id);
        }
        $update->execute();
        if ($update->affected_rows === 1) {
            header('Location: vendor_requests.php?updated=' . rawurlencode($action));
            exit();
        }
        $message = 'The request could not be updated. Its status may have changed.';
        $message_type = 'warning';
    } else {
        $message = 'The vendor request action was invalid.';
        $message_type = 'danger';
    }
}

$events_statement = $conn->prepare(
    'SELECT id, event_name, event_date, venue, max_participants
     FROM events
     WHERE organizer_id = ? AND event_date >= CURDATE()
     ORDER BY event_date, id'
);
$events_statement->bind_param('i', $organizer_id);
$events_statement->execute();
$events = $events_statement->get_result();

$services_statement = $conn->prepare(
    "SELECT vendor_services.id, vendor_services.category, vendor_services.service_name,
            vendor_services.package_name, vendor_services.base_price,
            vendor_profiles.business_name
     FROM vendor_services
     INNER JOIN vendor_profiles ON vendor_profiles.user_id = vendor_services.vendor_id
     WHERE vendor_services.is_active = 1
       AND vendor_profiles.verification_status = 'verified'
     ORDER BY vendor_services.category, vendor_profiles.business_name,
              vendor_services.package_name"
);
$services_statement->execute();
$services = $services_statement->get_result();
$selected_service_id = isset($_GET['service_id']) && is_string($_GET['service_id'])
    && ctype_digit($_GET['service_id'])
    ? (int)$_GET['service_id']
    : 0;

$requests_statement = $conn->prepare(
    'SELECT service_requests.id, service_requests.event_type, service_requests.event_date,
            service_requests.venue, service_requests.guests,
            service_requests.additional_requirements, service_requests.booking_status,
            service_requests.quote_amount, service_requests.quote_message,
            vendor_profiles.business_name, vendor_services.service_name,
            vendor_services.package_name
     FROM service_requests
     LEFT JOIN vendor_profiles ON vendor_profiles.user_id = service_requests.vendor_id
     LEFT JOIN vendor_services ON vendor_services.id = service_requests.vendor_service_id
     WHERE service_requests.user_id = ?
     ORDER BY service_requests.id DESC'
);
$requests_statement->bind_param('i', $organizer_id);
$requests_statement->execute();
$requests = $requests_statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Requests - EventEase Organizer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <main class="container py-5">
        <div class="row g-4">
            <aside class="col-lg-3"><?php include '../includes/organizer_sidebar.php'; ?></aside>
            <div class="col-lg-9">
                <section class="card border-0 shadow-sm rounded-4 p-4 p-lg-5 mb-4">
                    <h1 class="h3 fw-bold mb-2">Request a vendor</h1>
                    <p class="text-muted">Choose one of your upcoming events and a verified vendor package. Enter the expected guest count to help the vendor respond accurately.</p>
                    <?php if (isset($_GET['submitted'])): ?><div class="alert alert-success" role="status">Your request was sent to the vendor.</div><?php endif; ?>
                    <?php if (isset($_GET['updated'])): ?><div class="alert alert-success" role="status">Request updated: <?php echo htmlspecialchars($_GET['updated']); ?>.</div><?php endif; ?>
                    <?php if ($message !== ''): ?><div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                    <?php if ($events->num_rows === 0): ?>
                        <div class="alert alert-info mb-0">Create an upcoming event before requesting a vendor.</div>
                    <?php elseif ($services->num_rows === 0): ?>
                        <div class="alert alert-info mb-0">There are no active packages from verified vendors right now.</div>
                    <?php else: ?>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                            <input type="hidden" name="action" value="create">
                            <div class="col-12">
                                <label class="form-label" for="event-id">Your event</label>
                                <select id="event-id" name="event_id" class="form-select" required>
                                    <option value="">Select an upcoming event</option>
                                    <?php while ($event = $events->fetch_assoc()): ?>
                                        <option value="<?php echo (int)$event['id']; ?>">
                                            <?php echo htmlspecialchars($event['event_name'] . ' · ' . $event['event_date'] . ' · ' . $event['venue']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="service-id">Verified vendor package</label>
                                <select id="service-id" name="service_id" class="form-select" required>
                                    <option value="">Select a package</option>
                                    <?php while ($service = $services->fetch_assoc()): ?>
                                        <option value="<?php echo (int)$service['id']; ?>" <?php echo $selected_service_id === (int)$service['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($service['category'] . ' · ' . $service['business_name'] . ' · ' . $service['service_name'] . ' — ' . $service['package_name'] . ' (' . number_format((float)$service['base_price'], 2) . ')'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="guest-count">Expected guests</label>
                                <input id="guest-count" type="number" name="guests" class="form-control" min="1" max="100000" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="requirements">Requirements for the vendor</label>
                                <textarea id="requirements" name="additional_requirements" class="form-control" rows="4" maxlength="3000"></textarea>
                            </div>
                            <div class="col-12"><button type="submit" class="btn btn-primary">Send vendor request</button></div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="card border-0 shadow-sm rounded-4 p-4">
                    <h2 class="h4 fw-bold mb-3">Your vendor requests</h2>
                    <?php if ($requests->num_rows === 0): ?>
                        <p class="text-muted mb-0">You have not sent any vendor requests yet.</p>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php while ($request = $requests->fetch_assoc()): ?>
                                <article class="border rounded-3 p-3 p-md-4">
                                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                        <div>
                                            <h3 class="h5 fw-bold mb-1"><?php echo htmlspecialchars($request['event_type'] . ' · ' . $request['event_date']); ?></h3>
                                            <p class="text-muted mb-0"><?php echo htmlspecialchars($request['venue'] . ' · ' . (int)$request['guests'] . ' guests'); ?></p>
                                        </div>
                                        <span class="badge bg-primary-subtle text-primary"><?php echo htmlspecialchars(ucfirst($request['booking_status'])); ?></span>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars(($request['business_name'] ?? 'Vendor unavailable') . ' · ' . ($request['package_name'] ?? $request['service_name'] ?? 'Package unavailable')); ?></p>
                                    <?php if (!empty($request['additional_requirements'])): ?><p class="text-muted"><?php echo nl2br(htmlspecialchars($request['additional_requirements'])); ?></p><?php endif; ?>
                                    <?php if ($request['quote_amount'] !== null): ?><p class="mb-1"><strong>Vendor quote:</strong> <?php echo htmlspecialchars(number_format((float)$request['quote_amount'], 2)); ?></p><?php endif; ?>
                                    <?php if (!empty($request['quote_message'])): ?><p class="text-muted"><?php echo nl2br(htmlspecialchars($request['quote_message'])); ?></p><?php endif; ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php if ($request['business_name'] !== null && !in_array($request['booking_status'], array('cancelled', 'rejected'), true)): ?>
                                            <a href="../service_conversation.php?id=<?php echo (int)$request['id']; ?>" class="btn btn-outline-secondary btn-sm">Messages</a>
                                        <?php endif; ?>
                                        <?php if ($request['booking_status'] === 'quoted'): ?>
                                            <?php foreach (array('accept' => 'Accept quote', 'reject' => 'Reject quote') as $action => $label): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                                    <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                                                    <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
                                                    <button type="submit" class="btn <?php echo $action === 'accept' ? 'btn-primary' : 'btn-outline-danger'; ?> btn-sm"><?php echo htmlspecialchars($label); ?></button>
                                                </form>
                                            <?php endforeach; ?>
                                        <?php elseif ($request['booking_status'] === 'requested'): ?>
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                                <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Cancel request</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
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
