<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$selected_database = $conn->query('SELECT DATABASE()')->fetch_row()[0];
if ($dbName !== 'sakthi' || $selected_database !== 'sakthi') {
    fwrite(STDERR, "Phase 3 migration is restricted to the sakthi database.\n");
    exit(1);
}

function phase3_column_exists($conn, $table, $column)
{
    $statement = $conn->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $statement->bind_param('ss', $table, $column);
    $statement->execute();
    return $statement->get_result()->num_rows > 0;
}

function phase3_index_exists($conn, $table, $index)
{
    $statement = $conn->prepare(
        'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $statement->bind_param('ss', $table, $index);
    $statement->execute();
    return $statement->get_result()->num_rows > 0;
}

function phase3_make_registration_number($conn)
{
    $check = $conn->prepare('SELECT 1 FROM registrations WHERE registration_number = ? LIMIT 1');
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $number = 'EVT-' . strtoupper(bin2hex(random_bytes(16)));
        $check->bind_param('s', $number);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            return $number;
        }
    }

    throw new RuntimeException('Could not generate a unique registration number.');
}

$before_count = (int)$conn->query('SELECT COUNT(*) AS total FROM registrations')->fetch_assoc()['total'];
$registration_status_existed = phase3_column_exists($conn, 'registrations', 'registration_status');
$attendance_status_existed = phase3_column_exists($conn, 'registrations', 'attendance_status');
$registration_number_existed = phase3_column_exists($conn, 'registrations', 'registration_number');

if (!$registration_number_existed) {
    $conn->query('ALTER TABLE registrations ADD COLUMN registration_number VARCHAR(40) DEFAULT NULL AFTER id');
}
if (!$registration_status_existed) {
    $conn->query(
        "ALTER TABLE registrations ADD COLUMN registration_status ENUM('active','cancelled') NOT NULL DEFAULT 'active' AFTER registration_number"
    );
}
if (!$attendance_status_existed) {
    $conn->query(
        "ALTER TABLE registrations ADD COLUMN attendance_status ENUM('not_checked_in','checked_in','no_show') NOT NULL DEFAULT 'not_checked_in' AFTER registration_status"
    );
}

$conn->query(
    "UPDATE registrations SET registration_status = 'cancelled' WHERE status = 'Cancelled' AND registration_status = 'active'"
);
$conn->query(
    "UPDATE registrations SET attendance_status = 'checked_in' WHERE status = 'Attended' AND attendance_status = 'not_checked_in'"
);
$conn->query(
    "UPDATE registrations SET attendance_status = 'no_show' WHERE status = 'No-show' AND attendance_status = 'not_checked_in'"
);

$pending_numbers = $conn->query(
    "SELECT id FROM registrations WHERE registration_number IS NULL OR registration_number = '' ORDER BY id"
);
$update_number = $conn->prepare(
    "UPDATE registrations SET registration_number = ? WHERE id = ? AND (registration_number IS NULL OR registration_number = '')"
);
while ($registration = $pending_numbers->fetch_assoc()) {
    $number = phase3_make_registration_number($conn);
    $registration_id = (int)$registration['id'];
    $update_number->bind_param('si', $number, $registration_id);
    $update_number->execute();
}

$duplicates = (int)$conn->query(
    'SELECT COUNT(*) AS total FROM (SELECT registration_number FROM registrations GROUP BY registration_number HAVING COUNT(*) > 1) AS duplicate_numbers'
)->fetch_assoc()['total'];
if ($duplicates > 0) {
    throw new RuntimeException('Duplicate registration numbers exist; the unique index was not added.');
}

if (!phase3_index_exists($conn, 'registrations', 'registrations_registration_number_unique')) {
    $conn->query('ALTER TABLE registrations ADD UNIQUE INDEX registrations_registration_number_unique (registration_number)');
}
$conn->query('ALTER TABLE registrations MODIFY registration_number VARCHAR(40) NOT NULL');

$after_count = (int)$conn->query('SELECT COUNT(*) AS total FROM registrations')->fetch_assoc()['total'];
if ($before_count !== $after_count) {
    throw new RuntimeException('The migration changed the number of existing registration rows.');
}

echo "Phase 3 schema migration completed for sakthi.\n";
echo "Preserved registration rows: {$after_count}.\n";
