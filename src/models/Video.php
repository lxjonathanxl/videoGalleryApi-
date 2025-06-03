<?php
require_once __DIR__ . '/../config/db.php';

class Video {
    private $pdo;
    
    public $id;
    public $device_id;
    public $video_url;
    public $registered_at;

    public function __construct(?PDO $externalPdo = null) {
        if ($externalPdo) {
            $this->pdo = $externalPdo;
        } else {
            global $pdo;
            $this->pdo = $pdo;
        }
    }

    // Register new video URL for a device
    public function create($deviceId, $videoUrl) {
        // Validate URL format
        if (!filter_var($videoUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid video URL format");
        }

        $sql = "INSERT INTO videos (device_id, video_url) 
                VALUES (:device_id, :video_url)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'device_id' => $deviceId,
            'video_url' => $videoUrl
        ]);

        return $this->pdo->lastInsertId();
    }

    public function beginTransaction() {
        $this->pdo->beginTransaction();
    }

    public function commit() {
        $this->pdo->commit();
    }

    public function rollBack() {
        $this->pdo->rollBack();
    }

    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }

    public function findById($videoId) {
        $sql = "SELECT * FROM videos WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $videoId]);
        return $stmt->fetchObject(Video::class);
    }

    // Get all videos for a specific device
    public function findByDeviceId($deviceId) {
        $sql = "SELECT * FROM videos WHERE device_id = :device_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['device_id' => $deviceId]);
        
        return $stmt->fetchAll(PDO::FETCH_CLASS, Video::class);
    }

    // Get all videos for a user through their devices
    public function findByUserId($userId) {
        $sql = "SELECT videos.* FROM videos
                JOIN devices ON videos.device_id = devices.id
                WHERE devices.user_id = :user_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(PDO::FETCH_CLASS, Video::class);
    }
}