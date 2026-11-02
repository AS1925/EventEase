<?php
require_once __DIR__ . '/includes/access_control.php';
eventease_start_session();
require_once __DIR__ . '/includes/db.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

$token_input = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['token'] ?? '')
    : ($_GET['token'] ?? '');
$token_valid_format = is_string($token_input) && preg_match('/\A[a-f0-9]{64}\z/', $token_input) === 1;
$token_hash = $token_valid_format ? hash('sha256', $token_input) : '';
$message = '';
$message_type = 'danger';
$reset_complete = isset($_GET['reset']) && $_GET['reset'] === 'success';
$valid_token = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])
        ? $_POST['confirm_password']
        : '';

    if (!$token_valid_format) {
        $message = 'This reset link is invalid, expired, or already used. Request a new one.';
    } elseif (strlen($password) < 12 || strlen($password) > 72) {
        $message = 'Use a password between 12 and 72 bytes.';
    } elseif (!hash_equals($password, $confirm_password)) {
        $message = 'Passwords do not match.';
    } else {
        $conn->begin_transaction();
        try {
            $token_statement = $conn->prepare(
                'SELECT user_id FROM password_reset_tokens
                 WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()
                 LIMIT 1 FOR UPDATE'
            );
            $token_statement->bind_param('s', $token_hash);
            $token_statement->execute();
            $token_record = $token_statement->get_result()->fetch_assoc();

            if (!$token_record) {
                $conn->rollback();
                $message = 'This reset link is invalid, expired, or already used. Request a new one.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                if ($password_hash === false) {
                    throw new RuntimeException('Password hashing failed.');
                }
                $user_id = (int)$token_record['user_id'];
                $update_password = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                $update_password->bind_param('si', $password_hash, $user_id);
                $update_password->execute();

                if ($update_password->affected_rows !== 1) {
                    $conn->rollback();
                    $message = 'This reset link is invalid, expired, or already used. Request a new one.';
                } else {
                    $invalidate_tokens = $conn->prepare(
                        'UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP()
                         WHERE user_id = ? AND used_at IS NULL'
                    );
                    $invalidate_tokens->bind_param('i', $user_id);
                    $invalidate_tokens->execute();
                    $conn->commit();
                    header('Location: reset_password.php?reset=success');
                    exit();
                }
            }
        } catch (Throwable $exception) {
            $conn->rollback();
            throw $exception;
        }
    }
}

if (!$reset_complete && $token_valid_format) {
    $token_statement = $conn->prepare(
        'SELECT 1 FROM password_reset_tokens
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()
         LIMIT 1'
    );
    $token_statement->bind_param('s', $token_hash);
    $token_statement->execute();
    $valid_token = $token_statement->get_result()->num_rows === 1;
}

if (!$reset_complete && !$valid_token && $message === '') {
    $message = 'This reset link is invalid, expired, or already used. Request a new one.';
}

if ($reset_complete) {
    $message = 'Your password has been updated. Sign in with your new password.';
    $message_type = 'success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="events/logo.png" type="image/png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <title>Set New Password - Event Ease</title>
</head>
<body class="auth-page">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="brand-pill mb-3"><i class="fa-solid fa-shield-halved"></i> Secure Reset</div>
                        <h1 class="h3 fw-bold mb-2">Set a new password</h1>
                    </div>
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>" role="status"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <?php if ($valid_token && !$reset_complete): ?>
                        <form method="POST" class="auth-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token_input, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3">
                                <label class="form-label" for="new-password">New password</label>
                                <input id="new-password" type="password" name="password" class="form-control" minlength="12" maxlength="72" autocomplete="new-password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="confirm-password">Confirm new password</label>
                                <input id="confirm-password" type="password" name="confirm_password" class="form-control" minlength="12" maxlength="72" autocomplete="new-password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">Update password</button>
                        </form>
                    <?php endif; ?>
                    <div class="text-center mt-4">
                        <a href="login.php" class="text-decoration-none"><i class="fa-solid fa-right-to-bracket me-2"></i>Back to login</a>
                        <?php if (!$reset_complete): ?>
                            <div class="mt-2"><a href="forgot_password.php" class="text-decoration-none">Request another reset link</a></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
