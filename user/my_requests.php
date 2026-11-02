<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("user");
include("../includes/db.php");

$user_id = (int)$_SESSION['user_id'];
$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $request_id_input = isset($_POST['request_id']) && is_string($_POST['request_id'])
        ? $_POST['request_id']
        : '';
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $transitions = array(
        'accept' => array('accepted', array('quoted')),
        'reject' => array('rejected', array('quoted')),
        'cancel' => array('cancelled', array('requested', 'quoted'))
    );
    if (!ctype_digit($request_id_input) || !isset($transitions[$action])) {
        $message = 'The booking update was invalid.';
        $message_type = 'danger';
    } else {
        $request_id = (int)$request_id_input;
        list($next_status, $allowed_current) = $transitions[$action];
        $update = $conn->prepare(
            "UPDATE service_requests
             SET booking_status = ?, booking_updated_at = NOW()
             WHERE id = ? AND user_id = ? AND booking_status IN (?, ?)"
        );
        $allowed_first = $allowed_current[0];
        $allowed_second = $allowed_current[count($allowed_current) - 1];
        $update->bind_param('siiss', $next_status, $request_id, $user_id, $allowed_first, $allowed_second);
        $update->execute();
        if ($update->affected_rows === 1) {
            header('Location: my_requests.php?updated=' . rawurlencode($next_status));
            exit();
        }
        $message = 'The booking could not be updated. Its status may have changed.';
        $message_type = 'warning';
    }
}

$statement = $conn->prepare(
    'SELECT service_requests.id, service_requests.event_type, service_requests.event_date,
            service_requests.venue, service_requests.guests, service_requests.status,
            service_requests.booking_status, service_requests.quote_amount,
            service_requests.quote_message, service_requests.vendor_id,
            vendor_profiles.business_name, vendor_services.service_name,
            vendor_services.package_name,
            vendor_reviews.status AS review_status
     FROM service_requests
     LEFT JOIN vendor_profiles ON vendor_profiles.user_id = service_requests.vendor_id
     LEFT JOIN vendor_services ON vendor_services.id = service_requests.vendor_service_id
     LEFT JOIN vendor_reviews ON vendor_reviews.request_id = service_requests.id
     WHERE service_requests.user_id = ?
     ORDER BY service_requests.id DESC'
);
$statement->bind_param("i", $user_id);
$statement->execute();
$requests = $statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Requests - EventEase</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="../assets/css/style.css">
	<link rel="icon" href="../events/logo.png" type="image/png">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
	<?php include '../includes/navbar.php'; ?>
	<div class="container py-5">
		<div class="row g-4">
			<div class="col-lg-3">
				<?php include '../includes/user_sidebar.php'; ?>
			</div>
			<div class="col-lg-9">
				<div class="card border-0 shadow-sm rounded-4 p-4">
					<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
						<div>
							<h1 class="h3 fw-bold mb-1">My Service Requests</h1>
							<p class="text-muted mb-0">Review the status of your event service requests.</p>
						</div>
						<?php if (isset($_GET['submitted'])): ?><div class="alert alert-success" role="status">Your service request was submitted.</div><?php endif; ?>
						<?php if (isset($_GET['updated'])): ?><div class="alert alert-success" role="status">Booking updated: <?php echo htmlspecialchars($_GET['updated']); ?>.</div><?php endif; ?>
						<?php if (isset($_GET['review_submitted'])): ?><div class="alert alert-success" role="status">Your vendor review was submitted for admin moderation.</div><?php endif; ?>
						<?php if ($message !== ''): ?><div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
						<a href="service_request.php" class="btn btn-primary">New Request</a>
					</div>
					<?php if ($requests->num_rows === 0): ?>
						<div class="text-center py-5">
							<i class="fa-solid fa-clipboard-list text-primary fs-2 mb-3"></i>
							<h2 class="h5 fw-semibold">No requests yet</h2>
							<p class="text-muted">Your service requests will appear here after you submit one.</p>
							<a href="service_request.php" class="btn btn-outline-primary">Request a Service</a>
						</div>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table align-middle modern-table">
								<thead><tr><th>Request</th><th>Date</th><th>Vendor / Package</th><th>Quote</th><th>Booking status</th><th>Actions</th></tr></thead>
								<tbody>
									<?php while ($request = $requests->fetch_assoc()): ?>
										<tr>
											<td><?php echo htmlspecialchars($request['event_type']); ?><br><small class="text-muted"><?php echo htmlspecialchars($request['venue']); ?> · <?php echo (int)$request['guests']; ?> guests</small></td>
											<td><?php echo htmlspecialchars($request['event_date']); ?></td>
											<td><?php echo $request['business_name'] === null ? 'General request' : htmlspecialchars($request['business_name'] . ' · ' . ($request['package_name'] ?: $request['service_name'])); ?></td>
											<td><?php echo $request['quote_amount'] === null ? '—' : htmlspecialchars(number_format((float)$request['quote_amount'], 2)); ?><?php if (!empty($request['quote_message'])): ?><br><small class="text-muted"><?php echo htmlspecialchars($request['quote_message']); ?></small><?php endif; ?></td>
											<td><span class="badge bg-soft-primary text-primary"><?php echo htmlspecialchars(ucfirst($request['booking_status'])); ?></span><br><small class="text-muted">Admin: <?php echo htmlspecialchars($request['status']); ?></small></td>
											<td>
												<div class="d-flex flex-wrap gap-2">
													<?php if ($request['vendor_id'] !== null && !in_array($request['booking_status'], array('cancelled', 'rejected'), true)): ?>
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
													<?php elseif (in_array($request['booking_status'], array('requested'), true)): ?>
														<form method="POST">
															<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
															<input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
															<input type="hidden" name="action" value="cancel">
															<button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>
														</form>
													<?php endif; ?>
													<?php if ($request['booking_status'] === 'completed' && $request['vendor_id'] !== null && $request['review_status'] === null): ?>
														<a href="vendor_review.php?request_id=<?php echo (int)$request['id']; ?>" class="btn btn-outline-primary btn-sm">Review vendor</a>
													<?php endif; ?>
												</div>
											</td>
										</tr>
									<?php endwhile; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<?php include '../includes/footer.php'; ?>
</body>
</html>
