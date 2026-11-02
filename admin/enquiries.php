<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("admin");
include("../includes/db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && is_string($_POST['delete_id']) && ctype_digit($_POST['delete_id'])) {
    $message_id = (int)$_POST['delete_id'];
    $delete = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
    $delete->bind_param("i", $message_id);
    $delete->execute();
    header("Location: enquiries.php?deleted=1");
    exit();
}

$messages = $conn->query("SELECT id, name, email, subject, message, submitted_at FROM contact_messages ORDER BY submitted_at DESC, id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enquiries - EventEase Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3"><?php include '../includes/admin_sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <h1 class="h3 fw-bold mb-1">Contact Enquiries</h1>
                            <p class="text-muted mb-0">Messages submitted from the public contact page.</p>
                        </div>
                        <span class="badge bg-soft-primary text-primary"><?php echo (int)$messages->num_rows; ?> messages</span>
                    </div>
                    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success" role="status">Enquiry removed.</div><?php endif; ?>
                    <?php if ($messages->num_rows === 0): ?>
                        <div class="text-center py-5">
                            <i class="fa-solid fa-inbox text-primary fs-2 mb-3"></i>
                            <h2 class="h5 fw-semibold">No enquiries yet</h2>
                            <p class="text-muted mb-0">New contact messages will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php while ($message = $messages->fetch_assoc()): ?>
                                <?php $reply_subject = rawurlencode('Re: ' . $message['subject']); ?>
                                <article class="border rounded-3 p-3 p-md-4">
                                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                        <div>
                                            <h2 class="h5 fw-bold mb-1"><?php echo htmlspecialchars($message['subject']); ?></h2>
                                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($message['name']); ?> &lt;<?php echo htmlspecialchars($message['email']); ?>&gt;</p>
                                        </div>
                                        <time class="text-muted small" datetime="<?php echo htmlspecialchars($message['submitted_at']); ?>"><?php echo htmlspecialchars($message['submitted_at']); ?></time>
                                    </div>
                                    <p class="mb-3"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a class="btn btn-sm btn-outline-primary" href="mailto:<?php echo rawurlencode($message['email']); ?>?subject=<?php echo $reply_subject; ?>"><i class="fa-solid fa-reply me-1"></i>Reply</a>
                                        <form method="POST" class="m-0" onsubmit="return confirm('Remove this enquiry?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                            <input type="hidden" name="delete_id" value="<?php echo (int)$message['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash me-1"></i>Remove</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
