<?php
// Generates a QR code image on the fly, purely locally (no third-party
// API call) so contractor ID-card data is never sent off-server and the
// feature keeps working without internet access.

require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

$data = $_GET['data'] ?? '';
if ($data === '') {
    http_response_code(400);
    exit;
}

// Keep the same visual size (~95x95) used by the ID card template.
$result = Builder::create()
    ->writer(new PngWriter())
    ->data($data)
    ->size(95)
    ->margin(2)
    ->build();

header('Content-Type: ' . $result->getMimeType());
echo $result->getString();
