<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$selected_database = $conn->query('SELECT DATABASE()')->fetch_row()[0];
if ($dbName !== 'sakthi' || $selected_database !== 'sakthi') {
    fwrite(STDERR, "Phase 4 migration is restricted to the sakthi database.\n");
    exit(1);
}

$column_check = $conn->prepare(
    'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
$table_check = $conn->prepare(
    'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
);

$table_name = 'events';
$column_name = 'attendance_enabled';
$column_check->bind_param('ss', $table_name, $column_name);
$column_check->execute();
if ($column_check->get_result()->num_rows === 0) {
    $conn->query(
        'ALTER TABLE events ADD COLUMN attendance_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER publish_status'
    );
}

$table_name = 'event_attendance';
$table_check->bind_param('s', $table_name);
$table_check->execute();
if ($table_check->get_result()->num_rows === 0) {
    $conn->query(
        "CREATE TABLE event_attendance (
            id INT NOT NULL AUTO_INCREMENT,
            registration_id INT NOT NULL,
            checked_in_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            location_distance_m DECIMAL(7,2) NOT NULL,
            consented_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY event_attendance_registration_unique (registration_id),
            CONSTRAINT event_attendance_registration_fk
                FOREIGN KEY (registration_id) REFERENCES registrations (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

echo "Phase 4 attendance migration completed for sakthi.\n";
echo "Attendance tracking defaults to disabled for existing events.\n";
