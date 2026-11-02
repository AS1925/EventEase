<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$selected_database = $conn->query('SELECT DATABASE()')->fetch_row()[0];
if ($dbName !== 'sakthi' || $selected_database !== 'sakthi') {
    fwrite(STDERR, "Attendance verification migration is restricted to the sakthi database.\n");
    exit(1);
}

$backup_file = null;
foreach (array_slice($argv, 1) as $argument) {
    if (strpos($argument, '--backup-file=') === 0) {
        $backup_file = substr($argument, strlen('--backup-file='));
    }
}
if (!is_string($backup_file) || $backup_file === '' || !is_file($backup_file) || !is_readable($backup_file)) {
    fwrite(STDERR, "Provide a fresh sakthi SQL backup using --backup-file=PATH.\n");
    exit(1);
}

$backup_contents = file_get_contents($backup_file);
$backup_age = time() - filemtime($backup_file);
if (
    !is_string($backup_contents)
    || $backup_age < 0
    || $backup_age > 86400
    || filesize($backup_file) < 10000
    || strpos($backup_contents, 'Database: sakthi') === false
    || strpos($backup_contents, 'CREATE TABLE `events`') === false
    || strpos($backup_contents, 'CREATE TABLE `registrations`') === false
    || strpos($backup_contents, 'CREATE TABLE `event_attendance`') === false
    || strpos($backup_contents, 'Dump completed') === false
) {
    fwrite(STDERR, "The backup must be a recent, complete-looking sakthi SQL dump with event and attendance tables.\n");
    exit(1);
}

function attendance_verification_column_exists($conn, $table, $column)
{
    $statement = $conn->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $statement->bind_param('ss', $table, $column);
    $statement->execute();
    return $statement->get_result()->num_rows > 0;
}

function attendance_verification_index_exists($conn, $table, $index)
{
    $statement = $conn->prepare(
        'SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $statement->bind_param('ss', $table, $index);
    $statement->execute();
    return $statement->get_result()->num_rows > 0;
}

function attendance_verification_foreign_key_exists($conn, $table, $constraint)
{
    $statement = $conn->prepare(
        'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?
           AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = \'FOREIGN KEY\''
    );
    $statement->bind_param('ss', $table, $constraint);
    $statement->execute();
    return $statement->get_result()->num_rows > 0;
}

function attendance_verification_add_column($conn, $table, $column, $definition)
{
    if (!attendance_verification_column_exists($conn, $table, $column)) {
        $conn->query('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
        echo "Added {$table}.{$column}.\n";
    } else {
        echo "{$table}.{$column} already exists; unchanged.\n";
    }
}

function attendance_verification_add_index($conn, $table, $index, $definition)
{
    if (!attendance_verification_index_exists($conn, $table, $index)) {
        $conn->query('ALTER TABLE `' . $table . '` ADD ' . $definition);
        echo "Added index {$index}.\n";
    } else {
        echo "Index {$index} already exists; unchanged.\n";
    }
}

attendance_verification_add_column(
    $conn,
    'events',
    'checkin_method',
    "ENUM('gps','organizer_manual') NOT NULL DEFAULT 'organizer_manual' AFTER attendance_enabled"
);
attendance_verification_add_column(
    $conn,
    'events',
    'venue_verification_enabled',
    'TINYINT(1) NOT NULL DEFAULT 0 AFTER checkin_method'
);
attendance_verification_add_column(
    $conn,
    'events',
    'checkin_radius_m',
    'SMALLINT UNSIGNED NOT NULL DEFAULT 100 AFTER venue_verification_enabled'
);

attendance_verification_add_index(
    $conn,
    'registrations',
    'registrations_id_event_unique',
    'UNIQUE KEY `registrations_id_event_unique` (`id`, `event_id`)'
);

attendance_verification_add_column($conn, 'event_attendance', 'event_id', 'INT DEFAULT NULL AFTER registration_id');
attendance_verification_add_column(
    $conn,
    'event_attendance',
    'checkin_method',
    "ENUM('gps','organizer_manual') NOT NULL DEFAULT 'gps'"
);
attendance_verification_add_column(
    $conn,
    'event_attendance',
    'verification_result',
    "ENUM('venue_verified','organizer_confirmed') NOT NULL DEFAULT 'venue_verified'"
);
attendance_verification_add_column(
    $conn,
    'event_attendance',
    'location_accuracy_m',
    'DECIMAL(7,2) DEFAULT NULL'
);

$conn->query(
    'UPDATE event_attendance
     INNER JOIN registrations ON registrations.id = event_attendance.registration_id
     SET event_attendance.event_id = registrations.event_id
     WHERE event_attendance.event_id IS NULL'
);

$missing_event_ids = (int)$conn->query(
    'SELECT COUNT(*) AS total FROM event_attendance WHERE event_id IS NULL'
)->fetch_assoc()['total'];
if ($missing_event_ids > 0) {
    throw new RuntimeException(
        'Attendance rows without a linked event remain; no event foreign keys were added.'
    );
}

$conn->query('ALTER TABLE event_attendance MODIFY COLUMN event_id INT NOT NULL');
$conn->query('ALTER TABLE event_attendance MODIFY COLUMN location_distance_m DECIMAL(7,2) DEFAULT NULL');
$conn->query('ALTER TABLE event_attendance MODIFY COLUMN consented_at TIMESTAMP NULL DEFAULT NULL');

attendance_verification_add_index(
    $conn,
    'event_attendance',
    'event_attendance_event_id',
    'KEY `event_attendance_event_id` (`event_id`)'
);

if (attendance_verification_foreign_key_exists($conn, 'event_attendance', 'event_attendance_registration_fk')) {
    $conn->query('ALTER TABLE event_attendance DROP FOREIGN KEY event_attendance_registration_fk');
    echo "Replaced the registration-only attendance foreign key with an event-matched key.\n";
}
if (!attendance_verification_foreign_key_exists($conn, 'event_attendance', 'event_attendance_registration_event_fk')) {
    $conn->query(
        'ALTER TABLE event_attendance
         ADD CONSTRAINT event_attendance_registration_event_fk
         FOREIGN KEY (registration_id, event_id)
         REFERENCES registrations (id, event_id) ON DELETE CASCADE'
    );
}
if (!attendance_verification_foreign_key_exists($conn, 'event_attendance', 'event_attendance_event_fk')) {
    $conn->query(
        'ALTER TABLE event_attendance
         ADD CONSTRAINT event_attendance_event_fk
         FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE'
    );
}

echo "Attendance verification migration completed for sakthi.\n";
echo "Event attendance remains disabled by default; no event or attendance records were created or deleted.\n";
