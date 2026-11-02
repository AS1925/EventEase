<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('user');
require_once __DIR__ . '/../includes/db.php';

$user_id = (int)$_SESSION['user_id'];
$event_id_input = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['event_id'] ?? '')
    : ($_GET['id'] ?? '');
if (!is_string($event_id_input) || !ctype_digit($event_id_input)) {
    header('Location: my_events.php');
    exit();
}
$event_id = (int)$event_id_input;
$message = isset($_GET['submitted']) && $_GET['submitted'] === '1'
    ? 'Your question was sent to the event organizer.'
    : '';
$message_type = 'success';

$event_statement = $conn->prepare(
    "SELECT events.id, events.event_name, events.event_date
     FROM events
     INNER JOIN registrations ON registrations.event_id = events.id
     WHERE events.id = ? AND registrations.user_id = ?
       AND registrations.registration_status = 'active'
     LIMIT 1"
);
$event_statement->bind_param('ii', $event_id, $user_id);
$event_statement->execute();
$event = $event_statement->get_result()->fetch_assoc();
if (!$event) {
    http_response_code(404);
    exit('Event questions are available to active registrants only.');
}

$question_text = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $question_text = isset($_POST['question_text']) && is_string($_POST['question_text'])
        ? trim($_POST['question_text'])
        : '';
    if ($question_text === '' || strlen($question_text) > 12000) {
        $message = 'Enter a question of up to 3,000 characters.';
        $message_type = 'danger';
    } else {
        $insert = $conn->prepare(
            "INSERT INTO event_questions (event_id, user_id, question_text, status)
             VALUES (?, ?, ?, 'pending')"
        );
        $insert->bind_param('iis', $event_id, $user_id, $question_text);
        $insert->execute();
        header('Location: event_questions.php?id=' . $event_id . '&submitted=1');
        exit();
    }
}

$questions_statement = $conn->prepare(
    'SELECT event_questions.question_text, event_questions.answer_text, event_questions.status,
            event_questions.created_at, event_questions.answered_at, users.name AS answerer_name
     FROM event_questions
     LEFT JOIN users ON users.id = event_questions.answered_by
     WHERE event_questions.event_id = ? AND event_questions.user_id = ?
     ORDER BY event_questions.created_at DESC, event_questions.id DESC'
);
$questions_statement->bind_param('ii', $event_id, $user_id);
$questions_statement->execute();
$questions = $questions_statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Questions - EventEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3"><?php include '../includes/user_sidebar.php'; ?></div>
            <main class="col-lg-9">
                <section class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <h1 class="h3 fw-bold mb-1">Questions for <?php echo htmlspecialchars($event['event_name']); ?></h1>
                    <p class="text-muted"><?php echo htmlspecialchars($event['event_date']); ?></p>
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    <form method="POST" class="d-grid gap-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                        <input type="hidden" name="event_id" value="<?php echo (int)$event_id; ?>">
                        <div>
                            <label class="form-label" for="question-text">Ask the organizer</label>
                            <textarea id="question-text" name="question_text" class="form-control" rows="4" maxlength="3000" required><?php echo htmlspecialchars($question_text); ?></textarea>
                        </div>
                        <div><button type="submit" class="btn btn-primary">Send question</button></div>
                    </form>
                </section>
                <section class="card border-0 shadow-sm rounded-4 p-4">
                    <h2 class="h5 fw-bold mb-3">Your questions and replies</h2>
                    <?php if ($questions->num_rows === 0): ?>
                        <p class="text-muted mb-0">You have not asked a question about this event yet.</p>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php while ($question = $questions->fetch_assoc()): ?>
                                <article class="border rounded-3 p-3">
                                    <div class="d-flex justify-content-between gap-2 mb-2">
                                        <span class="badge <?php echo $question['status'] === 'answered' ? 'bg-success' : ($question['status'] === 'closed' ? 'bg-secondary' : 'bg-warning text-dark'); ?>">
                                            <?php echo htmlspecialchars(ucfirst($question['status'])); ?>
                                        </span>
                                        <small class="text-muted"><?php echo htmlspecialchars($question['created_at']); ?></small>
                                    </div>
                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                                    <?php if ($question['answer_text'] !== null && $question['answer_text'] !== ''): ?>
                                        <div class="bg-light rounded-3 p-3">
                                            <strong>Organizer reply<?php echo $question['answerer_name'] ? ' · ' . htmlspecialchars($question['answerer_name']) : ''; ?></strong>
                                            <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($question['answer_text'])); ?></p>
                                            <?php if ($question['answered_at']): ?><small class="text-muted"><?php echo htmlspecialchars($question['answered_at']); ?></small><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
