<?php
require_once("includes/access_control.php");
eventease_start_session();
include("includes/db.php");
$current_role = eventease_current_role();

/* Upcoming Events */
$query = $conn->query("SELECT * FROM events WHERE publish_status = 'published' AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 3");
$registration_count_statement = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM registrations
     WHERE event_id = ? AND registration_status = 'active'"
);

/* Statistics */
$total_events = $conn->query("SELECT COUNT(*) AS total FROM events WHERE publish_status = 'published'")->fetch_assoc();
$total_users = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc();
$total_registrations = $conn->query("SELECT COUNT(*) AS total FROM registrations")->fetch_assoc();

$site_content = array();
$content_result = $conn->query("SELECT content_key, content_value FROM page_content");
while ($content_row = $content_result->fetch_assoc()) {
    $site_content[$content_row['content_key']] = $content_row['content_value'];
}
$approved_reviews = $conn->query("SELECT event_reviews.rating, event_reviews.review_text, users.name, events.event_name FROM event_reviews INNER JOIN users ON users.id = event_reviews.user_id INNER JOIN events ON events.id = event_reviews.event_id WHERE event_reviews.status = 'Published' ORDER BY event_reviews.created_at DESC LIMIT 6");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventEase - Event Management & Service Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="events/logo.png" type="image/png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>

<body>
    <?php include 'includes/navbar.php'; ?>
    <section class="hero">

        <div class="container">

            <div class="row align-items-center">

                <div class="col-lg-6">

                    <h5 class="hero-sub"><?php echo htmlspecialchars($site_content['home_hero_eyebrow'] ?? 'WELCOME TO EVENTEASE'); ?></h5>
                    <h1 class="hero-title">
                        <?php echo htmlspecialchars($site_content['home_hero_title'] ?? 'Creating'); ?>
                        <span><?php echo htmlspecialchars($site_content['home_hero_highlight'] ?? 'Unforgettable'); ?></span>
                        <?php echo htmlspecialchars($site_content['home_hero_suffix'] ?? 'Moments'); ?>
                    </h1>
                    <p class="hero-text"><?php echo nl2br(htmlspecialchars($site_content['home_hero_description'] ?? 'Plan your weddings, birthdays, corporate meetings and celebrations effortlessly. Book decorations, photography, DJ, catering, and much more all in one place.')); ?></p>

                    <div class="mt-4">

                        <a href="events.php" class="btn btn-warning btn-lg">

                            Explore Events

                        </a>

                        <a href="register.php" class="btn btn-light btn-lg ms-2">

                            Get Started

                        </a>

                    </div>

                </div>

                <div class="col-lg-6 text-center">

                    <img src="events/event1.png" class="img-fluid hero-image">

                </div>

            </div>

        </div>

    </section>
    <!--================ ABOUT ================-->

    <section class="about-section py-5" id="about">

        <div class="container">

            <div class="text-center mb-5">

                <h6 class="section-subtitle">
                    ABOUT US
                </h6>

                <h2 class="section-title"><?php echo htmlspecialchars($site_content['home_about_title'] ?? 'Making Every Celebration'); ?> <span><?php echo htmlspecialchars($site_content['home_about_highlight'] ?? 'Extraordinary'); ?></span></h2>
                <p class="section-text"><?php echo nl2br(htmlspecialchars($site_content['home_about_description'] ?? 'EventEase is an online event management and service booking platform designed to make planning effortless. From weddings and birthdays to corporate events, we connect customers with trusted services like decoration, photography, catering, DJ, and stage setup, all in one place.')); ?></p>

            </div>

            <div class="row align-items-center">

                <div class="col-lg-6">

                    <img src="events/about.jpg" class="img-fluid rounded-4 shadow-lg">

                </div>

                <div class="col-lg-6">

                    <div class="glass-card">

                        <h3>Why EventEase?</h3>

                        <p>

                            We simplify the entire event planning
                            process through a secure and user-friendly
                            platform.

                            Whether you're organizing a birthday,
                            wedding, corporate seminar, or cultural
                            event, EventEase helps you manage everything
                            efficiently.

                        </p>

                        <div class="row mt-4">

                            <div class="col-6 mb-3">

                                <div class="feature-box">

                                    <i class="fas fa-calendar-check"></i>

                                    <h5>Easy Booking</h5>

                                </div>

                            </div>

                            <div class="col-6 mb-3">

                                <div class="feature-box">

                                    <i class="fas fa-user-shield"></i>

                                    <h5>Secure Platform</h5>

                                </div>

                            </div>

                            <div class="col-6">

                                <div class="feature-box">

                                    <i class="fas fa-star"></i>

                                    <h5>Trusted Services</h5>

                                </div>

                            </div>

                            <div class="col-6">

                                <div class="feature-box">

                                    <i class="fas fa-headset"></i>

                                    <h5>24×7 Support</h5>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>
    <!--================ SERVICES ================-->

    <section class="services-section py-5" id="services">

        <div class="container">

            <div class="text-center mb-5">

                <h6 class="section-subtitle">
                    OUR SERVICES
                </h6>

                <h2 class="section-title">
                    Everything You Need For A
                    <span>Perfect Event</span>
                </h2>

                <p class="section-text">

                    From venue decoration to photography,
                    EventEase provides complete event planning
                    services under one platform.

                </p>

            </div>

            <div class="row g-4">

                <div class="col-lg-4 col-md-6">

                    <div class="service-card">

                        <div class="service-icon">

                            <i class="fas fa-ring"></i>

                        </div>

                        <h3>Wedding Planning</h3>

                        <p>

                            Complete wedding planning including
                            decoration, stage setup,
                            photography and catering.

                        </p>

                    </div>

                </div>

                <div class="col-lg-4 col-md-6">

                    <div class="service-card">

                        <div class="service-icon">

                            <i class="fas fa-birthday-cake"></i>

                        </div>

                        <h3>Birthday Events</h3>

                        <p>

                            Celebrate birthdays with beautiful
                            decorations, cakes,
                            music and entertainment.

                        </p>

                    </div>

                </div>

                <div class="col-lg-4 col-md-6">

                    <div class="service-card">

                        <div class="service-icon">

                            <i class="fas fa-camera"></i>

                        </div>

                        <h3>Photography</h3>

                        <p>

                            Professional photographers
                            to capture every beautiful
                            moment of your event.

                        </p>

                    </div>

                </div>

                <div class="col-lg-4 col-md-6">

                    <div class="service-card">

                        <div class="service-icon">

                            <i class="fas fa-utensils"></i>

                        </div>

                        <h3>Catering</h3>

                        <p>

                            Delicious food menus with
                            professional catering
                            services.

                        </p>

                    </div>

                </div>

                <div class="col-lg-4 col-md-6">

                    <div class="service-card">

                        <div class="service-icon">

                            <i class="fas fa-music"></i>

                        </div>

                        <h3>DJ & Music</h3>

                        <p>

                            Professional DJ,
                            sound systems
                            and live music arrangements.

                        </p>

                    </div>

                </div>

                <div class="col-lg-4 col-md-6">

                    <div class="service-card">

                        <div class="service-icon">

                            <i class="fas fa-lightbulb"></i>

                        </div>

                        <h3>Lighting & Stage</h3>

                        <p>

                            Creative lighting,
                            LED walls and
                            modern stage setup.

                        </p>

                    </div>

                </div>

            </div>

        </div>

    </section>
    <!--================ STATISTICS ================-->

<section class="stats-section">

<div class="container">

<div class="row text-center">

<div class="col-lg-3 col-6">

<div class="stat-box">

<i class="fas fa-calendar-check"></i>

<h2 class="counter">

<?php echo $total_events['total']; ?>

</h2>

<p>Total Events</p>

</div>

</div>

<div class="col-lg-3 col-6">

<div class="stat-box">

<i class="fas fa-users"></i>

<h2 class="counter">

<?php echo $total_users['total']; ?>

</h2>

<p>Registered Users</p>

</div>

</div>

<div class="col-lg-3 col-6">

<div class="stat-box">

<i class="fas fa-ticket-alt"></i>

<h2 class="counter">

<?php echo $total_registrations['total']; ?>

</h2>

<p>Bookings</p>

</div>

</div>

<div class="col-lg-3 col-6">

<div class="stat-box">

<i class="fas fa-headset"></i>

<h2>

24/7

</h2>

<p>Support</p>

</div>

</div>

</div>

</div>

</section>
<!--================ UPCOMING EVENTS =================-->

<section class="upcoming-events py-5" id="events">

<div class="container">

<div class="text-center mb-5">

<h6 class="section-subtitle">
UPCOMING EVENTS
</h6>

<h2 class="section-title">

Discover Our

<span>Upcoming Events</span>

</h2>

<p class="section-text">

Register now and be a part of exciting
events organized by EventEase.

</p>

</div>

<div class="row">

<?php if ($query->num_rows === 0): ?>
<div class="col-12 text-center py-4">
    <p class="text-muted mb-3">There are no upcoming events available right now.</p>
    <a href="events.php" class="btn btn-outline-primary">Browse Events</a>
</div>
<?php else: while ($row = $query->fetch_assoc()):
$event_id = (int)$row['id'];
$registration_count_statement->bind_param("i", $event_id);
$registration_count_statement->execute();
$registered = $registration_count_statement->get_result()->fetch_assoc();
$available = max(0, (int)$row['max_participants'] - (int)$registered['total']);
$registration_is_open = (!$row['registration_open_at'] || $row['registration_open_at'] <= date('Y-m-d H:i:s'))
    && (!$row['registration_close_at'] || $row['registration_close_at'] >= date('Y-m-d H:i:s'));
?>

<div class="col-lg-4 mb-4">

<div class="event-card">

<?php if ($row['image'] !== ""): ?>
<img src="events/<?php echo rawurlencode($row['image']); ?>" class="event-img" alt="<?php echo htmlspecialchars($row['event_name']); ?>">
<?php else: ?>
<div class="event-placeholder"><i class="fa-solid fa-calendar-days"></i></div>
<?php endif; ?>

<div class="event-content">

<span class="badge bg-warning">

<?php echo htmlspecialchars($row['category'] ?: $row['event_type']); ?>

</span>

<h3>

<?php echo htmlspecialchars($row['event_name']); ?>

</h3>

<p>

<?php echo htmlspecialchars(substr($row['description'], 0, 90)); ?>

...

</p>

<div class="event-info">

<p>

<i class="fas fa-calendar"></i>

<?php echo htmlspecialchars($row['event_date']); ?>
 · <?php echo htmlspecialchars(substr($row['start_time'] ?: $row['event_time'], 0, 5)); ?>
<?php if (!empty($row['end_time'])): ?>–<?php echo htmlspecialchars(substr($row['end_time'], 0, 5)); ?><?php endif; ?>

</p>

<p>

<i class="fas fa-location-dot"></i>

<?php echo htmlspecialchars($row['venue']); ?>
<?php if (!empty($row['address'])): ?>, <?php echo htmlspecialchars($row['address']); ?><?php endif; ?>

</p>

<p>

<i class="fas fa-users"></i>

Seats Left:

<?php echo $available; ?>

</p>

</div>

<div class="d-flex gap-2 mb-2">
<a href="event.php?id=<?php echo $event_id; ?>" class="btn btn-outline-primary w-100">Event details</a>
<button type="button" class="btn btn-outline-secondary" data-share-event data-share-url="event.php?id=<?php echo $event_id; ?>" data-share-title="<?php echo htmlspecialchars($row['event_name'], ENT_QUOTES, 'UTF-8'); ?>" data-share-text="<?php echo htmlspecialchars(trim(strip_tags((string)$row['description'])), ENT_QUOTES, 'UTF-8'); ?>" data-share-label="Share">
<i class="fa-solid fa-share-nodes" aria-hidden="true"></i><span class="visually-hidden">Share event</span>
</button>
</div>
<?php if ($current_role === 'customer' && $registration_is_open && $available > 0): ?>
<a href="register_event.php?id=<?php echo $event_id; ?>" class="btn btn-warning w-100">Register Now</a>
<?php elseif ($current_role === 'customer'): ?>
<span class="badge bg-secondary-subtle text-secondary"><?php echo $available > 0 ? 'Registration is closed' : 'Event Full'; ?></span>
<?php elseif ($current_role === 'admin'): ?>
<a href="admin/dashboard.php" class="btn btn-outline-primary w-100">Admin Dashboard</a>
<?php elseif ($current_role === 'organizer'): ?>
<a href="organizer/dashboard.php" class="btn btn-outline-primary w-100">Organizer Dashboard</a>
<?php elseif ($current_role === 'vendor'): ?>
<a href="vendor/dashboard.php" class="btn btn-outline-primary w-100">Vendor Account</a>
<?php else: ?>
<a href="login.php" class="btn btn-primary w-100">Login to Register</a>
<?php endif; ?>

</div>

</div>

</div>

<?php endwhile; endif; ?>

</div>

</div>

</section>
<!--================ GALLERY =================-->

<section class="gallery-section py-5">

<div class="container">

<div class="text-center mb-5">

<h6 class="section-subtitle">

OUR GALLERY

</h6>

<h2 class="section-title">

Captured

<span>Memories</span>

</h2>

<p class="section-text">

A glimpse of our successful events,
beautiful decorations,
and unforgettable celebrations.

</p>

</div>

<div class="row g-4">

<div class="col-lg-4 col-md-6">

<div class="gallery-card">

<img src="events/g1.jpg">

<div class="overlay">

<i class="fas fa-search-plus"></i>

</div>

</div>

</div>

<div class="col-lg-4 col-md-6">

<div class="gallery-card">

<img src="events/g2.jpg">

<div class="overlay">

<i class="fas fa-search-plus"></i>

</div>

</div>

</div>

<div class="col-lg-4 col-md-6">

<div class="gallery-card">

<img src="events/g3.jpg">

<div class="overlay">

<i class="fas fa-search-plus"></i>

</div>

</div>

</div>

</div>

</div>

</section>
<!--================ TESTIMONIALS =================-->

<section class="testimonial-section py-5">
<div class="container">
    <div class="text-center mb-5">
        <h6 class="section-subtitle">EVENT REVIEWS</h6>
        <h2 class="section-title">What Our <span>Attendees Say</span></h2>
        <p class="section-text">Reviews are published after admin approval.</p>
    </div>
    <?php if ($approved_reviews->num_rows === 0): ?>
        <p class="text-center text-muted mb-0">There are no published event reviews yet.</p>
    <?php else: ?>
        <div class="row g-4">
            <?php while ($review = $approved_reviews->fetch_assoc()): ?>
                <?php $rating = max(1, min(5, (int)$review['rating'])); $initial = strtoupper(substr(trim($review['name']), 0, 1)); ?>
                <div class="col-lg-4 col-md-6">
                    <article class="testimonial-card h-100">
                        <div class="client-avatar" aria-hidden="true"><?php echo htmlspecialchars($initial); ?></div>
                        <h3 class="h5 fw-bold"><?php echo htmlspecialchars($review['name']); ?></h3>
                        <p class="client-role mb-2">Attendee · <?php echo htmlspecialchars($review['event_name']); ?></p>
                        <div class="stars" aria-label="<?php echo $rating; ?> out of 5 stars"><?php echo str_repeat('★', $rating); ?><span class="text-muted"><?php echo str_repeat('☆', 5 - $rating); ?></span></div>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                    </article>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>
</section>
<!--================ CONTACT =================-->

<section class="contact-section py-5" id="contact">

<div class="container">

<div class="text-center mb-5">

<h6 class="section-subtitle">

CONTACT US

</h6>

<h2 class="section-title">

Let's Plan Your

<span>Next Event</span>

</h2>

<p class="section-text">

Have questions or need assistance?
Our team is ready to help you create unforgettable experiences.

</p>

</div>

<div class="row">

<div class="col-lg-5">

<div class="contact-info">

<div class="info-box">

<i class="fas fa-location-dot"></i>

<div>

<h5>Office</h5>

<p>Chennai, Tamil Nadu, India</p>

</div>

</div>

<div class="info-box">

<i class="fas fa-phone"></i>

<div>

<h5>Phone</h5>

<p>+91 98765 43210</p>

</div>

</div>

<div class="info-box">

<i class="fas fa-envelope"></i>

<div>

<h5>Email</h5>

<p>support@eventease.com</p>

</div>

</div>

<div class="info-box">

<i class="fas fa-clock"></i>

<div>

<h5>Working Hours</h5>

<p>Mon - Sat : 9 AM - 7 PM</p>

</div>

</div>

</div>

</div>

<div class="col-lg-7">

<div class="contact-card">

<h3>Need Event Services?</h3>

<p>

To request decoration,
photography,
DJ,
lighting,
or catering services,

please login and submit a
Service Request through your dashboard.

</p>

<a href="login.php"
class="btn btn-warning btn-lg mt-3">

Request a Service

</a>

</div>

</div>

</div>

</div>

</section>
<?php include 'includes/footer.php'; ?>
</body>