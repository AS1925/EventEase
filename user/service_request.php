<?php

require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("user");
include("../includes/db.php");

$msg="";
$msg_type = 'success';
$services = $conn->query(
    "SELECT vendor_services.id, vendor_services.category, vendor_services.service_name,
            vendor_services.package_name, vendor_services.base_price,
            vendor_profiles.business_name
     FROM vendor_services
     INNER JOIN vendor_profiles ON vendor_profiles.user_id = vendor_services.vendor_id
     WHERE vendor_services.is_active = 1
       AND vendor_profiles.verification_status = 'verified'
     ORDER BY vendor_services.category, vendor_profiles.business_name, vendor_services.package_name"
);
$selected_service_id = isset($_GET['service_id']) && is_string($_GET['service_id']) && ctype_digit($_GET['service_id'])
    ? (int)$_GET['service_id']
    : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
}

if(isset($_POST['submit']))
{
    $user_id=(int)$_SESSION['user_id'];

$event_type = isset($_POST['event_type']) && is_string($_POST['event_type']) ? trim($_POST['event_type']) : '';
$event_date = isset($_POST['event_date']) && is_string($_POST['event_date']) ? $_POST['event_date'] : '';
$venue = isset($_POST['venue']) && is_string($_POST['venue']) ? trim($_POST['venue']) : '';
$guests = filter_var($_POST['guests'] ?? null, FILTER_VALIDATE_INT);
$decoration = isset($_POST['decoration']) && is_string($_POST['decoration']) ? $_POST['decoration'] : 'No';
$photography = isset($_POST['photography']) && is_string($_POST['photography']) ? $_POST['photography'] : 'No';
$catering = isset($_POST['catering']) && is_string($_POST['catering']) ? $_POST['catering'] : 'No';
$dj = isset($_POST['dj']) && is_string($_POST['dj']) ? $_POST['dj'] : 'No';
$additional_requirements = isset($_POST['additional_requirements']) && is_string($_POST['additional_requirements'])
    ? trim($_POST['additional_requirements'])
    : '';
$vendor_service_input = isset($_POST['vendor_service_id']) && is_string($_POST['vendor_service_id'])
    ? $_POST['vendor_service_id']
    : '';
$vendor_id = null;
$vendor_service_id = null;

if (
    $event_type === ''
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)
    || !checkdate((int)substr($event_date, 5, 2), (int)substr($event_date, 8, 2), (int)substr($event_date, 0, 4))
    || $event_date < date('Y-m-d')
    || $venue === ''
    || $guests === false
    || $guests < 1
    || strlen($additional_requirements) > 12000
    || !in_array($decoration, array('Yes', 'No'), true)
    || !in_array($photography, array('Yes', 'No'), true)
    || !in_array($catering, array('Yes', 'No'), true)
    || !in_array($dj, array('Yes', 'No'), true)
) {
    $msg = 'Enter valid event, venue, guest, and service details.';
    $msg_type = 'danger';
} elseif ($vendor_service_input !== '' && (!ctype_digit($vendor_service_input) || (int)$vendor_service_input < 1)) {
    $msg = 'Choose a valid vendor package.';
    $msg_type = 'danger';
} else {
    if ($vendor_service_input !== '') {
        $vendor_service_id = (int)$vendor_service_input;
        $vendor_lookup = $conn->prepare(
            "SELECT vendor_services.vendor_id
             FROM vendor_services
             INNER JOIN vendor_profiles ON vendor_profiles.user_id = vendor_services.vendor_id
             WHERE vendor_services.id = ? AND vendor_services.is_active = 1
               AND vendor_profiles.verification_status = 'verified'"
        );
        $vendor_lookup->bind_param('i', $vendor_service_id);
        $vendor_lookup->execute();
        $available_service = $vendor_lookup->get_result()->fetch_assoc();
        if (!$available_service) {
            $msg = 'That vendor package is not currently available.';
            $msg_type = 'danger';
        } else {
            $vendor_id = (int)$available_service['vendor_id'];
        }
    }

    if ($msg === '') {
        $statement = $conn->prepare(
            "INSERT INTO service_requests
                (user_id, event_type, event_date, venue, guests, decoration, photography,
                 catering, dj, additional_requirements, vendor_id, vendor_service_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $statement->bind_param(
            'isssisssssii',
            $user_id,
            $event_type,
            $event_date,
            $venue,
            $guests,
            $decoration,
            $photography,
            $catering,
            $dj,
            $additional_requirements,
            $vendor_id,
            $vendor_service_id
        );
        if ($statement->execute()) {
            header('Location: my_requests.php?submitted=1');
            exit();
        }
    }
}
}
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
    <title>Service Request</title>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3">
                <?php include '../includes/user_sidebar.php'; ?>
            </div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
                    <div class="text-center text-md-start mb-4">
                        <h3 class="fw-bold mb-2">Event Service Request</h3>
                        <p class="text-muted mb-0">Tell us about your vision and we’ll help you plan it beautifully.</p>
                    </div>
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                        <div class="col-md-6">
                            <label class="form-label">Event Type</label>
                            <select name="event_type" class="form-select" required>
                                <option>Wedding</option>
                                <option>Birthday</option>
                                <option>Reception</option>
                                <option>Corporate</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Event Date</label>
                            <input type="date" name="event_date" min="<?php echo date('Y-m-d'); ?>" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Venue</label>
                            <input type="text" name="venue" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Number of Guests</label>
                            <input type="number" name="guests" min="1" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="vendor-service">Vendor package (optional)</label>
                            <select id="vendor-service" name="vendor_service_id" class="form-select">
                                <option value="">Send a general service request</option>
                                <?php while ($service = $services->fetch_assoc()): ?>
                                    <option value="<?php echo (int)$service['id']; ?>" <?php echo $selected_service_id === (int)$service['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($service['category'] . ' · ' . $service['business_name'] . ' · ' . $service['service_name'] . ' — ' . $service['package_name'] . ' ($' . number_format((float)$service['base_price'], 2) . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-text">Only packages from verified vendors are listed. A final quote can be discussed through your request.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Decoration</label>
                            <select name="decoration" class="form-select"><option>Yes</option><option>No</option></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Photography</label>
                            <select name="photography" class="form-select"><option>Yes</option><option>No</option></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Catering</label>
                            <select name="catering" class="form-select"><option>Yes</option><option>No</option></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">DJ</label>
                            <select name="dj" class="form-select"><option>Yes</option><option>No</option></select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Additional Requirements</label>
                            <textarea name="additional_requirements" class="form-control" rows="4"></textarea>
                        </div>
                        <?php if($msg != ""): ?><div class="col-12"><div class="alert alert-<?php echo htmlspecialchars($msg_type); ?> rounded-4 mb-0" role="alert"><?php echo htmlspecialchars($msg); ?></div></div><?php endif; ?>
                        <div class="col-12"><button type="submit" name="submit" class="btn btn-primary">Submit Request</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>