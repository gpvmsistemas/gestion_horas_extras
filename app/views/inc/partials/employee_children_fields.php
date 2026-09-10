<?php
/**
 * Formulario de hijos/as (admin y portal empleado).
 *
 * Variables:
 * - $childrenUi: 'admin' | 'employee'
 * - $employeeChildrenReady (bool)
 * - $hasChildren (bool)
 * - $employeeChildren: filas con birth_date, sex
 * - $personalReady (bool, fallback legacy)
 * - $pf callable (admin)
 */
$childrenUi = ($childrenUi ?? 'admin') === 'employee' ? 'employee' : 'admin';
$employeeChildrenReady = !empty($employeeChildrenReady);
$hasChildren = !empty($hasChildren);
$employeeChildren = $employeeChildren ?? [];
$isEmployeeUi = $childrenUi === 'employee';
$inputClass = $isEmployeeUi ? 'emp-input' : 'form-control';
$selectClass = $isEmployeeUi ? 'emp-input' : 'form-select';
$labelClass = $isEmployeeUi ? 'emp-label' : 'form-label';
$groupClass = $isEmployeeUi ? 'emp-form-group' : 'mb-3';
$sexOpts = EmployeeChild::sexOptions();

$normalizeChildRow = static function ($row) {
    if (is_object($row)) {
        return [
            'birth_date' => isset($row->birth_date) ? substr((string)$row->birth_date, 0, 10) : '',
            'sex' => strtoupper(trim((string)($row->sex ?? ''))),
        ];
    }
    if (is_array($row)) {
        return [
            'birth_date' => trim((string)($row['birth_date'] ?? '')),
            'sex' => strtoupper(trim((string)($row['sex'] ?? ''))),
        ];
    }
    return ['birth_date' => '', 'sex' => ''];
};

$renderChildRow = static function ($index, $row, $isTemplate = false) use ($sexOpts, $inputClass, $selectClass, $labelClass, $groupClass, $isEmployeeUi, $normalizeChildRow) {
    $row = $normalizeChildRow($row);
    $birthVal = $row['birth_date'];
    if ($birthVal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $birthVal)) {
        $birthVal = substr($birthVal, 0, 10);
    }
    $ageLabel = function_exists('employee_child_age_label') ? employee_child_age_label($birthVal) : '';
    $tplAttr = $isTemplate ? ' data-child-row-template hidden' : '';
    $namePrefix = $isTemplate ? 'children_rows[__INDEX__]' : 'children_rows[' . (int)$index . ']';
    ?>
    <div class="employee-child-row<?php echo $isEmployeeUi ? '' : ' row align-items-end g-2'; ?>"<?php echo $tplAttr; ?>>
        <?php if ($isEmployeeUi): ?>
        <div class="<?php echo $groupClass; ?>">
            <label class="<?php echo $labelClass; ?>">Sexo</label>
            <select name="<?php echo $namePrefix; ?>[sex]" class="<?php echo $selectClass; ?> employee-child-sex" autocomplete="off">
                <option value="">— Elegir —</option>
                <?php foreach ($sexOpts as $val => $label): ?>
                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $row['sex'] === $val ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="<?php echo $groupClass; ?>">
            <label class="<?php echo $labelClass; ?>">Fecha de nacimiento</label>
            <input type="date" name="<?php echo $namePrefix; ?>[birth_date]" class="<?php echo $inputClass; ?> employee-child-birth"
                   value="<?php echo htmlspecialchars($birthVal); ?>" max="<?php echo date('Y-m-d'); ?>" autocomplete="bday">
        </div>
        <div class="<?php echo $groupClass; ?> employee-child-age-wrap d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="employee-child-age-label small text-muted"></span>
            <button type="button" class="emp-btn-outline emp-btn-compact employee-child-remove">Quitar</button>
        </div>
        <?php else: ?>
        <div class="col-md-4">
            <label class="<?php echo $labelClass; ?>">Sexo</label>
            <select name="<?php echo $namePrefix; ?>[sex]" class="<?php echo $selectClass; ?> employee-child-sex" autocomplete="off">
                <option value="">— Elegir —</option>
                <?php foreach ($sexOpts as $val => $label): ?>
                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $row['sex'] === $val ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="<?php echo $labelClass; ?>">Fecha de nacimiento</label>
            <input type="date" name="<?php echo $namePrefix; ?>[birth_date]" class="<?php echo $inputClass; ?> employee-child-birth"
                   value="<?php echo htmlspecialchars($birthVal); ?>" max="<?php echo date('Y-m-d'); ?>" autocomplete="bday">
        </div>
        <div class="col-md-4">
            <div class="employee-child-age-wrap mb-2">
                <span class="employee-child-age-label small text-muted d-block"><?php echo $ageLabel !== '' ? 'Edad: ' . htmlspecialchars($ageLabel) : ''; ?></span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger employee-child-remove">Quitar</button>
        </div>
        <?php endif; ?>
    </div>
    <?php
};
?>

<?php if ($employeeChildrenReady): ?>
<div class="employee-children-block mt-2" data-children-ui="<?php echo htmlspecialchars($childrenUi); ?>">
    <h6 class="<?php echo $isEmployeeUi ? 'emp-section-title mb-2' : 'mb-2 text-muted small text-uppercase'; ?>">
        <i class="fas fa-child me-1"></i> Hijos/as
    </h6>

    <div class="<?php echo $groupClass; ?> form-check">
        <input class="form-check-input" type="checkbox" name="has_children" id="has_children" value="1" <?php echo $hasChildren ? 'checked' : ''; ?>>
        <label class="form-check-label" for="has_children">Tengo hijos/as</label>
    </div>

    <div class="employee-children-panel<?php echo $hasChildren ? '' : ' d-none'; ?>" id="employeeChildrenPanel">
        <p class="small text-muted employee-children-count-label mb-2">
            <?php
            $count = count($employeeChildren);
            echo $count === 1 ? '1 hijo/a registrado' : $count . ' hijos/as registrados';
            ?>
        </p>

        <?php if (!empty(($data ?? [])['errors']['children'])): ?>
        <div class="alert alert-danger small py-2"><?php echo htmlspecialchars($data['errors']['children']); ?></div>
        <?php endif; ?>

        <div class="employee-children-rows">
            <?php if ($employeeChildren !== []): ?>
                <?php foreach ($employeeChildren as $i => $child): ?>
                    <?php $renderChildRow($i, $child); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php $renderChildRow(0, ['birth_date' => '', 'sex' => ''], true); ?>

        <button type="button" class="<?php echo $isEmployeeUi ? 'emp-btn-outline employee-child-add' : 'btn btn-sm btn-outline-primary employee-child-add'; ?> mt-2" id="employeeChildAddBtn">
            <i class="fas fa-plus me-1"></i>Agregar hijo/a
        </button>

        <?php if ($employeeChildren !== []): ?>
        <ul class="list-unstyled small mt-3 mb-0 employee-children-summary">
            <?php foreach ($employeeChildren as $child):
                $child = $normalizeChildRow($child);
                if ($child['birth_date'] === '') continue;
            ?>
            <li class="mb-1">
                <strong><?php echo htmlspecialchars(employee_child_sex_label($child['sex'])); ?></strong>
                <?php echo htmlspecialchars(employee_child_birth_display($child['birth_date'])); ?>
                <?php $age = employee_child_age_label($child['birth_date']); if ($age !== ''): ?>
                <span class="text-muted">(<?php echo htmlspecialchars($age); ?>)</span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
<script src="<?php echo URLROOT; ?>/js/employee-children-form.js?v=<?php echo (int)@filemtime(APPROOT . '/../public/js/employee-children-form.js'); ?>" defer></script>
<?php elseif (!empty($personalReady)): ?>
<div class="row">
    <div class="col-md-2 mb-3">
        <label for="children_count" class="<?php echo $labelClass; ?>">Hijos</label>
        <input type="number" name="children_count" id="children_count" class="<?php echo $inputClass; ?>" min="0" max="20"
               value="<?php echo htmlspecialchars((string)(is_callable($pf ?? null) ? $pf('children_count') : '')); ?>" autocomplete="off">
    </div>
</div>
<?php endif; ?>
