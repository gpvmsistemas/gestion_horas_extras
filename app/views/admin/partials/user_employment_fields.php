<?php
/**
 * Fechas de empleo, convenio y saldo de vacaciones.
 * El controlador pasa flags en $data[]; este bloque los resuelve y consulta la BD si hace falta.
 */
if (isset($data) && is_array($data)) {
    if (!isset($employment_ready)) {
        $employment_ready = $data['employment_ready'] ?? null;
    }
    if (!isset($agreements_ready)) {
        $agreements_ready = $data['agreements_ready'] ?? null;
    }
    if (!isset($vacation_balance_ready)) {
        $vacation_balance_ready = $data['vacation_balance_ready'] ?? null;
    }
    if (!isset($agreements)) {
        $agreements = $data['agreements'] ?? null;
    }
    if (!isset($vacation_total_pending)) {
        $vacation_total_pending = $data['vacation_total_pending'] ?? null;
    }
    if (!isset($show_legacy_vacation_days)) {
        $show_legacy_vacation_days = $data['show_legacy_vacation_days'] ?? null;
    }
    if (!isset($user_id) && !empty($data['user_id'])) {
        $user_id = (int)$data['user_id'];
    }
    if (!isset($source) && !empty($data['user'])) {
        $source = $data['user'];
    }
}
$userModel = new User();
if (!isset($employment_ready) || $employment_ready === null) {
    $employment_ready = $userModel->isVacationProfileReady();
}
$probation_ready = $userModel->isProbationDateReady();
$agreementModel = new CollectiveAgreement();
if (!isset($agreements_ready) || $agreements_ready === null) {
    $agreements_ready = $agreementModel->isReady();
}
if (!isset($vacation_balance_ready) || $vacation_balance_ready === null) {
    $vacation_balance_ready = function_exists('vacation_module_ready') && vacation_module_ready();
}
if (!isset($agreements) || $agreements === null) {
    $agreements = $agreements_ready ? $agreementModel->getAll() : [];
}
if (!isset($show_legacy_vacation_days) || $show_legacy_vacation_days === null) {
    $show_legacy_vacation_days = $employment_ready && !$vacation_balance_ready;
}
$src = $source ?? null;
$probation = '';
$hire = '';
$agreementId = 0;
if (is_object($src)) {
    $probation = $src->probation_start_date ?? '';
    $hire = $src->hire_date ?? '';
    $agreementId = (int)($src->agreement_id ?? 0);
} elseif (is_array($src)) {
    $probation = $src['probation_start_date'] ?? '';
    $hire = $src['hire_date'] ?? '';
    $agreementId = (int)($src['agreement_id'] ?? 0);
}
if ($agreementId <= 0 && isset($data) && is_array($data)) {
    if (!empty($data['agreement_id'])) {
        $agreementId = (int)$data['agreement_id'];
    } elseif (!empty($data['employee_record']['assignment']->agreement_id)) {
        $agreementId = (int)$data['employee_record']['assignment']->agreement_id;
    }
}
$uid = (int)($user_id ?? ($src->id ?? 0));
?>

<hr class="my-4">
<h6 class="mb-2 text-muted"><i class="fas fa-briefcase me-1"></i> Empleo y vacaciones</h6>

<?php if (empty($employment_ready)): ?>
<div class="alert alert-warning small mb-0">
    Para cargar fechas de ingreso y convenio, ejecutá en MySQL
    <code>migration_collective_agreements.sql</code> y
    <code>migration_users_probation_date.sql</code> (ver <code>MIGRATIONS.md</code> #22 y #24).
    Mientras tanto podés usar <a href="<?php echo URLROOT; ?>/vacationAdmin/agreements">Convenios / Vacaciones</a>
    solo si ya corriste la migración base.
</div>
<?php else: ?>

<p class="small text-muted mb-3">
    <strong>Plan de prueba:</strong> cuándo empezó el período municipal (si aplica).
    <strong>Ingreso formal:</strong> desde cuándo cuenta la antigüedad para vacaciones (puede ser el mismo día u otro, ej. 01/06/2026).
    Las vacaciones del convenio se calculan solo con la <strong>fecha de ingreso formal</strong>.
</p>

<div class="row">
    <?php if ($probation_ready): ?>
    <div class="col-md-6 mb-3">
        <label for="probation_start_date" class="form-label">Inicio plan de prueba <span class="text-muted">(opcional)</span></label>
        <input type="date" name="probation_start_date" id="probation_start_date" class="form-control"
               value="<?php echo htmlspecialchars($probation); ?>">
        <small class="text-muted">Ej. ingreso municipal 01/01/2026; no define días de vacaciones.</small>
    </div>
    <?php endif; ?>
    <div class="col-md-6 mb-3">
        <label for="hire_date" class="form-label">Fecha de ingreso formal</label>
        <input type="date" name="hire_date" id="hire_date" class="form-control"
               value="<?php echo htmlspecialchars($hire); ?>">
        <small class="text-muted">Antigüedad y liquidación de vacaciones (CEC, farmacia, etc.).</small>
    </div>
    <?php if (!empty($agreements_ready) && !empty($agreements)): ?>
    <div class="col-md-6 mb-3">
        <label for="agreement_id" class="form-label">Convenio colectivo</label>
        <select name="agreement_id" id="agreement_id" class="form-select">
            <option value="0">Default de la empresa</option>
            <?php foreach ($agreements as $ag): ?>
            <option value="<?php echo (int)$ag->id; ?>" <?php echo $agreementId === (int)$ag->id ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($ag->name); ?> (<?php echo htmlspecialchars($ag->code); ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <small class="text-muted"><a href="<?php echo URLROOT; ?>/vacationAdmin/agreements" target="_blank">Gestionar convenios</a></small>
    </div>
    <?php endif; ?>
    <?php if (!empty($vacation_balance_ready) && $uid > 0): ?>
    <div class="col-md-6 mb-3">
        <label class="form-label">Días de vacaciones pendientes</label>
        <input type="text" class="form-control" readonly
               value="<?php echo vacation_format_days($vacation_total_pending ?? 0); ?>">
        <small class="text-muted">
            <a href="<?php echo URLROOT; ?>/vacationAdmin/vacationSetup/<?php echo $uid; ?>">Carga inicial / períodos</a>
            · <a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo $uid; ?>#tab-vacation">Ficha vacaciones</a>
        </small>
    </div>
    <?php elseif (!empty($show_legacy_vacation_days)): ?>
    <div class="col-md-6 mb-3">
        <label for="vacation_days_available" class="form-label">Días de vacaciones (manual)</label>
        <input type="number" name="vacation_days_available" id="vacation_days_available" class="form-control"
               value="<?php echo htmlspecialchars(is_object($src) ? ($src->vacation_days_available ?? 0) : ($src['vacation_days_available'] ?? 0)); ?>">
        <small class="text-muted">Legacy hasta activar saldos por período (#22).</small>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>
