<?php $titulo = 'Trabajá con nosotros · ' . $d['brand']['name']; require APPROOT . '/views/careers/partials/head.php'; ?>
<div class="careers-top careers-hero">
  <div class="container">
    <span class="eyebrow">Oportunidades</span>
    <h1 class="display-5 fw-bold mb-2">Sumate al equipo</h1>
    <p class="lead mb-0 opacity-75">Búsquedas abiertas en <?= htmlspecialchars($d['brand']['name']) ?>.</p>
  </div>
</div>
<main class="container py-5">
  <div class="row g-4">
    <?php foreach ($d['vacancies'] as $v): ?>
    <article class="col-md-6 col-xl-4">
      <div class="card careers-card h-100">
        <div class="card-body p-4 d-flex flex-column">
          <small class="text-secondary"><?= htmlspecialchars($v->company_name) ?><?= $v->branch_name ? ' · ' . htmlspecialchars($v->branch_name) : '' ?></small>
          <h2 class="h4 mt-2"><?= htmlspecialchars($v->title) ?></h2>
          <p class="flex-grow-1 text-secondary"><?= htmlspecialchars(mb_strimwidth(strip_tags($v->description), 0, 170, '…')) ?></p>
          <?php if ($v->closes_at): ?><small class="text-secondary mb-2">Postulaciones hasta el <?= date('d/m/Y', strtotime($v->closes_at)) ?></small><?php endif; ?>
          <a class="btn btn-brand mt-auto" href="<?= URLROOT ?>/careers/vacancy/<?= rawurlencode($v->slug) ?>">Ver búsqueda</a>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
    <?php if (!$d['vacancies']): ?>
    <div class="col-12">
      <div class="card careers-card"><div class="card-body p-5 text-center text-secondary">
        No hay búsquedas abiertas en este momento. Volvé a visitarnos pronto.
      </div></div>
    </div>
    <?php endif; ?>
  </div>
</main>
<?php require APPROOT . '/views/careers/partials/foot.php'; ?>
