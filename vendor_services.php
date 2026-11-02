<?php
require_once __DIR__ . '/includes/session.php';
eventease_start_session();
require_once __DIR__ . '/includes/db.php';

$category_filter = isset($_GET['category']) && is_string($_GET['category'])
    ? trim($_GET['category'])
    : '';
$category_statement = $conn->prepare(
    "SELECT DISTINCT vendor_services.category
     FROM vendor_services
     INNER JOIN vendor_profiles ON vendor_profiles.user_id = vendor_services.vendor_id
     WHERE vendor_services.is_active = 1 AND vendor_profiles.verification_status = 'verified'
     ORDER BY vendor_services.category"
);
$category_statement->execute();
$categories = $category_statement->get_result();

$services_statement = $conn->prepare(
    "SELECT vendor_services.id, vendor_services.vendor_id, vendor_services.category,
            vendor_services.service_name, vendor_services.description,
            vendor_services.package_name, vendor_services.package_details,
            vendor_services.base_price, vendor_profiles.business_name,
            vendor_profiles.description AS business_description,
            vendor_profiles.service_area,
            (SELECT ROUND(AVG(vendor_reviews.rating), 1)
             FROM vendor_reviews
             WHERE vendor_reviews.vendor_id = vendor_profiles.user_id
               AND vendor_reviews.status = 'published') AS average_rating,
            (SELECT COUNT(*) FROM vendor_reviews
             WHERE vendor_reviews.vendor_id = vendor_profiles.user_id
               AND vendor_reviews.status = 'published') AS review_count,
            (SELECT image_path FROM vendor_portfolio
             WHERE vendor_portfolio.vendor_id = vendor_profiles.user_id
             ORDER BY vendor_portfolio.created_at DESC, vendor_portfolio.id DESC
             LIMIT 1) AS portfolio_image,
            (SELECT GROUP_CONCAT(CONCAT(DATE_FORMAT(vendor_availability.available_date, '%b %e'),
                                        ' ', LEFT(vendor_availability.start_time, 5),
                                        '-', LEFT(vendor_availability.end_time, 5))
                                 ORDER BY vendor_availability.available_date, vendor_availability.start_time
                                 SEPARATOR ' · ')
             FROM vendor_availability
             WHERE vendor_availability.vendor_id = vendor_profiles.user_id
               AND vendor_availability.status = 'available'
               AND vendor_availability.available_date >= CURDATE()) AS upcoming_availability
     FROM vendor_services
     INNER JOIN vendor_profiles ON vendor_profiles.user_id = vendor_services.vendor_id
     WHERE vendor_services.is_active = 1
       AND vendor_profiles.verification_status = 'verified'
       AND (? = '' OR vendor_services.category = ?)
     ORDER BY vendor_services.category, vendor_profiles.business_name, vendor_services.base_price"
);
$services_statement->bind_param('ss', $category_filter, $category_filter);
$services_statement->execute();
$services = $services_statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Marketplace - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="events/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="services-page-shell">
    <?php include 'includes/navbar.php'; ?>

    <main class="services-page">
        <?php if (eventease_current_role() === 'organizer'): ?>
            <div class="container pt-4">
                <div class="alert alert-info mb-0" role="status">
                    Organizer marketplace: browse verified vendors and their active packages, then send a request for one of your upcoming events.
                </div>
            </div>
        <?php endif; ?>
        <section class="services-hero">
            <div class="container services-hero-inner">
                <p class="services-eyebrow">OUR SERVICES</p>
                <h1>Everything you need for a magical celebration.</h1>
                <p class="services-subtitle">Book high-quality services with confidence and let EventEase bring your event vision to life.</p>
                <a href="#vendor-packages" class="btn btn-primary services-cta-btn">Book Decorators</a>
            </div>
        </section>

        <section class="container services-showcase">
            <div class="services-grid">
                <article class="service-feature-card">
                    <div class="service-icon-wrap"><i class="fa-solid fa-ring"></i></div>
                    <h2>Wedding Planning</h2>
                    <p>Elegant decor, stage design, photography, and hospitality planning tailored to your big day.</p>
                </article>

                <article class="service-feature-card">
                    <div class="service-icon-wrap"><i class="fa-solid fa-cake-candles"></i></div>
                    <h2>Birthday Styling</h2>
                    <p>Colorful decor, cakes, entertainment, and celebration setup for every age and theme.</p>
                </article>

                <article class="service-feature-card">
                    <div class="service-icon-wrap"><i class="fa-solid fa-camera"></i></div>
                    <h2>Photography</h2>
                    <p>Professional photography packages that capture every memory in detail and style.</p>
                </article>

                <article class="service-feature-card">
                    <div class="service-icon-wrap"><i class="fa-solid fa-utensils"></i></div>
                    <h2>Catering</h2>
                    <p>Delicious food and beverage services designed to suit your event size and menu preferences.</p>
                </article>

                <article class="service-feature-card">
                    <div class="service-icon-wrap"><i class="fa-solid fa-music"></i></div>
                    <h2>DJ &amp; Sound</h2>
                    <p>Professional DJ, music, and sound setup to keep the energy alive throughout your event.</p>
                </article>

                <article class="service-feature-card">
                    <div class="service-icon-wrap"><i class="fa-solid fa-lightbulb"></i></div>
                    <h2>Lighting &amp; Stage</h2>
                    <p>Creative lighting and stage solutions that make your event feel polished and premium.</p>
                </article>
            </div>
        </section>

        <section id="vendor-packages" class="services-marketplace">
            <div class="container">
                <div class="services-marketplace-header">
                    <div>
                        <p class="services-mini-label">Verified vendors</p>
                        <h3>Explore service packages</h3>
                    </div>
                    <form method="GET" class="services-filter-form">
                        <label class="visually-hidden" for="category">Filter by category</label>
                        <select id="category" name="category" class="form-select">
                            <option value="">All categories</option>
                            <?php while ($category = $categories->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($category['category']); ?>" <?php echo $category_filter === $category['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['category']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-primary">Filter</button>
                    </form>
                </div>

                <?php if ($services->num_rows === 0): ?>
                    <div class="empty-marketplace-card">
                        <p>No verified vendor packages match this category yet.</p>
                    </div>
                <?php else: ?>
                    <div class="marketplace-grid row g-4">
                        <?php while ($service = $services->fetch_assoc()): ?>
                            <div class="col-md-6 col-xl-4">
                                <article class="package-card h-100">
                                    <?php if (!empty($service['portfolio_image'])): ?>
                                        <img src="events/vendor_portfolio/<?php echo rawurlencode(basename($service['portfolio_image'])); ?>" alt="<?php echo htmlspecialchars($service['business_name']); ?> portfolio" class="package-card-image">
                                    <?php else: ?>
                                        <div class="package-card-placeholder">
                                            <i class="fa-solid fa-store"></i>
                                        </div>
                                    <?php endif; ?>

                                    <div class="package-card-body d-flex flex-column">
                                        <div class="package-card-top">
                                            <span class="category-badge"><?php echo htmlspecialchars($service['category']); ?></span>
                                            <?php if ($service['average_rating'] !== null): ?>
                                                <span class="rating-badge">★ <?php echo htmlspecialchars((string)$service['average_rating']); ?> <small>(<?php echo (int)$service['review_count']; ?>)</small></span>
                                            <?php endif; ?>
                                        </div>

                                        <h4><?php echo htmlspecialchars($service['package_name']); ?></h4>
                                        <p class="package-provider"><?php echo htmlspecialchars($service['service_name']); ?> · <?php echo htmlspecialchars($service['business_name']); ?></p>
                                        <?php if (!empty($service['service_area'])): ?><p class="package-meta"><?php echo htmlspecialchars($service['service_area']); ?></p><?php endif; ?>
                                        <p class="package-availability"><?php echo !empty($service['upcoming_availability']) ? '<strong>Available soon:</strong> ' . htmlspecialchars($service['upcoming_availability']) : 'Contact vendor to confirm availability.'; ?></p>
                                        <?php if (!empty($service['business_description'])): ?><p class="package-summary"><?php echo nl2br(htmlspecialchars($service['business_description'])); ?></p><?php endif; ?>
                                        <p class="package-details"><?php echo nl2br(htmlspecialchars($service['package_details'] ?: $service['description'])); ?></p>
                                        <?php if (!empty($service['upcoming_availability'])): ?><p class="package-availability"><strong>Upcoming:</strong> <?php echo htmlspecialchars($service['upcoming_availability']); ?></p><?php endif; ?>

                                        <div class="package-footer mt-auto">
                                            <p class="package-price">From <?php echo htmlspecialchars(number_format((float)$service['base_price'], 2)); ?></p>
                                            <?php if (eventease_current_role() === 'customer'): ?>
                                                <a href="user/service_request.php?service_id=<?php echo (int)$service['id']; ?>" class="btn btn-primary w-100">Request this package</a>
                                            <?php elseif (eventease_current_role() === 'organizer'): ?>
                                                <a href="organizer/vendor_requests.php?service_id=<?php echo (int)$service['id']; ?>" class="btn btn-primary w-100">Request for your event</a>
                                            <?php elseif (!isset($_SESSION['user_id'])): ?>
                                                <a href="login.php" class="btn btn-outline-primary w-100">Sign in to request</a>
                                            <?php else: ?>
                                                <p class="small text-muted mb-0">Customer accounts can request this service.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
