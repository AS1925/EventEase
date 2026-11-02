<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('organizer');
require_once __DIR__ . '/../includes/db.php';

$organizer_id = (int)$_SESSION['user_id'];
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $question_id_input = isset($_POST['question_id']) && is_string($_POST['question_id'])
        ? $_POST['question_id']
        : '';
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    if (!ctype_digit($question_id_input) || !in_array($action, array('reply', 'close'), true)) {
        $message = 'The question update was invalid.';
        $message_type = 'danger';
    } else {
        $question_id = (int)$question_id_input;
        if ($action === 'close') {
            $update = $conn->prepare(
                "UPDATE event_questions
                 INNER JOIN events ON events.id = event_questions.event_id
                 SET event_questions.status = 'closed',
                     event_questions.closed_by = ?,
                     event_questions.closed_at = NOW()
                 WHERE event_questions.id = ? AND events.organizer_id = ?
                   AND event_questions.status <> 'closed'"
            );
            $update->bind_param('iii', $organizer_id, $question_id, $organizer_id);
            $update->execute();
            $message = $update->affected_rows === 1
                ? 'The question was closed.'
                : 'The question could not be closed. It may already be closed or not belong to your event.';
            $message_type = $update->affected_rows === 1 ? 'success' : 'warning';
        } else {
            $answer_text = isset($_POST['answer_text']) && is_string($_POST['answer_text'])
                ? trim($_POST['answer_text'])
                : '';
            if ($answer_text === '' || strlen($answer_text) > 12000) {
                $message = 'Enter a reply of up to 3,000 characters.';
                $message_type = 'danger';
            } else {
                $update = $conn->prepare(
                    "UPDATE event_questions
                     INNER JOIN events ON events.id = event_questions.event_id
                     SET event_questions.answer_text = ?,
                         event_questions.status = 'answered',
                         event_questions.answered_by = ?,
                         event_questions.answered_at = NOW()
                     WHERE event_questions.id = ? AND events.organizer_id = ?
                       AND event_questions.status <> 'closed'"
                );
                $update->bind_param('siii', $answer_text, $organizer_id, $question_id, $organizer_id);
                $update->execute();
                $message = $update->affected_rows === 1
                    ? 'Your reply was saved.'
                    : 'The reply could not be saved. The question may be closed or not belong to your event.';
                $message_type = $update->affected_rows === 1 ? 'success' : 'warning';
            }
        }
    }
}

$status_filter = isset($_GET['status']) && is_string($_GET['status'])
    && in_array($_GET['status'], array('pending', 'answered', 'closed'), true)
    ? $_GET['status']
    : '';
$questions_statement = $conn->prepare(
    'SELECT event_questions.id, event_questions.question_text, event_questions.answer_text,
            event_questions.status, event_questions.created_at, event_questions.answered_at,
            users.name AS participant_name, events.event_name
     FROM event_questions
     INNER JOIN events ON events.id = event_questions.event_id
     INNER JOIN users ON users.id = event_questions.user_id
     WHERE events.organizer_id = ? AND (? = \'\' OR event_questions.status = ?)
     ORDER BY FIELD(event_questions.status, \'pending\', \'answered\', \'closed\'),
              event_questions.created_at DESC'
);
$questions_statement->bind_param('iss', $organizer_id, $status_filter, $status_filter);
$questions_statement->execute();
$questions = $questions_statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Questions - EventEase Organizer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3"><?php include '../includes/organizer_sidebar.php'; ?></div>
            <main class="col-lg-9">
                <section class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h1 class="h3 fw-bold mb-1">Event Questions</h1>
                            <p class="text-muted mb-0">Reply to questions for your events or close them when resolved.</p>
                        </div>
                        <form method="GET" class="d-flex gap-2 align-items-center">
                            <label class="visually-hidden" for="question-status">Filter status</label>
                            <select id="question-status" name="status" class="form-select">
                                <option value="">All statuses</option>
                                <?php foreach (array('pending', 'answered', 'closed') as $status): ?>
                                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($status)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-outline-primary">Filter</button>
                        </form>
                    </div>
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    <?php if ($questions->num_rows === 0): ?>
                        <p class="text-muted mb-0">There are no matching questions for your events.</p>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php while ($question = $questions->fetch_assoc()): ?>
                                <article class="border rounded-3 p-3 p-md-4">
                                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                        <div>
                                            <h2 class="h5 fw-bold mb-1"><?php echo htmlspecialchars($question['event_name']); ?></h2>
                                            <small class="text-muted">From <?php echo htmlspecialchars($question['participant_name']); ?> · <?php echo htmlspecialchars($question['created_at']); ?></small>
                                        </div>
                                        <span class="badge <?php echo $question['status'] === 'answered' ? 'bg-success' : ($question['status'] === 'closed' ? 'bg-secondary' : 'bg-warning text-dark'); ?>">
                                            <?php echo htmlspecialchars(ucfirst($question['status'])); ?>
                                        </span>
                                    </div>
                                    <p class="mb-3"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                                    <?php if ($question['status'] === 'closed'): ?>
                                        <?php if ($question['answer_text'] !== null && $question['answer_text'] !== ''): ?>
                                            <div class="bg-light rounded-3 p-3"><?php echo nl2br(htmlspecialchars($question['answer_text'])); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <form method="POST" class="d-grid gap-2 mb-2">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                            <input type="hidden" name="question_id" value="<?php echo (int)$question['id']; ?>">
                                            <input type="hidden" name="action" value="reply">
                                            <label class="form-label" for="answer-<?php echo (int)$question['id']; ?>">Your reply</label>
                                            <textarea id="answer-<?php echo (int)$question['id']; ?>" name="answer_text" class="form-control" rows="3" maxlength="3000" required><?php echo htmlspecialchars($question['answer_text'] ?? ''); ?></textarea>
                                            <div><button type="submit" class="btn btn-primary btn-sm">Save reply</button></div>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                            <input type="hidden" name="question_id" value="<?php echo (int)$question['id']; ?>">
                                            <input type="hidden" name="action" value="close">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">Close question</button>
                                        </form>
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
