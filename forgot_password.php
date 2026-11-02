<?php
require_once __DIR__ . '/includes/access_control.php';
eventease_start_session();
require_once __DIR__ . '/includes/db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $message = 'If an account matches that email address, a password reset link will be sent. The link expires in 30 minutes.';

    $email_input = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
    $email = filter_var($email_input, FILTER_VALIDATE_EMAIL);
    if ($email !== false) {
        $app_base_url = rtrim((string)getenv('APP_BASE_URL'), '/');
        $url_parts = parse_url($app_base_url);
        $mail_from = filter_var((string)getenv('MAIL_FROM'), FILTER_VALIDATE_EMAIL);
        $valid_base_url = is_array($url_parts)
            && isset($url_parts['scheme'], $url_parts['host'])
            && in_array(strtolower($url_parts['scheme']), array('http', 'https'), true)
            && !isset($url_parts['user'])
            && !isset($url_parts['pass'])
            && !isset($url_parts['query'])
            && !isset($url_parts['fragment']);

        if (!$valid_base_url || $mail_from === false || !function_exists('mail')) {
            error_log('Password reset email is unavailable; configure APP_BASE_URL, MAIL_FROM, and PHP mail delivery.');
        } else {
            $account_statement = $conn->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
            $account_statement->bind_param('s', $email);
            $account_statement->execute();
            $account = $account_statement->get_result()->fetch_assoc();

            if ($account) {
                $cleanup = $conn->prepare(
                    'DELETE FROM password_reset_tokens WHERE expires_at < UTC_TIMESTAMP()
                     OR used_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)'
                );
                $cleanup->execute();

                $limit_statement = $conn->prepare(
                    'SELECT COUNT(*) AS token_count FROM password_reset_tokens
                     WHERE user_id = ? AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)'
                );
                $limit_statement->bind_param('i', $account['id']);
                $limit_statement->execute();
                $token_count = (int)$limit_statement->get_result()->fetch_assoc()['token_count'];

                if ($token_count < 3) {
                    $token = bin2hex(random_bytes(32));
                    $token_hash = hash('sha256', $token);
                    $insert = $conn->prepare(
                        'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at)
                         VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE), UTC_TIMESTAMP())'
                    );
                    $insert->bind_param('is', $account['id'], $token_hash);
                    $insert->execute();

                    $reset_url = $app_base_url . '/reset_password.php?token=' . rawurlencode($token);
                    $subject = 'Reset your EventEase password';
                    $body = "A password reset was requested for your EventEase account.\n\n"
                        . "Use this one-time link within 30 minutes:\n"
                        . $reset_url
                        . "\n\nIf you did not request this change, you can ignore this email.";
                    $headers = "From: " . $mail_from . "\r\n"
                        . "Content-Type: text/plain; charset=UTF-8";

                    set_error_handler(function () {
                        error_log('PHP mail transport reported an error while sending a password reset link.');
                        return true;
                    }, E_WARNING);
                    try {
                        $mail_sent = mail($account['email'], $subject, $body, $headers);
                    } finally {
                        restore_error_handler();
                    }

                    if (!$mail_sent) {
                        $mark_failed = $conn->prepare(
                            'UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE token_hash = ?'
                        );
                        $mark_failed->bind_param('s', $token_hash);
                        $mark_failed->execute();
                        error_log('Password reset email could not be sent by the configured PHP mail transport.');
                    } else {
                        $invalidate = $conn->prepare(
                            'UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP()
                             WHERE user_id = ? AND used_at IS NULL AND token_hash <> ?'
                        );
                        $invalidate->bind_param('is', $account['id'], $token_hash);
                        $invalidate->execute();
                    }
                }
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
    <link rel="icon" href="events/logo.png" type="image/png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <title>Forgot Password - Event Ease</title>
</head>
<body class="auth-page">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="brand-pill mb-3"><i class="fa-solid fa-shield-halved"></i> Secure Reset</div>
                        <h1 class="h3 fw-bold mb-2">Reset your password</h1>
                        <p class="text-muted">Enter your account email to receive a one-time reset link.</p>
                    </div>
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-3">
                            <label class="form-label" for="reset-email">Email Address</label>
                            <input id="reset-email" type="email" name="email" class="form-control" maxlength="100" autocomplete="email" required>
                        </div>
                        <?php if ($message !== ''): ?>
                            <div class="alert alert-success rounded-4" role="status"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <button type="submit" name="reset" class="btn btn-primary w-100 py-2">Send reset link</button>
                    </form>
                    <div class="text-center mt-4">
                        <a href="login.php" class="text-decoration-none"><i class="fa-solid fa-right-to-bracket me-2"></i>Back to login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
