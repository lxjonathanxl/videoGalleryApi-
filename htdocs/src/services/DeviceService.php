<?php
require_once __DIR__ . '/../models/Device.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../exceptions/DeviceServiceException.php';
require_once __DIR__ . '/../exceptions/DeviceNotFoundException.php';
require_once __DIR__ . '/../exceptions/InvalidInputException.php';
require_once __DIR__ . '/../exceptions/DeviceLimitExceededException.php';
require_once __DIR__ . '/../exceptions/DatabaseException.php';
require_once __DIR__ . '/../exceptions/UnauthorizedException.php';



class DeviceService {
    private $deviceModel;
    private $userService;
    private $maxDevicesPerUser = 5; // Configurable limit

    public function __construct(?Device $deviceModel = null, ?UserService $userService = null) {
        $this->deviceModel = $deviceModel ?? new Device();
        $this->userService = $userService ?? new UserService();
    }


    // 1. Register new device
    public function registerDevice(int $userId, string $deviceCode): array {
        try {
            // Validate user exists
            if (!$this->userService->userExists($userId)) {
                throw new DeviceNotFoundException("User not found");
            }

            // Validate device code format
            if (!preg_match('/^[A-Z0-9]{24}$/', $deviceCode)) {
                throw new InvalidInputException("Invalid device code format");
            }

            // Check device limit
            $currentDevices = $this->deviceModel->findByUserId($userId);
            if (count($currentDevices) >= $this->maxDevicesPerUser) {
                throw new DeviceLimitExceededException("Maximum devices per user exceeded");
            }

            // Create device
            $deviceId = $this->deviceModel->create($userId, $deviceCode);
            
            return [
                'id' => $deviceId,
                'device_code' => $deviceCode,
                'user_id' => $userId
            ];
            
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new InvalidInputException("Device code already exists");
            }
            throw new DatabaseException("Device registration failed: " . $e->getMessage());
        }
    }

    // 2. Find device by device code
    public function findByDeviceCode(string $deviceCode): int {
        $device = $this->deviceModel->findIdByCode($deviceCode);
        if (!$device) {
            throw new DeviceNotFoundException("Device not found");
        }
        return $device;
    }

    // 3. Find user devices by user ID
    public function findUserDevices(int $userId): array {
        if (!$this->userService->userExists($userId)) {
            throw new DeviceNotFoundException("User not found");
        }
        
        return $this->deviceModel->findByUserId($userId);
    }

    // 4. Delete device
    public function deleteDevice(int $deviceId, int $userId): bool {
        try {
            // Verify device belongs to user
            $device = $this->deviceModel->findById($deviceId);
            
            if (!$device) {
                throw new DeviceNotFoundException("Device not found");
            }
            
            if ($device->user_id != $userId) {
                throw new UnauthorizedException("Device does not belong to user");
            }

            return $this->deviceModel->delete($deviceId);
            
        } catch (PDOException $e) {
            throw new DatabaseException("Device deletion failed: " . $e->getMessage());
        }
    }
}