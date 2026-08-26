<?php
require APPROOT . '/views/inc/header.php';
$month = $data['month'];
$ini = $data['ini'];
$porDia = $data['por_dia'];
$colors = $data['colors'];
$tipoSel = $data['tipo'];
$tipoLetra = ['vacaciones' => 'V', 'licencia' => 'L', 'guardia' => 'G'];
$tipoLabel = ['vacaciones' => 'Vacaciones', 'licencia' => 'Licencia', 'guardia' => 'Guardia'];
$meses = ['01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
          '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'];
$titulo = ($meses[substr($month, 5, 2)] ?? '') . ' ' . substr($month, 0, 4);
$dim = (int)date('t', strtotime($ini));
$dow = (int)date('N', strtotime($ini));
$hoy = date('Y-m-d');
$qsTipo = $tipoSel !== '' ? '&tipo=' . $tipoSel : '';
?>
<style>
.hrr .rh-cal-day { min-height: 96px; }
.hrr-chip { display: block; font-size: .68rem; font-weight: 600; color: #fff; border-radius: .4rem;
    padding: .1rem .38rem; margin-top: .2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .18); }
.hrr-chip b { font-weight: 800; opacity: .85; }
.hrr-mas { display: block; font-size: .66rem; color: var(--clr-admin-muted); margin-top: .2rem; }

/* Barra de filtros + leyenda */
.hrr-toolbar { border: 0; border-radius: .9rem; box-shadow: var(--card-sh, 0 2px 12px rgba(0,0,0,.07)); background: #fff; }
.hrr-filtros { display: flex; flex-wrap: wrap; gap: .9rem 1.1rem; align-items: flex-end; }
.hrr-filtros-icono { width: 38px; height: 38px; flex: 0 0 38px; display: grid; place-items: center;
    border-radius: .65rem; background: var(--clr-primary-xl, #eff6ff); color: var(--clr-primary, #1d4ed8); align-self: center; }
.hrr-filtro { min-width: 160px; flex: 1 1 160px; max-width: 230px; }
.hrr-filtro > label { display: block; font-size: .66rem; font-weight: 800; letter-spacing: .06em;
    text-transform: uppercase; color: var(--clr-admin-muted, #64748b); margin-bottom: .3rem; }
.hrr-filtro .form-select { border-radius: .6rem; font-weight: 600; }
.hrr-filtro .form-select.is-active { border-color: var(--clr-primary, #1d4ed8);
    background-color: var(--clr-primary-xl, #eff6ff); color: var(--clr-primary-d, #1e40af); }
.hrr-limpiar { align-self: flex-end; margin-bottom: .15rem; font-size: .78rem; font-weight: 700;
    color: var(--clr-danger, #ef4444); text-decoration: none; white-space: nowrap; }
.hrr-limpiar:hover { text-decoration: underline; color: var(--clr-danger, #ef4444); }
.hrr-leyenda { display: flex; flex-wrap: wrap; gap: .45rem .55rem; align-items: center;
    border-top: 1px solid var(--clr-admin-border, #e2e8f0); margin-top: 1rem; padding-top: .85rem; }
.hrr-leyenda-titulo { font-size: .66rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
    color: var(--clr-admin-muted, #64748b); margin-right: .3rem; }
.hrr-pill { display: inline-flex; align-items: center; gap: .45rem; padding: .28rem .7rem;
    background: #f8fafc; border: 1px solid var(--clr-admin-border, #e2e8f0); border-radius: 999px;
    font-size: .74rem; font-weight: 650; color: var(--clr-admin-text, #0f172a); }
.hrr-pill .dot { width: 10px; height: 10px; border-radius: 50%; flex: 0 0 10px; }
.hrr-tipos { margin-left: auto; display: inline-flex; gap: .45rem; align-items: center; }
.hrr-key { display: inline-flex; align-items: center; gap: .35rem; font-size: .72rem; color: var(--clr-admin-muted, #64748b); }
.hrr-key b { display: inline-grid; place-items: center; width: 18px; height: 18px; border-radius: .35rem;
    background: #334155; color: #fff; font-size: .62rem; }
@media (max-width: 767px) { .hrr-tipos { margin-left: 0; } .hrr-filtro { max-width: none; } }
</style>

<div class="admin-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-calendar-week"></i></div>
        <div class="admin-page-meta">
            <h1 class="page-title">Roadmap RRHH</h1>
            <p class="page-subtitle mb-0">Vacaciones, licencias y guardias de toda la organización, coloreadas por empresa.</p>
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a class="btn btn-outline-primary btn-sm" href="<?php echo URLROOT; ?>/admin/hrRoadmap?m=<?php echo $data['prev'] . $qsTipo; ?>" aria-label="Mes anterior"><i class="fas fa-chevron-left"></i></a>
        <span class="fw-bold text-white"><?php echo $titulo; ?></span>
        <a class="btn btn-outline-primary btn-sm" href="<?php echo URLROOT; ?>/admin/hrRoadmap?m=<?php echo $data['next'] . $qsTipo; ?>" aria-label="Mes siguiente"><i class="fas fa-chevron-right"></i></a>
    </div>
</div>

<?php $_hayFiltro = $data['f_empresa'] || $data['f_ciudad'] !== '' || $data['f_sucursal'] || $tipoSel !== ''; ?>
<div class="card hrr-toolbar mb-3">
    <div class="card-body py-3">
        <form method="get" action="<?php echo URLROOT; ?>/admin/hrRoadmap" class="hrr-filtros" id="hrrFiltros">
            <input type="hidden" name="m" value="<?php echo htmlspecialchars($month); ?>">
            <span class="hrr-filtros-icono"><i class="fas fa-filter"></i></span>
            <div class="hrr-filtro">
                <label for="hrrEmpresa">Empresa</label>
                <select class="form-select form-select-sm <?php echo $data['f_empresa'] ? 'is-active' : ''; ?>" name="empresa" id="hrrEmpresa" onchange="hrrSync(true)">
                    <option value="0">Todas</option>
                    <?php foreach ($data['companies'] as $c): ?>
                    <option value="<?php echo (int)$c->id; ?>" <?php echo (int)$data['f_empresa'] === (int)$c->id ? 'selected' : ''; ?>><?php echo htmlspecialchars($c->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="hrr-filtro">
                <label for="hrrCiudad">Ciudad</label>
                <select class="form-select form-select-sm <?php echo $data['f_ciudad'] !== '' ? 'is-active' : ''; ?>" name="ciudad" id="hrrCiudad" onchange="hrrSync(true)">
                    <option value="">Todas</option>
                    <?php foreach ($data['ciudades'] as $ci): ?>
                    <option value="<?php echo htmlspecialchars($ci); ?>" <?php echo $data['f_ciudad'] === $ci ? 'selected' : ''; ?>><?php echo htmlspecialchars($ci); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="hrr-filtro">
                <label for="hrrSucursal">Sucursal</label>
                <select class="form-select form-select-sm <?php echo $data['f_sucursal'] ? 'is-active' : ''; ?>" name="sucursal" id="hrrSucursal" onchange="this.form.submit()">
                    <option value="0">Todas</option>
                    <?php foreach ($data['branches'] as $b): ?>
                    <option value="<?php echo (int)$b->id; ?>" data-company="<?php echo (int)$b->company_id; ?>" data-city="<?php echo htmlspecialchars($b->locality); ?>" <?php echo (int)$data['f_sucursal'] === (int)$b->id ? 'selected' : ''; ?>><?php echo htmlspecialchars($b->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="hrr-filtro">
                <label for="hrrTipo">Tipo de ausencia</label>
                <select class="form-select form-select-sm <?php echo $tipoSel !== '' ? 'is-active' : ''; ?>" name="tipo" id="hrrTipo" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($tipoLabel as $tv => $tl): ?>
                    <option value="<?php echo $tv; ?>" <?php echo $tipoSel === $tv ? 'selected' : ''; ?>><?php echo $tl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($_hayFiltro): ?>
            <a class="hrr-limpiar" href="<?php echo URLROOT; ?>/admin/hrRoadmap?m=<?php echo htmlspecialchars($month); ?>"><i class="fas fa-times me-1"></i>Limpiar filtros</a>
            <?php endif; ?>
        </form>
        <div class="hrr-leyenda">
            <span class="hrr-leyenda-titulo">Empresas</span>
            <?php foreach ($data['companies'] as $c): ?>
            <span class="hrr-pill"><span class="dot" style="background:<?php echo htmlspecialchars($colors[(int)$c->id]); ?>"></span><?php echo htmlspecialchars($c->name); ?></span>
            <?php endforeach; ?>
            <span class="hrr-tipos">
                <span class="hrr-key"><b>V</b>Vacaciones</span>
                <span class="hrr-key"><b>L</b>Licencia</span>
                <span class="hrr-key"><b>G</b>Guardia</span>
            </span>
        </div>
        <script>
        // Cascada: la lista de sucursales se acota a la empresa y ciudad elegidas.
        function hrrSync(submit) {
            var emp = document.getElementById('hrrEmpresa').value;
            var ciu = document.getElementById('hrrCiudad').value;
            var suc = document.getElementById('hrrSucursal');
            [].forEach.call(suc.options, function (o) {
                if (!o.value || o.value === '0') return;
                var ok = (emp === '0' || o.dataset.company === emp) && (ciu === '' || o.dataset.city === ciu);
                o.hidden = !ok; o.disabled = !ok;
                if (!ok && o.selected) suc.value = '0';
            });
            if (submit) document.getElementById('hrrFiltros').submit();
        }
        hrrSync(false);
        </script>
    </div>
</div>

<div class="card border-0 shadow-sm hrr">
    <div class="card-body">
        <div class="rh-cal" role="grid" aria-label="Roadmap <?php echo htmlspecialchars($titulo); ?>">
            <?php foreach (['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'] as $h): ?>
            <div class="rh-cal-head" role="columnheader"><?php echo $h; ?></div>
            <?php endforeach; ?>
            <?php for ($i = 1; $i < $dow; $i++): ?>
            <div class="rh-cal-day is-empty" role="gridcell" aria-hidden="true"></div>
            <?php endfor; ?>
            <?php for ($d = 1; $d <= $dim; $d++):
                $iso = $month . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
                $chips = $porDia[$iso] ?? [];
                usort($chips, fn($a, $b) => [$a->company_id, $a->name] <=> [$b->company_id, $b->name]);
            ?>
            <div class="rh-cal-day <?php echo $iso === $hoy ? 'is-today' : ''; ?>" role="gridcell">
                <?php echo $d; ?>
                <?php foreach (array_slice($chips, 0, 4) as $ch): ?>
                <span class="hrr-chip" style="background:<?php echo htmlspecialchars($colors[$ch->company_id] ?? '#64748b'); ?>"
                      title="<?php echo htmlspecialchars($ch->name . ' · ' . ($tipoLabel[$ch->tipo] ?? $ch->tipo)); ?>" data-rh-variant="info">
                    <b><?php echo $tipoLetra[$ch->tipo] ?? '·'; ?></b> <?php echo htmlspecialchars($ch->name); ?>
                </span>
                <?php endforeach; ?>
                <?php if (count($chips) > 4): ?>
                <span class="hrr-mas" title="<?php echo htmlspecialchars(implode(' · ', array_map(fn($c) => $c->name . ' (' . ($tipoLetra[$c->tipo] ?? '') . ')', array_slice($chips, 4)))); ?>">+<?php echo count($chips) - 4; ?> más</span>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
        <?php if (!$porDia): ?>
        <p class="text-secondary mt-3 mb-0">Sin vacaciones, licencias ni guardias registradas este mes.</p>
        <?php endif; ?>
    </div>
</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
