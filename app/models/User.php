<?php

class User {
    private $db;

    public function __construct($db = null){
        $this->db = $db instanceof Database ? $db : new Database;
    }

    public function findUserByUsername($username){
        $this->db->query('SELECT * FROM users WHERE username = :username');
        $this->db->bind(':username', $username);
        $row = $this->db->single();
        return ($this->db->rowCount() > 0);
    }

    public function getUserIdsByClockIds($clockIds) {
        if (empty($clockIds)) {
            return [];
        }
    
        $placeholders = [];
        foreach ($clockIds as $index => $id) {
            $placeholders[] = ":id$index";
        }
    
        $inClause = implode(',', $placeholders);
    
        $this->db->query("SELECT DISTINCT user_id FROM user_clock_mappings WHERE user_clock_id IN ($inClause)");
    
        foreach ($clockIds as $index => $id) {
            $this->db->bind(":id$index", $id);
        }
    
        $results = $this->db->resultSet();
    
        $userIds = [];
        foreach ($results as $row) {
            $userIds[] = $row->user_id;
        }
    
        return $userIds;
    }

    public function login($username, $password){
        $this->db->query('SELECT * FROM users WHERE username = :username');
        $this->db->bind(':username', $username);
        $row = $this->db->single();
        if ($row) {
            if (isset($row->is_active) && !(int)$row->is_active) {
                return 'inactive';
            }
            $hashed_password = $row->password;
            if (password_verify($password, $hashed_password)) {
                return $row;
            }
        }
        return false;
    }

    public function getProfilePictureById($userId) {
        $this->db->query('SELECT profile_picture FROM users WHERE id = :id LIMIT 1');
        $this->db->bind(':id', (int)$userId);
        $row = $this->db->single();
        return $row ? (string)($row->profile_picture ?? '') : '';
    }

    public static function sexOptions() {
        return [
            ''  => '— No especificado',
            'M' => 'Masculino',
            'F' => 'Femenino',
            'X' => 'Otro / X',
        ];
    }

    public static function genderOptions() {
        return [
            ''                  => '— No especificado',
            'mujer'             => 'Mujer',
            'hombre'            => 'Hombre',
            'no_binario'        => 'No binario',
            'otro'              => 'Otro',
            'prefiero_no_decir' => 'Prefiero no decir',
        ];
    }

    public static function sexLabel($code) {
        $opts = self::sexOptions();
        return $opts[$code] ?? ($code ?: '—');
    }

    public static function genderLabel($code) {
        $opts = self::genderOptions();
        return $opts[$code] ?? ($code ?: '—');
    }

    public static function organizationGroupOptions() {
        return [
            'paviotti' => 'Paviotti',
            'moderna' => 'Moderna',
        ];
    }

    public static function normalizeOrganizationGroup($value) {
        $value = strtolower(trim((string)$value));
        return array_key_exists($value, self::organizationGroupOptions()) ? $value : 'paviotti';
    }

    public static function attendanceControlOptions() {
        return ['required' => 'Obligatorio — genera alertas', 'flexible' => 'Flexible — registra sin alertas', 'no_clock' => 'Sin reloj — no controla fichadas'];
    }

    public static function normalizeAttendanceControlMode($value) {
        $value = strtolower(trim((string)$value));
        return array_key_exists($value, self::attendanceControlOptions()) ? $value : 'required';
    }

    public function isAttendanceControlReady() {
        static $ready = null;
        if ($ready !== null) return $ready;
        try { $this->db->query("SHOW COLUMNS FROM users LIKE 'attendance_control_mode'"); $ready = (bool)$this->db->single(); }
        catch (Throwable $e) { $ready = false; }
        return $ready;
    }

    /** Campos de perfil desde POST (crear / editar usuario). */
    public static function profileFromPost(array $post) {
        $sex = isset($post['sex']) ? trim((string)$post['sex']) : '';
        if (!in_array($sex, ['M', 'F', 'X'], true)) {
            $sex = null;
        }
        $gender = isset($post['gender']) ? trim((string)$post['gender']) : '';
        $allowedGender = array_keys(array_filter(self::genderOptions(), function ($k) {
            return $k !== '';
        }, ARRAY_FILTER_USE_KEY));
        if ($gender !== '' && !in_array($gender, $allowedGender, true)) {
            $gender = null;
        } elseif ($gender === '') {
            $gender = null;
        }
        $birth = isset($post['birth_date']) ? trim((string)$post['birth_date']) : '';
        if ($birth !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) {
            $birth = null;
        } elseif ($birth === '') {
            $birth = null;
        }
        return [
            'email'                   => isset($post['email']) ? trim((string)$post['email']) : null,
            'phone_number'            => isset($post['phone_number']) ? trim((string)$post['phone_number']) : null,
            'address'                 => isset($post['address']) ? trim((string)$post['address']) : null,
            'document_number'         => isset($post['document_number']) ? trim((string)$post['document_number']) : null,
            'cuil'                    => isset($post['cuil']) ? trim((string)$post['cuil']) : null,
            'sex'                     => $sex,
            'gender'                  => $gender,
            'birth_date'              => $birth,
            'emergency_contact_name'  => isset($post['emergency_contact_name']) ? trim((string)$post['emergency_contact_name']) : null,
            'emergency_contact_phone' => isset($post['emergency_contact_phone']) ? trim((string)$post['emergency_contact_phone']) : null,
            'emergency_contact_relationship' => isset($post['emergency_contact_relationship']) ? trim((string)$post['emergency_contact_relationship']) : null,
            'marital_status'          => self::normalizeMaritalStatus($post['marital_status'] ?? ''),
            'children_count'          => (isset($post['children_count']) && $post['children_count'] !== '') ? max(0, (int)$post['children_count']) : null,
            'hr_notes'                => isset($post['hr_notes']) ? trim((string)$post['hr_notes']) : null,
        ];
    }

    /** Opciones de estado civil (valores de la nómina de RRHH). */
    public static function maritalStatusOptions() {
        return [
            ''             => '— Sin definir —',
            'Soltero/a'    => 'Soltero/a',
            'Casado/a'     => 'Casado/a',
            'Concubinato'  => 'Concubinato',
            'Divorciado/a' => 'Divorciado/a',
            'Viudo/a'      => 'Viudo/a',
        ];
    }

    public static function normalizeMaritalStatus($value) {
        $value = trim((string)$value);
        return array_key_exists($value, self::maritalStatusOptions()) && $value !== '' ? $value : null;
    }

    /** migración migration_ficha_personal.sql aplicada (estado civil, hijos, parentesco, observaciones). */
    public function isPersonalFileReady() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $this->db->query("SHOW COLUMNS FROM `users` LIKE 'marital_status'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    public function isProfileExtendedReady() {
        try {
            $this->db->query("SHOW COLUMNS FROM `users` LIKE 'phone_number'");
            return (bool)$this->db->single();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function isAreaReady() {
        try {
            $this->db->query("SHOW COLUMNS FROM `users` LIKE 'area_id'");
            return (bool)$this->db->single();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function isOrganizationGroupReady() {
        try {
            $this->db->query("SHOW COLUMNS FROM `users` LIKE 'employee_group'");
            return (bool)$this->db->single();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function isVacationProfileReady() {
        try {
            $this->db->query("SHOW COLUMNS FROM `users` LIKE 'hire_date'");
            return (bool)$this->db->single();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function isAgreementIdReady() {
        try {
            $this->db->query("SHOW COLUMNS FROM `users` LIKE 'agreement_id'");
            return (bool)$this->db->single();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function isProbationDateReady() {
        try {
            $this->db->query("SHOW COLUMNS FROM `users` LIKE 'probation_start_date'");
            return (bool)$this->db->single();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function updateVacationProfile($userId, $hireDate, $agreementId = null, $probationStartDate = null) {
        if (!$this->isVacationProfileReady()) {
            return false;
        }
        // Parámetros posicionales: evita HY093 con nombres tipo :p_agreement_id / :p_uid en PDO+MySQL.
        $sets = ['hire_date = ?'];
        $params = [$hireDate ?: null];
        if ($this->isAgreementIdReady()) {
            $sets[] = 'agreement_id = ?';
            $params[] = $agreementId > 0 ? (int)$agreementId : null;
        }
        if ($this->isProbationDateReady()) {
            $sets[] = 'probation_start_date = ?';
            $params[] = $probationStartDate ?: null;
        }
        $params[] = (int)$userId;
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $this->db->query($sql);
        return $this->db->execute($params);
    }

    private function employmentColumnNames() {
        $cols = [];
        if ($this->isProbationDateReady()) {
            $cols[] = 'probation_start_date';
        }
        if ($this->isVacationProfileReady()) {
            $cols[] = 'hire_date';
        }
        if ($this->isAgreementIdReady()) {
            $cols[] = 'agreement_id';
        }
        return $cols;
    }

    private function employmentValues(array $data) {
        $vals = [];
        if ($this->isProbationDateReady()) {
            $vals[] = !empty($data['probation_start_date']) ? $data['probation_start_date'] : null;
        }
        if ($this->isVacationProfileReady()) {
            $vals[] = !empty($data['hire_date']) ? $data['hire_date'] : null;
        }
        if ($this->isAgreementIdReady()) {
            $vals[] = isset($data['agreement_id']) && (int)$data['agreement_id'] > 0 ? (int)$data['agreement_id'] : null;
        }
        return $vals;
    }

    public function createUser($data) {
        $companyId = isset($data['company_id']) && (int)$data['company_id'] > 0
            ? (int)$data['company_id']
            : 0;
        if ($companyId <= 0) {
            $companyModel = new Company();
            $companyId = (int)($companyModel->getDefaultCompanyId() ?? 0);
        }
        if ($companyId <= 0) {
            $companyId = (int)($_SESSION['user_company_id'] ?? 0);
        }

        $areaId = isset($data['area_id']) && (int)$data['area_id'] > 0 ? (int)$data['area_id'] : null;
        $companyValue = $companyId > 0 ? $companyId : null;

        // Parámetros posicionales: evita HY093 con nombres tipo :company_id / :phone_number en PDO+MySQL.
        $cols = [];
        $vals = [];

        if ($this->isProfileExtendedReady()) {
            $cols = [
                'username', 'full_name', 'email', 'phone_number', 'address', 'document_number', 'cuil',
                'sex', 'gender', 'birth_date', 'emergency_contact_name', 'emergency_contact_phone',
                'password', 'role', 'company_id',
            ];
            $vals = [
                $data['username'],
                $data['full_name'],
                $data['email'] ?? null,
                $data['phone_number'] ?? null,
                $data['address'] ?? null,
                $data['document_number'] ?? null,
                $data['cuil'] ?? null,
                $data['sex'] ?? null,
                $data['gender'] ?? null,
                $data['birth_date'] ?? null,
                $data['emergency_contact_name'] ?? null,
                $data['emergency_contact_phone'] ?? null,
                $data['password_hash'],
                $data['role'],
                $companyValue,
            ];
        } else {
            $cols = ['username', 'full_name', 'password', 'role', 'company_id'];
            $vals = [
                $data['username'],
                $data['full_name'],
                $data['password_hash'],
                $data['role'],
                $companyValue,
            ];
        }

        if ($this->isAreaReady()) {
            $cols[] = 'area_id';
            $vals[] = $areaId;
        }
        if ($this->isOrganizationGroupReady()) {
            $cols[] = 'employee_group';
            $vals[] = self::normalizeOrganizationGroup($data['employee_group'] ?? 'paviotti');
        }
        if ($this->isBranchAssignmentReady()) {
            $cols[] = 'branch_id';
            $vals[] = isset($data['branch_id']) && (int)$data['branch_id'] > 0 ? (int)$data['branch_id'] : null;
        }
        if ($this->isAttendanceControlReady()) {
            $cols[] = 'attendance_control_mode';
            $vals[] = self::normalizeAttendanceControlMode($data['attendance_control_mode'] ?? 'required');
        }
        if ($this->isPersonalFileReady()) {
            $cols = array_merge($cols, ['marital_status', 'children_count', 'emergency_contact_relationship', 'hr_notes']);
            $vals = array_merge($vals, [
                $data['marital_status'] ?? null,
                $data['children_count'] ?? null,
                $data['emergency_contact_relationship'] ?? null,
                ($data['hr_notes'] ?? '') !== '' ? $data['hr_notes'] : null,
            ]);
        }

        $cols = array_merge($cols, $this->employmentColumnNames());
        $vals = array_merge($vals, $this->employmentValues($data));

        $cols[] = 'profile_picture';
        $vals[] = $data['profile_picture'];

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        // Alta atómica: si fallan las sucursales, no queda un usuario a medias.
        $this->lastCreateError = '';
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) $this->db->beginTransaction();
        try {
            $this->db->query('INSERT INTO users (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')');
            if (!$this->db->execute($vals)) {
                throw new RuntimeException('No se pudo insertar el usuario.');
            }
            $newId = (int)$this->db->lastInsertId();
            if ($this->isMultipleBranchAssignmentsReady()
                && !$this->saveBranchAssignments($newId, $companyId, $data['branch_ids'] ?? [], (int)($data['branch_id'] ?? 0))) {
                throw new RuntimeException('No se pudieron guardar las sucursales (¿pertenecen a la empresa elegida?).');
            }
            if ($ownTx) $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($ownTx) $this->db->rollBack();
            $this->lastCreateError = $e->getMessage();
            error_log('User::createUser(' . ($data['username'] ?? '?') . '): ' . $e->getMessage());
            return false;
        }
    }

    /** Detalle del último fallo de createUser() (para mostrarlo en el formulario). */
    public $lastCreateError = '';

    public function updateUser($data) {
        $profileReady = $this->isProfileExtendedReady();
        $areaReady = $this->isAreaReady();
        $companyId = isset($data['company_id']) && (int)$data['company_id'] > 0 ? (int)$data['company_id'] : null;

        // Parámetros posicionales: evita HY093 con nombres en PDO+MySQL (:phone_number, :p_company_id, etc.).
        $sets = [
            'full_name = ?',
            'role = ?',
            'company_id = ?',
            'hourly_rate = ?',
            'weekly_hour_limit = ?',
            'vacation_days_available = ?',
        ];
        $vals = [
            $data['full_name'],
            $data['role'],
            $companyId,
            is_numeric($data['hourly_rate'] ?? null) ? $data['hourly_rate'] : 0,
            is_numeric($data['weekly_hour_limit'] ?? null) ? $data['weekly_hour_limit'] : 0,
            is_numeric($data['vacation_days_available'] ?? null) ? $data['vacation_days_available'] : 0,
        ];

        if ($areaReady) {
            $sets[] = 'area_id = ?';
            $vals[] = isset($data['area_id']) && (int)$data['area_id'] > 0 ? (int)$data['area_id'] : null;
        }
        if ($this->isOrganizationGroupReady()) {
            $sets[] = 'employee_group = ?';
            $vals[] = self::normalizeOrganizationGroup($data['employee_group'] ?? 'paviotti');
        }
        if ($this->isBranchAssignmentReady()) {
            $sets[] = 'branch_id = ?';
            $vals[] = isset($data['branch_id']) && (int)$data['branch_id'] > 0 ? (int)$data['branch_id'] : null;
        }
        if ($this->isAttendanceControlReady()) {
            $sets[] = 'attendance_control_mode = ?';
            $vals[] = self::normalizeAttendanceControlMode($data['attendance_control_mode'] ?? 'required');
        }
        if ($profileReady) {
            $sets = array_merge($sets, [
                'email = ?',
                'phone_number = ?',
                'address = ?',
                'document_number = ?',
                'cuil = ?',
                'sex = ?',
                'gender = ?',
                'birth_date = ?',
                'emergency_contact_name = ?',
                'emergency_contact_phone = ?',
            ]);
            $vals = array_merge($vals, [
                $data['email'] ?? null,
                $data['phone_number'] ?? null,
                $data['address'] ?? null,
                $data['document_number'] ?? null,
                $data['cuil'] ?? null,
                $data['sex'] ?? null,
                $data['gender'] ?? null,
                $data['birth_date'] ?? null,
                $data['emergency_contact_name'] ?? null,
                $data['emergency_contact_phone'] ?? null,
            ]);
        }
        if ($this->isPersonalFileReady()) {
            $sets = array_merge($sets, [
                'marital_status = ?',
                'children_count = ?',
                'emergency_contact_relationship = ?',
                'hr_notes = ?',
            ]);
            $vals = array_merge($vals, [
                $data['marital_status'] ?? null,
                is_numeric($data['children_count'] ?? null) ? (int)$data['children_count'] : null,
                $data['emergency_contact_relationship'] ?? null,
                ($data['hr_notes'] ?? '') !== '' ? $data['hr_notes'] : null,
            ]);
        }
        foreach ($this->employmentColumnNames() as $col) {
            $sets[] = $col . ' = ?';
        }
        $vals = array_merge($vals, $this->employmentValues($data));
        if ($this->isPlexOperatorReady()) {
            $sets[] = 'plex_operator_name = ?';
            $plex = trim($data['plex_operator_name'] ?? '');
            $vals[] = $plex !== '' ? $plex : null;
        }
        if (!empty($data['password_hash'])) {
            $sets[] = 'password = ?';
            $vals[] = $data['password_hash'];
        }
        if (!empty($data['profile_picture'])) {
            $sets[] = 'profile_picture = ?';
            $vals[] = $data['profile_picture'];
        }

        $vals[] = (int)$data['id'];
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $this->db->query($sql);
        return $this->db->execute($vals);
    }

    /** Validación de datos personales editables por el empleado. */
    public static function validateSelfServiceProfile(array $data) {
        $errors = [];
        $email = trim((string)($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email no válido.';
        }
        return $errors;
    }

    /** Actualización de datos personales propios (empleado). */
    public function updateEmployeePersonalData($userId, array $data) {
        if (!$this->isProfileExtendedReady()) {
            return false;
        }
        $extended = [
            'email', 'phone_number', 'address', 'document_number', 'cuil', 'sex', 'gender', 'birth_date',
            'emergency_contact_name', 'emergency_contact_phone',
        ];
        $sets = [];
        $vals = [];
        foreach ($extended as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $sets[] = $col . ' = ?';
            $vals[] = $data[$col];
        }
        if ($this->isPersonalFileReady()) {
            foreach (['marital_status', 'emergency_contact_relationship'] as $col) {
                if (!array_key_exists($col, $data)) {
                    continue;
                }
                $sets[] = $col . ' = ?';
                $vals[] = $data[$col];
            }
        }
        if ($sets === []) {
            return true;
        }
        $vals[] = (int)$userId;
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $this->db->query($sql);
        return $this->db->execute($vals);
    }

    /** Actualización de perfil propio (empleado): contraseña y/o foto. */
    public function updateEmployeeProfile($userId, $data) {
        $sets = [];
        $vals = [];
        if (!empty($data['password_hash'])) {
            $sets[] = 'password = ?';
            $vals[] = $data['password_hash'];
        }
        if (!empty($data['profile_picture'])) {
            $sets[] = 'profile_picture = ?';
            $vals[] = $data['profile_picture'];
        }
        if ($sets === []) {
            return true;
        }
        $vals[] = (int)$userId;
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $this->db->query($sql);
        return $this->db->execute($vals);
    }

    public function getUserById($id){
        $this->db->query('SELECT * FROM users WHERE id = :id');
        $this->db->bind(':id', $id);
        $row = $this->db->single();
        return $row;
    }

    public function getUserByUsername($username) {
        $this->db->query('SELECT * FROM users WHERE username = ? LIMIT 1');
        return $this->db->single([trim((string)$username)]);
    }

    /** La migración de sedes puede aplicarse después del código sin romper altas existentes. */
    public function isBranchAssignmentReady() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $this->db->query("SHOW COLUMNS FROM users LIKE 'branch_id'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    /** Relación N:M: un empleado puede trabajar en varias sucursales. */
    public function isMultipleBranchAssignmentsReady() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $this->db->query("SHOW TABLES LIKE 'employee_branch_assignments'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    public function getBranchAssignmentsForUser($userId) {
        if (!$this->isMultipleBranchAssignmentsReady()) {
            $user = $this->getUserById((int)$userId);
            if (!$user || empty($user->branch_id)) return [];
            $this->db->query('SELECT b.*, 1 AS is_primary FROM company_branches b WHERE b.id = ?');
            return $this->db->resultSet([(int)$user->branch_id]);
        }
        $this->db->query('SELECT b.*, eba.is_primary FROM employee_branch_assignments eba
            INNER JOIN company_branches b ON b.id = eba.branch_id
            WHERE eba.user_id = ? ORDER BY eba.is_primary DESC, b.locality ASC, b.name ASC');
        return $this->db->resultSet([(int)$userId]);
    }

    public function isUserAssignedToBranch($userId, $branchId) {
        if ((int)$userId <= 0 || (int)$branchId <= 0) return false;
        if ($this->isMultipleBranchAssignmentsReady()) {
            $this->db->query('SELECT 1 FROM employee_branch_assignments WHERE user_id = ? AND branch_id = ? LIMIT 1');
            return (bool)$this->db->single([(int)$userId, (int)$branchId]);
        }
        $user = $this->getUserById((int)$userId);
        return $user && (int)($user->branch_id ?? 0) === (int)$branchId;
    }

    public function saveBranchAssignments($userId, $companyId, $branchIds, $primaryBranchId = 0) {
        if (!$this->isMultipleBranchAssignmentsReady()) {
            return true;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$branchIds))));
        $primaryBranchId = (int)$primaryBranchId;
        if ($primaryBranchId > 0 && !in_array($primaryBranchId, $ids, true)) {
            $ids[] = $primaryBranchId;
        }
        if (empty($ids)) {
            $primaryBranchId = 0;
        } elseif ($primaryBranchId <= 0) {
            $primaryBranchId = (int)$ids[0];
        }
        $company = new Company();
        foreach ($ids as $branchId) {
            if (!$company->getBranchByIdForCompany($branchId, $companyId, true)) {
                return false;
            }
        }
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) $this->db->beginTransaction();
        try {
            $this->db->query('DELETE FROM employee_branch_assignments WHERE user_id = ?');
            $this->db->execute([(int)$userId]);
            if (!empty($ids)) {
                $this->db->query('INSERT INTO employee_branch_assignments (user_id, branch_id, is_primary) VALUES (?, ?, ?)');
                foreach ($ids as $branchId) {
                    if (!$this->db->execute([(int)$userId, (int)$branchId, $branchId === $primaryBranchId ? 1 : 0])) {
                        throw new RuntimeException('No se pudo guardar la sucursal.');
                    }
                }
            }
            $this->db->query('UPDATE users SET branch_id = ? WHERE id = ?');
            if (!$this->db->execute([$primaryBranchId > 0 ? $primaryBranchId : null, (int)$userId])) {
                throw new RuntimeException('No se pudo guardar la sucursal principal.');
            }
            if ($ownTx) $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($ownTx) $this->db->rollBack();
            return false;
        }
    }

    /** Búsqueda por nombre (todas las empresas) para mapeo de relojes. */
    public function searchUsersByName($query, $limit = 20) {
        $query = trim((string)$query);
        if ($query === '') {
            return [];
        }
        $this->db->query('
            SELECT u.id, u.full_name, u.username, u.company_id, u.is_active,
                   c.name AS company_name
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            WHERE u.full_name LIKE :q1 OR u.username LIKE :q2
            ORDER BY u.full_name ASC
            LIMIT :lim
        ');
        $like = '%' . $query . '%';
        $this->db->bind(':q1', $like);
        $this->db->bind(':q2', $like);
        $this->db->bind(':lim', (int)$limit, PDO::PARAM_INT);
        return $this->db->resultSet();
    }

    public function getUsersByCompany($companyId, $branchId = null) {
        $branchSelect = $this->isBranchAssignmentReady() ? ', u.branch_id, b.name AS branch_name, b.locality AS branch_locality' : '';
        $branchJoin = $this->isBranchAssignmentReady() ? ' LEFT JOIN company_branches b ON b.id = u.branch_id' : '';
        $branchWhere = '';
        if ($this->isMultipleBranchAssignmentsReady() && (int)$branchId > 0) {
            $branchWhere = ' AND EXISTS (SELECT 1 FROM employee_branch_assignments eba WHERE eba.user_id = u.id AND eba.branch_id = :branch_id)';
        } elseif ($this->isBranchAssignmentReady() && (int)$branchId > 0) {
            $branchWhere = ' AND u.branch_id = :branch_id';
        }
        $this->db->query('
            SELECT u.id, u.username, u.full_name, u.role, u.is_active, u.weekly_hour_limit, u.profile_picture, u.company_id,
                   c.name AS company_name' . $branchSelect . '
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id' . $branchJoin . '
            WHERE u.company_id = :company_id' . $branchWhere . '
            ORDER BY u.full_name ASC
        ');
        $this->db->bind(':company_id', $companyId);
        if ((($this->isMultipleBranchAssignmentsReady() || $this->isBranchAssignmentReady()) && (int)$branchId > 0)) {
            $this->db->bind(':branch_id', (int)$branchId);
        }
        return $this->db->resultSet();
    }

    /**
     * Empleados activos (no baja) para liquidación masiva de vacaciones.
     * is_active = 0 → despido, renuncia u otra baja (toggle en usuarios).
     */
    public function getActiveEmployeesForVacationLiquidation($companyId) {
        $sql = '
            SELECT u.id, u.full_name, u.hire_date, u.agreement_id, u.company_id, c.name AS company_name
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            WHERE u.is_active = 1 AND u.role = :role_emp
        ';
        if ((int)$companyId > 0) {
            $sql .= ' AND u.company_id = :company_id';
        }
        $sql .= ' ORDER BY u.full_name ASC';
        $this->db->query($sql);
        $this->db->bind(':role_emp', 'empleado');
        if ((int)$companyId > 0) {
            $this->db->bind(':company_id', (int)$companyId);
        }
        return $this->db->resultSet();
    }

    /** Empleados activos con empresa y área (notificaciones / avisos). */
    public function getActiveEmployeesForNotifications() {
        $areaJoin = '';
        $areaCol = '';
        $groupCol = $this->isOrganizationGroupReady() ? ', u.employee_group' : ", 'paviotti' AS employee_group";
        try {
            $this->db->query("SHOW COLUMNS FROM `users` LIKE 'area_id'");
            if ($this->db->single()) {
                $areaJoin = ' LEFT JOIN areas a ON a.id = u.area_id';
                $areaCol = ', u.area_id, a.name AS area_name';
            }
        } catch (Throwable $e) {
            // sin area_id
        }
        $this->db->query("
            SELECT u.id, u.full_name, u.company_id{$groupCol}, c.name AS company_name{$areaCol}
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            {$areaJoin}
            WHERE u.role = 'empleado' AND u.is_active = 1
            ORDER BY c.name ASC, u.full_name ASC
        ");
        return $this->db->resultSet();
    }

    /** Lista para agregar destinatario manual (empleados + admins activos, todas las empresas). */
    public function getUsersForNotificationPicker() {
        $areaJoin = '';
        $areaCol = '';
        $groupCol = $this->isOrganizationGroupReady() ? ', u.employee_group' : ", 'paviotti' AS employee_group";
        try {
            $this->db->query("SHOW COLUMNS FROM `users` LIKE 'area_id'");
            if ($this->db->single()) {
                $areaJoin = ' LEFT JOIN areas a ON a.id = u.area_id';
                $areaCol = ', u.area_id, a.name AS area_name';
            }
        } catch (Throwable $e) {
        }
        $this->db->query("
            SELECT u.id, u.full_name, u.role, u.company_id{$groupCol}, c.name AS company_name{$areaCol}
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            {$areaJoin}
            WHERE u.is_active = 1 AND u.role IN ('empleado', 'admin')
            ORDER BY u.role DESC, c.name ASC, u.full_name ASC
        ");
        return $this->db->resultSet();
    }

    /** Compañeros activos de la misma empresa (para cambio de turno). */
    public function getColleaguesForShiftSwap($companyId, $excludeUserId) {
        if (!$companyId) {
            return [];
        }
        $this->db->query('
            SELECT u.id, u.full_name, u.profile_picture
            FROM users u
            WHERE u.company_id = :company_id
              AND u.id != :exclude_user_id
              AND u.is_active = 1
            ORDER BY u.full_name ASC
        ');
        $this->db->bind(':company_id', $companyId);
        $this->db->bind(':exclude_user_id', $excludeUserId);
        return $this->db->resultSet();
    }

    public function getAllUsersWithCompany($companyFilterId = null, $branchId = 0) {
        $result = $this->getAdminDirectory([
            'company_id' => $companyFilterId ? (int)$companyFilterId : 0,
            'branch_id' => (int)$branchId,
            'page' => 1,
            'per_page' => 100000,
        ]);
        return $result['users'];
    }

    /**
     * Directorio de /admin/users: filtros en SQL, paginación y KPIs del contexto.
     * $opts: company_id, branch_id, company_ids, q, filter, city, branch_name,
     *        page, per_page, record_ready, access_ready.
     */
    public function getAdminDirectory(array $opts) {
        $companyFilterId = (int)($opts['company_id'] ?? 0);
        $branchId = (int)($opts['branch_id'] ?? 0);
        $restrictIds = array_values(array_unique(array_filter(array_map('intval', $opts['company_ids'] ?? []))));
        $q = trim((string)($opts['q'] ?? ''));
        $q = str_replace(['%', '_'], '', $q);
        if (mb_strlen($q) > 80) {
            $q = mb_substr($q, 0, 80);
        }
        $filter = (string)($opts['filter'] ?? 'all');
        $city = trim((string)($opts['city'] ?? ''));
        $branchName = trim((string)($opts['branch_name'] ?? ''));
        $page = max(1, (int)($opts['page'] ?? 1));
        $perPage = max(1, min(96, (int)($opts['per_page'] ?? 48)));
        $recordReady = !empty($opts['record_ready']);
        $accessReady = !empty($opts['access_ready']);
        $multiBranchReady = $this->isMultipleBranchAssignmentsReady();
        $branchColReady = $this->isBranchAssignmentReady();
        $profileReady = $this->isProfileExtendedReady();
        $hireReady = $this->isVacationProfileReady();

        $joins = ' FROM users u LEFT JOIN companies c ON c.id = u.company_id';
        if ($accessReady) {
            $joins .= ' LEFT JOIN user_access_scopes uas ON uas.user_id = u.id AND uas.is_primary = 1 AND uas.is_active = 1';
        }
        if ($recordReady) {
            $joins .= ' LEFT JOIN employee_company_assignments eca ON eca.id = (
                    SELECT e2.id FROM employee_company_assignments e2
                    WHERE e2.user_id = u.id AND e2.company_id = u.company_id
                    ORDER BY e2.is_primary DESC, e2.id DESC LIMIT 1
                )
                LEFT JOIN job_positions jp ON jp.id = eca.position_id
                LEFT JOIN areas a ON a.id = eca.area_id';
        }

        $scopeSql = $accessReady
            ? "COALESCE(uas.access_role, CASE u.role WHEN 'admin' THEN 'administrador' WHEN 'supervisor' THEN 'encargado' ELSE 'operario' END)"
            : "CASE u.role WHEN 'admin' THEN 'administrador' WHEN 'supervisor' THEN 'encargado' ELSE 'operario' END";

        $context = $this->adminDirectoryContextWhere($companyFilterId, $branchId, $restrictIds, $multiBranchReady, $branchColReady);
        $filtered = $this->adminDirectoryFilterWhere($filter, $q, $city, $branchName, $scopeSql, $recordReady, $multiBranchReady, $profileReady, $hireReady);

        $whereSql = '';
        $whereParts = array_merge($context['sql'], $filtered['sql']);
        if ($whereParts) {
            $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
        }
        $filterParams = array_merge($context['params'], $filtered['params']);

        $this->db->query('SELECT COUNT(*) AS n' . $joins . $whereSql);
        $totalRow = $this->db->single($filterParams);
        $total = (int)($totalRow->n ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $select = 'SELECT u.*, c.name AS company_name';
        if ($accessReady) {
            $select .= ', uas.access_role AS access_role';
        }
        $this->db->query($select . $joins . $whereSql . ' ORDER BY c.name ASC, u.full_name ASC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset);
        $users = $this->db->resultSet($filterParams);

        $kpiWhereSql = $context['sql'] ? (' WHERE ' . implode(' AND ', $context['sql'])) : '';
        $kpiSelect = 'SELECT COUNT(*) AS total, SUM(u.is_active = 1) AS access_active';
        if ($recordReady) {
            $kpiSelect .= ", SUM(eca.status = 'activo') AS labor_active";
            if ($multiBranchReady) {
                $kpiSelect .= ', SUM((SELECT COUNT(*) FROM employee_branch_assignments ebc WHERE ebc.user_id = u.id) > 1) AS multi_branch';
            } else {
                $kpiSelect .= ', 0 AS multi_branch';
            }
            $kpiSelect .= ', SUM((' . $this->adminDirectoryCompletenessSql($multiBranchReady, $profileReady, $hireReady) . ') < 70) AS incomplete';
        } else {
            $kpiSelect .= ', NULL AS labor_active, NULL AS multi_branch, NULL AS incomplete';
        }
        $this->db->query($kpiSelect . $joins . $kpiWhereSql);
        $kpiRow = $this->db->single($context['params']);

        return [
            'users' => $users ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'kpis' => [
                'total' => (int)($kpiRow->total ?? 0),
                'access_active' => (int)($kpiRow->access_active ?? 0),
                'labor_active' => isset($kpiRow->labor_active) ? (int)$kpiRow->labor_active : null,
                'incomplete' => isset($kpiRow->incomplete) ? (int)$kpiRow->incomplete : null,
                'multi_branch' => isset($kpiRow->multi_branch) ? (int)$kpiRow->multi_branch : null,
            ],
        ];
    }

    private function adminDirectoryContextWhere($companyFilterId, $branchId, array $restrictIds, $multiBranchReady, $branchColReady) {
        $sql = [];
        $params = [];
        if ($companyFilterId > 0) {
            $sql[] = 'u.company_id = ?';
            $params[] = $companyFilterId;
        } elseif ($restrictIds) {
            $sql[] = 'u.company_id IN (' . implode(',', array_fill(0, count($restrictIds), '?')) . ')';
            $params = array_merge($params, $restrictIds);
        }
        if ($branchId > 0 && $companyFilterId > 0) {
            if ($multiBranchReady) {
                $sql[] = 'EXISTS (SELECT 1 FROM employee_branch_assignments eba WHERE eba.user_id = u.id AND eba.branch_id = ?)';
                $params[] = $branchId;
            } elseif ($branchColReady) {
                $sql[] = 'u.branch_id = ?';
                $params[] = $branchId;
            }
        }
        return ['sql' => $sql, 'params' => $params];
    }

    private function adminDirectoryFilterWhere($filter, $q, $city, $branchName, $scopeSql, $recordReady, $multiBranchReady, $profileReady, $hireReady) {
        $sql = [];
        $params = [];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $parts = ['u.full_name LIKE ?', 'u.username LIKE ?', 'c.name LIKE ?'];
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            if ($profileReady) {
                $parts[] = 'u.document_number LIKE ?';
                $params[] = $like;
            }
            if ($recordReady) {
                $parts[] = 'eca.employee_number LIKE ?';
                $parts[] = 'jp.name LIKE ?';
                $parts[] = 'a.name LIKE ?';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
            if ($multiBranchReady) {
                $parts[] = 'EXISTS (SELECT 1 FROM employee_branch_assignments eba2 INNER JOIN company_branches b2 ON b2.id = eba2.branch_id WHERE eba2.user_id = u.id AND b2.name LIKE ?)';
                $params[] = $like;
            }
            $sql[] = '(' . implode(' OR ', $parts) . ')';
        }
        if ($multiBranchReady && ($city !== '' || $branchName !== '')) {
            $exists = 'EXISTS (SELECT 1 FROM employee_branch_assignments eba3 INNER JOIN company_branches b3 ON b3.id = eba3.branch_id WHERE eba3.user_id = u.id';
            if ($branchName !== '') {
                $exists .= ' AND b3.name = ?';
                $params[] = $branchName;
            }
            if ($city !== '') {
                $exists .= ' AND b3.locality = ?';
                $params[] = $city;
            }
            $sql[] = $exists . ')';
        }
        $accessRoles = array_keys(AccessControl::roles());
        if (in_array($filter, $accessRoles, true)) {
            $sql[] = $scopeSql . ' = ?';
            $params[] = $filter;
        } elseif ($filter === 'access-active') {
            $sql[] = 'u.is_active = 1';
        } elseif ($filter === 'labor-active' && $recordReady) {
            $sql[] = "eca.status = 'activo'";
        } elseif ($filter === 'multibranch' && $multiBranchReady) {
            $sql[] = '(SELECT COUNT(*) FROM employee_branch_assignments ebc WHERE ebc.user_id = u.id) > 1';
        } elseif ($filter === 'incomplete' && $recordReady) {
            $sql[] = '(' . $this->adminDirectoryCompletenessSql($multiBranchReady, $profileReady, $hireReady) . ') < 70';
        } elseif ($filter === 'admin') {
            $sql[] = $scopeSql . " IN ('administrador','rrhh')";
        } elseif ($filter === 'supervisor') {
            $sql[] = $scopeSql . " IN ('encargado','coordinador')";
        } elseif ($filter === 'empleado') {
            $sql[] = $scopeSql . " IN ('operario')";
        }
        return ['sql' => $sql, 'params' => $params];
    }

    private function adminDirectoryCompletenessSql($multiBranchReady, $profileReady, $hireReady) {
        $checks = [];
        if ($profileReady) {
            $checks[] = "CASE WHEN (IFNULL(u.document_number,'') <> '' OR IFNULL(u.cuil,'') <> '') THEN 1 ELSE 0 END";
            $checks[] = "CASE WHEN (IFNULL(u.email,'') <> '' OR IFNULL(u.phone_number,'') <> '') THEN 1 ELSE 0 END";
        } else {
            $checks[] = '0';
            $checks[] = '0';
        }
        $checks[] = $hireReady
            ? 'CASE WHEN u.hire_date IS NOT NULL THEN 1 ELSE 0 END'
            : '0';
        $checks[] = "CASE WHEN jp.name IS NOT NULL AND jp.name <> '' THEN 1 ELSE 0 END";
        $checks[] = $multiBranchReady
            ? 'CASE WHEN (SELECT COUNT(*) FROM employee_branch_assignments ebc WHERE ebc.user_id = u.id) > 0 THEN 1 ELSE 0 END'
            : "CASE WHEN IFNULL(u.branch_id,0) > 0 THEN 1 ELSE 0 END";
        $checks[] = "CASE WHEN EXISTS (SELECT 1 FROM employee_addresses ea WHERE ea.user_id = u.id AND ea.is_primary = 1) THEN 1 ELSE 0 END";
        $checks[] = "CASE WHEN EXISTS (SELECT 1 FROM employee_health_coverages ehc WHERE ehc.user_id = u.id AND ehc.is_primary = 1 AND ehc.status IN ('activa','en_tramite')) THEN 1 ELSE 0 END";
        return '((' . implode(' + ', $checks) . ') * 100 / 7)';
    }

    public function getAllUsers(){
        $this->db->query('SELECT * FROM users ORDER BY full_name ASC');
        return $this->db->resultSet();
    }

    public function toggleUserStatus($id){
        $this->db->query('UPDATE users SET is_active = !is_active WHERE id = :id');
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }
    
    public function countActiveUsersByCompany($companyId) {
        $this->db->query("SELECT COUNT(id) as count FROM users WHERE company_id = :company_id AND is_active = 1");
        $this->db->bind(':company_id', $companyId);
        $row = $this->db->single();
        return $row ? $row->count : 0;
    }

    public function getBirthdayInfo($companyId, $limit = 5) {
        $this->db->query("SELECT id, full_name, profile_picture, birth_date FROM users WHERE company_id = :company_id AND is_active = 1 AND MONTH(birth_date) = MONTH(CURDATE()) AND DAY(birth_date) = DAY(CURDATE())");
        $this->db->bind(':company_id', $companyId);
        $todaysBirthdays = $this->db->resultSet();

        $sqlUpcoming = "SELECT id, full_name, profile_picture, birth_date FROM users WHERE company_id = :company_id AND is_active = 1 AND birth_date IS NOT NULL AND NOT (MONTH(birth_date) = MONTH(CURDATE()) AND DAY(birth_date) = DAY(CURDATE())) ORDER BY CASE WHEN MONTH(birth_date) < MONTH(CURDATE()) THEN 1 WHEN MONTH(birth_date) = MONTH(CURDATE()) AND DAY(birth_date) < DAY(CURDATE()) THEN 1 ELSE 0 END ASC, MONTH(birth_date) ASC, DAY(birth_date) ASC LIMIT :limit";
        $this->db->query($sqlUpcoming);
        $this->db->bind(':company_id', $companyId);
        $this->db->bind(':limit', $limit);
        $upcomingBirthdays = $this->db->resultSet();

        return [
            'today' => $todaysBirthdays,
            'upcoming' => $upcomingBirthdays
        ];
    }

    public function clockDeviceMappingsReady() {
        static $ready = null;
        if ($ready !== null) return $ready;
        try {
            $this->db->query("SHOW TABLES LIKE 'user_clock_device_mappings'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) { $ready = false; }
        return $ready;
    }

    /** Busca por dispositivo + legajo. El legado se conserva como compatibilidad temporal. */
     public function findUserByClockId($clockId, $deviceName = null){
        if ($this->clockDeviceMappingsReady() && trim((string)$deviceName) !== '') {
            $this->db->query('SELECT u.* FROM users u
                JOIN user_clock_device_mappings m ON u.id = m.user_id
                JOIN clock_devices d ON d.id = m.clock_device_id
                WHERE m.employee_id = :clock_id AND d.external_name = :device_name LIMIT 1');
            $this->db->bind(':clock_id', (string)$clockId);
            $this->db->bind(':device_name', trim((string)$deviceName));
            $row = $this->db->single();
            if ($row) return $row;
        }
        $this->db->query('SELECT u.* FROM users u JOIN user_clock_mappings m ON u.id = m.user_id WHERE m.user_clock_id = :clock_id');
        $this->db->bind(':clock_id', $clockId);
        return $this->db->single();
    }

    public function getClockMappingsForUser($userId){
        $this->db->query("SELECT clock_name, user_clock_id FROM user_clock_mappings WHERE user_id = :user_id");
        $this->db->bind(':user_id', $userId);
        $results = $this->db->resultSet();
        $mappings = array();
        foreach($results as $row){
            $mappings[$row->clock_name] = $row->user_clock_id;
        }
        return $mappings;
    }

    public function saveClockMappings($userId, $mappings){
        $this->db->query("DELETE FROM user_clock_mappings WHERE user_id = :user_id");
        $this->db->bind(':user_id', $userId);
        $this->db->execute();

        $this->db->query("INSERT INTO user_clock_mappings (user_id, clock_name, user_clock_id) VALUES (:user_id, :clock_name, :user_clock_id)");
        foreach($mappings as $clockName => $clockId){
            if(!empty(trim($clockId))){
                $this->db->bind(':user_id', $userId);
                $this->db->bind(':clock_name', $clockName);
                $this->db->bind(':user_clock_id', trim($clockId));
                $this->db->execute();
            }
        }
        return true;
    }

    /**
     * Asigna (o reasigna) un employeeID de reloj a un usuario local.
     * Si ese clock_id ya estaba mapeado a otro usuario, lo desvincula primero.
     */
    public function upsertClockMapping($userId, $clockName, $clockId) {
        if ($this->clockDeviceMappingsReady() && trim((string)$clockName) !== '' && strpos((string)$clockName, ',') === false) {
            $device = new ClockDevice();
            $deviceId = $device->getOrCreate($clockName);
            if ($deviceId) {
                $this->db->query('DELETE FROM user_clock_device_mappings WHERE clock_device_id = ? AND employee_id = ?');
                $this->db->execute([(int)$deviceId, (string)$clockId]);
                $this->db->query('INSERT INTO user_clock_device_mappings (user_id, clock_device_id, employee_id) VALUES (?, ?, ?)');
                return $this->db->execute([(int)$userId, (int)$deviceId, (string)$clockId]);
            }
        }
        // Eliminar cualquier mapeo previo de este clock_id (sea cual sea el usuario)
        $this->db->query("DELETE FROM user_clock_mappings WHERE user_clock_id = :clock_id");
        $this->db->bind(':clock_id', $clockId);
        $this->db->execute();

        // Insertar nuevo mapeo
        $this->db->query("INSERT INTO user_clock_mappings (user_id, clock_name, user_clock_id) VALUES (:user_id, :clock_name, :user_clock_id)");
        $this->db->bind(':user_id',      $userId);
        $this->db->bind(':clock_name',   $clockName);
        $this->db->bind(':user_clock_id', $clockId);
        return $this->db->execute();
    }

    /**
     * Elimina el mapeo de un clock_id (desasociar empleado de reloj).
     */
    public function getUserIdByClockEmployeeId($clockId, $deviceName = null) {
        if ($this->clockDeviceMappingsReady() && trim((string)$deviceName) !== '') {
            $this->db->query('SELECT m.user_id FROM user_clock_device_mappings m JOIN clock_devices d ON d.id = m.clock_device_id WHERE m.employee_id = ? AND d.external_name = ? LIMIT 1');
            $row = $this->db->single([(string)$clockId, trim((string)$deviceName)]);
            return $row ? (int)$row->user_id : 0;
        }
        $this->db->query('SELECT user_id FROM user_clock_mappings WHERE user_clock_id = :clock_id LIMIT 1');
        $this->db->bind(':clock_id', (string)$clockId);
        $row = $this->db->single();
        return $row ? (int)$row->user_id : 0;
    }

    public function deleteClockMapping($clockId, $deviceName = null) {
        if ($this->clockDeviceMappingsReady() && trim((string)$deviceName) !== '') {
            $this->db->query('DELETE m FROM user_clock_device_mappings m JOIN clock_devices d ON d.id = m.clock_device_id WHERE m.employee_id = ? AND d.external_name = ?');
            return $this->db->execute([(string)$clockId, trim((string)$deviceName)]);
        }
        $this->db->query("DELETE FROM user_clock_mappings WHERE user_clock_id = :clock_id");
        $this->db->bind(':clock_id', $clockId);
        return $this->db->execute();
    }

    public function updateVacationBalance($userId, $newBalance){
        $this->db->query('UPDATE users SET vacation_days_available = :balance WHERE id = :id');
        $this->db->bind(':balance', $newBalance);
        $this->db->bind(':id', $userId);
        return $this->db->execute();
    }

    public function isPlexOperatorReady() {
        try {
            $this->db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'plex_operator_name'");
            return (bool)$this->db->single();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function updatePlexOperatorName($userId, $name) {
        if (!$this->isPlexOperatorReady()) {
            return false;
        }
        $name = trim((string)$name);
        $this->db->query('UPDATE users SET plex_operator_name = :name WHERE id = :id');
        $this->db->bind(':name', $name !== '' ? $name : null, $name !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $this->db->bind(':id', (int)$userId);
        return $this->db->execute();
    }

    public function getActiveAdminsByCompany($companyId) {
        $this->db->query("SELECT id, full_name, email FROM users
            WHERE company_id = :company_id AND is_active = 1 AND role = 'admin'");
        $this->db->bind(':company_id', (int)$companyId);
        return $this->db->resultSet();
    }

    public function setInactive($userId) {
        $this->db->query('UPDATE users SET is_active = 0 WHERE id = :id');
        $this->db->bind(':id', (int)$userId);
        return $this->db->execute();
    }
}
?>
