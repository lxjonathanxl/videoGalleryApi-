<?php
require_once __DIR__ . '/../config/db.php';

class User {
    private $pdo;
    
    // Database fields (similar to entity class in Java)
    public $id;
    public $email;
    public $password_hash;
    public $created_at;
    
    public function __construct(?PDO $pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            global $pdo;
            $this->pdo = $pdo;
        }
    }

    // Create new user (similar to DAO insert)
    public function create($email, $password) {
        // Input validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format");
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO users (email, password_hash) 
                VALUES (:email, :password_hash)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'email' => $email,
            'password_hash' => $hashedPassword
        ]);

        return $this->pdo->lastInsertId();
    }

    // Find user by email (similar to DAO findBy)
    public function findByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        
        $stmt->setFetchMode(PDO::FETCH_INTO, $this);
        return $stmt->fetch();
    }

    // Verify password (similar to BCrypt.checkpw in Java)
    public function verifyPassword($password) {
        return password_verify($password, $this->password_hash);
    }

    // Optional: Update user details
    public function update() {
        $sql = "UPDATE users SET 
                email = :email
                WHERE id = :id";
                
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'email' => $this->email,
            'id' => $this->id
        ]);
    }

    public function findById($userId) {
    $sql = "SELECT * FROM users WHERE id = :id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['id' => $userId]);
    
    $stmt->setFetchMode(PDO::FETCH_INTO, $this);
    return $stmt->fetch();
    }

    public function delete($userId) {
        $sql = "DELETE FROM users WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $userId]);
    }
}