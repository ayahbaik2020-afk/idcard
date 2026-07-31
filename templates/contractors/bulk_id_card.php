<?php
$base_url = 'http://192.168.20.17:8081/idcard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Cetak Massal Kartu ID (3-Part Final)</title>
<style>
  @page {
    size: A4 portrait;
    margin: 5mm;
  }
  body {
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
    background-color: #f0f2f5;
  }
  .no-print {
    text-align: center;
    margin: 20px;
  }
  .btn { padding: 10px 20px; border: none; cursor: pointer; text-decoration: none; display: inline-block; margin: 4px 2px; }
  .btn-primary { background-color: #007bff; color: white; }
  .btn-secondary { background-color: #6c757d; color: white; }

  .page-container {
      display: flex;
      flex-direction: column;
      align-items: center;
  }

  .id-card-container {
    display: flex;
    justify-content: center;
    width: 211.5mm; /* 68.5mm * 3 + 2mm * 2 */
    height: 97mm;
    border: 1px dashed grey;
    box-sizing: border-box;
    margin-bottom: 10mm;
    page-break-inside: avoid;
    background-color: white;
  }

  .section {
    margin-right: 1mm;
  }

  .section:last-child {
    margin-right: 0;
  }

  /* Page break for printing, 4 cards per page */
  .id-card-container:nth-child(4n) {
      page-break-after: always;
  }

  .section {
    width: 68.5mm;
    height: 97mm;
    position: relative;
    color: black;
    box-sizing: border-box;
    border-left: 1px dashed grey;
    background-size: contain;
    background-position: center;
    background-repeat: no-repeat;
    overflow: hidden;
  }

    /* Make left/right backgrounds fill the section height similarly */
    .section.left-content, .section.right-content {
      background-size: 99% !important;
      background-position: center center !important;
    }

  .section:first-child {
    border-left: none;
  }

  /* Assign backgrounds to each section */
  .section.middle-content { background-image: url('<?php echo $base_url; ?>/uploads/background/2.png'); }

  /* --- Data Positioning based on kartu.jpg --- */
  .photo {
    position: absolute;
    top: 18.5mm;
    left: 22mm; /* Center horizontally in 68.5mm section */
    width: 25mm;
    height: 25mm;
    object-fit: cover;
    border: 1px solid #fff;
    background-color: #eee;
    border-radius: 50%; /* Make it circular */
  }
  .photo-left {
    position: absolute;
    top: 22.5mm; /* Raised by 1mm more */
    left: 17mm; /* Shifted left by 5mm */
    width: 35.75mm; /* Increased by 10% more */
    height: 35.75mm; /* Increased by 10% more */
    object-fit: cover;
    border: 1px solid #fff;
    background-color: #eee;
    border-radius: 50%; /* Make it circular */
  }
  .name {
    position: absolute;
    top: 19mm;
    left: 35mm;
    font-weight: bold;
    font-size: 10px;
    width: 48mm;
    text-align: left;
    color: #000;
  }
  .id-card {
    position: absolute;
    top: 24mm;
    left: 35mm;
    font-size: 10px;
    color: #000;
  }
  .company-name {
    position: absolute;
    top: 29mm;
    left: 35mm;
    font-size: 10px;
    font-weight: bold;
    width: 48mm;
    text-align: left;
    color: #000;
  }
  .name-left {
    position: absolute;
    top: 63.25mm; /* 5mm below photo */
    left: 0;
    width: 68.5mm;
    text-align: center;
    font-weight: bold;
    font-size: 10px;
    color: #000;
  }
  .id-card-left {
    position: absolute;
    top: 66.25mm; /* Slightly spaced: 3mm below name */
    left: 0;
    width: 68.5mm;
    text-align: center;
    font-size: 13px; /* 30% larger */
    font-weight: bold;
    color: #000;
  }
  .company-name-left {
    position: absolute;
    top: 69.25mm; /* Slightly more spaced: 3mm below id-card */
    left: 0;
    width: 68.5mm;
    text-align: center;
    font-size: 10px;
    font-weight: bold;
    color: #000;
  }
  .reg-date-left {
    position: absolute;
    top: 74.25mm; /* Below company name */
    left: 0;
    width: 68.5mm;
    text-align: center;
    font-size: 10px;
    color: #000;
  }
  .exp-date-left {
    position: absolute;
    top: 77.25mm; /* Same spacing as between name/id/company */
    left: 0;
    width: 68.5mm;
    text-align: center;
    font-size: 10px;
    color: #000;
  }
  .plant-arc {
    position: absolute;
    top: 0;
    left: 0;
    width: 68.5mm;
    height: 97mm;
    pointer-events: none;
  }
  .reg-date, .exp-date {
    position: absolute;
    font-size: 8px;
    bottom: 10.5mm;
    color: #000;
  }
  .reg-date {
    left: 35mm;
  }
  .exp-date {
    left: 58mm;
  }
  .qr-code {
      position: absolute;
      bottom: 2mm;
      right: 2mm;
      width: 12mm;
      height: 12mm;
  }
  .qr-code-middle {
    position: absolute;
    top: 5mm; /* Lowered a bit more from 2mm */
    left: 25.18mm; /* Re-centered for new width: (68.5 - 18.15) / 2 */
    width: 18.15mm; /* 60% of previous 30.25mm */
    height: 18.15mm; /* 60% of previous 30.25mm */
    object-fit: cover;
  }
  .id-card-middle {
    position: absolute;
    top: 23.3mm; /* Tighter gap below the QR code */
    left: 0;
    width: 68.5mm;
    text-align: center;
    font-size: 15px;
    font-weight: bold;
    color: #000;
  }
  .qr-code-right {
    position: absolute;
    top: 18.5mm;
    left: 13.25mm; /* Center in 68.5mm section: (68.5 - 42) / 2 = 13.25mm */
    width: 42mm; /* 20% larger: 35mm * 1.2 = 42mm */
    height: 42mm; /* 20% larger: 35mm * 1.2 = 42mm */
    object-fit: cover;
  }
  .photo-right {
    position: absolute;
    bottom: 6mm; /* Lowered slightly */
    right: 2mm;
    width: 12mm;
    height: 16mm; /* Changed to 3:4 ratio */
    object-fit: cover;
    border-radius: 3mm; /* Rounded corners instead of circular */
    border: 1px solid #fff;
    background-color: #eee;
  }
  .name-right {
    position: absolute;
    top: 61mm; /* Raised 15mm up */
    left: 0;
    width: 68.5mm;
    text-align: center;
    font-weight: bold;
    font-size: 10px;
    color: #000;
  }
  .id-card-right {
    position: absolute;
    top: 64mm; /* Slightly spaced: 3mm below name */
    left: 0;
    width: 68.5mm;
    text-align: center;
    font-size: 15px; /* 50% larger: 10px * 1.5 = 15px */
    font-weight: bold;
    color: #000;
  }
  .company-name-right {
    position: absolute;
    top: 68mm; /* Slightly more spaced: 4mm below id-card */
    left: 0;
    width: 68.5mm;
    text-align: center;
    font-size: 10px;
    font-weight: bold;
    color: #000;
  }
  .reg-date-right {
    position: absolute;
    top: 73mm; /* Below company name */
    left: 0;
    width: 68.5mm;
    text-align: center;
    font-size: 10px;
    color: #000;
  }
  .exp-date-right {
    position: absolute;
    top: 76mm; /* Same spacing as between name/id/company */
    left: 0;
    width: 68.5mm;
    text-align: center;
    font-size: 10px;
    color: #000;
  }

  /* --- Middle Section Content --- */
  .notes-section {
      padding: 4mm;
      font-size: 6px;
      line-height: 1.2;
      color: #fff;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
  }
  .notes-section h3 {
      margin-top: 0;
      margin-bottom: 2px;
      font-size: 8px;
      text-align: center;
  }
  .note-list {
    margin-left: 0;
    padding-left: 8px;
    margin-bottom: 2px;
  }
  .emergency {
    font-weight: bold;
    margin-top: 2px;
    text-align: center;
  }

  @media print {
    body { background-color: white; }
    .no-print { display: none; }
    .id-card-container { border: 1px dashed grey; }
    .page-container { margin: 0; padding: 0; }
    .id-card-container:last-child { page-break-after: auto; }
  }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn btn-primary" onclick="window.print()">Cetak Semua</button>
    <a href="index.php?page=contractors" class="btn btn-secondary">Kembali ke Daftar</a>
</div>

<div class="page-container">
<?php foreach ($contractors as $contractor): ?>
  <?php
    // Normalize plant name and map to background filenames
    $plant_raw = $contractor['plant_location'] ?? '';
    $plant = strtoupper(trim($plant_raw));
    $plant_normalized = preg_replace('/[^A-Z]/', '', $plant);

  // Helper: return URL if file exists on filesystem, else fall back to default URL
  // Always resolves against public/uploads/background only (no other public folder).
  // Appends filemtime as a cache-busting query string so replaced images show immediately.
  if (!function_exists('bg_url_or_default')) {
  function bg_url_or_default($base_url, $relative_public_path, $filename, $fallback_filename) {
    // relative_public_path should be like '/uploads/background'
    $fs_path = __DIR__ . '/../../public' . $relative_public_path . '/' . $filename;
    if (file_exists($fs_path)) {
      $url = rtrim($base_url, '/') . $relative_public_path . '/' . $filename;
      return $url . '?v=' . filemtime($fs_path);
    }
    // fallback (still within the same background folder)
    $fallback_fs_path = __DIR__ . '/../../public' . $relative_public_path . '/' . $fallback_filename;
    $url = rtrim($base_url, '/') . $relative_public_path . '/' . $fallback_filename;
    if (file_exists($fallback_fs_path)) {
      return $url . '?v=' . filemtime($fallback_fs_path);
    }
    return $url;
  }
  }

  $rel_path = '/uploads/background';
    // Defaults
    $left_bg = bg_url_or_default($base_url, $rel_path, '1.png', '1.png');
    $middle_bg = bg_url_or_default($base_url, $rel_path, '2.png', '2.png');
    $right_bg = bg_url_or_default($base_url, $rel_path, '3.png', '3.png');

    if ($plant_normalized === 'CA' || $plant === 'CA PLANT') {
      $left_bg = bg_url_or_default($base_url, $rel_path, 'ca-1.png', '1.png');
      $middle_bg = bg_url_or_default($base_url, $rel_path, 'ca-2.png', '2.png');
      $right_bg = bg_url_or_default($base_url, $rel_path, 'ca-3.png', '3.png');
    } elseif ($plant_normalized === 'EDC' || $plant_normalized === 'VCM' || $plant === 'EDC PLANT' || $plant === 'VCM PLANT') {
      $left_bg = bg_url_or_default($base_url, $rel_path, 'edcvcm-1.png', '1.png');
      $middle_bg = bg_url_or_default($base_url, $rel_path, 'edcvcm-2.png', '2.png');
      $right_bg = bg_url_or_default($base_url, $rel_path, 'edcvcm-3.png', '3.png');
    } elseif ($plant_normalized === 'PVC' || $plant === 'PVC PLANT') {
      $left_bg = bg_url_or_default($base_url, $rel_path, 'pvc-1.png', '1.png');
      $middle_bg = bg_url_or_default($base_url, $rel_path, 'pvc-2.png', '2.png');
      $right_bg = bg_url_or_default($base_url, $rel_path, 'pvc-3.png', '3.png');
    }
  ?>
  <div class="id-card-container">
    <!-- Left Section (Copy of Card Face) -->
    <div class="section left-content" style="background-image: url('<?php echo $left_bg; ?>');">
        <svg class="plant-arc" viewBox="0 0 68.5 97" xmlns="http://www.w3.org/2000/svg">
          <path id="plantArc<?php echo (int)$contractor['id']; ?>" d="M15.27,33.24 A20.875,20.875 0 0,1 54.49,33.24" fill="none" />
          <text font-family="Arial, sans-serif" font-weight="bold" font-size="4.3" fill="#000">
            <textPath href="#plantArc<?php echo (int)$contractor['id']; ?>" startOffset="50%" text-anchor="middle"><?php echo strtoupper(htmlspecialchars($contractor['plant_location'])); ?></textPath>
          </text>
        </svg>
        <img src="<?php echo !empty($contractor['photo']) ? $base_url . '/uploads/photos/' . htmlspecialchars($contractor['photo']) : $base_url . '/assets/images/placeholder-avatar.svg'; ?>" alt="Foto" class="photo-left">
        <div class="name-left"><?php echo strtoupper(htmlspecialchars($contractor['name'])); ?></div>
        <div class="id-card-left"><?php echo htmlspecialchars($contractor['id_card']); ?></div>
        <div class="company-name-left"><?php echo strtoupper(htmlspecialchars($contractor['company_name'])); ?></div>
        <div class="reg-date-left">REG: <?php echo date('d-M-y', strtotime($contractor['registration_date'] ?? '')); ?></div>
        <div class="exp-date-left">EXP: <?php echo date('d-M-y', strtotime($contractor['expiry_date'] ?? '')); ?></div>
    </div>

  <!-- Middle Section (QR Code) -->
  <div class="section middle-content" style="background-image: url('<?php echo $middle_bg; ?>');">
    <img src="<?php echo !empty($contractor['qr_code']) ? $base_url . '/uploads/qrcodes/' . htmlspecialchars($contractor['qr_code']) : $base_url . '/qr_generator.php?data=' . urlencode($contractor['id_card']); ?>" alt="QR" class="qr-code-middle">
    <div class="id-card-middle"><?php echo htmlspecialchars($contractor['id_card']); ?></div>
  </div>

    <!-- Right Section (Copy of Card Face) -->
    <div class="section right-content" style="background-image: url('<?php echo $right_bg; ?>');">
        <img src="<?php echo !empty($contractor['qr_code']) ? $base_url . '/uploads/qrcodes/' . htmlspecialchars($contractor['qr_code']) : $base_url . '/qr_generator.php?data=' . urlencode($contractor['id_card']); ?>" alt="QR" class="qr-code-right">
        <img src="<?php echo !empty($contractor['photo']) ? $base_url . '/uploads/photos/' . htmlspecialchars($contractor['photo']) : $base_url . '/assets/images/placeholder-avatar.svg'; ?>" alt="Foto" class="photo-right">
        <div class="name-right"><?php echo strtoupper(htmlspecialchars($contractor['name'])); ?></div>
        <div class="id-card-right"><?php echo htmlspecialchars($contractor['id_card']); ?></div>
        <div class="company-name-right"><?php echo strtoupper(htmlspecialchars($contractor['company_name'])); ?></div>
        <div class="reg-date-right">REG: <?php echo date('d-M-y', strtotime($contractor['registration_date'] ?? '')); ?></div>
        <div class="exp-date-right">EXP: <?php echo date('d-M-y', strtotime($contractor['expiry_date'] ?? '')); ?></div>
    </div>
  </div>
<?php endforeach; ?>
</div>

</body>
</html>
