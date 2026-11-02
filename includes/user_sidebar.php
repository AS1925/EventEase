<?php
$active_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-card">
    <div class="sidebar-brand">
        <h5 class="mb-1">My Account</h5>
        <small class="text-muted">Welcome, <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?></small>
    </div>
    <div class="list-group list-group-flush">
        <a href="dashboard.php" class="list-group-item list-group-item-action <?php echo $active_page === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
        <a href="profile.php" class="list-group-item list-group-item-action <?php echo $active_page === 'profile.php' ? 'active' : ''; ?>">Profile</a>
        <a href="my_events.php" class="list-group-item list-group-item-action <?php echo $active_page === 'my_events.php' ? 'active' : ''; ?>">My Events</a>
        <a href="service_request.php" class="list-group-item list-group-item-action <?php echo $active_page === 'service_request.php' ? 'active' : ''; ?>">Service Request</a>
        <a href="my_requests.php" class="list-group-item list-group-item-action <?php echo $active_page === 'my_requests.php' ? 'active' : ''; ?>">My Requests</a>
        <a href="../vendor_services.php" class="list-group-item list-group-item-action <?php echo $active_page === 'vendor_services.php' ? 'active' : ''; ?>">Vendor Marketplace</a>
        <form action="../logout.php" method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to logout?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <button type="submit" class="list-group-item list-group-item-action text-danger text-start w-100 border-0 bg-transparent">Logout</button>
        </form>
    </div>
</div>
