// migrations/002_create_devices_table.php
<?php
require __DIR__ . '/../config/db.php';

$sql = "CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_code VARCHAR(24) UNIQUE NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

try {
    $pdo->exec($sql);
    echo "Devices table created successfully!";
} catch (PDOException $e) {
    die("Table creation failed: " . $e->getMessage());
}