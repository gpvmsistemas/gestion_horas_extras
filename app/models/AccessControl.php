<?php

/** Perfiles por empresa/sucursal y herencia de módulos del portal. */
class AccessControl {
    private $db;

    public function __construct($db = null) {
        $this->db = $db instanceof Database ? $db : new Database();
    }

    public static function roles() {
        return [
            'operario' => 'Operario', 'encargado' => 'Encargado', 'coordinador' => 'Coordinador',
            'rrhh' => 'RRHH', 'administrador' => 'Administrador',
        ];
    }

    public static function portalFeatures() {
        return [
            'pay_stubs' => 'Ver recibos de sueldo', 'schedule' => 'Ver su horario',
            'salary_advance' => 'Solicitar adelanto de sueldo', 'overtime_submit' => 'Cargar horas extras',
            'overtime_review' => 'Ver horas extras cargadas', 'vacation_request' => 'Solicitar vacaciones',
            'requests' => 'Enviar solicitudes', 'suggestions' => 'Enviar sugerencias anónimas',
            'profile_edit' => 'Editar perfil', 'training' => 'Capacitaciones', 'surveys' => 'Encuestas',
            'announcements' => 'Comunicados y notificaciones', 'peer_stars' => 'Reconocimiento entre pares',
        ];
    }

    public static function capabilities() {
        return [
            'attendance.review','attendance.justify','attendance.prepare','attendance.close',
            'ppe.catalog','ppe.issue','ppe.receive','assets.catalog','assets.assign','assets.maintain',
            'discipline.create','discipline.review','discipline.view','expirations.manage',
            'recruiting.publish','recruiting.review','recruiting.ai','performance.manage',
            'performance.evaluate','performance.calibrate','metrics.operational','metrics.strategic','audit.view',
        ];
    }

    public static function capabilityLabels() {
        return [
            'attendance.review'=>'Asistencia: revisar','attendance.justify'=>'Asistencia: justificar','attendance.prepare'=>'Asistencia: preparar cierre','attendance.close'=>'Asistencia: cerrar/reabrir',
            'ppe.catalog'=>'EPP: catálogo/stock','ppe.issue'=>'EPP: entregar','ppe.receive'=>'EPP: recibir/recambiar','assets.catalog'=>'Activos: inventario','assets.assign'=>'Activos: asignar/transferir','assets.maintain'=>'Activos: mantener/baja',
            'discipline.create'=>'Sanciones: crear','discipline.review'=>'Sanciones: revisar/notificar','discipline.view'=>'Sanciones: consultar','expirations.manage'=>'Vencimientos: administrar',
            'recruiting.publish'=>'ATS: publicar','recruiting.review'=>'ATS: revisar/entrevistar/ofertar','recruiting.ai'=>'ATS: administrar IA','performance.manage'=>'Desempeño: configurar','performance.evaluate'=>'Desempeño: evaluar','performance.calibrate'=>'Desempeño: calibrar/cerrar','metrics.operational'=>'KPIs operativos','metrics.strategic'=>'KPIs estratégicos/globales','audit.view'=>'Auditoría: consultar',
        ];
    }

    public function getScopeCapabilities($scopeId) {$this->db->query('SELECT capability_key,decision FROM access_scope_capabilities WHERE access_scope_id=?');$out=[];foreach($this->db->resultSet([(int)$scopeId]) as $r)$out[$r->capability_key]=$r->decision;return $out;}
    public function saveScopeCapabilities($scopeId,array $values,$actorId){$scope=$this->getScope($scopeId);if(!$scope||!$this->canManageScope($actorId,$scope->company_id,(int)$scope->branch_id))return false;$this->db->beginTransaction();try{$this->db->query('DELETE FROM access_scope_capabilities WHERE access_scope_id=?');$this->db->execute([(int)$scopeId]);$this->db->query('INSERT INTO access_scope_capabilities(access_scope_id,capability_key,decision,updated_by) VALUES(?,?,?,?)');foreach(self::capabilities() as $key){$d=$values[$key]??'';if(in_array($d,['allow','deny'],true))$this->db->execute([(int)$scopeId,$key,$d,(int)$actorId]);}$this->audit($actorId,(int)$scope->user_id,'capabilities_saved',$scope->company_id,$scope->branch_id,$values);$this->db->commit();return true;}catch(Throwable $e){$this->db->rollBack();return false;}}

    public function hasCapability($userId, $capability, $companyId, $branchId = 0) {
        if (!in_array($capability, self::capabilities(), true)) return false;
        $scope = $this->currentScopeForUser($userId, (int)($_SESSION['access_scope_id'] ?? 0));
        if (!$scope) return false;
        if ($scope->access_role === 'administrador') return true;
        if ((int)$scope->company_id !== (int)$companyId) return false;
        if ($scope->access_role === 'encargado' && (int)$scope->branch_id !== (int)$branchId) return false;
        $this->db->query('SELECT decision FROM access_scope_capabilities WHERE access_scope_id=? AND capability_key=?');
        $decision = $this->db->single([(int)$scope->id,$capability]);
        if ($decision) return $decision->decision === 'allow';
        $defaults = [
            'rrhh'=>['attendance.review','attendance.justify','attendance.prepare','attendance.close','discipline.create','discipline.review','discipline.view','expirations.manage','recruiting.publish','recruiting.review','recruiting.ai','performance.manage','performance.evaluate','performance.calibrate','metrics.operational','metrics.strategic','audit.view'],
            'coordinador'=>['attendance.review','attendance.justify','attendance.prepare','discipline.create','discipline.view','performance.evaluate','metrics.operational'],
            'encargado'=>['attendance.review','attendance.justify','discipline.view','performance.evaluate'],
        ];
        return in_array($capability,$defaults[$scope->access_role]??[],true);
    }

    public function isReady() {
        static $ready = null;
        if ($ready !== null) return $ready;
        try {
            $this->db->query("SHOW TABLES LIKE 'user_access_scopes'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) { $ready = false; }
        return $ready;
    }

    public function getScopesForUser($userId, $activeOnly = false) {
        if (!$this->isReady()) return [];
        $sql = 'SELECT uas.*, c.name AS company_name, b.name AS branch_name, b.locality AS branch_locality
            FROM user_access_scopes uas
            INNER JOIN companies c ON c.id = uas.company_id
            LEFT JOIN company_branches b ON b.id = uas.branch_id
            WHERE uas.user_id = ?';
        if ($activeOnly) $sql .= ' AND uas.is_active = 1 AND (uas.starts_on IS NULL OR uas.starts_on <= CURDATE()) AND (uas.ends_on IS NULL OR uas.ends_on >= CURDATE())';
        $sql .= ' ORDER BY uas.is_primary DESC, uas.company_id, uas.branch_id, uas.id DESC';
        $this->db->query($sql);
        return $this->db->resultSet([(int)$userId]);
    }

    public function getScope($scopeId, $userId = 0) {
        if (!$this->isReady()) return null;
        $sql = 'SELECT * FROM user_access_scopes WHERE id = ?'; $params = [(int)$scopeId];
        if ($userId > 0) { $sql .= ' AND user_id = ?'; $params[] = (int)$userId; }
        $this->db->query($sql); return $this->db->single($params);
    }

    public function currentScopeForUser($userId, $scopeId = 0) {
        $scopes = $this->getScopesForUser($userId, true);
        foreach ($scopes as $scope) if ((int)$scope->id === (int)$scopeId) return $scope;
        return $scopes[0] ?? null;
    }

    public function saveScopes($userId, array $rows, $actorId = 0) {
        if (!$this->isReady()) return false;
        $userId = (int)$userId; $clean = []; $primarySeen = false;
        foreach ($rows as $row) {
            $companyId = (int)($row['company_id'] ?? 0); $branchId = (int)($row['branch_id'] ?? 0);
            $role = (string)($row['access_role'] ?? 'operario');
            if ($companyId <= 0 || !isset(self::roles()[$role])) return false;
            if ($branchId > 0 && !$this->branchBelongsToCompany($branchId, $companyId)) return false;
            $isPrimary = !empty($row['is_primary']) && !$primarySeen;
            $primarySeen = $primarySeen || $isPrimary;
            $clean[] = [$companyId, $branchId ?: null, $role, $isPrimary ? 1 : 0, !empty($row['is_active']) ? 1 : 0,
                trim((string)($row['starts_on'] ?? '')) ?: null, trim((string)($row['ends_on'] ?? '')) ?: null];
        }
        if (empty($clean)) return false;
        if (!$primarySeen) $clean[0][3] = 1;
        $this->db->beginTransaction();
        try {
            $this->db->query('DELETE FROM user_access_scopes WHERE user_id = ?'); $this->db->execute([$userId]);
            $this->db->query('INSERT INTO user_access_scopes (user_id, company_id, branch_id, access_role, is_primary, is_active, starts_on, ends_on, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($clean as $row) $this->db->execute(array_merge([$userId], $row, [$actorId ?: null]));
            $primary = $clean[0]; foreach ($clean as $row) if ($row[3]) { $primary = $row; break; }
            $this->db->query('UPDATE users SET company_id = ?, branch_id = ? WHERE id = ?');
            $this->db->execute([$primary[0], $primary[1], $userId]);
            $this->audit($actorId, $userId, 'scope_assignment_saved', $primary[0], $primary[1], ['count'=>count($clean)]);
            $this->db->commit(); return true;
        } catch (Throwable $e) { $this->db->rollBack(); return false; }
    }

    public function canManageScope($actorId, $companyId, $branchId = 0) {
        $scope = $this->currentScopeForUser($actorId, (int)($_SESSION['access_scope_id'] ?? 0));
        if (!$scope) return false;
        if ($scope->access_role === 'administrador') return true;
        if ($scope->access_role === 'rrhh') return (int)$scope->company_id === (int)$companyId;
        if ($scope->access_role === 'coordinador') return (int)$scope->company_id === (int)$companyId;
        if ($scope->access_role === 'encargado') return (int)$scope->company_id === (int)$companyId && (int)$scope->branch_id === (int)$branchId;
        return false;
    }

    public function getPolicies($companyId, $branchId = null) {
        if (!$this->isReady()) return [];
        $this->db->query($branchId ? 'SELECT * FROM organization_feature_policies WHERE company_id = ? AND branch_id = ?' : 'SELECT * FROM organization_feature_policies WHERE company_id = ? AND branch_id IS NULL');
        $rows = $this->db->resultSet($branchId ? [(int)$companyId, (int)$branchId] : [(int)$companyId]);
        $out = []; foreach ($rows as $row) $out[$row->feature_key] = $row->decision; return $out;
    }

    public function savePolicies($companyId, $branchId, array $decisions, $actorId) {
        if (!$this->isReady() || !$this->canManageScope($actorId, $companyId, $branchId)) return false;
        if ($branchId && !$this->branchBelongsToCompany($branchId, $companyId)) return false;
        $this->db->beginTransaction();
        try {
            $this->db->query($branchId ? 'DELETE FROM organization_feature_policies WHERE company_id = ? AND branch_id = ?' : 'DELETE FROM organization_feature_policies WHERE company_id = ? AND branch_id IS NULL');
            $this->db->execute($branchId ? [(int)$companyId, (int)$branchId] : [(int)$companyId]);
            $this->db->query('INSERT INTO organization_feature_policies (company_id, branch_id, feature_key, decision, updated_by) VALUES (?, ?, ?, ?, ?)');
            foreach (self::portalFeatures() as $key => $_) {
                $decision = $decisions[$key] ?? ''; if (!in_array($decision, ['allow','deny'], true)) continue;
                $this->db->execute([(int)$companyId, $branchId ? (int)$branchId : null, $key, $decision, (int)$actorId]);
            }
            $this->audit($actorId, 0, 'feature_policy_saved', $companyId, $branchId, $decisions); $this->db->commit(); return true;
        } catch (Throwable $e) { $this->db->rollBack(); return false; }
    }

    /** Copia solamente las reglas explícitas; las reglas ausentes conservan la herencia. */
    public function copyPolicies($sourceCompanyId, $sourceBranchId, $targetCompanyId, $targetBranchId, $actorId) {
        if (!$this->isReady() || !$this->canManageScope($actorId, $targetCompanyId, $targetBranchId)) return false;
        if ($sourceCompanyId <= 0 || $targetCompanyId <= 0) return false;
        if (!$this->companyExists($sourceCompanyId) || !$this->companyExists($targetCompanyId)) return false;
        if ($sourceBranchId && !$this->branchBelongsToCompany($sourceBranchId, $sourceCompanyId)) return false;
        if ($targetBranchId && !$this->branchBelongsToCompany($targetBranchId, $targetCompanyId)) return false;
        $source = $this->getPolicies($sourceCompanyId, $sourceBranchId ?: null);
        if (!$this->savePolicies($targetCompanyId, $targetBranchId, $source, $actorId)) return false;
        $this->audit($actorId, 0, 'feature_policy_copied', $targetCompanyId, $targetBranchId, [
            'source_company_id' => (int)$sourceCompanyId, 'source_branch_id' => (int)$sourceBranchId,
        ]);
        return true;
    }

    public function getUserOverrides($userId, $companyId, $branchId = null) {
        if (!$this->isReady()) return [];
        $this->db->query($branchId ? 'SELECT * FROM user_feature_overrides WHERE user_id = ? AND company_id = ? AND branch_id = ?' : 'SELECT * FROM user_feature_overrides WHERE user_id = ? AND company_id = ? AND branch_id IS NULL');
        $rows = $this->db->resultSet($branchId ? [(int)$userId,(int)$companyId,(int)$branchId] : [(int)$userId,(int)$companyId]);
        $out=[]; foreach($rows as $row) $out[$row->feature_key]=$row->decision; return $out;
    }

    public function saveUserOverrides($userId, $companyId, $branchId, array $decisions, $actorId) {
        if (!$this->isReady() || !$this->canManageScope($actorId, $companyId, $branchId)) return false;
        $this->db->beginTransaction();
        try {
            $this->db->query($branchId ? 'DELETE FROM user_feature_overrides WHERE user_id = ? AND company_id = ? AND branch_id = ?' : 'DELETE FROM user_feature_overrides WHERE user_id = ? AND company_id = ? AND branch_id IS NULL');
            $this->db->execute($branchId ? [(int)$userId,(int)$companyId,(int)$branchId] : [(int)$userId,(int)$companyId]);
            $this->db->query('INSERT INTO user_feature_overrides (user_id, company_id, branch_id, feature_key, decision, updated_by) VALUES (?, ?, ?, ?, ?, ?)');
            foreach (self::portalFeatures() as $key => $_) { $d=$decisions[$key]??''; if (in_array($d,['allow','deny'],true)) $this->db->execute([(int)$userId,(int)$companyId,$branchId?(int)$branchId:null,$key,$d,(int)$actorId]); }
            $this->audit($actorId,$userId,'user_feature_override_saved',$companyId,$branchId,$decisions); $this->db->commit(); return true;
        } catch (Throwable $e) { $this->db->rollBack(); return false; }
    }

    public function resolveFeature($userId, $companyId, $branchId, $feature, $globalDefault = true) {
        if (!$this->isReady() || !isset(self::portalFeatures()[$feature])) return $globalDefault;
        $decision = $globalDefault ? 'allow' : 'deny';
        foreach ([$this->getPolicies($companyId), $branchId ? $this->getPolicies($companyId,$branchId) : [], $this->getUserOverrides($userId,$companyId), $branchId ? $this->getUserOverrides($userId,$companyId,$branchId) : []] as $layer) {
            if (isset($layer[$feature])) $decision = $layer[$feature];
        }
        return $decision === 'allow';
    }

    private function branchBelongsToCompany($branchId, $companyId) {
        $this->db->query('SELECT 1 FROM company_branches WHERE id = ? AND company_id = ? AND is_active = 1');
        return (bool)$this->db->single([(int)$branchId,(int)$companyId]);
    }

    private function companyExists($companyId) {
        $this->db->query('SELECT 1 FROM companies WHERE id = ?');
        return (bool)$this->db->single([(int)$companyId]);
    }

    public function audit($actorId, $subjectId, $event, $companyId = null, $branchId = null, array $payload = []) {
        if (!$this->isReady()) return;
        $this->db->query('INSERT INTO access_audit_log (actor_user_id, subject_user_id, event_type, company_id, branch_id, payload_json) VALUES (?, ?, ?, ?, ?, ?)');
        $this->db->execute([$actorId ?: null,$subjectId ?: null,$event,$companyId ?: null,$branchId ?: null,json_encode($payload)]);
    }
}
