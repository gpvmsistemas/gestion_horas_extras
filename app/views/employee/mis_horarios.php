<?php require APPROOT . '/views/inc/header.php'; ?>

<?php
$diasES  = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves',
            'Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
$diasCortos = ['Monday'=>'Lun','Tuesday'=>'Mar','Wednesday'=>'Mié','Thursday'=>'Jue',
               'Friday'=>'Vie','Saturday'=>'Sáb','Sunday'=>'Dom'];
$mesesES = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril',
            'May'=>'Mayo','June'=>'Junio','July'=>'Julio','August'=>'Agosto',
            'September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];

$currentMonth = $data['currentMonth'];
$prevMonth    = date('Y-m', strtotime($currentMonth . '-01 -1 month'));
$nextMonth    = date('Y-m', strtotime($currentMonth . '-01 +1 month'));
$monthName    = $mesesES[date('F', strtotime($currentMonth . '-01'))] ?? date('F', strtotime($currentMonth . '-01'));
$yearNum      = date('Y', strtotime($currentMonth . '-01'));
$scheduleByDate = $data['scheduleByDate'];
?>

<!-- ══ ENCABEZADO ══ -->
<div class="emp-page-header">
    <a href="<?php echo URLROOT; ?>/employee/index" class="emp-back-btn">
        <i class="fas fa-arrow-left"></i>
    </a>
    <div>
        <h1 class="emp-page-title">Mis Horarios</h1>
        <p class="emp-page-subtitle">Turnos y ausencias programadas</p>
    </div>
</div>

<!-- ══ NAVEGADOR DE MES ══ -->
<div class="emp-month-nav">
    <a href="?mes=<?php echo $prevMonth; ?>" class="emp-month-btn">
        <i class="fas fa-chevron-left"></i>
    </a>
    <span class="emp-month-label"><?php echo $monthName . ' ' . $yearNum; ?></span>
    <a href="?mes=<?php echo $nextMonth; ?>" class="emp-month-btn">
        <i class="fas fa-chevron-right"></i>
    </a>
</div>

<!-- ══ VISTA TOGGLE: LISTA / CALENDARIO ══ -->
<div class="emp-view-toggle">
    <button class="emp-view-btn active" id="btnViewList" onclick="switchView('list')">
        <i class="fas fa-list me-1"></i>Lista
    </button>
    <button class="emp-view-btn" id="btnViewCal" onclick="switchView('cal')">
        <i class="fas fa-calendar me-1"></i>Calendario
    </button>
</div>

<!-- ══ VISTA LISTA ══ -->
<div id="viewList">
<?php if(empty($scheduleByDate)): ?>
<div class="emp-card">
    <div class="emp-empty">
        <i class="fas fa-calendar-xmark"></i>
        <p>No hay turnos programados para <?php echo $monthName; ?></p>
    </div>
</div>
<?php else: ?>
<?php
// Agrupar por semana
$byWeek = [];
foreach($scheduleByDate as $date => $entries) {
    $weekNum = date('W', strtotime($date));
    foreach($entries as $e) {
        $byWeek[$weekNum][$date][] = $e;
    }
}
foreach($byWeek as $weekNum => $daysInWeek):
    $firstDay = array_key_first($daysInWeek);
    $lastDay  = array_key_last($daysInWeek);
?>
<div class="emp-week-group">
    <div class="emp-week-label">
        Semana <?php echo (int)$weekNum; ?>
        <span class="emp-week-range"><?php echo date('d', strtotime($firstDay)) . '–' . date('d', strtotime($lastDay)); ?></span>
    </div>
    <?php foreach($daysInWeek as $date => $entries): ?>
    <?php $isToday = ($date === date('Y-m-d')); ?>
    <?php foreach($entries as $entry): ?>
    <div class="emp-shift-item <?php echo $isToday ? 'today' : ''; ?>"
         style="border-left:4px solid <?php echo htmlspecialchars($entry->color ?? '#e91e8c'); ?>">
        <div class="emp-shift-date">
            <?php
            $dn = date('l', strtotime($date));
            echo ($diasCortos[$dn] ?? $dn) . ' ' . date('j');
            if($isToday) echo ' <span class="badge rounded-pill" style="background:var(--clr-primary);color:#fff;font-size:.6rem;vertical-align:middle">HOY</span>';
            ?>
        </div>
        <div class="emp-shift-info">
            <span class="emp-shift-name"><?php echo htmlspecialchars(employee_schedule_entry_label($entry)); ?></span>
            <?php if($entry->start_time && $entry->end_time): ?>
            <span class="emp-shift-hours">
                <i class="fas fa-clock me-1"></i><?php echo substr($entry->start_time,0,5); ?> – <?php echo substr($entry->end_time,0,5); ?>
            </span>
            <?php endif; ?>
            <?php if(!empty($entry->notes)): ?>
            <span class="emp-shift-note"><i class="fas fa-sticky-note me-1"></i><?php echo htmlspecialchars($entry->notes); ?></span>
            <?php endif; ?>
        </div>
        <?php if (!empty($entry->id) && strtotime($date) >= strtotime('today')): ?>
        <a href="<?php echo URLROOT; ?>/request/index?tab=swap&amp;schedule_id=<?php echo (int)$entry->id; ?>"
           class="emp-shift-swap-link" title="Solicitar cambio de turno"><i class="fas fa-exchange-alt"></i></a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div><!-- /viewList -->

<!-- ══ VISTA CALENDARIO ══ -->
<div id="viewCal" style="display:none">
    <div class="emp-card" style="padding:0;overflow:hidden">
        <div id="empCalendar"></div>
    </div>
</div>

<div class="emp-page-bottom-spacer d-lg-none" aria-hidden="true"></div>

<?php require APPROOT . '/views/inc/footer.php'; ?>

<script>
function switchView(v) {
    document.getElementById('viewList').style.display = v === 'list' ? '' : 'none';
    document.getElementById('viewCal').style.display  = v === 'cal'  ? '' : 'none';
    document.getElementById('btnViewList').classList.toggle('active', v === 'list');
    document.getElementById('btnViewCal').classList.toggle('active', v === 'cal');
    if (v === 'cal' && !window._calInit) {
        window._calInit = true;
        const calEl = document.getElementById('empCalendar');
        const cal = new FullCalendar.Calendar(calEl, {
            initialView: 'dayGridMonth',
            locale: 'es',
            initialDate: '<?php echo $currentMonth; ?>-01',
            headerToolbar: false,
            height: 'auto',
            events: <?php echo $data['calendarEvents']; ?>,
            eventDisplay: 'block',
        });
        cal.render();
    }
}
</script>
