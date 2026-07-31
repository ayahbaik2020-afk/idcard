<?php

namespace App\Repositories;

use PDO;

class CompanyRepository
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll()
    {
        $stmt = $this->pdo->query("SELECT * FROM contractor_companies ORDER BY name");
        return $stmt->fetchAll();
    }

    public function findByName($name)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM contractor_companies WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetchColumn();
    }

    public function insert($name)
    {
        $stmt = $this->pdo->prepare("INSERT INTO contractor_companies (name) VALUES (?)");
        $stmt->execute([$name]);
        return $this->pdo->lastInsertId();
    }
}
