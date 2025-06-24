<?php
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/DeviceService.php';

class DeviceController {
    private $deviceService;

    public function __construct() {
        $this->deviceService = new DeviceService();
    }

    /**
     * Register a new device to a user
     */
    public function registerDevice() {
        $input = Request::getJsonBody();

        if (empty($input['user_id']) || empty($input['device_code'])) {
            Response::json(400, ['error' => 'user_id and device_code are required']);
        }

        try {
            $device = $this->deviceService->registerDevice(
                (int)$input['user_id'],
                strtoupper($input['device_code'])
            );

            Response::json(201, [
                'message' => 'Device registered successfully',
                'device' => $device
            ]);

        } catch (Exception $e) {
            Response::json(400, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get all devices for a user
     */
    public function getUserDevices() {
        $input = Request::getJsonBody();

        if (empty($input['user_id'])) {
            Response::json(400, ['error' => 'user_id is required']);
        }

        try {
            $devices = $this->deviceService->findUserDevices((int)$input['user_id']);
            Response::json(200, ['devices' => $devices]);
        } catch (Exception $e) {
            Response::json(400, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get device videos by device code (no authentication)
     */
    public function getDeviceVideos() {
        $input = Request::getJsonBody();

        if (empty($input['device_code'])) {
            Response::json(400, ['error' => 'device_code is required']);
        }

        try {
            $deviceCode = strtoupper($input['device_code']);

            // Use 0 as userId since this request doesn't need an authenticated user
            $videos = $this->deviceService->getDevicePlaybackData($deviceCode, 0);

            Response::json(200, ['videos' => $videos]);
        } catch (Exception $e) {
            Response::json(404, ['error' => $e->getMessage()]);
        }
    }
}
