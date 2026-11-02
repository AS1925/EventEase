<?php
$active_page = basename($_SERVER['PHP_SELF']);
$admin_path = strpos($_SERVER['PHP_SELF'], '/organizer/') !== false ? '../admin/' : '';
$attendance_path = strpos($_SERVER['PHP_SELF'], '/organizer/') !== false
    ? 'attendance.php'
    : '../organizer/attendance.php';
?>
<div class="sidebar-card">
    <div class="sidebar-brand">
        <h5 class="mb-1">Admin Panel</h5>
        <small class="text-muted">EventEase</small>
    </div>
    <div class="list-group list-group-flush">
        <a href="<?php echo $admin_path; ?>dashboard.php" class="list-group-item list-group-item-action <?php echo $active_page === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
        <a href="<?php echo $admin_path; ?>add_event.php" class="list-group-item list-group-item-action <?php echo $active_page === 'add_event.php' ? 'active' : ''; ?>">Add Event</a>
        <a href="<?php echo $admin_path; ?>view_events.php" class="list-group-item list-group-item-action <?php echo $active_page === 'view_events.php' ? 'active' : ''; ?>">Manage Events</a>
        <a href="<?php echo $admin_path; ?>manage_organizers.php" class="list-group-item list-group-item-action <?php echo $active_page === 'manage_organizers.php' ? 'active' : ''; ?>">Verify Organizers</a>
        <a href="<?php echo $admin_path; ?>manage_vendors.php" class="list-group-item list-group-item-action <?php echo $active_page === 'manage_vendors.php' ? 'active' : ''; ?>">Verify Vendors</a>
        <a href="<?php echo $admin_path; ?>view_registrations.php" class="list-group-item list-group-item-action <?php echo $active_page === 'view_registrations.php' ? 'active' : ''; ?>">Registrations</a>
        <a href="<?php echo $attendance_path; ?>" class="list-group-item list-group-item-action <?php echo $active_page === 'attendance.php' ? 'active' : ''; ?>">Attendance</a>
        <a href="<?php echo $admin_path; ?>view_service_requests.php" class="list-group-item list-group-item-action <?php echo $active_page === 'view_service_requests.php' ? 'active' : ''; ?>">Service Requests</a>
        <a href="<?php echo $admin_path; ?>enquiries.php" class="list-group-item list-group-item-action <?php echo $active_page === 'enquiries.php' ? 'active' : ''; ?>">Contact Enquiries</a>
        <a href="<?php echo $admin_path; ?>manage_reviews.php" class="list-group-item list-group-item-action <?php echo $active_page === 'manage_reviews.php' ? 'active' : ''; ?>">Event Reviews</a>
        <a href="<?php echo $admin_path; ?>manage_vendor_reviews.php" class="list-group-item list-group-item-action <?php echo $active_page === 'manage_vendor_reviews.php' ? 'active' : ''; ?>">Vendor Reviews</a>
        <a href="<?php echo $admin_path; ?>manage_questions.php" class="list-group-item list-group-item-action <?php echo $active_page === 'manage_questions.php' ? 'active' : ''; ?>">Event Questions</a>
        <a href="<?php echo $admin_path; ?>reports.php" class="list-group-item list-group-item-action <?php echo $active_page === 'reports.php' ? 'active' : ''; ?>">Reports</a>
        <a href="<?php echo $admin_path; ?>manage_content.php" class="list-group-item list-group-item-action <?php echo $active_page === 'manage_content.php' ? 'active' : ''; ?>">Edit Public Content</a>
        <form action="../logout.php" method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to logout?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <button type="submit" class="list-group-item list-group-item-action text-danger text-start w-100 border-0 bg-transparent">Logout</button>
        </form>
    </div>
</div>
