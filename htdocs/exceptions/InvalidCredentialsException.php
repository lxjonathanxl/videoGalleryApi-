<?php
class InvalidCredentialsException extends UserServiceException {
    protected $statusCode = 401;

    public function __construct(string $message = "Invalid credentials") {
        parent::__construct($message);
    }
}