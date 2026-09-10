<?php require APPROOT . '/views/inc/header.php'; ?>



<div class="emp-page-header">

    <a href="<?php echo URLROOT; ?>/employee/index" class="emp-back-btn">

        <i class="fas fa-arrow-left"></i>

    </a>

    <div>

        <h1 class="emp-page-title">Horas Extras</h1>

        <p class="emp-page-subtitle">Registrá las horas trabajadas fuera de tu horario</p>

    </div>

</div>



<div class="emp-card emp-form-card">

    <div class="emp-card-header">

        <i class="fas fa-plus-circle me-2" style="color:var(--clr-primary)"></i>

        <span>Nueva carga</span>

    </div>

    <form action="<?php echo URLROOT; ?>/employee/add" method="post" id="formHorasExtras">

        <?php echo csrf_field(); ?>

        <div class="emp-form-group">

            <label class="emp-label">Fecha</label>

            <input type="date" name="date" class="emp-input" value="<?php echo date('Y-m-d'); ?>" required>

        </div>

        <div class="emp-form-row">

            <div class="emp-form-group">

                <label class="emp-label">Hora inicio</label>

                <input type="time" name="start_time" class="emp-input" required>

            </div>

            <div class="emp-form-group">

                <label class="emp-label">Hora fin</label>

                <input type="time" name="end_time" class="emp-input" required>

            </div>

        </div>

        <div class="emp-form-group">

            <label class="emp-label">¿Es feriado?</label>

            <div class="emp-radio-group">

                <label class="emp-radio-option">

                    <input type="radio" name="is_holiday" value="0" checked>

                    <span class="emp-radio-label">No</span>

                </label>

                <label class="emp-radio-option">

                    <input type="radio" name="is_holiday" value="1">

                    <span class="emp-radio-label">Sí — Feriado</span>

                </label>

            </div>

        </div>

        <div class="emp-form-group">

            <label class="emp-label">Motivo</label>

            <textarea name="reason" class="emp-input emp-textarea" placeholder="Ej: Reemplazo de compañero, cierre de mes..." rows="3" required></textarea>

        </div>

        <button type="submit" class="emp-btn-primary w-100" id="btnGuardarHoras">

            <i class="fas fa-save me-2"></i>Guardar horas

        </button>

    </form>

</div>



<?php

$monthSummary = $data['month_summary'] ?? ['count' => 0, 'hours_total' => 0, 'pending_count' => 0, 'archived_count' => 0];

$monthEntries = $data['month_entries'] ?? [];

$highlightId = !empty($data['overtime_feedback']['saved']['id']) ? (int)$data['overtime_feedback']['saved']['id'] : 0;

?>



<div class="emp-month-summary mt-4" id="monthSummary">

    <div class="emp-section-title">

        <i class="fas fa-calendar-check me-2" style="color:var(--clr-primary)"></i>

        <?php echo htmlspecialchars($data['month_label'] ?? 'Este mes'); ?>

    </div>

    <div class="emp-card emp-month-card">

        <div class="emp-month-stats">

            <div class="emp-month-stat">

                <span class="emp-month-stat-value"><?php echo (int)$monthSummary['count']; ?></span>

                <span class="emp-month-stat-label">Cargas</span>

            </div>

            <div class="emp-month-stat">

                <span class="emp-month-stat-value"><?php echo number_format($monthSummary['hours_total'], 1); ?>h</span>

                <span class="emp-month-stat-label">Total</span>

            </div>

            <div class="emp-month-stat">

                <span class="emp-month-stat-value"><?php echo (int)$monthSummary['pending_count']; ?></span>

                <span class="emp-month-stat-label">Pendientes</span>

            </div>

            <?php if (!empty($monthSummary['archived_count'])): ?>

            <div class="emp-month-stat">

                <span class="emp-month-stat-value"><?php echo (int)$monthSummary['archived_count']; ?></span>

                <span class="emp-month-stat-label">Cerradas</span>

            </div>

            <?php endif; ?>

        </div>



        <?php if (empty($monthEntries)): ?>

        <p class="text-muted small mb-0 px-3 pb-3">Todavía no cargaste horas extras este mes.</p>

        <?php else: ?>

        <div class="emp-month-list">

            <?php foreach ($monthEntries as $entry): ?>

            <?php $isNew = $highlightId > 0 && (int)$entry->id === $highlightId; ?>

            <div class="emp-ot-card <?php echo $isNew ? 'emp-ot-card--highlight' : ''; ?>">

                <div class="emp-ot-card-left">

                    <span class="emp-ot-card-date"><?php echo date('d', strtotime($entry->entry_date)); ?></span>

                    <span class="emp-ot-card-month"><?php

                        $mesesES = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun',

                                    '07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];

                        echo $mesesES[date('m', strtotime($entry->entry_date))] ?? date('m', strtotime($entry->entry_date));

                    ?></span>

                </div>

                <div class="emp-ot-card-body">

                    <div class="emp-ot-card-time">

                        <i class="fas fa-clock me-1"></i>

                        <?php echo date('H:i', strtotime($entry->start_time)) . ' – ' . date('H:i', strtotime($entry->end_time)); ?>

                    </div>

                    <div class="emp-ot-card-reason"><?php echo htmlspecialchars($entry->reason); ?></div>

                    <?php if ($isNew): ?>

                    <span class="badge bg-success mt-1">Recién cargada</span>

                    <?php endif; ?>

                </div>

                <div class="emp-ot-card-right">

                    <span class="emp-ot-card-badge"><?php echo number_format(overtime_entry_total_hours($entry), 1); ?>h</span>

                    <span class="badge <?php echo overtime_status_badge_class($entry->status); ?> mt-1">

                        <?php echo htmlspecialchars(overtime_status_label($entry->status)); ?>

                    </span>

                    <?php if ($entry->is_holiday): ?>

                    <span class="emp-ot-card-holiday">Feriado</span>

                    <?php endif; ?>

                </div>

            </div>

            <?php endforeach; ?>

        </div>

        <?php endif; ?>

    </div>

</div>



<div class="emp-section-title mt-4">

    <i class="fas fa-hourglass-half me-2" style="color:var(--clr-warning)"></i>Pendientes de cierre

</div>



<?php if (empty($data['entries'])): ?>

<div class="emp-card">

    <div class="emp-empty">

        <i class="fas fa-check-circle" style="color:var(--clr-success)"></i>

        <p>No tenés cargas pendientes de cierre</p>

        <small class="text-muted">Las horas que cargues aparecerán aquí hasta que RRHH cierre el período.</small>

    </div>

</div>

<?php else: ?>

<?php foreach ($data['entries'] as $entry): ?>

<div class="emp-ot-card">

    <div class="emp-ot-card-left">

        <span class="emp-ot-card-date"><?php echo date('d', strtotime($entry->entry_date)); ?></span>

        <span class="emp-ot-card-month"><?php

            $mesesES = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun',

                        '07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];

            echo $mesesES[date('m', strtotime($entry->entry_date))] ?? date('m', strtotime($entry->entry_date));

        ?></span>

    </div>

    <div class="emp-ot-card-body">

        <div class="emp-ot-card-time">

            <i class="fas fa-clock me-1"></i>

            <?php echo date('H:i', strtotime($entry->start_time)) . ' – ' . date('H:i', strtotime($entry->end_time)); ?>

        </div>

        <div class="emp-ot-card-reason"><?php echo htmlspecialchars($entry->reason); ?></div>

    </div>

    <div class="emp-ot-card-right">

        <span class="emp-ot-card-badge"><?php echo number_format(overtime_entry_total_hours($entry), 1); ?>h</span>

        <?php if ($entry->is_holiday): ?>

        <span class="emp-ot-card-holiday">Feriado</span>

        <?php endif; ?>

    </div>

</div>

<?php endforeach; ?>

<?php endif; ?>



<?php if (!empty($data['history'])): ?>

<div class="emp-section-title mt-4">

    <i class="fas fa-history me-2" style="color:var(--clr-primary)"></i>Períodos cerrados

</div>

<?php foreach ($data['history'] as $entry): ?>

<div class="emp-ot-card opacity-75">

    <div class="emp-ot-card-body">

        <div class="emp-ot-card-time"><?php echo date('d/m/Y', strtotime($entry->entry_date)); ?> · <?php echo date('H:i', strtotime($entry->start_time)) . ' – ' . date('H:i', strtotime($entry->end_time)); ?></div>

        <div class="emp-ot-card-reason"><?php echo htmlspecialchars($entry->reason); ?></div>

    </div>

    <span class="badge <?php echo overtime_status_badge_class($entry->status); ?>"><?php echo htmlspecialchars(overtime_status_label($entry->status)); ?></span>

</div>

<?php endforeach; ?>

<?php endif; ?>



<div class="emp-page-bottom-spacer d-lg-none" aria-hidden="true"></div>



<?php require APPROOT . '/views/inc/footer.php'; ?>



<?php if (!empty($data['overtime_feedback'])): ?>
<script>window.OVERTIME_FEEDBACK = <?php echo json_encode($data['overtime_feedback'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;</script>
<?php endif; ?>
<?php if (!empty($_SESSION['overtime_flash_error'])): ?>
<script>window.OVERTIME_ERROR = <?php echo json_encode($_SESSION['overtime_flash_error'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;</script>
<?php unset($_SESSION['overtime_flash_error']); ?>
<?php endif; ?>
<script src="<?php echo URLROOT; ?>/js/employee-overtime.js"></script>

