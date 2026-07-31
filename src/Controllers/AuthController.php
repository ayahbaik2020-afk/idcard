<?php

namespace App\Controllers;

use PDO;

class AuthController
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function login($email, $password)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email']; // Added this line
            header('Location: index.php?page=dashboard');
            exit();
        } else {
            header('Location: index.php?page=login&error=1');
            exit();
        }
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        header('Location: index.php?page=login');
        exit();
    }
}