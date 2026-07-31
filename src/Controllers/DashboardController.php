<?php

namespace App\Controllers;

use PDO;

class DashboardController
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index()
    {
        // 1. Get System Settings (for Man Hours)
        $settings_stmt = $this->pdo->query("SELECT `key`, `value` FROM system_settings");
        $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Calculate dynamic plant_working_hours
        $settings['plant_working_hours'] = $this->calculatePlantWorkingHours($settings);
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM attendances WHERE DATE(check_in_time) = CURDATE() AND check_out_time IS NULL");
        $total_contractors_in_plant = $stmt->fetchColumn() ?? 0;

        // 3. Total Jenis Pelanggaran
        $stmt = $this->pdo->query("SELECT COUNT(id) as total_violations FROM violations");
        $total_violations = $stmt->fetchColumn() ?? 0;

        // 4. Pie Chart: Contractor Distribution per Plant
        $plant_distribution_stmt = $this->pdo->query(
            "SELECT plant_location, COUNT(*) as count"
            . " FROM contractors"
            . " WHERE plant_location IN ('CA PLANT', 'EDC PLANT', 'VCM PLANT', 'PVC PLANT')"
            . " GROUP BY plant_location"
        );
        $plant_distribution = $plant_distribution_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // 5. Bar Chart: Top 4 Companies by Contractor Count
        $company_count_stmt = $this->pdo->query(
            "SELECT cc.name, COUNT(c.id) as count"
            . " FROM contractors c"
            . " JOIN contractor_companies cc ON c.company_id = cc.id"
            . " GROUP BY cc.name"
            . " ORDER BY count DESC"
            . " LIMIT 4"
        );
        $company_count = $company_count_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // 6. Banned Contractors List
        $banned_stmt = $this->pdo->query(
            "SELECT c.name, c.id_card, c.photo, cc.name as company_name, s.reason"
            . " FROM contractors c"
            . " JOIN sanctions s ON c.id = s.contractor_id"
            . " JOIN contractor_companies cc ON c.company_id = cc.id"
            . " WHERE c.status = 'Banned' AND (s.is_permanent = 1 OR s.end_date >= CURDATE())"
        );
        $banned_contractors = $banned_stmt->fetchAll();

        // Pass data to the view
        $data = compact(
            'settings',
            'total_contractors_in_plant',
            'total_violations',
            'plant_distribution',
            'company_count',
            'banned_contractors'
        );

        // Render the view
        $content = '';
        ob_start();
        extract($data);
        include __DIR__ . '/../../templates/dashboard.php';
        $content = ob_get_clean();
        include __DIR__ . '/../../templates/layout.php';
    }

    /**
     * Calculates the accumulated "Man Hours Without LTI" figure:
     * base value from settings + sum of work_hours logged since the last
     * LTI reset date. Global across all plants (company-wide counter).
     */
    private function calculatePlantWorkingHours($settings)
    {
        $base_hours = (float) ($settings['base_plant_working_hours'] ?? 0);
        $reset_date = $settings['lti_last_reset_date'] ?? '1970-01-01';
        $sum_work_hours_stmt = $this->pdo->prepare("SELECT SUM(work_hours) FROM attendances WHERE check_in_time >= ?");
        $sum_work_hours_stmt->execute([$reset_date]);
        // SUM() returns NULL (not 0) when there are no matching rows yet.
        $additional_hours = (float) ($sum_work_hours_stmt->fetchColumn() ?? 0);
        return $base_hours + $additional_hours;
    }

    public function getUpdate()
    {
        header('Content-Type: application/json');

        // Get System Settings (for Man Hours)
        $settings_stmt = $this->pdo->query("SELECT `key`, `value` FROM system_settings");
        $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Calculate dynamic plant_working_hours
        $plant_working_hours = $this->calculatePlantWorkingHours($settings);

        // Total Kontraktor Dalam Plant
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM attendances WHERE DATE(check_in_time) = CURDATE() AND check_out_time IS NULL");
        $total_contractors_in_plant = $stmt->fetchColumn() ?? 0;

        // Total Jenis Pelanggaran
        $stmt = $this->pdo->query("SELECT COUNT(id) as total_violations FROM violations");
        $total_violations = $stmt->fetchColumn() ?? 0;

        echo json_encode([
            'plant_working_hours' => $plant_working_hours,
            'total_contractors_in_plant' => $total_contractors_in_plant,
            'total_violations' => $total_violations
        ]);
        exit;
    }
}
