<?php 
require APPROOT . '/views/inc/header.php'; 

/**
 * Renderiza un chip de horario (entrada existente cargada desde la BD).
 * Se usa SOLO para entradas pre-existentes; las nuevas se crean via JS.
 */
function renderChip($userId, $date, $index, $entry, $shifts, $readOnly = false) {
    $type      = $entry ? $entry->type : 'shift';
    $color     = '#6c757d';
    $viewLabel = 'Turno';
    $viewTime  = '';
    $selectedShift = $entry ? (int)$entry->shift_id : 0;
    $startTime = ($entry && $entry->start_time) ? $entry->start_time : '';
    $endTime   = ($entry && $entry->end_time)   ? $entry->end_time   : '';
    $notes     = ($entry && $entry->notes)       ? $entry->notes      : '';

    if ($type === 'shift') {
        $color     = ($entry && !empty($entry->color))      ? $entry->color      : '#6c757d';
        $viewLabel = ($entry && !empty($entry->shift_name)) ? $entry->shift_name : 'Turno';
    } elseif ($type === 'overtime') {
        $color     = '#e67e22';
        $viewLabel = 'Horas Extras';
    } elseif ($type === 'vacation') {
        $color     = '#0ea5e9';
        $viewLabel = 'VACACIONES';
    } elseif ($type === 'leave') {
        $color     = '#a855f7';
        $viewLabel = 'Licencia';
    } else {
        $color     = '#8b5cf6';
        $viewLabel = 'Personalizado';
    }
    if ($startTime && $endTime) {
        $viewTime = substr($startTime, 0, 5) . ' - ' . substr($endTime, 0, 5);
    }

    $showSel  = ($type !== 'shift') ? ' style="display:none;"' : '';
    $showTime = in_array($type, ['shift', 'vacation', 'leave'], true) ? ' style="display:none;"' : '';
    $prefix   = 'schedules[' . $userId . '][' . $date . '][' . $index . ']';

    $shiftOptions = '';
    if (!empty($shifts)) {
        foreach ($shifts as $s) {
            $sel  = ((int)$s->id === $selectedShift) ? ' selected' : '';
            $c    = htmlspecialchars($s->color ?? '#6c757d');
            $shiftOptions .= '<option value="' . $s->id . '" data-color="' . $c . '"' . $sel . '>'
                           . htmlspecialchars($s->shift_name) . '</option>';
        }
    }

    ob_start();
    ?>
<div class="entry-chip type-<?php echo htmlspecialchars($type); ?><?php echo $readOnly ? ' chip-readonly' : ''; ?>"
     draggable="<?php echo $readOnly ? 'false' : 'true'; ?>"
     data-type="<?php echo htmlspecialchars($type); ?>"
     <?php if ($readOnly): ?>data-readonly="1"<?php endif; ?>
     style="--chip-color:<?php echo htmlspecialchars($color); ?>;">
    <?php if (!$readOnly): ?>
    <div class="chip-drag-handle" title="Arrastrar"><i class="fas fa-grip-vertical"></i></div>
    <?php else: ?>
    <div class="chip-drag-handle text-muted" title="Solo lectura — horas extras deshabilitadas"><i class="fas fa-lock"></i></div>
    <?php endif; ?>
    <div class="chip-body">
        <div class="chip-view">
            <span class="chip-dot"></span>
            <span class="chip-name"><?php echo htmlspecialchars($viewLabel); ?></span>
            <?php if ($viewTime): ?><span class="chip-time"><?php echo htmlspecialchars($viewTime); ?></span><?php endif; ?>
            <?php if ($notes): ?><span class="chip-note-icon" title="<?php echo htmlspecialchars($notes); ?>"><i class="fas fa-comment-alt"></i></span><?php endif; ?>
            <?php if ($readOnly): ?><span class="badge bg-secondary ms-1" style="font-size:.6rem;">solo lectura</span><?php endif; ?>
        </div>
        <?php if ($readOnly): ?>
        <input type="hidden" name="<?php echo $prefix; ?>[type]" value="<?php echo htmlspecialchars($type); ?>">
        <input type="hidden" name="<?php echo $prefix; ?>[shift_id]" value="<?php echo (int)$selectedShift; ?>">
        <input type="hidden" name="<?php echo $prefix; ?>[start_time]" value="<?php echo htmlspecialchars($startTime); ?>">
        <input type="hidden" name="<?php echo $prefix; ?>[end_time]" value="<?php echo htmlspecialchars($endTime); ?>">
        <input type="hidden" name="<?php echo $prefix; ?>[notes]" value="<?php echo htmlspecialchars($notes); ?>">
        <?php else: ?>
        <div class="chip-controls">
            <input type="hidden" name="<?php echo $prefix; ?>[type]" value="<?php echo htmlspecialchars($type); ?>">
            <div class="chip-selector"<?php echo $showSel; ?>>
                <select class="form-select form-select-sm"
                        name="<?php echo $prefix; ?>[shift_id]"
                        onchange="updateChipFromSelect(this)">
                    <?php echo $shiftOptions; ?>
                </select>
            </div>
            <div class="chip-times"<?php echo $showTime; ?>>
                <div class="d-flex gap-1 align-items-center mt-1">
                    <input type="time" class="form-control form-control-sm"
                           name="<?php echo $prefix; ?>[start_time]"
                           value="<?php echo htmlspecialchars($startTime); ?>">
                          <span class="text-muted small">-</span>
                    <input type="time" class="form-control form-control-sm"
                           name="<?php echo $prefix; ?>[end_time]"
                           value="<?php echo htmlspecialchars($endTime); ?>">
                </div>
            </div>
            <input type="text" class="form-control form-control-sm mt-1"
                   name="<?php echo $prefix; ?>[notes]"
                   placeholder="Nota (opcional)..."
                   value="<?php echo htmlspecialchars($notes); ?>">
        </div>
        <?php endif; ?>
    </div>
    <?php if (!$readOnly): ?>
    <button type="button" class="btn-chip-remove" onclick="removeEntry(this)" title="Eliminar">
        <i class="fas fa-times"></i>
    </button>
    <?php endif; ?>
</div>
    <?php
    return ob_get_clean();
}
?>

<style>
/* â”€â”€ Shift palette â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.shift-palette {
    display: flex; align-items: center; gap: .6rem;
    padding: .55rem 1rem;
    background: #fff;
    border-radius: .7rem;
    box-shadow: 0 1px 6px rgba(0,0,0,.08);
    flex-wrap: wrap;
    margin-bottom: .75rem;
}
.palette-label {
    font-size: .72rem; font-weight: 700; color: #6b7280;
    text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; flex-shrink: 0;
}
.palette-sep { width: 1px; height: 22px; background: #e5e7eb; flex-shrink: 0; }
.palette-chip {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .28rem .75rem; border-radius: 20px;
    color: #fff; font-size: .76rem; font-weight: 600;
    cursor: grab; user-select: none;
    box-shadow: 0 2px 6px rgba(0,0,0,.18);
    transition: transform .15s ease, box-shadow .15s ease, opacity .15s;
    white-space: nowrap;
}
.palette-chip:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,.22); }
.palette-chip:active { cursor: grabbing; transform: scale(.96); }
.palette-chip.is-dragging { opacity: .45; }

/* â”€â”€ Nav toolbar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.planner-nav {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: .5rem;
    background: #fff; padding: .55rem 1rem;
    border-radius: .7rem; box-shadow: 0 1px 6px rgba(0,0,0,.08);
    margin-bottom: .75rem;
}
.planner-context {
    display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;
    padding:1rem 1.15rem; margin-bottom:.75rem; border-radius:.85rem;
    color:#fff; background:linear-gradient(120deg,#18223d 0%,#285a78 100%);
    box-shadow:0 8px 22px rgba(24,34,61,.16);
}
.planner-context h1 { margin:0; font-size:1.15rem; font-weight:800; letter-spacing:.01em; }
.planner-context p { margin:.15rem 0 0; color:rgba(255,255,255,.75); font-size:.8rem; }
.planner-context .context-branch { padding:.34rem .7rem; border-radius:999px; background:rgba(255,255,255,.14); font-size:.78rem; font-weight:700; }
.btn-fixed-week { border-color:#0f766e; color:#0f766e; font-weight:700; }
.btn-fixed-week:hover { background:#0f766e; color:#fff; }
.template-form { display: flex; gap: .5rem; align-items: center; }
.date-picker-container { position: relative; }
#date-picker-icon { cursor: pointer; font-size: 1.35rem; color: #6b7280; }
#date-picker { position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%); opacity: 0; width: 40px; height: 40px; cursor: pointer; }

/* â”€â”€ Table chrome â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.planner-table th, .planner-table td { vertical-align: top; padding: .4rem !important; }
.planner-table thead th {
    position: sticky; top: 0; z-index: 3;
    background: #1a1d2e; color: #fff;
    font-size: .78rem; font-weight: 600; text-align: center;
    white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,.18);
}
.planner-table thead th.is-holiday { background: #7c2d5a; }
.planner-table thead th.user-col {
    z-index: 5;
    text-align: left;
    background: #1a1d2e;
    color: #fff;
}
.planner-table tbody .user-col {
    text-align: left; min-width: 180px; max-width: 210px;
    font-weight: 600; font-size: .81rem;
    background: #f8f9fa;
    position: sticky; left: 0; z-index: 2;
    border-right: 2px solid #e5e7eb !important;
}
.planner-table-wrap {
    position: relative;
    overflow: auto;
    max-height: calc(100vh - 250px);
    overscroll-behavior: contain;
}
.planner-table-wrap .planner-table { min-width: 1100px; }
.planner-table-wrap::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}
.planner-table-wrap::-webkit-scrollbar-thumb {
    background: rgba(107, 114, 128, .45);
    border-radius: 999px;
}
@media (max-width: 991.98px) {
    .planner-table-wrap { max-height: calc(100vh - 210px); }
}
.hours-summary { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 4px; }
.hours-summary .badge { font-size: .78rem; padding: .35em .65em; font-weight: 600; letter-spacing: .01em; }
.hours-summary .weekly-gap { white-space: nowrap; }
.day-cell.day-over-limit:not(.is-editing) {
    background: linear-gradient(180deg, #fff7ed 0%, #ffedd5 100%) !important;
    box-shadow: inset 0 0 0 2px #fb923c;
}
.day-cell.day-over-limit .entries-container {
    border-radius: 6px;
}

/* â”€â”€ Day cell â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.day-cell {
    min-width: 128px; min-height: 72px;
    position: relative; transition: background .12s ease;
}
.day-cell.is-holiday  { background: #fff0f5 !important; }
.day-cell.is-editing  { background: #f0f7ff !important; outline: 2px dashed #3b82f6; outline-offset: -2px; }
.day-cell.drag-over   { background: #dbeafe !important; outline: 2px dashed #2563eb; outline-offset: -2px; }
.empty-label { color: #d1d5db; font-size: .73rem; font-style: italic; text-align: center; padding: .2rem 0 .3rem; }

/* â”€â”€ Entry chip â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.entries-container { min-height: 4px; }
.entry-chip {
    display: flex; align-items: flex-start; gap: 3px;
    border-radius: 6px; border-left: 4px solid var(--chip-color, #6c757d);
    background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.09);
    margin-bottom: 4px; font-size: .77rem; position: relative;
    transition: box-shadow .12s ease;
}
.entry-chip.is-dragging { opacity: .4; box-shadow: 0 6px 20px rgba(0,0,0,.18); }
.entry-chip:not(.is-dragging):hover { box-shadow: 0 2px 8px rgba(0,0,0,.14); }

/* drag handle: always in DOM but shown only when editing */
.chip-drag-handle {
    padding: 4px 2px 4px 4px; color: #d1d5db;
    cursor: grab; font-size: .72rem; flex-shrink: 0;
    display: none; align-items: center;
}
.day-cell.is-editing .chip-drag-handle { display: flex; }

.chip-body { flex: 1; min-width: 0; padding: 4px 2px 4px 0; }

/* â€” view mode (compact) â€” */
.chip-view { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; min-height: 22px; padding: 1px 0; }
.chip-dot  { width: 7px; height: 7px; border-radius: 50%; background: var(--chip-color, #6c757d); flex-shrink: 0; }
.chip-name { font-weight: 700; color: #1e2235; line-height: 1.3; }
.chip-time { font-size: .68rem; color: #6b7280; }
.chip-note-icon { color: #9ca3af; font-size: .63rem; }

/* â€” edit mode (controls) â€” */
.chip-controls { display: none; padding-top: 3px; }
.day-cell.is-editing .chip-view     { display: none; }
.day-cell.is-editing .chip-controls { display: block; }

/* delete button */
.btn-chip-remove {
    background: none; border: none; color: #d1d5db;
    font-size: .68rem; padding: 4px 5px 0 0; cursor: pointer;
    flex-shrink: 0; display: none; align-self: flex-start;
}
.day-cell.is-editing .btn-chip-remove { display: block; }
.btn-chip-remove:hover { color: #ef4444; }

/* â”€â”€ Cell action bar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.cell-footer { margin-top: 5px; }
.cell-actions { display: flex; justify-content: center; gap: 4px; }
.btn-cell {
    width: 26px; height: 26px; border-radius: 50%; border: none;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .7rem; cursor: pointer; color: #fff;
    transition: transform .12s ease, opacity .12s ease;
}
.btn-cell:hover  { transform: scale(1.15); }
.btn-cell:active { transform: scale(.92); }
.btn-cell-edit   { background: #3b82f6; }
.btn-cell-save   { background: #10b981; }
.btn-cell-copy   { background: #6b7280; }
.btn-cell-cancel { background: #f59e0b; display: none; }
.day-cell.is-editing .btn-cell-copy   { display: none !important; }
.day-cell.is-editing .btn-cell-cancel { display: inline-flex !important; }

/* add pills â€” visible only when editing */
.add-pills { display: none; justify-content: center; flex-wrap: wrap; gap: 3px; margin-top: 5px; }
.day-cell.is-editing .add-pills { display: flex; }
.add-pill {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 2px 8px; border-radius: 12px; border: none;
    font-size: .68rem; font-weight: 700; color: #fff; cursor: pointer;
    transition: opacity .12s; opacity: .9;
}
.add-pill:hover { opacity: 1; }
.add-pill-shift   { background: #3b82f6; }
.add-pill-ot      { background: #e67e22; }
.add-pill-custom  { background: #8b5cf6; }

/* â”€â”€ Request badge â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.request-badge {
    border-left: 4px solid; padding: 3px 6px; margin-bottom: 5px;
    border-radius: 4px; text-align: left; font-weight: 600; font-size: .74rem;
}

/* â”€â”€ Save week footer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.save-week-bar {
    background: #fff; border-radius: .7rem;
    box-shadow: 0 1px 6px rgba(0,0,0,.08);
    padding: .75rem 1.25rem;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: .5rem;
    margin-top: .75rem;
}
.save-week-hint { font-size: .78rem; color: #6b7280; }
.btn-save-week {
    padding: .45rem 2rem; font-weight: 700; font-size: .9rem; border-radius: .45rem;
}
.entry-chip.chip-readonly {
    opacity: .92;
    border: 1px dashed rgba(0,0,0,.2);
    cursor: default;
}
.entry-chip.chip-readonly .chip-drag-handle { cursor: not-allowed; }
</style>

<!-- â•â•â• PALETTE â•â•â• -->
<section class="planner-context" aria-labelledby="planner-heading">
    <div>
        <h1 id="planner-heading"><i class="fas fa-calendar-week me-2"></i>Planificación semanal</h1>
        <p>Arrastrá turnos o usá una jornada fija para completar rápidamente una semana laboral.</p>
    </div>
    <?php if (!empty($data['selected_branch'])): ?>
    <span class="context-branch"><i class="fas fa-store me-1"></i><?php echo htmlspecialchars($data['selected_branch']->name); ?></span>
    <?php endif; ?>
</section>
<div class="shift-palette">
    <span class="palette-label"><i class="fas fa-grip-dots me-1"></i> Arrastr&aacute; a un d&iacute;a:</span>

    <?php foreach($data['shifts'] as $s):
        $pc = htmlspecialchars($s->color ?? '#6c757d');
        $pt = htmlspecialchars($s->shift_name);
        $hrs = '';
        if ($s->total_hours > 0) $hrs = ' <small style="opacity:.75">(' . number_format($s->total_hours,1) . 'h)</small>';
    ?>
    <div class="palette-chip"
         draggable="true"
         data-type="shift"
         data-shift-id="<?php echo $s->id; ?>"
         data-shift-color="<?php echo $pc; ?>"
         data-shift-name="<?php echo $pt; ?>"
         style="background:<?php echo $pc; ?>;">
        <i class="fas fa-tag" style="font-size:.65rem;"></i>
        <?php echo $pt . $hrs; ?>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($data['shifts'])): ?>
    <div class="palette-sep"></div>
    <?php endif; ?>

    <?php if (!empty($data['overtime_planner_enabled'])): ?>
    <div class="palette-chip" draggable="true" data-type="overtime" style="background:#e67e22;">
        <i class="fas fa-clock" style="font-size:.65rem;"></i> Horas Extra
    </div>
    <?php endif; ?>
    <div class="palette-chip" draggable="true" data-type="custom" style="background:#8b5cf6;">
        <i class="fas fa-user-clock" style="font-size:.65rem;"></i> Personalizado
    </div>

    <span class="ms-auto" style="font-size:.7rem;color:#9ca3af;">
        <a href="javascript:location.reload()" style="color:#9ca3af;" title="Recarga para ver turnos nuevos"><i class="fas fa-sync-alt me-1"></i>Actualizar turnos</a>
    </span>
</div>

<!-- â•â•â• NAV â•â•â• -->
<div class="planner-nav">
    <?php $branchQuery = !empty($data['selected_branch_id']) ? '&branch_id=' . (int)$data['selected_branch_id'] : ''; ?>
    <a href="<?php echo URLROOT; ?>/admin/weeklyPlanner?week=<?php echo $data['prev_week_string']; ?><?php echo $branchQuery; ?>"
       class="btn btn-outline-secondary btn-sm">&laquo; Anterior</a>

    <?php if (!empty($data['branch_scope_ready'])): ?>
    <form method="get" action="<?php echo URLROOT; ?>/admin/weeklyPlanner" class="d-flex align-items-center gap-2" aria-label="Seleccionar sucursal">
        <input type="hidden" name="week" value="<?php echo htmlspecialchars($data['current_week_string']); ?>">
        <label for="planner-branch" class="visually-hidden">Sucursal</label>
        <select name="branch_id" id="planner-branch" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ($data['branches'] as $branch): ?>
            <option value="<?php echo (int)$branch->id; ?>" <?php echo (int)$data['selected_branch_id'] === (int)$branch->id ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($branch->name . ' — ' . $branch->locality); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-sm btn-outline-primary">Ver</button></noscript>
    </form>
    <?php endif; ?>

    <?php if (!empty($data['branches']) && empty($data['branch_scope_ready'])): ?>
    <div class="small text-warning-emphasis" role="status">
        Aplicá <code>migration_branch_planner_scope.sql</code> para planificar por sucursal.
    </div>
    <?php endif; ?>

    <form action="<?php echo URLROOT; ?>/admin/applyTemplate" method="post" class="template-form">
        <input type="hidden" name="target_week" value="<?php echo $data['current_week_string']; ?>">
        <input type="hidden" name="branch_id" value="<?php echo (int)($data['selected_branch_id'] ?? 0); ?>">
        <input type="hidden" name="csrf_token"   value="<?php echo csrf_token(); ?>">
        <select name="template_id" class="form-select form-select-sm" required>
            <option value="" disabled selected>Aplicar plantilla...</option>
            <?php if(!empty($data['templates'])): foreach($data['templates'] as $tpl): ?>
                <option value="<?php echo $tpl->id; ?>"><?php echo htmlspecialchars($tpl->template_name); ?></option>
            <?php endforeach; endif; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary">Aplicar</button>
    </form>

    <div class="d-flex align-items-center gap-2">
        <div class="date-picker-container">
            <label for="date-picker" id="date-picker-icon" title="Ir a fecha"><i class="fas fa-calendar-alt"></i></label>
            <input type="date" id="date-picker">
        </div>
        <a href="<?php echo URLROOT; ?>/admin/weeklyPlanner?week=<?php echo $data['next_week_string']; ?><?php echo $branchQuery; ?>"
           class="btn btn-outline-secondary btn-sm">Siguiente &raquo;</a>
    </div>
</div>

<!-- â•â•â• PLANNER FORM â•â•â• -->
<form id="planner-form" action="<?php echo URLROOT; ?>/admin/weeklyPlanner" method="post">
    <input type="hidden" name="current_week" value="<?php echo $data['current_week_string']; ?>">
    <input type="hidden" name="branch_id" value="<?php echo (int)($data['selected_branch_id'] ?? 0); ?>">
    <input type="hidden" name="csrf_token"   value="<?php echo csrf_token(); ?>">

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive planner-table-wrap">
                <table class="table table-bordered planner-table mb-0">
                    <thead>
                        <tr>
                            <th class="user-col">Empleado / Horas</th>
                            <?php foreach($data['week_dates'] as $day):
                                $isHol = isset($data['holidays'][$day['full_date']]);
                            ?>
                            <th class="<?php echo $isHol ? 'is-holiday' : ''; ?>">
                                <?php echo $day['display']; ?>
                                <?php if($isHol): ?><div style="font-size:.62rem;opacity:.75;font-weight:400;">Feriado</div><?php endif; ?>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['users'] as $user):
                            $weekly_total = $data['weekly_totals'][$user->id];
                            $branch_total = $data['weekly_branch_totals'][$user->id] ?? $weekly_total;
                            $limit        = $user->weekly_hour_limit;
                            $badge_class  = 'bg-primary';
                            $gap_class = 'bg-success';
                            $gap_label = 'Meta cumplida';
                            if ($limit > 0 && $weekly_total > $limit)         { $badge_class = 'bg-danger'; }
                            elseif ($limit > 0 && $weekly_total > $limit*0.9) { $badge_class = 'bg-warning text-dark'; }
                            if ($limit > 0 && $weekly_total < $limit) {
                                $gap_class = 'bg-warning text-dark';
                                $gap_label = 'Faltan: ' . number_format($limit - $weekly_total, 1) . ' hs';
                            } elseif ($limit > 0 && $weekly_total > $limit) {
                                $gap_class = 'bg-danger';
                                $gap_label = 'Excede: ' . number_format($weekly_total - $limit, 1) . ' hs';
                            }
                        ?>
                        <tr data-user-row-id="<?php echo $user->id; ?>">
                            <td class="user-col">
                                <div><?php echo htmlspecialchars($user->full_name); ?></div>
                                <div class="hours-summary">
                                    <span class="badge <?php echo $badge_class; ?> weekly-total"
                                                                                    title="L&iacute;mite: <?php echo $limit; ?> hs"
                                                                                    data-weekly-limit="<?php echo (float)$limit; ?>"
                                                                                    data-weekly-offset="<?php echo (float)($weekly_total - $branch_total); ?>">
                                        Total: <?php echo number_format($weekly_total, 1); ?> / <?php echo number_format($limit, 1); ?> hs
                                    </span>
                                    <?php if (!empty($data['branch_scope_ready'])): ?>
                                    <span class="badge bg-info branch-weekly-total">Esta sede: <?php echo number_format($branch_total, 1); ?> hs</span>
                                    <?php endif; ?>
                                    <?php if ($limit > 0): ?>
                                    <span class="badge <?php echo $gap_class; ?> weekly-gap" aria-live="polite"><?php echo $gap_label; ?></span>
                                    <?php endif; ?>
                                    <span class="badge bg-info monthly-total-<?php echo $data['month1_num']; ?>">
                                        <?php echo $data['nombres_mes'][$data['month1_num']]; ?>: <?php echo number_format($data['monthly_totals'][$user->id][$data['month1_num']], 1); ?> hs
                                    </span>
                                    <?php if($data['month1_num'] != $data['month2_num']): ?>
                                    <span class="badge bg-secondary monthly-total-<?php echo $data['month2_num']; ?>">
                                        <?php echo $data['nombres_mes'][$data['month2_num']]; ?>: <?php echo number_format($data['monthly_totals'][$user->id][$data['month2_num']], 1); ?> hs
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <button type="button"
                                        class="btn btn-outline-secondary btn-sm mt-1 btn-repeat-schedule"
                                        data-user-id="<?php echo (int)$user->id; ?>"
                                        data-user-name="<?php echo htmlspecialchars($user->full_name, ENT_QUOTES); ?>"
                                        title="Repetir horario de esta semana">
                                    <i class="fas fa-calendar-plus"></i> Repetir
                                </button>
                                <button type="button"
                                        class="btn btn-fixed-week btn-sm mt-1 btn-fixed-weekday"
                                        data-user-id="<?php echo (int)$user->id; ?>"
                                        data-user-name="<?php echo htmlspecialchars($user->full_name, ENT_QUOTES); ?>"
                                        title="Copiar una jornada base todos los lunes a viernes">
                                    <i class="fas fa-business-time"></i> Jornada L–V
                                </button>
                            </td>

                            <?php foreach($data['week_dates'] as $day_index => $day):
                                $date    = $day['full_date'];
                                $entries = isset($data['schedules'][$user->id][$date]) ? $data['schedules'][$user->id][$date] : [];
                                $request = isset($data['requests'][$user->id][$date])  ? $data['requests'][$user->id][$date]  : null;
                                if ($request && $request->status !== 'Aprobado') { $request = null; }
                            ?>
                            <td class="day-cell <?php echo isset($data['holidays'][$date]) ? 'is-holiday' : ''; ?>"
                                data-user-id="<?php echo $user->id; ?>"
                                data-date="<?php echo $date; ?>"
                                data-day-index="<?php echo $day_index; ?>">

                                <?php if($request): ?>
                                <div class="request-badge"
                                     style="border-left-color:<?php echo htmlspecialchars($request->color); ?>;background:<?php echo htmlspecialchars($request->color); ?>18;color:#333;">
                                    <i class="fas fa-calendar-check me-1"></i><strong><?php echo htmlspecialchars($request->type_name); ?></strong>
                                </div>
                                <?php endif; ?>

                                <div class="entries-container">
                                    <?php foreach($entries as $idx => $entry):
                                        $otReadonly = ($entry && ($entry->type ?? '') === 'overtime' && empty($data['overtime_planner_enabled']));
                                        echo renderChip($user->id, $date, $idx, $entry, $data['shifts'], $otReadonly);
                                    endforeach; ?>
                                </div>

                                <div class="empty-label" <?php echo !empty($entries) ? 'style="display:none;"' : ''; ?>>
                                    <i class="fas fa-moon me-1" style="font-size:.65rem;"></i>Libre
                                </div>

                                <div class="cell-footer">
                                    <div class="add-pills">
                                        <button type="button" class="add-pill add-pill-shift"  onclick="addNewEntry(this,'shift')"><i class="fas fa-tag"></i> Turno</button>
                                        <?php if (!empty($data['overtime_planner_enabled'])): ?>
                                        <button type="button" class="add-pill add-pill-ot"     onclick="addNewEntry(this,'overtime')"><i class="fas fa-clock"></i> Extra</button>
                                        <?php endif; ?>
                                        <button type="button" class="add-pill add-pill-custom" onclick="addNewEntry(this,'custom')"><i class="fas fa-user-clock"></i> Custom</button>
                                        <?php if (function_exists('vacation_module_ready') && vacation_module_ready()): ?>
                                        <button type="button" class="add-pill" style="background:#0ea5e9;color:#fff" onclick="addNewEntry(this,'vacation')"><i class="fas fa-umbrella-beach"></i> Vacaciones</button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cell-actions mt-1">
                                        <button type="button" class="btn-cell btn-cell-edit"
                                                onclick="toggleCellEdit(this)"
                                            title="Editar d&iacute;a"><i class="fas fa-pencil-alt"></i></button>
                                        <button type="button" class="btn-cell btn-cell-copy"
                                                onclick="copyPrevDay(this)"
                                            title="Copiar d&iacute;a anterior"><i class="fas fa-copy"></i></button>
                                        <button type="button" class="btn-cell btn-cell-cancel"
                                                onclick="cancelEdit(this)"
                                            title="Cancelar edici&oacute;n"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</form>

<div class="save-week-bar">
    <span class="save-week-hint"><i class="fas fa-info-circle me-1"></i>Guard&aacute; d&iacute;as individualmente (✓) o toda la semana de golpe.</span>
    <button type="submit" form="planner-form" class="btn btn-primary btn-save-week">
        <i class="fas fa-save me-2"></i>Guardar Toda la Semana
    </button>
</div>

<script>
/* â”€â”€ Datos de turnos disponibles â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   Siempre actualizado desde PHP al cargar la pÃ¡gina.
   Nuevas entradas se construyen desde este array, evitando que el selector
   quede con turnos viejos si la pÃ¡gina no se recargÃ³.                        */
var PLANNER_OVERTIME_ENABLED = <?php echo !empty($data['overtime_planner_enabled']) ? 'true' : 'false'; ?>;

var plannerShifts = <?php echo json_script_safe(array_values(array_map(function($s){
    return ['id' => (int)$s->id, 'name' => $s->shift_name, 'color' => $s->color ?? '#6c757d', 'hours' => (float)($s->total_hours ?? 0)];
}, $data['shifts']))); ?>;

/* â”€â”€ Drag & Drop â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€*/
var _dragSource = null;   // 'chip' | 'palette'
var _dragEl     = null;   // elemento DOM arrastrado
var _palData    = null;   // { type, shiftId } cuando viene de paleta

document.addEventListener('dragstart', function(e) {
    var chip = e.target.closest('.entry-chip');
    if (chip) {
        if (chip.dataset.readonly === '1') {
            e.preventDefault();
            return;
        }
        _dragSource = 'chip';
        _dragEl     = chip;
        chip.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        return;
    }
    var pal = e.target.closest('.palette-chip');
    if (pal) {
        if ((pal.dataset.type || '') === 'overtime' && !PLANNER_OVERTIME_ENABLED) {
            e.preventDefault();
            return;
        }
        _dragSource = 'palette';
        _dragEl     = pal;
        _palData    = { type: pal.dataset.type || 'shift', shiftId: pal.dataset.shiftId || null };
        pal.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'copy';
    }
});

document.addEventListener('dragend', function() {
    if (_dragEl) _dragEl.classList.remove('is-dragging');
    _dragSource = _dragEl = _palData = null;
    document.querySelectorAll('.day-cell.drag-over').forEach(function(c){ c.classList.remove('drag-over'); });
});

/* DelegaciÃ³n de eventos en toda la tabla para drop zones */
document.querySelectorAll('.day-cell').forEach(function(cell) {
    cell.addEventListener('dragover', function(e) {
        if (!_dragSource) return;
        e.preventDefault();
        cell.classList.add('drag-over');
    });
    cell.addEventListener('dragleave', function(e) {
        if (!cell.contains(e.relatedTarget)) cell.classList.remove('drag-over');
    });
    cell.addEventListener('drop', function(e) {
        e.preventDefault();
        cell.classList.remove('drag-over');

        if (_dragSource === 'chip' && _dragEl) {
            if (_dragEl.dataset.readonly === '1') return;
            var srcCell = _dragEl.closest('.day-cell');
            if (srcCell === cell) return;
            renameChipInputs(_dragEl, cell.dataset.userId, cell.dataset.date);
            cell.querySelector('.entries-container').appendChild(_dragEl);
            if (srcCell) updateEmptyLabel(srcCell);
            enterEditMode(cell);
            updateEmptyLabel(cell);
            if (srcCell) refreshRowTotals(srcCell.closest('tr'));
            refreshRowTotals(cell.closest('tr'));
        }

        if (_dragSource === 'palette' && _palData) {
            if (_palData.type === 'overtime' && !PLANNER_OVERTIME_ENABLED) return;
            var chip = buildChipElement(cell.dataset.userId, cell.dataset.date, Date.now(), _palData.type, _palData.shiftId);
            cell.querySelector('.entries-container').appendChild(chip);
            enterEditMode(cell);
            updateEmptyLabel(cell);
            refreshRowTotals(cell.closest('tr'));
        }
    });
});

/* Actualiza los atributos name de todos los inputs/selects de un chip */
function renameChipInputs(chip, newUserId, newDate) {
    var newIdx = Date.now();
    chip.querySelectorAll('[name]').forEach(function(el) {
        el.name = el.name.replace(
            /^schedules\[\d+\]\[\d{4}-\d{2}-\d{2}\]\[\d+\]/,
            'schedules[' + newUserId + '][' + newDate + '][' + newIdx + ']'
        );
    });
}

/* â”€â”€ Edit mode â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€*/
function enterEditMode(cell) {
    cell.classList.add('is-editing');
    var btn = cell.querySelector('.btn-cell-edit');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-save"></i>';
        btn.title = 'Guardar d\u00eda';
        btn.classList.replace('btn-cell-edit', 'btn-cell-save');
        btn.onclick = function() { saveDay(cell, this); };
    }
}

function exitEditMode(cell) {
    cell.classList.remove('is-editing');
    var btn = cell.querySelector('.btn-cell-save');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-pencil-alt"></i>';
        btn.title = 'Editar d\u00eda';
        btn.classList.replace('btn-cell-save', 'btn-cell-edit');
        btn.onclick = function() { toggleCellEdit(this); };
    }
}

function toggleCellEdit(btn) {
    var cell = btn.closest('.day-cell');
    if (cell.classList.contains('is-editing')) { exitEditMode(cell); }
    else { enterEditMode(cell); }
}

function cancelEdit(btn) { exitEditMode(btn.closest('.day-cell')); }

/* â”€â”€ Save day (AJAX) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€*/
function saveDay(cell, btn) {
    var formData = new FormData(document.getElementById('planner-form'));
    formData.append('is_ajax', '1');
    formData.append('user_id', cell.dataset.userId);
    formData.append('date',    cell.dataset.date);
    formData.set('csrf_token', document.querySelector('#planner-form [name="csrf_token"]').value);

    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }

    fetch('<?php echo URLROOT; ?>/admin/saveDayAjax', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i>'; }
            refreshRowTotals(cell.closest('tr'));
            setTimeout(function() { exitEditMode(cell); }, 700);
        } else {
            alert('Error: ' + (data.message || 'Error desconocido'));
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i>'; }
        }
    })
    .catch(function() {
        alert('Error de conexi\u00f3n. Intenta de nuevo.');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i>'; }
    });
}

/* â”€â”€ Remove entry â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€*/
function removeEntry(btn) {
    var chip = btn.closest('.entry-chip');
    if (chip && chip.dataset.readonly === '1') return;
    var cell = btn.closest('.day-cell');
    chip.remove();
    updateEmptyLabel(cell);
    refreshRowTotals(cell.closest('tr'));
}

/* â”€â”€ Copy prev day â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€*/
function copyPrevDay(btn) {
    var cell     = btn.closest('.day-cell');
    var dayIndex = parseInt(cell.dataset.dayIndex);
    if (dayIndex === 0) { alert('No hay d\u00eda anterior para copiar.'); return; }

    var prevCell = cell.parentElement.children[dayIndex]; // children[0]=user-col td
    if (prevCell.querySelector('.request-badge')) {
        alert('No se puede copiar: el d\u00eda anterior tiene una solicitud aprobada.'); return;
    }

    var src = prevCell.querySelector('.entries-container');
    var tgt = cell.querySelector('.entries-container');
    if (!src) return;

    tgt.innerHTML = src.innerHTML
        .replace(new RegExp(prevCell.dataset.date, 'g'), cell.dataset.date)
        .replace(new RegExp('\\[' + prevCell.dataset.userId + '\\]', 'g'), '[' + cell.dataset.userId + ']');

    enterEditMode(cell);
    updateEmptyLabel(cell);
    refreshRowTotals(cell.closest('tr'));
}

/* â”€â”€ Add new entry (desde botones pill) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€*/
function addNewEntry(btn, type) {
    if (type === 'overtime' && !PLANNER_OVERTIME_ENABLED) return;
    var cell  = btn.closest('.day-cell');
    var shiftId = (type === 'shift' && plannerShifts.length > 0) ? plannerShifts[0].id : null;
    var chip  = buildChipElement(cell.dataset.userId, cell.dataset.date, Date.now(), type, shiftId);
    cell.querySelector('.entries-container').appendChild(chip);
    updateEmptyLabel(cell);
    refreshRowTotals(cell.closest('tr'));
}

/* â”€â”€ Construir chip desde JS (usa plannerShifts, siempre actualizado) â”€â”€â”€â”€â”€â”€â”€â”€â”€*/
function buildChipElement(userId, date, index, type, shiftId) {
    var color = '#6c757d', label = 'Turno';
    if (type === 'overtime') { color = '#e67e22'; label = 'Horas Extras'; }
    else if (type === 'custom') { color = '#8b5cf6'; label = 'Personalizado'; }
    else if (type === 'vacation') { color = '#0ea5e9'; label = 'VACACIONES'; }
    else if (type === 'leave') { color = '#a855f7'; label = 'Licencia'; }
    else {
        var sh = null;
        for (var i = 0; i < plannerShifts.length; i++) {
            if (plannerShifts[i].id == shiftId) { sh = plannerShifts[i]; break; }
        }
        if (!sh && plannerShifts.length > 0) { sh = plannerShifts[0]; shiftId = sh.id; }
        if (sh) { color = sh.color || '#6c757d'; label = sh.name; }
    }

    var prefix   = 'schedules[' + userId + '][' + date + '][' + index + ']';
    var showSel  = (type === 'shift')  ? '' : 'style="display:none;"';
    var showTime = (type === 'shift' || type === 'vacation' || type === 'leave') ? 'style="display:none;"' : '';

    var opts = '';
    for (var j = 0; j < plannerShifts.length; j++) {
        var s   = plannerShifts[j];
        var sel = (s.id == shiftId) ? ' selected' : '';
        opts += '<option value="' + s.id + '" data-color="' + escHtml(s.color) + '"' + sel + '>' + escHtml(s.name) + '</option>';
    }

    var div = document.createElement('div');
    div.className = 'entry-chip type-' + type;
    div.draggable = true;
    div.dataset.type = type;
    div.style.setProperty('--chip-color', color);
    div.innerHTML =
        '<div class="chip-drag-handle"><i class="fas fa-grip-vertical"></i></div>' +
        '<div class="chip-body">' +
            '<div class="chip-view">' +
                '<span class="chip-dot"></span>' +
                '<span class="chip-name">' + escHtml(label) + '</span>' +
            '</div>' +
            '<div class="chip-controls">' +
                '<input type="hidden" name="' + prefix + '[type]" value="' + type + '">' +
                '<div class="chip-selector" ' + showSel + '>' +
                    '<select class="form-select form-select-sm" name="' + prefix + '[shift_id]" onchange="updateChipFromSelect(this)">' +
                        opts +
                    '</select>' +
                '</div>' +
                '<div class="chip-times" ' + showTime + '>' +
                    '<div class="d-flex gap-1 align-items-center mt-1">' +
                        '<input type="time" class="form-control form-control-sm" name="' + prefix + '[start_time]" value="">' +
                        '<span class="text-muted small">-</span>' +
                        '<input type="time" class="form-control form-control-sm" name="' + prefix + '[end_time]" value="">' +
                    '</div>' +
                '</div>' +
                '<input type="text" class="form-control form-control-sm mt-1" name="' + prefix + '[notes]" placeholder="Nota (opcional)..." value="">' +
            '</div>' +
        '</div>' +
        '<button type="button" class="btn-chip-remove" onclick="removeEntry(this)" title="Eliminar"><i class="fas fa-times"></i></button>';
    return div;
}

/* â”€â”€ Actualizar color/label del chip al cambiar el select de turno â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€*/
function updateChipFromSelect(select) {
    var chip = select.closest('.entry-chip');
    if (!chip) return;
    var opt   = select.options[select.selectedIndex];
    var color = (opt && opt.dataset.color) ? opt.dataset.color : '#6c757d';
    chip.style.setProperty('--chip-color', color);
    var nameEl = chip.querySelector('.chip-name');
    if (nameEl) nameEl.textContent = opt ? opt.text : '';
    refreshRowTotals(chip.closest('tr'));
}

/* â”€â”€ Mostrar/ocultar "Libre" segÃºn haya chips â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€*/
function updateEmptyLabel(cell) {
    var container = cell.querySelector('.entries-container');
    var label     = cell.querySelector('.empty-label');
    if (!container || !label) return;
    label.style.display = (container.querySelectorAll('.entry-chip').length > 0) ? 'none' : '';
}

function parseTimeToMinutes(value) {
    if (!value || !/^\d{2}:\d{2}/.test(value)) return 0;
    var parts = value.split(':');
    return (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
}

function calculateRangeHours(startValue, endValue) {
    var startMinutes = parseTimeToMinutes(startValue);
    var endMinutes = parseTimeToMinutes(endValue);
    if (!startMinutes && !endMinutes) return 0;
    if (endMinutes < startMinutes) endMinutes += 24 * 60;
    return (endMinutes - startMinutes) / 60;
}

function getShiftHours(shiftId) {
    for (var i = 0; i < plannerShifts.length; i++) {
        if (String(plannerShifts[i].id) === String(shiftId)) {
            return parseFloat(plannerShifts[i].hours || 0);
        }
    }
    return 0;
}

function calculateChipHours(chip) {
    if (!chip) return 0;
    var typeInput = chip.querySelector('input[type="hidden"][name$="[type]"]');
    var type = typeInput ? typeInput.value : (chip.dataset.type || 'shift');

    if (type === 'shift') {
        var shiftSelect = chip.querySelector('select[name$="[shift_id]"]');
        return shiftSelect ? getShiftHours(shiftSelect.value) : 0;
    }

    var startInput = chip.querySelector('input[name$="[start_time]"]');
    var endInput = chip.querySelector('input[name$="[end_time]"]');
    return calculateRangeHours(startInput ? startInput.value : '', endInput ? endInput.value : '');
}

function updateWeeklyBadgeState(badge, total) {
    if (!badge) return;
    var limit = parseFloat(badge.dataset.weeklyLimit || 0);
    badge.className = 'badge weekly-total';

    if (limit > 0 && total > limit) {
        badge.classList.add('bg-danger');
    } else if (limit > 0 && total > (limit * 0.9)) {
        badge.classList.add('bg-warning', 'text-dark');
    } else {
        badge.classList.add('bg-primary');
    }
}

function updateWeeklyGap(row, total) {
    var badge = row.querySelector('.weekly-gap');
    var weekly = row.querySelector('.weekly-total');
    if (!badge || !weekly) return;
    var limit = parseFloat(weekly.dataset.weeklyLimit || 0);
    if (limit <= 0) return;
    badge.className = 'badge weekly-gap';
    if (total < limit) {
        badge.textContent = 'Faltan: ' + (limit - total).toFixed(1) + ' hs';
        badge.classList.add('bg-warning', 'text-dark');
    } else if (total > limit) {
        badge.textContent = 'Excede: ' + (total - limit).toFixed(1) + ' hs';
        badge.classList.add('bg-danger');
    } else {
        badge.textContent = 'Meta cumplida';
        badge.classList.add('bg-success');
    }
}

function refreshRowTotals(row) {
    if (!row) return;

    var weeklyTotal = 0;
    var monthlyTotals = {};

    row.querySelectorAll('.day-cell').forEach(function(cell) {
        var cellTotal = 0;
        cell.querySelectorAll('.entry-chip').forEach(function(chip) {
            cellTotal += calculateChipHours(chip);
        });

        weeklyTotal += cellTotal;

        var monthKey = String(parseInt(cell.dataset.date.split('-')[1], 10));
        monthlyTotals[monthKey] = (monthlyTotals[monthKey] || 0) + cellTotal;

        cell.classList.toggle('day-over-limit', cellTotal > 9);
        cell.title = cellTotal > 9 ? ('D\u00eda excedido: ' + cellTotal.toFixed(1) + ' hs') : '';
    });

    var weeklyBadge = row.querySelector('.weekly-total');
    if (weeklyBadge) {
        var globalTotal = weeklyTotal + parseFloat(weeklyBadge.dataset.weeklyOffset || 0);
        weeklyBadge.textContent = 'Total: ' + globalTotal.toFixed(1) + ' / ' + parseFloat(weeklyBadge.dataset.weeklyLimit || 0).toFixed(1) + ' hs';
        updateWeeklyBadgeState(weeklyBadge, globalTotal);
        updateWeeklyGap(row, globalTotal);
    }
    var branchBadge = row.querySelector('.branch-weekly-total');
    if (branchBadge) {
        branchBadge.textContent = 'Esta sede: ' + weeklyTotal.toFixed(1) + ' hs';
    }

    row.querySelectorAll('[class*="monthly-total-"]').forEach(function(badge) {
        var monthClass = Array.from(badge.classList).find(function(cls) {
            return cls.indexOf('monthly-total-') === 0;
        });
        if (!monthClass) return;

        var monthKey = monthClass.replace('monthly-total-', '');
        var label = badge.textContent.split(':')[0];
        var total = monthlyTotals[monthKey] || 0;
        badge.textContent = label + ': ' + total.toFixed(1) + ' hs';
    });
}

document.getElementById('planner-form').addEventListener('input', function(e) {
    var cell = e.target.closest('.day-cell');
    if (cell) refreshRowTotals(cell.closest('tr'));
});

document.getElementById('planner-form').addEventListener('change', function(e) {
    var cell = e.target.closest('.day-cell');
    if (cell) refreshRowTotals(cell.closest('tr'));
});

/* â”€â”€ Date picker â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€*/
document.getElementById('date-picker').addEventListener('change', function(e) {
    if (!e.target.value) return;
    var d = new Date(e.target.value);
    d.setMinutes(d.getMinutes() + d.getTimezoneOffset());
    var jan4  = new Date(d.getFullYear(), 0, 4);
    var start = new Date(jan4.getFullYear(), jan4.getMonth(), jan4.getDate() - (jan4.getDay() + 6) % 7);
    var week  = 1 + Math.floor((d - start) / (7 * 86400000));
    window.location.href = '<?php echo URLROOT; ?>/admin/weeklyPlanner?week=' + d.getFullYear() + '-W' + String(week).padStart(2, '0') + '<?php echo $branchQuery; ?>';
});

/* â”€â”€ Escape HTML â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€*/
function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* â”€â”€ Inicializar empty labels al cargar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€*/
document.querySelectorAll('.day-cell').forEach(function(c) {
    updateEmptyLabel(c);
});
document.querySelectorAll('tr[data-user-row-id]').forEach(function(row) {
    refreshRowTotals(row);
});

/* Repetir horario de la semana visible */
(function() {
    var modalEl = document.getElementById('repeatScheduleModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    var modal = new bootstrap.Modal(modalEl);
    var form = document.getElementById('repeatScheduleForm');
    var weekStart = '<?php echo htmlspecialchars($data['week_dates'][0]['full_date'] ?? date('Y-m-d')); ?>';
    var weekEnd = '<?php echo htmlspecialchars(end($data['week_dates'])['full_date'] ?? date('Y-m-d')); ?>';
    var monthEnd = '<?php echo date('Y-m-t', strtotime($data['week_dates'][0]['full_date'] ?? 'now')); ?>';

    document.querySelectorAll('.btn-repeat-schedule').forEach(function(btn) {
        btn.addEventListener('click', function() {
            form.querySelector('[name="user_id"]').value = btn.getAttribute('data-user-id');
            document.getElementById('repeatScheduleUserName').textContent = btn.getAttribute('data-user-name');
            var parts = weekEnd.split('-');
            var y = parseInt(parts[0], 10);
            var m = parseInt(parts[1], 10) - 1;
            var d = parseInt(parts[2], 10) + 1;
            var nextMon = new Date(y, m, d);
            var fromDefault = nextMon.getFullYear() + '-'
                + String(nextMon.getMonth() + 1).padStart(2, '0') + '-'
                + String(nextMon.getDate()).padStart(2, '0');
            form.querySelector('[name="from_date"]').value = fromDefault;
            form.querySelector('[name="to_date"]').value = monthEnd;
            modal.show();
        });
    });
})();

/* Jornada fija: una fecha base se replica en cada lunes a viernes del rango. */
(function() {
    var modalEl = document.getElementById('fixedWeekdayModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    var modal = new bootstrap.Modal(modalEl);
    var form = document.getElementById('fixedWeekdayForm');
    var weekStart = '<?php echo htmlspecialchars($data['week_dates'][0]['full_date'] ?? date('Y-m-d')); ?>';
    var weekEnd = '<?php echo htmlspecialchars(end($data['week_dates'])['full_date'] ?? date('Y-m-d')); ?>';
    var monthEnd = '<?php echo date('Y-m-t', strtotime($data['week_dates'][0]['full_date'] ?? 'now')); ?>';
    document.querySelectorAll('.btn-fixed-weekday').forEach(function(btn) {
        btn.addEventListener('click', function() {
            form.querySelector('[name="user_id"]').value = btn.getAttribute('data-user-id');
            document.getElementById('fixedWeekdayUserName').textContent = btn.getAttribute('data-user-name');
            form.querySelector('[name="source_date"]').value = weekStart;
            var nextMonday = new Date(weekEnd + 'T12:00:00');
            nextMonday.setDate(nextMonday.getDate() + 1);
            form.querySelector('[name="from_date"]').value = nextMonday.toISOString().slice(0, 10);
            form.querySelector('[name="to_date"]').value = monthEnd;
            modal.show();
        });
    });
})();
</script>

<div class="modal fade" id="repeatScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="repeatScheduleForm" method="post" action="<?php echo URLROOT; ?>/admin/applySchedulePattern">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="user_id" value="">
                <input type="hidden" name="ref_week" value="<?php echo htmlspecialchars($data['current_week_string']); ?>">
                <input type="hidden" name="return_week" value="<?php echo htmlspecialchars($data['current_week_string']); ?>">
                <input type="hidden" name="branch_id" value="<?php echo (int)($data['selected_branch_id'] ?? 0); ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Repetir horario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Copia el patrón de <strong>esta semana</strong>
                        (<?php echo htmlspecialchars($data['week_dates'][0]['full_date']); ?>
                        – <?php echo htmlspecialchars(end($data['week_dates'])['full_date']); ?>)
                        para <strong id="repeatScheduleUserName"></strong>.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Desde</label>
                        <input type="date" name="from_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="to_date" class="form-control" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="overwrite" value="1" id="repeatOverwrite" checked>
                        <label class="form-check-label" for="repeatOverwrite">Sobrescribir días que ya tengan turnos</label>
                    </div>
                    <p class="form-text mb-0">Sin marcar la casilla, solo se completan días vacíos.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Aplicar al rango</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="fixedWeekdayModal" tabindex="-1" aria-labelledby="fixedWeekdayTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="fixedWeekdayForm" method="post" action="<?php echo URLROOT; ?>/admin/applyFixedWeekdaySchedule">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="user_id" value="">
                <input type="hidden" name="return_week" value="<?php echo htmlspecialchars($data['current_week_string']); ?>">
                <input type="hidden" name="branch_id" value="<?php echo (int)($data['selected_branch_id'] ?? 0); ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="fixedWeekdayTitle"><i class="fas fa-business-time me-2 text-success"></i>Jornada fija de lunes a viernes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Se copiará la jornada de la fecha base para <strong id="fixedWeekdayUserName"></strong> en cada día hábil del rango. Sábados y domingos quedan sin cambios.</p>
                    <div class="mb-3">
                        <label class="form-label" for="fixedSourceDate">Fecha base con el horario correcto</label>
                        <input id="fixedSourceDate" type="date" name="source_date" class="form-control" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label" for="fixedFromDate">Desde</label><input id="fixedFromDate" type="date" name="from_date" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label" for="fixedToDate">Hasta</label><input id="fixedToDate" type="date" name="to_date" class="form-control" required></div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="overwrite" value="1" id="fixedOverwrite" checked>
                        <label class="form-check-label" for="fixedOverwrite">Sobrescribir días hábiles con turnos existentes</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-copy me-1"></i>Aplicar jornada fija</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
