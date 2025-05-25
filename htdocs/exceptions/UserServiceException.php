<?php
abstract class UserServiceException extends Exception {
    // Base class for all user service exceptions
    protected $statusCode = 400;

    public function getStatusCode(): int {
        return $this->statusCode;
    }
}