<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$selected_database = $conn->query('SELECT DATABASE()')->fetch_row()[0];
if ($dbName !== 'sakthi' || $selected_database !== 'sakthi') {
    fwrite(STDERR, "Organizer profile migration is restricted to the sakthi database.\n");
    exit(1);
}

$backup_file = null;
foreach (array_slice($argv, 1) as $argument) {
    if (strpos($argument, '--backup-file=') === 0) {
        $backup_file = substr($argument, strlen('--backup-file='));
    }
}
if (!is_string($backup_file) || $backup_file === '' || !is_file($backup_file) || !is_readable($backup_file)) {
    fwrite(STDERR, "Provide a readable fresh sakthi SQL backup using --backup-file=PATH.\n");
    exit(1);
}

$backup_age = time() - filemtime($backup_file);
$backup_contents = file_get_contents($backup_file);
if (
    $backup_age < 0
    || $backup_age > 86400
    || filesize($backup_file) < 10000
    || !is_string($backup_contents)
    || strpos($backup_contents, 'Database: sakthi') === false
    || strpos($backup_contents, 'CREATE TABLE `organizer_profiles`') === false
    || strpos($backup_contents, 'CREATE TABLE `events`') === false
) {
    fwrite(STDERR, "The backup must be a recent, complete-looking SQL dump of sakthi containing events and organizer_profiles.\n");
    exit(1);
}

function organizer_profile_column_exists($conn, $column)
{
    $statement = $conn->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = 'sakthi' AND TABLE_NAME = 'organizer_profiles' AND COLUMN_NAME = ?"
    );
    $statement->bind_param('s', $column);
    $statement->execute();
    return $statement->get_result()->num_rows > 0;
}

$profile_columns = array(
    'location' => 'VARCHAR(255) DEFAULT NULL AFTER website',
    'logo_path' => 'VARCHAR(255) DEFAULT NULL AFTER location'
);
foreach ($profile_columns as $column => $definition) {
    if (!organizer_profile_column_exists($conn, $column)) {
        $conn->query('ALTER TABLE organizer_profiles ADD COLUMN `' . $column . '` ' . $definition);
        echo "Added organizer_profiles.$column.\n";
    } else {
        echo "organizer_profiles.$column already exists; unchanged.\n";
    }
}

echo "Organizer profile fields are ready in sakthi. No rows or existing ownership values were modified.\n";
