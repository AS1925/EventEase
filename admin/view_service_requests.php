<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("admin");
include("../includes/db.php");

$booking_statuses = array('requested', 'quoted', 'accepted', 'rejected', 'cancelled', 'completed');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $request_id = isset($_POST['request_id']) && is_string($_POST['request_id']) ? $_POST['request_id'] : '';
    $booking_status = isset($_POST['booking_status']) && is_string($_POST['booking_status'])
        ? $_POST['booking_status']
        : '';
    if (ctype_digit($request_id) && in_array($booking_status, $booking_statuses, true)) {
        $update = $conn->prepare(
            'UPDATE service_requests SET booking_status = ?, booking_updated_at = NOW() WHERE id = ?'
        );
        $request_id_value = (int)$request_id;
        $update->bind_param('si', $booking_status, $request_id_value);
        $update->execute();
        header('Location: view_service_requests.php?updated=1');
        exit();
    }
    http_response_code(400);
    exit('Invalid booking update.');
}

$query = $conn->query(
    'SELECT service_requests.id, users.name, service_requests.event_type,
            service_requests.event_date, service_requests.venue, service_requests.guests,
            service_requests.status, service_requests.booking_status,
            service_requests.quote_amount, vendor_profiles.business_name
     FROM service_requests
     INNER JOIN users ON service_requests.user_id = users.id
     LEFT JOIN vendor_profiles ON vendor_profiles.user_id = service_requests.vendor_id
     ORDER BY service_requests.id DESC'
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <title>Service Requests</title>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3">
                <?php include '../includes/admin_sidebar.php'; ?>
            </div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h3 class="fw-bold mb-3">Service Requests</h3>
                    <?php if (isset($_GET['updated'])): ?><div class="alert alert-success" role="status">Booking status updated.</div><?php endif; ?>
                    <div class="table-responsive">
                        <table class="table align-middle modern-table">
                            <thead><tr><th>S.No</th><th>Customer</th><th>Request</th><th>Date</th><th>Vendor</th><th>Quote</th><th>Booking</th><th>Admin status</th></tr></thead>
                            <tbody>
                                <?php $count=1; while($row = $query->fetch_assoc()){ ?>
                                <tr>
                                    <td><?php echo $count++; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['event_type']); ?> · <?php echo (int)$row['guests']; ?> guests</td>
                                    <td><?php echo htmlspecialchars($row['event_date']); ?></td>
                                    <td><?php echo $row['business_name'] === null ? 'Unassigned' : htmlspecialchars($row['business_name']); ?></td>
                                    <td><?php echo $row['quote_amount'] === null ? '—' : htmlspecialchars(number_format((float)$row['quote_amount'], 2)); ?></td>
                                    <td>
                                        <form method="POST" class="d-flex gap-2">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int)$row['id']; ?>">
                                            <label class="visually-hidden" for="booking-status-<?php echo (int)$row['id']; ?>">Booking status</label>
                                            <select id="booking-status-<?php echo (int)$row['id']; ?>" name="booking_status" class="form-select form-select-sm">
                                                <?php foreach ($booking_statuses as $status): ?><option value="<?php echo htmlspecialchars($status); ?>" <?php echo $row['booking_status'] === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($status)); ?></option><?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-outline-primary btn-sm">Save</button>
                                        </form>
                                    </td>
                                    <td><span class="badge bg-soft-primary text-primary"><?php echo htmlspecialchars($row['status']); ?></span><br><a href="update_request_status.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-link btn-sm px-0">Admin status</a></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>