<?php
require_once __DIR__ . '/includes/session.php';
eventease_start_session();
include("includes/db.php");
$about_content = array();
$about_result = $conn->query("SELECT content_key, content_value FROM page_content WHERE content_key IN ('about_title', 'about_description')");
while ($about_row = $about_result->fetch_assoc()) {
    $about_content[$about_row['content_key']] = $about_row['content_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="events/logo.png" type="image/png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <section class="py-5">
        <div class="container py-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="brand-pill mb-3"><i class="fa-solid fa-sparkles"></i> Why EventEase</div>
                    <h1 class="page-title mb-3"><?php echo htmlspecialchars($about_content['about_title'] ?? 'Crafting polished experiences for every celebration.'); ?></h1>
                    <p class="page-subtitle"><?php echo nl2br(htmlspecialchars($about_content['about_description'] ?? 'EventEase combines event discovery, registration, and service requests into one elegant platform designed for modern planners and guests.')); ?></p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="events.php" class="btn btn-primary">Explore Events</a>
                        <a href="contact.php" class="btn btn-outline-primary">Contact Us</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="glass-card">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="feature-box h-100">
                                    <i class="fa-solid fa-calendar-check"></i>
                                    <h5 class="fw-bold">Smart Scheduling</h5>
                                    <p class="mb-0">Plan and manage events from start to finish with clarity.</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="feature-box h-100">
                                    <i class="fa-solid fa-users"></i>
                                    <h5 class="fw-bold">Seamless Registrations</h5>
                                    <p class="mb-0">Guests can register in seconds without any friction.</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="feature-box h-100">
                                    <i class="fa-solid fa-handshake"></i>
                                    <h5 class="fw-bold">Trusted Services</h5>
                                    <p class="mb-0">Book photography, décor, catering, and more from one dashboard.</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="feature-box h-100">
                                    <i class="fa-solid fa-chart-line"></i>
                                    <h5 class="fw-bold">Actionable Insights</h5>
                                    <p class="mb-0">Admin tools help keep every event organized and visible.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php include 'includes/footer.php'; ?>
</body>
</html>