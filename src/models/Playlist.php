<?php
require_once __DIR__ . '/../config/db.php';

class Playlist {
    private $pdo;
    
    public $id;
    public $user_id;
    public $name;
    public $created_at;
    public $updated_at;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function create($userId, $name) {
        $sql = "INSERT INTO playlists (user_id, name) VALUES (:user_id, :name)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name
        ]);
        return $this->pdo->lastInsertId();
    }

    public function findById($playlistId) {
        $sql = "SELECT * FROM playlists WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $playlistId]);
        return $stmt->fetchObject(Playlist::class);
    }

    public function findByUser($userId) {
        $sql = "SELECT * FROM playlists WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_CLASS, Playlist::class);
    }

    public function update($playlistId, $name) {
        $sql = "UPDATE playlists SET name = :name WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $playlistId,
            'name' => $name
        ]);
    }

    public function delete($playlistId) {
        $sql = "DELETE FROM playlists WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $playlistId]);
    }
}