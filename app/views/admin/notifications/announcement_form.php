<?php
require APPROOT . '/views/inc/header.php';
$item = $data['item'];
$isEdit = (bool)$item;
?>
<link rel="stylesheet" href="<?php echo URLROOT; ?>/css/notifications.css">
<script src="<?php echo URLROOT; ?>/js/notifications-admin.js" defer></script>

<div class="mb-4">
    <a href="<?php echo URLROOT; ?>/notificationsAdmin/announcements" class="text-muted small"><i class="fas fa-arrow-left me-1"></i>Avisos</a>
    <h1 class="h4 mb-0 mt-1"><?php echo $isEdit ? 'Editar aviso' : 'Nuevo aviso'; ?></h1>
</div>

<form method="post" enctype="multipart/form-data" action="<?php echo URLROOT; ?>/notificationsAdmin/announcementForm<?php echo $isEdit ? '/' . (int)$item->id : ''; ?>">
    <?php echo csrf_field(); ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="title" id="notifTitle" class="form-control" required value="<?php echo htmlspecialchars($item->title ?? ''); ?>" placeholder="Bienvenido, &lt;nombre&gt;">
                    </div>
                    <?php require APPROOT . '/views/admin/notifications/partials/placeholders_help.php'; ?>
                    <div class="mb-3">
                        <label class="form-label">Contenido</label>
                        <textarea name="body" id="notifBody" class="form-control" rows="8" required><?php echo htmlspecialchars($item->body ?? ''); ?></textarea>
                        <div class="form-text">Podés usar HTML básico y variables como &lt;nombre&gt;.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Imagen (opcional)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <?php if (!empty($item->image_path)): ?>
                        <img src="<?php echo announcement_image_stream_url((int)$item->id, true); ?>" alt="" class="notif-preview-img mt-2">
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Video (opcional)</label>
                        <input type="file" name="video" class="form-control" accept="video/mp4,video/webm">
                        <div class="form-text">MP4 o WebM, máximo 50MB. Si el aviso tiene video, se muestra en lugar de la imagen.</div>
                        <?php if (!empty($item->video_path)): ?>
                        <video src="<?php echo announcement_video_stream_url((int)$item->id, true); ?>" class="announcement-modal-video mt-2" controls preload="metadata" style="max-width:320px;"></video>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="remove_video" value="1" id="removeVideoChk">
                            <label class="form-check-label" for="removeVideoChk">Quitar el video actual</label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label">URL del botón</label>
                            <input type="url" name="link_url" class="form-control" value="<?php echo htmlspecialchars($item->link_url ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Etiqueta botón</label>
                            <input type="text" name="link_label" class="form-control" value="<?php echo htmlspecialchars($item->link_label ?? 'Ver más'); ?>">
                        </div>
                    </div>
                </div>
            </div>
            <?php
            $targeting = notification_admin_targeting_data();
            $companies = $targeting['companies'];
            $areas = $targeting['areas'];
            $users = $targeting['users'];
            $picker_users = $targeting['picker_users'];
            $selected = $data['selected'];
            require APPROOT . '/views/admin/notifications/partials/target_form.php';
            ?>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Vigencia</div>
                <div class="card-body">
                    <div class="btn-group btn-group-sm w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="date_preset" id="presetNone" value="" checked>
                        <label class="btn btn-outline-secondary" for="presetNone">Manual</label>
                        <input type="radio" class="btn-check" name="date_preset" id="presetWeek" value="week">
                        <label class="btn btn-outline-secondary" for="presetWeek">Esta semana</label>
                        <input type="radio" class="btn-check" name="date_preset" id="presetMonth" value="month">
                        <label class="btn btn-outline-secondary" for="presetMonth">Este mes</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Desde</label>
                        <input type="datetime-local" name="starts_at" class="form-control"
                            value="<?php echo $item ? date('Y-m-d\TH:i', strtotime($item->starts_at)) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hasta</label>
                        <input type="datetime-local" name="ends_at" class="form-control"
                            value="<?php echo $item ? date('Y-m-d\TH:i', strtotime($item->ends_at)) : ''; ?>">
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Visualización</div>
                <div class="card-body">
                    <?php
                    $modes = ['once' => 'Una vez (al cerrar no vuelve)', 'sessions_3' => '3 inicios de sesión', 'always' => 'Siempre (hasta vencimiento)'];
                    $cur = $item->display_mode ?? 'once';
                    foreach ($modes as $val => $lbl):
                    ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="display_mode" id="dm<?php echo $val; ?>" value="<?php echo $val; ?>" <?php echo $cur === $val ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="dm<?php echo $val; ?>"><?php echo $lbl; ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" <?php echo !$item || (int)$item->is_active ? 'checked' : ''; ?>>
                        <label for="is_active" class="form-check-label">Aviso activo</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="send_email" value="1" class="form-check-input" id="send_email" <?php echo $item && (int)$item->send_email ? 'checked' : ''; ?>>
                        <label for="send_email" class="form-check-label">Enviar correo a destinatarios</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Guardar aviso</button>
        </div>
    </div>
</form>

<?php require APPROOT . '/views/inc/footer.php'; ?>
