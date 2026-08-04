<?php

namespace App\Services;

use App\Repositories\ContractorRepository;
use App\Repositories\CompanyRepository;
use App\Support\IdCardNumberFormatter;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Exception;

class ContractorService
{
    // Absolute path to the single canonical uploads folder (public/uploads/)
    private const UPLOAD_ROOT = __DIR__ . '/../../public/uploads/';

    private const ALLOWED_PHOTO_EXT = ['jpg', 'jpeg', 'png', 'webp'];
    private const ALLOWED_PHOTO_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_PHOTO_SIZE = 5 * 1024 * 1024; // 5 MB

    protected $contractorRepo;
    protected $companyRepo;

    public function __construct(ContractorRepository $contractorRepo, CompanyRepository $companyRepo)
    {
        $this->contractorRepo = $contractorRepo;
        $this->companyRepo = $companyRepo;
    }

    public function getList($filters, $page = 1, $perPage = 50)
    {
        return $this->contractorRepo->getAllContractors($filters, $page, $perPage);
    }

    /**
     * Unpaginated variant for CSV/Excel export, which must include every
     * row matching the filters, not just the current page.
     */
    public function getListAll($filters)
    {
        return $this->contractorRepo->getAllContractors($filters, 1, null)['data'];
    }

    public function getInPlant($filters, $page = 1, $perPage = 50)
    {
        $this->autoBanOverdueContractors();
        return $this->contractorRepo->getInPlantContractors($filters, $page, $perPage);
    }

    private function autoBanOverdueContractors()
    {
        $ten_hours_ago = date('Y-m-d H:i:s', strtotime('-10 hours'));
        $overdue_contractors = $this->contractorRepo->findOverdueContractors($ten_hours_ago);

        if (count($overdue_contractors) > 0) {
            $this->contractorRepo->beginTransaction();
            try {
                foreach ($overdue_contractors as $c) {
                    $this->contractorRepo->insertSanction([
                        'contractor_id' => $c['contractor_id'],
                        'sanction_type' => 'BANNED',
                        'start_date' => date('Y-m-d'),
                        'is_permanent' => 1,
                        'reason' => 'Tidak disiplin / tidak melakukan check-out'
                    ]);
                    $this->contractorRepo->updateStatus($c['contractor_id'], 'Banned');
                    $this->contractorRepo->logActivity('update', 'contractors', $c['contractor_id'], 'Automatically banned for not checking out');
                }
                $this->contractorRepo->commit();
            } catch (Exception $e) {
                $this->contractorRepo->rollBack();
            }
        }
    }

    /**
     * Reactivates contractors stuck at status 'Banned' with no currently
     * active sanction (temporary ban expired or all bans revoked).
     * Runs automatically during sync / banned-list views so the status
     * field never drifts out of line with the `active_bans` view.
     */
    public function autoReactivateExpiredBanned(): int
    {
        $count = $this->contractorRepo->autoReactivateExpiredBanned();
        if ($count > 0) {
            $this->contractorRepo->logActivity('update', 'contractors', null, "Auto-reactivated {$count} contractor(s) whose ban has expired");
        }
        return $count;
    }

    public function resolveCompanyId($companyId, $newCompanyName)
    {
        if ($companyId === 'new_company' && !empty($newCompanyName)) {
            $existingId = $this->companyRepo->findByName($newCompanyName);
            if ($existingId) {
                return $existingId;
            }
            return $this->companyRepo->insert($newCompanyName);
        }
        return $companyId;
    }

    public function createContractor($data, $files)
    {
        if (!empty($data['ktp_no'])) {
            if ($this->contractorRepo->findByKtpNo($data['ktp_no'])) {
                throw new Exception('Nomer KTP sudah dipakai, pastikan nomer KTP yg anda input asli dan benar...!!');
            }
        }

        $data['company_id'] = $this->resolveCompanyId($data['company_id'], $data['new_company_name'] ?? '');

        // Generate ID Card based on year
        $year_prefix = date('y');
        $max_id = $this->contractorRepo->getMaxIdByYearPrefix($year_prefix);
        $data['id_card'] = IdCardNumberFormatter::format($year_prefix, $max_id);

        // Handle Photo Upload
        $data['photo'] = $this->handlePhotoUpload($files['photo'] ?? null, $data['ktp_no']);

        // Handle QR Code Gen
        $data['qr_code'] = $this->generateQrCode($data['id_card']);

        $id = $this->contractorRepo->insertContractor($data);
        $this->contractorRepo->logActivity('create', 'contractors', $id, "Created contractor: {$data['name']}");
        return $id;
    }

    public function updateContractor($id, $data, $files)
    {
        if (!empty($data['ktp_no'])) {
            if ($this->contractorRepo->findByKtpNo($data['ktp_no'], $id)) {
                throw new Exception('Nomer KTP sudah dipakai, pastikan nomer KTP yg anda input asli dan benar...!!');
            }
        }

        $data['company_id'] = $this->resolveCompanyId($data['company_id'], $data['new_company_name'] ?? '');

        // Renewal detection: if this contractor was expired and the edit
        // extends expiry_date to today-or-later, treat it as a renewal -
        // issue a brand new ID Card number (+ matching QR code), since the
        // old physical card is being replaced. A plain edit that doesn't
        // touch an already-expired record keeps its existing ID Card.
        $oldContractor = $this->contractorRepo->findById($id);
        $wasExpired = $oldContractor && !empty($oldContractor['expiry_date']) && $oldContractor['expiry_date'] < date('Y-m-d');
        $isRenewal = $wasExpired && !empty($data['expiry_date']) && $data['expiry_date'] >= date('Y-m-d');

        if ($isRenewal) {
            $year_prefix = date('y');
            $max_id = $this->contractorRepo->getMaxIdByYearPrefix($year_prefix);
            $data['id_card'] = IdCardNumberFormatter::format($year_prefix, $max_id);
        }

        // Update basic info
        $this->contractorRepo->updateContractor($id, $data);

        if ($isRenewal) {
            if (!empty($oldContractor['qr_code'])) {
                $oldQrPath = self::UPLOAD_ROOT . 'qrcodes/' . $oldContractor['qr_code'];
                if (is_file($oldQrPath)) {
                    unlink($oldQrPath);
                }
            }
            $newQrCode = $this->generateQrCode($data['id_card']);
            if ($newQrCode) {
                $this->contractorRepo->updateQrCode($id, $newQrCode);
            }
            $this->contractorRepo->logActivity('update', 'contractors', $id, "Registrasi diperpanjang: ID Card {$oldContractor['id_card']} -> {$data['id_card']}");
        }

        // Handle photo upload
        if (isset($files['photo']) && $files['photo']['error'] == 0) {
            $oldPhotoPath = self::UPLOAD_ROOT . 'photos/' . ($oldContractor['photo'] ?? '');
            if (!empty($oldContractor['photo']) && is_file($oldPhotoPath)) {
                unlink($oldPhotoPath);
            }
            $newPhoto = $this->handlePhotoUpload($files['photo'], $data['ktp_no']);
            $this->contractorRepo->updatePhoto($id, $newPhoto);
        }

        $contractor = $this->contractorRepo->findById($id);
        if ($contractor && empty($contractor['qr_code'])) {
            $qrCode = $this->generateQrCode($contractor['id_card']);
            if ($qrCode) {
                $this->contractorRepo->updateQrCode($id, $qrCode);
            }
        }

        // Apply Sanction if provided
        if (!empty($data['sanction_type'])) {
            $this->applySanctionToContractor($id, $data);
        }

        $this->contractorRepo->logActivity('update', 'contractors', $id, "Updated contractor: {$data['name']}");

        return $isRenewal ? ['renewed' => true, 'id_card' => $data['id_card']] : ['renewed' => false];
    }

    private function applySanctionToContractor($id, $data)
    {
        $sanctionType = $data['sanction_type'];
        $startDate = date('Y-m-d');
        $endDate = null;
        $isPermanent = 0;
        $status = 'Active';

        if ($sanctionType == 'BANNED') {
            $bannedDays = (int)($data['banned_days'] ?? 0);
            if ($bannedDays > 0) {
                $endDate = date('Y-m-d', strtotime("+$bannedDays days"));
            } else {
                $isPermanent = 1;
            }
            $status = 'Banned';
        } elseif (in_array($sanctionType, ['SP1', 'SP2'])) {
            $status = 'Banned';
            $endDate = date('Y-m-d', strtotime("+30 days"));
        }

        $this->contractorRepo->insertSanction([
            'contractor_id' => $id,
            'violation_id' => $data['violation_id'] ?? null,
            'sanction_type' => $sanctionType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_permanent' => $isPermanent,
            'reason' => $data['reason'] ?? ''
        ]);
        $this->contractorRepo->updateStatus($id, $status);
    }

    public function deleteContractor($id)
    {
        $contractor = $this->contractorRepo->findById($id);
        if ($contractor) {
            $photoPath = self::UPLOAD_ROOT . 'photos/' . ($contractor['photo'] ?? '');
            if (!empty($contractor['photo']) && is_file($photoPath)) {
                unlink($photoPath);
            }
            $qrPath = self::UPLOAD_ROOT . 'qrcodes/' . ($contractor['qr_code'] ?? '');
            if (!empty($contractor['qr_code']) && is_file($qrPath)) {
                unlink($qrPath);
            }
            $this->contractorRepo->delete($id);
            $this->contractorRepo->logActivity('delete', 'contractors', $id, "Deleted contractor: {$contractor['name']}");
        }
    }

    /**
     * Validates and stores an uploaded photo. Filename is fully generated
     * server-side (never taken from user input) and the extension/MIME type
     * is whitelisted to prevent arbitrary file upload (e.g. .php webshells).
     */
    private function handlePhotoUpload($file, $ktpNo)
    {
        if (!isset($file) || $file['error'] != 0) {
            return null;
        }

        if ($file['size'] > self::MAX_PHOTO_SIZE) {
            throw new Exception('Ukuran foto terlalu besar. Maksimal 5MB.');
        }

        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, self::ALLOWED_PHOTO_EXT, true)) {
            throw new Exception('Format foto tidak didukung. Gunakan JPG, JPEG, PNG, atau WEBP.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mimeType, self::ALLOWED_PHOTO_MIME, true)) {
            throw new Exception('File yang diupload bukan gambar yang valid.');
        }

        $uploadDir = self::UPLOAD_ROOT . 'photos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // ktp_no is user input: strip anything except alphanumerics before
        // using it in a filename to avoid path traversal / injection.
        $safeKtp = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $ktpNo);
        if ($safeKtp === '') {
            $safeKtp = 'contractor';
        }
        $fileName = $safeKtp . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
            throw new Exception('Gagal menyimpan foto yang diupload.');
        }

        return $fileName;
    }

    /**
     * Generates the QR code locally using endroid/qr-code instead of an
     * external third-party API, so contractor data is never sent off-server
     * and the feature keeps working without internet access.
     */
    private function generateQrCode($idCard)
    {
        $qrUploadDir = self::UPLOAD_ROOT . 'qrcodes/';
        if (!is_dir($qrUploadDir)) {
            mkdir($qrUploadDir, 0755, true);
        }

        $qrFilenameClean = 'qrcode_' . str_replace('.', '_', $idCard) . '.png';

        try {
            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($idCard)
                ->size(200)
                ->margin(8)
                ->build();
            $result->saveToFile($qrUploadDir . $qrFilenameClean);
            return $qrFilenameClean;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Creates a contractor coming from the mobile registration app
     * (staging_contractors row already pulled from Supabase). Unlike
     * createContractor(), the face photo is a local file already
     * downloaded to disk (not a $_FILES upload), so it's copied rather
     * than moved via move_uploaded_file().
     *
     * Returns ['status' => 'created', 'id' => ..., 'id_card' => ...,
     * 'reactivated' => bool] or ['status' => 'duplicate', 'message' => ...]
     * if the KTP number already exists locally as an ACTIVE contractor.
     * An existing contractor whose ID Card has already expired is treated
     * as a re-activation: a brand new ID Card + QR code is issued.
     */
    public function createFromMobileSync(array $data, ?string $localFacePhotoPath): array
    {
        if (!empty($data['ktp_no'])) {
            $existingId = $this->contractorRepo->findByKtpNo($data['ktp_no']);
            if ($existingId) {
                $existing = $this->contractorRepo->findById($existingId);
                $isExpired = $existing && !empty($existing['expiry_date']) && $existing['expiry_date'] < date('Y-m-d');
                if (!$isExpired) {
                    return ['status' => 'duplicate', 'message' => 'KTP sudah terdaftar di sistem lokal'];
                }
                return $this->reactivateFromMobile($existing, $data, $localFacePhotoPath);
            }
        }

        if (empty(trim($data['name'] ?? ''))) {
            return ['status' => 'invalid', 'message' => 'Nama kosong, dilewati (perlu diisi manual dari mobile app)'];
        }

        $data['company_id'] = $this->resolveCompanyId('new_company', $data['company_name'] ?? '');

        $year_prefix = date('y');
        $max_id = $this->contractorRepo->getMaxIdByYearPrefix($year_prefix);
        $data['id_card'] = IdCardNumberFormatter::format($year_prefix, $max_id);

        $data['photo'] = $localFacePhotoPath ? $this->storeLocalImageCopy($localFacePhotoPath, $data['ktp_no']) : null;
        $data['qr_code'] = $this->generateQrCode($data['id_card']);
        $data['registration_date'] = $data['registration_date'] ?? date('Y-m-d');
        // Default 1 bulan masa aktif untuk registrasi dari mobile app, supaya
        // setiap kartu baru selalu punya expiry_date (aplikasi offline/sync
        // tidak menangani NULL). Admin bisa ubah tanggalnya di dashboard.
        $data['expiry_date'] = $data['expiry_date']
            ?? date('Y-m-d', strtotime('+1 month', strtotime($data['registration_date'])));
        $data['status'] = $data['status'] ?? 'Active';
        // alamat is optional (OCR may not have read it); passed through as-is.

        $id = $this->contractorRepo->insertContractorFromMobile($data);
        $this->contractorRepo->logActivity('create', 'contractors', $id, "Created contractor from mobile sync: {$data['name']}");

        return ['status' => 'created', 'id' => $id, 'id_card' => $data['id_card']];
    }

    /**
     * Re-activates an existing (expired) contractor from the mobile app:
     * issues a brand new ID Card number + QR code, deletes the old QR file
     * (physical card replaced), updates profile/photo, and sets a default
     * 1-month expiry_date (never NULL), same as a fresh mobile registration.
     */
    private function reactivateFromMobile(array $existing, array $data, ?string $localFacePhotoPath): array
    {
        $data['company_id'] = $this->resolveCompanyId('new_company', $data['company_name'] ?? '');

        $year_prefix = date('y');
        $max_id = $this->contractorRepo->getMaxIdByYearPrefix($year_prefix);
        $newIdCard = IdCardNumberFormatter::format($year_prefix, $max_id);

        $photo = $localFacePhotoPath
            ? $this->storeLocalImageCopy($localFacePhotoPath, $data['ktp_no'])
            : ($existing['photo'] ?? null);
        $newQrCode = $this->generateQrCode($newIdCard);

        if (!empty($existing['qr_code'])) {
            $oldQrPath = self::UPLOAD_ROOT . 'qrcodes/' . $existing['qr_code'];
            if (is_file($oldQrPath)) {
                unlink($oldQrPath);
            }
        }

        $this->contractorRepo->renewFromMobile($existing['id'], [
            'name' => $data['name'],
            'ktp_no' => $data['ktp_no'],
            'alamat' => $data['alamat'] ?? null,
            'company_id' => $data['company_id'],
            'plant_location' => $data['plant_location'] ?? ($existing['plant_location'] ?? ''),
            'photo' => $photo,
            'id_card' => $newIdCard,
            'qr_code' => $newQrCode,
            // Default 1 bulan masa aktif (sama seperti registrasi baru dari
            // mobile) — kartu tidak lagi dibuat tanpa expiry_date.
            'expiry_date' => date('Y-m-d', strtotime('+1 month')),
            'mobile_sync_id' => $data['mobile_sync_id'],
        ]);
        $this->contractorRepo->logActivity('update', 'contractors', $existing['id'], "Re-aktivasi via mobile sync: ID Card {$existing['id_card']} -> {$newIdCard}");

        return ['status' => 'created', 'id' => $existing['id'], 'id_card' => $newIdCard, 'reactivated' => true];
    }

    /**
     * Applies a sanction coming from the P2K3 mobile app to an existing
     * contractor (matched by ktp_no). Returns null if no contractor with
     * that KTP number exists locally yet (e.g. sync ran before the
     * contractor themself was synced).
     */
    public function applySanctionFromMobile(array $data): ?array
    {
        $contractorId = $this->contractorRepo->findByKtpNo($data['ktp_no']);
        if (!$contractorId) {
            return null;
        }

        $status = ($data['sanction_type'] === 'BANNED' || in_array($data['sanction_type'], ['SP1', 'SP2'], true))
            ? 'Banned' : 'Active';

        $this->contractorRepo->insertSanctionFromMobile([
            'contractor_id' => $contractorId,
            'sanction_type' => $data['sanction_type'],
            'start_date' => date('Y-m-d'),
            'end_date' => $data['end_date'] ?? null,
            'is_permanent' => !empty($data['is_permanent']) ? 1 : 0,
            'reason' => $data['reason'] ?? '',
            'mobile_sync_id' => $data['mobile_sync_id'],
        ]);
        $this->contractorRepo->updateStatus($contractorId, $status);
        $this->contractorRepo->logActivity('update', 'contractors', $contractorId, "Sanction added via P2K3 mobile app: {$data['sanction_type']}");

        return ['status' => 'applied', 'contractor_id' => $contractorId];
    }

    /**
     * Snapshots used to push data back up to Supabase so both mobile apps
     * stay in sync with the local system's authoritative state.
     */
    public function getSyncSnapshots(): array
    {
        return [
            'active_bans' => $this->contractorRepo->getActiveBansSnapshot(),
            'contractors' => $this->contractorRepo->getContractorsSnapshot(),
            'sanction_history' => $this->contractorRepo->getSanctionHistorySnapshot(),
            'companies' => $this->contractorRepo->getCompanyNamesSnapshot(),
        ];
    }

    /**
     * Copies an already-downloaded local image file (e.g. pulled from
     * Supabase Storage by the sync script) into the canonical uploads
     * folder, after validating it's actually an image. Unlike
     * handlePhotoUpload(), this uses copy() instead of
     * move_uploaded_file() since the source isn't an HTTP upload.
     */
    private function storeLocalImageCopy(string $sourcePath, $ktpNo): ?string
    {
        if (!is_file($sourcePath)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $sourcePath);
        finfo_close($finfo);

        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extMap[$mimeType])) {
            return null;
        }

        $uploadDir = self::UPLOAD_ROOT . 'photos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $safeKtp = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $ktpNo);
        if ($safeKtp === '') {
            $safeKtp = 'contractor';
        }
        $fileName = $safeKtp . '_' . bin2hex(random_bytes(4)) . '.' . $extMap[$mimeType];

        return copy($sourcePath, $uploadDir . $fileName) ? $fileName : null;
    }

    public function importCsv($filePath)
    {
        $file = fopen($filePath, 'r');
        fgetcsv($file); // skip header
        $created = 0; $updated = 0; $skipped = 0;

        $this->contractorRepo->beginTransaction();
        try {
            while (($row = fgetcsv($file)) !== FALSE) {
                if (count($row) < 8) { $skipped++; continue; }
                $data = $this->mapCsvRowToData($row);
                if (empty($data['ktp_no']) || empty($data['name'])) { $skipped++; continue; }

                $companyId = $this->resolveCompanyId('new_company', $data['company_name']);
                
                $existingId = $this->contractorRepo->findByKtpNo($data['ktp_no']);
                if ($existingId) {
                    $updateData = [
                        'name' => $data['name'],
                        'ktp_no' => $data['ktp_no'],
                        'company_id' => $companyId,
                        'plant_location' => $data['plant_location'],
                        'registration_date' => $data['registration_date'],
                        'status' => $data['status'],
                        'photo' => $data['photo_filename']
                    ];
                    $this->contractorRepo->updateContractor($existingId, $updateData);
                    $this->contractorRepo->updatePhoto($existingId, $data['photo_filename']);
                    $updated++;
                } else {
                    $idCard = $data['id_card'];
                    if (empty($idCard)) {
                        $yearPrefix = date('y');
                        $maxId = $this->contractorRepo->getMaxIdByYearPrefix($yearPrefix);
                        $idCard = IdCardNumberFormatter::format($yearPrefix, $maxId);
                    }
                    $qrFilename = $this->generateQrCode($idCard);

                    $this->contractorRepo->insertContractor([
                        'id_card' => $idCard,
                        'ktp_no' => $data['ktp_no'],
                        'name' => $data['name'],
                        'company_id' => $companyId,
                        'plant_location' => $data['plant_location'],
                        'registration_date' => $data['registration_date'],
                        'status' => $data['status'],
                        'photo' => $data['photo_filename'],
                        'qr_code' => $qrFilename
                    ]);
                    $created++;
                }
            }
            $this->contractorRepo->commit();
        } catch (Exception $e) {
            $this->contractorRepo->rollBack();
            fclose($file);
            throw $e;
        }
        fclose($file);
        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    private function mapCsvRowToData($row)
    {
        return [
            'id_card' => trim($row[0]),
            'ktp_no' => trim($row[1]),
            'photo_filename' => trim($row[2]),
            'name' => trim($row[3]),
            'company_name' => trim($row[4]),
            'plant_location' => trim($row[5]),
            'registration_date' => trim($row[6]),
            'status' => trim($row[7])
        ];
    }
}
