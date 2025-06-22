<?php
require_once __DIR__ . '/../config/db.php';

class PlaylistVideo {
    private $pdo;
    
    public $id;
    public $playlist_id;
    public $video_id;
    public $position;
    public $added_at;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Add a video to a playlist at a given position or next available position
     */
    public function addVideoToPlaylist($playlistId, $videoId, $position = null) {
        $this->pdo->beginTransaction();
    
        try {
            if ($position !== null) {
                // Shift existing videos down from this position
                $this->shiftVideosDown($playlistId, $position);
            } else {
                // Get the next available position
                $position = $this->getNextPosition($playlistId);
            }
        
            $sql = "INSERT INTO playlist_videos (playlist_id, video_id, position, added_at) 
                    VALUES (:playlist_id, :video_id, :position, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'playlist_id' => $playlistId,
                'video_id' => $videoId,
                'position' => $position
            ]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get the current position of a video in a playlist
     */
    public function getVideoPosition($playlistId, $videoId) {
        $sql = "SELECT position FROM playlist_videos 
                WHERE playlist_id = :playlist_id AND video_id = :video_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'playlist_id' => $playlistId,
            'video_id' => $videoId
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['position'] : null;
    }

    /**
     * Shift videos down starting from a given position (to insert a new one)
     */
    private function shiftVideosDown($playlistId, $fromPosition) {
        $sql = "UPDATE playlist_videos 
                SET position = position + 1 
                WHERE playlist_id = :playlist_id 
                AND position >= :position";
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'playlist_id' => $playlistId,
            'position' => $fromPosition
        ]);
    }

    /**
     * Shift videos up after removing one
     */
    private function shiftVideosUp($playlistId, $fromPosition) {
        $sql = "UPDATE playlist_videos 
                SET position = position - 1 
                WHERE playlist_id = :playlist_id 
                AND position > :position";
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'playlist_id' => $playlistId,
            'position' => $fromPosition
        ]);
    }

    /**
     * Remove a video from a playlist and reorder
     */
    public function removeFromPlaylist($playlistId, $videoId) {
        $this->pdo->beginTransaction();

        try {
            // Get position of the video being removed
            $position = $this->getVideoPosition($playlistId, $videoId);

            if ($position === null) {
                throw new Exception("Video not found in playlist.");
            }

            $sql = "DELETE FROM playlist_videos 
                    WHERE playlist_id = :playlist_id AND video_id = :video_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'playlist_id' => $playlistId,
                'video_id' => $videoId
            ]);

            // Shift videos up to fill the gap
            $this->shiftVideosUp($playlistId, $position);
            
            $this->pdo->commit();
            return true;
        } catch(PDOException | Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Update the position of a video in a playlist
     */
    public function updatePosition($playlistId, $videoId, $newPosition) {
        $sql = "UPDATE playlist_videos SET position = :position 
                WHERE playlist_id = :playlist_id AND video_id = :video_id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'playlist_id' => $playlistId,
            'video_id' => $videoId,
            'position' => $newPosition
        ]);
    }

    /**
     * Reorder the playlist based on an array of video IDs
     */
    public function reorderPlaylist($playlistId, array $newOrder) {
        $this->pdo->beginTransaction();
        
        try {
            // Reset all positions to 0 (optional but clean)
            $stmt = $this->pdo->prepare(
                "UPDATE playlist_videos SET position = 0 
                 WHERE playlist_id = :playlist_id"
            );
            $stmt->execute(['playlist_id' => $playlistId]);
            
            // Assign new positions
            $sql = "UPDATE playlist_videos SET position = :position 
                    WHERE playlist_id = :playlist_id AND video_id = :video_id";
            $stmt = $this->pdo->prepare($sql);
            
            foreach ($newOrder as $position => $videoId) {
                $stmt->execute([
                    'playlist_id' => $playlistId,
                    'video_id' => $videoId,
                    'position' => $position + 1 // positions start at 1
                ]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get the next available position in the playlist
     */
    private function getNextPosition($playlistId) {
        $sql = "SELECT COALESCE(MAX(position), 0) + 1 AS next_position 
                FROM playlist_videos 
                WHERE playlist_id = :playlist_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['playlist_id' => $playlistId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['next_position'];
    }

    public function videoExistsInPlaylist($playlistId, $videoId) {
        $sql = "SELECT COUNT(*) FROM playlist_videos 
                WHERE playlist_id = :playlist_id AND video_id = :video_id";
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'playlist_id' => $playlistId,
            'video_id' => $videoId
        ]);
    
        return (bool)$stmt->fetchColumn();
    }

    public function getVideos($playlistId) {
        $sql = "SELECT v.* FROM videos v
                JOIN playlist_videos pv ON v.id = pv.video_id
                WHERE pv.playlist_id = :playlist_id
                ORDER BY pv.position ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['playlist_id' => $playlistId]);
        return $stmt->fetchAll(PDO::FETCH_CLASS, Video::class);
    }
}