<?php
$c = $data['course'];
$cid = $c ? (int)$c->id : 0;
$editLessonId = (int)($_GET['edit_lesson'] ?? 0);
$editLesson = null;
if ($editLessonId) {
    foreach ($data['lessons'] as $l) {
        if ((int)$l->id === $editLessonId) {
            $editLesson = $l;
            break;
        }
    }
}
require APPROOT . '/views/inc/header.php';
?>
<link rel="stylesheet" href="<?php echo URLROOT; ?>/css/learning.css">
<h2 class="page-title mb-3"><?php echo $c ? 'Editar curso' : 'Nuevo curso'; ?></h2>
<a href="<?php echo URLROOT; ?>/trainingAdmin/courses" class="btn btn-sm btn-outline-secondary mb-3">&larr; Volver</a>

<div class="card shadow mb-4">
    <div class="card-header">Datos del curso</div>
    <div class="card-body">
        <form method="post" action="<?php echo URLROOT; ?>/trainingAdmin/courseEdit/<?php echo $cid; ?>">
            <?php echo csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Título</label>
                    <input type="text" name="title" class="form-control" required value="<?php echo $c ? htmlspecialchars($c->title) : ''; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Área (opcional)</label>
                    <select name="area_id" class="form-select">
                        <option value="">Toda la empresa</option>
                        <?php foreach ($data['areas'] as $ar): ?>
                        <option value="<?php echo (int)$ar->id; ?>" <?php echo $c && (int)$c->area_id === (int)$ar->id ? 'selected' : ''; ?>><?php echo htmlspecialchars($ar->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripción</label>
                    <textarea name="description" class="form-control" rows="2"><?php echo $c ? htmlspecialchars($c->description) : ''; ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estrellas</label>
                    <input type="number" name="stars_on_complete" class="form-control" min="1" max="50" value="<?php echo $c ? (int)$c->stars_on_complete : 5; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">% aprobación</label>
                    <input type="number" name="passing_score" class="form-control" min="50" max="100" value="<?php echo $c ? (int)$c->passing_score : 70; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Intentos quiz</label>
                    <input type="number" name="max_quiz_attempts" class="form-control" min="1" max="10" value="<?php echo $c ? (int)$c->max_quiz_attempts : 3; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Minutos est.</label>
                    <input type="number" name="estimated_minutes" class="form-control" value="<?php echo $c ? (int)$c->estimated_minutes : 60; ?>">
                    <div class="row g-2 mt-2"><div class="col"><label class="form-label">Horas de formación</label><input type="number" step="0.25" min="0" name="duration_hours" class="form-control" value="<?php echo $c ? htmlspecialchars($c->duration_hours ?? '') : ''; ?>"></div><div class="col"><label class="form-label">Vigencia certificado (días)</label><input type="number" min="1" name="certificate_valid_days" class="form-control" value="<?php echo $c ? htmlspecialchars($c->certificate_valid_days ?? '') : ''; ?>"></div></div>
                </div>
                <div class="col-12">
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="require_quiz" class="form-check-input" id="rq" <?php echo !$c || $c->require_quiz ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="rq">Cuestionario obligatorio</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="is_published" class="form-check-input" id="pub" <?php echo $c && $c->is_published ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="pub">Publicado</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Guardar curso</button>
        </form>
    </div>
</div>

<?php if ($cid): ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Lecciones</span>
                <small class="text-muted">Video: enlace YouTube/Vimeo o archivo MP4</small>
            </div>
            <div class="card-body">
                <?php if (empty($data['lessons'])): ?>
                <p class="text-muted small">Todavía no hay lecciones. Agregá la primera abajo.</p>
                <?php else: ?>
                <?php foreach ($data['lessons'] as $l):
                    $hasVideo = $l->content_type === 'video' && !empty($l->content_url);
                    $hasFile = $l->content_type === 'file' && !empty($l->content_url);
                ?>
                <div class="lrn-lesson-item d-flex align-items-start gap-2">
                    <div class="flex-grow-1">
                        <span class="badge bg-secondary badge-type me-1"><?php echo htmlspecialchars($l->content_type); ?></span>
                        <strong><?php echo (int)$l->position; ?>.</strong>
                        <?php echo htmlspecialchars($l->title); ?>
                        <?php if ($hasVideo): ?>
                        <span class="text-muted small d-block mt-1">
                            <i class="fas fa-link"></i>
                            <?php echo learning_is_embed_video_url($l->content_url) ? 'Enlace externo' : 'Video subido'; ?>
                        </span>
                        <?php elseif ($hasFile): ?>
                        <span class="text-muted small d-block mt-1"><i class="fas fa-file-pdf"></i> PDF adjunto</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-nowrap">
                        <a href="<?php echo URLROOT; ?>/trainingAdmin/courseEdit/<?php echo $cid; ?>?edit_lesson=<?php echo (int)$l->id; ?>#lesson-form" class="btn btn-sm btn-outline-primary">Editar</a>
                        <form method="post" action="<?php echo URLROOT; ?>/trainingAdmin/deleteLesson/<?php echo $cid; ?>/<?php echo (int)$l->id; ?>" class="d-inline" onsubmit="return confirm('¿Eliminar lección?')">
                            <?php echo csrf_field(); ?>
                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <div class="lrn-lesson-form-panel mt-3" id="lesson-form">
                    <h6 class="mb-2"><?php echo $editLesson ? 'Editar lección' : 'Nueva lección'; ?></h6>
                    <form method="post" action="<?php echo URLROOT; ?>/trainingAdmin/saveLesson/<?php echo $cid; ?>" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <?php if ($editLesson): ?>
                        <input type="hidden" name="lesson_id" value="<?php echo (int)$editLesson->id; ?>">
                        <?php endif; ?>
                        <div class="row g-2 mb-2">
                            <div class="col-2">
                                <label class="form-label small">Nº</label>
                                <input type="number" name="position" class="form-control form-control-sm" min="1"
                                    value="<?php echo $editLesson ? (int)$editLesson->position : count($data['lessons']) + 1; ?>">
                            </div>
                            <div class="col-10">
                                <label class="form-label small">Título</label>
                                <input type="text" name="title" class="form-control form-control-sm" required
                                    value="<?php echo $editLesson ? htmlspecialchars($editLesson->title) : ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Tipo de contenido</label>
                                <select name="content_type" id="lessonContentType" class="form-select form-select-sm">
                                    <?php
                                    $types = ['text' => 'Texto', 'video' => 'Video', 'file' => 'PDF / archivo'];
                                    $curType = $editLesson ? $editLesson->content_type : 'text';
                                    foreach ($types as $val => $label):
                                    ?>
                                    <option value="<?php echo $val; ?>" <?php echo $curType === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Duración (min)</label>
                                <input type="number" name="duration_minutes" class="form-control form-control-sm" min="1"
                                    value="<?php echo $editLesson ? (int)$editLesson->duration_minutes : 5; ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input type="checkbox" name="is_required" class="form-check-input" id="reqL" <?php echo !$editLesson || $editLesson->is_required ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="reqL">Obligatoria</label>
                                </div>
                            </div>
                        </div>

                        <div id="field-text" class="lrn-content-fields mb-2">
                            <label class="form-label small">Texto de la lección</label>
                            <textarea name="content_body" class="form-control form-control-sm" rows="4" placeholder="Contenido en texto plano…"><?php echo $editLesson ? htmlspecialchars($editLesson->content_body ?? '') : ''; ?></textarea>
                        </div>

                        <div id="field-video" class="lrn-content-fields mb-2">
                            <label class="form-label small">Enlace de video (YouTube, Vimeo)</label>
                            <input type="url" name="content_url" class="form-control form-control-sm mb-2" placeholder="https://www.youtube.com/watch?v=…"
                                value="<?php echo $editLesson && $editLesson->content_type === 'video' && learning_is_embed_video_url($editLesson->content_url ?? '') ? htmlspecialchars($editLesson->content_url) : ''; ?>">
                            <p class="small text-muted mb-2">O subí un archivo de video (MP4, WebM — máx. 150 MB):</p>
                            <input type="file" name="content_file" class="form-control form-control-sm" accept="video/mp4,video/webm,video/ogg">
                            <?php if ($editLesson && $editLesson->content_type === 'video' && !empty($editLesson->content_url) && !learning_is_embed_video_url($editLesson->content_url)): ?>
                            <p class="small text-success mt-1 mb-0"><i class="fas fa-check"></i> Video actual: <?php echo htmlspecialchars(basename($editLesson->content_url)); ?></p>
                            <?php endif; ?>
                        </div>

                        <div id="field-file" class="lrn-content-fields mb-2">
                            <label class="form-label small">Subir PDF</label>
                            <input type="file" name="content_file" class="form-control form-control-sm" accept="application/pdf,.pdf" disabled data-file-input>
                            <?php if ($editLesson && $editLesson->content_type === 'file' && !empty($editLesson->content_url)): ?>
                            <p class="small text-success mt-1 mb-0"><i class="fas fa-file-pdf"></i> PDF actual: <?php echo htmlspecialchars(basename($editLesson->content_url)); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-success"><?php echo $editLesson ? 'Guardar cambios' : 'Agregar lección'; ?></button>
                            <?php if ($editLesson): ?>
                            <a href="<?php echo URLROOT; ?>/trainingAdmin/courseEdit/<?php echo $cid; ?>#lesson-form" class="btn btn-sm btn-outline-secondary">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header">Cuestionario</div>
            <div class="card-body">
                <?php foreach ($data['questions'] as $q): ?>
                <p class="small border-bottom pb-1 mb-1"><?php echo (int)$q->position; ?>. <?php echo htmlspecialchars($q->question_text); ?></p>
                <?php endforeach; ?>
                <form method="post" action="<?php echo URLROOT; ?>/trainingAdmin/saveQuiz/<?php echo $cid; ?>" class="mt-2">
                    <?php echo csrf_field(); ?>
                    <input type="number" name="position" class="form-control form-control-sm mb-1" value="<?php echo count($data['questions']) + 1; ?>" min="1">
                    <textarea name="question_text" class="form-control form-control-sm mb-1" rows="2" required placeholder="Pregunta"></textarea>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="input-group input-group-sm mb-1">
                        <span class="input-group-text"><input type="radio" name="correct_option" value="<?php echo $i; ?>" <?php echo $i === 0 ? 'checked' : ''; ?>></span>
                        <input type="text" name="option_text[]" class="form-control" placeholder="Opción <?php echo $i + 1; ?>">
                    </div>
                    <?php endfor; ?>
                    <button type="submit" class="btn btn-sm btn-primary">Agregar pregunta</button>
                </form>
            </div>
        </div>
        <div class="card shadow">
            <div class="card-header">Asignación</div>
            <div class="card-body">
                <form method="post" action="<?php echo URLROOT; ?>/trainingAdmin/saveAssignments/<?php echo $cid; ?>">
                    <?php echo csrf_field(); ?>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="assign_company" value="1" class="form-check-input" id="ac" checked>
                        <label for="ac">Toda la empresa activa</label>
                    </div>
                    <?php foreach ($data['areas'] as $ar): ?>
                    <div class="form-check">
                        <input type="checkbox" name="area_ids[]" value="<?php echo (int)$ar->id; ?>" id="a<?php echo $ar->id; ?>">
                        <label for="a<?php echo $ar->id; ?>"><?php echo htmlspecialchars($ar->name); ?></label>
                    </div>
                    <?php endforeach; ?>
                    <label class="form-label small mt-2">Usuarios específicos</label>
                    <select name="user_ids[]" class="form-select form-select-sm" multiple size="4">
                        <?php foreach ($data['users'] as $u): ?>
                        <option value="<?php echo (int)$u->id; ?>"><?php echo htmlspecialchars($u->full_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary w-100 mt-3">Guardar asignaciones</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var sel = document.getElementById('lessonContentType');
    if (!sel) return;
    var panels = {
        text: document.getElementById('field-text'),
        video: document.getElementById('field-video'),
        file: document.getElementById('field-file')
    };
    var videoFile = panels.video ? panels.video.querySelector('input[type=file]') : null;
    var pdfFile = panels.file ? panels.file.querySelector('[data-file-input]') : null;

    function sync() {
        var t = sel.value;
        Object.keys(panels).forEach(function (k) {
            if (!panels[k]) return;
            panels[k].classList.toggle('is-visible', k === t);
        });
        if (videoFile) videoFile.disabled = t !== 'video';
        if (pdfFile) pdfFile.disabled = t !== 'file';
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
<?php endif; ?>
<?php require APPROOT . '/views/inc/footer.php'; ?>
