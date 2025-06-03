<?php
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
}