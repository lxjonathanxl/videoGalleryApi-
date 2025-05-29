<?php
require_once __DIR__ . '/../config/db.php';

class Device {
    private $pdo;
    
    public $id;
    public $user_id;
    public $device_code;
    public $registered_at;

    public function __construct(?PDO $externalPdo = null) {
        if ($externalPdo) {
            $this->pdo = $externalPdo;
        } else {
            global $pdo;
            $this->pdo = $pdo;
        }
    }

    // Register new device to user
    public function create($userId, $deviceCode) {
        // Validate device code format (example: 24-char alphanumeric)
        if (!preg_match('/^[a-zA-Z0-9]{24}$/', $deviceCode)) {
            throw new InvalidArgumentException("Invalid device code format");
        }

        $sql = "INSERT INTO devices (user_id, device_code) 
                VALUES (:user_id, :device_code)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'device_code' => $deviceCode
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            // Handle duplicate device code (error code 23000 = integrity constraint violation)
            if ($e->getCode() == '23000') {
                throw new RuntimeException("Device code already registered");
            }
            throw $e;
        }
    }

    // Get all devices for a user
    public function findByUserId($userId) {
        $sql = "SELECT * FROM devices WHERE user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(PDO::FETCH_CLASS, Device::class);
    }

    // Validate device code exists and return its user
    public function validateDeviceCode($deviceCode) {
        $sql = "SELECT users.* FROM devices
                JOIN users ON devices.user_id = users.id
                WHERE device_code = :device_code";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['device_code' => $deviceCode]);
        
        return $stmt->fetchObject('User'); // Returns User object if valid
    }

    public function findIdByCode(string $deviceCode): ?int {
    $sql = "SELECT id FROM devices WHERE device_code = :code";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['code' => $deviceCode]);
    return $stmt->fetchColumn(); // Returns ID or false
    }

    public function findById($deviceId) {
    $sql = "SELECT * FROM devices WHERE id = :id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['id' => $deviceId]);
    return $stmt->fetchObject(Device::class);
    }

    public function delete($deviceId) {
    $sql = "DELETE FROM devices WHERE id = :id";
    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute(['id' => $deviceId]);
    }
}