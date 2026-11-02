<?php
require_once __DIR__ . '/../includes/session.php';
eventease_start_session();
require_once __DIR__ . '/../includes/access_control.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventease_require_valid_csrf_post();
    $question_id_input = isset($_POST['question_id']) && is_string($_POST['question_id'])
        ? $_POST['question_id']
        : '';
    if (!ctype_digit($question_id_input)) {
        $message = 'The question update was invalid.';
        $message_type = 'danger';
    } else {
        $question_id = (int)$question_id_input;
        $admin_id = (int)$_SESSION['user_id'];
        $close = $conn->prepare(
            "UPDATE event_questions
             SET status = 'closed', closed_by = ?, closed_at = NOW()
             WHERE id = ? AND status <> 'closed'"
        );
        $close->bind_param('ii', $admin_id, $question_id);
        $close->execute();
        if ($close->affected_rows === 1) {
            $message = 'The question was closed by an administrator.';
        } else {
            $message = 'The question is already closed or no longer exists.';
            $message_type = 'warning';
        }
    }
}

$questions = $conn->query(
    "SELECT event_questions.id, event_questions.question_text, event_questions.answer_text,
            event_questions.status, event_questions.created_at, event_questions.closed_at,
            users.name AS participant_name, events.event_name
     FROM event_questions
     INNER JOIN users ON users.id = event_questions.user_id
     INNER JOIN events ON events.id = event_questions.event_id
     ORDER BY FIELD(event_questions.status, 'pending', 'answered', 'closed'),
              event_questions.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderate Event Questions - EventEase Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../events/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3"><?php include '../includes/admin_sidebar.php'; ?></div>
            <main class="col-lg-9">
                <section class="card border-0 shadow-sm rounded-4 p-4">
                    <h1 class="h3 fw-bold mb-3">Moderate Event Questions</h1>
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="status"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    <?php if ($questions->num_rows === 0): ?>
                        <p class="text-muted mb-0">No event questions have been submitted.</p>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php while ($question = $questions->fetch_assoc()): ?>
                                <article class="border rounded-3 p-3 p-md-4">
                                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                        <div>
                                            <h2 class="h5 fw-bold mb-1"><?php echo htmlspecialchars($question['event_name']); ?></h2>
                                            <small class="text-muted">By <?php echo htmlspecialchars($question['participant_name']); ?> · <?php echo htmlspecialchars($question['created_at']); ?></small>
                                        </div>
                                        <span class="badge <?php echo $question['status'] === 'answered' ? 'bg-success' : ($question['status'] === 'closed' ? 'bg-secondary' : 'bg-warning text-dark'); ?>">
                                            <?php echo htmlspecialchars(ucfirst($question['status'])); ?>
                                        </span>
                                    </div>
                                    <p><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                                    <?php if ($question['answer_text'] !== null && $question['answer_text'] !== ''): ?>
                                        <div class="bg-light rounded-3 p-3 mb-3"><strong>Organizer reply</strong><br><?php echo nl2br(htmlspecialchars($question['answer_text'])); ?></div>
                                    <?php endif; ?>
                                    <?php if ($question['status'] !== 'closed'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(eventease_csrf_token()); ?>">
                                            <input type="hidden" name="question_id" value="<?php echo (int)$question['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Close question</button>
                                        </form>
                                    <?php elseif ($question['closed_at']): ?>
                                        <small class="text-muted">Closed <?php echo htmlspecialchars($question['closed_at']); ?></small>
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
