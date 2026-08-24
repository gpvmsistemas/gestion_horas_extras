<?php $titulo = 'Seguimiento · ' . $d['brand']['name']; require APPROOT . '/views/careers/partials/head.php'; ?>
<main class="container py-5" style="max-width:680px">
  <div class="card careers-card">
    <div class="card-body p-5">
      <span class="badge badge-brand mb-3">Seguimiento privado</span>
      <h1 class="h2"><?= htmlspecialchars($d['application']->title) ?></h1>
      <p class="text-secondary"><?= htmlspecialchars($d['application']->company_name) ?></p>
      <hr>
      <p>Estado actual: <strong><?= htmlspecialchars(ucfirst($d['application']->current_stage)) ?></strong></p>
      <p class="small text-secondary mb-0">Recibida el <?= date('d/m/Y', strtotime($d['application']->created_at)) ?>. Conservá este enlace; no muestra datos personales ni notas internas.</p>
    </div>
  </div>
</main>
<?php require APPROOT . '/views/careers/partials/foot.php'; ?>
