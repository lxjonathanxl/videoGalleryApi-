<?php

use src\Core\JWTHelper;

require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/JWTHelper.php';

class AuthController {
    private $userService;
    
    public function __construct() {
        $this->userService = new UserService();
    }
    
    public function register() {
        $input = Request::getJsonBody();
        $errors = $this->validateRegistration($input);
        
        if (!empty($errors)) {
            Response::json(400, ['errors' => $errors]);
        }
        
        try {
            $user = $this->userService->registerUser(
                $input['email'],
                $input['password']
            );
            
            Response::json(201, [
                'message' => 'User registered successfully',
                'user_id' => $user['id']
            ]);
        } catch (\Exception $e) {
            Response::json(400, ['error' => $e->getMessage()]);
        }
    }
    
    private function validateRegistration(array $input): array {
        $errors = [];
        $required = ['email', 'password', 'password_confirmation'];
        
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $errors[$field] = 'This field is required';
            }
        }
        
        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (!empty($input['password']) && $input['password'] !== $input['password_confirmation']) {
            $errors['password'] = 'Passwords do not match';
        }
        
        if (!empty($input['password']) && strlen($input['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
        
        return $errors;
    }

    public function login() {
    $input = Request::getJsonBody();
    
    // Validate input
    $errors = $this->validateLogin($input);
    if (!empty($errors)) {
        Response::json(400, ['errors' => $errors]);
    }
    
    try {
        // Authenticate user
        $user = $this->userService->authenticate(
            $input['email'],
            $input['password']
        );
        
        // Generate JWT token
        $token = JWTHelper::generateToken($user['id']);
        
        Response::json(200, [
            'message' => 'Login successful',
            'token' => $token,
            'user_id' => $user['id'],
            'expires_in' => $_ENV['JWT_EXPIRY']
        ]);
    } catch (\Exception $e) {
        Response::json(401, ['error' => $e->getMessage()]);
    }
}

private function validateLogin(array $input): array {
    $errors = [];
    
    if (empty($input['email'])) {
        $errors['email'] = 'Email is required';
    }
    
    if (empty($input['password'])) {
        $errors['password'] = 'Password is required';
    }
    
    return $errors;
}

}