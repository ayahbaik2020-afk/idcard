<?php

session_start();
header('Content-Type: application/json');

// Manual trigger for scripts/sync_from_cloud.php, called via AJAX from a
// "Sync Now" button in the local dashboard. Auth-gated the same way as
// reset_attendance.php: Super Admin session + POST only.

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Super Admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden: hanya Super Admin yang login yang boleh menjalankan sync.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$scriptPath = __DIR__ . '/../scripts/sync_from_cloud.php';

// PHP_BINARY under a web server often points at php-cgi.exe (which emits
// CGI headers like "X-Powered-By"/"Content-type" before real output when
// exec()'d), not the plain CLI php.exe. Prefer a real php.exe next to it
// when available, falling back to PHP_BINARY otherwise.
$phpBinary = PHP_BINARY;
if (stripos(basename($phpBinary), 'cgi') !== false) {
    $cliCandidate = dirname($phpBinary) . DIRECTORY_SEPARATOR . 'php.exe';
    if (is_file($cliCandidate)) {
        $phpBinary = $cliCandidate;
    }
}

$output = [];
$exitCode = 0;
exec(escapeshellarg($phpBinary) . ' ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $exitCode);

echo json_encode([
    'ok' => $exitCode === 0,
    'exit_code' => $exitCode,
    'log' => implode("\n", $output),
]);
