<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Device.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../exceptions/UserServiceException.php';

class UserService {
    private $userModel;
    private $deviceModel;
    private $videoModel;
    private $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo;
        $this->userModel = new User($pdo);
        $this->deviceModel = new Device();
        $this->videoModel = new Video();
    }

    // 1. Register new user
    public function registerUser(string $email, string $password): array {
        try {
            // Validate password strength
            if (strlen($password) < 8) {
                throw new UserServiceException("Password must be at least 8 characters");
            }

            // Check email existence
            if ($this->userModel->findByEmail($email)) {
                throw new UserServiceException("Email already registered");
            }

            $userId = $this->userModel->create($email, $password);
            return ['id' => $userId, 'email' => $email];
        } catch (PDOException $e) {
            throw new UserServiceException("Registration failed: " . $e->getMessage());
        }
    }

    // 2. Get user devices
    public function getUserDevices(int $userId): array {
        $user = $this->userModel->findById($userId);
        if (!$user) {
            throw new UserNotFoundException("User not found");
        }
        
        return $this->deviceModel->findByUserId($userId);
    }

    // 3. Get user videos
    public function getUserVideos(int $userId): array {
        $user = $this->userModel->findById($userId);
        if (!$user) {
            throw new UserNotFoundException("User not found");
        }
        
        return $this->videoModel->findByUserId($userId);
    }

    // 4. Delete user account
    public function deleteUser(int $userId, string $password): bool {
        $user = $this->userModel->findById($userId);
        if (!$user || !$user->verifyPassword($password)) {
            throw new InvalidCredentialsException("Invalid credentials");
        }

        // Database foreign keys will cascade delete devices/videos
        return $this->userModel->delete($userId);
    }

    // 5. Change email
    public function changeEmail(int $userId, string $newEmail, string $password): bool {
        $user = $this->userModel->findById($userId);
        if (!$user || !$user->verifyPassword($password)) {
            throw new InvalidCredentialsException("Invalid credentials");
        }

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            throw new UserServiceException("Invalid email format");
        }

        $existingUser = $this->userModel->findByEmail($newEmail);
        if ($existingUser && $existingUser->id !== $userId) {
            throw new UserServiceException("Email already in use");
        }

        $user->email = $newEmail;
        return $user->update();
    }

    // 6. Change password
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool {
        $user = $this->userModel->findById($userId);
        if (!$user || !$user->verifyPassword($currentPassword)) {
            throw new InvalidCredentialsException("Invalid current password");
        }

        if (strlen($newPassword) < 8) {
            throw new UserServiceException("New password too short");
        }

        $user->password_hash = password_hash($newPassword, PASSWORD_BCRYPT);
        return $user->update();
    }

    public function findIdByEmail(string $email): int {
        try {
            $user = $this->userModel->findByEmail($email);
            
            if (!$user) {
                throw new UserNotFoundException("User not found with email: $email");
            }
            
            return $user->id;
        } catch (PDOException $e) {
            throw new DatabaseException("Database operation failed: " . $e->getMessage());
        }
    }

    public function login(string $email, string $password): int {
        try {
            $userId = $this->findIdByEmail($email);
            $user = $this->userModel->findById($userId);
            
            if (!$user->verifyPassword($password)) {
                throw new InvalidCredentialsException();
            }
            
            return $userId;
        } catch (UserServiceException $e) {
            throw $e; // Re-throw custom exceptions
        } catch (Exception $e) {
            throw new DatabaseException("Login failed: " . $e->getMessage());
        }
    }


}