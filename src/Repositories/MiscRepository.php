<?php

namespace App\Repositories;

use PDO;

class MiscRepository
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllViolations()
    {
        $stmt = $this->pdo->query("SELECT * FROM violations ORDER BY name");
        return $stmt->fetchAll();
    }

    public function getIdCardAndPlantSettings()
    {
        $stmt = $this->pdo->query("SELECT `key`, `value` FROM system_settings WHERE `key` LIKE 'id_card_%' OR `key` LIKE 'plant_color_%'");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
