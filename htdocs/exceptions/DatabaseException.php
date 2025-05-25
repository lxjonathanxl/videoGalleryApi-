<?php
class DatabaseException extends UserServiceException {
    protected $statusCode = 500;

    public function __construct(string $message = "Database error") {
        parent::__construct($message);
    }
}