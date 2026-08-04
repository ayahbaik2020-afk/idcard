<?php

namespace App\Controllers;

use PDO;

use App\Repositories\ContractorRepository;

class SanctionController
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index()
    {
        // Reaktivasi kontraktor yang status Banned tapi sanksinya sudah
        // berakhir/dicabut, supaya daftar ini tetap akurat.
        (new ContractorRepository($this->pdo))->autoReactivateExpiredBanned();

        // List banned contractors
        $stmt = $this->pdo->query(
            "SELECT s.id, c.name, c.id_card, c.photo, cc.name as company_name, s.reason, s.sanction_type, s.start_date, s.end_date, s.is_permanent"
            . " FROM contractors c"
            . " JOIN sanctions s ON c.id = s.contractor_id"
            . " JOIN contractor_companies cc ON c.company_id = cc.id"
            . " WHERE c.status = 'Banned' AND s.revoked_at IS NULL AND (s.is_permanent = 1 OR s.end_date >= CURDATE())"
            . " ORDER BY s.start_date DESC"
        );
        $banned_contractors = $stmt->fetchAll();

        $data = compact('banned_contractors');

        $content = '';
        ob_start();
        extract($data);
        include __DIR__ . '/../../templates/sanctions/list.php';
        $content = ob_get_clean();
        include __DIR__ . '/../../templates/layout.php';
    }

    public function history($contractor_id)
    {
        if (!$contractor_id) {
            header('Location: index.php?page=contractors');
            exit();
        }

        // Contractor info for the page header
        $c_stmt = $this->pdo->prepare(
            "SELECT c.id, c.name, c.id_card, c.photo, c.plant_location, c.status, cc.name as company_name"
            . " FROM contractors c"
            . " JOIN contractor_companies cc ON c.company_id = cc.id"
            . " WHERE c.id = ?"
        );
        $c_stmt->execute([$contractor_id]);
        $contractor = $c_stmt->fetch();
        if (!$contractor) {
            header('Location: index.php?page=contractors');
            exit();
        }

        $stmt = $this->pdo->prepare(
            "SELECT s.*, c.name as contractor_name, cc.name as company_name, v.name as violation_name \n"
            . "FROM sanctions s \n"
            . "JOIN contractors c ON s.contractor_id = c.id \n"
            . "JOIN contractor_companies cc ON c.company_id = cc.id \n"
            . "LEFT JOIN violations v ON s.violation_id = v.id \n"
            . "WHERE s.contractor_id = ? ORDER BY s.start_date DESC"
        );
        $stmt->execute([$contractor_id]);
        $sanctions = $stmt->fetchAll();

        // Per-sanction status label (Berlaku / Selesai / Dicabut) so the
        // history page can tell at a glance which records are still live.
        $today = date('Y-m-d');
        foreach ($sanctions as &$s) {
            if (!empty($s['revoked_at'])) {
                $s['status'] = 'Dicabut';
            } elseif ($s['is_permanent'] == 1) {
                $s['status'] = 'Berlaku (permanen)';
            } elseif (empty($s['end_date']) || $s['end_date'] >= $today) {
                $s['status'] = 'Berlaku';
            } else {
                $s['status'] = 'Selesai';
            }
        }
        unset($s);

        $total_count = count($sanctions);

        $data = compact('contractor', 'sanctions', 'total_count');

        $content = '';
        ob_start();
        extract($data);
        include __DIR__ . '/../../templates/sanctions/history.php';
        $content = ob_get_clean();
        include __DIR__ . '/../../templates/layout.php';
    }

    public function create()
    {
        $contractors_stmt = $this->pdo->query(
            "SELECT c.id, c.name, c.id_card, cc.name as company_name"
            . " FROM contractors c"
            . " JOIN contractor_companies cc ON c.company_id = cc.id"
            . " WHERE c.status = 'Active'"
            . " ORDER BY c.name"
        );
        $contractors = $contractors_stmt->fetchAll();

        $violations_stmt = $this->pdo->query("SELECT * FROM violations ORDER BY name");
        $violations = $violations_stmt->fetchAll();

        $data = compact('contractors', 'violations');

        $content = '';
        ob_start();
        extract($data);
        include __DIR__ . '/../../templates/sanctions/form.php';
        $content = ob_get_clean();
        include __DIR__ . '/../../templates/layout.php';
    }

    public function store()
    {
        $contractor_id = $_POST['contractor_id'] ?? '';
        $violation_id = $_POST['violation_id'] ?? '';
        $new_violation_name = $_POST['new_violation_name'] ?? '';
        $sanction_type = $_POST['sanction_type'] ?? '';
        $banned_days = $_POST['banned_days'] ?? 0;
        $reason = $_POST['reason'] ?? '';

        if ($violation_id === 'new_violation' && !empty($new_violation_name)) {
            // Check if violation already exists
            $stmt = $this->pdo->prepare("SELECT id FROM violations WHERE name = ?");
            $stmt->execute([$new_violation_name]);
            $existing_violation_id = $stmt->fetchColumn();

            if ($existing_violation_id) {
                $violation_id = $existing_violation_id;
            } else {
                // Insert new violation
                $stmt = $this->pdo->prepare("INSERT INTO violations (name) VALUES (?)");
                $stmt->execute([$new_violation_name]);
                $violation_id = $this->pdo->lastInsertId();
            }
        }

        $start_date = date('Y-m-d');
        $end_date = null;
        $is_permanent = 0;
        $status = 'Banned';

        if ($sanction_type == 'BANNED') {
            if ($banned_days > 0) {
                $end_date = date('Y-m-d', strtotime("+$banned_days days"));
            } else {
                $is_permanent = 1;
            }
        } elseif ($sanction_type == 'SP1' || $sanction_type == 'SP2') {
            $end_date = date('Y-m-d', strtotime("+30 days")); // Assume 30 days
        }

        // Insert sanction
        $stmt = $this->pdo->prepare(
            "INSERT INTO sanctions (contractor_id, violation_id, sanction_type, start_date, end_date, is_permanent, reason)"
            . " VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$contractor_id, $violation_id, $sanction_type, $start_date, $end_date, $is_permanent, $reason]);

        // Update contractor status
        $this->pdo->prepare("UPDATE contractors SET status = ? WHERE id = ?")->execute([$status, $contractor_id]);

        // Log activity
        $this->logActivity('create', 'sanctions', $this->pdo->lastInsertId(), "Created sanction for contractor ID: $contractor_id");

        header('Location: index.php?page=sanctions');
        exit();
    }

    public function edit($id)
    {
        // Fetch the specific sanction to edit
        $stmt = $this->pdo->prepare("SELECT * FROM sanctions WHERE id = ?");
        $stmt->execute([$id]);
        $sanction = $stmt->fetch();

        if (!$sanction) {
            // Handle not found case
            header("Location: index.php?page=sanctions&error=not_found");
            exit();
        }

        // Fetch contractors and violations for dropdowns
        $contractors_stmt = $this->pdo->query("SELECT id, name, id_card FROM contractors ORDER BY name");
        $contractors = $contractors_stmt->fetchAll();

        $violations_stmt = $this->pdo->query("SELECT * FROM violations ORDER BY name");
        $violations = $violations_stmt->fetchAll();

        $data = compact('sanction', 'contractors', 'violations');

        $content = '';
        ob_start();
        extract($data);
        include __DIR__ . '/../../templates/sanctions/form.php';
        $content = ob_get_clean();
        include __DIR__ . '/../../templates/layout.php';
    }

    public function update($id)
    {
        // Basic validation
        $violation_id = $_POST['violation_id'] ?? '';
        $new_violation_name = $_POST['new_violation_name'] ?? '';
        $sanction_type = $_POST['sanction_type'] ?? '';
        $reason = $_POST['reason'] ?? '';
        $start_date = $_POST['start_date'] ?? date('Y-m-d');
        $is_permanent = isset($_POST['is_permanent']) ? 1 : 0;
        $end_date = $_POST['end_date'] ?? null;

        if ($violation_id === 'new_violation' && !empty($new_violation_name)) {
            // Check if violation already exists
            $stmt = $this->pdo->prepare("SELECT id FROM violations WHERE name = ?");
            $stmt->execute([$new_violation_name]);
            $existing_violation_id = $stmt->fetchColumn();

            if ($existing_violation_id) {
                $violation_id = $existing_violation_id;
            } else {
                // Insert new violation
                $stmt = $this->pdo->prepare("INSERT INTO violations (name) VALUES (?)");
                $stmt->execute([$new_violation_name]);
                $violation_id = $this->pdo->lastInsertId();
            }
        }

        if ($is_permanent) {
            $end_date = null;
        }

        // Update sanction in the database
        $stmt = $this->pdo->prepare(
            "UPDATE sanctions "
            . " SET violation_id = ?, sanction_type = ?, reason = ?, start_date = ?, end_date = ?, is_permanent = ?"
            . " WHERE id = ?"
        );
        $stmt->execute([$violation_id, $sanction_type, $reason, $start_date, $end_date, $is_permanent, $id]);

        // Log activity
        $this->logActivity('update', 'sanctions', $id, "Updated sanction ID: $id");

        header('Location: index.php?page=sanctions');
        exit();
    }

    public function release($id)
    {
        // First, get the sanction details, especially the contractor_id
        $stmt = $this->pdo->prepare("SELECT contractor_id FROM sanctions WHERE id = ?");
        $stmt->execute([$id]);
        $sanction = $stmt->fetch();

        if (!$sanction) {
            header("Location: index.php?page=sanctions&error=not_found");
            exit();
        }
        $contractor_id = $sanction['contractor_id'];

        // End the current sanction by setting the end_date to yesterday
        $update_stmt = $this->pdo->prepare("\n            UPDATE sanctions \n            SET end_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY), is_permanent = 0 \n            WHERE id = ?\n        ");
        $update_stmt->execute([$id]);

        // Check if the contractor has any other active sanctions (BANNED/SP1/SP2)
        $check_stmt = $this->pdo->prepare("\n            SELECT COUNT(*) \n            FROM sanctions \n            WHERE contractor_id = ? \n            AND sanction_type IN ('BANNED', 'SP1', 'SP2') \n            AND (is_permanent = 1 OR end_date >= CURDATE())\n        ");
        $check_stmt->execute([$contractor_id]);
        $active_bans = $check_stmt->fetchColumn();

        // If there are no other active bans, update the contractor's status
        if ($active_bans == 0) {
            $this->pdo->prepare("UPDATE contractors SET status = 'Active' WHERE id = ?")->execute([$contractor_id]);
        }

        // Log activity
        $this->logActivity('release', 'sanctions', $id, "Released sanction ID: $id for contractor ID: $contractor_id");

        header('Location: index.php?page=sanctions');
        exit();
    }

    private function logActivity($action, $table, $record_id, $description)
    {
        if (isset($_SESSION['user_id'])) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at)"
                . " VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$_SESSION['user_id'], $action, $table, $record_id, $description]);
        }
    }
}
