<?php
// check_ktp.php
// Return JSON only. Avoid printing PHP warnings/notices to keep JSON valid.
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json; charset=utf-8');

session_start();

// SECURITY: this is only meant to be called from the (authenticated)
// contractor create/edit form, not as a public data-lookup endpoint.
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['exists' => false, 'error' => 'Unauthorized']);
    exit;
}

$config = require __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(['exists' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get KTP number from POST request
$ktp_no = trim($_POST['ktp_no'] ?? '');
$contractor_id = isset($_POST['contractor_id']) ? (int) $_POST['contractor_id'] : 0;

if ($ktp_no === '') {
    echo json_encode(['exists' => false, 'error' => 'Empty KTP number']);
    exit;
}

// Check if KTP exists for another contractor (fully parameterized - no
// manual string concatenation into the SQL like the previous version).
$query = "SELECT id FROM contractors WHERE ktp_no = ?";
$params = [$ktp_no];
if ($contractor_id > 0) {
    $query .= " AND id != ?";
    $params[] = $contractor_id;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    echo json_encode(['exists' => (bool) $stmt->fetchColumn()]);
} catch (PDOException $e) {
    echo json_encode(['exists' => false, 'error' => 'Query failed']);
}
