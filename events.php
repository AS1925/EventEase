<?php require_once("includes/access_control.php");
eventease_start_session();
include("includes/db.php");
$current_role = eventease_current_role();
$search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : "";
if ($search !== "") {
    $search_term = "%" . $search . "%";
    $statement = $conn->prepare("SELECT * FROM events WHERE publish_status = 'published' AND event_date >= CURDATE() AND (event_name LIKE ? OR category LIKE ? OR event_type LIKE ?) ORDER BY event_date ASC");
    $statement->bind_param("sss", $search_term, $search_term, $search_term);
} else {
    $statement = $conn->prepare("SELECT * FROM events WHERE publish_status = 'published' AND event_date >= CURDATE() ORDER BY event_date ASC");
}
$statement->execute();
$query = $statement->get_result();
$capacity_statement = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM registrations
     WHERE event_id = ? AND registration_status = 'active'"
);
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
        <title>Events - Event Ease</title>
    </head>
    <body>
        <?php include 'includes/navbar.php'; ?>
        <section class="py-5">
            <div class="container">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
                    <div>
                        <h2 class="fw-bold mb-2">Available Events</h2>
                        <p class="text-muted mb-0">Browse upcoming experiences and reserve your spot.</p>
                    </div>
                    <form method="GET" class="d-flex gap-2 flex-wrap">
                        <input class="form-control" type="search" name="search" placeholder="Search by event or type" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit">Search</button>
                    </form>
                </div>
                <?php if($query->num_rows == 0): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                        <h3 class="fw-semibold mb-2">No events found</h3>
                        <p class="text-muted mb-0">Try a different search term or check back soon.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php while($row = $query->fetch_assoc()){ $event_id=(int)$row['id']; $capacity_statement->bind_param("i", $event_id); $capacity_statement->execute(); $registered=$capacity_statement->get_result()->fetch_assoc(); $available=max(0, (int)$row['max_participants'] - (int)$registered['total']); ?>
                            <div class="col-lg-4 col-md-6">
                                <div class="card event-card h-100">
                                    <?php if($row['image']!=''){ ?><img src="events/<?php echo rawurlencode($row['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($row['event_name']); ?>"><?php } else { ?><div class="event-placeholder"><i class="fa-solid fa-calendar-days"></i></div><?php } ?>
                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <h5 class="card-title fw-bold mb-0"><?php echo htmlspecialchars($row['event_name']); ?></h5>
                                            <span class="badge bg-soft-primary text-primary"><?php echo htmlspecialchars($row['category'] ?: $row['event_type']); ?></span>
                                        </div>
                                        <p class="text-muted small mb-3"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                        <ul class="list-unstyled text-muted small mb-3">
                                            <li><i class="fa-solid fa-calendar me-2"></i><?php echo htmlspecialchars($row['event_date']); ?>, <?php echo htmlspecialchars(substr($row['start_time'] ?: $row['event_time'], 0, 5)); ?><?php if (!empty($row['end_time'])): ?>–<?php echo htmlspecialchars(substr($row['end_time'], 0, 5)); ?><?php endif; ?></li>
                                            <li><i class="fa-solid fa-location-dot me-2"></i><?php echo htmlspecialchars($row['venue']); ?><?php if (!empty($row['address'])): ?>, <?php echo htmlspecialchars($row['address']); ?><?php endif; ?></li>
                                            <li><i class="fa-solid fa-users me-2"></i><?php echo $available; ?> seats left</li>
                                            <li><i class="fa-solid fa-indian-rupee-sign me-2"></i><?php echo (float)$row['ticket_price'] > 0 ? '₹' . htmlspecialchars(number_format((float)$row['ticket_price'], 2)) : 'Free'; ?> per attendee · Offline payment only</li>
                                        </ul>
                                        <div class="mt-auto">
                                            <div class="d-flex gap-2 mb-2">
                                                <a href="event.php?id=<?php echo $event_id; ?>" class="btn btn-outline-primary w-100">Event details</a>
                                                <button type="button" class="btn btn-outline-secondary" data-share-event data-share-url="event.php?id=<?php echo $event_id; ?>" data-share-title="<?php echo htmlspecialchars($row['event_name'], ENT_QUOTES, 'UTF-8'); ?>" data-share-text="<?php echo htmlspecialchars(trim(strip_tags((string)$row['description'])), ENT_QUOTES, 'UTF-8'); ?>" data-share-label="Share">
                                                    <i class="fa-solid fa-share-nodes" aria-hidden="true"></i><span class="visually-hidden">Share event</span>
                                                </button>
                                            </div>
                                            <?php if ($available <= 0): ?>
                                                <span class="badge bg-danger-subtle text-danger">Event Full</span>
                                            <?php elseif ($current_role === 'customer' && (!$row['registration_open_at'] || $row['registration_open_at'] <= date('Y-m-d H:i:s')) && (!$row['registration_close_at'] || $row['registration_close_at'] >= date('Y-m-d H:i:s'))): ?>
                                                <a href="register_event.php?id=<?php echo $event_id; ?>" class="btn btn-primary w-100">Register Now</a>
                                            <?php elseif ($current_role === 'customer'): ?>
                                                <span class="badge bg-secondary-subtle text-secondary">Registration is closed</span>
                                            <?php elseif ($current_role === 'admin'): ?>
                                                <a href="admin/dashboard.php" class="btn btn-outline-primary w-100">Admin Dashboard</a>
                                            <?php elseif ($current_role === 'organizer'): ?>
                                                <a href="organizer/dashboard.php" class="btn btn-outline-primary w-100">Organizer Dashboard</a>
                                            <?php elseif ($current_role === 'vendor'): ?>
                                                <a href="vendor/dashboard.php" class="btn btn-outline-primary w-100">Vendor Account</a>
                                            <?php else: ?>
                                                <a href="login.php" class="btn btn-outline-primary w-100">Login to Register</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php include 'includes/footer.php'; ?>
    </body>
</html>