<?php require APPROOT . '/views/inc/header.php'; ?>

<?php
$clockMap  = $GLOBALS['CLOCK_DEVICE_MAP'] ?? [];
$filters   = $data['filters'];
$viewMode  = $data['view_mode'] ?? 'grouped';
$isGrouped = ($viewMode === 'grouped');

function marcacionesQueryString($filters, $viewMode, $overrides = []) {
    $q = array_merge([
        'start_date'  => $filters['start_date'],
        'end_date'    => $filters['end_date'],
        'device_name' => $filters['device_name'],
        'employee_id' => $filters['employee_id'],
        'person_q'    => $filters['person_q'],
        'mapped'      => $filters['mapped'],
        'direction'   => $filters['direction'],
        'branch_id'   => $filters['branch_id'] ?? 0,
        'view'        => $viewMode,
    ], $overrides);
    return http_build_query(array_filter($q, function ($v) {
        return $v !== null && $v !== '';
    }));
}
?>

<div class="admin-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-fingerprint"></i></div>
        <div class="admin-page-meta">
            <h1 class="page-title mb-0">Historial de fichadas</h1>
            <p class="page-subtitle mb-0">Auditoría de reloj, persona recibida y vínculo con el sistema</p>
        </div>
    </div>
    <div class="admin-page-actions">
        <a href="<?php echo URLROOT; ?>/admin/sync" class="btn btn-outline-primary">
            <i class="fas fa-sync-alt me-1"></i> Sincronizar
        </a>
        <a href="<?php echo URLROOT; ?>/admin/mapeoApi" class="btn btn-primary">
            <i class="fas fa-link me-1"></i> Mapear legajos
        </a>
    </div>
</div>

<div class="admin-kpi-grid marc-kpi-grid-5">
    <div class="admin-kpi-card">
        <div class="admin-kpi-icon is-total"><i class="fas fa-list"></i></div>
        <div>
            <div class="admin-kpi-value"><?php echo (int)$data['stats']['total']; ?></div>
            <div class="admin-kpi-label">Marcaciones</div>
        </div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-icon marc-kpi-persons"><i class="fas fa-user-friends"></i></div>
        <div>
            <div class="admin-kpi-value"><?php echo (int)($data['stats']['persons'] ?? 0); ?></div>
            <div class="admin-kpi-label">Personas</div>
        </div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-icon marc-kpi-in"><i class="fas fa-sign-in-alt"></i></div>
        <div>
            <div class="admin-kpi-value"><?php echo (int)$data['stats']['entrada']; ?></div>
            <div class="admin-kpi-label">Entradas</div>
        </div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-icon marc-kpi-out"><i class="fas fa-sign-out-alt"></i></div>
        <div>
            <div class="admin-kpi-value"><?php echo (int)$data['stats']['salida']; ?></div>
            <div class="admin-kpi-label">Salidas</div>
        </div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-icon marc-kpi-unmapped"><i class="fas fa-user-slash"></i></div>
        <div>
            <div class="admin-kpi-value"><?php echo (int)$data['stats']['unmapped']; ?></div>
            <div class="admin-kpi-label">Sin mapear</div>
        </div>
    </div>
</div>

<div class="admin-toolbar marc-filter-panel mb-3">
    <div class="marc-filter-heading"><div><span class="admin-section-eyebrow">Búsqueda avanzada</span><strong>Filtros de auditoría</strong></div><i class="fas fa-sliders-h"></i></div>
    <form method="get" action="<?php echo URLROOT; ?>/admin/marcacionesTodas" class="w-100">
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewMode); ?>">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg-3">
                <label class="admin-toolbar-label d-block mb-1"><i class="fas fa-user me-1"></i>Persona</label>
                <input type="search" name="person_q" class="form-control form-control-sm marc-filter-person"
                       placeholder="Nombre o legajo..."
                       value="<?php echo htmlspecialchars($filters['person_q']); ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="admin-toolbar-label d-block mb-1">Desde</label>
                <input type="date" name="start_date" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($filters['start_date']); ?>">
            </div>
            <?php if (!empty($data['branches'])): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="admin-toolbar-label d-block mb-1">Sucursal</label>
                <select name="branch_id" class="form-select form-select-sm">
                    <option value="0">Todas</option>
                    <?php foreach ($data['branches'] as $branch): ?>
                    <option value="<?php echo (int)$branch->id; ?>" <?php echo (int)($filters['branch_id'] ?? 0) === (int)$branch->id ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="admin-toolbar-label d-block mb-1">Hasta</label>
                <input type="date" name="end_date" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($filters['end_date']); ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="admin-toolbar-label d-block mb-1">Reloj</label>
                <select name="device_name" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($data['devices'] as $dev): ?>
                        <?php if (!$dev->device_name) continue; ?>
                        <?php $cfg = $clockMap[$dev->device_name] ?? null; ?>
                        <option value="<?php echo htmlspecialchars($dev->device_name); ?>"
                            <?php echo ($filters['device_name'] === $dev->device_name) ? 'selected' : ''; ?>>
                            <?php echo $cfg ? $cfg['label'] : htmlspecialchars($dev->device_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-1">
                <label class="admin-toolbar-label d-block mb-1">Sistema</label>
                <select name="mapped" class="form-select form-select-sm">
                    <option value="" <?php echo $filters['mapped'] === '' ? 'selected' : ''; ?>>Todos</option>
                    <option value="yes" <?php echo $filters['mapped'] === 'yes' ? 'selected' : ''; ?>>Mapeados</option>
                    <option value="no" <?php echo $filters['mapped'] === 'no' ? 'selected' : ''; ?>>Sin mapear</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2 d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="fas fa-filter me-1"></i>Filtrar
                </button>
                <a href="<?php echo URLROOT; ?>/admin/marcacionesTodas" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </div>
    </form>
</div>

<div class="admin-surface">
    <div class="admin-surface-head">
        <h3 class="admin-surface-title mb-0">
            <i class="fas fa-table"></i>
            <?php echo $isGrouped ? 'Resumen por persona y día' : 'Listado detallado'; ?>
        </h3>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="btn-group marc-view-tabs" role="group">
                <a href="?<?php echo marcacionesQueryString($filters, 'grouped'); ?>"
                   class="btn btn-sm <?php echo $isGrouped ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    <i class="fas fa-layer-group me-1"></i>Agrupado
                </a>
                <a href="?<?php echo marcacionesQueryString($filters, 'list'); ?>"
                   class="btn btn-sm <?php echo !$isGrouped ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    <i class="fas fa-list me-1"></i>Detalle
                </a>
            </div>
            <span class="badge bg-secondary">
                <?php echo $isGrouped ? count($data['groups']) . ' jornadas' : count($data['marcaciones']) . ' registros'; ?>
            </span>
        </div>
    </div>

    <div class="admin-surface-body is-tight">
        <?php if (empty($data['marcaciones'])): ?>
        <div class="admin-empty py-5">
            <i class="fas fa-inbox"></i>
            <p class="mb-2">Sin marcaciones para los filtros seleccionados.</p>
            <p class="text-muted small mb-3 mx-auto" style="max-width:36rem;">
                <?php $compact = true; require APPROOT . '/views/admin/partials/api_sync_help.php'; ?>
            </p>
            <a href="<?php echo URLROOT; ?>/admin/sync" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-sync-alt me-1"></i>Sincronizar desde API
            </a>
        </div>
        <?php elseif ($isGrouped): ?>

        <div class="table-responsive">
            <table class="table table-hover mb-0 marc-group-table" id="tbl-marcaciones-grouped">
                <thead>
                    <tr>
                        <th style="width:28px"></th>
                        <th>Persona</th>
                        <th>Fecha</th>
                        <th>Entradas</th>
                        <th>Salidas</th>
                        <th>Jornada</th>
                        <th>Reloj</th>
                        <th>Empresa / sucursal</th>
                        <th>Sistema</th>
                        <th style="width:70px" class="text-center">Marcas</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['groups'] as $gi => $g):
                    $label = marcPersonLabel($g['person_name'], $g['employee_id'], $g['full_name']);
                    $summary = $g['summary'] ?? [];
                    $hours = marcGroupHoursLabel($g);
                    $primaryIn  = marcGroupPrimaryTime($summary['entry_time'] ?? null);
                    $primaryOut = marcGroupPrimaryTime($summary['exit_time'] ?? null);
                    $useAdjust  = marcGroupUsesFirstLast($g);
                    $rowId = 'marc-g-' . $gi;
                    $isUnmapped = empty($g['user_id']);
                ?>
                <tr class="marc-group-row <?php echo $isUnmapped ? 'is-unmapped' : ''; ?>"
                    data-bs-toggle="collapse" data-bs-target="#<?php echo $rowId; ?>"
                    aria-expanded="false">
                    <td class="text-muted"><i class="fas fa-chevron-right marc-chevron small"></i></td>
                    <td>
                        <?php if ($g['user_id']): ?>
                            <a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo (int)$g['user_id']; ?>"
                               class="fw-semibold text-decoration-none" onclick="event.stopPropagation();">
                                <?php echo htmlspecialchars($label); ?>
                            </a>
                        <?php else: ?>
                            <span class="fw-semibold"><?php echo htmlspecialchars($label); ?></span>
                        <?php endif; ?>
                        <div class="small text-muted">ID <?php echo htmlspecialchars($g['employee_id']); ?></div>
                    </td>
                    <td class="text-nowrap">
                        <?php echo date('d/m/Y', strtotime($g['date'])); ?>
                        <?php if ($useAdjust): ?>
                            <span class="badge bg-warning text-dark ms-1" title="Marcas desbalanceadas: 1ª entrada y última salida">Ajuste</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (empty($g['entradas'])): ?>
                            <span class="text-muted">—</span>
                        <?php else: ?>
                            <?php foreach ($g['entradas'] as $t):
                                $isPrimary = marcTimeChipIsPrimary($t, $primaryIn);
                            ?>
                                <span class="marc-time-chip is-in<?php echo $isPrimary ? ' is-primary' : ''; ?>"
                                      <?php if ($isPrimary): ?>title="Usada para el cálculo"<?php endif; ?>>
                                    <i class="fas fa-sign-in-alt"></i><?php echo $t; ?>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (empty($g['salidas'])): ?>
                            <span class="text-muted">—</span>
                        <?php else: ?>
                            <?php foreach ($g['salidas'] as $t):
                                $isPrimary = marcTimeChipIsPrimary($t, $primaryOut);
                            ?>
                                <span class="marc-time-chip is-out<?php echo $isPrimary ? ' is-primary' : ''; ?>"
                                      <?php if ($isPrimary): ?>title="Usada para el cálculo"<?php endif; ?>>
                                    <i class="fas fa-sign-out-alt"></i><?php echo $t; ?>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($hours): ?>
                            <span class="marc-hours-pill"><?php echo $hours; ?></span>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td onclick="event.stopPropagation();"><?php echo marcClockBadge($g['device_name'], $clockMap); ?></td>
                    <td class="small"><?php $scope = $g['events'][0] ?? null; echo htmlspecialchars(($scope->company_name ?? 'Sin asignar') . (!empty($scope->employee_branch_name) ? ' · ' . $scope->employee_branch_name : (!empty($scope->clock_branch_names) ? ' · ' . $scope->clock_branch_names : ''))); ?></td>
                    <td onclick="event.stopPropagation();"><?php echo marcMappingBadge($g['user_id']); ?></td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border"><?php echo count($g['events']); ?></span>
                    </td>
                </tr>
                <tr class="marc-detail-row">
                    <td colspan="10" class="p-0">
                        <div class="collapse" id="<?php echo $rowId; ?>">
                            <div class="marc-detail-inner">
                                <table class="table table-sm table-borderless">
                                    <thead>
                                        <tr class="text-muted">
                                            <th>Hora</th>
                                            <th>Tipo</th>
                                            <th>ID evento</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    usort($g['events'], function ($a, $b) {
                                        return strcmp($a->event_time, $b->event_time);
                                    });
                                    foreach ($g['events'] as $ev):
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo date('H:i:s', strtotime($ev->event_time)); ?></td>
                                        <td><?php echo marcDirectionBadge($ev->direction ?? null, $ev->direction_label ?? null); ?></td>
                                        <td><code class="small text-muted"><?php echo htmlspecialchars($ev->event_serial_no ?? ''); ?></code></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>

        <div class="table-responsive">
            <table class="table table-hover mb-0 admin-table" id="tbl-marcaciones-todas" style="width:100%">
                <thead>
                    <tr>
                        <th>Persona</th>
                        <th>Sistema</th>
                        <th>Reloj</th>
                        <th>Empresa / sucursal</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Tipo</th>
                        <th>ID reloj</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['marcaciones'] as $m): ?>
                <tr class="<?php echo empty($m->user_id) ? 'table-warning' : ''; ?>">
                    <td>
                        <?php if ($m->user_id): ?>
                            <a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo $m->user_id; ?>"
                               class="fw-semibold text-decoration-none">
                                <?php echo htmlspecialchars($m->full_name); ?>
                            </a>
                        <?php else: ?>
                            <span class="fw-semibold"><?php echo htmlspecialchars(marcPersonLabel($m->person_name ?? '', $m->employee_id ?? '')); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo marcMappingBadge($m->user_id); ?></td>
                    <td><?php echo marcClockBadge($m->device_name, $clockMap); ?></td>
                    <td class="small"><div><?php echo htmlspecialchars($m->company_name ?? 'Sin asignar'); ?></div><div class="text-muted"><?php echo htmlspecialchars(($m->employee_branch_name ?? '') ?: ($m->clock_branch_names ?? '')); ?></div></td>
                    <td><?php echo date('d/m/Y', strtotime($m->event_time)); ?></td>
                    <td><strong><?php echo date('H:i:s', strtotime($m->event_time)); ?></strong></td>
                    <td><?php echo marcDirectionBadge($m->direction ?? null, $m->direction_label ?? null); ?></td>
                    <td><code class="small"><?php echo htmlspecialchars($m->employee_id ?? '—'); ?></code></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function(){
    document.querySelectorAll('.marc-group-row').forEach(function(row) {
        var target = row.getAttribute('data-bs-target');
        if (!target) return;
        var el = document.querySelector(target);
        if (!el) return;
        el.addEventListener('show.bs.collapse', function() {
            row.classList.add('is-expanded');
            var icon = row.querySelector('.marc-chevron');
            if (icon) { icon.classList.remove('fa-chevron-right'); icon.classList.add('fa-chevron-down'); }
        });
        el.addEventListener('hide.bs.collapse', function() {
            row.classList.remove('is-expanded');
            var icon = row.querySelector('.marc-chevron');
            if (icon) { icon.classList.add('fa-chevron-right'); icon.classList.remove('fa-chevron-down'); }
        });
    });

    if ($('#tbl-marcaciones-todas').length) {
        $('#tbl-marcaciones-todas').DataTable({
            pageLength: 50,
            order: [[3,'desc'],[4,'desc']],
            language: window.DATATABLES_LANG_ES || { url: '<?php echo URLROOT; ?>/js/datatables-es-ES.json' },
            columnDefs: [{ targets: [1,2,5], orderable: false }]
        });
    }

});
</script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
