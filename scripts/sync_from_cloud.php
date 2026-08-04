<?php

/**
 * Bridge between the Supabase-backed mobile apps (registration + P2K3) and
 * this local MySQL database (idcard_system). See MOBILE_APP_PLAN.md
 * section 6 for the design.
 *
 * Run manually:   php scripts/sync_from_cloud.php
 *                 php scripts/sync_from_cloud.php --push   (kirim saja)
 *                 php scripts/sync_from_cloud.php --pull   (tarik saja)
 *
 * Cron/Task Scheduler (task "idcard_mobile_sync") menjalankannya TANPA
 * argumen = mode full, supaya registrasi dari HP otomatis masuk ke sistem
 * lokal. Tombol "Kirim"/"Tarik" di dashboard memakai --push / --pull
 * (lihat public/sync_now.php).
 *
 * Direction 1 (cloud -> local): pull pending staging_contractors /
 * staging_sanctions from Supabase, insert into MySQL, then ack them so
 * they're not pulled again.
 *
 * Direction 2 (local -> cloud): push a fresh snapshot of active bans, the
 * full contractor directory, and sanction history back up to Supabase so
 * both mobile apps stay current even without a live link to this server.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Repositories\ContractorRepository;
use App\Repositories\CompanyRepository;
use App\Services\ContractorService;

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../src/' . str_replace(['App\\', '\\'], ['', '/'], $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

function sync_log(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $line . PHP_EOL;
    @file_put_contents(__DIR__ . '/sync.log', $line . PHP_EOL, FILE_APPEND);
}

/**
 * Minimal curl JSON client. Returns [httpCode, decodedBodyOrNull, error].
 *
 * Resilience notes:
 * - The host is resolved ONCE via gethostbyname() and pinned with
 *   CURLOPT_RESOLVE, so a flaky system resolver (seen on this machine when
 *   the sync is spawned from the web server's exec() - "Could not resolve
 *   host") can't abort the request at curl time.
 * - Failed/empty responses are retried a few times before giving up, so a
 *   single transient network/DNS hiccup doesn't fail the whole sync.
 */
function http_json(string $method, string $url, ?array $body, array $headers): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_DNS_CACHE_TIMEOUT => 600,
    ];

    $host = parse_url($url, PHP_URL_HOST);
    $port = parse_url($url, PHP_URL_PORT)
        ?: ((parse_url($url, PHP_URL_SCHEME) === 'https') ? 443 : 80);
    $resolved = $host ? gethostbyname($host) : $host;
    if ($host && is_string($resolved) && filter_var($resolved, FILTER_VALIDATE_IP)) {
        $opts[CURLOPT_RESOLVE] = ["$host:$port:$resolved"];
    }

    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    $lastErr = 'unknown error';
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        if ($raw !== false) {
            $err = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return [$code, json_decode($raw, true), null];
        }
        $lastErr = curl_error($ch) ?: $lastErr;
        if ($attempt < 3) {
            usleep(500000); // 0.5s between retries
        }
    }
    curl_close($ch);
    return [0, null, $lastErr];
}

/**
 * Downloads a remote (Supabase Storage) URL to a local temp file.
 * Returns the temp path, or null if the URL is empty or download failed.
 */
function download_to_temp(?string $url): ?string
{
    if (empty($url)) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $data = curl_exec($ch);
    $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);

    if (!$ok || $data === false || $data === '') {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'idcard_sync_');
    file_put_contents($tmp, $data);
    return $tmp;
}

// ---------------------------------------------------------------------

$syncConfig = require __DIR__ . '/../config/mobile_sync.php';
if (empty($syncConfig['vercel_base_url'])) {
    sync_log('SKIP: config/mobile_sync.php -> vercel_base_url belum diisi (mobile-app belum di-deploy ke Vercel). Tidak ada yang disinkronkan.');
    exit(0);
}

$dbConfig = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$contractorRepo = new ContractorRepository($pdo);
$companyRepo = new CompanyRepository($pdo);
$service = new ContractorService($contractorRepo, $companyRepo);

$baseUrl = rtrim($syncConfig['vercel_base_url'], '/');
$headers = [
    'Content-Type: application/json',
    'x-sync-key: ' . $syncConfig['sync_api_key'],
];

// Mode kontrol arah sinkronisasi:
//   --push : hanya kirim snapshot lokal -> cloud (dipakai tombol "Sync Now"
//            di dashboard - sesuai spesifikasi: Sync Now = kirim data ke
//            Vercel/Supabase).
//   --pull : hanya tarik registrasi/sanksi baru dari cloud -> lokal.
//   (tanpa argumen = full): keduanya. Mode ini yang dijalankan oleh
//   cron/Task Scheduler, supaya data registrasi dari HP otomatis masuk ke
//   sistem lokal tanpa menunggu admin klik Sync Now.
$mode = 'full';
if (in_array('--push', $argv ?? [], true)) {
    $mode = 'push';
} elseif (in_array('--pull', $argv ?? [], true)) {
    $mode = 'pull';
}
sync_log("Mode: $mode");

// ------------------------- 0. KONSISTENSI STATUS -------------------------
// Reaktivasi otomatis kontraktor yang statusnya masih 'Banned' tapi sudah
// tidak punya sanksi aktif (ban sementara kedaluwarsa / sudah dicabut),
// supaya daftar banned, kartu, dan snapshot cloud selalu konsisten.
$reactivated = $service->autoReactivateExpiredBanned();
if ($reactivated > 0) {
    sync_log("Auto-reaktivasi $reactivated kontraktor yang ban-nya sudah berakhir.");
}

// ------------------------- 1. PULL -------------------------
if ($mode === 'full' || $mode === 'pull') {
    sync_log('Menarik registrasi/sanksi baru dari cloud ke sistem lokal...');
    [$code, $pulled, $err] = http_json('POST', "$baseUrl/api/sync/pull", null, $headers);
    if ($code !== 200 || !is_array($pulled)) {
        sync_log('ERROR: gagal pull dari cloud (' . ($err ?: "HTTP $code") . '). Sync dibatalkan.');
        exit(1);
    }

    $pendingContractors = $pulled['contractors'] ?? [];
    $pendingSanctions = $pulled['sanctions'] ?? [];
    sync_log('Pulled: ' . count($pendingContractors) . ' registrasi, ' . count($pendingSanctions) . ' sanksi.');

    $ackContractors = [];
    $ackSanctions = [];

    foreach ($pendingContractors as $row) {
        $tmpPhoto = download_to_temp($row['face_photo_url'] ?? null);
        try {
            $result = $service->createFromMobileSync([
                'ktp_no' => $row['ktp_no'],
                'name' => $row['name'],
                'alamat' => $row['alamat'] ?? null,
                'company_name' => $row['company_name'],
                'plant_location' => $row['plant_location'],
                'mobile_sync_id' => $row['id'],
            ], $tmpPhoto);

            if ($result['status'] === 'created') {
                if (!empty($result['reactivated'])) {
                    sync_log("  + Re-aktivasi ID: {$row['name']} ({$row['ktp_no']}) -> id_card baru {$result['id_card']}");
                } else {
                    sync_log("  + Kontraktor baru: {$row['name']} ({$row['ktp_no']}) -> id_card {$result['id_card']}");
                }
                $ackContractors[] = ['id' => $row['id'], 'status' => 'synced'];
            } else {
                sync_log("  ! Dilewati ({$row['name']} / {$row['ktp_no']}): {$result['message']}");
                $ackContractors[] = ['id' => $row['id'], 'status' => 'rejected', 'message' => $result['message']];
            }
        } catch (Throwable $e) {
            sync_log("  x Gagal memproses {$row['name']} ({$row['ktp_no']}): " . $e->getMessage());
            $ackContractors[] = ['id' => $row['id'], 'status' => 'rejected', 'message' => $e->getMessage()];
        } finally {
            if ($tmpPhoto && is_file($tmpPhoto)) {
                unlink($tmpPhoto);
            }
        }
    }

    foreach ($pendingSanctions as $row) {
        try {
            $result = $service->applySanctionFromMobile([
                'ktp_no' => $row['ktp_no'],
                'sanction_type' => $row['sanction_type'],
                'is_permanent' => $row['is_permanent'],
                'end_date' => $row['end_date'],
                'reason' => $row['reason'],
                'mobile_sync_id' => $row['id'],
            ]);

            if ($result === null) {
                // Contractor not synced locally yet - leave pending, retry next run.
                sync_log("  ~ Sanksi untuk KTP {$row['ktp_no']} ditunda: kontraktor belum ada di lokal.");
            } else {
                sync_log("  + Sanksi diterapkan: {$row['sanction_type']} untuk KTP {$row['ktp_no']}");
                $ackSanctions[] = ['id' => $row['id'], 'status' => 'synced'];
            }
        } catch (Throwable $e) {
            sync_log("  x Gagal menerapkan sanksi KTP {$row['ktp_no']}: " . $e->getMessage());
            $ackSanctions[] = ['id' => $row['id'], 'status' => 'rejected', 'message' => $e->getMessage()];
        }
    }

    if ($ackContractors || $ackSanctions) {
        [$ackCode] = http_json('POST', "$baseUrl/api/sync/ack", [
            'contractors' => $ackContractors,
            'sanctions' => $ackSanctions,
        ], $headers);
        sync_log($ackCode === 200 ? 'Ack terkirim.' : "WARNING: ack gagal (HTTP $ackCode).");
    }
}

// ------------------------- 2. PUSH -------------------------
if ($mode === 'full' || $mode === 'push') {
    sync_log('Mengirim snapshot data terbaru ke cloud (Supabase)...');
    $snapshots = $service->getSyncSnapshots();

    [$pushCode, $pushResult] = http_json('POST', "$baseUrl/api/sync/push", [
        'active_bans' => $snapshots['active_bans'],
        'contractors' => $snapshots['contractors'],
        'sanction_history' => $snapshots['sanction_history'],
        'companies' => $snapshots['companies'],
        'local_base_url' => $syncConfig['local_base_url'],
    ], $headers);

    if ($pushCode === 200) {
        sync_log('Push OK: ' . count($snapshots['active_bans']) . ' active bans, '
            . count($snapshots['contractors']) . ' kontraktor, '
            . count($snapshots['sanction_history']) . ' histori sanksi, '
            . count($snapshots['companies']) . ' PT.');
    } else {
        sync_log('ERROR: push gagal (HTTP ' . $pushCode . '): ' . json_encode($pushResult));
        exit(1);
    }
}

sync_log('Sync selesai.');
