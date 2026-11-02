<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_once __DIR__ . '/../includes/db.php';
require_role('vendor');

$vendor_id = (int)$_SESSION['user_id'];
$message = '';
$message_type = 'success';
$portfolio_directory = __DIR__ . '/../events/vendor_portfolio';
if (!is_dir($portfolio_directory) && !mkdir($portfolio_directory, 0755, true) && !is_dir($portfolio_directory)) {
    throw new RuntimeException('The vendor portfolio directory could not be created.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'upload') {
        $caption = isset($_POST['caption']) && is_string($_POST['caption']) ? trim($_POST['caption']) : '';
        $upload = $_FILES['portfolio_image'] ?? array('error' => UPLOAD_ERR_NO_FILE);
        if (strlen($caption) > 255) {
            $message = 'Keep the caption to 255 characters or fewer.';
            $message_type = 'danger';
        } elseif (!eventease_is_valid_image_upload($upload)) {
            $message = 'Upload a valid JPG, JPEG, PNG or WEBP image under 5 MB.';
            $message_type = 'danger';
        } else {
            $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
            $image_name = bin2hex(random_bytes(16)) . '.' . $extension;
            $destination = $portfolio_directory . DIRECTORY_SEPARATOR . $image_name;
            if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                $message = 'The portfolio image could not be saved. Please try again.';
                $message_type = 'danger';
            } else {
                $insert = $conn->prepare(
                    'INSERT INTO vendor_portfolio (vendor_id, image_path, caption) VALUES (?, ?, ?)'
                );
                $insert->bind_param('iss', $vendor_id, $image_name, $caption);
                if ($insert->execute()) {
                    header('Location: portfolio.php?saved=1');
                    exit();
                }
                if (is_file($destination)) {
                    unlink($destination);
                }
                $message = 'The portfolio image could not be saved. Please try again.';
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'delete' && isset($_POST['portfolio_id']) && is_string($_POST['portfolio_id']) && ctype_digit($_POST['portfolio_id'])) {
        $portfolio_id = (int)$_POST['portfolio_id'];
        $lookup = $conn->prepare(
            'SELECT image_path FROM vendor_portfolio WHERE id = ? AND vendor_id = ?'
        );
        $lookup->bind_param('ii', $portfolio_id, $vendor_id);
        $lookup->execute();
        $image = $lookup->get_result()->fetch_assoc();
        if ($image) {
            $delete = $conn->prepare('DELETE FROM vendor_portfolio WHERE id = ? AND vendor_id = ?');
            $delete->bind_param('ii', $portfolio_id, $vendor_id);
            $delete->execute();
            $image_path = $portfolio_directory . DIRECTORY_SEPARATOR . basename($image['image_path']);
            if (is_file($image_path)) {
                unlink($image_path);
            }
            header('Location: portfolio.php?deleted=1');
            exit();
        }
        $message = 'That portfolio image does not belong to your account.';
        $message_type = 'warning';
    } else {
        $message = 'The portfolio action was invalid.';
        $message_type = 'danger';
    }
}

$portfolio_statement = $conn->prepare(
    'SELECT id, image_path, caption, created_at FROM vendor_portfolio
     WHERE vendor_id = ? ORDER BY created_at DESC, id DESC'
);
$portfolio_statement->bind_param('i', $vendor_id);
$portfolio_statement->execute();
$portfolio = $portfolio_statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Portfolio - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <main class="container py-5">
        <section class="card border-0 shadow-sm rounded-4 p-4 p-lg-5 mb-4">
            <h1 class="h3 fw-bold mb-3">Portfolio</h1>
            <?php if (isset($_GET['saved'])): ?><div class="alert alert-success" role="status">Portfolio image added.</div><?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success" role="status">Portfolio image removed.</div><?php endif; ?>
            <?php if ($message !== ''): ?><div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                <input type="hidden" name="action" value="upload">
                <div class="col-md-7">
                    <label class="form-label" for="portfolio-image">Image</label>
                    <input type="file" id="portfolio-image" name="portfolio_image" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
                    <div class="form-text">Images are validated and limited to 5 MB.</div>
                </div>
                <div class="col-md-5"><label class="form-label" for="caption">Caption (optional)</label><input type="text" id="caption" name="caption" class="form-control" maxlength="255"></div>
                <div class="col-12"><button type="submit" class="btn btn-primary">Upload image</button></div>
            </form>
        </section>
        <section class="row g-3">
            <?php if ($portfolio->num_rows === 0): ?>
                <div class="col-12"><div class="card border-0 shadow-sm rounded-4 p-4"><p class="text-muted mb-0">Your portfolio is empty.</p></div></div>
            <?php endif; ?>
            <?php while ($item = $portfolio->fetch_assoc()): ?>
                <div class="col-sm-6 col-lg-4">
                    <article class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                        <img src="../events/vendor_portfolio/<?php echo rawurlencode(basename($item['image_path'])); ?>" alt="<?php echo htmlspecialchars($item['caption'] ?: 'Vendor portfolio image'); ?>" class="w-100" style="height:220px;object-fit:cover">
                        <div class="card-body">
                            <p><?php echo htmlspecialchars($item['caption'] ?? ''); ?></p>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="portfolio_id" value="<?php echo (int)$item['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                            </form>
                        </div>
                    </article>
                </div>
            <?php endwhile; ?>
        </section>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
