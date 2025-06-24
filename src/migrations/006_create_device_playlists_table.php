<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

require __DIR__ . '/../config/db.php';

$sql = "CREATE TABLE IF NOT EXISTS device_playlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    playlist_id INT NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
    UNIQUE KEY unique_device_playlist (device_id, playlist_id),
    INDEX idx_active_playlist (is_active)
)";

try {
    $pdo->exec($sql);
    echo "Device playlists table created successfully!";
} catch (PDOException $e) {
    die("Table creation failed: " . $e->getMessage());
}