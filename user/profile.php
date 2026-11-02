<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("user");
include("../includes/db.php");

$user_id = (int)$_SESSION['user_id'];
$statement = $conn->prepare("SELECT id, name, email, phone FROM users WHERE id = ?");
$statement->bind_param("i", $user_id);
$statement->execute();
$user = $statement->get_result()->fetch_assoc();

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
}

if(isset($_POST['update']))
{
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $check->bind_param("si", $email, $user_id);
    $check->execute();
    if($check->get_result()->num_rows > 0)
        {
            $msg="Email Already Exists";
        }
    else{
    $update = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
    $update->bind_param("sssi", $name, $email, $phone, $user_id);

    if($update->execute())
    {
        $_SESSION['user_name'] = $name;

        $msg = "Profile Updated Successfully";

        $statement = $conn->prepare("SELECT id, name, email, phone FROM users WHERE id = ?");
        $statement->bind_param("i", $user_id);
        $statement->execute();
        $user = $statement->get_result()->fetch_assoc();
    }
    else {
        $msg = "Profile could not be updated. Please try again.";
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
    <title>Profile</title>
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
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                        <div>
                            <h3 class="fw-bold mb-1">My Profile</h3>
                            <p class="text-muted mb-0">Keep your details up to date for smoother event planning.</p>
                        </div>
                        <div class="avatar-circle"><i class="fa-solid fa-user"></i></div>
                    </div>
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                        <?php if($msg != ""): ?><div class="col-12"><div class="alert <?php echo strpos($msg, 'Successfully') !== false ? 'alert-success' : 'alert-danger'; ?> rounded-4 mb-0" role="alert"><?php echo htmlspecialchars($msg); ?></div></div><?php endif; ?>
                        <div class="col-12"><button type="submit" name="update" class="btn btn-primary">Update Profile</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>