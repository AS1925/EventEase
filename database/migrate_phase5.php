<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$selected_database = $conn->query('SELECT DATABASE()')->fetch_row()[0];
if ($dbName !== 'sakthi' || $selected_database !== 'sakthi') {
    fwrite(STDERR, "Phase 5 migration is restricted to the sakthi database.\n");
    exit(1);
}

$statement = $conn->prepare(
    'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
);
$table_name = 'event_questions';
$statement->bind_param('s', $table_name);
$statement->execute();
if ($statement->get_result()->num_rows === 0) {
    $conn->query(
        "CREATE TABLE event_questions (
            id INT(11) NOT NULL AUTO_INCREMENT,
            event_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            question_text TEXT NOT NULL,
            answer_text TEXT DEFAULT NULL,
            status ENUM('pending','answered','closed') NOT NULL DEFAULT 'pending',
            answered_by INT(11) DEFAULT NULL,
            answered_at DATETIME DEFAULT NULL,
            closed_by INT(11) DEFAULT NULL,
            closed_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_questions_event_status (event_id, status),
            KEY event_questions_user (user_id),
            CONSTRAINT event_questions_event_fk
                FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
            CONSTRAINT event_questions_user_fk
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT event_questions_answered_by_fk
                FOREIGN KEY (answered_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT event_questions_closed_by_fk
                FOREIGN KEY (closed_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

echo "Phase 5 question migration completed for sakthi.\n";
