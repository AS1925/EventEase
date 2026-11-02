<?php
$active_page = basename($_SERVER['PHP_SELF']);
$organizer_path = strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../organizer/' : '';
$events_path = strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'view_events.php' : '../admin/view_events.php';
?>
<div class="sidebar-card">
    <div class="sidebar-brand">
        <h5 class="mb-1">Organizer Panel</h5>
        <small class="text-muted">EventEase</small>
    </div>
    <div class="list-group list-group-flush">
        <a href="<?php echo $organizer_path; ?>dashboard.php" class="list-group-item list-group-item-action <?php echo $active_page === 'dashboard.php' && strpos($_SERVER['PHP_SELF'], '/admin/') === false ? 'active' : ''; ?>">Dashboard</a>
        <a href="<?php echo $events_path; ?>" class="list-group-item list-group-item-action <?php echo $active_page === 'view_events.php' ? 'active' : ''; ?>">My Events</a>
        <a href="../vendor_services.php" class="list-group-item list-group-item-action <?php echo $active_page === 'vendor_services.php' ? 'active' : ''; ?>">Vendor Marketplace</a>
        <a href="<?php echo $organizer_path; ?>vendor_requests.php" class="list-group-item list-group-item-action <?php echo $active_page === 'vendor_requests.php' ? 'active' : ''; ?>">Vendor Requests</a>
        <a href="<?php echo $organizer_path; ?>registrations.php" class="list-group-item list-group-item-action <?php echo $active_page === 'registrations.php' ? 'active' : ''; ?>">Registrations</a>
        <a href="<?php echo $organizer_path; ?>attendance.php" class="list-group-item list-group-item-action <?php echo $active_page === 'attendance.php' ? 'active' : ''; ?>">Attendance</a>
        <a href="<?php echo $organizer_path; ?>questions.php" class="list-group-item list-group-item-action <?php echo $active_page === 'questions.php' ? 'active' : ''; ?>">Event Questions</a>
        <a href="<?php echo $organizer_path; ?>reviews.php" class="list-group-item list-group-item-action <?php echo $active_page === 'reviews.php' ? 'active' : ''; ?>">Event Reviews</a>
        <a href="<?php echo $organizer_path; ?>profile.php" class="list-group-item list-group-item-action <?php echo $active_page === 'profile.php' ? 'active' : ''; ?>">Organizer Profile</a>
        <form action="../logout.php" method="POST" class="m-0">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
            <button type="submit" class="list-group-item list-group-item-action text-danger text-start w-100 border-0 bg-transparent">Logout</button>
        </form>
    </div>
</div>
