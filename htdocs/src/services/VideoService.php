<?php
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../models/Device.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../exceptions/VideoServiceException.php';

class VideoService {
    private $videoModel;
    private $deviceModel;
    private $userModel;

    public function __construct() {
        $this->videoModel = new Video();
        $this->deviceModel = new Device();
        $this->userModel = new User();
    }

    // 1. Register video to all user devices
    public function registerNewVideo(string $videoUrl, int $userId): array {
        try {
            // Validate user exists
            if (!$this->userModel->findById($userId)) {
                throw new VideoServiceException("User not found", 404);
            }

            // Get all user devices
            $devices = $this->deviceModel->findByUserId($userId);
            if (empty($devices)) {
                throw new VideoServiceException("No devices registered", 400);
            }

            $registeredIds = [];
            $this->videoModel->beginTransaction();
            
            foreach ($devices as $device) {
                $videoId = $this->videoModel->create($device->id, $videoUrl);
                $registeredIds[] = $videoId;
            }
            
            $this->videoModel->commit();
            return $registeredIds;

        } catch (PDOException $e) {
            $this->videoModel->rollBack();
            throw new VideoServiceException("Video registration failed: " . $e->getMessage(), 500);
        }
    }

    // 2. Delete all videos with same URL across user devices
    public function deleteVideo(int $videoId, int $userId): int {
        try {
            // Get original video details
            $originalVideo = $this->videoModel->findById($videoId);
            if (!$originalVideo) {
                throw new VideoServiceException("Video not found", 404);
            }

            // Verify device ownership
            $device = $this->deviceModel->findById($originalVideo->device_id);
            if (!$device || $device->user_id !== $userId) {
                throw new OwnershipVerificationException("Video not owned by user", 403);
            }

            // Get all devices for user
            $userDevices = $this->deviceModel->findByUserId($userId);
            $deviceIds = array_column($userDevices, 'id');

            // Delete all videos with same URL across user devices
            $sql = "DELETE FROM videos 
                    WHERE video_url = :url 
                    AND device_id IN (" . implode(',', $deviceIds) . ")";
            
            $stmt = $this->videoModel->prepare($sql);
            $stmt->execute(['url' => $originalVideo->video_url]);
            
            return $stmt->rowCount();

        } catch (PDOException $e) {
            throw new VideoServiceException("Deletion failed: " . $e->getMessage(), 500);
        }
    }

    // 3. Get video IDs from first user device
    public function getVideosIdFromUser(int $userId): array {
        $devices = $this->deviceModel->findByUserId($userId);
        if (empty($devices)) {
            return [];
        }
        
        // Get first device (ordered by earliest registration)
        usort($devices, fn($a, $b) => $a->id <=> $b->id);
        $firstDevice = $devices[0];
        
        return $this->videoModel->findByDeviceId($firstDevice->id);
    }

    // 4. Get video URL from ID
    public function getVideoUrlFromId(int $videoId): string {
        $video = $this->videoModel->findById($videoId);
        if (!$video) {
            throw new VideoServiceException("Video not found", 404);
        }
        return $video->video_url;
    }
}