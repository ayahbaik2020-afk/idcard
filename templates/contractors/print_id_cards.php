<?php
// Determine the base URL dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$script_name = $_SERVER['SCRIPT_NAME'];
// Assuming the script is in the root of the public folder
$base_url = $protocol . $host . rtrim(dirname($script_name), '/');

// Adjust base URL if the app is in a subdirectory
$project_subdirectory = '/idcard/public'; 
$base_url = $protocol . $host . $project_subdirectory;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Print ID Cards</title>
<style>
  @page {
    size: A4;
    margin: 10mm;
  }
  body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
  }
  .page {
    page-break-after: always;
    width: 210mm;
    height: 297mm;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
  }
  .id-card {
    box-sizing: border-box;
    width: 65mm;
    height: 90mm;
    border: 1px solid #000;
    margin-bottom: 10mm;
    padding: 8px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .header {
    text-align: center;
    font-weight: bold;
    font-size: 12px;
    margin-bottom: 4px;
  }
  .logo {
    width: 40px;
    height: 40px;
    margin: 0 auto 4px auto;
  }
  .photo {
    width: 50px;
    height: 60px;
    border: 1px solid #000;
    margin: 0 auto 6px auto;
    object-fit: cover;
  }
  .info {
    font-size: 9px;
    line-height: 1.1;
  }
  .info strong {
    display: inline-block;
    width: 40px;
  }
  .footer {
    font-size: 7px;
    text-align: center;
    margin-top: 6px;
  }
  .signature {
    margin-top: 8px;
    font-size: 8px;
    text-align: center;
  }
</style>
</head>
<body>
<div class="page">
<?php foreach ($contractors as $contractor): ?>
  <div class="id-card">
    <div class="header">
      <img src="<?php echo $base_url . '/uploads/settings/id_card_logo_url_1759978111.png'; ?>" alt="Logo" class="logo" />
      <div>PT SULFINDO ADIUSAHA</div>
      <div>Plant Site Merak</div>
      <div>CONTRACTOR ENTRY PERMIT PASS</div>
    </div>
    <img src="<?php echo !empty($contractor['photo']) ? $base_url . '/uploads/photos/' . htmlspecialchars($contractor['photo']) : $base_url . '/assets/images/placeholder-avatar.svg'; ?>" alt="Photo" class="photo" />
    <div class="info">
      <div><strong>Nama:</strong> <?php echo htmlspecialchars($contractor['name']); ?></div>
      <div><strong>ID Card:</strong> <?php echo htmlspecialchars($contractor['id_card']); ?></div>
      <div><strong>Perusahaan:</strong> <?php echo htmlspecialchars($contractor['company_name']); ?></div>
      <div><strong>SP1:</strong> <?php echo htmlspecialchars($contractor['sp1'] ?? ''); ?></div>
      <div><strong>SP2:</strong> <?php echo htmlspecialchars($contractor['sp2'] ?? ''); ?></div>
      <div><strong>Pelanggaran:</strong> <?php echo htmlspecialchars($contractor['violation'] ?? ''); ?></div>
      <div><strong>Tgl Berlaku:</strong> <?php echo htmlspecialchars($contractor['valid_until'] ?? ''); ?></div>
    </div>
    <div class="signature">
      <div>(Chairul Khodri)</div>
      <div>SHE Manager</div>
    </div>
    <div class="footer">
      <div>SAFETY HEALTH AND ENVIRONMENT</div>
      <div>EMERGENCY CALL:</div>
      <div>1. SHE Dept: Telp: 1900 / 1901</div>
      <div>2. Security Pos1: Telp. CA: 1110, VCM: 2220, PVC: 3330</div>
    </div>
  </div>
<?php endforeach; ?>
</div>
</body>
</html>