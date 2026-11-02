<?php require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("admin", "organizer");
include("../includes/db.php");
$role = eventease_current_role();
$is_admin = $role === 'admin';
if ($is_admin) {
    $query = $conn->prepare(
        'SELECT events.*, organizer_profiles.organization_name AS owner_organization
         FROM events
         LEFT JOIN organizer_profiles ON organizer_profiles.user_id = events.organizer_id
         ORDER BY events.id DESC'
    );
    $query->execute();
    $result = $query->get_result();
    $verified_organizers_result = $conn->query(
        "SELECT users.id, organizer_profiles.organization_name
         FROM users
         INNER JOIN organizer_profiles ON organizer_profiles.user_id = users.id
         WHERE users.role = 'organizer' AND organizer_profiles.verification_status = 'verified'
         ORDER BY organizer_profiles.organization_name, users.id"
    );
    $verified_organizers = array();
    while ($organizer = $verified_organizers_result->fetch_assoc()) {
        $verified_organizers[] = $organizer;
    }
} else {
    $organizer_id = (int)$_SESSION['user_id'];
    $query = $conn->prepare("SELECT * FROM events WHERE organizer_id = ? ORDER BY id DESC");
    $query->bind_param("i", $organizer_id);
    $query->execute();
    $result = $query->get_result();
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
    <title>View Events</title>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3">
                <?php include ($is_admin ? '../includes/admin_sidebar.php' : '../includes/organizer_sidebar.php'); ?>
            </div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h3 class="fw-bold mb-1">Manage Events</h3>
                            <p class="text-muted mb-0">Review your event catalog and update listings.</p>
                        </div>
                        <a href="add_event.php" class="btn btn-primary">Add New Event</a>
                    </div>
                    <?php if (isset($_GET['created']) && $_GET['created'] === '1'): ?>
                        <div class="alert alert-success" role="status">
                            Your event was added and is listed below. Draft and archived events are visible here but not on the public events page.
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
                        <div class="alert alert-success" role="status">Your event changes were saved and are listed below.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['owner_assignment']) && $_GET['owner_assignment'] === 'assigned'): ?>
                        <div class="alert alert-success" role="status">The event was assigned to the verified organizer.</div>
                    <?php elseif (isset($_GET['owner_assignment']) && $_GET['owner_assignment'] === 'unchanged'): ?>
                        <div class="alert alert-warning" role="status">The event was not changed. It may already have an owner or no longer exist.</div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table align-middle modern-table">
                            <thead>
                                <tr><th>ID</th><th>Event</th><?php if ($is_admin): ?><th>Owner</th><?php endif; ?><th>Category</th><th>Date</th><th>Venue</th><th>Status</th><th>Image</th><th>Seats</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php while($row = $result->fetch_assoc()){ ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['event_name']); ?></td>
                                    <?php if ($is_admin): ?>
                                        <td>
                                            <?php if ($row['organizer_id'] !== null): ?>
                                                <?php echo htmlspecialchars($row['owner_organization'] ?? 'Assigned organizer'); ?>
                                            <?php elseif (count($verified_organizers) > 0): ?>
                                                <form method="POST" action="assign_event_owner.php" class="d-flex flex-column gap-2">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                                    <input type="hidden" name="event_id" value="<?php echo (int)$row['id']; ?>">
                                                    <label class="visually-hidden" for="event-owner-<?php echo (int)$row['id']; ?>">Assign verified organizer</label>
                                                    <select class="form-select form-select-sm" id="event-owner-<?php echo (int)$row['id']; ?>" name="organizer_id" required>
                                                        <option value="">Unassigned</option>
                                                        <?php foreach ($verified_organizers as $organizer): ?>
                                                            <option value="<?php echo (int)$organizer['id']; ?>"><?php echo htmlspecialchars($organizer['organization_name']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-outline-primary btn-sm">Assign</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($row['category'] ?: $row['event_type']); ?></td>
                                    <td><?php echo htmlspecialchars($row['event_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['venue']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($row['publish_status'])); ?></td>
                                    <td><?php if($row['image']!="") { ?><img src="../events/<?php echo rawurlencode($row['image']); ?>" width="90" class="rounded-3" alt="<?php echo htmlspecialchars($row['event_name']); ?>"> <?php } else { echo 'No image'; } ?></td>
                                    <td><?php echo $row['max_participants']; ?></td>
                                    <td class="text-nowrap">
                                        <?php if ($row['publish_status'] === 'published'): ?>
                                            <a href="../event.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-outline-secondary btn-sm">View public page</a>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-share-event data-share-url="../event.php?id=<?php echo (int)$row['id']; ?>" data-share-title="<?php echo htmlspecialchars($row['event_name'], ENT_QUOTES, 'UTF-8'); ?>" data-share-text="View this event on EventEase." data-share-label="Share">Share</button>
                                        <?php endif; ?>
                                        <a href="edit_event.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                                        <form method="POST" action="delete_event.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this event and its registrations?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
