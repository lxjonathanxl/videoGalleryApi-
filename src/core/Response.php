<?php
class Response {
    public static function json(int $status, $data = []) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['status' => $status, 'data' => $data]);
        exit;
    }
}