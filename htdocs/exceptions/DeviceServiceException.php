abstract class DeviceServiceException extends Exception {
    protected $statusCode = 400;
    public function getStatusCode(): int { return $this->statusCode; }
}