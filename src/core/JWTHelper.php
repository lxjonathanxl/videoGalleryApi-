<?php
namespace src\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTHelper {
    public static function generateToken(int $userId): string {
        $secretKey = $_ENV['JWT_SECRET'];
        $expiry = time() + (int)$_ENV['JWT_EXPIRY'];
        
        $payload = [
            'iat' => time(),
            'exp' => $expiry,
            'sub' => $userId
        ];
        
        return JWT::encode($payload, $secretKey, 'HS256');
    }

    public static function validateToken(string $token): int {
        try {
            $secretKey = $_ENV['JWT_SECRET'];
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
            return $decoded->sub;
        } catch (\Exception $e) {
            throw new \Exception("Invalid token: " . $e->getMessage());
        }
    }
}