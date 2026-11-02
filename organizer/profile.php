<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('organizer');
require_once __DIR__ . '/../includes/db.php';

$user_id = (int)$_SESSION['user_id'];
$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $organization_name = isset($_POST['organization_name']) && is_string($_POST['organization_name'])
        ? trim($_POST['organization_name'])
        : '';
    $bio = isset($_POST['bio']) && is_string($_POST['bio']) ? trim($_POST['bio']) : '';
    $website = isset($_POST['website']) && is_string($_POST['website']) ? trim($_POST['website']) : '';
    $phone = isset($_POST['phone']) && is_string($_POST['phone']) ? trim($_POST['phone']) : '';
    $location = isset($_POST['location']) && is_string($_POST['location']) ? trim($_POST['location']) : '';
    $logo_upload = isset($_FILES['logo']) && is_array($_FILES['logo']) ? $_FILES['logo'] : null;
    $has_logo_upload = $logo_upload !== null
        && isset($logo_upload['error'])
        && $logo_upload['error'] !== UPLOAD_ERR_NO_FILE;

    if ($organization_name === '' || strlen($organization_name) > 150) {
        $message = 'Enter an organization name of no more than 150 characters.';
        $message_type = 'danger';
    } elseif (strlen($bio) > 10000) {
        $message = 'The organization description must be no more than 10,000 characters.';
        $message_type = 'danger';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $message = 'Enter a valid 10-digit contact phone number.';
        $message_type = 'danger';
    } elseif (strlen($location) > 255) {
        $message = 'Location must be no more than 255 characters.';
        $message_type = 'danger';
    } elseif (
        $website !== ''
        && (
            filter_var($website, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string)parse_url($website, PHP_URL_SCHEME)), array('http', 'https'), true)
        )
    ) {
        $message = 'Enter a valid website URL.';
        $message_type = 'danger';
    } elseif ($has_logo_upload && !eventease_is_valid_image_upload($logo_upload)) {
        $message = 'Choose a valid JPG, PNG, or WebP image no larger than 5 MB.';
        $message_type = 'danger';
    } else {
        $new_logo_path = null;
        $new_logo_file = null;
        $old_logo_path = null;
        $transaction_started = false;
        try {
            $current_logo = $conn->prepare(
                'SELECT logo_path FROM organizer_profiles WHERE user_id = ?'
            );
            $current_logo->bind_param('i', $user_id);
            $current_logo->execute();
            $current_profile = $current_logo->get_result()->fetch_assoc();
            if (!$current_profile) {
                throw new RuntimeException('Organizer profile record is missing.');
            }
            $old_logo_path = $current_profile['logo_path'];
            $saved_logo_path = $old_logo_path;

            if ($has_logo_upload) {
                $extension = strtolower(pathinfo($logo_upload['name'], PATHINFO_EXTENSION));
                $filename = 'organizer-logo-' . bin2hex(random_bytes(16)) . '.' . $extension;
                $upload_directory = __DIR__ . '/../events/organizer-logos';
                if (!is_dir($upload_directory) && !mkdir($upload_directory, 0755, true) && !is_dir($upload_directory)) {
                    throw new RuntimeException('Could not create the organizer logo directory.');
                }
                $new_logo_file = $upload_directory . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($logo_upload['tmp_name'], $new_logo_file)) {
                    throw new RuntimeException('Could not save the organizer logo upload.');
                }
                $new_logo_path = 'events/organizer-logos/' . $filename;
                $saved_logo_path = $new_logo_path;
            }

            $conn->begin_transaction();
            $transaction_started = true;
            $update = $conn->prepare(
                'UPDATE organizer_profiles
                 SET organization_name = ?, bio = ?, website = ?, location = ?, logo_path = ?
                 WHERE user_id = ?'
            );
            $update->bind_param('sssssi', $organization_name, $bio, $website, $location, $saved_logo_path, $user_id);
            $update->execute();
            $contact_update = $conn->prepare('UPDATE users SET phone = ? WHERE id = ? AND role = \'organizer\'');
            $contact_update->bind_param('si', $phone, $user_id);
            $contact_update->execute();
            $conn->commit();
            $transaction_started = false;

            $old_logo_cleanup_failed = false;
            if (
                $new_logo_path !== null
                && is_string($old_logo_path)
                && preg_match('#^events/organizer-logos/organizer-logo-[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $old_logo_path)
            ) {
                $old_logo_file = __DIR__ . '/../' . $old_logo_path;
                if (is_file($old_logo_file) && !unlink($old_logo_file)) {
                    error_log('Could not remove a replaced organizer logo.');
                    $old_logo_cleanup_failed = true;
                }
            }
            $message = $old_logo_cleanup_failed
                ? 'Organizer profile updated, but the previous logo could not be removed.'
                : 'Organizer profile updated.';
            if ($old_logo_cleanup_failed) {
                $message_type = 'warning';
            }
        } catch (mysqli_sql_exception $exception) {
            if ($transaction_started) {
                $conn->rollback();
            }
            if ($new_logo_file !== null && is_file($new_logo_file) && !unlink($new_logo_file)) {
                error_log('Could not remove an uncommitted organizer logo upload.');
            }
            error_log('Organizer profile update failed with MySQL error code ' . $exception->getCode() . '.');
            $message = 'The organizer profile could not be updated.';
            $message_type = 'danger';
        } catch (RuntimeException $exception) {
            if ($transaction_started) {
                $conn->rollback();
            }
            if ($new_logo_file !== null && is_file($new_logo_file) && !unlink($new_logo_file)) {
                error_log('Could not remove an unsaved organizer logo upload.');
            }
            error_log('Organizer profile update failed: ' . $exception->getMessage());
            $message = 'The organizer profile could not be updated.';
            $message_type = 'danger';
        }
    }
}

$profile_statement = $conn->prepare(
    'SELECT organizer_profiles.organization_name, organizer_profiles.bio, organizer_profiles.website,
            organizer_profiles.location, organizer_profiles.logo_path,
            organizer_profiles.verification_status, users.email, users.phone
     FROM organizer_profiles
     INNER JOIN users ON users.id = organizer_profiles.user_id
     WHERE organizer_profiles.user_id = ? AND users.role = \'organizer\''
);
$profile_statement->bind_param('i', $user_id);
$profile_statement->execute();
$profile = $profile_statement->get_result()->fetch_assoc();
if (!$profile) {
    http_response_code(500);
    exit('Organizer profile is unavailable.');
}
$logo_display_path = '';
if (
    is_string($profile['logo_path'])
    && preg_match('#^events/organizer-logos/organizer-logo-[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $profile['logo_path'])
) {
    $logo_display_path = $profile['logo_path'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Profile - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3"><?php include '../includes/organizer_sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
                    <h1 class="h3 fw-bold mb-2">Organizer Profile</h1>
                    <p class="text-muted">Verification status: <?php echo htmlspecialchars(ucfirst($profile['verification_status'])); ?></p>
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                        <div class="col-12">
                            <label class="form-label" for="organization-name">Organization name</label>
                            <input id="organization-name" name="organization_name" class="form-control" maxlength="150" required value="<?php echo htmlspecialchars($profile['organization_name']); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="bio">About your organization</label>
                            <textarea id="bio" name="bio" class="form-control" rows="5"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="website">Website</label>
                            <input id="website" type="url" name="website" class="form-control" value="<?php echo htmlspecialchars($profile['website'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="location">Location</label>
                            <input id="location" name="location" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="logo">Organization logo</label>
                            <?php if ($logo_display_path !== ''): ?>
                                <div class="mb-2"><img src="../<?php echo htmlspecialchars($logo_display_path); ?>" alt="Current organization logo" class="rounded-3" style="max-width: 180px; max-height: 120px;"></div>
                            <?php endif; ?>
                            <input id="logo" type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <small class="form-text text-muted">Optional; JPG, PNG, or WebP, up to 5 MB.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contact-email">Contact email</label>
                            <input id="contact-email" type="email" class="form-control" value="<?php echo htmlspecialchars($profile['email']); ?>" readonly>
                            <small class="form-text text-muted">This is your account login email.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Contact phone</label>
                            <input id="phone" type="tel" name="phone" class="form-control" pattern="[0-9]{10}" maxlength="10" required value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                        </div>
                        <?php if ($message !== ''): ?><div class="col-12"><div class="alert alert-<?php echo $message_type; ?>" role="status"><?php echo htmlspecialchars($message); ?></div></div><?php endif; ?>
                        <div class="col-12"><button type="submit" class="btn btn-primary">Save Profile</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
