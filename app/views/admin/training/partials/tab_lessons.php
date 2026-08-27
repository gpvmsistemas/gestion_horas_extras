<?php /** @var $c,$cid,$data */ ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between">
                <span>Programa del curso</span>
                <a href="<?php echo URLROOT; ?>/trainingAdmin/courseEdit/<?php echo $cid; ?>?tab=lesson&edit_lesson=new" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Nueva lección</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($data['lessons'])): ?>
                <p class="p-4 text-muted mb-0">Sin lecciones. Creá la primera para armar el contenido del curso.</p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                <?php foreach ($data['lessons'] as $l): ?>
                    <div class="list-group-item d-flex align-items-center gap-2">
                        <span class="badge bg-light text-dark"><?php echo (int)$l->position; ?></span>
                        <i class="fas <?php echo learning_lesson_type_icon($l->content_type); ?> text-muted"></i>
                        <div class="flex-grow-1">
                            <strong><?php echo htmlspecialchars($l->title); ?></strong>
                            <span class="text-muted small"> · <?php echo (int)$l->duration_minutes; ?> min</span>
                        </div>
                        <a href="<?php echo URLROOT; ?>/trainingAdmin/previewLesson/<?php echo $cid; ?>/<?php echo (int)$l->id; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="Vista previa"><i class="fas fa-eye"></i></a>
                        <a href="<?php echo URLROOT; ?>/trainingAdmin/courseEdit/<?php echo $cid; ?>?tab=lesson&edit_lesson=<?php echo (int)$l->id; ?>" class="btn btn-sm btn-primary">Editar</a>
                        <form method="post" action="<?php echo URLROOT; ?>/trainingAdmin/deleteLesson/<?php echo $cid; ?>/<?php echo (int)$l->id; ?>" class="d-inline" onsubmit="return confirm('¿Eliminar?')">
                            <?php echo csrf_field(); ?>
                            <button class="btn btn-sm btn-outline-danger" aria-label="Eliminar lección" title="Eliminar lección"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow border-0">
            <div class="card-body">
                <h6 class="fw-bold"><i class="fab fa-youtube text-danger me-1"></i> Videos (YouTube embebido)</h6>
                <p class="small text-muted mb-3">En <strong>Lecciones → Editar</strong>: tipo <strong>Video</strong> y pegá el enlace. El empleado lo ve dentro del curso.</p>
                <h6 class="fw-bold"><i class="fas fa-lightbulb text-warning me-1"></i> Consejos</h6>
                <ul class="small text-muted mb-0 ps-3">
                    <li>Combiná videos, texto y archivos Excel/PDF de ejemplo.</li>
                    <li>En cada lección podés agregar objetivos, puntos clave y notas para el alumno.</li>
                    <li>Subí plantillas en la pestaña <strong>Materiales</strong>.</li>
                    <li>Respondé preguntas en <strong>Comunidad</strong>.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
