<?php
class DeviceLimitExceededException extends DeviceServiceException {
    protected $statusCode = 429; // Too Many Requests
}