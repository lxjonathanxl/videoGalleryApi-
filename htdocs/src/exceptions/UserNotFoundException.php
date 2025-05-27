<?php
class UserNotFoundException extends UserServiceException {
    protected $statusCode = 404;

    public function __construct(string $message = "User not found") {
        parent::__construct($message);
    }
}