<?php
namespace App\Middleware;

use App\Core\JWTHelper;
use Response;
use src\Core\JWTHelper as CoreJWTHelper;

require_once __DIR__ . '/../core/JWTHelper.php';
require_once __DIR__ . '/../core/Response.php';

class AuthMiddleware {
    public static function authenticate(): int {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Response::json(401, ['error' => 'Authorization header missing']);
        }
        
        $token = $matches[1];
        
        try {
            return CoreJWTHelper::validateToken($token);
        } catch (\Exception $e) {
            Response::json(401, ['error' => $e->getMessage()]);
        }
    }
}