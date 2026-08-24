<?php $titulo = $d['vacancy']->title . ' · ' . $d['brand']['name']; require APPROOT . '/views/careers/partials/head.php'; ?>
<main class="container py-5" style="max-width:1000px">
  <a href="<?= URLROOT ?>/careers/<?= htmlspecialchars($d['org']) ?>" class="text-decoration-none">← Todas las búsquedas</a>
  <div class="row g-4 mt-1">
    <section class="col-lg-7">
      <small class="text-brand fw-semibold"><?= htmlspecialchars($d['vacancy']->company_name) ?><?= $d['vacancy']->branch_name ? ' · ' . htmlspecialchars($d['vacancy']->branch_name) : '' ?></small>
      <h1 class="display-6 fw-bold mt-2"><?= htmlspecialchars($d['vacancy']->title) ?></h1>
      <?php if ($d['vacancy']->closes_at): ?><p class="text-secondary small">Postulaciones abiertas hasta el <?= date('d/m/Y', strtotime($d['vacancy']->closes_at)) ?>.</p><?php endif; ?>
      <div class="lh-lg"><?= nl2br(htmlspecialchars($d['vacancy']->description)) ?></div>
      <?php $criteria = json_decode($d['vacancy']->requirements_json ?? '', true) ?: []; if ($criteria): ?>
      <h2 class="h5 mt-4">Qué buscamos</h2>
      <ul class="lh-lg">
        <?php foreach ($criteria as $c): ?><li><?= htmlspecialchars($c) ?></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
    <aside class="col-lg-5">
      <div class="card careers-card">
        <div class="card-body p-4">
          <h2 class="h4">Postularme</h2>
          <?php if (!empty($_SESSION['flash_error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>
          <?php if (!empty($_SESSION['flash_success'])): ?><div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?><?php if (!empty($_SESSION['career_tracking_url'])): ?><a class="d-block mt-2 fw-bold text-break" href="<?= htmlspecialchars($_SESSION['career_tracking_url']) ?>"><?= htmlspecialchars($_SESSION['career_tracking_url']) ?></a><span class="d-block small mt-1">Guardá este enlace: es la única forma de seguir tu postulación.</span><?php unset($_SESSION['career_tracking_url']); endif; ?></div><?php endif; ?>
          <form method="post" enctype="multipart/form-data" action="<?= URLROOT ?>/careers/apply/<?= rawurlencode($d['vacancy']->slug) ?>">
            <?= csrf_field() ?>
            <input name="website" tabindex="-1" autocomplete="off" class="d-none">
            <label class="form-label">Nombre y apellido</label>
            <input class="form-control mb-3" name="full_name" required maxlength="180">
            <label class="form-label">Email</label>
            <input class="form-control mb-3" type="email" name="email" required>
            <label class="form-label">Teléfono</label>
            <input class="form-control mb-3" name="phone">
            <label class="form-label">CV (PDF o DOCX, máximo 5 MB)</label>
            <input class="form-control mb-3" type="file" name="cv" accept=".pdf,.docx" required>
            <label class="form-label">Control: ¿cuánto es <?= (int)$d['challenge'] ?> + 3?</label>
            <input class="form-control mb-3" type="number" name="challenge" required>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="consent" value="1" id="consent" required>
              <label class="form-check-label small" for="consent">Autorizo el tratamiento por 24 meses y comprendo que puede emplearse IA como asistencia, siempre con revisión humana y sin rechazo automático.</label>
            </div>
            <button class="btn btn-brand w-100">Enviar postulación</button>
          </form>
        </div>
      </div>
    </aside>
  </div>
</main>
<?php require APPROOT . '/views/careers/partials/foot.php'; ?>
