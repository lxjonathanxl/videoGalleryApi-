<?php
class OwnershipVerificationException extends VideoServiceException {
    public function __construct($message = "", $code = 403) {
        parent::__construct($message, $code);
    }
}