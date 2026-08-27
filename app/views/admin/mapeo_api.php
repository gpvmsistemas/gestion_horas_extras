<?php require APPROOT . '/views/inc/header.php'; ?>

<?php
$clockMap = $GLOBALS['CLOCK_DEVICE_MAP'] ?? [];
function mapeoClockBadge($devices, $clockMap) {
    if (empty($devices)) return '<span class="badge bg-secondary">—</span>';
    $out = [];
    foreach ($devices as $d) {
        $cfg   = $clockMap[$d] ?? null;
        $label = $cfg ? $cfg['label'] : htmlspecialchars($d);
        $color = $cfg ? $cfg['badge'] : 'secondary';
        $out[] = "<span class=\"badge bg-{$color}\">{$label}</span>";
    }
    return implode(' ', $out);
}
?>

<div class="d-flex align-items-center flex-wrap gap-3 mb-4">
    <div style="background:var(--clr-primary-l);border-radius:1rem;padding:.75rem 1rem;">
        <i class="fas fa-link fa-lg" style="color:var(--clr-primary);"></i>
    </div>
    <div>
        <h2 class="page-title mb-0">Mapeo de Empleados</h2>
        <p class="page-subtitle mb-0">Asocia cada ID del reloj con un usuario del sistema</p>
    </div>
    <a href="<?php echo URLROOT; ?>/admin/sync" class="btn btn-outline-secondary ms-auto btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Volver a Sync
    </a>
</div>

<?php if (!empty($data['company_name'])): ?>
<p class="small text-muted mb-3">
    Empresa activa: <strong><?php echo htmlspecialchars($data['company_name']); ?></strong>.
    Los usuarios del desplegable son solo de esta empresa. Si no encontrás a alguien, revisá el selector de empresa arriba a la derecha.
</p>
<?php endif; ?>

<!-- Buscar usuario en el sistema -->
<div class="card shadow mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-user-search me-2"></i>Buscar en usuarios del sistema</h5></div>
    <div class="card-body">
        <form method="get" action="<?php echo URLROOT; ?>/admin/mapeoApi" class="row g-2 align-items-end">
            <?php if ($data['consulted']): ?>
            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($data['start_date']); ?>">
            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($data['end_date']); ?>">
            <?php endif; ?>
            <div class="col-md-8">
                <label class="form-label small mb-1">Nombre (ej. Duarte, Milagros)</label>
                <input type="search" name="buscar_usuario" class="form-control"
                       value="<?php echo htmlspecialchars($data['buscar_usuario'] ?? ''); ?>"
                       placeholder="Buscar por nombre o usuario...">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-primary w-100">Buscar</button>
            </div>
        </form>
        <?php if (!empty($data['buscar_usuario'])): ?>
            <?php if (empty($data['usuarios_busqueda'])): ?>
            <p class="text-muted small mt-3 mb-0">No hay usuarios con ese nombre en ninguna empresa.</p>
            <?php else: ?>
            <div class="table-responsive mt-3">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Usuario sistema</th><th>Empresa</th><th>IDs reloj actuales</th><th>Asignar ID manual</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['usuarios_busqueda'] as $su):
                        $maps = (new User())->getClockMappingsForUser($su->id);
                        $mapLabel = empty($maps) ? '—' : implode(', ', array_map(function ($cid) { return (string)$cid; }, array_values($maps)));
                        $enEmpresaActiva = (int)$su->company_id === (int)($data['company_id'] ?? 0);
                    ?>
                    <tr class="<?php echo $enEmpresaActiva ? '' : 'table-secondary'; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($su->full_name); ?></strong>
                            <?php if (!$enEmpresaActiva): ?>
                            <span class="badge bg-secondary ms-1">otra empresa</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?php echo htmlspecialchars($su->company_name ?? '—'); ?></td>
                        <td class="small"><code><?php echo htmlspecialchars($mapLabel); ?></code></td>
                        <td>
                            <?php if ($enEmpresaActiva): ?>
                            <form method="post" action="<?php echo URLROOT; ?>/admin/saveMappingFromApi" class="d-flex gap-1">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="user_id" value="<?php echo (int)$su->id; ?>">
                                <input type="hidden" name="device_name" value="API">
                                <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($data['start_date']); ?>">
                                <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($data['end_date']); ?>">
                                <input type="text" name="employee_id" class="form-control form-control-sm" placeholder="ID reloj" required style="max-width:100px;">
                                <button type="submit" class="btn btn-sm btn-success" title="Guardar"><i class="fas fa-link"></i></button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted small">Cambiá la empresa activa para mapear</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── CONSULTAR API ── -->
<div class="card shadow mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="fas fa-search" style="color:var(--clr-primary);"></i>
        <h5 class="mb-0">Consultar empleados en la API</h5>
    </div>
    <div class="card-body">
        <div class="alert mb-3 d-flex gap-2 align-items-start"
             style="background:var(--clr-primary-xl,#fff0f9);border-left:4px solid var(--clr-primary);border-radius:.5rem;">
            <i class="fas fa-info-circle mt-1 flex-shrink-0" style="color:var(--clr-primary);"></i>
            <div class="small">
                La tabla inferior muestra IDs del reloj con fichadas en el rango <strong>y</strong> legajos del catálogo API (aunque no hayan fichado).<br>
                Los nombres del reloj suelen venir como <code>nombreApellido</code> (a veces apellido primero: «Duarte Milagros» vs «Milagros Duarte» en el sistema).<br>
                Si no ves a alguien, ampliá las fechas, usá <strong>Buscar en usuarios del sistema</strong> arriba, o asigná el ID manualmente.<br>
                <strong>Un usuario puede tener múltiples IDs</strong> (uno por reloj / empresa).
            </div>
        </div>
        <form method="post" action="<?php echo URLROOT; ?>/admin/mapeoApi" class="row g-3 align-items-end">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="consultar" value="1">
            <div class="col-sm-6 col-md-4">
                <label class="form-label fw-semibold">Desde</label>
                <input type="date" name="start_date" class="form-control"
                       value="<?php echo htmlspecialchars($data['start_date']); ?>" required>
            </div>
            <div class="col-sm-6 col-md-4">
                <label class="form-label fw-semibold">Hasta</label>
                <input type="date" name="end_date" class="form-control"
                       value="<?php echo htmlspecialchars($data['end_date']); ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <button type="submit" class="btn fw-bold text-white w-100"
                        style="background:linear-gradient(135deg,var(--clr-primary),#c2185b);">
                    <i class="fas fa-search me-1"></i> Consultar API
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($data['error']): ?>
    <div class="alert alert-danger d-flex gap-2 align-items-center">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($data['error']); ?>
    </div>
<?php endif; ?>

<?php if ($data['consulted'] && !$data['error']): ?>
<!-- ── RESULTADOS ── -->
<div class="card shadow">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="fas fa-users" style="color:var(--clr-primary);"></i>
        <h5 class="mb-0">Empleados encontrados en la API</h5>
        <span class="badge ms-auto" style="background:var(--clr-primary);">
            <?php echo count($data['employees']); ?> únicos
        </span>
        <?php
        $sinMapear = array_filter($data['employees'], function($e){ return !$e['mapped_user']; });
        if (count($sinMapear)): ?>
            <span class="badge bg-warning text-dark"><?php echo count($sinMapear); ?> sin mapear</span>
        <?php endif; ?>
    </div>

    <?php if (empty($data['employees'])): ?>
    <div class="card-body text-center py-5 text-muted">
        <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
        La API no devolvió marcaciones para el período seleccionado.
    </div>
    <?php else: ?>
    <div class="px-3 pt-3">
        <input type="search" id="mapeo-filtro-nombre" class="form-control form-control-sm"
               placeholder="Filtrar tabla por nombre o ID de reloj..." style="max-width:320px;">
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="mapeo-tabla-api">
            <thead>
                <tr>
                    <th style="width:90px">ID Reloj</th>
                    <th>Nombre en Reloj</th>
                    <th>Dispositivo(s)</th>
                    <th style="width:80px" class="text-center">Marcaciones</th>
                    <th style="width:110px">Última vez</th>
                    <th style="width:240px">Mapeado a</th>
                    <th style="width:50px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data['employees'] as $emp):
                $filtroTexto = strtolower(($emp['employeeID'] ?? '') . ' ' . ($emp['personName'] ?? ''));
            ?>
            <tr class="mapeo-fila <?php echo $emp['mapped_user'] ? '' : 'table-warning'; ?>"
                data-mapeo-filtro="<?php echo htmlspecialchars($filtroTexto); ?>">
                <!-- ID Reloj -->
                <td>
                    <code class="fw-bold" style="font-size:.95rem;color:var(--clr-primary);">
                        <?php echo htmlspecialchars($emp['employeeID']); ?>
                    </code>
                </td>
                <!-- Nombre -->
                <td class="fw-semibold">
                    <?php echo htmlspecialchars(marcPersonLabel($emp['personName'] ?? '', $emp['employeeID'] ?? '')); ?>
                    <?php if (!empty($emp['legajo_only'])): ?>
                    <span class="badge bg-light text-muted border ms-1" title="Sin fichadas en el período; aparece por catálogo de legajos">sin fichadas</span>
                    <?php endif; ?>
                    <?php if (!empty($emp['suggestions']) && empty($emp['mapped_user'])): ?>
                    <div class="small text-muted mt-1">
                        ¿Coincide con
                        <?php foreach ($emp['suggestions'] as $i => $sug): ?>
                            <?php if ($i > 0) echo ', '; ?>
                            <strong><?php echo htmlspecialchars($sug['user']->full_name); ?></strong>
                        <?php endforeach; ?>?
                    </div>
                    <?php endif; ?>
                </td>
                <!-- Dispositivos -->
                <td><?php echo mapeoClockBadge($emp['devices'], $clockMap); ?></td>
                <!-- Conteo -->
                <td class="text-center">
                    <span class="badge bg-light text-dark border"><?php echo $emp['count']; ?></span>
                </td>
                <!-- Última -->
                <td class="text-muted small">
                    <?php echo $emp['lastSeen'] ? date('d/m/Y', strtotime($emp['lastSeen'])) : '—'; ?>
                </td>
                <!-- Mapeo actual / formulario asignación -->
                <td>
                    <?php if ($emp['mapped_user']): ?>
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-check-circle text-success fa-sm"></i>
                            <a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo $emp['mapped_user']->id; ?>"
                               class="fw-semibold text-decoration-none" style="color:var(--clr-primary);">
                                <?php echo htmlspecialchars($emp['mapped_user']->full_name); ?>
                            </a>
                        </div>
                        <!-- Re-mapear -->
                        <form method="post" action="<?php echo URLROOT; ?>/admin/saveMappingFromApi"
                              class="d-flex gap-1 mt-1">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($emp['employeeID']); ?>">
                            <input type="hidden" name="device_name" value="<?php echo htmlspecialchars(implode(',', $emp['devices'])); ?>">
                            <input type="hidden" name="start_date"  value="<?php echo htmlspecialchars($data['start_date']); ?>">
                            <input type="hidden" name="end_date"    value="<?php echo htmlspecialchars($data['end_date']); ?>">
                            <select name="user_id" class="form-select form-select-sm" style="font-size:.75rem;">
                                <option value="">Cambiar a...</option>
                                <?php foreach ($data['all_users'] as $u): ?>
                                    <option value="<?php echo $u->id; ?>"
                                        <?php echo ($u->id == $emp['mapped_user']->id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u->full_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Guardar">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- Sin mapeo — formulario principal -->
                        <form method="post" action="<?php echo URLROOT; ?>/admin/saveMappingFromApi"
                              class="d-flex gap-1">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($emp['employeeID']); ?>">
                            <input type="hidden" name="device_name" value="<?php echo htmlspecialchars(implode(',', $emp['devices'])); ?>">
                            <input type="hidden" name="start_date"  value="<?php echo htmlspecialchars($data['start_date']); ?>">
                            <input type="hidden" name="end_date"    value="<?php echo htmlspecialchars($data['end_date']); ?>">
                            <select name="user_id" class="form-select form-select-sm" style="font-size:.75rem;" required>
                                <option value="">Seleccionar usuario...</option>
                                <?php foreach ($data['all_users'] as $u): ?>
                                    <option value="<?php echo $u->id; ?>">
                                        <?php echo htmlspecialchars($u->full_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-success" title="Asignar">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
                <!-- Desvincular -->
                <td class="text-center">
                    <?php if ($emp['mapped_user']): ?>
                    <form method="post" action="<?php echo URLROOT; ?>/admin/deleteMappingFromApi"
                          onsubmit="return confirm('¿Desvincular ID <?php echo htmlspecialchars($emp['employeeID']); ?>?')">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($emp['employeeID']); ?>">
                        <input type="hidden" name="device_name" value="<?php echo htmlspecialchars($emp['devices'][0] ?? ''); ?>">
                        <input type="hidden" name="start_date"  value="<?php echo htmlspecialchars($data['start_date']); ?>">
                        <input type="hidden" name="end_date"    value="<?php echo htmlspecialchars($data['end_date']); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Desvincular">
                            <i class="fas fa-unlink"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
(function () {
    var input = document.getElementById('mapeo-filtro-nombre');
    if (!input) return;
    input.addEventListener('input', function () {
        var q = (input.value || '').toLowerCase().trim();
        document.querySelectorAll('#mapeo-tabla-api .mapeo-fila').forEach(function (row) {
            var hay = !q || (row.getAttribute('data-mapeo-filtro') || '').indexOf(q) !== -1;
            row.style.display = hay ? '' : 'none';
        });
    });
})();
</script>
<?php require APPROOT . '/views/inc/footer.php'; ?>
