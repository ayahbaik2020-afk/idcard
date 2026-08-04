<?php

namespace App\Repositories;

use PDO;
use Exception;
use App\Support\Paginator;

class ContractorRepository
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function beginTransaction()
    {
        $this->pdo->beginTransaction();
    }

    public function commit()
    {
        $this->pdo->commit();
    }

    public function rollBack()
    {
        $this->pdo->rollBack();
    }

    /**
     * @param int|null $perPage Pass null to fetch every matching row with
     *                          no LIMIT (used for CSV/Excel export, where
     *                          the whole filtered set must be included).
     */
    public function getAllContractors(array $filters, int $page = 1, ?int $perPage = 50)
    {
        $query = "SELECT c.*, cc.name as company_name 
                  FROM contractors c 
                  JOIN contractor_companies cc ON c.company_id = cc.id 
                  WHERE 1=1";
        
        $params = [];
        $this->applyFilters($query, $params, $filters);

        // Count matching rows before ORDER BY/LIMIT are appended, so the
        // pagination UI knows the true total regardless of page size.
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM ($query) as sub");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $query .= " ORDER BY c.created_at DESC";

        $page = max(1, $page);
        if ($perPage !== null) {
            $perPage = max(1, $perPage);
            $offset = Paginator::offset($page, $perPage);
            // LIMIT/OFFSET can't be bound as regular PDO params on every
            // driver config, but these are ints we just computed above
            // (never raw user input), so direct interpolation is safe here.
            $query .= " LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $perPage !== null ? Paginator::totalPages($total, $perPage) : 1,
        ];
    }

    private function applyFilters(&$query, &$params, $filters)
    {
        if (!empty($filters['search'])) {
            $query .= " AND (c.name LIKE ? OR c.id_card LIKE ? OR c.ktp_no LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'Expired') {
                // Virtual status: not a real value in the `status` enum,
                // computed from expiry_date instead so admins can find
                // who needs renewal (see AttendanceController::scan()
                // for where this same expiry check blocks check-in).
                $query .= " AND c.expiry_date IS NOT NULL AND c.expiry_date < CURDATE()";
            } else {
                $query .= " AND c.status = ?";
                $params[] = $filters['status'];
            }
        }
        if (!empty($filters['plant'])) {
            $query .= " AND c.plant_location = ?";
            $params[] = $filters['plant'];
        }
        if (!empty($filters['company_id'])) {
            $query .= " AND c.company_id = ?";
            $params[] = $filters['company_id'];
        }
        $this->applyDateFilters($query, $params, $filters);
        $this->applySanctionFilters($query, $filters);
    }

    private function applyDateFilters(&$query, &$params, $filters)
    {
        if (!empty($filters['day'])) {
            $query .= " AND DAY(c.registration_date) = ?";
            $params[] = (int)$filters['day'];
        }
        if (!empty($filters['month'])) {
            $query .= " AND MONTH(c.registration_date) = ?";
            $params[] = (int)$filters['month'];
        }
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(c.registration_date) = ?";
            $params[] = (int)$filters['year'];
        }
    }

    private function applySanctionFilters(&$query, $filters)
    {
        if (isset($filters['sanksi']) && $filters['sanksi'] === 'with') {
            $query .= " AND EXISTS (SELECT 1 FROM sanctions s WHERE s.contractor_id = c.id)";
        } elseif (isset($filters['sanksi']) && $filters['sanksi'] === 'without') {
            $query .= " AND NOT EXISTS (SELECT 1 FROM sanctions s WHERE s.contractor_id = c.id)";
        }
    }

    public function getInPlantContractors(array $filters, int $page = 1, int $perPage = 50)
    {
        $query = "SELECT c.*, cc.name as company_name, a.check_in_time 
                  FROM contractors c 
                  JOIN contractor_companies cc ON c.company_id = cc.id 
                  JOIN (
                      SELECT contractor_id, MAX(check_in_time) as check_in_time 
                      FROM attendances 
                      WHERE check_out_time IS NULL AND DATE(check_in_time) = CURDATE() 
                      GROUP BY contractor_id
                  ) a ON c.id = a.contractor_id 
                  WHERE 1=1";
        
        $params = [];
        if (!empty($filters['search'])) {
            $query .= " AND (c.name LIKE ? OR c.id_card LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        if (!empty($filters['plant'])) {
            $query .= " AND c.plant_location = ?";
            $params[] = $filters['plant'];
        }
        if (!empty($filters['company_id'])) {
            $query .= " AND c.company_id = ?";
            $params[] = $filters['company_id'];
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM ($query) as sub");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $query .= " ORDER BY a.check_in_time DESC";

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = Paginator::offset($page, $perPage);
        $query .= " LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return array_merge(['data' => $stmt->fetchAll()], Paginator::meta($total, $page, $perPage));
    }

    public function findOverdueContractors(string $timeLimit)
    {
        $query = "SELECT a.contractor_id 
                  FROM attendances a 
                  JOIN contractors c ON a.contractor_id = c.id 
                  WHERE a.check_in_time < ? 
                  AND a.check_out_time IS NULL 
                  AND c.status != 'Banned'";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$timeLimit]);
        return $stmt->fetchAll();
    }

    public function logActivity($action, $table, $record_id, $description)
    {
        if (isset($_SESSION['user_id'])) {
            $stmt = $this->pdo->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$_SESSION['user_id'], $action, $table, $record_id, $description]);
        }
    }

    public function findById($id)
    {
        $stmt = $this->pdo->prepare("SELECT c.*, cc.name as company_name FROM contractors c JOIN contractor_companies cc ON c.company_id = cc.id WHERE c.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findByIds(array $ids)
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT c.*, cc.name as company_name FROM contractors c JOIN contractor_companies cc ON c.company_id = cc.id WHERE c.id IN ($placeholders)");
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }

    public function findByKtpNo($ktpNo, $excludeId = null)
    {
        $query = "SELECT id FROM contractors WHERE ktp_no = ?";
        $params = [$ktpNo];
        if ($excludeId !== null) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function getMaxIdByYearPrefix($yearPrefix)
    {
        $stmt = $this->pdo->prepare("SELECT MAX(CAST(SUBSTRING(id_card, 4) AS UNSIGNED)) as max_id FROM contractors WHERE SUBSTRING(id_card, 1, 2) = ?");
        $stmt->execute([$yearPrefix]);
        return $stmt->fetchColumn() ?: 0;
    }

    public function insertContractor($data)
    {
        $stmt = $this->pdo->prepare("INSERT INTO contractors (id_card, ktp_no, name, company_id, plant_location, registration_date, expiry_date, photo, qr_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['id_card'], $data['ktp_no'], $data['name'], $data['company_id'], 
            $data['plant_location'], $data['registration_date'], $data['expiry_date'] ?? null, 
            $data['photo'] ?? null, $data['qr_code'] ?? null, $data['status'] ?? 'Active'
        ]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Same as insertContractor() but also tags the row as having come from
     * the mobile registration app (source='mobile') and records which
     * staging_contractors row it was synced from, so re-running the sync
     * never creates duplicates.
     */
    public function insertContractorFromMobile($data)
    {
        $stmt = $this->pdo->prepare("INSERT INTO contractors (id_card, ktp_no, alamat, name, company_id, plant_location, registration_date, expiry_date, photo, qr_code, status, source, mobile_sync_id, synced_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'mobile', ?, NOW())");
        $stmt->execute([
            $data['id_card'], $data['ktp_no'], $data['alamat'] ?? null, $data['name'], $data['company_id'],
            $data['plant_location'], $data['registration_date'], $data['expiry_date'] ?? null,
            $data['photo'] ?? null, $data['qr_code'] ?? null, $data['status'] ?? 'Active',
            $data['mobile_sync_id']
        ]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Re-activates an existing contractor from the mobile app: issues a
     * brand new id_card + qr_code (the old physical card is replaced),
     * updates profile/photo, and records which staging_contractors row it
     * came from. expiry_date is deliberately reset to NULL so the card is
     * treated as active until the admin sets a new date - same behaviour
     * as a fresh mobile registration.
     */
    public function renewFromMobile($id, $data)
    {
        $stmt = $this->pdo->prepare("UPDATE contractors SET name = ?, ktp_no = ?, alamat = ?, company_id = ?, plant_location = ?, registration_date = ?, status = 'Active', expiry_date = NULL, id_card = ?, photo = ?, qr_code = ?, mobile_sync_id = ?, synced_at = NOW() WHERE id = ?");
        $stmt->execute([
            $data['name'], $data['ktp_no'], $data['alamat'], $data['company_id'],
            $data['plant_location'], date('Y-m-d'), $data['id_card'],
            $data['photo'], $data['qr_code'], $data['mobile_sync_id'], $id
        ]);
    }

    /**
     * Same as insertSanction() but tags the row as coming from the P2K3
     * mobile app.
     */
    public function insertSanctionFromMobile($data)
    {
        $stmt = $this->pdo->prepare("INSERT INTO sanctions (contractor_id, violation_id, sanction_type, start_date, end_date, is_permanent, reason, source, mobile_sync_id, synced_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'mobile', ?, NOW())");
        $stmt->execute([
            $data['contractor_id'], $data['violation_id'] ?? null, $data['sanction_type'],
            $data['start_date'], $data['end_date'] ?? null, $data['is_permanent'], $data['reason'],
            $data['mobile_sync_id']
        ]);
        return $this->pdo->lastInsertId();
    }

    public function findByMobileSyncId(string $mobileSyncId)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM contractors WHERE mobile_sync_id = ?");
        $stmt->execute([$mobileSyncId]);
        return $stmt->fetchColumn();
    }

    /**
     * Snapshot of every currently-active (non-revoked, non-expired) BANNED
     * sanction, pushed up to Supabase `synced_active_bans` so the mobile
     * apps can filter registrations without needing a live connection to
     * this local MySQL server.
     */
    public function getActiveBansSnapshot()
    {
        $stmt = $this->pdo->query(
            "SELECT c.ktp_no, c.name AS contractor_name, s.sanction_type, s.is_permanent, s.end_date, s.reason
             FROM active_bans s
             JOIN contractors c ON c.id = s.contractor_id"
        );
        return $stmt->fetchAll();
    }

    /**
     * Snapshot of every contractor, keyed by id_card (what's encoded in the
     * QR on the physical card), for the P2K3 mobile app's scan lookup.
     */
    public function getContractorsSnapshot()
    {
        $stmt = $this->pdo->query(
            "SELECT c.id_card, c.ktp_no, c.name, cc.name AS company_name, c.plant_location, c.status, c.photo, c.expiry_date
             FROM contractors c
             JOIN contractor_companies cc ON c.company_id = cc.id
             WHERE c.id_card IS NOT NULL AND c.id_card != ''"
        );
        return $stmt->fetchAll();
    }

    /**
     * Full sanction history (active, expired, and revoked) per contractor,
     * for the P2K3 mobile app's "histori sanksi" view.
     */
    public function getSanctionHistorySnapshot()
    {
        $stmt = $this->pdo->query(
            "SELECT s.id, c.ktp_no, s.sanction_type, s.is_permanent, s.start_date, s.end_date, s.revoked_at, s.reason
             FROM sanctions s
             JOIN contractors c ON c.id = s.contractor_id"
        );
        return $stmt->fetchAll();
    }

    /**
     * List of company names already known locally, pushed up to Supabase
     * `contractor_companies_cache` so the registration app can offer a
     * dropdown instead of free-text (reduces duplicate/typo'd PT names).
     */
    public function getCompanyNamesSnapshot()
    {
        $stmt = $this->pdo->query("SELECT name FROM contractor_companies ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function updateContractor($id, $data)
    {
        // NOTE: PDO does not allow mixing positional (?) and named (:x)
        // placeholders in the same statement - the previous version of this
        // query did that and would throw "SQLSTATE[HY093]" on every update.
        // Everything below uses positional placeholders only.
        // id_card is COALESCE'd so a plain edit (no renewal) leaves it
        // untouched; ContractorService only sets $data['id_card'] when a
        // renewal actually issues a new one.
        $stmt = $this->pdo->prepare("UPDATE contractors SET name = ?, ktp_no = ?, company_id = ?, plant_location = ?, registration_date = ?, expiry_date = ?, status = COALESCE(?, status), id_card = COALESCE(?, id_card) WHERE id = ?");
        $stmt->execute([
            $data['name'],
            $data['ktp_no'],
            $data['company_id'],
            $data['plant_location'],
            $data['registration_date'],
            $data['expiry_date'] ?? null,
            $data['status'] ?? null,
            $data['id_card'] ?? null,
            $id
        ]);
    }

    public function updatePhoto($id, $photo)
    {
        $stmt = $this->pdo->prepare("UPDATE contractors SET photo = ? WHERE id = ?");
        $stmt->execute([$photo, $id]);
    }

    public function updateQrCode($id, $qrCode)
    {
        $stmt = $this->pdo->prepare("UPDATE contractors SET qr_code = ? WHERE id = ?");
        $stmt->execute([$qrCode, $id]);
    }

    public function updateStatus($id, $status)
    {
        $stmt = $this->pdo->prepare("UPDATE contractors SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    /**
     * Reactivates contractors whose status is stuck at 'Banned' but who no
     * longer have any currently-active sanction (temporary ban expired, or
     * all their bans revoked). Keeps the `status` column consistent with
     * the `active_bans` view, so a stale "Banned" row doesn't silently
     * disappear from the banned list while the ID card stays blocked.
     * Returns how many rows were flipped back to 'Active'.
     */
    public function autoReactivateExpiredBanned(): int
    {
        $stmt = $this->pdo->query(
            "UPDATE contractors c"
            . " SET c.status = 'Active'"
            . " WHERE c.status = 'Banned'"
            . " AND NOT EXISTS ("
            . "   SELECT 1 FROM sanctions s"
            . "   WHERE s.contractor_id = c.id"
            . "     AND s.revoked_at IS NULL"
            . "     AND s.sanction_type IN ('BANNED','SP1','SP2')"
            . "     AND (s.is_permanent = 1 OR s.end_date IS NULL OR s.end_date >= CURDATE())"
            . " )"
        );
        return $stmt->rowCount();
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM contractors WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function insertSanction($data)
    {
        $stmt = $this->pdo->prepare("INSERT INTO sanctions (contractor_id, violation_id, sanction_type, start_date, end_date, is_permanent, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['contractor_id'], $data['violation_id'] ?? null, $data['sanction_type'], 
            $data['start_date'], $data['end_date'] ?? null, $data['is_permanent'], $data['reason']
        ]);
    }
}
