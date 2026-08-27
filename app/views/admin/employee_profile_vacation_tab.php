<div class="tab-pane fade" id="tab-vacation">
    <?php if (empty($data['vacation_ready'])): ?>
    <div class="alert alert-warning small">
        Ejecutá las migraciones históricas de convenios y luego <code>migration_vacation_management_v2.sql</code> (ver <code>MIGRATIONS.md</code>).
    </div>
    <?php else:
        $vs = $data['vacation_summary'];
        $total = $vs['total_pending'] ?? 0;
    ?>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-0"><?php echo vacation_format_days($total); ?> <span class="text-muted fs-6">días pendientes (total)</span></h4>
            <p class="small text-muted mb-0">
                Antigüedad: <?php echo (int)($vs['seniority_months'] ?? 0); ?> meses
                <?php if (!empty($vs['agreement'])): ?> · Convenio <?php echo htmlspecialchars($vs['agreement']->code); ?><?php endif; ?>
                <?php if (!empty($data['user']->probation_start_date)): ?> · Prueba <?php echo date('d/m/Y', strtotime($data['user']->probation_start_date)); ?><?php endif; ?>
                <?php if (!empty($data['user']->hire_date)): ?> · Ingreso formal <?php echo date('d/m/Y', strtotime($data['user']->hire_date)); ?><?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo URLROOT; ?>/vacationAdmin/vacationSetup/<?php echo (int)$data['user']->id; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit me-1"></i>Carga / períodos
            </a>
        </div>
    </div>

    <?php if (empty($data['user']->hire_date)): ?>
    <div class="alert alert-danger small">Indicá la <strong>fecha de ingreso formal</strong> (en <a href="<?php echo URLROOT; ?>/admin/editUser/<?php echo (int)$data['user']->id; ?>">editar usuario</a> o carga de vacaciones) antes de liquidar.</div>
    <?php endif; ?>

    <div class="table-responsive border rounded mb-3">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr><th>Período</th><th>Tipo / unidad</th><th>Desde</th><th>Hasta</th><th>Corresponden</th><th>Ajuste</th><th>Tomados</th><th>Pendientes</th><th>Estado</th></tr>
            </thead>
            <tbody>
            <?php if (empty($vs['periods'])): ?>
            <tr><td colspan="9" class="text-muted text-center py-3">Sin períodos cargados. Usá «Carga / períodos».</td></tr>
            <?php else: foreach ($vs['periods'] as $p): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($p->period_label); ?></strong></td>
                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($p->balance_type ?? 'annual'); ?></span><div class="small text-muted"><?php echo htmlspecialchars($p->count_mode_snapshot ?? 'calendar'); ?></div><?php if(!empty($p->expires_at)): ?><div class="small text-warning">Vence <?php echo date('d/m/Y',strtotime($p->expires_at)); ?></div><?php endif; ?></td>
                <td><?php echo date('d/m/Y', strtotime($p->period_start)); ?></td>
                <td><?php echo date('d/m/Y', strtotime($p->period_end)); ?></td>
                <td><?php echo vacation_format_days($p->days_entitled); ?></td>
                <td><?php echo vacation_format_days($p->adjustment_days ?? 0); ?></td>
                <td><?php echo vacation_format_days($p->days_taken); ?></td>
                <td><strong><?php echo vacation_format_days(vacation_period_pending($p)); ?></strong></td>
                <td><?php echo $p->status === 'open' ? 'Abierto' : 'Cerrado'; ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/liquidateUser/<?php echo (int)$data['user']->id; ?>" class="d-inline">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-outline-secondary btn-sm" <?php echo empty($data['user']->hire_date) ? 'disabled' : ''; ?>>
            <i class="fas fa-sync me-1"></i>Liquidar período vigente
        </button>
    </form>
    <?php endif; ?>
</div>
