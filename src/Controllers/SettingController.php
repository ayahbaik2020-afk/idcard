<?php

namespace App\Controllers;

use PDO;
use Exception;

class SettingController
{
    // Absolute path to the single canonical uploads folder (public/uploads/)
    private const UPLOAD_ROOT = __DIR__ . '/../../public/uploads/';

    private const ALLOWED_IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp'];
    private const ALLOWED_IMAGE_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const ALLOWED_VIDEO_EXT = ['mp4', 'webm'];
    private const ALLOWED_VIDEO_MIME = ['video/mp4', 'video/webm'];
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024;   // 5 MB
    private const MAX_VIDEO_SIZE = 200 * 1024 * 1024; // 200 MB

    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index()
    {
        // Redirect to system settings
        header('Location: index.php?page=settings&action=system');
        exit();
    }

    public function system()
    {
        $settings_stmt = $this->pdo->query("SELECT `key`, `value` FROM system_settings");
        $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $data = compact('settings');

        $content = '';
        ob_start();
        extract($data);
        include __DIR__ . '/../../templates/settings/system.php';
        $content = ob_get_clean();
        include __DIR__ . '/../../templates/layout.php';
    }

    public function updateSystem()
    {
        try {
            $this->doUpdateSystem();
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }
        header('Location: index.php?page=settings&action=system');
        exit();
    }

    private function doUpdateSystem()
    {
        // Handle LTI reset
        if (isset($_POST['reset_lti'])) {
            $this->updateSetting('base_plant_working_hours', $_POST['base_plant_working_hours'] ?? '0');
            $this->updateSetting('lti_last_reset_date', date('Y-m-d H:i:s'));
        } else {
            // Fields from original system form
            $this->updateSetting('app_logo', $_POST['app_logo'] ?? '');
            $this->updateSetting('running_text', $_POST['running_text'] ?? '');
            $this->updateSetting('plant_information', $_POST['plant_information'] ?? '');
            $this->updateSetting('base_plant_working_hours', $_POST['base_plant_working_hours'] ?? '0');
            $this->updateSetting('on_duty_name', $_POST['on_duty_name'] ?? '');
            $this->updateSetting('on_duty_position', $_POST['on_duty_position'] ?? '');
            $this->updateSetting('on_duty_plant', $_POST['on_duty_plant'] ?? '');
        }



        // Handle video upload
        if (isset($_FILES['safety_video']) && $_FILES['safety_video']['error'] == 0) {
            $video_path = $this->handleVideoUpload('safety_video', 'safety_video_url');
            $this->updateSetting('safety_video_url', $video_path);
        }

        // Handle On Duty Officer Image Upload
        if (isset($_FILES['on_duty_photo']) && $_FILES['on_duty_photo']['error'] == 0) {
            $officer_image_path = $this->handleSettingFileUpload('on_duty_photo', 'on_duty_photo_url');
            $this->updateSetting('on_duty_photo_url', $officer_image_path);
        }

        // id_card_title / id_card_header_color have no field on either the
        // System or ID Card settings form yet, so only touch them if a
        // future form actually submits them - otherwise every save here
        // was silently resetting them back to the hardcoded default below,
        // permanently overwriting whatever value was set some other way.
        $this->updateSettingIfPresentInPost('id_card_title', 'KARTU KONTRAKTOR');
        $this->updateSettingIfPresentInPost('id_card_header_color', '#0d6efd');

        // Shared with the ID Card settings page (updateIdCard()) - manager
        // name, plant colors, logo, and signature can be edited from
        // either screen.
        $this->applyIdCardSharedSettings();

        // Log activity
        $this->logActivity('update', 'system_settings', null, "Updated system and ID card settings");
    }

    public function user()
    {
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll();

        $data = compact('users');

        $content = '';
        ob_start();
        extract($data);
        include __DIR__ . '/../../templates/settings/users.php';
        $content = ob_get_clean();
        include __DIR__ . '/../../templates/layout.php';
    }

    public function createUser()
    {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        // Validation
        $errors = [];
        if (empty($name)) $errors[] = 'Nama diperlukan.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email valid diperlukan.';
        if (empty($password) || strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';
        if (empty($role)) $errors[] = 'Role diperlukan.';

        // Check if email already exists
        $stmt_check = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->execute([$email]);
        if ($stmt_check->fetch()) $errors[] = 'Email sudah terdaftar.';

        if (!empty($errors)) {
            // Store errors in session and redirect back
            $_SESSION['errors'] = $errors;
            header('Location: index.php?page=settings&action=user');
            exit();
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hashed_password, $role]);

        // Log activity
        $this->logActivity('create', 'users', $this->pdo->lastInsertId(), "Created user: $name");

        header('Location: index.php?page=settings&action=user');
        exit();
    }

    public function updateUser($id)
    {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $role = $_POST['role'] ?? '';

        $stmt = $this->pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
        $stmt->execute([$name, $email, $role, $id]);

        // Log activity
        $this->logActivity('update', 'users', $id, "Updated user: $name");

        header('Location: index.php?page=settings&action=user');
        exit();
    }

    public function deleteUser($id)
    {
        $stmt = $this->pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        // Log activity
        $this->logActivity('delete', 'users', $id, "Deleted user: $name");

        header('Location: index.php?page=settings&action=user');
        exit();
    }

    public function idCard()
    {
        $settings_stmt = $this->pdo->query("SELECT `key`, `value` FROM system_settings WHERE `key` LIKE 'id_card_%' OR `key` LIKE 'plant_color_%'");
        $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $data = compact('settings');

        $content = '';
        ob_start();
        extract($data);
        include __DIR__ . '/../../templates/settings/id_card.php';
        $content = ob_get_clean();
        include __DIR__ . '/../../templates/layout.php';
    }

    public function updateIdCard()
    {
        try {
            $this->doUpdateIdCard();
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }
        header('Location: index.php?page=settings&action=idCard');
        exit();
    }

    private function doUpdateIdCard()
    {
        $this->applyIdCardSharedSettings();

        // Log activity
        $this->logActivity('update', 'system_settings', null, "Updated ID Card settings");
    }

    /**
     * Fields shared by both the System settings form and the dedicated
     * ID Card settings form. Extracted so the two forms can't drift out
     * of sync with each other, as they had before (see doUpdateSystem's
     * former "=== Merged from updateIdCard ===" block, which duplicated
     * this exact logic).
     */
    private function applyIdCardSharedSettings()
    {
        $this->updateSetting('id_card_manager_name', $_POST['id_card_manager_name'] ?? '');

        // Handle plant color settings
        $this->updateSetting('plant_color_ca', $_POST['plant_color_ca'] ?? '#008000');
        $this->updateSetting('plant_color_edc_vcm', $_POST['plant_color_edc_vcm'] ?? '#0000FF');
        $this->updateSetting('plant_color_pvc', $_POST['plant_color_pvc'] ?? '#FFFF00');
        $this->updateSetting('plant_color_mei', $_POST['plant_color_mei'] ?? '#FFA500');
        $this->updateSetting('plant_color_hpi', $_POST['plant_color_hpi'] ?? '#800080');

        // Handle logo upload
        if (isset($_FILES['id_card_logo']) && $_FILES['id_card_logo']['error'] == 0) {
            $logo_path = $this->handleSettingFileUpload('id_card_logo', 'id_card_logo_url');
            $this->updateSetting('id_card_logo_url', $logo_path);
        }

        // Handle signature upload
        if (isset($_FILES['id_card_signature']) && $_FILES['id_card_signature']['error'] == 0) {
            $signature_path = $this->handleSettingFileUpload('id_card_signature', 'id_card_signature_url');
            $this->updateSetting('id_card_signature_url', $signature_path);
        }
    }

    private function handleSettingFileUpload($file_key, $setting_key)
    {
        $file_ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, self::ALLOWED_IMAGE_EXT, true)) {
            throw new Exception('Format file tidak didukung. Gunakan JPG, JPEG, PNG, atau WEBP.');
        }
        if ($_FILES[$file_key]['size'] > self::MAX_IMAGE_SIZE) {
            throw new Exception('Ukuran file terlalu besar. Maksimal 5MB.');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES[$file_key]['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mimeType, self::ALLOWED_IMAGE_MIME, true)) {
            throw new Exception('File yang diupload bukan gambar yang valid.');
        }

        // Get old file path to delete it
        $stmt_old = $this->pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = ?");
        $stmt_old->execute([$setting_key]);
        $old_file = $stmt_old->fetchColumn();

        // If old is local file, delete it
        if ($old_file && strpos($old_file, 'uploads/settings/') !== false) {
            $oldPath = self::UPLOAD_ROOT . 'settings/' . basename($old_file);
            if (is_file($oldPath)) {
                unlink($oldPath);
            }
        }

        // Upload new file
        $upload_dir = self::UPLOAD_ROOT . 'settings/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = preg_replace('/[^A-Za-z0-9_-]/', '', $setting_key) . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $file_ext;
        $new_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $new_path)) {
            // Return web-relative path (relative to public/index.php)
            return 'uploads/settings/' . $file_name;
        }
        throw new Exception('Gagal menyimpan file yang diupload.');
    }

    public function companies()
    {
        $stmt = $this->pdo->query("SELECT * FROM contractor_companies ORDER BY name");
        $companies = $stmt->fetchAll();

        $data = compact('companies');

        $content = '';
        ob_start();
        extract($data);
        include __DIR__ . '/../../templates/settings/companies.php';
        $content = ob_get_clean();
        include __DIR__ . '/../../templates/layout.php';
    }

    public function createCompany()
    {
        $name = $_POST['name'] ?? '';

        $stmt = $this->pdo->prepare("INSERT INTO contractor_companies (name) VALUES (?)");
        $stmt->execute([$name]);

        // Log activity
        $this->logActivity('create', 'contractor_companies', $this->pdo->lastInsertId(), "Created company: $name");

        header('Location: index.php?page=settings&action=companies');
        exit();
    }

    public function updateCompany($id)
    {
        $name = $_POST['name'] ?? '';

        $stmt = $this->pdo->prepare("UPDATE contractor_companies SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);

        // Log activity
        $this->logActivity('update', 'contractor_companies', $id, "Updated company: $name");

        header('Location: index.php?page=settings&action=companies');
        exit();
    }

    public function deleteCompany($id)
    {
        $stmt = $this->pdo->prepare("SELECT name FROM contractor_companies WHERE id = ?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        try {
            $stmt = $this->pdo->prepare("DELETE FROM contractor_companies WHERE id = ?");
            $stmt->execute([$id]);

            // Log activity
            $this->logActivity('delete', 'contractor_companies', $id, "Deleted company: $name");
            $_SESSION['success_message'] = "Perusahaan \"$name\" berhasil dihapus.";
        } catch (\PDOException $e) {
            // Foreign key RESTRICT (contractors.company_id) - company still
            // has contractors registered under it.
            if ($e->getCode() === '23000') {
                $_SESSION['error_message'] = "Perusahaan \"$name\" tidak bisa dihapus karena masih memiliki data kontraktor terdaftar. Pindahkan atau hapus dulu kontraktor di perusahaan ini.";
            } else {
                throw $e;
            }
        }

        header('Location: index.php?page=settings&action=companies');
        exit();
    }

    public function violations()
    {
        $stmt = $this->pdo->query("SELECT * FROM violations ORDER BY name");
        $violations = $stmt->fetchAll();

        $data = compact('violations');

        $content = '';
        ob_start();
        extract($data);
        include __DIR__ . '/../../templates/settings/violations.php';
        $content = ob_get_clean();
        include __DIR__ . '/../../templates/layout.php';
    }

    public function createViolation()
    {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';

        $stmt = $this->pdo->prepare("INSERT INTO violations (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);

        // Log activity
        $this->logActivity('create', 'violations', $this->pdo->lastInsertId(), "Created violation: $name");

        header('Location: index.php?page=settings&action=violations');
        exit();
    }

    public function updateViolation($id)
    {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';

        $stmt = $this->pdo->prepare("UPDATE violations SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $id]);

        // Log activity
        $this->logActivity('update', 'violations', $id, "Updated violation: $name");

        header('Location: index.php?page=settings&action=violations');
        exit();
    }

    public function deleteViolation($id)
    {
        $stmt = $this->pdo->prepare("SELECT name FROM violations WHERE id = ?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        try {
            $stmt = $this->pdo->prepare("DELETE FROM violations WHERE id = ?");
            $stmt->execute([$id]);

            // Log activity
            $this->logActivity('delete', 'violations', $id, "Deleted violation: $name");
            $_SESSION['success_message'] = "Jenis pelanggaran \"$name\" berhasil dihapus.";
        } catch (\PDOException $e) {
            // Foreign key RESTRICT (sanctions.violation_id) - this violation
            // type is still referenced by existing sanction/ban history.
            if ($e->getCode() === '23000') {
                $_SESSION['error_message'] = "Jenis pelanggaran \"$name\" tidak bisa dihapus karena masih dipakai di riwayat sanksi kontraktor. Menghapusnya akan menghilangkan jejak riwayat sanksi tersebut.";
            } else {
                throw $e;
            }
        }

        header('Location: index.php?page=settings&action=violations');
        exit();
    }

    private function updateSetting($key, $value)
    {
        $stmt = $this->pdo->prepare("INSERT INTO system_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
        $stmt->execute([$key, $value, $value]);
    }

    /**
     * Like updateSetting(), but leaves the existing stored value alone
     * when the form didn't actually submit this field - only seeds the
     * default the first time the key has never been set at all. Use this
     * for settings that aren't (yet) exposed on any form, so re-saving an
     * unrelated form can't keep clobbering them back to the default.
     */
    private function updateSettingIfPresentInPost($key, $default)
    {
        if (array_key_exists($key, $_POST)) {
            $this->updateSetting($key, $_POST[$key]);
            return;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE `key` = ?");
        $stmt->execute([$key]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->updateSetting($key, $default);
        }
    }

    private function logActivity($action, $table, $record_id, $description)
    {
        if (isset($_SESSION['user_id'])) {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $action, $table, $record_id, $description]);
        }
    }

    private function handleVideoUpload($file_key, $setting_key)
    {
        $file_ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, self::ALLOWED_VIDEO_EXT, true)) {
            throw new Exception('Format video tidak didukung. Gunakan MP4 atau WEBM.');
        }
        if ($_FILES[$file_key]['size'] > self::MAX_VIDEO_SIZE) {
            throw new Exception('Ukuran video terlalu besar. Maksimal 200MB.');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES[$file_key]['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mimeType, self::ALLOWED_VIDEO_MIME, true)) {
            throw new Exception('File yang diupload bukan video yang valid.');
        }

        // Get old file path to delete it
        $stmt_old = $this->pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = ?");
        $stmt_old->execute([$setting_key]);
        $old_file = $stmt_old->fetchColumn();

        // If old is local file, delete it
        if ($old_file && strpos($old_file, 'uploads/video/') !== false) {
            $oldPath = self::UPLOAD_ROOT . 'video/' . basename($old_file);
            if (is_file($oldPath)) {
                unlink($oldPath);
            }
        }

        // Upload new file
        $upload_dir = self::UPLOAD_ROOT . 'video/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = 'safety_video_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $file_ext;
        $new_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $new_path)) {
            // Return web-relative path (relative to public/index.php)
            return 'uploads/video/' . $file_name;
        }
        throw new Exception('Gagal menyimpan video yang diupload.');
    }
}