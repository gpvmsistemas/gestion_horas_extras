<?php require APPROOT . '/views/inc/header.php'; ?>

<?php
$data = $data ?? [];
$mesesES = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril','May'=>'Mayo','June'=>'Junio',
            'July'=>'Julio','August'=>'Agosto','September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];
$mesActual   = $mesesES[date('F')] ?? date('F');
$anioActual  = date('Y');
$totalHoras  = ($data['stats']['overtime_50'] + $data['stats']['overtime_100']);
$costo       = $data['stats']['estimated_cost'];
?>

<div class="admin-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-chart-line"></i></div>
        <div class="admin-page-meta">
            <h2 class="page-title">Dashboard</h2>
            <p class="page-subtitle mb-0">Resumen operativo de horas extras, solicitudes y alertas del equipo.</p>
        </div>
    </div>
</div>

<?php $overtimeEnabled = !empty($data['overtime_enabled']); ?>

<!-- ══════════════════════════════════════════════
     HERO — Mes sin cerrar
══════════════════════════════════════════════ -->
<?php if ($overtimeEnabled): ?>
<div class="db-hero">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <div class="db-hero-month"><i class="fas fa-lock-open me-1"></i>Período abierto · <?php echo $mesActual . ' ' . $anioActual; ?></div>
            <div class="db-hero-title">Horas extras pendientes de cierre</div>
            <div class="db-hero-stats">
                <div class="db-hero-stat">
                    <span class="db-hero-stat-val pink"><?php echo number_format($data['stats']['overtime_50'], 2); ?></span>
                    <span class="db-hero-stat-lbl">Horas al 50%</span>
                </div>
                <div class="db-hero-divider"></div>
                <div class="db-hero-stat">
                    <span class="db-hero-stat-val orange"><?php echo number_format($data['stats']['overtime_100'], 2); ?></span>
                    <span class="db-hero-stat-lbl">Horas al 100%</span>
                </div>
                <div class="db-hero-divider"></div>
                <div class="db-hero-stat">
                    <span class="db-hero-stat-val" style="font-size:1.9rem"><?php echo number_format($totalHoras, 2); ?></span>
                    <span class="db-hero-stat-lbl">Total horas</span>
                </div>
                <div class="db-hero-divider"></div>
                <div class="db-hero-stat">
                    <span class="db-hero-stat-val green" style="font-size:1.9rem">$<?php echo number_format($costo, 2); ?></span>
                    <span class="db-hero-stat-lbl">Costo estimado</span>
                </div>
            </div>
        </div>
        <div class="col-lg-4 text-lg-end">
            <a href="<?php echo URLROOT; ?>/admin/pendingOvertime" class="btn btn-outline-light mb-2 w-100 w-lg-auto">
                <i class="fas fa-list-ul me-2"></i>Ver horas pendientes
            </a>
            <button type="button" id="showClosureModalBtn" class="db-close-btn">
                <i class="fas fa-file-invoice-dollar"></i>
                CERRAR MES
            </button>
            <div class="mt-2" style="color:rgba(255,255,255,.4);font-size:.73rem">
                <i class="fas fa-users me-1"></i><?php echo $data['stats']['employees_with_pending']; ?> empleados con horas pendientes
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-light border mb-4">
    <i class="fas fa-info-circle me-2 text-muted"></i>
    Las horas extras (50%/100%) no están habilitadas para la empresa activa, tu perfil o el área correspondiente.
    Configuralo en <strong>Empresas</strong>, <strong>Áreas</strong> o <strong>Configuración del sistema</strong>.
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     KPIs generales
══════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="db-kpi">
            <div class="db-kpi-icon" style="background:var(--clr-primary-l)"><i class="fas fa-users" style="color:var(--clr-primary)"></i></div>
            <div>
                <div class="db-kpi-val"><?php echo $data['stats']['active_users']; ?></div>
                <div class="db-kpi-lbl">Empleados activos</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="db-kpi">
            <div class="db-kpi-icon" style="background:#d1fae5"><i class="fas fa-walking" style="color:#10b981"></i></div>
            <div>
                <div class="db-kpi-val"><?php echo $data['stats']['working_now']; ?></div>
                <div class="db-kpi-lbl">Trabajando ahora</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="db-kpi">
            <div class="db-kpi-icon" style="background:#dbeafe"><i class="fas fa-calendar-times" style="color:#3b82f6"></i></div>
            <div>
                <div class="db-kpi-val"><?php echo $data['stats']['on_leave_today']; ?></div>
                <div class="db-kpi-lbl">De licencia hoy</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="db-kpi">
            <div class="db-kpi-icon" style="background:#fff3cd"><i class="fas fa-inbox" style="color:#f59e0b"></i></div>
            <div>
                <div class="db-kpi-val"><?php echo $data['stats']['pending_requests_count']; ?></div>
                <div class="db-kpi-lbl">Solicitudes pendientes</div>
            </div>
        </div>
    </div>
</div>

<?php require APPROOT . '/views/admin/partials/attendance_dashboard.php'; ?>

<?php if ($overtimeEnabled): ?>
<!-- ══════════════════════════════════════════════
     Gráficos principales
══════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <!-- Histórico 6 meses -->
    <div class="col-lg-8">
        <div class="db-card">
            <div class="db-card-title"><i class="fas fa-chart-bar" style="color:var(--clr-primary)"></i>Evolución de horas extras — últimos 6 meses</div>
            <div style="height:260px"><canvas id="historicalOvertimeChart"></canvas></div>
        </div>
    </div>
    <!-- Distribución doughnut -->
    <div class="col-lg-4">
        <div class="db-card d-flex flex-column">
            <div class="db-card-title"><i class="fas fa-chart-pie" style="color:var(--clr-secondary)"></i>Distribución actual</div>
            <div class="flex-grow-1 d-flex align-items-center justify-content-center" style="min-height:220px">
                <div style="width:200px;height:200px"><canvas id="overtimeDistributionChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- H.E. por día de semana -->
    <div class="col-lg-5">
        <div class="db-card">
            <div class="db-card-title"><i class="fas fa-calendar-week" style="color:var(--clr-info)"></i>H.E. por día de semana</div>
            <div style="height:220px"><canvas id="overtimeByDayChart"></canvas></div>
        </div>
    </div>
    <!-- Top 5 empleados -->
    <div class="col-lg-7">
        <div class="db-card">
            <div class="db-card-title"><i class="fas fa-trophy" style="color:#f59e0b"></i>Top empleados con más horas pendientes</div>
            <?php if(empty($data['top_employees'])): ?>
                <p class="text-muted text-center mt-3 mb-0" style="font-size:.85rem">No hay horas pendientes.</p>
            <?php else:
                $maxH = $data['top_employees'][0]->total_hours > 0 ? $data['top_employees'][0]->total_hours : 1;
                $rank = 1;
                foreach($data['top_employees'] as $emp): ?>
                <a href="<?php echo URLROOT; ?>/admin/employeeDetails/<?php echo (int)$emp->user_id; ?>" class="db-emp-row db-emp-row-link text-decoration-none" title="Ver detalle y editar">
                    <span class="db-emp-rank"><?php echo $rank++; ?></span>
                    <img src="<?php echo htmlspecialchars(avatar_url($emp->profile_picture ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;flex-shrink:0" alt="<?php echo htmlspecialchars($emp->full_name); ?>" onerror="<?php echo avatar_img_onerror_attr(); ?>">
                    <span class="db-emp-name"><?php echo htmlspecialchars($emp->full_name); ?></span>
                    <div class="db-emp-bar-wrap"><div class="db-emp-bar" style="width:<?php echo round(($emp->total_hours/$maxH)*100); ?>%"></div></div>
                    <span class="db-emp-hours"><?php echo number_format($emp->total_hours,2); ?> hs</span>
                    <i class="fas fa-chevron-right text-muted" style="font-size:.7rem;flex-shrink:0"></i>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     Solicitudes + Alertas + Acciones rápidas
══════════════════════════════════════════════ -->
<div class="row g-3">
    <!-- Solicitudes pendientes -->
    <div class="col-lg-5">
        <div class="db-card">
            <div class="db-card-title"><i class="fas fa-file-alt" style="color:var(--clr-primary)"></i>Solicitudes pendientes</div>
            <?php if(empty($data['pending_requests'])): ?>
                <p class="text-muted text-center mt-2 mb-0" style="font-size:.85rem">Sin solicitudes pendientes.</p>
            <?php else: foreach(array_slice($data['pending_requests'], 0, 6) as $req): ?>
                <div class="db-req-row">
                    <img src="<?php echo htmlspecialchars(avatar_url($req->profile_picture ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;flex-shrink:0" alt="" onerror="<?php echo avatar_img_onerror_attr(); ?>">
                    <div class="flex-grow-1 min-width-0">
                        <div class="db-req-name"><?php echo htmlspecialchars($req->full_name); ?></div>
                        <div class="db-req-type"><?php echo htmlspecialchars($req->type_name); ?></div>
                    </div>
                    <div class="db-req-actions d-flex gap-1">
                        <form method="post" action="<?php echo URLROOT; ?>/admin/approveRequest/<?php echo (int)$req->id; ?>" class="d-inline">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-success btn-sm" title="Aprobar"><i class="fas fa-check"></i></button>
                        </form>
                        <form method="post" action="<?php echo URLROOT; ?>/admin/rejectRequest/<?php echo (int)$req->id; ?>" class="d-inline">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-danger btn-sm" title="Rechazar"><i class="fas fa-times"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <!-- Alertas + cumpleaños -->
    <div class="col-lg-4">
        <div class="db-card">
            <div class="db-card-title"><i class="fas fa-bell" style="color:#f59e0b"></i>Alertas</div>
            <?php if(empty($data['users_near_limit'])): ?>
                <p class="text-muted mb-3" style="font-size:.83rem">Nadie cerca de su límite semanal.</p>
            <?php else: foreach($data['users_near_limit'] as $u): ?>
                <div class="db-alert-row">
                    <img src="<?php echo htmlspecialchars(avatar_url($u->profile_picture ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="rounded-circle" style="width:28px;height:28px;object-fit:cover;flex-shrink:0" alt="" onerror="<?php echo avatar_img_onerror_attr(); ?>">
                    <span class="db-alert-name"><?php echo htmlspecialchars($u->full_name); ?></span>
                    <span class="db-alert-badge"><?php echo number_format($u->hours_worked,2); ?> hs</span>
                </div>
            <?php endforeach; endif; ?>

            <?php if(!empty($data['birthday_info']['today_birthdays'])): ?>
            <div class="mt-3 p-3 rounded-3 text-center" style="background:linear-gradient(135deg,var(--clr-primary-l),var(--clr-primary-xl))">
                <div style="font-size:1.6rem">🎂</div>
                <div style="font-weight:700;font-size:.88rem;color:#7c3aed">¡Cumpleaños hoy!</div>
                <?php foreach($data['birthday_info']['today_birthdays'] as $b): ?>
                <div style="font-size:.82rem;color:#2d1f2b;font-weight:600"><?php echo htmlspecialchars($b->full_name); ?></div>
                <?php endforeach; ?>
            </div>
            <?php elseif(!empty($data['birthday_info']['upcoming_birthdays'])): $nb=$data['birthday_info']['upcoming_birthdays'][0]; ?>
            <div class="mt-3 d-flex align-items-center gap-2 p-2 rounded-3" style="background:var(--clr-primary-xl);border:1px solid var(--clr-primary-l)">
                <span style="font-size:1.3rem">🎂</span>
                <div>
                    <div style="font-size:.73rem;color:var(--clr-primary);font-weight:700">Próximo cumpleaños</div>
                    <div style="font-size:.82rem;font-weight:600;color:#2d1f2b"><?php echo htmlspecialchars($nb->full_name); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if($data['latest_suggestion']): ?>
            <div class="db-card-title mt-3"><i class="fas fa-lightbulb" style="color:var(--clr-secondary)"></i>Última sugerencia</div>
            <div class="db-suggestion">
                <?php echo htmlspecialchars($data['latest_suggestion']->suggestion_text); ?>
                <div class="text-end mt-1" style="font-size:.7rem;color:#c09ab8;font-style:normal">— <?php echo isset($data['latest_suggestion']->full_name) ? htmlspecialchars($data['latest_suggestion']->full_name) : 'Anónimo'; ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Acciones rápidas -->
    <div class="col-lg-3">
        <div class="db-card">
            <div class="db-card-title"><i class="fas fa-bolt" style="color:var(--clr-primary)"></i>Acciones rápidas</div>
            <div class="d-flex flex-column gap-2">
                <a href="<?php echo URLROOT; ?>/admin/weeklyPlanner" class="db-action-btn" style="background:var(--clr-primary-l);color:var(--clr-primary-d)">
                    <i class="fas fa-calendar-alt"></i> Planificador semanal
                </a>
                <a href="<?php echo URLROOT; ?>/admin/calendar" class="db-action-btn" style="background:var(--clr-admin-l);color:var(--clr-admin-d)">
                    <i class="fas fa-calendar"></i> Calendario mensual
                </a>
                <a href="<?php echo URLROOT; ?>/admin/createUser" class="db-action-btn" style="background:#dbeafe;color:#1d4ed8">
                    <i class="fas fa-user-plus"></i> Crear empleado
                </a>
                <a href="<?php echo URLROOT; ?>/admin/reports" class="db-action-btn" style="background:#d1fae5;color:#065f46">
                    <i class="fas fa-file-export"></i> Ver reportes
                </a>
                <?php if ($overtimeEnabled): ?>
                <a href="<?php echo URLROOT; ?>/admin/pendingOvertime" class="db-action-btn" style="background:var(--clr-primary-l);color:var(--clr-primary-d)">
                    <i class="fas fa-clock"></i> Horas pendientes
                </a>
                <a href="<?php echo URLROOT; ?>/admin/history" class="db-action-btn" style="background:#f5f3ff;color:#6d28d9">
                    <i class="fas fa-history"></i> Historial de cierres
                </a>
                <?php endif; ?>
                <a href="<?php echo URLROOT; ?>/admin/users" class="db-action-btn" style="background:#fff3cd;color:#92400e">
                    <i class="fas fa-users-cog"></i> Gestionar usuarios
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($overtimeEnabled): ?>
<!-- ══════════════════════════════════════════════
     Modal Cierre de Mes
══════════════════════════════════════════════ -->
<div class="modal fade" id="closureSummaryModal" tabindex="-1" aria-labelledby="closureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header db-dark-head">
                <h5 class="modal-title" id="closureModalLabel">
                    <i class="fas fa-file-invoice-dollar me-2" style="color:var(--clr-primary)"></i>
                    Resumen del cierre — <?php echo $mesActual . ' ' . $anioActual; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Esta acción marcará todas las horas extras pendientes como <strong>cerradas</strong>. No se puede deshacer.</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead style="background:var(--clr-primary-xl)"><tr>
                            <th>Empleado</th>
                            <th class="text-end">Hs. 50%</th>
                            <th class="text-end">Hs. 100%</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Ver</th>
                        </tr></thead>
                        <tbody id="closureSummaryTableBody"></tbody>
                        <tfoot id="closureSummaryFoot" style="display:none;font-weight:800;background:#fdf4fa">
                            <tr>
                                <td>TOTAL</td>
                                <td class="text-end" id="closureTot50">—</td>
                                <td class="text-end" id="closureTot100">—</td>
                                <td class="text-end" id="closureTotAll">—</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center w-100">
                <a href="<?php echo URLROOT; ?>/admin/exportPendingClosure" class="btn btn-success" id="exportPendingClosureBtn" download="resumen_cierre_pendiente.csv">
                    <i class="fas fa-file-excel me-2"></i>Descargar Excel
                </a>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form action="<?php echo URLROOT; ?>/admin/createClosure" method="post" class="mb-0">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-danger px-4 fw-700">
                            <i class="fas fa-check me-2"></i>Confirmar cierre
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var charts = {};
    function createChart(id, cfg) {
        var el = document.getElementById(id);
        if (!el) return;
        if (charts[id]) charts[id].destroy();
        charts[id] = new window.Chart(el, cfg);
    }

    <?php if ($overtimeEnabled): ?>
    /* Paleta de los gráficos tomada del tema activo (Paviotti rosa / Moderna azul) */
    var _cs = getComputedStyle(document.body);
    var chartPrimary   = (_cs.getPropertyValue('--clr-primary') || '#e91e8c').trim();
    var chartSecondary = (_cs.getPropertyValue('--clr-secondary') || '#7c3aed').trim();
    function chartRgba(hex, a) {
        var h = hex.replace('#', '');
        if (h.length === 3) h = h[0]+h[0]+h[1]+h[1]+h[2]+h[2];
        var n = parseInt(h, 16);
        return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
    }
    var dist50   = <?php echo $data['charts']['overtime_distribution'] ?? '[]'; ?>;
    var byDay    = <?php echo $data['charts']['overtime_by_day'] ?? '[]'; ?>;
    var histLbls = <?php echo $data['charts']['historical']['labels'] ?? '[]'; ?>;
    var hist50   = <?php echo $data['charts']['historical']['data50'] ?? '[]'; ?>;
    var hist100  = <?php echo $data['charts']['historical']['data100'] ?? '[]'; ?>;

    /* Histórico */
    createChart('historicalOvertimeChart', {
        type: 'bar',
        data: {
            labels: histLbls,
            datasets: [
                { label: 'Hs. 50%', data: hist50,  backgroundColor: chartRgba(chartPrimary, .75), borderRadius: 5 },
                { label: 'Hs. 100%', data: hist100, backgroundColor: chartRgba(chartSecondary, .65), borderRadius: 5 }
            ]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' } }
            },
            plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 12 } } } }
        }
    });

    /* Distribución doughnut */
    var totalDist = dist50.reduce(function(a,b){ return a+b; }, 0);
    if (totalDist > 0) {
        createChart('overtimeDistributionChart', {
            type: 'doughnut',
            data: {
                labels: ['Hs. 50%', 'Hs. 100%'],
                datasets: [{ data: dist50, backgroundColor: [chartPrimary, chartSecondary], borderWidth: 3, borderColor: '#fff' }]
            },
            options: {
                maintainAspectRatio: false, cutout: '72%',
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
            }
        });
    } else {
        document.getElementById('overtimeDistributionChart').parentElement.innerHTML =
            '<p class="text-center text-muted" style="font-size:.83rem;margin-top:3rem">Sin horas pendientes</p>';
    }

    /* H.E. por día */
    createChart('overtimeByDayChart', {
        type: 'bar',
        data: {
            labels: ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'],
            datasets: [{ data: byDay, backgroundColor: chartRgba(chartPrimary, .7), borderRadius: 5 }]
        },
        options: {
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' } }, x: { grid: { display: false } } },
            plugins: { legend: { display: false } }
        }
    });

    /* Modal Cierre */
    var closureModal = new bootstrap.Modal(document.getElementById('closureSummaryModal'));
    document.getElementById('showClosureModalBtn').addEventListener('click', function() {
        var tbody = document.getElementById('closureSummaryTableBody');
        var tfoot = document.getElementById('closureSummaryFoot');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</td></tr>';
        tfoot.style.display = 'none';
        closureModal.show();
        var detailsBase = '<?php echo URLROOT; ?>/admin/employeeDetails/';
        fetch('<?php echo URLROOT; ?>/admin/getClosureSummaryAjax')
            .then(function(r){ return r.json(); })
            .then(function(data) {
                tbody.innerHTML = '';
                if (!data.length) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No hay horas pendientes.</td></tr>';
                    return;
                }
                var t50=0, t100=0;
                data.forEach(function(e) {
                    var h50=parseFloat(e.hours_50), h100=parseFloat(e.hours_100), tot=parseFloat(e.total_hours);
                    t50+=h50; t100+=h100;
                    var tr = document.createElement('tr');
                    var tdName = document.createElement('td');
                    if (e.user_id) {
                        var nameLink = document.createElement('a');
                        nameLink.href = detailsBase + e.user_id;
                        nameLink.textContent = e.full_name || '';
                        nameLink.className = 'fw-semibold text-decoration-none';
                        tdName.appendChild(nameLink);
                    } else {
                        tdName.textContent = e.full_name || '';
                    }
                    tr.appendChild(tdName);
                    ['text-end', 'text-end', 'text-end fw-bold'].forEach(function(cls, i) {
                        var td = document.createElement('td');
                        td.className = cls;
                        td.textContent = [h50.toFixed(2), h100.toFixed(2), tot.toFixed(2)][i];
                        tr.appendChild(td);
                    });
                    var tdAct = document.createElement('td');
                    tdAct.className = 'text-center';
                    if (e.user_id) {
                        var verLink = document.createElement('a');
                        verLink.href = detailsBase + e.user_id;
                        verLink.className = 'btn btn-sm btn-outline-primary py-0 px-2';
                        verLink.title = 'Ver detalle';
                        verLink.innerHTML = '<i class="fas fa-eye"></i>';
                        tdAct.appendChild(verLink);
                    }
                    tr.appendChild(tdAct);
                    tbody.appendChild(tr);
                });
                document.getElementById('closureTot50').textContent  = t50.toFixed(2);
                document.getElementById('closureTot100').textContent = t100.toFixed(2);
                document.getElementById('closureTotAll').textContent = (t50+t100).toFixed(2);
                tfoot.style.display = '';
            })
            .catch(function() {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error al cargar el resumen.</td></tr>';
            });
    });
    <?php endif; ?>
});
</script>

<?php require APPROOT . '/views/inc/footer.php'; ?>