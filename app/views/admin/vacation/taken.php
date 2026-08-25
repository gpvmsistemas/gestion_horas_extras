<?php
require APPROOT . '/views/inc/header.php';
$filters = $data['filters'];
$tramos = $data['tramos'];
$tot = $data['totales'];
$estadoBadge = ['pasada' => 'text-bg-secondary', 'en_curso' => 'text-bg-success', 'futura' => 'text-bg-info'];
$estadoLabel = ['pasada' => 'Pasada', 'en_curso' => 'En curso', 'futura' => 'Futura'];
$fmt = fn($iso) => date('d/m/Y', strtotime($iso));
?>

<div class="admin-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-plane-departure"></i></div>
        <div class="admin-page-meta">
            <h1 class="page-title">Vacaciones tomadas</h1>
            <p class="page-subtitle mb-0">Cada tramo tomado por colaborador: fechas, días y si ya pasó, está en curso o es futuro.</p>
        </div>
    </div>
    <a href="<?php echo URLROOT; ?>/vacationAdmin/reports" class="btn btn-outline-primary btn-sm"><i class="fas fa-umbrella-beach me-1"></i>Saldos pendientes</a>
</div>

<div class="admin-kpi-grid vac-report-kpi-grid mb-3">
    <?php foreach ([
        ['Tramos', $tot['tramos']],
        ['Días tomados', rtrim(rtrim(number_format($tot['dias'], 1, '.', ''), '0'), '.')],
        ['En curso hoy', $tot['en_curso']],
        ['Futuras programadas', $tot['futuras']],
    ] as [$kl, $kv]): ?>
    <div class="admin-kpi-card"><div class="admin-kpi-value"><?php echo htmlspecialchars((string)$kv); ?></div><div class="admin-kpi-label"><?php echo $kl; ?></div></div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="get" action="<?php echo URLROOT; ?>/vacationAdmin/tomadas" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Colaborador</label>
                <input class="form-control form-control-sm" name="q" value="<?php echo htmlspecialchars($filters['q']); ?>" placeholder="Nombre…">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Empresa</label>
                <select class="form-select form-select-sm" name="company_id">
                    <option value="0">Todas</option>
                    <?php foreach ($data['companies'] as $c): ?>
                    <option value="<?php echo (int)$c->id; ?>" <?php echo (int)$filters['company_id'] === (int)$c->id ? 'selected' : ''; ?>><?php echo htmlspecialchars($c->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Período</label>
                <select class="form-select form-select-sm" name="anio">
                    <option value="">Todos</option>
                    <?php foreach ($data['anios'] as $a): ?>
                    <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $filters['anio'] === $a ? 'selected' : ''; ?>><?php echo htmlspecialchars($a); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Estado</label>
                <select class="form-select form-select-sm" name="estado">
                    <option value="">Todos</option>
                    <option value="pasadas" <?php echo $filters['estado'] === 'pasadas' ? 'selected' : ''; ?>>Pasadas</option>
                    <option value="en_curso" <?php echo $filters['estado'] === 'en_curso' ? 'selected' : ''; ?>>En curso</option>
                    <option value="futuras" <?php echo $filters['estado'] === 'futuras' ? 'selected' : ''; ?>>Futuras</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Aplicar</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo URLROOT; ?>/vacationAdmin/tomadas">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (!$tramos): ?>
        <p class="text-secondary mb-0">No hay tramos tomados con esos filtros. Los tramos aparecen al importar el informe de RRHH o al aprobar solicitudes de vacaciones.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Colaborador</th><th>Período</th><th>Desde</th><th>Hasta</th><th class="text-end">Días</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($tramos as $clave => $lista):
                    $nombre = explode('|', $clave)[0];
                    $uid = (int)($lista[0]->user_id ?? 0);
                    $subtotal = array_sum(array_map(fn($t) => $t->dias, $lista));
                    $n = count($lista);
                    $first = true;
                    foreach ($lista as $t): ?>
                <tr>
                    <?php if ($first): ?>
                    <td rowspan="<?php echo $n; ?>" class="align-top">
                        <strong><?php echo htmlspecialchars($nombre); ?></strong>
                        <small class="d-block text-secondary"><?php echo htmlspecialchars($t->company_name ?? ''); ?></small>
                        <small class="d-block text-secondary"><?php echo $n; ?> tramo<?php echo $n > 1 ? 's' : ''; ?> · <?php echo rtrim(rtrim(number_format($subtotal, 1, '.', ''), '0'), '.'); ?> días</small>
                        <a class="small" href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo $uid; ?>#tab-vacaciones">Ficha</a>
                    </td>
                    <?php $first = false; endif; ?>
                    <td><?php echo htmlspecialchars($t->period_label ?? '—'); ?><?php if ($t->source === 'import'): ?><small class="d-block text-secondary">importado</small><?php endif; ?></td>
                    <td><?php echo $fmt($t->desde); ?></td>
                    <td><?php echo $fmt($t->hasta); ?></td>
                    <td class="text-end fw-semibold"><?php echo rtrim(rtrim(number_format($t->dias, 1, '.', ''), '0'), '.'); ?></td>
                    <td><span class="badge <?php echo $estadoBadge[$t->estado]; ?>"><?php echo $estadoLabel[$t->estado]; ?></span></td>
                </tr>
                <?php endforeach; endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
