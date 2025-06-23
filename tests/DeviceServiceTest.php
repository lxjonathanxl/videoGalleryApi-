<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/models/Device.php';
require_once __DIR__ . '/../src/models/DevicePlaylist.php';
require_once __DIR__ . '/../src/models/Playlist.php';
require_once __DIR__ . '/../src/services/DeviceService.php';
require_once __DIR__ . '/../src/services/PlaylistService.php';
require_once __DIR__ . '/../src/exceptions/DeviceNotFoundException.php';
require_once __DIR__ . '/../src/exceptions/DeviceServiceException.php';
require_once __DIR__ . '/../src/exceptions/InvalidInputException.php';
require_once __DIR__ . '/../src/exceptions/DeviceLimitExceededException.php';
require_once __DIR__ . '/../src/exceptions/DatabaseException.php';
require_once __DIR__ . '/../src/exceptions/UnauthorizedException.php';

class DeviceServiceTest extends TestCase {
    private PDO $pdo;
    private DeviceService $deviceService;
    private $mockUserService;
    private $mockPlaylistService;

    protected function setUp(): void {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("PRAGMA foreign_keys = ON;");

        // Create users table
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            );
        ");

        // Create devices table
        $sql = require __DIR__ . '/../src/migrations/device_table_sql.php';
        $this->pdo->exec($sql);

        // Create playlists table
        $this->pdo->exec("
            CREATE TABLE playlists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ");

        // Create device_playlists table
        $this->pdo->exec("
        CREATE TABLE device_playlists (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        device_id INTEGER NOT NULL,
        playlist_id INTEGER NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
        FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE
            );
        ");

        // Insert test user
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Test User')");

        $deviceModel = new Device($this->pdo);
        $devicePlaylistModel = new DevicePlaylist($this->pdo);

        $this->mockUserService = $this->createMock(UserService::class);
        $this->mockPlaylistService = $this->createMock(PlaylistService::class);

        $this->deviceService = new DeviceService(
            $deviceModel,
            $devicePlaylistModel,
            $this->mockUserService,
            $this->mockPlaylistService
        );
    }

    protected function tearDown(): void {
         unset($this->pdo);
    }

    public function testRegisterDeviceSuccess() {
        // Mock user exists
        $this->mockUserService->method('userExists')
            ->willReturn(true);
        
        $result = $this->deviceService->registerDevice(1, 'ABC123DEF456GHI789JKL0MN');
        
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('ABC123DEF456GHI789JKL0MN', $result['device_code']);
    }

    public function testRegisterDeviceUserNotFound() {
        $this->expectException(DeviceNotFoundException::class);
        
        $this->mockUserService->method('userExists')
            ->willReturn(false);
            
        $this->deviceService->registerDevice(999, 'VALID123CODE4567890ABC');
    }

    public function testRegisterDeviceInvalidCode() {
        $this->expectException(InvalidInputException::class);
        
        $this->mockUserService->method('userExists')
            ->willReturn(true);
            
        $this->deviceService->registerDevice(1, 'invalid-code!');
    }

    public function testRegisterDeviceDuplicateCode() {
        $this->expectException(InvalidInputException::class);
        
        $this->mockUserService->method('userExists')
            ->willReturn(true);
            
        // Insert duplicate device directly
        $stmt = $this->pdo->prepare("INSERT INTO devices (user_id, device_code) VALUES (1, ?)");
        $stmt->execute(['DUPLICATE123CODE456']);
            
        $this->deviceService->registerDevice(1, 'DUPLICATE123CODE456');
    }

    public function testFindByDeviceCodeSuccess() {
        // Insert test device
        $stmt = $this->pdo->prepare("INSERT INTO devices (user_id, device_code) VALUES (1, ?)");
        $stmt->execute(['TESTCODE1234567890ABC']);
        $deviceId = $this->pdo->lastInsertId();
        
        $foundId = $this->deviceService->findByDeviceCode('TESTCODE1234567890ABC');
        $this->assertEquals($deviceId, $foundId);
    }

    public function testFindByDeviceCodeNotFound() {
        $this->expectException(DeviceNotFoundException::class);
        $this->deviceService->findByDeviceCode('NONEXISTENTCODE123');
    }

    public function testFindUserDevicesSuccess() {
        $this->mockUserService->method('userExists')
            ->willReturn(true);  // ✅ Mock user exists

    // Insert test devices
        $stmt = $this->pdo->prepare("INSERT INTO devices (user_id, device_code) VALUES (1, ?)");
        $stmt->execute(['DEVICE1']);
        $stmt->execute(['DEVICE2']);
    
        $devices = $this->deviceService->findUserDevices(1);
        $this->assertCount(2, $devices);
        $this->assertEquals('DEVICE1', $devices[0]->device_code);
        $this->assertEquals('DEVICE2', $devices[1]->device_code);
    }


    public function testFindUserDevicesUserNotFound() {
        $this->expectException(DeviceNotFoundException::class);
        $this->mockUserService->method('userExists')
            ->willReturn(false);
            
        $this->deviceService->findUserDevices(999);
    }

    public function testDeleteDeviceSuccess() {
        // Insert test device
        $stmt = $this->pdo->prepare("INSERT INTO devices (user_id, device_code) VALUES (1, ?)");
        $stmt->execute(['DELETE_TEST']);
        $deviceId = $this->pdo->lastInsertId();
        
        $result = $this->deviceService->deleteDevice($deviceId, 1);
        $this->assertTrue($result);
        
        // Verify deletion
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM devices WHERE id = ?");
        $stmt->execute([$deviceId]);
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testDeleteDeviceNotFound() {
        $this->expectException(DeviceNotFoundException::class);
        $this->deviceService->deleteDevice(9999, 1);
    }

    public function testDeleteDeviceUnauthorized() {
        // Insert test device for user 2
        $stmt = $this->pdo->prepare("INSERT INTO users (name) VALUES (?)");
        $stmt->execute(['User Two']);
        $userId2 = $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("INSERT INTO devices (user_id, device_code) VALUES (2, ?)");
        $stmt->execute(['UNAUTHORIZED']);
        $deviceId = $this->pdo->lastInsertId();
        
        $this->expectException(UnauthorizedException::class);
        $this->deviceService->deleteDevice($deviceId, 1); // User 1 trying to delete
    
    }

    // ✅ New: Assign playlist to device
    public function testAssignPlaylistToDeviceSuccess() {
        // Mock user and playlist ownership
        $this->mockPlaylistService->method('getPlaylistById')
            ->willReturn((object)['id' => 1, 'user_id' => 1]);

        // Insert test device
        $this->pdo->exec("INSERT INTO devices (user_id, device_code) VALUES (1, 'TESTDEVICE')");
        $deviceId = $this->pdo->lastInsertId();

        // Insert playlist
        $this->pdo->exec("INSERT INTO playlists (user_id, name) VALUES (1, 'My Playlist')");
        $playlistId = $this->pdo->lastInsertId();

        $result = $this->deviceService->assignPlaylistToDevice($deviceId, $playlistId, 1);
        $this->assertTrue($result);

        // Check database
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM device_playlists WHERE device_id = ? AND playlist_id = ?");
        $stmt->execute([$deviceId, $playlistId]);
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function testAssignPlaylistUnauthorizedDevice() {
        $this->expectException(UnauthorizedException::class);

        // Mock playlist owned by user 1
        $this->mockPlaylistService->method('getPlaylistById')
            ->willReturn((object)['id' => 1, 'user_id' => 1]);

        // Insert device owned by user 2
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Other User')");
        $userId2 = $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO devices (user_id, device_code) VALUES ($userId2, 'DEVICE2')");
        $deviceId = $this->pdo->lastInsertId();

        // Insert playlist owned by user 1
        $this->pdo->exec("INSERT INTO playlists (user_id, name) VALUES (1, 'My Playlist')");
        $playlistId = $this->pdo->lastInsertId();

        $this->deviceService->assignPlaylistToDevice($deviceId, $playlistId, 1);
    }

    public function testAssignPlaylistUnauthorizedPlaylist() {
        $this->expectException(UnauthorizedException::class);

        // Mock playlist NOT owned by user
        $this->mockPlaylistService->method('getPlaylistById')
            ->willReturn((object)['id' => 1, 'user_id' => 999]); // wrong owner

        // Insert device owned by user 1
        $this->pdo->exec("INSERT INTO devices (user_id, device_code) VALUES (1, 'DEVICE')");
        $deviceId = $this->pdo->lastInsertId();

        // Insert playlist
        $this->pdo->exec("INSERT INTO playlists (user_id, name) VALUES (1, 'Playlist')");
        $playlistId = $this->pdo->lastInsertId();

        $this->deviceService->assignPlaylistToDevice($deviceId, $playlistId, 1);
    }

    // ✅ New: Test getDevicePlaybackData
    public function testGetDevicePlaybackDataSuccess() {
        // Insert device
        $this->pdo->exec("INSERT INTO devices (user_id, device_code) VALUES (1, 'DEVICE123')");
        $deviceId = $this->pdo->lastInsertId();

        // Insert playlist
        $this->pdo->exec("INSERT INTO playlists (user_id, name) VALUES (1, 'Playlist')");
        $playlistId = $this->pdo->lastInsertId();

        // Assign playlist
        $this->pdo->exec("INSERT INTO device_playlists (device_id, playlist_id) VALUES ($deviceId, $playlistId)");

        // Mock playlist videos
        $this->mockPlaylistService->method('getPlaylistVideos')
            ->willReturn([
                ['id' => 1, 'title' => 'Video 1'],
                ['id' => 2, 'title' => 'Video 2']
            ]);

        $result = $this->deviceService->getDevicePlaybackData('DEVICE123', 1);

        $this->assertCount(2, $result);
        $this->assertEquals('Video 1', $result[0]['title']);
    }

    public function testGetDevicePlaybackDataDeviceNotFound() {
        $this->expectException(DeviceNotFoundException::class);
        $this->deviceService->getDevicePlaybackData('INVALIDDEVICE', 1);
    }

    public function testGetDevicePlaybackDataNoActivePlaylist() {
        $this->expectException(DeviceNotFoundException::class);

        // Insert device
        $this->pdo->exec("INSERT INTO devices (user_id, device_code) VALUES (1, 'DEVICE')");
        $deviceId = $this->pdo->lastInsertId();

        // No playlist assigned
        $this->deviceService->getDevicePlaybackData('DEVICE', 1);
    }
}
