<?php

namespace App\Controllers;

use PDO;
use DateTime;
use App\Support\WorkHoursCalculator;
use App\Support\Paginator;

class AttendanceController
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index()
    {
        $search = $_GET['search'] ?? '';
        $plant = $_GET['plant'] ?? '';
        $company_id = $_GET['company_id'] ?? '';
        $start_date = $_GET['start_date'] ?? '';
        $end_date = $_GET['end_date'] ?? '';

        $query = "
            SELECT a.id, c.name as contractor_name, c.id_card, cc.name as company_name, a.plant_location, a.check_in_time, a.check_out_time, a.work_hours
            FROM attendances a
            JOIN contractors c ON a.contractor_id = c.id
            JOIN contractor_companies cc ON c.company_id = cc.id
            WHERE 1=1
        ";
        $params = [];

        if ($search) {
            $query .= " AND (c.name LIKE ? OR c.id_card LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($plant) {
            $query .= " AND a.plant_location = ?";
            $params[] = $plant;
        }

        if ($company_id) {
            $query .= " AND c.company_id = ?";
            $params[] = $company_id;
        }

        if ($start_date) {
            $query .= " AND DATE(a.check_in_time) >= ?";
            $params[] = $start_date;
        }

        if ($end_date) {
            $query .= " AND DATE(a.check_in_time) <= ?";
            $params[] = $end_date;
        }

        // If no date range is provided, default to today
        if (empty($start_date) && empty($end_date)) {
            $query .= " AND DATE(a.check_in_time) = CURDATE()";
        }

        // Count matching rows before ORDER BY/LIMIT for the pagination UI.
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM ($query) as sub");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $query .= " ORDER BY a.check_in_time DESC";

        $perPage = 50;
        $pg = max(1, (int) ($_GET['pg'] ?? 1));
        $offset = Paginator::offset($pg, $perPage);
        $query .= " LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $attendances = $stmt->fetchAll();

        $pagination = Paginator::meta($total, $pg, $perPage);

        // Calculate totals
        $totals = [];
        $totals['today'] = $this->pdo->query("SELECT SUM(work_hours) as total FROM attendances WHERE DATE(check_in_time) = CURDATE()")->fetchColumn();
        $totals['week'] = $this->pdo->query("SELECT SUM(work_hours) as total FROM attendances WHERE YEARWEEK(check_in_time, 1) = YEARWEEK(CURDATE(), 1)")->fetchColumn();
        $totals['month'] = $this->pdo->query("SELECT SUM(work_hours) as total FROM attendances WHERE YEAR(check_in_time) = YEAR(CURDATE()) AND MONTH(check_in_time) = MONTH(CURDATE())")->fetchColumn();
        $totals['year'] = $this->pdo->query("SELECT SUM(work_hours) as total FROM attendances WHERE YEAR(check_in_time) = YEAR(CURDATE())")->fetchColumn();
        $totals['all'] = $this->pdo->query("SELECT SUM(work_hours) as total FROM attendances")->fetchColumn();

        // Get companies for filter
        $companies_stmt = $this->pdo->query("SELECT * FROM contractor_companies ORDER BY name");
        $companies = $companies_stmt->fetchAll();

        $data = compact('attendances', 'companies', 'search', 'plant', 'company_id', 'start_date', 'end_date', 'totals', 'pagination');

        $content = '';
        ob_start();
        extract($data);
        // The view file will be created in the next step
        if (!file_exists(__DIR__ . '/../../templates/attendance/list.php')) {
            // Create a placeholder file if it doesn't exist
            $placeholder_content = "<h1>Attendance List</h1><p>Filters and table will be implemented here.</p>";
            file_put_contents(__DIR__ . '/../../templates/attendance/list.php', $placeholder_content);
        }
        include __DIR__ . '/../../templates/attendance/list.php';
        $content = ob_get_clean();
        include __DIR__ . '/../../templates/layout.php';
    }

    public function export()
    {
        $format = $_GET['format'] ?? 'csv';
        
        $search = $_GET['search'] ?? '';
        $plant = $_GET['plant'] ?? '';
        $company_id = $_GET['company_id'] ?? '';
        $start_date = $_GET['start_date'] ?? '';
        $end_date = $_GET['end_date'] ?? '';

        $query = "
            SELECT a.id, c.name as contractor_name, c.id_card, cc.name as company_name, a.plant_location, a.check_in_time, a.check_out_time, a.work_hours
            FROM attendances a
            JOIN contractors c ON a.contractor_id = c.id
            JOIN contractor_companies cc ON c.company_id = cc.id
            WHERE 1=1
        ";
        $params = [];

        if ($search) {
            $query .= " AND (c.name LIKE ? OR c.id_card LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($plant) {
            $query .= " AND a.plant_location = ?";
            $params[] = $plant;
        }

        if ($company_id) {
            $query .= " AND c.company_id = ?";
            $params[] = $company_id;
        }

        if ($start_date) {
            $query .= " AND DATE(a.check_in_time) >= ?";
            $params[] = $start_date;
        }

        if ($end_date) {
            $query .= " AND DATE(a.check_in_time) <= ?";
            $params[] = $end_date;
        }

        $query .= " ORDER BY a.check_in_time DESC";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="attendance_export_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID Card', 'Name', 'Company', 'Plant', 'Check In', 'Check Out', 'Work Hours']);

            foreach ($attendances as $attendance) {
                fputcsv($output, [
                    $attendance['id_card'],
                    $attendance['contractor_name'],
                    $attendance['company_name'],
                    $attendance['plant_location'],
                    $attendance['check_in_time'],
                    $attendance['check_out_time'],
                    $attendance['work_hours']
                ]);
            }

            fclose($output);
            exit();
        }

        if ($format === 'xlsx' && class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $headers = ['ID Card', 'Name', 'Company', 'Plant', 'Check In', 'Check Out', 'Work Hours'];
            $sheet->fromArray($headers, NULL, 'A1');
            $rowNum = 2;
            foreach ($attendances as $attendance) {
                $sheet->fromArray([
                    $attendance['id_card'],
                    $attendance['contractor_name'],
                    $attendance['company_name'],
                    $attendance['plant_location'],
                    $attendance['check_in_time'],
                    $attendance['check_out_time'],
                    $attendance['work_hours']
                ], NULL, 'A' . $rowNum);
                $rowNum++;
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="attendance_export_' . date('Y-m-d') . '.xlsx"');
            $writer->save('php://output');
            exit();
        }
    }

    public function scan()
    {
        header('Content-Type: application/json');

        $id_card = $_POST['id_card'] ?? '';
        $plant_location = $_POST['plant_location'] ?? '';

        if (!$id_card || !$plant_location) {
            echo json_encode(['success' => false, 'message' => 'Invalid input from client']);
            exit();
        }

        // Check if contractor exists and not banned
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.name, c.status, c.plant_location, c.expiry_date
            FROM contractors c
            WHERE c.id_card = ?
        ");
        $stmt->execute([$id_card]);
        $contractor = $stmt->fetch();

        if (!$contractor) {
            echo json_encode(['success' => false, 'message' => 'ID Card tidak ditemukan']);
            exit();
        }

        if ($contractor['status'] == 'Banned') {
            echo json_encode(['success' => false, 'message' => 'ID Card BANNED - Tidak boleh masuk']);
            exit();
        }

        $isExpired = !empty($contractor['expiry_date']) && $contractor['expiry_date'] < date('Y-m-d');

        // Optional: Keep server-side validation for security.
        // NOTE: the EDC/VCM plant display sends the combined location
        // 'EDC/VCM PLANT', but contractors are registered under the
        // separate 'EDC PLANT' or 'VCM PLANT' values (see form.php). Treat
        // those as a match for that specific combined display, otherwise
        // EDC/VCM contractors could never check in/out.
        $plant_matches = ($contractor['plant_location'] == $plant_location)
            || ($plant_location === 'EDC/VCM PLANT' && in_array($contractor['plant_location'], ['EDC PLANT', 'VCM PLANT'], true));

        if (!$plant_matches) {
            echo json_encode(['success' => false, 'message' => 'ID Card tidak sesuai dengan lokasi plant']);
            exit();
        }

        // Find the last attendance record for this contractor
        $stmt = $this->pdo->prepare("
            SELECT id, check_in_time, check_out_time
            FROM attendances
            WHERE contractor_id = ?
            ORDER BY check_in_time DESC LIMIT 1
        ");
        $stmt->execute([$contractor['id']]);
        $last_attendance = $stmt->fetch();

        $now = new DateTime();

        if ($last_attendance) {
            if ($last_attendance['check_out_time'] === null) {
                // Contractor is currently IN the plant
                $check_in_time = new DateTime($last_attendance['check_in_time']);

                if (WorkHoursCalculator::minutesHaveElapsed($check_in_time, $now, 5)) {
                    // More than 5 minutes have passed, so CHECK-OUT
                    $work_hours = WorkHoursCalculator::hoursBetween($check_in_time, $now);

                    $stmt = $this->pdo->prepare("
                        UPDATE attendances
                        SET check_out_time = NOW(), work_hours = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$work_hours, $last_attendance['id']]);

                    echo json_encode(['success' => true, 'message' => 'Check Out berhasil - Terima kasih!', 'type' => 'check-out']);
                    exit();
                } else {
                    // Less than 5 minutes, do nothing
                    echo json_encode(['success' => false, 'message' => 'Harap tunggu 5 menit setelah Check-in untuk melakukan Check-out.', 'type' => 'too-soon']);
                    exit();
                }
            } else {
                // Contractor is OUT of the plant
                $check_out_time = new DateTime($last_attendance['check_out_time']);

                if (WorkHoursCalculator::minutesHaveElapsed($check_out_time, $now, 5)) {
                    // More than 5 minutes have passed since last check-out, so CHECK-IN
                    if ($isExpired) {
                        echo json_encode(['success' => false, 'message' => 'ID Card EXPIRED (masa berlaku habis ' . $contractor['expiry_date'] . ') - Tidak boleh masuk. Hubungi admin untuk perpanjangan.', 'type' => 'expired']);
                        exit();
                    }

                    $stmt = $this->pdo->prepare("
                        INSERT INTO attendances (contractor_id, plant_location, check_in_time)
                        VALUES (?, ?, NOW())
                    ");
                    $stmt->execute([$contractor['id'], $plant_location]);

                    echo json_encode(['success' => true, 'message' => 'Check In berhasil - Selamat bekerja!', 'type' => 'check-in']);
                    exit();
                } else {
                    // Less than 5 minutes, do nothing
                    echo json_encode(['success' => false, 'message' => 'Harap tunggu 5 menit setelah Check-out untuk melakukan Check-in kembali.', 'type' => 'too-soon']);
                    exit();
                }
            }
        } else {
            // No previous record, so this is the first CHECK-IN
            if ($isExpired) {
                echo json_encode(['success' => false, 'message' => 'ID Card EXPIRED (masa berlaku habis ' . $contractor['expiry_date'] . ') - Tidak boleh masuk. Hubungi admin untuk perpanjangan.', 'type' => 'expired']);
                exit();
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO attendances (contractor_id, plant_location, check_in_time)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$contractor['id'], $plant_location]);

            echo json_encode(['success' => true, 'message' => 'Check In berhasil - Selamat bekerja!', 'type' => 'check-in']);
            exit();
        }
    }
}