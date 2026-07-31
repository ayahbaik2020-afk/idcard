<?php

namespace App\Controllers;

use PDO;
use Exception;
use App\Repositories\ContractorRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\MiscRepository;
use App\Services\ContractorService;

class ContractorController
{
    protected $pdo;
    protected $service;
    protected $companyRepo;
    protected $miscRepo;
    protected $contractorRepo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->contractorRepo = new ContractorRepository($pdo);
        $this->companyRepo = new CompanyRepository($pdo);
        $this->miscRepo = new MiscRepository($pdo);
        $this->service = new ContractorService($this->contractorRepo, $this->companyRepo);
    }

    public function index()
    {
        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
            'plant' => $_GET['plant'] ?? '',
            'company_id' => $_GET['company_id'] ?? '',
            'day' => $_GET['day'] ?? '',
            'month' => $_GET['month'] ?? '',
            'year' => $_GET['year'] ?? '',
            'sanksi' => $_GET['sanksi'] ?? ''
        ];
        // Separate query param from the router's own "page" (page=contractors)
        $pg = max(1, (int) ($_GET['pg'] ?? 1));

        $result = $this->service->getList($filters, $pg);
        $contractors = $result['data'];
        $pagination = $result;
        $companies = $this->companyRepo->getAll();

        $data = array_merge(['contractors' => $contractors, 'companies' => $companies, 'pagination' => $pagination], $filters);

        $this->renderView('contractors/list.php', $data);
    }

    public function inPlant()
    {
        $filters = [
            'search' => $_GET['search'] ?? '',
            'plant' => $_GET['plant'] ?? '',
            'company_id' => $_GET['company_id'] ?? ''
        ];
        $pg = max(1, (int) ($_GET['pg'] ?? 1));

        $result = $this->service->getInPlant($filters, $pg);
        $contractors = $result['data'];
        $pagination = $result;
        $companies = $this->companyRepo->getAll();

        $data = array_merge(['contractors' => $contractors, 'companies' => $companies, 'pagination' => $pagination], $filters);

        $this->renderView('contractors/in_plant.php', $data);
    }

    public function create()
    {
        $companies = $this->companyRepo->getAll();
        $this->renderView('contractors/form.php', compact('companies'));
    }

    public function store()
    {
        try {
            $this->service->createContractor($_POST, $_FILES);
            header('Location: index.php?page=contractors');
            exit();
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?page=contractors&action=create');
            exit();
        }
    }

    public function edit($id)
    {
        $contractor = $this->contractorRepo->findById($id);
        if (!$contractor) {
            http_response_code(404);
            echo "Contractor not found";
            exit();
        }

        $companies = $this->companyRepo->getAll();
        $violations = $this->miscRepo->getAllViolations();

        $this->renderView('contractors/form.php', compact('contractor', 'companies', 'violations'));
    }

    public function update($id)
    {
        try {
            $this->service->updateContractor($id, $_POST, $_FILES);
            header('Location: index.php?page=contractors');
            exit();
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?page=contractors&action=edit&id=' . $id);
            exit();
        }
    }

    public function delete($id)
    {
        $this->service->deleteContractor($id);
        header('Location: index.php?page=contractors');
        exit();
    }

    public function import()
    {
        $this->renderView('contractors/import.php', []);
    }

    public function handleImport()
    {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
            header('Location: index.php?page=contractors&action=import&error=1');
            exit();
        }

        try {
            $summary = $this->service->importCsv($_FILES['csv_file']['tmp_name']);
            $_SESSION['import_summary'] = $summary;
            header('Location: index.php?page=contractors&action=import');
            exit();
        } catch (Exception $e) {
            header('Location: index.php?page=contractors&action=import&error=2');
            exit();
        }
    }

    public function export()
    {
        $format = $_GET['format'] ?? 'csv';
        $contractors = $this->service->getListAll([]);

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="contractors_export_' . date('Y-m-d') . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID Card', 'KTP No', 'photo', 'Name', 'Company', 'Plant Location', 'Registration Date', 'Status']);

            foreach ($contractors as $c) {
                fputcsv($output, [
                    $c['id_card'] ?? '', $c['ktp_no'] ?? '', ($c['ktp_no'] ?? '') . '.jpg', 
                    $c['name'] ?? '', $c['company_name'] ?? '', $c['plant_location'] ?? '', 
                    $c['registration_date'] ?? '', $c['status'] ?? ''
                ]);
            }
            fclose($output);
            exit();
        }

        // Export as HTML Excel format
        if ($format === 'xlsx') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="contractors_export_' . date('Y-m-d') . '.xls"');
            echo "<table border=1><tr><th>ID Card</th><th>KTP No</th><th>photo</th><th>Name</th><th>Company</th><th>Plant Location</th><th>Registration Date</th><th>Status</th></tr>";
            foreach ($contractors as $c) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($c['id_card'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($c['ktp_no'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars(($c['ktp_no'] ?? '') . '.jpg') . '</td>';
                echo '<td>' . htmlspecialchars($c['name'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($c['company_name'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($c['plant_location'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($c['registration_date'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($c['status'] ?? '') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            exit();
        }
    }

    public function printIdCard($id)
    {
        $contractor = $this->contractorRepo->findById($id);
        if (!$contractor) {
            http_response_code(404);
            echo "Contractor not found";
            exit();
        }
        $settings = $this->miscRepo->getIdCardAndPlantSettings();
        $contractors = [$contractor];
        
        $this->renderStandaloneView('contractors/bulk_id_card.php', compact('contractors', 'settings'));
    }

    public function bulkPrint()
    {
        $ids = $_POST['contractor_ids'] ?? [];
        if (empty($ids)) {
            header('Location: index.php?page=contractors');
            exit();
        }

        $contractors = $this->contractorRepo->findByIds($ids);
        if (empty($contractors)) {
            http_response_code(404);
            echo "No contractors found for printing.";
            exit();
        }

        $settings = $this->miscRepo->getIdCardAndPlantSettings();
        
        $this->renderStandaloneView('contractors/bulk_id_card.php', compact('contractors', 'settings'));
    }

    private function renderView($templatePath, $data)
    {
        extract($data);
        ob_start();
        include __DIR__ . '/../../templates/' . $templatePath;
        $content = ob_get_clean();
        include __DIR__ . '/../../templates/layout.php';
    }

    private function renderStandaloneView($templatePath, $data)
    {
        extract($data);
        ob_start();
        include __DIR__ . '/../../templates/' . $templatePath;
        echo ob_get_clean();
    }
}