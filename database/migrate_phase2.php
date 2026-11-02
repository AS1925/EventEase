<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$selected_database = $conn->query('SELECT DATABASE()')->fetch_row()[0];
if ($dbName !== 'sakthi' || $selected_database !== 'sakthi') {
    fwrite(STDERR, "Phase 2 migration is restricted to the sakthi database.\n");
    exit(1);
}

function phase2_column_exists($conn, $table, $column)
{
    $statement = $conn->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $statement->bind_param('ss', $table, $column);
    $statement->execute();
    return $statement->get_result()->num_rows > 0;
}

function phase2_index_exists($conn, $table, $index)
{
    $statement = $conn->prepare(
        'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $statement->bind_param('ss', $table, $index);
    $statement->execute();
    return $statement->get_result()->num_rows > 0;
}

function phase2_constraint_exists($conn, $table, $constraint)
{
    $statement = $conn->prepare(
        'SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?'
    );
    $statement->bind_param('ss', $table, $constraint);
    $statement->execute();
    return $statement->get_result()->num_rows > 0;
}

function phase2_row_count($conn, $table)
{
    $result = $conn->query('SELECT COUNT(*) AS total FROM `' . $table . '`');
    return (int)$result->fetch_assoc()['total'];
}

$before_counts = array(
    'users' => phase2_row_count($conn, 'users'),
    'events' => phase2_row_count($conn, 'events'),
    'registrations' => phase2_row_count($conn, 'registrations')
);

$conn->query(
    "ALTER TABLE users MODIFY role ENUM('admin','user','customer','organizer','vendor') DEFAULT 'customer'"
);
$conn->query(
    "UPDATE users SET role = 'customer' WHERE role = 'user' OR role IS NULL"
);
$conn->query(
    "UPDATE users SET role = 'admin' WHERE LOWER(email) = 'admin@gmail.com'"
);
$conn->query(
    "ALTER TABLE users MODIFY role ENUM('customer','organizer','vendor','admin') DEFAULT 'customer'"
);

$conn->query(
    "CREATE TABLE IF NOT EXISTS organizer_profiles (
        user_id INT(11) NOT NULL,
        organization_name VARCHAR(150) NOT NULL,
        bio TEXT DEFAULT NULL,
        website VARCHAR(255) DEFAULT NULL,
        verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
        verified_by INT(11) DEFAULT NULL,
        verified_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id),
        KEY organizer_verification_status (verification_status),
        CONSTRAINT fk_organizer_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_organizer_profiles_verifier FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$event_columns = array(
    'organizer_id' => 'INT(11) DEFAULT NULL',
    'category' => 'VARCHAR(100) DEFAULT NULL',
    'start_time' => 'TIME DEFAULT NULL',
    'end_time' => 'TIME DEFAULT NULL',
    'address' => 'VARCHAR(255) DEFAULT NULL',
    'latitude' => 'DECIMAL(10,7) DEFAULT NULL',
    'longitude' => 'DECIMAL(10,7) DEFAULT NULL',
    'registration_open_at' => 'DATETIME DEFAULT NULL',
    'registration_close_at' => 'DATETIME DEFAULT NULL',
    'publish_status' => "ENUM('draft','published','archived') NOT NULL DEFAULT 'published'"
);

foreach ($event_columns as $column => $definition) {
    if (!phase2_column_exists($conn, 'events', $column)) {
        $conn->query('ALTER TABLE events ADD COLUMN `' . $column . '` ' . $definition);
    }
}

$conn->query(
    "UPDATE events SET category = event_type WHERE (category IS NULL OR category = '') AND event_type IS NOT NULL"
);
$conn->query(
    "UPDATE events SET start_time = event_time WHERE start_time IS NULL AND event_time IS NOT NULL"
);

if (!phase2_index_exists($conn, 'events', 'events_organizer_id')) {
    $conn->query('ALTER TABLE events ADD INDEX events_organizer_id (organizer_id)');
}
if (!phase2_constraint_exists($conn, 'events', 'fk_events_organizer')) {
    $conn->query(
        'ALTER TABLE events ADD CONSTRAINT fk_events_organizer FOREIGN KEY (organizer_id) REFERENCES users (id) ON DELETE SET NULL'
    );
}

$conn->query(
    "INSERT IGNORE INTO organizer_profiles (user_id, organization_name)
     SELECT id, COALESCE(NULLIF(name, ''), 'Organizer') FROM users WHERE role = 'organizer'"
);

$after_counts = array(
    'users' => phase2_row_count($conn, 'users'),
    'events' => phase2_row_count($conn, 'events'),
    'registrations' => phase2_row_count($conn, 'registrations')
);
if ($before_counts !== $after_counts) {
    throw new RuntimeException('The migration changed the number of existing user, event, or registration rows.');
}

echo "Phase 2 schema migration completed for sakthi.\n";
echo "Preserved rows: users={$after_counts['users']}, events={$after_counts['events']}, registrations={$after_counts['registrations']}.\n";
