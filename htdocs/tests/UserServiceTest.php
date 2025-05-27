<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/services/UserService.php';
require_once __DIR__ . '/../src/exceptions/UserServiceException.php';
require_once __DIR__ . '/../src/exceptions/UserNotFoundException.php';

class UserServiceTest extends TestCase {
    private PDO $pdo;
    private UserService $userService;

    protected function setUp(): void {
        // Create PDO connection
        $this->pdo = new PDO("mysql:host=localhost", 'root', '');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create and use test database
        $this->pdo->exec("CREATE DATABASE IF NOT EXISTS test_database");
        $this->pdo->exec("USE test_database");

        // Load SQL from the SQL-only file
        $sql = require __DIR__ . '/../src/migrations/users_table_sql.php';
        $this->pdo->exec($sql);

        // Start transaction for isolation
        $this->pdo->beginTransaction();

        // Initialize service
        $this->userService = new UserService($this->pdo);
    }

    protected function tearDown(): void {
        // Rollback changes after each test
        $this->pdo->rollBack();
    }

    // ✅ All your test methods here...

    public function testRegisterUserSuccess(): void {
        $email = 'test@example.com';
        $password = 'ValidPass123!';

        $result = $this->userService->registerUser($email, $password);

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals($email, $result['email']);
    }

    public function testRegisterUserWithExistingEmail(): void {
        $this->expectException(UserServiceException::class);

        $email = 'duplicate@example.com';
        $this->userService->registerUser($email, 'password1');
        $this->userService->registerUser($email, 'password2');
    }

    public function testFindIdByEmailSuccess(): void {
        $email = 'findme@example.com';
        $registered = $this->userService->registerUser($email, 'password');

        $userId = $this->userService->findIdByEmail($email);

        $this->assertEquals($registered['id'], $userId);
    }

    public function testFindIdByEmailNotFound(): void {
        $this->expectException(UserNotFoundException::class);

        $this->userService->findIdByEmail('nonexistent@example.com');
    }

    public function testChangePasswordSuccess(): void {
        $user = $this->userService->registerUser('changepass@test.com', 'oldPassword');

        $result = $this->userService->changePassword(
            $user['id'],
            'oldPassword',
            'newSecurePassword123'
        );

        $this->assertTrue($result);
    }
}
