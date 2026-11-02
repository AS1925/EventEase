<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("admin");
include("../includes/db.php");

$fields = array(
    'home_hero_eyebrow' => 'Homepage hero eyebrow',
    'home_hero_title' => 'Homepage hero title',
    'home_hero_highlight' => 'Homepage highlighted title',
    'home_hero_suffix' => 'Homepage title ending',
    'home_hero_description' => 'Homepage hero description',
    'home_about_title' => 'Homepage intro title',
    'home_about_highlight' => 'Homepage intro highlight',
    'home_about_description' => 'Homepage intro description',
    'about_title' => 'About page title',
    'about_description' => 'About page description'
);
$message = "";
$message_type = "success";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $values = array();
    foreach ($fields as $key => $label) {
        $values[$key] = trim($_POST[$key] ?? "");
        if ($values[$key] === "") {
            $message = "Every content field is required.";
            $message_type = "danger";
            break;
        }
    }

    if ($message === "") {
        $conn->begin_transaction();
        $statement = $conn->prepare("INSERT INTO page_content (content_key, content_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE content_value = VALUES(content_value)");
        foreach ($fields as $key => $label) {
            $value = $values[$key];
            $statement->bind_param("ss", $key, $value);
            if (!$statement->execute()) {
                $message = "Content could not be saved. Please try again.";
                $message_type = "danger";
                break;
            }
        }
        if ($message === "") {
            $conn->commit();
        } else {
            $conn->rollback();
        }
    }
    if ($message === "") {
        $message = "Public page content has been updated.";
    }
}

$content = array();
$result = $conn->query("SELECT content_key, content_value FROM page_content");
while ($row = $result->fetch_assoc()) {
    $content[$row['content_key']] = $row['content_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Public Content - EventEase Admin</title>
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
                <div class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <h1 class="h3 fw-bold mb-1">Public Page Content</h1>
                            <p class="text-muted mb-0">Edit the homepage introduction and About page copy.</p>
                        </div>
                        <a href="../index.php" class="btn btn-outline-primary" target="_blank" rel="noopener">View Site</a>
                    </div>
                    <?php if ($message !== ""): ?><div class="alert alert-<?php echo $message_type; ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                        <?php foreach ($fields as $key => $label): ?>
                            <?php $is_long = strpos($key, 'description') !== false; ?>
                            <div class="col-12">
                                <label class="form-label" for="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></label>
                                <?php if ($is_long): ?>
                                    <textarea class="form-control" id="<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" rows="3" required><?php echo htmlspecialchars($content[$key] ?? ''); ?></textarea>
                                <?php else: ?>
                                    <input class="form-control" id="<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($content[$key] ?? ''); ?>" required>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-12"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>Save Content</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
