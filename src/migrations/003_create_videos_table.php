// migrations/003_create_videos_table.php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

require __DIR__ . '/../config/db.php';

$sql = "CREATE TABLE IF NOT EXISTS videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    video_url VARCHAR(2048) NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
)";

try {
    $pdo->exec($sql);
    echo "Videos table created successfully!";
} catch (PDOException $e) {
    die("Table creation failed: " . $e->getMessage());
}