<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/models/PlaylistVideo.php';

class PlaylistVideoTest extends TestCase {
    private PDO $pdo;
    private PlaylistVideo $playlistVideo;

    protected function setUp(): void {
        // Create SQLite in-memory connection
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Enable foreign key support (optional here)
        $this->pdo->exec('PRAGMA foreign_keys = ON;');

        // Create required tables
        $this->pdo->exec("
            CREATE TABLE playlists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            );
        ");

        $this->pdo->exec("
            CREATE TABLE videos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL
            );
        ");

        $this->pdo->exec("
            CREATE TABLE playlist_videos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                playlist_id INTEGER NOT NULL,
                video_id INTEGER NOT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
                FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
                UNIQUE (playlist_id, video_id)
            );
        ");

        // Insert sample playlist and videos
        $this->pdo->exec("INSERT INTO playlists (name) VALUES ('My Playlist');");
        $this->pdo->exec("INSERT INTO videos (title) VALUES ('Video 1'), ('Video 2'), ('Video 3');");

        // Initialize PlaylistVideo instance
        $this->playlistVideo = new PlaylistVideo($this->pdo);
    }

    protected function tearDown(): void {
        unset($this->pdo);
    }

    // ✅ Tests

    public function testAddVideoToPlaylistAtNextAvailablePosition(): void {
        $result = $this->playlistVideo->addVideoToPlaylist(1, 1);
        $this->assertTrue($result);

        $position = $this->playlistVideo->getVideoPosition(1, 1);
        $this->assertEquals(1, $position);
    }

    public function testAddVideoToPlaylistAtSpecificPosition(): void {
        // Add first video normally
        $this->playlistVideo->addVideoToPlaylist(1, 1);
        $this->playlistVideo->addVideoToPlaylist(1, 2);

        // Insert video 3 at position 2 (should push video 2 to position 3)
        $this->playlistVideo->addVideoToPlaylist(1, 3, 2);

        $this->assertEquals(1, $this->playlistVideo->getVideoPosition(1, 1));
        $this->assertEquals(2, $this->playlistVideo->getVideoPosition(1, 3));
        $this->assertEquals(3, $this->playlistVideo->getVideoPosition(1, 2));
    }

    public function testRemoveFromPlaylistAndShiftPositions(): void {
        $this->playlistVideo->addVideoToPlaylist(1, 1);
        $this->playlistVideo->addVideoToPlaylist(1, 2);
        $this->playlistVideo->addVideoToPlaylist(1, 3);

        // Remove video 2 (position 2)
        $result = $this->playlistVideo->removeFromPlaylist(1, 2);
        $this->assertTrue($result);

        $this->assertEquals(1, $this->playlistVideo->getVideoPosition(1, 1));
        $this->assertEquals(2, $this->playlistVideo->getVideoPosition(1, 3));
        $this->assertNull($this->playlistVideo->getVideoPosition(1, 2)); // Should not exist
    }

    public function testUpdatePosition(): void {
        $this->playlistVideo->addVideoToPlaylist(1, 1);
        $this->playlistVideo->addVideoToPlaylist(1, 2);

        // Move video 2 to position 1
        $result = $this->playlistVideo->updatePosition(1, 2, 1);
        $this->assertTrue($result);

        $this->assertEquals(1, $this->playlistVideo->getVideoPosition(1, 2));
    }

    public function testReorderPlaylist(): void {
        $this->playlistVideo->addVideoToPlaylist(1, 1);
        $this->playlistVideo->addVideoToPlaylist(1, 2);
        $this->playlistVideo->addVideoToPlaylist(1, 3);

        // New order: [3, 1, 2]
        $result = $this->playlistVideo->reorderPlaylist(1, [3, 1, 2]);
        $this->assertTrue($result);

        $this->assertEquals(1, $this->playlistVideo->getVideoPosition(1, 3));
        $this->assertEquals(2, $this->playlistVideo->getVideoPosition(1, 1));
        $this->assertEquals(3, $this->playlistVideo->getVideoPosition(1, 2));
    }

    public function testVideoExistsInPlaylist(): void {
        $this->playlistVideo->addVideoToPlaylist(1, 1);

        $this->assertTrue($this->playlistVideo->videoExistsInPlaylist(1, 1));
        $this->assertFalse($this->playlistVideo->videoExistsInPlaylist(1, 2));
    }
}
