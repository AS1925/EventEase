<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $organizer_id = isset($_POST['organizer_id']) && is_string($_POST['organizer_id']) && ctype_digit($_POST['organizer_id'])
        ? (int)$_POST['organizer_id']
        : 0;
    $status = isset($_POST['verification_status']) && is_string($_POST['verification_status'])
        ? $_POST['verification_status']
        : '';

    if ($organizer_id < 1 || !in_array($status, array('verified', 'rejected'), true)) {
        $message = 'Choose a valid organizer and verification action.';
        $message_type = 'danger';
    } else {
        $update = $conn->prepare(
            "UPDATE organizer_profiles
             INNER JOIN users ON users.id = organizer_profiles.user_id
             SET organizer_profiles.verification_status = ?,
                 organizer_profiles.verified_by = ?,
                 organizer_profiles.verified_at = CASE WHEN ? = 'verified' THEN NOW() ELSE NULL END
             WHERE organizer_profiles.user_id = ? AND users.role = 'organizer'"
        );
        $admin_id = (int)$_SESSION['user_id'];
        $update->bind_param('sisi', $status, $admin_id, $status, $organizer_id);
        $update->execute();
        if ($update->affected_rows > 0) {
            $message = 'Organizer verification status updated.';
        } else {
            $exists = $conn->prepare(
                "SELECT organizer_profiles.user_id
                 FROM organizer_profiles
                 INNER JOIN users ON users.id = organizer_profiles.user_id
                 WHERE organizer_profiles.user_id = ? AND users.role = 'organizer'"
            );
            $exists->bind_param('i', $organizer_id);
            $exists->execute();
            if ($exists->get_result()->num_rows > 0) {
                $message = 'Organizer verification status is unchanged.';
            } else {
                $message = 'That organizer account was not found.';
                $message_type = 'danger';
            }
        }
    }
}

$organizers = $conn->query(
    "SELECT users.id, users.name, users.email, organizer_profiles.organization_name,
            organizer_profiles.bio, organizer_profiles.website,
            organizer_profiles.verification_status, organizer_profiles.created_at
     FROM users
     INNER JOIN organizer_profiles ON organizer_profiles.user_id = users.id
     WHERE users.role = 'organizer'
     ORDER BY FIELD(organizer_profiles.verification_status, 'pending', 'rejected', 'verified'),
              organizer_profiles.created_at ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Organizers - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3"><?php include '../includes/admin_sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h1 class="h3 fw-bold mb-3">Organizer Verification</h1>
                    <?php if ($message !== ''): ?><div class="alert alert-<?php echo $message_type; ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                    <?php if ($organizers->num_rows === 0): ?>
                        <p class="text-muted mb-0">There are no organizer accounts to review.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle modern-table">
                                <thead><tr><th>Organizer</th><th>Organization</th><th>Profile</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php while ($organizer = $organizers->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($organizer['name']); ?><br><small><?php echo htmlspecialchars($organizer['email']); ?></small></td>
                                            <td><?php echo htmlspecialchars($organizer['organization_name']); ?></td>
                                            <td>
                                                <?php echo nl2br(htmlspecialchars($organizer['bio'] ?? '')); ?>
                                                <?php if (!empty($organizer['website'])): ?><br><a href="<?php echo htmlspecialchars($organizer['website']); ?>" rel="noopener noreferrer"><?php echo htmlspecialchars($organizer['website']); ?></a><?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(ucfirst($organizer['verification_status'])); ?></td>
                                            <td>
                                                <form method="POST" class="d-flex gap-2">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                                    <input type="hidden" name="organizer_id" value="<?php echo (int)$organizer['id']; ?>">
                                                    <button type="submit" name="verification_status" value="verified" class="btn btn-success btn-sm">Verify</button>
                                                    <button type="submit" name="verification_status" value="rejected" class="btn btn-outline-danger btn-sm">Reject</button>
                                                </form>
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
