<?php
class Request {
    public static function getJsonBody() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Response::json(400, ['error' => 'Invalid JSON input']);
        }
        
        return $input;
    }
}