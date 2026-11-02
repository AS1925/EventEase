<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$selected_database = $conn->query('SELECT DATABASE()')->fetch_row()[0];
if ($dbName !== 'sakthi' || $selected_database !== 'sakthi') {
    fwrite(STDERR, "Password reset migration is restricted to the sakthi database.\n");
    exit(1);
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY password_reset_tokens_hash_unique (token_hash),
        KEY password_reset_tokens_user_created (user_id, created_at),
        KEY password_reset_tokens_expiry (expires_at),
        CONSTRAINT password_reset_tokens_user_fk
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$table_check = $conn->prepare(
    'SELECT 1 FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
);
$table_name = 'password_reset_tokens';
$table_check->bind_param('s', $table_name);
$table_check->execute();
if ($table_check->get_result()->num_rows !== 1) {
    fwrite(STDERR, "Password reset token table was not created.\n");
    exit(1);
}

echo "Password reset token migration completed for sakthi.\n";
