<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/models/Video.php';
require_once __DIR__ . '/../src/services/VideoService.php';
require_once __DIR__ . '/../src/exceptions/VideoServiceException.php';
require_once __DIR__ . '/../src/exceptions/OwnershipVerificationException.php';

class VideoServiceTest extends TestCase {
    private $pdo;
    private $videoModel;
    private $deviceServiceMock;
    private $userServiceMock;
    private $videoService;

    protected function setUp(): void {
        // Setup SQLite in-memory DB
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create tables
        $this->createTables();

        // Insert sample data
        $this->insertSampleData();

        // Real Video model using in-memory DB
        $this->videoModel = new Video($this->pdo);

        // Mocks for DeviceService and UserService
        $this->deviceServiceMock = $this->createMock(DeviceService::class);
        $this->userServiceMock = $this->createMock(UserService::class);

        // VideoService with real Video model
        $this->videoService = new VideoService(
            $this->videoModel,
            $this->userServiceMock,
            $this->deviceServiceMock
        );
    }

    private function createTables(): void {
        $videoTableSql = require __DIR__ . '/../src/migrations/video_table_sql.php';

        $this->pdo->exec("
            CREATE TABLE devices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL
            );
        ");
        
        $this->pdo->exec($videoTableSql);
    }

    private function insertSampleData(): void {
        $this->pdo->exec("INSERT INTO devices (id, user_id) VALUES (1, 100), (2, 100), (3, 200);");
    }

    // ✅ Test registerNewVideo success
    public function testRegisterNewVideoSuccess() {
        $this->userServiceMock->method('userExists')->with(100)->willReturn(true);
        $this->deviceServiceMock->method('findUserDevices')->with(100)->willReturn([
            (object)['id' => 1],
            (object)['id' => 2]
        ]);

        $result = $this->videoService->registerNewVideo('http://example.com/video.mp4', 100);

        $this->assertCount(2, $result);

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM videos WHERE video_url = 'http://example.com/video.mp4'");
        $count = $stmt->fetchColumn();
        $this->assertEquals(2, $count);
    }

    // ❌ Test registerNewVideo with no devices
    public function testRegisterNewVideoNoDevices() {
        $this->userServiceMock->method('userExists')->willReturn(true);
        $this->deviceServiceMock->method('findUserDevices')->willReturn([]);

        $this->expectException(VideoServiceException::class);
        $this->expectExceptionMessage("No devices registered");

        $this->videoService->registerNewVideo('http://example.com/video.mp4', 100);
    }

    // ✅ Test deleteVideo success
    public function testDeleteVideoSuccess() {
        // Insert videos for devices 1 and 2
        $this->pdo->exec("
            INSERT INTO videos (id, device_id, video_url) VALUES
            (10, 1, 'http://example.com/video.mp4'),
            (11, 2, 'http://example.com/video.mp4'),
            (12, 3, 'http://example.com/other.mp4');
        ");

        $originalVideo = (object)['id' => 10, 'device_id' => 1, 'video_url' => 'http://example.com/video.mp4'];
        $device = (object)['id' => 1, 'user_id' => 100];

        $this->deviceServiceMock->method('findDeviceById')->with(1)->willReturn($device);
        $this->deviceServiceMock->method('findUserDevices')->with(100)->willReturn([
            (object)['id' => 1],
            (object)['id' => 2]
        ]);

        $result = $this->videoService->deleteVideo(10, 100);

        $this->assertEquals(2, $result);

        // Verify that videos for devices 1 and 2 were deleted, but not device 3
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM videos");
        $count = $stmt->fetchColumn();
        $this->assertEquals(1, $count);
    }

    // ❌ Test deleteVideo with invalid ownership
    public function testDeleteVideoOwnershipFails() {
        $this->pdo->exec("
            INSERT INTO videos (id, device_id, video_url) VALUES
            (10, 3, 'http://example.com/video.mp4');
        ");

        $device = (object)['id' => 3, 'user_id' => 999]; // Not user 100

        $this->deviceServiceMock->method('findDeviceById')->willReturn($device);

        $this->expectException(OwnershipVerificationException::class);
        $this->expectExceptionMessage("Video not owned by user");

        $this->videoService->deleteVideo(10, 100);
    }

    // ✅ Test getVideosIdFromUser success
    public function testGetVideosIdFromUserSuccess() {
    $this->pdo->exec("
        INSERT INTO videos (device_id, video_url) VALUES
        (1, 'http://example.com/1.mp4'),
        (1, 'http://example.com/2.mp4'),
        (2, 'http://example.com/3.mp4');
    ");

    $this->deviceServiceMock->method('findUserDevices')->with(100)->willReturn([
        (object)['id' => 2],
        (object)['id' => 1]
    ]);

    $result = $this->videoService->getVideosIdFromUser(100);

    $this->assertCount(2, $result);
    $this->assertEquals(1, $result[0]->id);
    $this->assertEquals(2, $result[1]->id);
}


    // ❌ Test getVideosIdFromUser no devices
    public function testGetVideosIdFromUserNoDevices() {
        $this->deviceServiceMock->method('findUserDevices')->willReturn([]);

        $this->expectException(VideoServiceException::class);
        $this->expectExceptionMessage("user: 100 has no devices");

        $this->videoService->getVideosIdFromUser(100);
    }

    // ✅ Test getVideoUrlFromId success
    public function testGetVideoUrlFromIdSuccess() {
        $this->pdo->exec("
            INSERT INTO videos (id, device_id, video_url) VALUES
            (5, 1, 'http://example.com/video.mp4');
        ");

        $result = $this->videoService->getVideoUrlFromId(5);

        $this->assertEquals('http://example.com/video.mp4', $result);
    }

    // ❌ Test getVideoUrlFromId not found
    public function testGetVideoUrlFromIdNotFound() {
        $this->expectException(VideoServiceException::class);
        $this->expectExceptionMessage("Video not found");

        $this->videoService->getVideoUrlFromId(999);
    }
}
