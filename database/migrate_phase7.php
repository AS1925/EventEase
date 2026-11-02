<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$selected_database = $conn->query('SELECT DATABASE()')->fetch_row()[0];
if ($dbName !== 'sakthi' || $selected_database !== 'sakthi') {
    fwrite(STDERR, "Phase 7 migration is restricted to the sakthi database.\n");
    exit(1);
}

function phase7_table_exists($conn, $table)
{
    $statement = $conn->prepare(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->bind_param('s', $table);
    $statement->execute();
    return $statement->get_result()->num_rows > 0;
}

function phase7_column_exists($conn, $table, $column)
{
    $statement = $conn->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $statement->bind_param('ss', $table, $column);
    $statement->execute();
    return $statement->get_result()->num_rows > 0;
}

function phase7_index_exists($conn, $table, $index)
{
    $statement = $conn->prepare(
        'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $statement->bind_param('ss', $table, $index);
    $statement->execute();
    return $statement->get_result()->num_rows > 0;
}

$tables = array(
    'vendor_profiles' => "CREATE TABLE vendor_profiles (
        user_id INT(11) NOT NULL,
        business_name VARCHAR(150) NOT NULL,
        description TEXT DEFAULT NULL,
        service_area VARCHAR(255) DEFAULT NULL,
        verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
        verified_by INT(11) DEFAULT NULL,
        verified_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id),
        KEY vendor_profiles_verification (verification_status),
        CONSTRAINT vendor_profiles_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT vendor_profiles_verifier_fk FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'vendor_services' => "CREATE TABLE vendor_services (
        id INT(11) NOT NULL AUTO_INCREMENT,
        vendor_id INT(11) NOT NULL,
        category VARCHAR(100) NOT NULL,
        service_name VARCHAR(150) NOT NULL,
        description TEXT DEFAULT NULL,
        package_name VARCHAR(150) NOT NULL,
        package_details TEXT DEFAULT NULL,
        base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY vendor_services_vendor_active (vendor_id, is_active),
        KEY vendor_services_category (category),
        CONSTRAINT vendor_services_vendor_fk FOREIGN KEY (vendor_id) REFERENCES vendor_profiles (user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'vendor_portfolio' => "CREATE TABLE vendor_portfolio (
        id INT(11) NOT NULL AUTO_INCREMENT,
        vendor_id INT(11) NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        caption VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY vendor_portfolio_vendor (vendor_id),
        CONSTRAINT vendor_portfolio_vendor_fk FOREIGN KEY (vendor_id) REFERENCES vendor_profiles (user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'vendor_availability' => "CREATE TABLE vendor_availability (
        id INT(11) NOT NULL AUTO_INCREMENT,
        vendor_id INT(11) NOT NULL,
        available_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        status ENUM('available','unavailable') NOT NULL DEFAULT 'available',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY vendor_availability_slot_unique (vendor_id, available_date, start_time, end_time),
        KEY vendor_availability_date (available_date, status),
        CONSTRAINT vendor_availability_vendor_fk FOREIGN KEY (vendor_id) REFERENCES vendor_profiles (user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'service_messages' => "CREATE TABLE service_messages (
        id INT(11) NOT NULL AUTO_INCREMENT,
        request_id INT(11) NOT NULL,
        sender_id INT(11) NOT NULL,
        message_text TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY service_messages_request (request_id, created_at),
        KEY service_messages_sender (sender_id),
        CONSTRAINT service_messages_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
        CONSTRAINT service_messages_sender_fk FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'vendor_reviews' => "CREATE TABLE vendor_reviews (
        id INT(11) NOT NULL AUTO_INCREMENT,
        request_id INT(11) NOT NULL,
        vendor_id INT(11) NOT NULL,
        customer_id INT(11) NOT NULL,
        rating TINYINT(1) NOT NULL,
        review_text TEXT NOT NULL,
        status ENUM('pending','published','rejected') NOT NULL DEFAULT 'pending',
        moderated_by INT(11) DEFAULT NULL,
        moderated_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY vendor_reviews_request_unique (request_id),
        KEY vendor_reviews_vendor_status (vendor_id, status),
        CONSTRAINT vendor_reviews_request_fk FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
        CONSTRAINT vendor_reviews_vendor_fk FOREIGN KEY (vendor_id) REFERENCES vendor_profiles (user_id) ON DELETE CASCADE,
        CONSTRAINT vendor_reviews_customer_fk FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT vendor_reviews_moderator_fk FOREIGN KEY (moderated_by) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

foreach ($tables as $table => $create_sql) {
    if (!phase7_table_exists($conn, $table)) {
        $conn->query($create_sql);
    }
}

$request_columns = array(
    'vendor_id' => 'INT(11) DEFAULT NULL',
    'vendor_service_id' => 'INT(11) DEFAULT NULL',
    'quote_amount' => 'DECIMAL(10,2) DEFAULT NULL',
    'quote_message' => 'TEXT DEFAULT NULL',
    'booking_status' => "ENUM('requested','quoted','accepted','rejected','cancelled','completed') NOT NULL DEFAULT 'requested'",
    'quoted_at' => 'DATETIME DEFAULT NULL',
    'booking_updated_at' => 'DATETIME DEFAULT NULL'
);
foreach ($request_columns as $column => $definition) {
    if (!phase7_column_exists($conn, 'service_requests', $column)) {
        $conn->query('ALTER TABLE service_requests ADD COLUMN `' . $column . '` ' . $definition);
    }
}

if (!phase7_index_exists($conn, 'service_requests', 'service_requests_vendor_booking')) {
    $conn->query('ALTER TABLE service_requests ADD INDEX service_requests_vendor_booking (vendor_id, booking_status)');
}
if (!phase7_index_exists($conn, 'service_requests', 'service_requests_vendor_service')) {
    $conn->query('ALTER TABLE service_requests ADD INDEX service_requests_vendor_service (vendor_service_id)');
}

$constraint_check = $conn->prepare(
    'SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?'
);
$table = 'service_requests';
$constraint = 'service_requests_vendor_fk';
$constraint_check->bind_param('ss', $table, $constraint);
$constraint_check->execute();
if ($constraint_check->get_result()->num_rows === 0) {
    $conn->query(
        'ALTER TABLE service_requests ADD CONSTRAINT service_requests_vendor_fk FOREIGN KEY (vendor_id) REFERENCES vendor_profiles (user_id) ON DELETE SET NULL'
    );
}

$constraint = 'service_requests_vendor_service_fk';
$constraint_check->bind_param('ss', $table, $constraint);
$constraint_check->execute();
if ($constraint_check->get_result()->num_rows === 0) {
    $conn->query(
        'ALTER TABLE service_requests ADD CONSTRAINT service_requests_vendor_service_fk FOREIGN KEY (vendor_service_id) REFERENCES vendor_services (id) ON DELETE SET NULL'
    );
}

$conn->query(
    "INSERT IGNORE INTO vendor_profiles (user_id, business_name)
     SELECT id, COALESCE(NULLIF(name, ''), 'Vendor') FROM users WHERE role = 'vendor'"
);

echo "Phase 7 marketplace migration completed for sakthi.\n";
echo "Existing service request rows were preserved and remain private bookings, separate from event registrations.\n";
