<?php $titulo = 'Sumate al equipo · ' . $d['brand']['name']; require APPROOT . '/views/careers/partials/head.php'; ?>
<div class="careers-top careers-hero">
  <div class="container">
    <span class="eyebrow">Postulación espontánea</span>
    <h1 class="display-5 fw-bold mb-2">Sumate al equipo</h1>
    <p class="lead mb-0 opacity-75">¿No encontraste una búsqueda puntual? Dejanos tu CV igual: lo tenemos en cuenta para las próximas incorporaciones.</p>
  </div>
</div>
<main class="container py-5" style="max-width:720px">
  <a href="<?= URLROOT ?>/careers/<?= htmlspecialchars($d['org']) ?>" class="text-decoration-none">← Ver búsquedas abiertas</a>
  <div class="card careers-card mt-3">
    <div class="card-body p-4 p-md-5">
      <?php if (!$d['abierta']): ?>
      <p class="text-secondary mb-0">La recepción de postulaciones espontáneas está pausada por el momento. Mirá las <a href="<?= URLROOT ?>/careers/<?= htmlspecialchars($d['org']) ?>">búsquedas abiertas</a>.</p>
      <?php else: ?>
      <h2 class="h4 mb-3">Contanos de vos</h2>
      <?php if (!empty($_SESSION['flash_error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>
      <?php if (!empty($_SESSION['flash_success'])): ?><div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?><?php if (!empty($_SESSION['career_tracking_url'])): ?><a class="d-block mt-2 fw-bold text-break" href="<?= htmlspecialchars($_SESSION['career_tracking_url']) ?>"><?= htmlspecialchars($_SESSION['career_tracking_url']) ?></a><span class="d-block small mt-1">Guardá este enlace: es la única forma de seguir tu postulación.</span><?php unset($_SESSION['career_tracking_url']); endif; ?></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data" action="<?= URLROOT ?>/careers/unete/<?= htmlspecialchars($d['org']) ?>">
        <?= csrf_field() ?>
        <input name="website" tabindex="-1" autocomplete="off" class="d-none">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nombre y apellido</label>
            <input class="form-control" name="full_name" required maxlength="180">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Teléfono</label>
            <input class="form-control" name="phone">
          </div>
          <div class="col-md-6">
            <label class="form-label">Ciudad</label>
            <select class="form-select" name="ciudad" required>
              <option value="">Elegí tu ciudad…</option>
              <?php foreach ($d['ciudades'] as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Área o puesto de interés <span class="text-secondary fw-normal">(opcional)</span></label>
            <input class="form-control" name="area_interes" maxlength="120" placeholder="Atención al público, administración, farmacia…">
          </div>
          <div class="col-12">
            <label class="form-label">CV (PDF o DOCX, máximo 5 MB)</label>
            <input class="form-control" type="file" name="cv" accept=".pdf,.docx" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Control: ¿cuánto es <?= (int)$d['challenge'] ?> + 3?</label>
            <input class="form-control" type="number" name="challenge" required>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="consent" value="1" id="consent" required>
              <label class="form-check-label small" for="consent">Autorizo el tratamiento por 24 meses y comprendo que puede emplearse IA como asistencia, siempre con revisión humana y sin rechazo automático.</label>
            </div>
          </div>
          <div class="col-12">
            <button class="btn btn-brand w-100">Enviar postulación</button>
          </div>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php require APPROOT . '/views/careers/partials/foot.php'; ?>
