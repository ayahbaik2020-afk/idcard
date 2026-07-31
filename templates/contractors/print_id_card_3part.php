<?php
// Full URL for proper image loading
$base_url = 'http://192.168.20.17:8081/idcard';

// Set up color mapping from settings, using correct plant names
$plant_color_map = [
    'CA PLANT' => $settings['plant_color_ca'] ?? '#008000',
    'EDC PLANT' => $settings['plant_color_edc_vcm'] ?? '#0000FF',
    'VCM PLANT' => $settings['plant_color_edc_vcm'] ?? '#0000FF', // Map VCM to the same color as EDC
    'PVC PLANT' => $settings['plant_color_pvc'] ?? '#FFFF00',
    'PVC PLANT' => $settings['plant_color_pvc'] ?? '#FFFF00'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Print ID Card</title>
<style>
  @page {
    size: A4;
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
  .id-card-container {
    display: flex;
    width: 190mm;
    height: 90mm;
    border: 1px solid black;
    box-sizing: border-box;
    margin-bottom: 20mm;
    page-break-inside: avoid;
    background-color: white;
  }
  .section {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    position: relative;
    color: black;
    padding: 25px 10px 10px;
    box-sizing: border-box;
    border-left: 1px solid black;
    height: 100%;
  }
  .section.left-content {
    padding-left: 30px;
    background-size: contain;
    background-position: center;
    background-repeat: no-repeat;
  }
  .section.right-content {
    padding-right: 30px;
    background-size: contain;
    background-position: center;
    background-repeat: no-repeat;
  }

  /* Ensure left/right backgrounds cover the entire section area */
  .section.left-content, .section.right-content {
    background-size: cover !important;
    background-position: center center !important;
  }
  .section:first-child {
    border-left: none;
  }
  .sidebar {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 20px;
    background: black;
    color: white;
    writing-mode: vertical-rl;
    text-align: center;
    font-weight: bold;
    font-size: 12px;
    padding: 4px 0;
    user-select: none;
    z-index: 1;
    transform: rotate(180deg);
  }
  .sidebar.left {
    left: 0;
  }
  .sidebar.right {
    right: 0;
  }
  .header-container {
    display: flex;
    align-items: center;
    margin-bottom: 4px;
    padding: 0;
  }
  .logo {
    width: 40px;
    height: 40px;
    margin-right: 8px;
  }
  .company-name {
    font-weight: bold;
    font-size: 12px; /* Reduced font size for better fit */
    white-space: nowrap; /* Prevent line wrapping */
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .photo-qr-container {
    display: flex;
    align-items: center;
    margin-bottom: 6px;
    gap: 6px;
  }
  .photo {
    width: 85px;
    height: 95px;
    border: 1px solid black;
    object-fit: cover;
  }
  .violation-box {
      font-size: 10px; /* Smaller font for violation text */
  }
  .sp-box {
    width: 15px; /* Smaller box */
    height: 15px;
    display: inline-block;
    vertical-align: middle;
    margin-left: 6px;
    border: 1px solid black;
  }
  .sp1 {
    background-color: yellow;
  }
  .sp2 {
    background-color: red;
  }
  .text-center {
    text-align: center;
  }
  .bold {
    font-weight: bold;
  }
  .info-box {
    border: 3px solid black;
    padding: 8px;
    margin-top: 4px;
    font-size: 12px;
    text-align: center;
    min-height: 40px;
    line-height: 1.2;
  }
  .notes-section h3 {
      margin-top: 0;
      margin-bottom: 4px;
      font-size: 14px;
  }
  .note-list {
    font-size: 8px;
    line-height: 1.1;
    margin-left: 0;
    padding-left: 5px;
    margin-bottom: 4px;
  }
  .note-list li {
    margin-bottom: 2px;
    text-align: left;
  }
  .emergency {
    color: red;
    font-weight: bold;
    margin-top: 4px;
  }
  .signature {
    margin-top: 5px;
    text-align: center;
    font-size: 10px;
    margin-bottom: 10px;
  }
  .signature-img {
    width: 80px;
    height: auto;
    display: block;
    margin: 0 auto 2px;
  }
  .signature .name {
    font-weight: bold;
    margin-top: 2px;
  }
  .qr-code {
    width: 95px;
    height: 95px;
    border: 1px solid black;
  }
  .bottom-right-letter {
    position: absolute;
    bottom: 10px;
    right: 4px;
    font-weight: bold;
    font-size: 24px;
  }
</style>
</head>
<body>
<div class="page">
<?php foreach ($contractors as $contractor): ?>
<?php
  // Determine the color for the current contractor
  $plant_location = $contractor['plant_location'] ?? '';
  $card_color = $plant_color_map[$plant_location] ?? '#FFFFFF'; // Default to white

  // Normalize plant and determine background images with filesystem fallback
  $plant = strtoupper(trim($plant_location));
  $plant_normalized = preg_replace('/[^A-Z]/', '', $plant);

  if (!function_exists('bg_url_or_default')) {
  function bg_url_or_default($base_url, $relative_public_path, $filename, $fallback_filename) {
    $url = rtrim($base_url, '/') . $relative_public_path . '/' . $filename;
    $fs_path = __DIR__ . '/../../public' . $relative_public_path . '/' . $filename;
    if (file_exists($fs_path)) {
      return $url;
    }
    return rtrim($base_url, '/') . $relative_public_path . '/' . $fallback_filename;
  }
  }

  // NOTE: this vhost's document root already points at the `public/`
  // folder (pretty URL), so URLs must NOT repeat "/public/" - that was
  // producing 404s for background/photo/qr images after the public/public
  // duplicate-folder cleanup.
  $rel_path = '/uploads/background';
  $left_bg = bg_url_or_default($base_url, $rel_path, '1.png', '1.png');
  $middle_bg = bg_url_or_default($base_url, $rel_path, '2.png', '2.png');
  $right_bg = bg_url_or_default($base_url, $rel_path, '3.png', '3.png');

  if ($plant_normalized === 'CA' || $plant === 'CA PLANT') {
    $left_bg = bg_url_or_default($base_url, $rel_path, 'ca-1.png', '1.png');
    $right_bg = bg_url_or_default($base_url, $rel_path, 'ca-3.png', '3.png');
  } elseif ($plant_normalized === 'EDC' || $plant_normalized === 'VCM' || $plant === 'EDC PLANT' || $plant === 'VCM PLANT') {
    $left_bg = bg_url_or_default($base_url, $rel_path, 'edcvcm-1.png', '1.png');
    $right_bg = bg_url_or_default($base_url, $rel_path, 'edcvcm-3.png', '3.png');
  } elseif ($plant_normalized === 'PVC' || $plant === 'PVC PLANT') {
    $left_bg = bg_url_or_default($base_url, $rel_path, 'pvc-1.png', '1.png');
    $right_bg = bg_url_or_default($base_url, $rel_path, 'pvc-3.png', '3.png');
  }
?>
  <div class="id-card-container">
    <!-- Left Section -->
    <div class="section left-content" style="background-color: <?php echo $card_color; ?>; background-image: url(<?php echo $left_bg; ?>) !important;">
      <div class="sidebar left">CONTRACTOR ENTRY PERMIT PASS</div>
      <div class="header-container">
        <img src="<?php echo !empty($settings['id_card_logo_url']) ? $base_url . '/' . htmlspecialchars($settings['id_card_logo_url']) : $base_url . '/assets/images/placeholder-avatar.svg'; ?>" alt="Logo" class="logo" />
        <div class="company-name">PT SULFINDO ADIUSAHA</div>
      </div>
      <div class="bold" style="font-size: 20px; margin-top: 6px; padding: 0 10px;"><?php echo htmlspecialchars($contractor['plant_location']); ?></div>
      <div class="photo-qr-container">
        <img src="<?php echo !empty($contractor['photo']) ? $base_url . '/uploads/photos/' . htmlspecialchars($contractor['photo']) : $base_url . '/assets/images/placeholder-avatar.svg'; ?>" alt="Photo" class="photo" />
        <img src="<?php echo !empty($contractor['qr_code']) ? $base_url . '/uploads/qrcodes/' . htmlspecialchars($contractor['qr_code']) : $base_url . '/qr_generator.php?data=' . urlencode($contractor['id_card']); ?>" alt="QR Code" class="qr-code" />
      </div>
      <div class="violation-box">
        <div>SP1 <span class="sp-box sp1"></span></div>
        <div>PELANGGARAN</div>
        <div>SP2 <span class="sp-box sp2"></span></div>
      </div>
      <div class="info-box">
        <div><?php echo strtoupper(htmlspecialchars($contractor['name'])); ?></div>
        <div><?php echo htmlspecialchars($contractor['id_card']); ?></div>
        <div><?php echo strtoupper(htmlspecialchars($contractor['company_name'])); ?></div>
        <div style="font-size: 11px; font-weight: bold;">
            <span style="margin-right: 10px;">REG: <?php echo date('d-M-y', strtotime($contractor['registration_date'] ?? '')); ?></span>
            <span>EXP: <?php echo date('d-M-y', strtotime($contractor['expiry_date'] ?? '')); ?></span>
        </div>
        <div>0</div>
      </div>
      <div class="bottom-right-letter">A</div>
    </div>
    <!-- Middle Section -->
    <div class="section" style="background-color: #FFFFFF; background-image: url(<?php echo $middle_bg; ?>); background-size: contain; background-position: center; background-repeat: no-repeat;">
      <div class="sidebar right">CONTRACTOR ENTRY PERMIT PASS</div>
      <div class="notes-section">
        <h3>NOTE</h3>
        <ol class="note-list">
          <li>ID Card harus dipakai selama berada di area PT. SULFINDO ADIUSAHA</li>
          <li>Pelanggaran tidak memakai ID Card dikeluarkan dari area PT. SULFINDO ADIUSAHA</li>
          <li>ID Card hanya berlaku bagi karyawan yang bersangkutan</li>
          <li>ID Card hanya berlaku sampai dengan tanggal yang dicantumkan, bila masa berlaku sudah habis segera diperpanjang ke SHE Departement</li>
          <li>Bila ID Card hilang dikenakan biaya administrasi Rp. 50.000,-</li>
        </ol>
        <div class="emergency">EMERGENCY CALL :</div>
        <div style="font-size: 10px;">1. SHE Dept : Telp,: 1900 / 1901</div>
        <div style="font-size: 10px;">2. Security Pos1 : Telp. CA : 1110, VCM : 2220, PVC : 3330</div>
        <div style="font-size: 10px;">SAFETY HEALTH AND ENVIRONMENT</div>
        <div style="font-size: 10px;">PT. SULFINDO ADIUSAHA</div>
        <div class="signature">
          <img src="<?php echo !empty($settings['id_card_signature_url']) ? $base_url . '/' . htmlspecialchars($settings['id_card_signature_url']) : $base_url . '/assets/images/placeholder-avatar.svg'; ?>" alt="SHE Manager Signature" class="signature-img" />
          <div>(Chairul Khodri)</div>
          <div class="name">SHE Manager</div>
        </div>
      </div>
    </div>
    <!-- Right Section -->
    <div class="section right-content" style="background-color: <?php echo $card_color; ?>; background-image: url(<?php echo $right_bg; ?>) !important;">
      <div class="sidebar right">SECURITY CHECKED</div>
      <div class="header-container">
        <img src="<?php echo !empty($settings['id_card_logo_url']) ? $base_url . '/' . htmlspecialchars($settings['id_card_logo_url']) : $base_url . '/assets/images/placeholder-avatar.svg'; ?>" alt="Logo" class="logo" />
        <div class="company-name">PT SULFINDO ADIUSAHA</div>
      </div>
      <div class="bold" style="font-size: 20px; margin-top: 6px; padding: 0 10px;"><?php echo htmlspecialchars($contractor['plant_location']); ?></div>
      <div class="photo-qr-container">
         <img src="<?php echo !empty($contractor['photo']) ? $base_url . '/uploads/photos/' . htmlspecialchars($contractor['photo']) : $base_url . '/assets/images/placeholder-avatar.svg'; ?>" alt="Photo" class="photo" />
         <img src="<?php echo !empty($contractor['qr_code']) ? $base_url . '/uploads/qrcodes/' . htmlspecialchars($contractor['qr_code']) : $base_url . '/qr_generator.php?data=' . urlencode($contractor['id_card']); ?>" alt="QR Code" class="qr-code" />
      </div>
      <div class="violation-box">
        <div>SP1 <span class="sp-box sp1"></span></div>
        <div>PELANGGARAN</div>
        <div>SP2 <span class="sp-box sp2"></span></div>
      </div>
      <div class="info-box">
        <div><?php echo strtoupper(htmlspecialchars($contractor['name'])); ?></div>
        <div><?php echo htmlspecialchars($contractor['id_card']); ?></div>
        <div><?php echo strtoupper(htmlspecialchars($contractor['company_name'])); ?></div>
        <div style="font-size: 11px; font-weight: bold;">
            <span style="margin-right: 10px;">REG: <?php echo date('d-M-y', strtotime($contractor['registration_date'] ?? '')); ?></span>
            <span>EXP: <?php echo date('d-M-y', strtotime($contractor['expiry_date'] ?? '')); ?></span>
        </div>
        <div>0</div>
      </div>
      <div class="bottom-right-letter">A</div>
    </div>
  </div>
<?php endforeach; ?>
</div>
</body>
</html>