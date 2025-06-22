<?php
class NotFoundException extends Exception {
    protected $statusCode = 404;
    public function getStatusCode(): int { return $this->statusCode; }
}