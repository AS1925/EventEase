<?php
require_once('includes/access_control.php');
include('includes/db.php');
$message="";
$account_type = 'customer';
$organization_name = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
}
if(isset($_POST['register']))
    {
        $name=trim($_POST['name']);
        $email=strtolower(trim($_POST['email']));
        $phone=trim($_POST['phone']);
        $password=$_POST['password'];
        $hashed_password=password_hash($password,PASSWORD_DEFAULT);
        $account_type = isset($_POST['account_type']) && is_string($_POST['account_type']) ? $_POST['account_type'] : 'customer';
        $organization_name = in_array($account_type, array('organizer', 'vendor'), true)
            && isset($_POST['organization_name'])
            && is_string($_POST['organization_name'])
            ? trim($_POST['organization_name'])
            : '';

        if ($email === EVENTEASE_ADMIN_EMAIL) {
            $message = "This email address is reserved for the administrator.";
        } elseif (!in_array($account_type, array('customer', 'organizer', 'vendor'), true)) {
            $message = "Choose a valid account type.";
        } elseif (in_array($account_type, array('organizer', 'vendor'), true) && $organization_name === '') {
            $message = "Enter your business or organization name.";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = "Email already registered";
            } else {
                $conn->begin_transaction();
                try {
                    $query = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
                    $query->bind_param("sssss", $name, $email, $phone, $hashed_password, $account_type);
                    $query->execute();
                    $new_user_id = $conn->insert_id;
                    if ($account_type === 'organizer') {
                        $profile = $conn->prepare("INSERT INTO organizer_profiles (user_id, organization_name) VALUES (?, ?)");
                        $profile->bind_param("is", $new_user_id, $organization_name);
                        $profile->execute();
                    } elseif ($account_type === 'vendor') {
                        $profile = $conn->prepare("INSERT INTO vendor_profiles (user_id, business_name) VALUES (?, ?)");
                        $profile->bind_param("is", $new_user_id, $organization_name);
                        $profile->execute();
                    }
                    $conn->commit();
                    $message = "Registration Successful!";
                } catch (mysqli_sql_exception $exception) {
                    $conn->rollback();
                    $message = "Registration could not be completed. Please try again.";
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
        <title>User Registration - Event Ease</title>
    </head>
    <body class="auth-page">
        <?php include 'includes/navbar.php'; ?>
            <div class="container py-5">
                <div class="row auth-card shadow-lg overflow-hidden">
                    <div class="col-lg-6 p-4 p-lg-5 bg-white">
                        <div class="text-center mb-4">
                        <div class="brand-pill mb-3"><i class="fa-solid fa-user-plus"></i> EventEase</div>
                        <h3 class="fw-bold mb-2">Create your account</h3>
                        <p class="text-muted">Join thousands of event planners and guests</p>
                    </div>
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                    <input type="tel" name="phone" class="form-control" pattern="[0-9]{10}" maxlength="10" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="account-type">Account Type</label>
                                <select id="account-type" name="account_type" class="form-select" required>
                                    <option value="customer" <?php echo $account_type === 'customer' ? 'selected' : ''; ?>>Customer</option>
                                    <option value="organizer" <?php echo $account_type === 'organizer' ? 'selected' : ''; ?>>Event Organizer</option>
                                    <option value="vendor" <?php echo $account_type === 'vendor' ? 'selected' : ''; ?>>Service Vendor</option>
                                </select>
                                <small class="form-text text-muted">Organizer and vendor accounts require admin verification before using marketplace tools.</small>
                            </div>
                            <div class="col-12" id="organization-field" hidden>
                                <label class="form-label" for="organization-name">Business / Organization Name</label>
                                <input id="organization-name" type="text" name="organization_name" class="form-control" maxlength="150" value="<?php echo htmlspecialchars($organization_name, ENT_QUOTES, 'UTF-8'); ?>" disabled placeholder="Required for organizer or vendor accounts">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <?php if($message != ""): ?>
                            <div class="alert <?php echo strpos($message,'Successful') !== false ? 'alert-success' : 'alert-danger'; ?> rounded-4 mt-3" role="alert"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        <button type="submit" name="register" class="btn btn-primary w-100 mt-3">Create Account</button>
                    </form>
                    <div class="text-center mt-4">
                        <a href="login.php" class="text-decoration-none"><i class="fa-solid fa-right-to-bracket me-2"></i>Already have an account? Login</a>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-flex auth-illustration align-items-center justify-content-center p-5">
                    <div class="text-center text-white">
                        <div class="icon-badge mb-4"><i class="fa-solid fa-calendar-days"></i></div>
                        <h2 class="fw-bold mb-3">Plan Smarter</h2>
                        <p class="mb-0">Create an account to register for events and request premium services in just a few clicks.</p>
                    </div>
                </div>
            </div>
        </div>
        <script>
            (function () {
                var accountType = document.getElementById('account-type');
                var organizationField = document.getElementById('organization-field');
                var organizationName = document.getElementById('organization-name');

                function updateOrganizationField() {
                    var needsOrganization = accountType.value === 'organizer' || accountType.value === 'vendor';
                    organizationField.hidden = !needsOrganization;
                    organizationName.disabled = !needsOrganization;
                    organizationName.required = needsOrganization;
                    if (!needsOrganization) {
                        organizationName.value = '';
                    }
                }

                accountType.addEventListener('change', updateOrganizationField);
                updateOrganizationField();
            })();
        </script>
        <?php include 'includes/footer.php'; ?>
    </body>
</html>