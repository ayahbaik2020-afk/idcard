<?php
// Determine the base URL dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$script_name = $_SERVER['SCRIPT_NAME'];
// Assuming the script is in the root of the public folder
$base_url = $protocol . $host . rtrim(dirname($script_name), '/');

// Adjust base URL if the app is in a subdirectory
// NOTE: this vhost's document root already points at the `public/` folder
// (pretty URL), so the base URL must NOT include an extra "/public" - and
// the settings values below (id_card_logo_url etc.) already contain their
// own "uploads/settings/" prefix, so don't add it again here.
$project_subdirectory = '/idcard';
$base_url = $protocol . $host . $project_subdirectory;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print ID Card - <?php echo htmlspecialchars($contractor['name']); ?></title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            body {
                -webkit-print-color-adjust: exact; /* Chrome, Safari */
                print-color-adjust: exact; /* Standard */
            }
            .no-print {
                display: none;
            }
            .id-card {
                page-break-inside: avoid;
            }
        }
        body {
            background-color: #f0f2f5;
        }
        .id-card-container {
            width: 3.375in; /* 85.6mm */
            height: 2.125in; /* 53.98mm */
            margin: 50px auto;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .id-card {
            width: 100%;
            height: 100%;
            border: 1px solid #ccc;
            background-color: white;
            font-family: Arial, sans-serif;
            font-size: 10px;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .card-header {
            background-color: <?php echo $id_card_settings['id_card_header_color'] ?? '#0d6efd'; ?>;
            color: white;
            text-align: center;
            padding: 5px;
            font-weight: bold;
            font-size: 14px;
        }
        .card-body {
            display: flex;
            padding: 10px;
            flex-grow: 1;
        }
        .photo-qr-container {
            width: 45%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .photo {
            width: 1in;
            height: 1in;
            object-fit: cover;
            border: 2px solid #eee;
        }
        .qr-container {
            margin-top: 5px;
        }
        .info-container {
            width: 55%;
            padding-left: 10px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .info-item {
            margin-bottom: 4px;
        }
        .info-item strong {
            display: inline-block;
            width: 60px;
        }
        .card-footer {
            background-color: #f8f9fa;
            padding: 3px;
            text-align: center;
            font-size: 8px;
            border-top: 1px solid #eee;
        }
        .signature {
            width: 80px;
            height: auto;
        }
    </style>
</head>
<body>

<div class="container text-center mt-4 no-print">
    <button class="btn btn-primary" onclick="window.print()">Print ID Card</button>
    <a href="index.php?page=contractors" class="btn btn-secondary">Back to List</a>
</div>

<div class="id-card-container">
    <div class="id-card" id="idCard">
        <div class="card-header d-flex align-items-center justify-content-start" style="padding: 2px 5px;">
            <img src="<?php echo !empty($id_card_settings['id_card_logo_url']) ? $base_url . '/' . htmlspecialchars($id_card_settings['id_card_logo_url']) : $base_url . '/assets/images/placeholder-avatar.svg'; ?>" alt="Logo" style="height: 30px; margin-right: 10px;">
            <span style="font-weight: bold; font-size: 12px;"><?php echo $id_card_settings['id_card_title'] ?? 'KARTU KONTRAKTOR'; ?></span>
        </div>
        <div class="card-body">
            <div class="photo-qr-container">
                <img src="<?php echo !empty($contractor['photo']) ? $base_url . '/uploads/photos/' . htmlspecialchars($contractor['photo']) : $base_url . '/assets/images/placeholder-avatar.svg'; ?>" alt="Photo" class="photo">
                <div class="qr-container">
                    <img src="<?php echo $base_url; ?>/qr_generator.php?data=<?php echo urlencode($contractor['id_card']); ?>" alt="QR Code" width="60" height="60">
                </div>
            </div>
            <div class="info-container">
                <div class="info-item"><strong>ID Card:</strong> <?php echo htmlspecialchars($contractor['id_card']); ?></div>
                <div class="info-item"><strong>Nama:</strong> <span style="font-size: 12px; font-weight: bold;"><?php echo htmlspecialchars($contractor['name']); ?></span></div>
                <div class="info-item"><strong>Perusahaan:</strong> <?php echo htmlspecialchars($contractor['company_name']); ?></div>
                <div class="info-item"><strong>Plant:</strong> <?php echo htmlspecialchars($contractor['plant_location']); ?></div>
                <div class="info-item"><strong>Registrasi:</strong> <?php echo htmlspecialchars($contractor['registration_date']); ?></div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end align-items-center" style="padding: 2px 5px;">
            <div class="text-center">
                <img src="<?php echo !empty($id_card_settings['id_card_signature_url']) ? $base_url . '/' . htmlspecialchars($id_card_settings['id_card_signature_url']) : $base_url . '/assets/images/placeholder-avatar.svg'; ?>" alt="Signature" class="signature" style="height: 25px;">
                <div style="font-size: 7px;"><?php echo $id_card_settings['id_card_manager_name'] ?? 'Manager Name'; ?></div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
