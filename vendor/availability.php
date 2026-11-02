<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('vendor');
require_once __DIR__ . '/../includes/db.php';

$vendor_id = (int)$_SESSION['user_id'];
$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'add') {
        $date = isset($_POST['available_date']) && is_string($_POST['available_date']) ? $_POST['available_date'] : '';
        $start = isset($_POST['start_time']) && is_string($_POST['start_time']) ? $_POST['start_time'] : '';
        $end = isset($_POST['end_time']) && is_string($_POST['end_time']) ? $_POST['end_time'] : '';
        $status = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))
            || $date < date('Y-m-d')
            || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start)
            || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end)
            || $start >= $end
            || !in_array($status, array('available', 'unavailable'), true)
        ) {
            $message = 'Enter a future date, valid time range, and status.';
            $message_type = 'danger';
        } else {
            $overlap = $conn->prepare(
                "SELECT id FROM vendor_availability
                 WHERE vendor_id = ? AND available_date = ? AND status = 'available'
                   AND ? < end_time AND ? > start_time
                 LIMIT 1"
            );
            $overlap->bind_param('isss', $vendor_id, $date, $start, $end);
            $overlap->execute();
            if ($status === 'available' && $overlap->get_result()->num_rows > 0) {
                $message = 'This time overlaps an existing available slot.';
                $message_type = 'danger';
            } else {
                $insert = $conn->prepare(
                    'INSERT INTO vendor_availability (vendor_id, available_date, start_time, end_time, status)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $insert->bind_param('issss', $vendor_id, $date, $start, $end, $status);
                $insert->execute();
                header('Location: availability.php?saved=1');
                exit();
            }
        }
    } elseif ($action === 'delete' && isset($_POST['slot_id']) && is_string($_POST['slot_id']) && ctype_digit($_POST['slot_id'])) {
        $slot_id = (int)$_POST['slot_id'];
        $delete = $conn->prepare('DELETE FROM vendor_availability WHERE id = ? AND vendor_id = ?');
        $delete->bind_param('ii', $slot_id, $vendor_id);
        $delete->execute();
        if ($delete->affected_rows === 1) {
            header('Location: availability.php?deleted=1');
            exit();
        }
        $message = 'That availability slot was not found in your account.';
        $message_type = 'warning';
    } else {
        $message = 'The availability action was invalid.';
        $message_type = 'danger';
    }
}

$slots_statement = $conn->prepare(
    'SELECT id, available_date, start_time, end_time, status
     FROM vendor_availability WHERE vendor_id = ?
     ORDER BY available_date, start_time'
);
$slots_statement->bind_param('i', $vendor_id);
$slots_statement->execute();
$slots = $slots_statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Availability - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <main class="container py-5">
        <section class="card border-0 shadow-sm rounded-4 p-4 p-lg-5 mb-4">
            <h1 class="h3 fw-bold mb-3">Availability</h1>
            <?php if (isset($_GET['saved'])): ?><div class="alert alert-success" role="status">Availability slot added.</div><?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success" role="status">Availability slot removed.</div><?php endif; ?>
            <?php if ($message !== ''): ?><div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <form method="POST" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                <input type="hidden" name="action" value="add">
                <div class="col-md-3"><label class="form-label" for="available-date">Date</label><input type="date" id="available-date" name="available_date" min="<?php echo date('Y-m-d'); ?>" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label" for="start-time">Start</label><input type="time" id="start-time" name="start_time" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label" for="end-time">End</label><input type="time" id="end-time" name="end_time" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label" for="status">Status</label><select id="status" name="status" class="form-select"><option value="available">Available</option><option value="unavailable">Unavailable</option></select></div>
                <div class="col-12"><button type="submit" class="btn btn-primary">Add time slot</button></div>
            </form>
        </section>
        <section class="card border-0 shadow-sm rounded-4 p-4">
            <h2 class="h5 fw-bold mb-3">Your time slots</h2>
            <?php if ($slots->num_rows === 0): ?>
                <p class="text-muted mb-0">No availability has been added yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle modern-table">
                        <thead><tr><th>Date</th><th>Time</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php while ($slot = $slots->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($slot['available_date']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($slot['start_time'], 0, 5) . '–' . substr($slot['end_time'], 0, 5)); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($slot['status'])); ?></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="slot_id" value="<?php echo (int)$slot['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
