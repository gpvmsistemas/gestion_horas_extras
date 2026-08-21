<?php
// Encabezado canónico del módulo Registro de Horas (sistema admin-page-head
// de la suite, mismo patrón que Turnos/Asistencia/Solicitudes).
// Espera: $_rhActive (clave de $_rhTabs) y $data['org'/'orgLabel'].
$_rhTabs = [
    'vistaGeneral'  => ['fa-table',         'Vista general'],
    'carga'         => ['fa-plus-circle',   'Carga'],
    'horarios'      => ['fa-calendar-week', 'Por empleado'],
    'duplicar'      => ['fa-copy',          'Duplicación'],
    'cargaMasiva'   => ['fa-layer-group',   'Carga masiva'],
    'borradoMasivo' => ['fa-eraser',        'Borrado masivo'],
    'estados'       => ['fa-user-shield',   'Estados'],
];
?>
<div class="admin-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-clock"></i></div>
        <div class="admin-page-meta">
            <h2 class="page-title">Registro de Horas</h2>
            <p class="page-subtitle mb-0">
                Horarios planificados por sucursal ·
                <span class="badge" style="background:var(--clr-primary)"><?php echo htmlspecialchars($data['orgLabel'] ?? ''); ?></span>
            </p>
        </div>
    </div>
    <div class="admin-page-actions">
        <?php foreach ($_rhTabs as $_rhKey => $_rhTab): ?>
        <a href="<?php echo URLROOT; ?>/registroHoras/<?php echo $_rhKey; ?>"
           class="btn btn-sm <?php echo ($_rhActive ?? '') === $_rhKey ? 'btn-primary' : 'btn-outline-primary'; ?>">
            <i class="fas <?php echo $_rhTab[0]; ?> me-1"></i><?php echo $_rhTab[1]; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
