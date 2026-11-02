<?php
require_once("includes/access_control.php");
eventease_start_session();
include("includes/db.php");

$contact_message = "";
$contact_name = "";
$contact_email = "";
$contact_subject = "";
$contact_body = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $contact_name = trim($_POST['name'] ?? "");
    $contact_email = trim($_POST['email'] ?? "");
    $contact_subject = trim($_POST['subject'] ?? "");
    $contact_body = trim($_POST['message'] ?? "");

    if ($contact_name === "" || !filter_var($contact_email, FILTER_VALIDATE_EMAIL) || $contact_subject === "" || $contact_body === "") {
        $contact_message = "Please complete all fields with a valid email address.";
    } else {
        $statement = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $statement->bind_param("ssss", $contact_name, $contact_email, $contact_subject, $contact_body);
        if ($statement->execute()) {
            $contact_message = "Your message has been sent. Our team will be in touch.";
            $contact_name = $contact_email = $contact_subject = $contact_body = "";
        } else {
            $contact_message = "Your message could not be sent. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="events/logo.png" type="image/png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <section class="py-5">
        <div class="container py-4">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                        <div class="brand-pill mb-3"><i class="fa-solid fa-headset"></i> Get in touch</div>
                        <h2 class="fw-bold mb-3">We’re here to help with every event detail.</h2>
                        <p class="text-muted">Whether you need help planning a wedding, a corporate meetup, or a private celebration, our team is ready to assist.</p>
                        <div class="d-flex flex-column gap-3 mt-4">
                            <div class="info-box"><i class="fa-solid fa-location-dot"></i><div><h6 class="mb-1">Visit us</h6><p class="mb-0 text-muted">123 Celebration Avenue, Colombo</p></div></div>
                            <div class="info-box"><i class="fa-solid fa-phone"></i><div><h6 class="mb-1">Call us</h6><p class="mb-0 text-muted">+94 71 234 5678</p></div></div>
                            <div class="info-box"><i class="fa-solid fa-envelope"></i><div><h6 class="mb-1">Email</h6><p class="mb-0 text-muted">hello@eventease.com</p></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
                        <h3 class="fw-bold mb-3">Send a message</h3>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                            <?php if ($contact_message !== ""): ?><div class="alert <?php echo strpos($contact_message, 'could not') === false && strpos($contact_message, 'Please complete') === false ? 'alert-success' : 'alert-danger'; ?>" role="alert"><?php echo htmlspecialchars($contact_message); ?></div><?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="contact-name">Name</label>
                                    <input id="contact-name" type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($contact_name); ?>" placeholder="Your full name" autocomplete="name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="contact-email">Email</label>
                                    <input id="contact-email" type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($contact_email); ?>" placeholder="Your email address" autocomplete="email" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="contact-subject">Subject</label>
                                    <input id="contact-subject" type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($contact_subject); ?>" placeholder="How can we help?" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="contact-message">Message</label>
                                    <textarea id="contact-message" name="message" class="form-control" rows="5" placeholder="Tell us about your plans" required><?php echo htmlspecialchars($contact_body); ?></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-4">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php include 'includes/footer.php'; ?>
</body>
</html>