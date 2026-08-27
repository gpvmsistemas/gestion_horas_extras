<?php if ((new User())->isAttendanceControlReady()): ?>
<div class="mb-3">
    <label for="attendance_control_mode" class="form-label">Control de asistencia</label>
    <?php $attendanceMode = User::normalizeAttendanceControlMode($data['attendance_control_mode'] ?? ($data['user']->attendance_control_mode ?? 'required')); ?>
    <select name="attendance_control_mode" id="attendance_control_mode" class="form-select">
        <?php foreach (User::attendanceControlOptions() as $key => $label): ?>
        <option value="<?php echo $key; ?>" <?php echo $attendanceMode === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
        <?php endforeach; ?>
    </select>
    <div class="form-text">Flexible muestra las marcas sin alertas. Sin reloj excluye al empleado de tardanzas y ausencias automáticas.</div>
</div>
<?php endif; ?>
