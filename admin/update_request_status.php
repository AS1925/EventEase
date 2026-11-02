<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("admin");
include("../includes/db.php");

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: view_service_requests.php");
    exit();
}

$id = (int)$_GET['id'];
$statuses = array("Pending", "Approved", "Completed", "Rejected");
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
}
if (isset($_POST['update']) && isset($_POST['status']) && in_array($_POST['status'], $statuses, true)) {
    $status = $_POST['status'];
    $update = $conn->prepare("UPDATE service_requests SET status = ? WHERE id = ?");
    $update->bind_param("si", $status, $id);
    if ($update->execute()) {
        header("Location: view_service_requests.php");
        exit();
    }
}

$statement = $conn->prepare("SELECT id, status FROM service_requests WHERE id = ?");
$statement->bind_param("i", $id);
$statement->execute();
$row = $statement->get_result()->fetch_assoc();
if (!$row) {
    header("Location: view_service_requests.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Request Status - EventEase</title>
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
                <?php include '../includes/admin_sidebar.php'; ?>
            </div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
                    <h1 class="h3 fw-bold mb-2">Update Request Status</h1>
                    <p class="text-muted mb-4">Change the status for this service request.</p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                        <div class="mb-3">
                            <label class="form-label" for="status">Status</label>
                            <select id="status" name="status" class="form-select" required>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $row['status'] === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="update" class="btn btn-primary">Save Status</button>
                        <a href="view_service_requests.php" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>