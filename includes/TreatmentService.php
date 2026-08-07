<?php
// =============================================================================
//  TreatmentService.php — OOP Service Class for Dental Record Operations
// =============================================================================
//  Demonstrates:
//    • Encapsulation  — all dental-record SQL is contained in one class.
//    • Constructor Injection — $conn is injected via the constructor.
//    • Separation of Concerns — view pages only call service methods;
//                               they never write SQL themselves.
//
//  Usage:
//      require_once '../../includes/TreatmentService.php';
//      $treatmentService = new TreatmentService($conn);
//      $records = $treatmentService->getForPatient($patientId);
// =============================================================================

class TreatmentService
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    // =========================================================================
    //  READ: ALL RECORDS FOR A PATIENT
    // =========================================================================

    /**
     * Return all dental records for one patient, newest first.
     * Includes service name and doctor full name.
     *
     * @param  int $patientId
     * @return array
     */
    public function getForPatient(int $patientId): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                dr.*,
                s.service_name,
                s.name AS service_display_name,
                CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
            FROM dental_records dr
            LEFT JOIN services s ON dr.service_id = s.id
            LEFT JOIN users    u ON dr.doctor_id  = u.id
            WHERE dr.patient_id = ?
            ORDER BY dr.visit_date DESC, dr.id DESC
        ");
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    //  READ: SINGLE RECORD
    // =========================================================================

    /**
     * Fetch one dental record by ID with patient and doctor info.
     * Returns null when not found.
     *
     * @param  int       $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT
                dr.*,
                s.service_name,
                CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                p.patient_code,
                CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
            FROM dental_records dr
            LEFT JOIN services s ON dr.service_id  = s.id
            LEFT JOIN patients p ON dr.patient_id  = p.id
            LEFT JOIN users    u ON dr.doctor_id   = u.id
            WHERE dr.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // =========================================================================
    //  READ: COUNT
    // =========================================================================

    /**
     * Return the total number of dental records matching $filters.
     *
     * @param  array $filters  Keys: patient_id, date_from, date_to, doctor_id
     * @return int
     */
    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS c FROM dental_records dr $where"
        );
        $stmt->execute($params);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['c'];
    }

    // =========================================================================
    //  READ: LIST (paginated)
    // =========================================================================

    /**
     * Return a paginated list of dental records.
     *
     * @param  array $filters
     * @param  int   $limit
     * @param  int   $offset
     * @return array
     */
    public function getList(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->conn->prepare("
            SELECT
                dr.*,
                s.service_name,
                CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                p.patient_code,
                CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
            FROM dental_records dr
            LEFT JOIN services s ON dr.service_id = s.id
            LEFT JOIN patients p ON dr.patient_id = p.id
            LEFT JOIN users    u ON dr.doctor_id  = u.id
            $where
            ORDER BY dr.visit_date DESC, dr.id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    //  READ: SUMMARY STATS
    // =========================================================================

    /**
     * Return treatment summary for one patient.
     * Keys: total_visits, total_fees, last_visit
     *
     * @param  int $patientId
     * @return array
     */
    public function getSummaryForPatient(int $patientId): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                COUNT(*)                          AS total_visits,
                COALESCE(SUM(fee_charged), 0)     AS total_fees,
                MAX(visit_date)                   AS last_visit
            FROM dental_records
            WHERE patient_id = ?
        ");
        $stmt->execute([$patientId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_visits' => 0,
            'total_fees'   => 0,
            'last_visit'   => null,
        ];
    }

    // =========================================================================
    //  WRITE: CREATE
    // =========================================================================

    /**
     * Insert a new dental record and return the new ID.
     *
     * @param  array $data
     * @return int
     */
    public function create(array $data): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO dental_records
                (patient_id, doctor_id, service_id, visit_date,
                 tooth_number, tooth_surface,
                 treatment_done, treatment_plan, materials_used,
                 notes, fee_charged)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['patient_id'],
            $data['doctor_id']       ?? null,
            $data['service_id']      ?? null,
            $data['visit_date']      ?? date('Y-m-d'),
            $data['tooth_number']    ?? null,
            $data['tooth_surface']   ?? null,
            $data['treatment_done']  ?? null,
            $data['treatment_plan']  ?? null,
            $data['materials_used']  ?? null,
            $data['notes']           ?? null,
            $data['fee_charged']     ?? null,
        ]);
        return (int) $this->conn->lastInsertId();
    }

    // =========================================================================
    //  WRITE: UPDATE
    // =========================================================================

    /**
     * Update an existing dental record.
     *
     * @param  int   $id
     * @param  array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE dental_records
            SET doctor_id      = ?,
                service_id     = ?,
                visit_date     = ?,
                tooth_number   = ?,
                tooth_surface  = ?,
                treatment_done = ?,
                treatment_plan = ?,
                materials_used = ?,
                notes          = ?,
                fee_charged    = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['doctor_id']       ?? null,
            $data['service_id']      ?? null,
            $data['visit_date']      ?? date('Y-m-d'),
            $data['tooth_number']    ?? null,
            $data['tooth_surface']   ?? null,
            $data['treatment_done']  ?? null,
            $data['treatment_plan']  ?? null,
            $data['materials_used']  ?? null,
            $data['notes']           ?? null,
            $data['fee_charged']     ?? null,
            $id,
        ]);
    }

    // =========================================================================
    //  WRITE: DELETE
    // =========================================================================

    /**
     * Permanently delete a dental record by ID.
     *
     * @param  int  $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM dental_records WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    // =========================================================================
    //  PRIVATE: Filter builder
    // =========================================================================

    /**
     * Build WHERE clause and parameter array from a filter array.
     *
     * @param  array $filters  Keys: patient_id, doctor_id, date_from, date_to
     * @return array  [string $where, array $params]
     */
    private function buildWhere(array $filters): array
    {
        $where  = "WHERE 1=1";
        $params = [];

        if (!empty($filters['patient_id'])) {
            $where   .= " AND dr.patient_id = ?";
            $params[] = (int) $filters['patient_id'];
        }

        if (!empty($filters['doctor_id'])) {
            $where   .= " AND dr.doctor_id = ?";
            $params[] = (int) $filters['doctor_id'];
        }

        if (!empty($filters['date_from']) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
            $where   .= " AND dr.visit_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to']) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
            $where   .= " AND dr.visit_date <= ?";
            $params[] = $filters['date_to'];
        }

        return [$where, $params];
    }
}
