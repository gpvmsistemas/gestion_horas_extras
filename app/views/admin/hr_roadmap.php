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
.hrr-chip { display: block; font-size: .68rem; font-weight: 600; color: #fff; border-radius: .35rem;
    padding: .06rem .32rem; margin-top: .18rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hrr-mas { display: block; font-size: .66rem; color: var(--clr-admin-muted); margin-top: .18rem; }
.hrr-leyenda { display: flex; flex-wrap: wrap; gap: .5rem 1.1rem; align-items: center; }
.hrr-leyenda .dot { display: inline-block; width: 11px; height: 11px; border-radius: 50%; margin-right: .35rem; vertical-align: -1px; }
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

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="hrr-leyenda">
            <?php foreach ($data['companies'] as $c): ?>
            <span class="small"><span class="dot" style="background:<?php echo htmlspecialchars($colors[(int)$c->id]); ?>"></span><?php echo htmlspecialchars($c->name); ?></span>
            <?php endforeach; ?>
            <span class="small text-secondary">V = Vacaciones · L = Licencia · G = Guardia</span>
        </div>
        <form method="get" action="<?php echo URLROOT; ?>/admin/hrRoadmap" class="d-flex gap-2">
            <input type="hidden" name="m" value="<?php echo htmlspecialchars($month); ?>">
            <select class="form-select form-select-sm" name="tipo" onchange="this.form.submit()">
                <option value="">Todos los tipos</option>
                <?php foreach ($tipoLabel as $tv => $tl): ?>
                <option value="<?php echo $tv; ?>" <?php echo $tipoSel === $tv ? 'selected' : ''; ?>><?php echo $tl; ?></option>
                <?php endforeach; ?>
            </select>
        </form>
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
                    <?php echo $tipoLetra[$ch->tipo] ?? '·'; ?> · <?php echo htmlspecialchars($ch->name); ?>
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
