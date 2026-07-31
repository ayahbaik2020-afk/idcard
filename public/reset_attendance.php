<?php

session_start();

// SECURITY: this performs a destructive, system-wide write (force check-out
// every contractor currently inside the plant). It must never be reachable
// without an authenticated Super Admin session and must never run on a
// plain GET request (which browsers/crawlers/link-previews can trigger
// accidentally).

require_once __DIR__ . '/../vendor/autoload.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Super Admin') {
    http_response_code(403);
    echo 'Forbidden: hanya Super Admin yang login yang boleh mengakses halaman ini.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed: gunakan form konfirmasi untuk menjalankan reset ini.';
    exit;
}

$config = require __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}",
        $config['username'],
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare('UPDATE attendances SET check_out_time = NOW() WHERE check_out_time IS NULL');
    $stmt->execute();
    $rowCount = $stmt->rowCount();

    echo "Berhasil mereset data kehadiran. {$rowCount} data diperbarui.";
} catch (PDOException $e) {
    http_response_code(500);
    // Don't leak raw DB error details to the client.
    error_log('reset_attendance.php DB error: ' . $e->getMessage());
    echo "Gagal terhubung ke database. Silakan cek log server.";
}
