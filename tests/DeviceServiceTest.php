<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/models/Device.php';
require_once __DIR__ . '/../src/services/DeviceService.php';
require_once __DIR__ . '/../src/exceptions/DeviceServiceException.php';
require_once __DIR__ . '/../src/exceptions/DeviceNotFoundException.php';
require_once __DIR__ . '/../src/exceptions/InvalidInputException.php';
require_once __DIR__ . '/../src/exceptions/DeviceLimitExceededException.php';
require_once __DIR__ . '/../src/exceptions/DatabaseException.php';
require_once __DIR__ . '/../src/exceptions/UnauthorizedException.php';

class DeviceServiceTest extends TestCase {
    private PDO $pdo;
    private DeviceService $deviceService;
    private $mockUserService;

    protected function setUp(): void {
    $this->pdo = new PDO('sqlite::memory:');
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Enable foreign keys
    $this->pdo->exec("PRAGMA foreign_keys = ON;");

    // Create users table (required for foreign key)
    $this->pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        );
    ");

    // Create devices table
    $sql = require __DIR__ . '/../src/migrations/device_table_sql.php';
    $this->pdo->exec($sql);

    // Insert dummy user to satisfy foreign key constraint
    $this->pdo->exec("INSERT INTO users (name) VALUES ('Test User')");

    $deviceModel = new Device($this->pdo);
    $this->mockUserService = $this->createMock(UserService::class);

    $this->deviceService = new DeviceService($deviceModel, $this->mockUserService);
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
}