<?php
require_once __DIR__ . '/../config/db.php';

class DevicePlaylist {
    private $pdo;

    public $id;
    public $device_id;
    public $playlist_id;
    public $is_active;
    public $assigned_at;
    
    public function __construct(?PDO $externalPdo = null) {
        if ($externalPdo) {
            $this->pdo = $externalPdo;
        } else {
            global $pdo;
            $this->pdo = $pdo;
        }
    }

    public function assignPlaylist(int $deviceId, int $playlistId) {
        try {

            $this->pdo->beginTransaction();
             
            // Deactivate other playlists for this device
            $deactivateStmt = $this->pdo->prepare("
                UPDATE device_playlists 
                SET is_active = 0 
                WHERE device_id = :device_id
            ");
            $deactivateStmt->execute([':device_id' => $deviceId]);
            
            // Insert or update assignment
            $stmt = $this->pdo->prepare("
                INSERT OR REPLACE INTO device_playlists (device_id, playlist_id, is_active)
                VALUES (:device_id, :playlist_id, 1);

            ");
            
            $stmt->execute([
                ':device_id' => $deviceId,
                ':playlist_id' => $playlistId
            ]);
            
            // Get the assignment ID
            $this->pdo->commit();
            return true;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get active playlist for a device
     */
    public function getActivePlaylist(int $deviceId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT p.*, dp.playlist_id
            FROM device_playlists dp
            JOIN playlists p ON dp.playlist_id = p.id
            WHERE dp.device_id = :device_id AND dp.is_active = 1
        ");
        $stmt->execute([':device_id' => $deviceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    
    /**
     * Get all playlists assigned to a device
     */
    public function getDevicePlaylists(int $deviceId): array {
        $stmt = $this->pdo->prepare("
            SELECT dp.*, p.name as playlist_name
            FROM device_playlists dp
            JOIN playlists p ON dp.playlist_id = p.id
            WHERE dp.device_id = :device_id
            ORDER BY dp.assigned_at DESC
        ");
        $stmt->execute([':device_id' => $deviceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}