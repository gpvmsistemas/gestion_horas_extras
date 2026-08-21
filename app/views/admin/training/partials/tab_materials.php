<?php /** @var $c,$cid,$data,$enrich */ ?>
<?php if (!$enrich): ?>
<div class="alert alert-warning">Ejecutá <code>migration_learning_enrich.sql</code> para habilitar materiales de apoyo.</div>
<?php return; endif; ?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow">
            <div>Agregar material</div>
            <div class="card-body">
                <form method="post" action="<?php echo URLROOT; ?>/trainingAdmin/saveResource/<?php echo $cid; ?>" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <div>
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-control" required placeholder="Ej. Plantilla Excel ventas">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Descripción (opcional)</label>
                        <input type="text" name="description" class="form-control" placeholder="Breve descripción">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Vincular a lección</label>
                        <select name="lesson_id" class="form-select">
                            <option value="">Todo el curso (general)</option>
                            <?php foreach ($data['lessons'] as $l): ?>
                            <option value="<?php echo (int)$l->id; ?>">Lección <?php echo (int)$l->position; ?>: <?php echo htmlspecialchars($l->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Tipo</label>
                        <select name="resource_type" class="form-select">
                            <option value="document">Documento</option>
                            <option value="spreadsheet">Excel / hoja de cálculo</option>
                            <option value="pdf">PDF</option>
                            <option value="link">Enlace web</option>
                            <option value="video">Video (enlace)</option>
                            <option value="archive">ZIP / comprimido</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Archivo</label>
                        <input type="file" name="resource_file" class="form-control" accept=".pdf,.xls,.xlsx,.doc,.docx,.pptx,.zip,.csv,.mp4,.png,.jpg">
                        <p class="small text-muted mt-1">PDF, Excel, Word, PPT, ZIP, imágenes — máx. 40 MB</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">O enlace externo</label>
                        <input type="url" name="external_url" class="form-control" placeholder="https://…">
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_visible" class="form-check-input" id="rv" checked>
                        <label for="rv">Visible para empleados</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Subir material</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow">
            <div class="card-header">Biblioteca del curso (<?php echo count($data['resources']); ?>)</div>
            <div class="card-body p-0">
                <?php if (empty($data['resources'])): ?>
                <p class="p-4 text-muted mb-0">Subí plantillas Excel, PDFs de ayuda, enlaces a documentación, etc.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Material</th><th>Tipo</th><th>Lección</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($data['resources'] as $r):
                            $url = learning_resource_url($r, true);
                        ?>
                        <tr>
                            <td>
                                <i class="fas <?php echo learning_resource_icon($r->resource_type); ?> me-1 text-muted"></i>
                                <strong><?php echo htmlspecialchars($r->title); ?></strong>
                                <?php if ($r->file_size): ?><br><span class="small text-muted"><?php echo learning_format_bytes($r->file_size); ?></span><?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($r->resource_type); ?></span></td>
                            <td class="small"><?php echo $r->lesson_title ? htmlspecialchars($r->lesson_title) : 'General'; ?></td>
                            <td class="text-nowrap">
                                <?php if ($url): ?><a href="<?php echo htmlspecialchars($url); ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="fas fa-external-link-alt"></i></a><?php endif; ?>
                                <form method="post" action="<?php echo URLROOT; ?>/trainingAdmin/deleteResource/<?php echo $cid; ?>/<?php echo (int)$r->id; ?>" class="d-inline" onsubmit="return confirm('Eliminar?')">
                                    <?php echo csrf_field(); ?>
                                    <button class="btn btn-sm btn-outline-danger" aria-label="Eliminar material" title="Eliminar material"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
