<?php

namespace App\Controllers;

use PDO;
use DateTime;

class PlantDisplayController
{
    protected $pdo;

    // Local placeholder avatar used whenever a contractor has no photo on
    // file. Previously this pointed at https://via.placeholder.com/150,
    // which requires internet access - a problem for kiosk PCs that may
    // run without a connection (the QR code feature was deliberately built
    // to work offline; the banned-contractor photo fallback should too).
    private const DEFAULT_AVATAR = 'assets/images/placeholder-avatar.svg';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Handle the initial page load for the plant display dashboard.
     */
    public function index($plant_slug = null)
    {
        // session_start() is already called in the main index.php

        // 1. Determine the plant name
        $plant_name = $this->getPlantNameFromRequest($plant_slug);

        // Get system settings
        $stmt = $this->pdo->query("SELECT `key`, `value` FROM system_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Calculate dynamic plant_working_hours
        $settings['plant_working_hours'] = $this->calculatePlantWorkingHours($settings);

        // 3. Get initial contractor count
        $count_stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM attendances\n            WHERE plant_location = ? AND DATE(check_in_time) = CURDATE() AND check_out_time IS NULL\n        ");
        $count_stmt->execute([$plant_name]);
        $contractor_count = $count_stmt->fetchColumn() ?? 0;

        // 3b. Same in-plant contractors grouped by company/PT
        $contractor_count_by_company = $this->getContractorCountByCompany($plant_name);

        // 4. Get banned contractors for initial load
        $banned_stmt = $this->pdo->prepare(
            "SELECT c.id, c.name, c.photo, cc.name as company_name\n            FROM active_bans s\n            JOIN contractors c ON s.contractor_id = c.id\n            JOIN contractor_companies cc ON c.company_id = cc.id\n            GROUP BY c.id\n        ");
        $banned_stmt->execute();
        $banned_contractors_raw = $banned_stmt->fetchAll(PDO::FETCH_ASSOC);

        $banned_contractors = array_map(function($c) {
            return [
                'id' => $c['id'],
                'name' => $c['name'],
                'company_name' => $c['company_name'],
                'photo' => $this->resolvePhotoUrl($c['photo'])
            ];
        }, $banned_contractors_raw);

        // 5. Pass data to the view
        $data = compact('plant_name', 'settings', 'contractor_count', 'contractor_count_by_company', 'banned_contractors');

        extract($data);
        include __DIR__ . '/../../templates/plant_display.php';
    }

    /**
     * Resolves a contractor's photo filename to a URL usable in the view,
     * falling back to a local (no-internet-required) placeholder avatar
     * when no photo is on file.
     */
    private function resolvePhotoUrl($photo)
    {
        if (!empty($photo)) {
            return 'uploads/photos/' . htmlspecialchars($photo);
        }
        return self::DEFAULT_AVATAR;
    }

    /**
     * Counts the contractors currently in the plant, grouped by company
     * (PT). Same scope as the main in-plant counter: checked in today and
     * not yet checked out.
     */
    private function getContractorCountByCompany($plant_name)
    {
        $stmt = $this->pdo->prepare(
            "SELECT cc.name AS company_name, COUNT(*) AS total\n            FROM attendances a\n            JOIN contractors c ON a.contractor_id = c.id\n            JOIN contractor_companies cc ON c.company_id = cc.id\n            WHERE a.plant_location = ? AND DATE(a.check_in_time) = CURDATE() AND a.check_out_time IS NULL\n            GROUP BY cc.name\n            ORDER BY total DESC, cc.name ASC\n        ");
        $stmt->execute([$plant_name]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculates the accumulated "Man Hours Without LTI" figure:
     * base value from settings + sum of work_hours logged since the last
     * LTI reset date. This is intentionally global across all plants (one
     * company-wide safety counter), not per-plant.
     */
    private function calculatePlantWorkingHours($settings)
    {
        $base_hours = (float) ($settings['base_plant_working_hours'] ?? 0);
        $reset_date = $settings['lti_last_reset_date'] ?? '1970-01-01';
        $sum_work_hours_stmt = $this->pdo->prepare("SELECT SUM(work_hours) FROM attendances WHERE check_in_time >= ?");
        $sum_work_hours_stmt->execute([$reset_date]);
        // SUM() returns NULL (not 0) when there are no matching rows yet,
        // e.g. right after a reset with no fresh check-ins - guard against
        // that so we don't add null to a number (deprecated in PHP 8.1+).
        $additional_hours = (float) ($sum_work_hours_stmt->fetchColumn() ?? 0);
        return $base_hours + $additional_hours;
    }

    /**
     * Handle AJAX requests for real-time dashboard updates.
     */
    public function getUpdate()
    {
        header('Content-Type: application/json');
        $plant_name = $_GET['plant'] ?? '';
        if (!$plant_name) {
            echo json_encode(['error' => 'Plant not specified']);
            exit;
        }

        // Recalculate the Man Hours Without LTI figure so it stays live
        // instead of being frozen at the value from initial page load.
        $settings_stmt = $this->pdo->query("SELECT `key`, `value` FROM system_settings");
        $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $plant_working_hours = $this->calculatePlantWorkingHours($settings);

        // Get current contractor count
        $count_stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM attendances\n            WHERE plant_location = ? AND DATE(check_in_time) = CURDATE() AND check_out_time IS NULL\n        ");
        $count_stmt->execute([$plant_name]);
        $contractor_count = $count_stmt->fetchColumn() ?? 0;

        // Per-company breakdown of the in-plant count
        $contractor_by_company = $this->getContractorCountByCompany($plant_name);

        // Check for a recent scan event (check-in or check-out in the last 15 seconds)
        $last_scan = null;
        $scan_stmt = $this->pdo->prepare(
            "SELECT a.check_in_time, a.check_out_time, c.name, c.photo, c.id_card, cc.name as company_name\n            FROM attendances a\n            JOIN contractors c ON a.contractor_id = c.id\n            JOIN contractor_companies cc ON c.company_id = cc.id\n            WHERE a.plant_location = ? \n            AND (\n                a.check_in_time >= DATE_SUB(NOW(), INTERVAL 15 SECOND)\n                OR\n                a.check_out_time >= DATE_SUB(NOW(), INTERVAL 15 SECOND)\n            )\n            ORDER BY GREATEST(a.check_in_time, IFNULL(a.check_out_time, a.check_in_time)) DESC\n            LIMIT 1\n        ");
        $scan_stmt->execute([$plant_name]);
        $recent_scan = $scan_stmt->fetch(PDO::FETCH_ASSOC);

        if ($recent_scan) {
            $is_check_out = $recent_scan['check_out_time'] && (time() - strtotime($recent_scan['check_out_time'])) < 15;
            $last_scan = [
                'name' => $recent_scan['name'],
                'photo' => $this->resolvePhotoUrl($recent_scan['photo']),
                'company_name' => $recent_scan['company_name'],
                'id_card' => $recent_scan['id_card'],
                'type' => $is_check_out ? 'check-out' : 'check-in',
                'time' => $is_check_out ? $recent_scan['check_out_time'] : $recent_scan['check_in_time'],
            ];
        }

        // Always fetch banned contractors for the slideshow
        $banned_stmt = $this->pdo->prepare(
            "SELECT c.id, c.name, c.photo, cc.name as company_name\n            FROM active_bans s\n            JOIN contractors c ON s.contractor_id = c.id\n            JOIN contractor_companies cc ON c.company_id = cc.id\n            GROUP BY c.id\n        ");
        $banned_stmt->execute();
        $banned_contractors_raw = $banned_stmt->fetchAll(PDO::FETCH_ASSOC);

        $banned_contractors = array_map(function($c) {
            return [
                'id' => $c['id'],
                'name' => $c['name'],
                'company_name' => $c['company_name'],
                'photo' => $this->resolvePhotoUrl($c['photo'])
            ];
        }, $banned_contractors_raw);

        echo json_encode([
            'contractor_count' => $contractor_count,
            'contractor_by_company' => $contractor_by_company,
            'plant_working_hours' => $plant_working_hours,
            'last_scan' => $last_scan,
            'banned_contractors' => $banned_contractors,
        ]);
        exit;
    }

    /**
     * Determines the plant name from the user's session or the URL slug.
     */
    private function getPlantNameFromRequest($plant_slug = null)
    {
        // This map is for URL slug resolution
        $plant_map = [
            'ca-plant' => 'CA PLANT',
            'edc-vcm-plant' => 'EDC/VCM PLANT', // Combined for slug
            'pvc-plant' => 'PVC PLANT'
        ];

        // Priority 1: Logged-in Admin Plant user
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin Plant' && isset($_SESSION['user_email'])) {
            $email_prefix = explode('@', $_SESSION['user_email'])[0]; // e.g., 'admin.edc'
            if (preg_match('/admin\.(.+)/', $email_prefix, $matches)) {
                $plant_code = strtolower($matches[1]); // e.g., 'edc'
                
                switch ($plant_code) {
                    case 'edc':
                    case 'vcm':
                        return 'EDC/VCM PLANT';
                    case 'pvc':
                        return 'PVC PLANT';
                    case 'ca':
                        return 'CA PLANT';
                }
            }
        }

        // Priority 2: URL Slug
        if ($plant_slug && isset($plant_map[$plant_slug])) {
            return $plant_map[$plant_slug];
        }

        // Default fallback
        return 'CA PLANT';
    }
}
