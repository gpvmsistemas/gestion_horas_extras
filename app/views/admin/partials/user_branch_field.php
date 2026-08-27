<?php
/** Sucursales operativas; un empleado puede trabajar en varias sedes. */
$branchUserModel = new User();
if (!$branchUserModel->isBranchAssignmentReady()) return;
$branchCompanyModel = new Company();
$branchCompanies = $data['companies'] ?? $branchCompanyModel->getAllCompanies();
$selectedBranchIds = isset($data['branch_ids'])
    ? array_map('intval', (array)$data['branch_ids'])
    : array_map(function ($branch) { return (int)$branch->id; }, $branchUserModel->getBranchAssignmentsForUser((int)($data['user']->id ?? 0)));
$primaryBranchId = isset($data['branch_id']) ? (int)$data['branch_id'] : (int)($data['user']->branch_id ?? 0);
$branchesByCompany = [];
foreach ($branchCompanies as $branchCompany) {
    $branchesByCompany[(int)$branchCompany->id] = array_map(function ($branch) {
        return ['id'=>(int)$branch->id, 'name'=>(string)$branch->name, 'locality'=>(string)$branch->locality, 'province'=>(string)$branch->province];
    }, $branchCompanyModel->getBranches((int)$branchCompany->id, false));
}
?>
<fieldset class="mb-3" id="user-branch-field">
    <legend class="form-label mb-1">Sucursales / sedes operativas</legend>
    <p class="small text-muted mb-2">Marcá todas las sedes donde trabaja. Definí una principal para vacaciones y reglas generales.</p>
    <div id="branch-options" class="border rounded-3 p-2 bg-light" aria-live="polite"></div>
    <?php if (isset($data['errors']['branch_id'])): ?><div class="text-danger small mt-1"><?php echo htmlspecialchars($data['errors']['branch_id']); ?></div><?php endif; ?>
</fieldset>
<script>
(function () {
    var optionsByCompany = <?php echo json_encode($branchesByCompany, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var selected = <?php echo json_encode(array_values($selectedBranchIds)); ?>;
    var primary = <?php echo (int)$primaryBranchId; ?>;
    var company = document.getElementById('company_id'), target = document.getElementById('branch-options');
    if (!company || !target) return;
    function render(keep) {
        var rows = optionsByCompany[company.value] || [];
        var chosen = keep ? selected : [];
        target.innerHTML = rows.length ? '' : '<span class="small text-muted">Esta empresa no tiene sucursales activas.</span>';
        rows.forEach(function (row) {
            var checked = chosen.indexOf(row.id) !== -1;
            var line = document.createElement('div'); line.className = 'd-flex align-items-center gap-2 py-1';
            line.innerHTML = '<input class="form-check-input m-0 branch-check" type="checkbox" id="branch-' + row.id + '" name="branch_ids[]" value="' + row.id + '"' + (checked ? ' checked' : '') + '>'
                + '<label class="form-check-label flex-grow-1" for="branch-' + row.id + '"><strong>' + row.name + '</strong><span class="text-muted small"> — ' + row.locality + ', ' + row.province + '</span></label>'
                + '<label class="small text-muted mb-0"><input class="form-check-input me-1 branch-primary" type="radio" name="branch_id" value="' + row.id + '"' + (primary === row.id ? ' checked' : '') + '>Principal</label>';
            target.appendChild(line);
        });
        target.querySelectorAll('.branch-check').forEach(function (check) {
            check.addEventListener('change', function () {
                var radio = target.querySelector('.branch-primary[value="' + check.value + '"]');
                if (!check.checked && radio) radio.checked = false;
                if (check.checked && !target.querySelector('.branch-primary:checked') && radio) radio.checked = true;
            });
        });
        target.querySelectorAll('.branch-primary').forEach(function (radio) {
            radio.addEventListener('change', function () {
                var check = target.querySelector('.branch-check[value="' + radio.value + '"]'); if (check) check.checked = true;
            });
        });
    }
    render(true); company.addEventListener('change', function () { primary = 0; render(false); });
}());
</script>
