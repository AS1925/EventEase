<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
include("../includes/access_control.php");
require_role("admin", "organizer");
include("../includes/db.php");
$role = eventease_current_role();
$is_admin = $role === 'admin';
$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !ctype_digit($_POST['id'])) {
    header("Location: view_events.php");
    exit();
}

$id = (int)$_POST['id'];
if ($is_admin) {
    $image_query = $conn->prepare("SELECT image FROM events WHERE id = ?");
    $image_query->bind_param("i", $id);
} else {
    $image_query = $conn->prepare("SELECT image FROM events WHERE id = ? AND organizer_id = ?");
    $image_query->bind_param("ii", $id, $user_id);
}
$image_query->execute();
$event = $image_query->get_result()->fetch_assoc();

if ($event) {
    $conn->begin_transaction();
    $delete_registrations = $conn->prepare("DELETE FROM registrations WHERE event_id = ?");
    $delete_registrations->bind_param("i", $id);
    $registrations_deleted = $delete_registrations->execute();

    $delete_reviews = $conn->prepare("DELETE FROM event_reviews WHERE event_id = ?");
    $delete_reviews->bind_param("i", $id);
    $reviews_deleted = $delete_reviews->execute();

    if ($is_admin) {
        $delete_event = $conn->prepare("DELETE FROM events WHERE id = ?");
        $delete_event->bind_param("i", $id);
    } else {
        $delete_event = $conn->prepare("DELETE FROM events WHERE id = ? AND organizer_id = ?");
        $delete_event->bind_param("ii", $id, $user_id);
    }
    if ($registrations_deleted && $reviews_deleted && $delete_event->execute()) {
        $conn->commit();
        if ($event['image'] !== "" && is_file("../events/" . $event['image'])) {
            unlink("../events/" . $event['image']);
        }
    } else {
        $conn->rollback();
    }
}

header("Location: view_events.php");
exit();
?>