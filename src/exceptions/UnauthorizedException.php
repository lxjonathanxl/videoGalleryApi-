<?php
class UnauthorizedException extends Exception {
    protected $statusCode = 403;
}