<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$selected_database = $conn->query('SELECT DATABASE()')->fetch_row()[0];
if ($dbName !== 'sakthi' || $selected_database !== 'sakthi') {
    fwrite(STDERR, "Phase 8 migration is restricted to the sakthi database.\n");
    exit(1);
}

$before_count = (int)$conn->query('SELECT COUNT(*) AS total FROM events')->fetch_assoc()['total'];
$column_check = $conn->prepare(
    'SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
$table_name = 'events';
$column_name = 'ticket_price';
$column_check->bind_param('ss', $table_name, $column_name);
$column_check->execute();
if ($column_check->get_result()->num_rows === 0) {
    $conn->query(
        'ALTER TABLE events ADD COLUMN ticket_price DECIMAL(10,2) NOT NULL DEFAULT 0.00'
    );
}

$after_count = (int)$conn->query('SELECT COUNT(*) AS total FROM events')->fetch_assoc()['total'];
if ($before_count !== $after_count) {
    throw new RuntimeException('The migration changed the number of existing event rows.');
}

echo "Phase 8 event pricing migration completed for sakthi.\n";
echo "Preserved event rows: {$after_count}.\n";
