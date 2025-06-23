<?php
require_once __DIR__ . '/../models/Playlist.php';
require_once __DIR__ . '/../models/PlaylistVideo.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../exceptions/NotFoundException.php';
require_once __DIR__ . '/../exceptions/UnauthorizedException.php';

class PlaylistService {
    private $playlistModel;
    private $playlistVideoModel;
    private $videoModel;

    public function __construct(?Playlist $playlistModel = null,
     ?PlaylistVideo $playlistVideoModel = null,
     ?Video $videoModel = null) {
        $this->playlistModel = $playlistModel ?? new Playlist();
        $this->playlistVideoModel = $playlistVideoModel ?? new PlaylistVideo();
        $this->videoModel = $videoModel ?? new Video();
    }

    public function createPlaylist($userId, $name) {
        return $this->playlistModel->create($userId, $name);
    }

    public function getUserPlaylists($userId) {
        return $this->playlistModel->findByUser($userId);
    }

    public function getPlaylistById($playlistId) {
        return $this->playlistModel->findById($playlistId);
    }

    public function addVideoToPlaylist($userId, $playlistId, $videoId) {
        // Verify playlist ownership
        $playlist = $this->playlistModel->findById($playlistId);
        if (!$playlist || $playlist->user_id != $userId) {
            throw new UnauthorizedException("You don't own this playlist");
        }

        // Verify video exists
        $video = $this->videoModel->findById($videoId);
        if (!$video) {
            throw new NotFoundException("Video not found");
        }
        
        $existing = $this->playlistVideoModel->videoExistsInPlaylist($playlistId, $videoId);
        if ($existing) {
            throw new \Exception("Video already in playlist");
        }
    
        return $this->playlistVideoModel->addVideoToPlaylist($playlistId, $videoId);

    }

    public function removeVideoFromPlaylist($userId, $playlistId, $videoId) {
        $playlist = $this->playlistModel->findById($playlistId);
        if (!$playlist || $playlist->user_id != $userId) {
            throw new UnauthorizedException("You don't own this playlist");
        }

        return $this->playlistVideoModel->removeFromPlaylist($playlistId, $videoId);
    }

    public function reorderPlaylistVideos($userId, $playlistId, array $newOrder) {
        $playlist = $this->playlistModel->findById($playlistId);
        if (!$playlist || $playlist->user_id != $userId) {
            throw new UnauthorizedException("You don't own this playlist");
        }

        return $this->playlistVideoModel->reorderPlaylist($playlistId, $newOrder);
    }

    public function getPlaylistVideos($userId, $playlistId) {
        $playlist = $this->playlistModel->findById($playlistId);
        if (!$playlist || $playlist->user_id != $userId) {
            throw new UnauthorizedException("You don't own this playlist");
        }

        return $this->playlistVideoModel->getVideos($playlistId);
    }
}